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

// Seções - separando registro para coluna direita
$secoes_checklist = [
    'preparacao' => ['titulo' => 'Preparação', 'icone' => 'bi-gear', 'prefixo' => 'prep_'],
    'abertura' => ['titulo' => 'Abertura', 'icone' => 'bi-telephone-outbound', 'prefixo' => 'abert_'],
    'validacao' => ['titulo' => 'Validação do Titular', 'icone' => 'bi-shield-check', 'prefixo' => 'valid_'],
    'portal' => ['titulo' => 'Portal', 'icone' => 'bi-globe', 'prefixo' => 'portal_'],
    'atendimento' => ['titulo' => 'Atendimento', 'icone' => 'bi-star', 'prefixo' => 'atend_'],
    'financeiro' => ['titulo' => 'Financeiro', 'icone' => 'bi-currency-dollar', 'prefixo' => 'financ_'],
    'informacoes' => ['titulo' => 'Informações', 'icone' => 'bi-info-circle', 'prefixo' => 'info_'],
    'duvidas' => ['titulo' => 'Dúvidas', 'icone' => 'bi-question-circle', 'prefixo' => 'duvidas_'],
    'ofertas' => ['titulo' => 'Ofertas', 'icone' => 'bi-gift', 'prefixo' => 'oferta_'],
    'encerramento' => ['titulo' => 'Encerramento', 'icone' => 'bi-hand-thumbs-up', 'prefixo' => 'encerr_']
];

$secao_registro = ['titulo' => 'Registro Pós-Ligação', 'icone' => 'bi-journal-check', 'prefixo' => 'reg_'];

// Buscar tentativas
$tentativas = [];
$precisa_followup = false;
$ultima_tentativa = null;
$horas_desde_ultima = 0;
$sugestao_contato = 'telefone';
try {
    $stmt_tent = $db->prepare("SELECT tc.*, u.nome as nome_usuario FROM tentativas_contato tc LEFT JOIN usuarios u ON tc.usuario_id = u.id WHERE tc.boas_vindas_id = :id ORDER BY tc.criado_em DESC");
    $stmt_tent->execute([':id' => $titulo['id']]);
    $tentativas = $stmt_tent->fetchAll();

    // Verificar se precisa de follow-up
    if (!empty($tentativas) && $titulo['status'] !== 'concluido') {
        $ultima_tentativa = $tentativas[0];
        $resultados_sem_sucesso = ['nao_atendeu', 'caixa_postal', 'so_chamou', 'desligou'];

        if (in_array($ultima_tentativa['resultado'], $resultados_sem_sucesso)) {
            $precisa_followup = true;
            $data_ultima = new DateTime($ultima_tentativa['criado_em']);
            $agora = new DateTime();
            $diff = $agora->diff($data_ultima);
            $horas_desde_ultima = ($diff->days * 24) + $diff->h;

            // Sugerir tipo de contato baseado na última tentativa
            if ($ultima_tentativa['tipo_tentativa'] === 'ligacao') {
                $sugestao_contato = 'whatsapp';
            } elseif ($ultima_tentativa['tipo_tentativa'] === 'whatsapp') {
                $sugestao_contato = 'email';
            } else {
                $sugestao_contato = 'telefone';
            }
        }
    }
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

// Buscar título anterior e próximo para navegação
$titulo_anterior = null;
$titulo_proximo = null;
try {
    $numero_titulo_atual = $titulo['numero_titulo'];
    $user_id = Auth::getUserId();

    // Buscar todos títulos não concluídos do usuário e ordenar em PHP
    $stmt_all = $db->prepare("
        SELECT numero_titulo, status FROM boas_vindas
        WHERE usuario_id = :user_id AND status != 'concluido'
        ORDER BY numero_titulo ASC
    ");
    $stmt_all->execute([':user_id' => $user_id]);
    $todos_titulos = $stmt_all->fetchAll();

    // Extrair números e ordenar corretamente
    $titulos_ordenados = [];
    foreach ($todos_titulos as $t) {
        $num = intval(preg_replace('/[^0-9]/', '', $t['numero_titulo']));
        $titulos_ordenados[] = ['numero_titulo' => $t['numero_titulo'], 'num' => $num];
    }
    usort($titulos_ordenados, fn($a, $b) => $a['num'] - $b['num']);

    // Encontrar posição atual e pegar anterior/próximo
    $num_atual = intval(preg_replace('/[^0-9]/', '', $numero_titulo_atual));
    $anterior = null;
    $proximo = null;

    foreach ($titulos_ordenados as $i => $t) {
        if ($t['num'] < $num_atual) {
            $anterior = $t;
        }
        if ($t['num'] > $num_atual && !$proximo) {
            $proximo = $t;
            break;
        }
    }

    if ($anterior) $titulo_anterior = ['numero_titulo' => $anterior['numero_titulo']];
    if ($proximo) $titulo_proximo = ['numero_titulo' => $proximo['numero_titulo']];
} catch (Exception $e) {}

// Verificar se cliente tem ciência do título (flag para alerta vermelho)
$sem_ciencia_titulo = false;
try {
    if (isset($interacoes['valid_cliente_ciente'])) {
        $val = $interacoes['valid_cliente_ciente']['valor_texto'] ?? '';
        if (in_array($val, ['Não', 'Negativo', 'Não, corrigido'])) {
            $sem_ciencia_titulo = true;
        }
    }
} catch (Exception $e) {}

// Mapeamento de nomes amigáveis para atividades
$nomes_amigaveis = [
    'checkbox_marcado' => 'Item marcado',
    'checkbox_desmarcado' => 'Item desmarcado',
    'texto_salvo' => 'Texto salvo',
    'numero_salvo' => 'Número salvo',
    'observacao_salva' => 'Observação salva',
    'atend_nota_consultor' => 'Nota do consultor',
    'atend_feedback_texto' => 'Feedback registrado',
    'atend_feedback_positivo' => 'Avaliação do feedback',
    'prep_verificar_dados' => 'Verificou dados',
    'prep_verificar_consultor' => 'Verificou consultor',
    'abert_cumprimentar' => 'Cumprimentou cliente',
    'valid_confirmar_cpf' => 'Confirmou CPF',
    'portal_informar' => 'Informou portal',
    'financ_explicou_anuidade' => 'Explicou anuidade',
    'observacoes_gerais' => 'Observação geral'
];

function getNomeAmigavel($codigo, $tipo_log, $nomes) {
    if (isset($nomes[$codigo])) return $nomes[$codigo];
    if (isset($nomes[$tipo_log])) return $nomes[$tipo_log];
    // Tentar fazer replace básico
    $nome = str_replace('_', ' ', $codigo ?? $tipo_log);
    $nome = ucfirst($nome);
    return $nome;
}

// Calcular progresso e métricas de satisfação
$total_obrig = 0; $completos_obrig = 0;
$satisfacao_positiva = 0; $satisfacao_negativa = 0; $satisfacao_neutra = 0;
$nota_consultor = 0;

foreach ($etapas as $e) {
    if ($e['obrigatorio']) {
        $total_obrig++;
        if (isset($interacoes[$e['codigo']]) && $interacoes[$e['codigo']]['valor_checkbox']) $completos_obrig++;
    }

    // Calcular satisfação baseada nas respostas
    if (isset($interacoes[$e['codigo']])) {
        $val_txt = $interacoes[$e['codigo']]['valor_texto'] ?? '';
        $val_num = $interacoes[$e['codigo']]['valor_numero'] ?? 0;

        if (in_array($val_txt, ['Positivo', 'Sim', 'Sim, confirmado', 'Sim, correto'])) {
            $satisfacao_positiva++;
        } elseif (in_array($val_txt, ['Negativo', 'Não', 'Dados incorretos', 'Não, corrigido'])) {
            $satisfacao_negativa++;
        } elseif ($val_txt === 'Neutro') {
            $satisfacao_neutra++;
        }

        if ($e['codigo'] === 'atend_nota_consultor' && $val_num > 0) {
            $nota_consultor = (int)$val_num;
        }
    }
}
$progresso = $total_obrig > 0 ? round(($completos_obrig / $total_obrig) * 100) : 0;
$total_respostas = $satisfacao_positiva + $satisfacao_negativa + $satisfacao_neutra;
$satisfacao_score = $total_respostas > 0 ? round(($satisfacao_positiva / $total_respostas) * 100) : 0;

// Função para substituir variáveis
function substituirVars($tpl, $titulo, $configs) {
    if (!$tpl) return '';
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
        '{VALOR}' => $valor_formatado,
        '{FORMA_PAGAMENTO}' => $titulo['forma_pagamento'] ?? '',
        '{PROMOTOR}' => $titulo['promotor'] ?? '',
        '{ATENDENTE}' => Auth::getUserName(),
        '{PORTAL_URL}' => $configs['portal_url'] ?? '',
        '{WHATSAPP}' => formatarTelefone($configs['whatsapp_numero'] ?? ''),
        '{DIA_VENCIMENTO}' => '10'
    ];
    return str_replace(array_keys($subs), array_values($subs), $tpl);
}

function isValidScript($script) {
    if (!$script || trim($script) === '') return false;
    $trimmed = trim($script);
    if (preg_match('/^\{.*\}$/s', $trimmed)) {
        $decoded = json_decode($trimmed, true);
        if ($decoded !== null) return false;
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
        body { background: #f5f7fa; font-size: 13px; }
        .navbar { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }

        /* Layout 3 colunas */
        .main-container { display: flex; height: calc(100vh - 200px); gap: 15px; }
        .left-column { width: 280px; min-width: 280px; overflow-y: auto; }
        .center-column { flex: 1; overflow-y: auto; }
        .right-column { width: 320px; min-width: 320px; overflow-y: auto; }

        /* Scrollbar */
        .left-column::-webkit-scrollbar, .center-column::-webkit-scrollbar, .right-column::-webkit-scrollbar { width: 5px; }
        .left-column::-webkit-scrollbar-thumb, .center-column::-webkit-scrollbar-thumb, .right-column::-webkit-scrollbar-thumb {
            background: #ccc; border-radius: 3px;
        }

        .card-info { background: white; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); margin-bottom: 12px; }
        .card-info .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white; border-radius: 10px 10px 0 0; padding: 10px 12px;
        }
        .card-info .card-header h6 { margin: 0; font-size: 13px; }
        .info-item { padding: 6px 0; border-bottom: 1px solid #eee; }
        .info-item:last-child { border-bottom: none; }
        .info-label { font-size: 10px; color: #666; text-transform: uppercase; margin-bottom: 1px; }
        .info-value { font-weight: 500; color: #333; font-size: 12px; }

        .checklist-section { background: white; border-radius: 10px; margin-bottom: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
        .checklist-section-header {
            background: #f8f9fa; padding: 10px 12px; cursor: pointer;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid #eee; border-radius: 10px 10px 0 0;
        }
        .checklist-section-header:hover { background: #e9ecef; }
        .checklist-section-header h6 { margin: 0; display: flex; align-items: center; gap: 6px; font-size: 13px; }
        .checklist-section-body { padding: 12px; }

        .checklist-item { padding: 8px; border-radius: 6px; margin-bottom: 6px; background: #f8f9fa; }
        .checklist-item:hover { background: #e9ecef; }
        .checklist-item.completed { background: #d4edda; }
        .checklist-item.hidden { display: none; }
        .checklist-item .form-check-label { cursor: pointer; font-size: 12px; }
        .item-description { font-size: 10px; color: #666; margin-left: 22px; margin-top: 2px; }

        .script-box {
            background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px;
            padding: 8px; margin-top: 6px; font-size: 11px; position: relative;
        }
        .script-box .copy-btn { position: absolute; top: 4px; right: 4px; font-size: 10px; padding: 2px 6px; }
        .script-box pre { margin: 0; white-space: pre-wrap; font-family: inherit; font-size: 11px; }

        .field-input { margin-top: 6px; margin-left: 22px; }
        .field-input .form-control, .field-input .form-select { font-size: 12px; }

        .radio-group { display: flex; gap: 12px; margin-top: 4px; margin-left: 22px; }
        .radio-group .form-check { margin: 0; }
        .radio-group .form-check-label { font-size: 11px; }

        .rating-stars { display: flex; gap: 2px; margin-left: 22px; margin-top: 4px; }
        .rating-stars i { cursor: pointer; color: #ddd; font-size: 16px; }
        .rating-stars i.active { color: #ffc107; }
        .rating-stars i:hover { color: #ffc107; }

        .saving-indicator, .saved-indicator { display: none; font-size: 10px; margin-left: 4px; }
        .saving-indicator { color: #17a2b8; }
        .saved-indicator { color: #28a745; }

        .tentativa-item { padding: 8px; background: #f8f9fa; border-radius: 6px; margin-bottom: 6px; font-size: 11px; }
        .log-item { padding: 5px 8px; border-left: 3px solid #dee2e6; margin-bottom: 4px; background: #f8f9fa; font-size: 10px; }
        .log-item.checkbox_marcado { border-left-color: #28a745; }
        .log-item.texto_salvo { border-left-color: #17a2b8; }

        .toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .spin { animation: spin 1s linear infinite; }

        /* Satisfação */
        .satisfaction-card { background: white; border-radius: 10px; padding: 15px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
        .satisfaction-gauge { position: relative; width: 80px; height: 40px; margin: 0 auto; }
        .satisfaction-gauge-bg { width: 80px; height: 40px; border-radius: 80px 80px 0 0; background: linear-gradient(90deg, #dc3545 0%, #ffc107 50%, #28a745 100%); }
        .satisfaction-needle {
            position: absolute; bottom: 0; left: 50%; width: 2px; height: 35px;
            background: #333; transform-origin: bottom center; transform: rotate(<?= ($satisfacao_score - 50) * 1.8 ?>deg);
            border-radius: 2px;
        }
        .satisfaction-score { text-align: center; font-size: 18px; font-weight: bold; margin-top: 5px; }
        .satisfaction-label { text-align: center; font-size: 11px; color: #666; }

        .metric-box { text-align: center; padding: 8px; background: #f8f9fa; border-radius: 6px; }
        .metric-value { font-size: 18px; font-weight: bold; }
        .metric-label { font-size: 10px; color: #666; }

        /* Pesquisa */
        .search-box { position: relative; margin-bottom: 12px; }
        .search-box input { padding-left: 35px; font-size: 12px; }
        .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #999; }
        .search-highlight { background: #fff3cd; font-weight: bold; }

        /* Response radio inline */
        .response-radio-inline {
            display: flex; gap: 8px; margin-left: 22px; margin-top: 4px;
        }
        .response-radio-inline .form-check {
            margin: 0; padding-left: 18px;
        }
        .response-radio-inline .form-check-input { width: 12px; height: 12px; }
        .response-radio-inline .form-check-label {
            font-size: 10px; margin-left: 2px;
        }
        .response-radio-inline .form-check-label.text-success { color: #198754 !important; }
        .response-radio-inline .form-check-label.text-warning { color: #ffc107 !important; }
        .response-radio-inline .form-check-label.text-danger { color: #dc3545 !important; }

        /* Navegação */
        .nav-btn-group { display: flex; gap: 8px; align-items: center; }
        .nav-btn-group .btn { font-size: 11px; padding: 4px 10px; }
        .nav-btn-group .nav-id { font-size: 10px; color: #666; }

        /* Alerta ciência */
        .alert-ciencia {
            background: #ffebee; border: 1px solid #f44336; color: #c62828;
            padding: 6px 10px; border-radius: 6px; margin-bottom: 8px;
            font-size: 11px; font-weight: 500;
        }
        .alert-ciencia i { margin-right: 5px; }
        .titulo-sem-ciencia { color: #dc3545 !important; }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid py-2">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="d-flex align-items-center gap-3">
                <div>
                    <a href="<?= $base_url ?>/index" class="text-decoration-none text-muted small">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </a>
                    <h5 class="mb-0 mt-1 <?= $sem_ciencia_titulo ? 'titulo-sem-ciencia' : '' ?>" style="font-size: 16px;">
                        <i class="bi bi-ticket-perforated"></i>
                        Título: <strong id="tituloId"><?= htmlspecialchars($titulo['numero_titulo']) ?></strong>
                        <button class="btn btn-sm btn-outline-secondary py-0 px-1 ms-1" onclick="copiarTexto('<?= htmlspecialchars($titulo['numero_titulo']) ?>')" title="Copiar ID">
                            <i class="bi bi-clipboard" style="font-size: 12px;"></i>
                        </button>
                    </h5>
                </div>
                <!-- Navegação -->
                <div class="nav-btn-group">
                    <?php if ($titulo_anterior): ?>
                    <a href="<?= $base_url ?>/titulo?id=<?= urlencode($titulo_anterior['numero_titulo']) ?>" class="btn btn-outline-secondary btn-sm" title="Anterior: <?= htmlspecialchars($titulo_anterior['numero_titulo']) ?>">
                        <i class="bi bi-chevron-left"></i>
                        <span class="nav-id"><?= htmlspecialchars($titulo_anterior['numero_titulo']) ?></span>
                    </a>
                    <?php else: ?>
                    <button class="btn btn-outline-secondary btn-sm" disabled><i class="bi bi-chevron-left"></i></button>
                    <?php endif; ?>
                    <?php if ($titulo_proximo): ?>
                    <a href="<?= $base_url ?>/titulo?id=<?= urlencode($titulo_proximo['numero_titulo']) ?>" class="btn btn-outline-secondary btn-sm" title="Próximo: <?= htmlspecialchars($titulo_proximo['numero_titulo']) ?>">
                        <span class="nav-id"><?= htmlspecialchars($titulo_proximo['numero_titulo']) ?></span>
                        <i class="bi bi-chevron-right"></i>
                    </a>
                    <?php else: ?>
                    <button class="btn btn-outline-secondary btn-sm" disabled><i class="bi bi-chevron-right"></i></button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-2">
                <span class="badge <?= $titulo['status'] === 'concluido' ? 'bg-success' : ($titulo['status'] === 'em_andamento' ? 'bg-warning text-dark' : 'bg-danger') ?>" style="font-size: 12px; padding: 6px 12px;">
                    <?= ucfirst(str_replace('_', ' ', $titulo['status'])) ?>
                </span>
                <?php if ($titulo['status'] !== 'concluido'): ?>
                    <?php if ($titulo['status'] !== 'pendente'): ?>
                    <button class="btn btn-warning btn-sm" onclick="marcarPendente()">
                        <i class="bi bi-clock"></i> Pendente
                    </button>
                    <?php endif; ?>
                    <?php if ($titulo['status'] === 'pendente'): ?>
                    <button class="btn btn-info btn-sm text-white" onclick="iniciarAtendimento()">
                        <i class="bi bi-play-fill"></i> Iniciar
                    </button>
                    <?php endif; ?>
                <button class="btn btn-success btn-sm" onclick="concluirAtendimento()">
                    <i class="bi bi-check-lg"></i> Concluir
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($sem_ciencia_titulo): ?>
        <div class="alert-ciencia">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <strong>ATENÇÃO:</strong> Cliente não tem ciência do título! Verificar situação com o consultor.
        </div>
        <?php endif; ?>

        <!-- Progress & Satisfaction Row -->
        <div class="row g-2 mb-2">
            <div class="col-md-6">
                <div class="card-info p-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span style="font-size: 12px;">Progresso do Checklist</span>
                        <span class="fw-bold" style="font-size: 14px;"><?= $progresso ?>%</span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-success" style="width: <?= $progresso ?>%"></div>
                    </div>
                    <small class="text-muted" style="font-size: 10px;"><?= $completos_obrig ?> de <?= $total_obrig ?> obrigatórios</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="satisfaction-card">
                    <div class="row g-2 align-items-center">
                        <div class="col-4">
                            <div class="satisfaction-gauge">
                                <div class="satisfaction-gauge-bg"></div>
                                <div class="satisfaction-needle"></div>
                            </div>
                            <div class="satisfaction-score <?= $satisfacao_score >= 70 ? 'text-success' : ($satisfacao_score >= 40 ? 'text-warning' : 'text-danger') ?>"><?= $satisfacao_score ?>%</div>
                            <div class="satisfaction-label">Satisfação</div>
                        </div>
                        <div class="col-8">
                            <div class="row g-1">
                                <div class="col-4">
                                    <div class="metric-box">
                                        <div class="metric-value text-success"><?= $satisfacao_positiva ?></div>
                                        <div class="metric-label">Positivas</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="metric-box">
                                        <div class="metric-value text-warning"><?= $satisfacao_neutra ?></div>
                                        <div class="metric-label">Neutras</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="metric-box">
                                        <div class="metric-value text-danger"><?= $satisfacao_negativa ?></div>
                                        <div class="metric-label">Negativas</div>
                                    </div>
                                </div>
                            </div>
                            <?php
                            $nota_atend_bv = $titulo['nota_atendimento_bv'] ?? 0;
                            if ($nota_consultor > 0 || $nota_atend_bv > 0):
                            ?>
                            <div class="d-flex justify-content-around mt-1" style="font-size: 10px;">
                                <?php if ($nota_consultor > 0): ?>
                                <div class="text-center">
                                    <small class="text-muted">Consultor:</small>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star-fill <?= $i <= $nota_consultor ? 'text-warning' : 'text-muted' ?>" style="font-size: 11px;"></i>
                                    <?php endfor; ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($nota_atend_bv > 0): ?>
                                <div class="text-center">
                                    <small class="text-muted">Atendimento:</small>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star-fill <?= $i <= $nota_atend_bv ? 'text-info' : 'text-muted' ?>" style="font-size: 11px;"></i>
                                    <?php endfor; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($precisa_followup && $titulo['status'] !== 'concluido'): ?>
        <!-- Alerta de Follow-up -->
        <div class="alert alert-warning d-flex align-items-center justify-content-between py-2 mb-2" style="font-size: 12px;">
            <div>
                <i class="bi bi-bell-fill me-2"></i>
                <strong>Nova tentativa necessária!</strong>
                Última tentativa: <?= ucfirst(str_replace('_', ' ', $ultima_tentativa['resultado'])) ?>
                há <?= $horas_desde_ultima ?> hora<?= $horas_desde_ultima != 1 ? 's' : '' ?>
            </div>
            <div class="d-flex gap-2">
                <?php if (!empty($titulo['telefone'])): ?>
                <a href="tel:<?= preg_replace('/[^0-9]/', '', $titulo['telefone']) ?>" class="btn btn-sm btn-outline-primary <?= $sugestao_contato === 'telefone' ? 'btn-primary text-white' : '' ?>">
                    <i class="bi bi-telephone"></i> Ligar
                </a>
                <a href="https://wa.me/55<?= preg_replace('/[^0-9]/', '', $titulo['telefone']) ?>" target="_blank" class="btn btn-sm btn-outline-success <?= $sugestao_contato === 'whatsapp' ? 'btn-success text-white' : '' ?>">
                    <i class="bi bi-whatsapp"></i> WhatsApp
                </a>
                <?php endif; ?>
                <?php if (!empty($titulo['email'])): ?>
                <a href="mailto:<?= htmlspecialchars($titulo['email']) ?>" class="btn btn-sm btn-outline-info <?= $sugestao_contato === 'email' ? 'btn-info text-white' : '' ?>">
                    <i class="bi bi-envelope"></i> E-mail
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main 3-Column Content -->
        <div class="main-container">
            <!-- LEFT: Dados do Cliente -->
            <div class="left-column">
                <div class="card-info">
                    <div class="card-header"><h6><i class="bi bi-person-badge"></i> Dados do Cliente</h6></div>
                    <div class="card-body p-2">
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
                                <?php if (!empty($titulo['telefone'])): ?>
                                <a href="tel:<?= preg_replace('/[^0-9]/', '', $titulo['telefone'] ?? '') ?>" class="text-decoration-none">
                                    <?= formatarTelefone($titulo['telefone']) ?>
                                </a>
                                <button class="btn btn-sm btn-outline-success py-0 px-1 ms-1" onclick="copiarTexto('<?= preg_replace('/[^0-9]/', '', $titulo['telefone'] ?? '') ?>')" title="Copiar">
                                    <i class="bi bi-clipboard" style="font-size: 10px;"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary py-0 px-1 ms-1" data-bs-toggle="modal" data-bs-target="#modalTelefone" title="Editar">
                                    <i class="bi bi-pencil" style="font-size: 10px;"></i>
                                </button>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <button class="btn btn-sm btn-warning py-0 px-2 ms-1" data-bs-toggle="modal" data-bs-target="#modalTelefone">
                                    <i class="bi bi-plus"></i> Adicionar
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">E-mail</div>
                            <div class="info-value">
                                <?php if (!empty($titulo['email'])): ?>
                                <a href="mailto:<?= htmlspecialchars($titulo['email'] ?? '') ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($titulo['email']) ?>
                                </a>
                                <button class="btn btn-sm btn-outline-success py-0 px-1 ms-1" onclick="copiarTexto('<?= htmlspecialchars($titulo['email'] ?? '') ?>')" title="Copiar">
                                    <i class="bi bi-clipboard" style="font-size: 10px;"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary py-0 px-1 ms-1" data-bs-toggle="modal" data-bs-target="#modalEmail" title="Editar">
                                    <i class="bi bi-pencil" style="font-size: 10px;"></i>
                                </button>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <button class="btn btn-sm btn-warning py-0 px-2 ms-1" data-bs-toggle="modal" data-bs-target="#modalEmail">
                                    <i class="bi bi-plus"></i> Adicionar
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Data Venda</div>
                            <div class="info-value"><?= formatarData($titulo['data_venda'], 'd/m/Y H:i') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Tipo Título</div>
                            <div class="info-value"><?= htmlspecialchars($titulo['tipo_titulo'] ?? '-') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Valor</div>
                            <div class="info-value text-success fw-bold"><?= formatarMoeda($titulo['valor_total']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Pagamento</div>
                            <div class="info-value">
                                <?= htmlspecialchars($titulo['forma_pagamento'] ?? '-') ?>
                                <?php if (!empty($titulo['pagamento_obs'])): ?>
                                <span class="badge bg-info" style="font-size: 9px;"><?= htmlspecialchars($titulo['pagamento_obs']) ?></span>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-primary py-0 px-1 ms-1" data-bs-toggle="modal" data-bs-target="#modalInfoExtra" onclick="abrirModalInfo('pagamento')" title="Adicionar info">
                                    <i class="bi bi-plus-circle" style="font-size: 10px;"></i>
                                </button>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Consultor</div>
                            <div class="info-value">
                                <?= htmlspecialchars($titulo['promotor'] ?? '-') ?>
                                <?php if (!empty($titulo['promotor_obs'])): ?>
                                <span class="badge bg-info" style="font-size: 9px;"><?= htmlspecialchars($titulo['promotor_obs']) ?></span>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-primary py-0 px-1 ms-1" data-bs-toggle="modal" data-bs-target="#modalInfoExtra" onclick="abrirModalInfo('consultor')" title="Adicionar info">
                                    <i class="bi bi-plus-circle" style="font-size: 10px;"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tentativas -->
                <div class="card-info">
                    <div class="card-header d-flex justify-content-between">
                        <h6 class="mb-0"><i class="bi bi-clock-history"></i> Tentativas</h6>
                        <span class="badge bg-light text-dark" style="font-size: 10px;"><?= count($tentativas) ?></span>
                    </div>
                    <div class="card-body p-2" style="max-height: 150px; overflow-y: auto;">
                        <?php if (empty($tentativas)): ?>
                            <p class="text-muted text-center small mb-0">Nenhuma tentativa</p>
                        <?php else: ?>
                            <?php foreach ($tentativas as $t): ?>
                            <div class="tentativa-item">
                                <div class="d-flex justify-content-between">
                                    <span class="badge <?= $t['resultado'] === 'atendeu' ? 'bg-success' : 'bg-warning' ?>" style="font-size: 9px;">
                                        <?= ucfirst($t['tipo_tentativa']) ?>
                                    </span>
                                    <small class="text-muted" style="font-size: 9px;"><?= formatarData($t['criado_em']) ?></small>
                                </div>
                                <div class="mt-1"><strong style="font-size: 10px;"><?= ucfirst(str_replace('_', ' ', $t['resultado'])) ?></strong></div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Atividades -->
                <div class="card-info">
                    <div class="card-header d-flex justify-content-between">
                        <h6 class="mb-0"><i class="bi bi-activity"></i> Atividades</h6>
                        <span class="badge bg-light text-dark" style="font-size: 10px;"><?= count($logs) ?></span>
                    </div>
                    <div class="card-body p-2" style="max-height: 150px; overflow-y: auto;">
                        <?php if (empty($logs)): ?>
                            <p class="text-muted text-center small mb-0">Nenhuma atividade</p>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <div class="log-item <?= $log['tipo_log'] ?>">
                                <small class="text-muted"><?= formatarData($log['criado_em']) ?></small>
                                - <?= htmlspecialchars(getNomeAmigavel($log['etapa_codigo'], $log['tipo_log'], $nomes_amigaveis)) ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- CENTER: Checklist -->
            <div class="center-column">
                <!-- Search -->
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Pesquisar pergunta...">
                </div>

                <?php if (empty($etapas)): ?>
                <div class="alert alert-warning">
                    <h6><i class="bi bi-exclamation-triangle"></i> Checklist não configurado</h6>
                    <p class="mb-0">Execute o script SQL <code>update_database_v2.sql</code></p>
                </div>
                <?php else: ?>

                <?php foreach ($secoes_checklist as $secao_key => $secao):
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
                            <span class="badge <?= $completos_sec === $total_sec ? 'bg-success' : 'bg-secondary' ?>" style="font-size: 10px;"><?= $completos_sec ?>/<?= $total_sec ?></span>
                            <i class="bi bi-chevron-down" style="font-size: 12px;"></i>
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
                            <?php
                                // Determinar resposta atual do item
                                $resposta_item = '';
                                if (isset($interacoes[$etapa['codigo']])) {
                                    $vt = $interacoes[$etapa['codigo']]['valor_texto'] ?? '';
                                    if (in_array($vt, ['Positivo', 'Sim', 'Sim, confirmado', 'Sim, correto'])) {
                                        $resposta_item = 'Positivo';
                                    } elseif (in_array($vt, ['Negativo', 'Não', 'Dados incorretos', 'Não, corrigido'])) {
                                        $resposta_item = 'Negativo';
                                    } elseif ($vt === 'Neutro') {
                                        $resposta_item = 'Neutro';
                                    }
                                }
                            ?>
                            <div class="checklist-item <?= $val_cb ? 'completed' : '' ?>" data-codigo="<?= $etapa['codigo'] ?>" data-search="<?= strtolower(htmlspecialchars($etapa['titulo'] . ' ' . ($etapa['descricao'] ?? ''))) ?>">
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

                                <?php
                                // Só mostrar radios de resposta para itens checkbox-only (não para campos de texto/numero/data)
                                $mostrar_radios = empty($etapa['tipo_campo']) || $etapa['tipo_campo'] === 'checkbox';
                                if ($mostrar_radios):
                                ?>
                                <!-- Response Radio: Positivo/Neutro/Negativo -->
                                <div class="response-radio-inline" data-codigo="<?= $etapa['codigo'] ?>" data-resposta-atual="<?= htmlspecialchars($resposta_item) ?>">
                                    <div class="form-check">
                                        <input class="form-check-input response-radio" type="radio"
                                               name="resp_<?= $etapa['codigo'] ?>" id="resp_pos_<?= $etapa['codigo'] ?>"
                                               value="Positivo" data-codigo="<?= $etapa['codigo'] ?>"
                                               <?= $resposta_item === 'Positivo' ? 'checked' : '' ?>>
                                        <label class="form-check-label text-success" for="resp_pos_<?= $etapa['codigo'] ?>">
                                            <i class="bi bi-emoji-smile"></i> +
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input response-radio" type="radio"
                                               name="resp_<?= $etapa['codigo'] ?>" id="resp_neu_<?= $etapa['codigo'] ?>"
                                               value="Neutro" data-codigo="<?= $etapa['codigo'] ?>"
                                               <?= $resposta_item === 'Neutro' ? 'checked' : '' ?>>
                                        <label class="form-check-label text-warning" for="resp_neu_<?= $etapa['codigo'] ?>">
                                            <i class="bi bi-emoji-neutral"></i> ~
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input response-radio" type="radio"
                                               name="resp_<?= $etapa['codigo'] ?>" id="resp_neg_<?= $etapa['codigo'] ?>"
                                               value="Negativo" data-codigo="<?= $etapa['codigo'] ?>"
                                               <?= $resposta_item === 'Negativo' ? 'checked' : '' ?>>
                                        <label class="form-check-label text-danger" for="resp_neg_<?= $etapa['codigo'] ?>">
                                            <i class="bi bi-emoji-frown"></i> -
                                        </label>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($etapa['descricao']): ?>
                                <div class="item-description"><?= htmlspecialchars($etapa['descricao']) ?></div>
                                <?php endif; ?>

                                <?php if ($etapa['tipo_campo'] === 'select' && isset($opcoes['opcoes'])): ?>
                                    <?php
                                    $opts = $opcoes['opcoes'];
                                    $binary_keywords = ['Sim', 'Não', 'Positivo', 'Negativo', 'Neutro', 'Atendido', 'Retornar'];
                                    $is_binary = count($opts) <= 4 && count(array_intersect($opts, $binary_keywords)) > 0;
                                    ?>
                                    <?php if ($is_binary): ?>
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
                                    <input type="number" min="0" class="form-control form-control-sm checklist-numero" data-codigo="<?= $etapa['codigo'] ?>" value="<?= htmlspecialchars($val_num) ?>" placeholder="0">
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
                                        <i class="bi bi-clipboard"></i>
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
            </div>

            <!-- RIGHT: Registro Pós-Ligação -->
            <div class="right-column">
                <!-- Registrar Tentativa -->
                <div class="card-info">
                    <div class="card-header"><h6><i class="bi bi-telephone-plus"></i> Registrar Tentativa</h6></div>
                    <div class="card-body p-2">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small mb-1">Tipo</label>
                                <select class="form-select form-select-sm" id="tipo_tentativa">
                                    <option value="ligacao">Ligação</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="email">E-mail</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1">Resultado</label>
                                <select class="form-select form-select-sm" id="resultado_tentativa">
                                    <option value="atendeu">Atendeu</option>
                                    <option value="so_chamou">Só chamou</option>
                                    <option value="nao_atendeu">Não atendeu</option>
                                    <option value="desligou">Desligou</option>
                                    <option value="caixa_postal">Caixa postal</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="form-label small mb-1">Telefone</label>
                            <input type="text" class="form-control form-control-sm" id="telefone_tentativa" value="<?= htmlspecialchars($titulo['telefone'] ?? '') ?>">
                        </div>
                        <div class="mt-2">
                            <label class="form-label small mb-1">Observação</label>
                            <textarea class="form-control form-control-sm" id="obs_tentativa" rows="2"></textarea>
                        </div>
                        <button class="btn btn-primary btn-sm w-100 mt-2" onclick="registrarTentativa()">
                            <i class="bi bi-plus-lg"></i> Registrar
                        </button>
                    </div>
                </div>

                <!-- Registro Pós-Ligação (etapas reg_) -->
                <?php
                $etapas_registro = array_filter($etapas, fn($e) => strpos($e['codigo'], 'reg_') === 0);
                if (!empty($etapas_registro)):
                    $total_reg = count($etapas_registro);
                    $completos_reg = 0;
                    foreach ($etapas_registro as $et) {
                        if (isset($interacoes[$et['codigo']]) && $interacoes[$et['codigo']]['valor_checkbox']) $completos_reg++;
                    }
                ?>
                <div class="card-info">
                    <div class="card-header d-flex justify-content-between">
                        <h6 class="mb-0"><i class="bi bi-journal-check"></i> Registro Pós-Ligação</h6>
                        <span class="badge bg-light text-dark" style="font-size: 10px;"><?= $completos_reg ?>/<?= $total_reg ?></span>
                    </div>
                    <div class="card-body p-2">
                        <?php foreach ($etapas_registro as $etapa):
                            $val_cb = isset($interacoes[$etapa['codigo']]) ? $interacoes[$etapa['codigo']]['valor_checkbox'] : 0;
                            $val_txt = isset($interacoes[$etapa['codigo']]) ? ($interacoes[$etapa['codigo']]['valor_texto'] ?? '') : '';
                            $val_num = isset($interacoes[$etapa['codigo']]) ? ($interacoes[$etapa['codigo']]['valor_numero'] ?? '') : '';
                            $opcoes = $etapa['opcoes_campo'] ? json_decode($etapa['opcoes_campo'], true) : [];
                            // Determinar resposta atual
                            $resposta_reg = '';
                            if (isset($interacoes[$etapa['codigo']])) {
                                $vtr = $interacoes[$etapa['codigo']]['valor_texto'] ?? '';
                                if (in_array($vtr, ['Positivo', 'Sim', 'Sim, confirmado', 'Sim, correto'])) $resposta_reg = 'Positivo';
                                elseif (in_array($vtr, ['Negativo', 'Não', 'Dados incorretos', 'Não, corrigido'])) $resposta_reg = 'Negativo';
                                elseif ($vtr === 'Neutro') $resposta_reg = 'Neutro';
                            }
                        ?>
                        <div class="checklist-item <?= $val_cb ? 'completed' : '' ?>" data-codigo="<?= $etapa['codigo'] ?>" style="padding: 6px;">
                            <div class="form-check">
                                <input class="form-check-input checklist-checkbox" type="checkbox"
                                       id="check_<?= $etapa['codigo'] ?>" data-codigo="<?= $etapa['codigo'] ?>"
                                       <?= $val_cb ? 'checked' : '' ?>>
                                <label class="form-check-label" for="check_<?= $etapa['codigo'] ?>" style="font-size: 11px;">
                                    <?= htmlspecialchars($etapa['titulo']) ?>
                                    <?php if ($etapa['obrigatorio']): ?><span class="text-danger">*</span><?php endif; ?>
                                </label>
                                <span class="saving-indicator" id="saving_<?= $etapa['codigo'] ?>"><i class="bi bi-arrow-repeat spin"></i></span>
                                <span class="saved-indicator" id="saved_<?= $etapa['codigo'] ?>"><i class="bi bi-check"></i></span>
                            </div>

                            <?php
                            // Só mostrar radios para itens checkbox-only
                            $mostrar_radios_reg = empty($etapa['tipo_campo']) || $etapa['tipo_campo'] === 'checkbox';
                            if ($mostrar_radios_reg):
                            ?>
                            <!-- Response Radio para registro -->
                            <div class="response-radio-inline" style="margin-left: 18px;" data-codigo="<?= $etapa['codigo'] ?>" data-resposta-atual="<?= htmlspecialchars($resposta_reg) ?>">
                                <div class="form-check">
                                    <input class="form-check-input response-radio" type="radio" name="resp_<?= $etapa['codigo'] ?>" value="Positivo" data-codigo="<?= $etapa['codigo'] ?>" <?= $resposta_reg === 'Positivo' ? 'checked' : '' ?>>
                                    <label class="form-check-label text-success"><i class="bi bi-emoji-smile"></i></label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input response-radio" type="radio" name="resp_<?= $etapa['codigo'] ?>" value="Neutro" data-codigo="<?= $etapa['codigo'] ?>" <?= $resposta_reg === 'Neutro' ? 'checked' : '' ?>>
                                    <label class="form-check-label text-warning"><i class="bi bi-emoji-neutral"></i></label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input response-radio" type="radio" name="resp_<?= $etapa['codigo'] ?>" value="Negativo" data-codigo="<?= $etapa['codigo'] ?>" <?= $resposta_reg === 'Negativo' ? 'checked' : '' ?>>
                                    <label class="form-check-label text-danger"><i class="bi bi-emoji-frown"></i></label>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($etapa['tipo_campo'] === 'select' && isset($opcoes['opcoes'])): ?>
                                <?php $opts = $opcoes['opcoes']; $binary_keywords = ['Sim', 'Não', 'Positivo', 'Negativo', 'Neutro']; $is_binary = count($opts) <= 4 && count(array_intersect($opts, $binary_keywords)) > 0; ?>
                                <?php if ($is_binary): ?>
                                <div class="radio-group" style="margin-left: 18px;">
                                    <?php foreach ($opts as $i => $opt): ?>
                                    <div class="form-check">
                                        <input class="form-check-input checklist-radio" type="radio"
                                               name="radio_<?= $etapa['codigo'] ?>" id="radio_<?= $etapa['codigo'] ?>_<?= $i ?>"
                                               value="<?= htmlspecialchars($opt) ?>" data-codigo="<?= $etapa['codigo'] ?>"
                                               <?= $val_txt === $opt ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="radio_<?= $etapa['codigo'] ?>_<?= $i ?>" style="font-size: 10px;"><?= htmlspecialchars($opt) ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="field-input" style="margin-left: 18px;">
                                    <select class="form-select form-select-sm checklist-select" data-codigo="<?= $etapa['codigo'] ?>" style="font-size: 11px;">
                                        <option value="">Selecione...</option>
                                        <?php foreach ($opts as $opt): ?>
                                        <option value="<?= htmlspecialchars($opt) ?>" <?= $val_txt === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                            <?php elseif ($etapa['tipo_campo'] === 'textarea'): ?>
                            <div class="field-input" style="margin-left: 18px;">
                                <textarea class="form-control form-control-sm checklist-textarea" data-codigo="<?= $etapa['codigo'] ?>" rows="2" placeholder="Digite..." style="font-size: 11px;"><?= htmlspecialchars($val_txt) ?></textarea>
                            </div>
                            <?php elseif ($etapa['tipo_campo'] === 'numero'): ?>
                            <div class="field-input" style="margin-left: 18px;">
                                <input type="number" min="0" class="form-control form-control-sm checklist-numero" data-codigo="<?= $etapa['codigo'] ?>" value="<?= htmlspecialchars($val_num) ?>" placeholder="0" style="font-size: 11px;">
                            </div>
                            <?php elseif ($etapa['tipo_campo'] === 'rating'): ?>
                            <div class="rating-stars" data-codigo="<?= $etapa['codigo'] ?>" style="margin-left: 18px;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star-fill <?= $i <= (int)$val_num ? 'active' : '' ?>" data-value="<?= $i ?>" style="font-size: 14px;"></i>
                                <?php endfor; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Feedback do Atendente Boas-Vindas -->
                <div class="card-info">
                    <div class="card-header"><h6><i class="bi bi-chat-heart"></i> Feedback Boas-Vindas</h6></div>
                    <div class="card-body p-2">
                        <div class="mb-2">
                            <label class="form-label small mb-1">Sua avaliação do atendimento</label>
                            <div class="rating-stars" id="rating_atendente_bv">
                                <?php
                                $nota_atend_bv = $titulo['nota_atendimento_bv'] ?? 0;
                                for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star-fill <?= $i <= $nota_atend_bv ? 'active' : '' ?>" data-value="<?= $i ?>" style="font-size: 18px; cursor: pointer;"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-1">Feedback do atendimento</label>
                            <textarea class="form-control form-control-sm" id="feedback_atendente_bv" rows="2" placeholder="Como foi a ligação? Cliente receptivo? Dificuldades?" style="font-size: 11px;"><?= htmlspecialchars($titulo['feedback_atendente_bv'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="form-label small mb-1">Classificação geral</label>
                            <div class="d-flex gap-2 flex-wrap">
                                <?php
                                $classif_bv = $titulo['classificacao_bv'] ?? '';
                                $classifs = ['Excelente' => 'success', 'Bom' => 'primary', 'Regular' => 'warning', 'Difícil' => 'danger'];
                                foreach ($classifs as $label => $color): ?>
                                <div class="form-check">
                                    <input class="form-check-input classif-bv-radio" type="radio" name="classif_bv" id="classif_<?= strtolower($label) ?>" value="<?= $label ?>" <?= $classif_bv === $label ? 'checked' : '' ?>>
                                    <label class="form-check-label text-<?= $color ?>" for="classif_<?= strtolower($label) ?>" style="font-size: 11px;"><?= $label ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <small class="text-muted" id="feedback_bv_status" style="font-size: 10px;"></small>
                    </div>
                </div>

                <!-- Observações Gerais -->
                <div class="card-info">
                    <div class="card-header"><h6><i class="bi bi-journal-text"></i> Observações Gerais</h6></div>
                    <div class="card-body p-2">
                        <textarea class="form-control form-control-sm" id="observacoes_gerais" rows="4" placeholder="Adicione observações importantes..." style="font-size: 11px;"><?= htmlspecialchars($titulo['observacoes'] ?? '') ?></textarea>
                        <small class="text-muted" id="obs_status" style="font-size: 10px;"></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Telefone -->
    <div class="modal fade" id="modalTelefone" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-telephone"></i> Telefone</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="input_telefone" value="<?= htmlspecialchars($titulo['telefone'] ?? '') ?>" placeholder="(00) 00000-0000">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="salvarTelefone()">
                        <i class="bi bi-save"></i> Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Email -->
    <div class="modal fade" id="modalEmail" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-envelope"></i> E-mail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="input_email" value="<?= htmlspecialchars($titulo['email'] ?? '') ?>" placeholder="email@exemplo.com">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="salvarEmail()">
                        <i class="bi bi-save"></i> Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Info Extra -->
    <div class="modal fade" id="modalInfoExtra" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalInfoExtraTitulo"><i class="bi bi-info-circle"></i> Informação Extra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="info_extra_tipo">
                    <div class="mb-3">
                        <label class="form-label" id="info_extra_label">Observação</label>
                        <input type="text" class="form-control" id="info_extra_valor" placeholder="Ex: Pagou no PIX, Vendedor João...">
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removerInfoExtra()" id="btnRemoverInfo" style="display: none;">
                        <i class="bi bi-trash"></i> Remover
                    </button>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary btn-sm" onclick="salvarInfoExtra()">
                            <i class="bi bi-save"></i> Salvar
                        </button>
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

    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        document.querySelectorAll('.checklist-item[data-search]').forEach(item => {
            const searchText = item.dataset.search;
            if (query === '' || searchText.includes(query)) {
                item.classList.remove('hidden');
            } else {
                item.classList.add('hidden');
            }
        });
    });

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
            btn.innerHTML = '<i class="bi bi-check"></i>';
            setTimeout(() => btn.innerHTML = '<i class="bi bi-clipboard"></i>', 2000);
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
            if (hasValue) {
                const cb = document.getElementById('check_' + codigo);
                if (cb && !cb.checked) {
                    cb.checked = true;
                    salvarInteracao(codigo, 1, this.value, null);
                } else {
                    salvarInteracao(codigo, null, this.value, null);
                }
                // Atualizar satisfação dinamicamente
                atualizarSatisfacao(this.value);
            } else {
                salvarInteracao(codigo, null, this.value, null);
            }
        });
    });

    document.querySelectorAll('.checklist-radio').forEach(el => {
        el.addEventListener('change', function() {
            const codigo = this.dataset.codigo;
            const cb = document.getElementById('check_' + codigo);
            if (cb && !cb.checked) {
                cb.checked = true;
                salvarInteracao(codigo, 1, this.value, null);
            } else {
                salvarInteracao(codigo, null, this.value, null);
            }
            // Atualizar satisfação dinamicamente
            atualizarSatisfacao(this.value);
        });
    });

    document.querySelectorAll('.rating-stars').forEach(container => {
        // Pular o rating de feedback BV (tem handler próprio)
        if (container.id === 'rating_atendente_bv') return;

        container.querySelectorAll('i').forEach(star => {
            star.addEventListener('click', function() {
                const val = this.dataset.value;
                const codigo = this.parentElement.dataset.codigo;
                if (!codigo) return; // Proteção extra
                this.parentElement.querySelectorAll('i').forEach((s, i) => {
                    s.classList.toggle('active', i < val);
                });
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

    // Salvar telefone
    function salvarTelefone() {
        const telefone = document.getElementById('input_telefone').value;
        $.ajax({
            url: baseUrl + '/api_salvar_boasvindas.php',
            method: 'POST',
            data: { id: boasVindasId, telefone: telefone },
            success: function(response) {
                if (response.success) {
                    showToast('Telefone salvo!');
                    bootstrap.Modal.getInstance(document.getElementById('modalTelefone')).hide();
                    setTimeout(() => location.reload(), 500);
                } else {
                    showToast('Erro: ' + response.error, 'danger');
                }
            },
            error: () => showToast('Erro ao salvar', 'danger')
        });
    }

    // Modal info extra
    const infoPagamentoAtual = '<?= addslashes($titulo['pagamento_obs'] ?? '') ?>';
    const infoConsultorAtual = '<?= addslashes($titulo['promotor_obs'] ?? '') ?>';

    function abrirModalInfo(tipo) {
        document.getElementById('info_extra_tipo').value = tipo;
        let valorAtual = '';

        if (tipo === 'pagamento') {
            document.getElementById('modalInfoExtraTitulo').innerHTML = '<i class="bi bi-credit-card"></i> Info Pagamento';
            document.getElementById('info_extra_label').textContent = 'Observação sobre pagamento';
            document.getElementById('info_extra_valor').placeholder = 'Ex: Pagou no PIX, Parcelou em 3x...';
            valorAtual = infoPagamentoAtual;
        } else {
            document.getElementById('modalInfoExtraTitulo').innerHTML = '<i class="bi bi-person"></i> Info Consultor';
            document.getElementById('info_extra_label').textContent = 'Observação sobre o consultor';
            document.getElementById('info_extra_valor').placeholder = 'Ex: Vendedor real: João, Indicação...';
            valorAtual = infoConsultorAtual;
        }

        document.getElementById('info_extra_valor').value = valorAtual;
        document.getElementById('btnRemoverInfo').style.display = valorAtual ? 'inline-block' : 'none';
    }

    function salvarInfoExtra() {
        const tipo = document.getElementById('info_extra_tipo').value;
        const valor = document.getElementById('info_extra_valor').value;
        const campo = tipo === 'pagamento' ? 'pagamento_obs' : 'promotor_obs';

        $.ajax({
            url: baseUrl + '/api_salvar_boasvindas.php',
            method: 'POST',
            data: { id: boasVindasId, [campo]: valor },
            success: function(response) {
                if (response.success) {
                    showToast('Informação salva!');
                    bootstrap.Modal.getInstance(document.getElementById('modalInfoExtra')).hide();
                    setTimeout(() => location.reload(), 500);
                } else {
                    showToast('Erro: ' + response.error, 'danger');
                }
            },
            error: () => showToast('Erro ao salvar', 'danger')
        });
    }

    function removerInfoExtra() {
        if (!confirm('Deseja remover esta observação?')) return;

        const tipo = document.getElementById('info_extra_tipo').value;
        const campo = tipo === 'pagamento' ? 'pagamento_obs' : 'promotor_obs';

        $.ajax({
            url: baseUrl + '/api_salvar_boasvindas.php',
            method: 'POST',
            data: { id: boasVindasId, [campo]: '' },
            success: function(response) {
                if (response.success) {
                    showToast('Observação removida!');
                    bootstrap.Modal.getInstance(document.getElementById('modalInfoExtra')).hide();
                    setTimeout(() => location.reload(), 500);
                } else {
                    showToast('Erro: ' + response.error, 'danger');
                }
            },
            error: () => showToast('Erro ao remover', 'danger')
        });
    }

    // Atualizar satisfação dinamicamente (com suporte a trocar resposta)
    function atualizarSatisfacao(novoValor, valorAnterior = null) {
        const positivas = ['Positivo', 'Sim', 'Sim, confirmado', 'Sim, correto'];
        const negativas = ['Negativo', 'Não', 'Dados incorretos', 'Não, corrigido'];
        const neutras = ['Neutro'];

        let pos = parseInt(document.querySelector('.metric-value.text-success')?.textContent || 0);
        let neg = parseInt(document.querySelector('.metric-value.text-danger')?.textContent || 0);
        let neu = parseInt(document.querySelector('.metric-value.text-warning')?.textContent || 0);

        // Subtrair valor anterior se houver
        if (valorAnterior) {
            if (positivas.includes(valorAnterior) || valorAnterior === 'Positivo') pos = Math.max(0, pos - 1);
            else if (negativas.includes(valorAnterior) || valorAnterior === 'Negativo') neg = Math.max(0, neg - 1);
            else if (neutras.includes(valorAnterior) || valorAnterior === 'Neutro') neu = Math.max(0, neu - 1);
        }

        // Adicionar novo valor
        if (positivas.includes(novoValor) || novoValor === 'Positivo') pos++;
        else if (negativas.includes(novoValor) || novoValor === 'Negativo') neg++;
        else if (neutras.includes(novoValor) || novoValor === 'Neutro') neu++;

        const total = pos + neg + neu;
        const score = total > 0 ? Math.round((pos / total) * 100) : 0;

        // Atualizar UI
        const posEl = document.querySelector('.metric-value.text-success');
        const negEl = document.querySelector('.metric-value.text-danger');
        const neuEl = document.querySelector('.metric-value.text-warning');
        const scoreEl = document.querySelector('.satisfaction-score');
        const needleEl = document.querySelector('.satisfaction-needle');

        if (posEl) posEl.textContent = pos;
        if (negEl) negEl.textContent = neg;
        if (neuEl) neuEl.textContent = neu;
        if (scoreEl) {
            scoreEl.textContent = score + '%';
            scoreEl.className = 'satisfaction-score ' + (score >= 70 ? 'text-success' : (score >= 40 ? 'text-warning' : 'text-danger'));
        }
        if (needleEl) needleEl.style.transform = 'rotate(' + ((score - 50) * 1.8) + 'deg)';
    }

    // Salvar email
    function salvarEmail() {
        const email = document.getElementById('input_email').value;
        $.ajax({
            url: baseUrl + '/api_salvar_boasvindas.php',
            method: 'POST',
            data: { id: boasVindasId, email: email },
            success: function(response) {
                if (response.success) {
                    showToast('E-mail salvo!');
                    bootstrap.Modal.getInstance(document.getElementById('modalEmail')).hide();
                    setTimeout(() => location.reload(), 500);
                } else {
                    showToast('Erro: ' + response.error, 'danger');
                }
            },
            error: () => showToast('Erro ao salvar', 'danger')
        });
    }

    // Marcar como pendente
    function marcarPendente() {
        if (!confirm('Marcar este atendimento como pendente?')) return;
        $.ajax({
            url: baseUrl + '/api_salvar_boasvindas.php',
            method: 'POST',
            data: { id: boasVindasId, status: 'pendente' },
            success: () => { showToast('Marcado como pendente!'); setTimeout(() => location.reload(), 500); },
            error: () => showToast('Erro', 'danger')
        });
    }

    // Iniciar atendimento
    function iniciarAtendimento() {
        $.ajax({
            url: baseUrl + '/api_salvar_boasvindas.php',
            method: 'POST',
            data: { id: boasVindasId, status: 'em_andamento' },
            success: () => { showToast('Atendimento iniciado!'); setTimeout(() => location.reload(), 500); },
            error: () => showToast('Erro', 'danger')
        });
    }

    // Response radio handlers (Positivo/Neutro/Negativo para cada item)
    document.querySelectorAll('.response-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            const codigo = this.dataset.codigo;
            const novoValor = this.value;

            // Obter o valor anterior do container (armazenado em data-resposta-atual)
            const container = this.closest('.response-radio-inline');
            const valorAnterior = container?.dataset.respostaAtual || null;

            // Atualizar o valor armazenado
            if (container) container.dataset.respostaAtual = novoValor;

            // Marcar checkbox automaticamente
            const cb = document.getElementById('check_' + codigo);
            if (cb && !cb.checked) {
                cb.checked = true;
                salvarInteracao(codigo, 1, novoValor, null);
            } else {
                salvarInteracao(codigo, null, novoValor, null);
            }

            // Atualizar satisfação (passando valor anterior para subtrair)
            atualizarSatisfacao(novoValor, valorAnterior);
        });
    });

    // Feedback atendente boas-vindas
    document.getElementById('rating_atendente_bv').querySelectorAll('i').forEach(star => {
        star.addEventListener('click', function() {
            const val = this.dataset.value;
            this.parentElement.querySelectorAll('i').forEach((s, i) => {
                s.classList.toggle('active', i < val);
            });
            salvarFeedbackBV();
        });
    });

    document.getElementById('feedback_atendente_bv').addEventListener('blur', function() {
        salvarFeedbackBV();
    });

    // Armazenar classificação anterior para atualização de satisfação
    let classifAnterior = document.querySelector('.classif-bv-radio:checked')?.value || '';

    document.querySelectorAll('.classif-bv-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            const novaClassif = this.value;

            // Mapear classificação para satisfação
            const mapClassif = {
                'Excelente': 'Positivo',
                'Bom': 'Positivo',
                'Regular': 'Neutro',
                'Difícil': 'Negativo'
            };

            const valorAnterior = mapClassif[classifAnterior] || null;
            const novoValor = mapClassif[novaClassif] || null;

            // Atualizar satisfação
            if (novoValor) {
                atualizarSatisfacao(novoValor, valorAnterior);
            }

            classifAnterior = novaClassif;
            salvarFeedbackBV();
        });
    });

    function salvarFeedbackBV() {
        const status = document.getElementById('feedback_bv_status');
        status.textContent = 'Salvando...';

        const nota = document.querySelectorAll('#rating_atendente_bv i.active').length;
        const feedback = document.getElementById('feedback_atendente_bv').value;
        const classif = document.querySelector('.classif-bv-radio:checked')?.value || '';

        $.ajax({
            url: baseUrl + '/api_salvar_boasvindas.php',
            method: 'POST',
            data: {
                id: boasVindasId,
                nota_atendimento_bv: nota,
                feedback_atendente_bv: feedback,
                classificacao_bv: classif
            },
            success: () => { status.textContent = 'Salvo!'; setTimeout(() => status.textContent = '', 2000); },
            error: () => status.textContent = 'Erro ao salvar'
        });
    }
    </script>
</body>
</html>
