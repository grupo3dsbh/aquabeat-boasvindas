<?php
require_once 'config.php';
Auth::requireLogin();

$db_bv = Database::getConnectionBV();
$db_api = Database::getConnectionAPI();

$boas_vindas_id = $_GET['id'] ?? null;

if (!$boas_vindas_id) {
    header('Location: index.php');
    exit;
}

// Buscar boas-vindas
$stmt = $db_bv->prepare("
    SELECT bv.*, u.nome as nome_atendente
    FROM boas_vindas bv
    LEFT JOIN usuarios u ON bv.usuario_id = u.id
    WHERE bv.id = :id
");
$stmt->execute([':id' => $boas_vindas_id]);
$boas_vindas = $stmt->fetch();

if (!$boas_vindas) {
    die("Boas-vindas não encontrado!");
}

// Buscar tentativas
$stmt_tentativas = $db_bv->prepare("
    SELECT tc.*, u.nome as nome_usuario
    FROM tentativas_contato tc
    LEFT JOIN usuarios u ON tc.usuario_id = u.id
    WHERE tc.boas_vindas_id = :id
    ORDER BY tc.criado_em ASC
");
$stmt_tentativas->execute([':id' => $boas_vindas_id]);
$tentativas = $stmt_tentativas->fetchAll();

// Buscar logs
$stmt_logs = $db_bv->prepare("
    SELECT la.*, u.nome as nome_usuario
    FROM logs_atividades la
    LEFT JOIN usuarios u ON la.usuario_id = u.id
    WHERE la.boas_vindas_id = :id
    ORDER BY la.criado_em ASC
");
$stmt_logs->execute([':id' => $boas_vindas_id]);
$logs = $stmt_logs->fetchAll();

// Decodificar checklists
$checklists = [
    'preparacao' => json_decode($boas_vindas['checklist_preparacao'] ?? '[]', true) ?: [],
    'abertura' => json_decode($boas_vindas['checklist_abertura'] ?? '[]', true) ?: [],
    'validacao' => json_decode($boas_vindas['checklist_validacao'] ?? '[]', true) ?: [],
    'portal' => json_decode($boas_vindas['checklist_portal'] ?? '[]', true) ?: [],
    'atendimento' => json_decode($boas_vindas['checklist_atendimento'] ?? '[]', true) ?: [],
    'financeiro' => json_decode($boas_vindas['checklist_financeiro'] ?? '[]', true) ?: [],
    'informacoes' => json_decode($boas_vindas['checklist_informacoes'] ?? '[]', true) ?: [],
    'duvidas' => json_decode($boas_vindas['checklist_duvidas'] ?? '[]', true) ?: [],
    'ofertas' => json_decode($boas_vindas['checklist_ofertas'] ?? '[]', true) ?: [],
    'encerramento' => json_decode($boas_vindas['checklist_encerramento'] ?? '[]', true) ?: []
];

// Calcular progresso
$total_itens = 0;
$itens_completos = 0;
foreach ($checklists as $checklist) {
    $total_itens += 10; // Estimativa
    $itens_completos += count($checklist);
}
$progresso = $total_itens > 0 ? round(($itens_completos / $total_itens) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório - Boas-Vindas #<?= $boas_vindas_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: #f5f7fa;
        }
        .navbar {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .report-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .report-section h5 {
            color: #667eea;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .status-badge-lg {
            font-size: 16px;
            padding: 8px 15px;
        }
        .info-row {
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .timeline {
            position: relative;
            padding-left: 40px;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        .timeline-item:before {
            content: '';
            position: absolute;
            left: -32px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #667eea;
        }
        .timeline-item:after {
            content: '';
            position: absolute;
            left: -27px;
            top: 17px;
            width: 2px;
            height: calc(100% - 17px);
            background: #e9ecef;
        }
        .timeline-item:last-child:after {
            display: none;
        }
        .checklist-summary {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .checklist-summary i {
            font-size: 24px;
            margin-right: 15px;
        }
        .btn-print {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        @media print {
            .navbar, .btn-print, .no-print {
                display: none !important;
            }
            body {
                background: white;
            }
            .report-section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark no-print">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-hand-thumbs-up-fill"></i> Boas-Vindas Aquabeat
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-arrow-left"></i> Voltar ao Dashboard
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Header do Relatório -->
        <div class="report-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="bi bi-file-earmark-check-fill"></i> Relatório de Boas-Vindas</h2>
                    <h4 class="mt-3"><?= htmlspecialchars($boas_vindas['nome_cliente']) ?></h4>
                    <p class="mb-0">ID Cota: <strong><?= htmlspecialchars($boas_vindas['numero_titulo']) ?></strong></p>
                </div>
                <div class="col-md-4 text-end">
                    <?= badgeStatus($boas_vindas['status']) ?>
                    <?php if ($boas_vindas['status'] === 'concluido'): ?>
                        <div class="mt-2">
                            <small>Concluído em:<br>
                            <strong><?= formatarData($boas_vindas['concluido_em']) ?></strong></small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- Informações Gerais -->
                <div class="report-section">
                    <h5><i class="bi bi-info-circle-fill"></i> Informações Gerais</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-row">
                                <strong>Cliente:</strong><br>
                                <?= htmlspecialchars($boas_vindas['nome_cliente']) ?>
                            </div>
                            <div class="info-row">
                                <strong>Documento:</strong><br>
                                <?= formatarDocumento($boas_vindas['documento_cliente']) ?>
                            </div>
                            <div class="info-row">
                                <strong>Telefone:</strong><br>
                                <?= formatarTelefone($boas_vindas['telefone']) ?>
                            </div>
                            <div class="info-row">
                                <strong>Data da Venda:</strong><br>
                                <?= formatarData($boas_vindas['data_venda'], 'd/m/Y H:i') ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-row">
                                <strong>Tipo de Título:</strong><br>
                                <?= htmlspecialchars($boas_vindas['tipo_titulo']) ?>
                            </div>
                            <div class="info-row">
                                <strong>Valor Total:</strong><br>
                                <?= formatarMoeda($boas_vindas['valor_total']) ?>
                            </div>
                            <div class="info-row">
                                <strong>Forma de Pagamento:</strong><br>
                                <?= htmlspecialchars($boas_vindas['forma_pagamento']) ?>
                            </div>
                            <div class="info-row">
                                <strong>Promotor:</strong><br>
                                <?= htmlspecialchars($boas_vindas['promotor']) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Atendimento -->
                <div class="report-section">
                    <h5><i class="bi bi-person-check-fill"></i> Informações do Atendimento</h5>
                    
                    <div class="info-row">
                        <strong>Atendente:</strong><br>
                        <?= htmlspecialchars($boas_vindas['nome_atendente']) ?>
                    </div>
                    <div class="info-row">
                        <strong>Iniciado em:</strong><br>
                        <?= formatarData($boas_vindas['iniciado_em']) ?>
                    </div>
                    <?php if ($boas_vindas['concluido_em']): ?>
                    <div class="info-row">
                        <strong>Concluído em:</strong><br>
                        <?= formatarData($boas_vindas['concluido_em']) ?>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <strong>Tentativas de Contato:</strong><br>
                        <?= $boas_vindas['tentativas_contato'] ?> tentativa(s)
                    </div>
                </div>

                <!-- Resultados Coletados -->
                <div class="report-section">
                    <h5><i class="bi bi-clipboard-check-fill"></i> Resultados Coletados</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="checklist-summary">
                                <i class="bi bi-pc-display text-primary"></i>
                                <div>
                                    <strong>Acessou Portal:</strong><br>
                                    <?= $boas_vindas['acessou_portal'] ? '✅ Sim' : '❌ Não' ?>
                                </div>
                            </div>
                            
                            <div class="checklist-summary">
                                <i class="bi bi-key text-warning"></i>
                                <div>
                                    <strong>Resetou Senha:</strong><br>
                                    <?= $boas_vindas['resetou_senha'] ? '✅ Sim' : '❌ Não' ?>
                                </div>
                            </div>
                            
                            <div class="checklist-summary">
                                <i class="bi bi-currency-dollar text-success"></i>
                                <div>
                                    <strong>Sabia da Anuidade:</strong><br>
                                    <?= $boas_vindas['sabia_anuidade'] ? '✅ Sim' : '❌ Não' ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="checklist-summary">
                                <i class="bi bi-calendar-check text-info"></i>
                                <div>
                                    <strong>Agendou Visita:</strong><br>
                                    <?= $boas_vindas['agendou_primeira_visita'] ? '✅ Sim' : '❌ Não' ?>
                                    <?php if ($boas_vindas['data_agendamento']): ?>
                                        <br><small><?= formatarData($boas_vindas['data_agendamento'], 'd/m/Y H:i') ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="checklist-summary">
                                <i class="bi bi-whatsapp text-success"></i>
                                <div>
                                    <strong>Grupo WhatsApp:</strong><br>
                                    <?= $boas_vindas['adicionou_grupo_whatsapp'] ? '✅ Adicionado' : '❌ Não' ?>
                                </div>
                            </div>
                            
                            <div class="checklist-summary">
                                <i class="bi bi-envelope-check text-primary"></i>
                                <div>
                                    <strong>Resumo WhatsApp:</strong><br>
                                    <?= $boas_vindas['enviou_resumo_whatsapp'] ? '✅ Enviado' : '❌ Não' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feedback do Consultor -->
                <?php if ($boas_vindas['feedback_consultor'] || $boas_vindas['nota_atendimento_consultor']): ?>
                <div class="report-section">
                    <h5><i class="bi bi-star-fill"></i> Avaliação do Consultor</h5>
                    
                    <?php if ($boas_vindas['nota_atendimento_consultor']): ?>
                    <div class="mb-3">
                        <strong>Nota do Atendimento:</strong><br>
                        <span class="fs-4">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="bi bi-star<?= $i <= $boas_vindas['nota_atendimento_consultor'] ? '-fill text-warning' : ' text-muted' ?>"></i>
                            <?php endfor; ?>
                            (<?= $boas_vindas['nota_atendimento_consultor'] ?>/5)
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($boas_vindas['feedback_consultor']): ?>
                    <div>
                        <strong>Feedback:</strong><br>
                        <p class="mt-2"><?= nl2br(htmlspecialchars($boas_vindas['feedback_consultor'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Problemas Identificados -->
                <?php if ($boas_vindas['problema_pagamento_entrada']): ?>
                <div class="report-section">
                    <h5><i class="bi bi-exclamation-triangle-fill text-warning"></i> Problemas Identificados</h5>
                    
                    <div class="alert alert-warning">
                        <strong>Pagamento da Entrada:</strong><br>
                        <?= nl2br(htmlspecialchars($boas_vindas['problema_pagamento_entrada'])) ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Observações -->
                <?php if ($boas_vindas['observacoes']): ?>
                <div class="report-section">
                    <h5><i class="bi bi-pencil-square"></i> Observações</h5>
                    <p><?= nl2br(htmlspecialchars($boas_vindas['observacoes'])) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <!-- Timeline de Tentativas -->
                <?php if (count($tentativas) > 0): ?>
                <div class="report-section">
                    <h5><i class="bi bi-clock-history"></i> Histórico de Tentativas</h5>
                    
                    <div class="timeline">
                        <?php foreach ($tentativas as $tentativa): ?>
                        <div class="timeline-item">
                            <small class="text-muted"><?= formatarData($tentativa['criado_em'], 'd/m/Y H:i') ?></small>
                            <div class="mt-1">
                                <strong>
                                    <?php
                                    $icones = [
                                        'ligacao' => 'bi-telephone',
                                        'whatsapp' => 'bi-whatsapp',
                                        'email' => 'bi-envelope'
                                    ];
                                    ?>
                                    <i class="bi <?= $icones[$tentativa['tipo_tentativa']] ?>"></i>
                                    <?= ucfirst($tentativa['tipo_tentativa']) ?>
                                </strong>
                                <br>
                                <?php
                                $badges_resultado = [
                                    'atendeu' => 'success',
                                    'nao_atendeu' => 'warning',
                                    'caixa_postal' => 'secondary',
                                    'whatsapp_enviado' => 'info',
                                    'email_enviado' => 'info'
                                ];
                                ?>
                                <span class="badge bg-<?= $badges_resultado[$tentativa['resultado']] ?>">
                                    <?= str_replace('_', ' ', ucfirst($tentativa['resultado'])) ?>
                                </span>
                                <?php if ($tentativa['observacao']): ?>
                                    <br><small><?= htmlspecialchars($tentativa['observacao']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Logs de Atividades (para admin) -->
                <?php if (Auth::isAdmin() && count($logs) > 0): ?>
                <div class="report-section">
                    <h5><i class="bi bi-list-check"></i> Log de Atividades</h5>
                    
                    <div class="timeline">
                        <?php foreach ($logs as $log): ?>
                        <div class="timeline-item">
                            <small class="text-muted"><?= formatarData($log['criado_em'], 'd/m/Y H:i') ?></small>
                            <div class="mt-1">
                                <strong><?= htmlspecialchars($log['nome_usuario']) ?></strong>
                                <br>
                                <small><?= htmlspecialchars($log['descricao']) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Botão de Imprimir -->
    <button class="btn btn-primary btn-lg btn-print no-print" onclick="window.print()">
        <i class="bi bi-printer"></i> Imprimir
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
