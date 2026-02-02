<?php
require_once 'config.php';
Auth::requireLogin();

$pagina_atual = 'titulo';
$db = Database::getConnectionBV();

// Base URL para links absolutos
$base_url = '/boasvindas';

// Obter número do título da URL
$numero_titulo = $_GET['id'] ?? '';
if (empty($numero_titulo)) {
    header('Location: ' . $base_url . '/index');
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
if (!$titulo) {
    try {
        $db_api = Database::getConnectionAPI();
        $stmt_api = $db_api->prepare("SELECT * FROM titulos WHERE numero_titulo = :numero_titulo");
        $stmt_api->execute([':numero_titulo' => $numero_titulo]);
        $titulo_api = $stmt_api->fetch();

        if ($titulo_api) {
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
            $stmt->execute([':numero_titulo' => $numero_titulo]);
            $titulo = $stmt->fetch();
        }
    } catch (Exception $e) {}
}

if (!$titulo) {
    $_SESSION['erro'] = 'Título não encontrado';
    header('Location: ' . $base_url . '/index');
    exit;
}

// Buscar interações já salvas
$interacoes = [];
try {
    $stmt_int = $db->prepare("SELECT * FROM titulo_interacoes WHERE boas_vindas_id = :id");
    $stmt_int->execute([':id' => $titulo['id']]);
    foreach ($stmt_int->fetchAll() as $int) {
        $interacoes[$int['etapa_codigo']] = $int;
    }
} catch (Exception $e) {}

// Buscar etapas do checklist
$etapas = [];
try {
    $stmt_etapas = $db->query("SELECT * FROM checklist_etapas WHERE ativo = 1 ORDER BY ordem ASC");
    $etapas = $stmt_etapas->fetchAll();
} catch (Exception $e) {}

// Seções
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

// Buscar tentativas
$tentativas = [];
try {
    $stmt_tent = $db->prepare("SELECT tc.*, u.nome as nome_usuario FROM tentativas_contato tc LEFT JOIN usuarios u ON tc.usuario_id = u.id WHERE tc.boas_vindas_id = :id ORDER BY tc.criado_em DESC");
    $stmt_tent->execute([':id' => $titulo['id']]);
    $tentativas = $stmt_tent->fetchAll();
} catch (Exception $e) {}

// Buscar logs
$logs = [];
try {
    $stmt_logs = $db->prepare("SELECT tl.*, u.nome as nome_usuario FROM titulo_logs tl LEFT JOIN usuarios u ON tl.usuario_id = u.id WHERE tl.boas_vindas_id = :id ORDER BY tl.criado_em DESC LIMIT 30");
    $stmt_logs->execute([':id' => $titulo['id']]);
    $logs = $stmt_logs->fetchAll();
} catch (Exception $e) {}

// Buscar configs
$configs = [];
try {
    $stmt_cfg = $db->query("SELECT chave, valor FROM configuracoes");
    foreach ($stmt_cfg->fetchAll() as $c) $configs[$c['chave']] = $c['valor'];
} catch (Exception $e) {}

// Calcular progresso
$total_obrig = 0; $completos_obrig = 0;
foreach ($etapas as $e) {
    if ($e['obrigatorio']) {
        $total_obrig++;
        if (isset($interacoes[$e['codigo']]) && $interacoes[$e['codigo']]['valor_checkbox']) $completos_obrig++;
    }
}
$progresso = $total_obrig > 0 ? round(($completos_obrig / $total_obrig) * 100) : 0;

// Função para substituir variáveis
function substituirVars($tpl, $titulo, $configs) {
    if (!$tpl) return '';
    // Formatar valor sem prefixo R$ para evitar duplicação
    $valor_num = $titulo['valor_total'] ?? 0;
    $valor_formatado = number_format($valor_num, 2, ',', '.');
    $subs = [
        '{NOME}' => $titulo['nome_cliente'] ?? '',
        '{NUMERO_TITULO}' => $titulo['numero_titulo'] ?? '',
        '{DOCUMENTO}' => formatarDocumento($titulo['documento_cliente'] ?? ''),
        '{CPF_ULTIMOS_4}' => substr(preg_replace('/[^0-9]/', '', $titulo['documento_cliente'] ?? ''), -4),
        '{TELEFONE}' => formatarTelefone($titulo['telefone'] ?? ''),
        '{EMAIL}' => $titulo['email'] ?? '',
        '{DATA_VENDA}' => formatarData($titulo['data_venda'] ?? '', 'd/m/Y'),
        '{TIPO_TITULO}' => $titulo['tipo_titulo'] ?? '',
        '{VALOR}' => $valor_formatado, // Sem R$ para evitar "R$ R$"
        '{FORMA_PAGAMENTO}' => $titulo['forma_pagamento'] ?? '',
        '{PROMOTOR}' => $titulo['promotor'] ?? '',
        '{ATENDENTE}' => Auth::getUserName(),
        '{PORTAL_URL}' => $configs['portal_url'] ?? '',
        '{WHATSAPP}' => formatarTelefone($configs['whatsapp_numero'] ?? ''),
        '{DIA_VENCIMENTO}' => '10'
    ];
    return str_replace(array_keys($subs), array_values($subs), $tpl);
}

// Verificar se é um script válido (não é JSON de configuração)
function isValidScript($script) {
    if (!$script || trim($script) === '') return false;
    // Se começa com { e termina com }, provavelmente é JSON de configuração
    $trimmed = trim($script);
    if (preg_match('/^\{.*\}$/s', $trimmed)) {
        $decoded = json_decode($trimmed, true);
        if ($decoded !== null) return false; // É JSON válido, não mostrar como script
    }
    return true;
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
        :root { --primary: #1e3c72; --secondary: #2a5298; }
        body { background: #f5f7fa; font-size: 14px; }
        .navbar { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }

        /* Layout com scroll independente */
        .main-container { display: flex; height: calc(100vh - 180px); gap: 20px; }
        .left-column {
            width: 380px;
            min-width: 380px;
            overflow-y: auto;
            padding-right: 10px;
        }
        .right-column {
            flex: 1;
            overflow-y: auto;
            padding-right: 10px;
        }

        /* Scrollbar customizada */
        .left-column::-webkit-scrollbar, .right-column::-webkit-scrollbar { width: 6px; }
        .left-column::-webkit-scrollbar-thumb, .right-column::-webkit-scrollbar-thumb {
            background: #ccc; border-radius: 3px;
        }

        .card-info { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 15px; }
        .card-info .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white; border-radius: 12px 12px 0 0; padding: 12px 15px;
        }
        .card-info .card-header h6 { margin: 0; font-size: 14px; }
        .info-item { padding: 8px 0; border-bottom: 1px solid #eee; }
        .info-item:last-child { border-bottom: none; }
        .info-label { font-size: 11px; color: #666; text-transform: uppercase; margin-bottom: 2px; }
        .info-value { font-weight: 500; color: #333; font-size: 13px; }

        .checklist-section { background: white; border-radius: 12px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .checklist-section-header {
            background: #f8f9fa; padding: 12px 15px; cursor: pointer;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid #eee; border-radius: 12px 12px 0 0;
        }
        .checklist-section-header:hover { background: #e9ecef; }
        .checklist-section-header h6 { margin: 0; display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .checklist-section-body { padding: 15px; }

        .checklist-item { padding: 10px; border-radius: 8px; margin-bottom: 8px; background: #f8f9fa; }
        .checklist-item:hover { background: #e9ecef; }
        .checklist-item.completed { background: #d4edda; }
        .checklist-item .form-check-label { cursor: pointer; font-size: 13px; }
        .item-description { font-size: 11px; color: #666; margin-left: 25px; margin-top: 2px; }

        .script-box {
            background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px;
            padding: 10px; margin-top: 8px; font-size: 12px; position: relative;
        }
        .script-box .copy-btn { position: absolute; top: 5px; right: 5px; font-size: 11px; }
        .script-box pre { margin: 0; white-space: pre-wrap; font-family: inherit; font-size: 12px; }

        .field-input { margin-top: 8px; margin-left: 25px; }
        .field-input .form-control, .field-input .form-select { font-size: 13px; }

        /* Radio buttons inline */
        .radio-group { display: flex; gap: 15px; margin-top: 5px; margin-left: 25px; }
        .radio-group .form-check { margin: 0; }
        .radio-group .form-check-label { font-size: 12px; }

        /* Rating stars */
        .rating-stars { display: flex; gap: 3px; margin-left: 25px; margin-top: 5px; }
        .rating-stars i { cursor: pointer; color: #ddd; font-size: 18px; }
        .rating-stars i.active { color: #ffc107; }
        .rating-stars i:hover { color: #ffc107; }

        .saving-indicator, .saved-indicator { display: none; font-size: 11px; margin-left: 5px; }
        .saving-indicator { color: #17a2b8; }
        .saved-indicator { color: #28a745; }

        .tentativa-item { padding: 10px; background: #f8f9fa; border-radius: 8px; margin-bottom: 8px; font-size: 12px; }
        .log-item { padding: 6px 10px; border-left: 3px solid #dee2e6; margin-bottom: 5px; background: #f8f9fa; font-size: 11px; }
        .log-item.checkbox_marcado { border-left-color: #28a745; }
        .log-item.texto_salvo { border-left-color: #17a2b8; }

        .toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .spin { animation: spin 1s linear infinite; }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid py-3">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <a href="<?= $base_url ?>/index" class="text-decoration-none text-muted small">
                    <i class="bi bi-arrow-left"></i> Voltar ao Dashboard
                </a>
                <h5 class="mb-0 mt-1">
                    <i class="bi bi-ticket-perforated"></i>
                    Título: <strong><?= htmlspecialchars($titulo['numero_titulo']) ?></strong>
                </h5>
            </div>
            <div class="d-flex gap-2">
                <span class="badge <?= $titulo['status'] === 'concluido' ? 'bg-success' : ($titulo['status'] === 'em_andamento' ? 'bg-warning' : 'bg-danger') ?>" style="font-size: 14px; padding: 8px 15px;">
                    <?= ucfirst(str_replace('_', ' ', $titulo['status'])) ?>
                </span>
                <?php if ($titulo['status'] !== 'concluido'): ?>
                <button class="btn btn-success" onclick="concluirAtendimento()">
                    <i class="bi bi-check-lg"></i> Concluir Atendimento
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="card-info p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span>Progresso do Checklist</span>
                <span class="fw-bold"><?= $progresso ?>%</span>
            </div>
            <div class="progress" style="height: 8px;">
                <div class="progress-bar bg-success" style="width: <?= $progresso ?>%"></div>
            </div>
            <small class="text-muted"><?= $completos_obrig ?> de <?= $total_obrig ?> itens obrigatórios</small>
        </div>

        <!-- Main Content -->
        <div class="main-container">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Dados do Cliente -->
                <div class="card-info">
                    <div class="card-header"><h6><i class="bi bi-person-badge"></i> Dados do Cliente</h6></div>
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
                <div class="card-info">
                    <div class="card-header"><h6><i class="bi bi-telephone-plus"></i> Registrar Tentativa</h6></div>
                    <div class="card-body p-3">
                        <div class="mb-2">
                            <label class="form-label small">Tipo</label>
                            <select class="form-select form-select-sm" id="tipo_tentativa">
                                <option value="ligacao">Ligação</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="email">E-mail</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Resultado</label>
                            <select class="form-select form-select-sm" id="resultado_tentativa">
                                <option value="atendeu">Atendeu</option>
                                <option value="nao_atendeu">Não atendeu</option>
                                <option value="caixa_postal">Caixa postal</option>
                                <option value="whatsapp_enviado">WhatsApp enviado</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Telefone usado</label>
                            <input type="text" class="form-control form-control-sm" id="telefone_tentativa" value="<?= htmlspecialchars($titulo['telefone'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Observação</label>
                            <textarea class="form-control form-control-sm" id="obs_tentativa" rows="2"></textarea>
                        </div>
                        <button class="btn btn-primary btn-sm w-100" onclick="registrarTentativa()">
                            <i class="bi bi-plus-lg"></i> Registrar
                        </button>
                    </div>
                </div>

                <!-- Tentativas -->
                <div class="card-info">
                    <div class="card-header d-flex justify-content-between">
                        <h6 class="mb-0"><i class="bi bi-clock-history"></i> Tentativas</h6>
                        <span class="badge bg-light text-dark"><?= count($tentativas) ?></span>
                    </div>
                    <div class="card-body p-3" style="max-height: 200px; overflow-y: auto;">
                        <?php if (empty($tentativas)): ?>
                            <p class="text-muted text-center small mb-0">Nenhuma tentativa</p>
                        <?php else: ?>
                            <?php foreach ($tentativas as $t): ?>
                            <div class="tentativa-item">
                                <div class="d-flex justify-content-between">
                                    <span class="badge <?= $t['resultado'] === 'atendeu' ? 'bg-success' : 'bg-warning' ?>">
                                        <?= ucfirst($t['tipo_tentativa']) ?>
                                    </span>
                                    <small class="text-muted"><?= formatarData($t['criado_em']) ?></small>
                                </div>
                                <div class="mt-1"><strong><?= ucfirst(str_replace('_', ' ', $t['resultado'])) ?></strong></div>
                                <?php if ($t['observacao']): ?>
                                <small class="text-muted"><?= htmlspecialchars($t['observacao']) ?></small>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Logs -->
                <div class="card-info">
                    <div class="card-header d-flex justify-content-between">
                        <h6 class="mb-0"><i class="bi bi-activity"></i> Atividades</h6>
                        <span class="badge bg-light text-dark"><?= count($logs) ?></span>
                    </div>
                    <div class="card-body p-3" style="max-height: 200px; overflow-y: auto;">
                        <?php if (empty($logs)): ?>
                            <p class="text-muted text-center small mb-0">Nenhuma atividade</p>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <div class="log-item <?= $log['tipo_log'] ?>">
                                <small class="text-muted"><?= formatarData($log['criado_em']) ?></small>
                                - <?= htmlspecialchars($log['etapa_codigo'] ?? $log['tipo_log']) ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="right-column">
                <?php if (empty($etapas)): ?>
                <div class="alert alert-warning">
                    <h6><i class="bi bi-exclamation-triangle"></i> Checklist não configurado</h6>
                    <p class="mb-0">Execute o script SQL <code>update_database_v2.sql</code> no banco de dados.</p>
                </div>
                <?php else: ?>

                <?php foreach ($secoes as $secao_key => $secao):
                    $etapas_secao = array_filter($etapas, fn($e) => strpos($e['codigo'], $secao['prefixo']) === 0);
                    if (empty($etapas_secao)) continue;
                    $total_sec = count($etapas_secao);
                    $completos_sec = 0;
                    foreach ($etapas_secao as $et) {
                        if (isset($interacoes[$et['codigo']]) && $interacoes[$et['codigo']]['valor_checkbox']) $completos_sec++;
                    }
                ?>
                <div class="checklist-section">
                    <div class="checklist-section-header" data-bs-toggle="collapse" data-bs-target="#collapse_<?= $secao_key ?>">
                        <h6><i class="bi <?= $secao['icone'] ?>"></i> <?= $secao['titulo'] ?></h6>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge <?= $completos_sec === $total_sec ? 'bg-success' : 'bg-secondary' ?>"><?= $completos_sec ?>/<?= $total_sec ?></span>
                            <i class="bi bi-chevron-down"></i>
                        </div>
                    </div>
                    <div class="collapse show" id="collapse_<?= $secao_key ?>">
                        <div class="checklist-section-body">
                            <?php foreach ($etapas_secao as $etapa):
                                $val_cb = isset($interacoes[$etapa['codigo']]) ? $interacoes[$etapa['codigo']]['valor_checkbox'] : 0;
                                $val_txt = isset($interacoes[$etapa['codigo']]) ? ($interacoes[$etapa['codigo']]['valor_texto'] ?? '') : '';
                                $val_num = isset($interacoes[$etapa['codigo']]) ? ($interacoes[$etapa['codigo']]['valor_numero'] ?? '') : '';
                                $script = substituirVars($etapa['script_template'], $titulo, $configs);
                                $opcoes = $etapa['opcoes_campo'] ? json_decode($etapa['opcoes_campo'], true) : [];
                            ?>
                            <div class="checklist-item <?= $val_cb ? 'completed' : '' ?>" data-codigo="<?= $etapa['codigo'] ?>">
                                <div class="form-check">
                                    <input class="form-check-input checklist-checkbox" type="checkbox"
                                           id="check_<?= $etapa['codigo'] ?>" data-codigo="<?= $etapa['codigo'] ?>"
                                           <?= $val_cb ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="check_<?= $etapa['codigo'] ?>">
                                        <?= htmlspecialchars($etapa['titulo']) ?>
                                        <?php if ($etapa['obrigatorio']): ?><span class="text-danger">*</span><?php endif; ?>
                                    </label>
                                    <span class="saving-indicator" id="saving_<?= $etapa['codigo'] ?>"><i class="bi bi-arrow-repeat spin"></i></span>
                                    <span class="saved-indicator" id="saved_<?= $etapa['codigo'] ?>"><i class="bi bi-check"></i></span>
                                </div>

                                <?php if ($etapa['descricao']): ?>
                                <div class="item-description"><?= htmlspecialchars($etapa['descricao']) ?></div>
                                <?php endif; ?>

                                <?php if ($etapa['tipo_campo'] === 'select' && isset($opcoes['opcoes'])): ?>
                                    <?php
                                    $opts = $opcoes['opcoes'];
                                    // Detectar se deve usar radio buttons (opções binárias ou ternárias simples)
                                    $binary_keywords = ['Sim', 'Não', 'Positivo', 'Negativo', 'Neutro', 'Atendido', 'Retornar'];
                                    $is_binary = count($opts) <= 4 && count(array_intersect($opts, $binary_keywords)) > 0;
                                    ?>
                                    <?php if ($is_binary): ?>
                                    <!-- Radio buttons para Sim/Não -->
                                    <div class="radio-group">
                                        <?php foreach ($opts as $i => $opt): ?>
                                        <div class="form-check">
                                            <input class="form-check-input checklist-radio" type="radio"
                                                   name="radio_<?= $etapa['codigo'] ?>" id="radio_<?= $etapa['codigo'] ?>_<?= $i ?>"
                                                   value="<?= htmlspecialchars($opt) ?>" data-codigo="<?= $etapa['codigo'] ?>"
                                                   <?= $val_txt === $opt ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="radio_<?= $etapa['codigo'] ?>_<?= $i ?>"><?= htmlspecialchars($opt) ?></label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                    <!-- Select para múltiplas opções -->
                                    <div class="field-input">
                                        <select class="form-select form-select-sm checklist-select" data-codigo="<?= $etapa['codigo'] ?>">
                                            <option value="">Selecione...</option>
                                            <?php foreach ($opts as $opt): ?>
                                            <option value="<?= htmlspecialchars($opt) ?>" <?= $val_txt === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                <?php elseif ($etapa['tipo_campo'] === 'textarea'): ?>
                                <div class="field-input">
                                    <textarea class="form-control form-control-sm checklist-textarea" data-codigo="<?= $etapa['codigo'] ?>" rows="2" placeholder="Digite..."><?= htmlspecialchars($val_txt) ?></textarea>
                                </div>
                                <?php elseif ($etapa['tipo_campo'] === 'texto'): ?>
                                <div class="field-input">
                                    <input type="text" class="form-control form-control-sm checklist-texto" data-codigo="<?= $etapa['codigo'] ?>" value="<?= htmlspecialchars($val_txt) ?>" placeholder="Digite...">
                                </div>
                                <?php elseif ($etapa['tipo_campo'] === 'numero'): ?>
                                <div class="field-input">
                                    <input type="number" class="form-control form-control-sm checklist-numero" data-codigo="<?= $etapa['codigo'] ?>" value="<?= htmlspecialchars($val_num) ?>" placeholder="0">
                                </div>
                                <?php elseif ($etapa['tipo_campo'] === 'rating'): ?>
                                <div class="rating-stars" data-codigo="<?= $etapa['codigo'] ?>">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star-fill <?= $i <= (int)$val_num ? 'active' : '' ?>" data-value="<?= $i ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <?php endif; ?>

                                <?php if (isValidScript($script)): ?>
                                <div class="script-box">
                                    <button class="btn btn-sm btn-outline-warning copy-btn" onclick="copiarScript(this)">
                                        <i class="bi bi-clipboard"></i> Copiar
                                    </button>
                                    <strong><i class="bi bi-chat-quote"></i> Script:</strong>
                                    <pre class="mt-1"><?= htmlspecialchars($script) ?></pre>
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
                <div class="card-info">
                    <div class="card-header"><h6><i class="bi bi-journal-text"></i> Observações Gerais</h6></div>
                    <div class="card-body p-3">
                        <textarea class="form-control form-control-sm" id="observacoes_gerais" rows="3" placeholder="Adicione observações importantes sobre este atendimento..."><?= htmlspecialchars($titulo['observacoes'] ?? '') ?></textarea>
                        <small class="text-muted" id="obs_status"></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast-container">
        <div class="toast" id="toast" role="alert">
            <div class="toast-body" id="toast-body"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
    const boasVindasId = <?= $titulo['id'] ?>;
    const baseUrl = '<?= $base_url ?>';

    function showToast(msg, type = 'success') {
        const toast = document.getElementById('toast');
        const body = document.getElementById('toast-body');
        toast.className = 'toast bg-' + type + ' text-white';
        body.textContent = msg;
        new bootstrap.Toast(toast).show();
    }

    function copiarTexto(txt) {
        navigator.clipboard.writeText(txt).then(() => showToast('Copiado!'));
    }

    function copiarScript(btn) {
        const pre = btn.parentElement.querySelector('pre');
        navigator.clipboard.writeText(pre.textContent).then(() => {
            btn.innerHTML = '<i class="bi bi-check"></i> Copiado!';
            setTimeout(() => btn.innerHTML = '<i class="bi bi-clipboard"></i> Copiar', 2000);
        });
    }

    function salvarInteracao(codigo, checkbox = null, texto = null, numero = null) {
        const saving = document.getElementById('saving_' + codigo);
        const saved = document.getElementById('saved_' + codigo);
        if (saving) saving.style.display = 'inline';
        if (saved) saved.style.display = 'none';

        $.ajax({
            url: baseUrl + '/api_titulo_interacao.php',
            method: 'POST',
            data: { boas_vindas_id: boasVindasId, etapa_codigo: codigo, valor_checkbox: checkbox, valor_texto: texto, valor_numero: numero },
            success: function(r) {
                if (saving) saving.style.display = 'none';
                if (saved) { saved.style.display = 'inline'; setTimeout(() => saved.style.display = 'none', 2000); }
                const item = document.querySelector('[data-codigo="' + codigo + '"]');
                if (item && checkbox !== null) {
                    item.classList.toggle('completed', checkbox);
                }
                atualizarProgresso();
            },
            error: function() {
                if (saving) saving.style.display = 'none';
                showToast('Erro ao salvar', 'danger');
            }
        });
    }

    function atualizarProgresso() {
        const cbs = document.querySelectorAll('.checklist-checkbox');
        let total = 0, completos = 0;
        cbs.forEach(cb => { total++; if (cb.checked) completos++; });
        const prog = total > 0 ? Math.round((completos / total) * 100) : 0;
        document.querySelector('.progress-bar').style.width = prog + '%';
    }

    // Event listeners
    document.querySelectorAll('.checklist-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            salvarInteracao(this.dataset.codigo, this.checked ? 1 : 0, null, null);
        });
    });

    document.querySelectorAll('.checklist-textarea, .checklist-texto').forEach(el => {
        el.addEventListener('blur', function() {
            const codigo = this.dataset.codigo;
            const hasValue = this.value.trim() !== '';
            // Se tem valor, também marcar o checkbox como completo
            if (hasValue) {
                const cb = document.getElementById('check_' + codigo);
                if (cb && !cb.checked) {
                    cb.checked = true;
                    salvarInteracao(codigo, 1, this.value, null);
                } else {
                    salvarInteracao(codigo, null, this.value, null);
                }
            } else {
                salvarInteracao(codigo, null, this.value, null);
            }
        });
    });

    document.querySelectorAll('.checklist-numero').forEach(el => {
        el.addEventListener('blur', function() {
            const codigo = this.dataset.codigo;
            const hasValue = this.value.trim() !== '';
            // Se tem valor, também marcar o checkbox como completo
            if (hasValue) {
                const cb = document.getElementById('check_' + codigo);
                if (cb && !cb.checked) {
                    cb.checked = true;
                    salvarInteracao(codigo, 1, null, this.value);
                } else {
                    salvarInteracao(codigo, null, null, this.value);
                }
            } else {
                salvarInteracao(codigo, null, null, this.value);
            }
        });
    });

    document.querySelectorAll('.checklist-select').forEach(el => {
        el.addEventListener('change', function() {
            const codigo = this.dataset.codigo;
            const hasValue = this.value.trim() !== '';
            // Se tem valor, também marcar o checkbox como completo
            if (hasValue) {
                const cb = document.getElementById('check_' + codigo);
                if (cb && !cb.checked) {
                    cb.checked = true;
                    salvarInteracao(codigo, 1, this.value, null);
                } else {
                    salvarInteracao(codigo, null, this.value, null);
                }
            } else {
                salvarInteracao(codigo, null, this.value, null);
            }
        });
    });

    document.querySelectorAll('.checklist-radio').forEach(el => {
        el.addEventListener('change', function() {
            const codigo = this.dataset.codigo;
            // Ao selecionar radio, também marcar o checkbox como completo
            const cb = document.getElementById('check_' + codigo);
            if (cb && !cb.checked) {
                cb.checked = true;
                salvarInteracao(codigo, 1, this.value, null);
            } else {
                salvarInteracao(codigo, null, this.value, null);
            }
        });
    });

    document.querySelectorAll('.rating-stars').forEach(container => {
        container.querySelectorAll('i').forEach(star => {
            star.addEventListener('click', function() {
                const val = this.dataset.value;
                const codigo = this.parentElement.dataset.codigo;
                this.parentElement.querySelectorAll('i').forEach((s, i) => {
                    s.classList.toggle('active', i < val);
                });
                // Também marcar o checkbox como completo
                const cb = document.getElementById('check_' + codigo);
                if (cb && !cb.checked) {
                    cb.checked = true;
                    salvarInteracao(codigo, 1, null, val);
                } else {
                    salvarInteracao(codigo, null, null, val);
                }
            });
        });
    });

    document.getElementById('observacoes_gerais').addEventListener('blur', function() {
        const status = document.getElementById('obs_status');
        status.textContent = 'Salvando...';
        $.ajax({
            url: baseUrl + '/api_titulo_interacao.php',
            method: 'POST',
            data: { boas_vindas_id: boasVindasId, observacoes_gerais: this.value },
            success: () => { status.textContent = 'Salvo!'; setTimeout(() => status.textContent = '', 2000); },
            error: () => status.textContent = 'Erro'
        });
    });

    function registrarTentativa() {
        $.ajax({
            url: baseUrl + '/api_salvar_tentativa.php',
            method: 'POST',
            data: {
                boas_vindas_id: boasVindasId,
                tipo_tentativa: document.getElementById('tipo_tentativa').value,
                resultado: document.getElementById('resultado_tentativa').value,
                telefone: document.getElementById('telefone_tentativa').value,
                observacao: document.getElementById('obs_tentativa').value
            },
            success: () => { showToast('Tentativa registrada!'); setTimeout(() => location.reload(), 1000); },
            error: () => showToast('Erro ao registrar', 'danger')
        });
    }

    function concluirAtendimento() {
        if (!confirm('Concluir este atendimento?')) return;
        $.ajax({
            url: baseUrl + '/api_salvar_boasvindas.php',
            method: 'POST',
            data: { id: boasVindasId, status: 'concluido' },
            success: () => { showToast('Concluído!'); setTimeout(() => location.reload(), 1000); },
            error: () => showToast('Erro', 'danger')
        });
    }
    </script>
</body>
</html>
