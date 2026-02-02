<?php
require_once 'config.php';
Auth::requireLogin();

$pagina_atual = 'titulo';
$db = Database::getConnectionBV();

// Obter número do título da URL
$numero_titulo = $_GET['id'] ?? '';
if (empty($numero_titulo)) {
    header('Location: index');
    exit;
}

// Buscar dados do título
$stmt = $db->prepare("
    SELECT bv.*, u.nome as nome_atendente
    FROM boas_vindas bv
    LEFT JOIN usuarios u ON bv.usuario_id = u.id
    WHERE bv.numero_titulo = :numero_titulo
");
$stmt->execute([':numero_titulo' => $numero_titulo]);
$titulo = $stmt->fetch();

// Se não encontrou na tabela boas_vindas, buscar na API (tabela titulos)
$titulo_api = null;
if (!$titulo) {
    try {
        $db_api = Database::getConnectionAPI();
        $stmt_api = $db_api->prepare("SELECT * FROM titulos WHERE numero_titulo = :numero_titulo");
        $stmt_api->execute([':numero_titulo' => $numero_titulo]);
        $titulo_api = $stmt_api->fetch();

        if ($titulo_api) {
            // Criar registro na tabela boas_vindas
            $stmt_insert = $db->prepare("
                INSERT INTO boas_vindas (numero_titulo, usuario_id, status, nome_cliente, documento_cliente, telefone, email, data_venda, tipo_titulo, valor_total, forma_pagamento, promotor)
                VALUES (:numero_titulo, :usuario_id, 'pendente', :nome_cliente, :documento_cliente, :telefone, :email, :data_venda, :tipo_titulo, :valor_total, :forma_pagamento, :promotor)
            ");
            $stmt_insert->execute([
                ':numero_titulo' => $titulo_api['numero_titulo'],
                ':usuario_id' => Auth::getUserId(),
                ':nome_cliente' => $titulo_api['nome_titular'] ?? null,
                ':documento_cliente' => $titulo_api['documento_titular'] ?? null,
                ':telefone' => $titulo_api['telefone_residencial'] ?? null,
                ':email' => $titulo_api['email'] ?? null,
                ':data_venda' => $titulo_api['data_primeira_venda'] ?? null,
                ':tipo_titulo' => $titulo_api['nome_produto_atual'] ?? null,
                ':valor_total' => $titulo_api['valor_total_plano'] ?? null,
                ':forma_pagamento' => $titulo_api['forma_pagamento'] ?? null,
                ':promotor' => $titulo_api['promotor'] ?? null
            ]);

            // Buscar novamente
            $stmt->execute([':numero_titulo' => $numero_titulo]);
            $titulo = $stmt->fetch();
        }
    } catch (Exception $e) {
        // Silenciar erro
    }
}

if (!$titulo) {
    $_SESSION['erro'] = 'Título não encontrado';
    header('Location: index');
    exit;
}

// Buscar interações já salvas
$stmt_interacoes = $db->prepare("
    SELECT * FROM titulo_interacoes WHERE boas_vindas_id = :boas_vindas_id
");
try {
    $stmt_interacoes->execute([':boas_vindas_id' => $titulo['id']]);
    $interacoes_raw = $stmt_interacoes->fetchAll();
} catch (Exception $e) {
    $interacoes_raw = [];
}
$interacoes = [];
foreach ($interacoes_raw as $int) {
    $interacoes[$int['etapa_codigo']] = $int;
}

// Buscar etapas do checklist
$stmt_etapas = $db->query("SELECT * FROM checklist_etapas WHERE ativo = 1 ORDER BY ordem ASC");
try {
    $etapas = $stmt_etapas->fetchAll();
} catch (Exception $e) {
    $etapas = [];
}

// Agrupar etapas por seção
$secoes = [
    'preparacao' => ['titulo' => 'Preparação antes da Ligação', 'icone' => 'bi-gear', 'prefixo' => 'prep_'],
    'abertura' => ['titulo' => 'Abertura da Ligação', 'icone' => 'bi-telephone-outbound', 'prefixo' => 'abert_'],
    'validacao' => ['titulo' => 'Validação do Titular', 'icone' => 'bi-shield-check', 'prefixo' => 'valid_'],
    'portal' => ['titulo' => 'Informações sobre o Portal', 'icone' => 'bi-globe', 'prefixo' => 'portal_'],
    'atendimento' => ['titulo' => 'Validação do Atendimento', 'icone' => 'bi-star', 'prefixo' => 'atend_'],
    'financeiro' => ['titulo' => 'Validação Financeira', 'icone' => 'bi-currency-dollar', 'prefixo' => 'financ_'],
    'informacoes' => ['titulo' => 'Informações Importantes', 'icone' => 'bi-info-circle', 'prefixo' => 'info_'],
    'duvidas' => ['titulo' => 'Dúvidas Gerais', 'icone' => 'bi-question-circle', 'prefixo' => 'duvidas_'],
    'ofertas' => ['titulo' => 'Oferta de Serviços', 'icone' => 'bi-gift', 'prefixo' => 'oferta_'],
    'encerramento' => ['titulo' => 'Encerramento', 'icone' => 'bi-hand-thumbs-up', 'prefixo' => 'encerr_'],
    'registro' => ['titulo' => 'Registro Pós-Ligação', 'icone' => 'bi-journal-check', 'prefixo' => 'reg_']
];

// Buscar logs de atividades
$stmt_logs = $db->prepare("
    SELECT tl.*, u.nome as nome_usuario
    FROM titulo_logs tl
    LEFT JOIN usuarios u ON tl.usuario_id = u.id
    WHERE tl.boas_vindas_id = :boas_vindas_id
    ORDER BY tl.criado_em DESC
    LIMIT 50
");
try {
    $stmt_logs->execute([':boas_vindas_id' => $titulo['id']]);
    $logs = $stmt_logs->fetchAll();
} catch (Exception $e) {
    $logs = [];
}

// Buscar tentativas de contato
$stmt_tentativas = $db->prepare("
    SELECT tc.*, u.nome as nome_usuario
    FROM tentativas_contato tc
    LEFT JOIN usuarios u ON tc.usuario_id = u.id
    WHERE tc.boas_vindas_id = :boas_vindas_id
    ORDER BY tc.criado_em DESC
");
$stmt_tentativas->execute([':boas_vindas_id' => $titulo['id']]);
$tentativas = $stmt_tentativas->fetchAll();

// Buscar configurações
$stmt_config = $db->query("SELECT chave, valor FROM configuracoes");
$configs_raw = $stmt_config->fetchAll();
$configs = [];
foreach ($configs_raw as $c) {
    $configs[$c['chave']] = $c['valor'];
}

// Calcular progresso do checklist
$total_obrigatorios = 0;
$completos_obrigatorios = 0;
foreach ($etapas as $etapa) {
    if ($etapa['obrigatorio']) {
        $total_obrigatorios++;
        if (isset($interacoes[$etapa['codigo']]) && $interacoes[$etapa['codigo']]['valor_checkbox']) {
            $completos_obrigatorios++;
        }
    }
}
$progresso = $total_obrigatorios > 0 ? round(($completos_obrigatorios / $total_obrigatorios) * 100) : 0;

// Função para substituir variáveis no template
function substituirVariaveis($template, $titulo, $configs) {
    if (!$template) return '';

    $substituicoes = [
        '{NOME}' => $titulo['nome_cliente'] ?? '',
        '{NUMERO_TITULO}' => $titulo['numero_titulo'] ?? '',
        '{DOCUMENTO}' => formatarDocumento($titulo['documento_cliente'] ?? ''),
        '{CPF_ULTIMOS_4}' => substr(preg_replace('/[^0-9]/', '', $titulo['documento_cliente'] ?? ''), -4),
        '{TELEFONE}' => formatarTelefone($titulo['telefone'] ?? ''),
        '{EMAIL}' => $titulo['email'] ?? '',
        '{DATA_VENDA}' => formatarData($titulo['data_venda'] ?? '', 'd/m/Y'),
        '{TIPO_TITULO}' => $titulo['tipo_titulo'] ?? '',
        '{VALOR}' => formatarMoeda($titulo['valor_total'] ?? 0),
        '{FORMA_PAGAMENTO}' => $titulo['forma_pagamento'] ?? '',
        '{PROMOTOR}' => $titulo['promotor'] ?? '',
        '{ATENDENTE}' => Auth::getUserName(),
        '{PORTAL_URL}' => $configs['portal_url'] ?? '',
        '{WHATSAPP}' => formatarTelefone($configs['whatsapp_numero'] ?? ''),
        '{TELEFONE_ATENDIMENTO}' => formatarTelefone($configs['telefone_atendimento'] ?? ''),
        '{EMAIL_ATENDIMENTO}' => $configs['email_atendimento'] ?? '',
        '{DIA_VENCIMENTO}' => '10'
    ];

    return str_replace(array_keys($substituicoes), array_values($substituicoes), $template);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Título <?= htmlspecialchars($titulo['numero_titulo']) ?> - Boas-Vindas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #1e3c72;
            --secondary-color: #2a5298;
        }
        body {
            background: #f5f7fa;
            font-size: 14px;
        }
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }
        .card-info {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            height: 100%;
        }
        .card-info .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 15px 20px;
        }
        .info-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .info-value {
            font-weight: 500;
            color: #333;
        }
        .checklist-section {
            background: white;
            border-radius: 12px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .checklist-section-header {
            background: #f8f9fa;
            padding: 12px 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
        }
        .checklist-section-header:hover {
            background: #e9ecef;
        }
        .checklist-section-header h6 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .checklist-section-body {
            padding: 15px 20px;
        }
        .checklist-item {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            background: #f8f9fa;
            transition: all 0.2s;
        }
        .checklist-item:hover {
            background: #e9ecef;
        }
        .checklist-item.completed {
            background: #d4edda;
        }
        .checklist-item .form-check {
            margin-bottom: 0;
        }
        .checklist-item .form-check-label {
            cursor: pointer;
            width: 100%;
        }
        .checklist-item .item-description {
            font-size: 12px;
            color: #666;
            margin-left: 25px;
            margin-top: 3px;
        }
        .script-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            font-size: 13px;
            position: relative;
        }
        .script-box .copy-btn {
            position: absolute;
            top: 8px;
            right: 8px;
        }
        .script-box pre {
            margin: 0;
            white-space: pre-wrap;
            font-family: inherit;
        }
        .field-input {
            margin-top: 10px;
            margin-left: 25px;
        }
        .progress-bar-custom {
            height: 10px;
            border-radius: 5px;
        }
        .status-badge {
            font-size: 14px;
            padding: 8px 15px;
        }
        .log-item {
            padding: 8px 12px;
            border-left: 3px solid #dee2e6;
            margin-bottom: 8px;
            background: #f8f9fa;
            font-size: 12px;
        }
        .log-item.checkbox_marcado {
            border-left-color: #28a745;
        }
        .log-item.texto_salvo {
            border-left-color: #17a2b8;
        }
        .log-item.ligacao {
            border-left-color: #ffc107;
        }
        .tentativa-item {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .rating-stars {
            display: flex;
            gap: 5px;
        }
        .rating-stars i {
            cursor: pointer;
            color: #ddd;
            font-size: 20px;
            transition: color 0.2s;
        }
        .rating-stars i.active {
            color: #ffc107;
        }
        .rating-stars i:hover {
            color: #ffc107;
        }
        .saving-indicator {
            display: none;
            color: #17a2b8;
            font-size: 12px;
        }
        .saved-indicator {
            display: none;
            color: #28a745;
            font-size: 12px;
        }
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
        }
        .sticky-sidebar {
            position: sticky;
            top: 80px;
        }
        @media (max-width: 991px) {
            .sticky-sidebar {
                position: relative;
                top: 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <a href="index" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i> Voltar ao Dashboard
                        </a>
                        <h4 class="mb-0 mt-2">
                            <i class="bi bi-ticket-perforated"></i>
                            Título: <strong><?= htmlspecialchars($titulo['numero_titulo']) ?></strong>
                        </h4>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge <?= $titulo['status'] === 'concluido' ? 'bg-success' : ($titulo['status'] === 'em_andamento' ? 'bg-info' : 'bg-warning') ?> status-badge">
                            <?= ucfirst(str_replace('_', ' ', $titulo['status'])) ?>
                        </span>
                        <?php if ($titulo['status'] !== 'concluido'): ?>
                        <button class="btn btn-success" onclick="concluirAtendimento()">
                            <i class="bi bi-check-lg"></i> Concluir Atendimento
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card-info p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Progresso do Checklist</span>
                        <span class="fw-bold"><?= $progresso ?>%</span>
                    </div>
                    <div class="progress progress-bar-custom">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progresso ?>%"></div>
                    </div>
                    <small class="text-muted"><?= $completos_obrigatorios ?> de <?= $total_obrigatorios ?> itens obrigatórios</small>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column - Client Info -->
            <div class="col-lg-4 mb-4">
                <div class="sticky-sidebar">
                    <!-- Dados do Cliente -->
                    <div class="card-info mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-person-badge"></i> Dados do Cliente</h6>
                        </div>
                        <div class="card-body p-3">
                            <div class="info-item">
                                <div class="info-label">Nome</div>
                                <div class="info-value"><?= htmlspecialchars($titulo['nome_cliente'] ?? '-') ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">CPF/CNPJ</div>
                                <div class="info-value"><?= formatarDocumento($titulo['documento_cliente']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Telefone</div>
                                <div class="info-value">
                                    <a href="tel:<?= preg_replace('/[^0-9]/', '', $titulo['telefone'] ?? '') ?>" class="text-decoration-none">
                                        <?= formatarTelefone($titulo['telefone']) ?>
                                    </a>
                                    <button class="btn btn-sm btn-outline-success ms-2" onclick="copiarTexto('<?= preg_replace('/[^0-9]/', '', $titulo['telefone'] ?? '') ?>')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">E-mail</div>
                                <div class="info-value"><?= htmlspecialchars($titulo['email'] ?? '-') ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Data da Venda</div>
                                <div class="info-value"><?= formatarData($titulo['data_venda'], 'd/m/Y H:i') ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Tipo de Título</div>
                                <div class="info-value"><?= htmlspecialchars($titulo['tipo_titulo'] ?? '-') ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Valor Total</div>
                                <div class="info-value text-success fw-bold"><?= formatarMoeda($titulo['valor_total']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Forma de Pagamento</div>
                                <div class="info-value"><?= htmlspecialchars($titulo['forma_pagamento'] ?? '-') ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Consultor/Promotor</div>
                                <div class="info-value"><?= htmlspecialchars($titulo['promotor'] ?? '-') ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Registrar Tentativa -->
                    <div class="card-info mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-telephone-plus"></i> Registrar Tentativa</h6>
                        </div>
                        <div class="card-body p-3">
                            <div class="mb-3">
                                <label class="form-label">Tipo de Tentativa</label>
                                <select class="form-select" id="tipo_tentativa">
                                    <option value="ligacao">Ligação</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="email">E-mail</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Resultado</label>
                                <select class="form-select" id="resultado_tentativa">
                                    <option value="atendeu">Atendeu</option>
                                    <option value="nao_atendeu">Não atendeu</option>
                                    <option value="caixa_postal">Caixa postal</option>
                                    <option value="whatsapp_enviado">WhatsApp enviado</option>
                                    <option value="email_enviado">E-mail enviado</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Telefone usado</label>
                                <input type="text" class="form-control" id="telefone_tentativa" value="<?= htmlspecialchars($titulo['telefone'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Observação</label>
                                <textarea class="form-control" id="obs_tentativa" rows="2"></textarea>
                            </div>
                            <button class="btn btn-primary w-100" onclick="registrarTentativa()">
                                <i class="bi bi-plus-lg"></i> Registrar Tentativa
                            </button>
                        </div>
                    </div>

                    <!-- Histórico de Tentativas -->
                    <div class="card-info">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-clock-history"></i> Tentativas</h6>
                            <span class="badge bg-light text-dark"><?= count($tentativas) ?></span>
                        </div>
                        <div class="card-body p-3" style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($tentativas)): ?>
                                <p class="text-muted text-center mb-0">Nenhuma tentativa registrada</p>
                            <?php else: ?>
                                <?php foreach ($tentativas as $tentativa): ?>
                                <div class="tentativa-item">
                                    <div class="d-flex justify-content-between">
                                        <span class="badge <?= $tentativa['resultado'] === 'atendeu' ? 'bg-success' : ($tentativa['resultado'] === 'nao_atendeu' ? 'bg-warning' : 'bg-info') ?>">
                                            <?= ucfirst($tentativa['tipo_tentativa']) ?>
                                        </span>
                                        <small class="text-muted"><?= formatarData($tentativa['criado_em']) ?></small>
                                    </div>
                                    <div class="mt-2">
                                        <strong><?= ucfirst(str_replace('_', ' ', $tentativa['resultado'])) ?></strong>
                                    </div>
                                    <?php if ($tentativa['observacao']): ?>
                                    <small class="text-muted"><?= htmlspecialchars($tentativa['observacao']) ?></small>
                                    <?php endif; ?>
                                    <small class="d-block text-muted">Por: <?= htmlspecialchars($tentativa['nome_usuario']) ?></small>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Checklist -->
            <div class="col-lg-8">
                <?php
                // Verificar se temos etapas do checklist
                if (empty($etapas)):
                ?>
                <div class="alert alert-warning">
                    <h5><i class="bi bi-exclamation-triangle"></i> Checklist não configurado</h5>
                    <p>Execute o script SQL <code>update_database_v2.sql</code> para criar as etapas do checklist.</p>
                </div>

                <!-- Checklist Básico sem banco de dados -->
                <div class="checklist-section">
                    <div class="checklist-section-header">
                        <h6><i class="bi bi-list-check"></i> Checklist Básico</h6>
                    </div>
                    <div class="checklist-section-body">
                        <p class="text-muted">Use este checklist básico enquanto a configuração completa não está disponível.</p>

                        <!-- Seções básicas -->
                        <?php foreach ($secoes as $secao_key => $secao): ?>
                        <div class="mb-3">
                            <h6 class="text-muted"><i class="bi <?= $secao['icone'] ?>"></i> <?= $secao['titulo'] ?></h6>
                            <div class="checklist-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="basico_<?= $secao_key ?>">
                                    <label class="form-check-label" for="basico_<?= $secao_key ?>">
                                        <?= $secao['titulo'] ?> - Concluído
                                    </label>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>

                <?php foreach ($secoes as $secao_key => $secao): ?>
                    <?php
                    // Filtrar etapas desta seção
                    $etapas_secao = array_filter($etapas, function($e) use ($secao) {
                        return strpos($e['codigo'], $secao['prefixo']) === 0;
                    });
                    if (empty($etapas_secao)) continue;

                    // Calcular progresso da seção
                    $total_secao = count($etapas_secao);
                    $completos_secao = 0;
                    foreach ($etapas_secao as $etapa) {
                        if (isset($interacoes[$etapa['codigo']]) && $interacoes[$etapa['codigo']]['valor_checkbox']) {
                            $completos_secao++;
                        }
                    }
                    ?>
                    <div class="checklist-section" id="secao_<?= $secao_key ?>">
                        <div class="checklist-section-header" data-bs-toggle="collapse" data-bs-target="#collapse_<?= $secao_key ?>">
                            <h6>
                                <i class="bi <?= $secao['icone'] ?>"></i>
                                <?= $secao['titulo'] ?>
                            </h6>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge <?= $completos_secao === $total_secao ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $completos_secao ?>/<?= $total_secao ?>
                                </span>
                                <i class="bi bi-chevron-down"></i>
                            </div>
                        </div>
                        <div class="collapse show" id="collapse_<?= $secao_key ?>">
                            <div class="checklist-section-body">
                                <?php foreach ($etapas_secao as $etapa):
                                    $valor_checkbox = isset($interacoes[$etapa['codigo']]) ? $interacoes[$etapa['codigo']]['valor_checkbox'] : 0;
                                    $valor_texto = isset($interacoes[$etapa['codigo']]) ? $interacoes[$etapa['codigo']]['valor_texto'] : '';
                                    $valor_numero = isset($interacoes[$etapa['codigo']]) ? $interacoes[$etapa['codigo']]['valor_numero'] : '';
                                    $script_preenchido = substituirVariaveis($etapa['script_template'], $titulo, $configs);
                                    $opcoes = $etapa['opcoes_campo'] ? json_decode($etapa['opcoes_campo'], true) : [];
                                ?>
                                <div class="checklist-item <?= $valor_checkbox ? 'completed' : '' ?>" data-codigo="<?= $etapa['codigo'] ?>">
                                    <div class="form-check">
                                        <input class="form-check-input checklist-checkbox"
                                               type="checkbox"
                                               id="check_<?= $etapa['codigo'] ?>"
                                               data-codigo="<?= $etapa['codigo'] ?>"
                                               <?= $valor_checkbox ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="check_<?= $etapa['codigo'] ?>">
                                            <?= htmlspecialchars($etapa['titulo']) ?>
                                            <?php if ($etapa['obrigatorio']): ?>
                                            <span class="text-danger">*</span>
                                            <?php endif; ?>
                                        </label>
                                        <span class="saving-indicator ms-2" id="saving_<?= $etapa['codigo'] ?>">
                                            <i class="bi bi-arrow-repeat spin"></i> Salvando...
                                        </span>
                                        <span class="saved-indicator ms-2" id="saved_<?= $etapa['codigo'] ?>">
                                            <i class="bi bi-check"></i> Salvo
                                        </span>
                                    </div>

                                    <?php if ($etapa['descricao']): ?>
                                    <div class="item-description"><?= htmlspecialchars($etapa['descricao']) ?></div>
                                    <?php endif; ?>

                                    <?php if ($etapa['tipo_campo'] === 'textarea'): ?>
                                    <div class="field-input">
                                        <textarea class="form-control form-control-sm checklist-textarea"
                                                  data-codigo="<?= $etapa['codigo'] ?>"
                                                  rows="2"
                                                  placeholder="Digite aqui..."><?= htmlspecialchars($valor_texto) ?></textarea>
                                    </div>
                                    <?php elseif ($etapa['tipo_campo'] === 'texto'): ?>
                                    <div class="field-input">
                                        <input type="text" class="form-control form-control-sm checklist-texto"
                                               data-codigo="<?= $etapa['codigo'] ?>"
                                               value="<?= htmlspecialchars($valor_texto) ?>"
                                               placeholder="Digite aqui...">
                                    </div>
                                    <?php elseif ($etapa['tipo_campo'] === 'numero'): ?>
                                    <div class="field-input">
                                        <input type="number" class="form-control form-control-sm checklist-numero"
                                               data-codigo="<?= $etapa['codigo'] ?>"
                                               value="<?= htmlspecialchars($valor_numero) ?>"
                                               placeholder="0">
                                    </div>
                                    <?php elseif ($etapa['tipo_campo'] === 'select' && isset($opcoes['opcoes'])): ?>
                                    <div class="field-input">
                                        <select class="form-select form-select-sm checklist-select" data-codigo="<?= $etapa['codigo'] ?>">
                                            <option value="">Selecione...</option>
                                            <?php foreach ($opcoes['opcoes'] as $opt): ?>
                                            <option value="<?= htmlspecialchars($opt) ?>" <?= $valor_texto === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php elseif ($etapa['tipo_campo'] === 'rating'): ?>
                                    <div class="field-input">
                                        <div class="rating-stars" data-codigo="<?= $etapa['codigo'] ?>">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star-fill <?= $i <= (int)$valor_numero ? 'active' : '' ?>" data-value="<?= $i ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($script_preenchido): ?>
                                    <div class="script-box mt-2">
                                        <button class="btn btn-sm btn-outline-warning copy-btn" onclick="copiarScript(this)">
                                            <i class="bi bi-clipboard"></i> Copiar
                                        </button>
                                        <strong><i class="bi bi-chat-quote"></i> Script:</strong>
                                        <pre class="mt-2 mb-0"><?= htmlspecialchars($script_preenchido) ?></pre>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <!-- Observações Gerais -->
                <div class="card-info mt-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-journal-text"></i> Observações Gerais</h6>
                    </div>
                    <div class="card-body p-3">
                        <textarea class="form-control" id="observacoes_gerais" rows="4"
                                  placeholder="Adicione observações importantes sobre este atendimento..."><?= htmlspecialchars($titulo['observacoes'] ?? '') ?></textarea>
                        <div class="mt-2 text-end">
                            <small class="text-muted" id="obs_status"></small>
                        </div>
                    </div>
                </div>

                <!-- Log de Atividades -->
                <div class="card-info mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-activity"></i> Log de Atividades</h6>
                        <span class="badge bg-light text-dark"><?= count($logs) ?></span>
                    </div>
                    <div class="card-body p-3" style="max-height: 400px; overflow-y: auto;" id="logs_container">
                        <?php if (empty($logs)): ?>
                            <p class="text-muted text-center mb-0">Nenhuma atividade registrada</p>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <div class="log-item <?= $log['tipo_log'] ?>">
                                <div class="d-flex justify-content-between">
                                    <strong><?= htmlspecialchars($log['nome_usuario']) ?></strong>
                                    <small class="text-muted"><?= formatarData($log['criado_em']) ?></small>
                                </div>
                                <div>
                                    <?php if ($log['tipo_log'] === 'checkbox_marcado'): ?>
                                        <i class="bi bi-check-square text-success"></i>
                                    <?php elseif ($log['tipo_log'] === 'texto_salvo'): ?>
                                        <i class="bi bi-pencil text-info"></i>
                                    <?php else: ?>
                                        <i class="bi bi-info-circle"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($log['etapa_codigo'] ?? $log['tipo_log']) ?>
                                    <?php if ($log['valor_novo']): ?>
                                        <span class="text-muted">: <?= htmlspecialchars(substr($log['valor_novo'], 0, 100)) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container">
        <div class="toast" id="toast" role="alert">
            <div class="toast-body" id="toast-body"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
    const boasVindasId = <?= $titulo['id'] ?>;
    let saveTimeout = null;

    // Mostrar toast
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        const toastBody = document.getElementById('toast-body');
        toast.className = 'toast bg-' + type + ' text-white';
        toastBody.textContent = message;
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
    }

    // Copiar texto
    function copiarTexto(texto) {
        navigator.clipboard.writeText(texto).then(() => {
            showToast('Copiado!', 'success');
        });
    }

    // Copiar script
    function copiarScript(btn) {
        const pre = btn.parentElement.querySelector('pre');
        navigator.clipboard.writeText(pre.textContent).then(() => {
            btn.innerHTML = '<i class="bi bi-check"></i> Copiado!';
            setTimeout(() => {
                btn.innerHTML = '<i class="bi bi-clipboard"></i> Copiar';
            }, 2000);
        });
    }

    // Salvar interação
    function salvarInteracao(codigo, checkbox = null, texto = null, numero = null) {
        const savingEl = document.getElementById('saving_' + codigo);
        const savedEl = document.getElementById('saved_' + codigo);

        if (savingEl) savingEl.style.display = 'inline';
        if (savedEl) savedEl.style.display = 'none';

        $.ajax({
            url: 'api_titulo_interacao.php',
            method: 'POST',
            data: {
                boas_vindas_id: boasVindasId,
                etapa_codigo: codigo,
                valor_checkbox: checkbox,
                valor_texto: texto,
                valor_numero: numero
            },
            success: function(response) {
                if (savingEl) savingEl.style.display = 'none';
                if (savedEl) {
                    savedEl.style.display = 'inline';
                    setTimeout(() => { savedEl.style.display = 'none'; }, 2000);
                }

                // Atualizar visual do item
                const item = document.querySelector('[data-codigo="' + codigo + '"]');
                if (item && checkbox !== null) {
                    if (checkbox) {
                        item.classList.add('completed');
                    } else {
                        item.classList.remove('completed');
                    }
                }

                // Atualizar progresso
                atualizarProgresso();
            },
            error: function() {
                if (savingEl) savingEl.style.display = 'none';
                showToast('Erro ao salvar', 'danger');
            }
        });
    }

    // Atualizar progresso
    function atualizarProgresso() {
        const checkboxes = document.querySelectorAll('.checklist-checkbox');
        let total = 0;
        let completos = 0;

        checkboxes.forEach(cb => {
            total++;
            if (cb.checked) completos++;
        });

        const progresso = total > 0 ? Math.round((completos / total) * 100) : 0;
        document.querySelector('.progress-bar').style.width = progresso + '%';
    }

    // Event listeners para checkboxes
    document.querySelectorAll('.checklist-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            salvarInteracao(this.dataset.codigo, this.checked ? 1 : 0, null, null);
        });
    });

    // Event listeners para textareas (auto-save on blur)
    document.querySelectorAll('.checklist-textarea, .checklist-texto').forEach(input => {
        input.addEventListener('blur', function() {
            salvarInteracao(this.dataset.codigo, null, this.value, null);
        });
    });

    // Event listeners para números
    document.querySelectorAll('.checklist-numero').forEach(input => {
        input.addEventListener('blur', function() {
            salvarInteracao(this.dataset.codigo, null, null, this.value);
        });
    });

    // Event listeners para selects
    document.querySelectorAll('.checklist-select').forEach(select => {
        select.addEventListener('change', function() {
            salvarInteracao(this.dataset.codigo, null, this.value, null);
        });
    });

    // Event listeners para ratings
    document.querySelectorAll('.rating-stars').forEach(container => {
        container.querySelectorAll('i').forEach(star => {
            star.addEventListener('click', function() {
                const value = this.dataset.value;
                const codigo = this.parentElement.dataset.codigo;

                // Update visual
                this.parentElement.querySelectorAll('i').forEach((s, i) => {
                    if (i < value) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });

                salvarInteracao(codigo, null, null, value);
            });
        });
    });

    // Salvar observações gerais (auto-save)
    document.getElementById('observacoes_gerais').addEventListener('blur', function() {
        const statusEl = document.getElementById('obs_status');
        statusEl.textContent = 'Salvando...';

        $.ajax({
            url: 'api_titulo_interacao.php',
            method: 'POST',
            data: {
                boas_vindas_id: boasVindasId,
                observacoes_gerais: this.value
            },
            success: function() {
                statusEl.textContent = 'Salvo!';
                setTimeout(() => { statusEl.textContent = ''; }, 2000);
            },
            error: function() {
                statusEl.textContent = 'Erro ao salvar';
            }
        });
    });

    // Registrar tentativa
    function registrarTentativa() {
        const tipo = document.getElementById('tipo_tentativa').value;
        const resultado = document.getElementById('resultado_tentativa').value;
        const telefone = document.getElementById('telefone_tentativa').value;
        const obs = document.getElementById('obs_tentativa').value;

        $.ajax({
            url: 'api_salvar_tentativa.php',
            method: 'POST',
            data: {
                boas_vindas_id: boasVindasId,
                tipo_tentativa: tipo,
                resultado: resultado,
                telefone: telefone,
                observacao: obs
            },
            success: function(response) {
                showToast('Tentativa registrada!', 'success');
                document.getElementById('obs_tentativa').value = '';
                // Recarregar página para mostrar nova tentativa
                setTimeout(() => location.reload(), 1000);
            },
            error: function() {
                showToast('Erro ao registrar tentativa', 'danger');
            }
        });
    }

    // Concluir atendimento
    function concluirAtendimento() {
        if (!confirm('Tem certeza que deseja concluir este atendimento?')) return;

        $.ajax({
            url: 'api_salvar_boasvindas.php',
            method: 'POST',
            data: {
                id: boasVindasId,
                status: 'concluido'
            },
            success: function(response) {
                showToast('Atendimento concluído!', 'success');
                setTimeout(() => location.reload(), 1000);
            },
            error: function() {
                showToast('Erro ao concluir', 'danger');
            }
        });
    }

    // CSS para spinner
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .spin { animation: spin 1s linear infinite; }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>
