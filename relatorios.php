<?php
require_once 'config.php';
Auth::requireAdmin();

$pagina_atual = 'relatorios';
$db = Database::getConnectionBV();

// Filtros de data
$periodo = $_GET['periodo'] ?? 'mes_atual';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');

switch ($periodo) {
    case 'mes_atual':
        $data_inicio = date('Y-m-01');
        $data_fim = date('Y-m-t');
        break;
    case 'mes_anterior':
        $data_inicio = date('Y-m-01', strtotime('first day of last month'));
        $data_fim = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'ultimos_30':
        $data_inicio = date('Y-m-d', strtotime('-30 days'));
        $data_fim = date('Y-m-d');
        break;
    case 'ultimos_90':
        $data_inicio = date('Y-m-d', strtotime('-90 days'));
        $data_fim = date('Y-m-d');
        break;
    case 'ano_atual':
        $data_inicio = date('Y-01-01');
        $data_fim = date('Y-m-d');
        break;
    case 'personalizado':
        // Usa valores do GET
        break;
}

// Estatísticas gerais COM FILTRO DE DATA
$stats = [
    'total_usuarios' => 0,
    'total_atendimentos' => 0,
    'concluidos' => 0,
    'em_andamento' => 0,
    'pendentes' => 0,
    'taxa_conclusao' => 0,
    'tempo_medio_conclusao' => 0,
    'media_tentativas' => 0
];

$stmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE ativo = 1");
$stats['total_usuarios'] = $stmt->fetch()['total'];

// Total no período
$stmt = $db->prepare("SELECT COUNT(*) as total FROM boas_vindas WHERE DATE(criado_em) BETWEEN :inicio AND :fim");
$stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
$stats['total_atendimentos'] = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM boas_vindas WHERE status = 'concluido' AND DATE(criado_em) BETWEEN :inicio AND :fim");
$stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
$stats['concluidos'] = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM boas_vindas WHERE status = 'em_andamento' AND DATE(criado_em) BETWEEN :inicio AND :fim");
$stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
$stats['em_andamento'] = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM boas_vindas WHERE status = 'pendente' AND DATE(criado_em) BETWEEN :inicio AND :fim");
$stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
$stats['pendentes'] = $stmt->fetch()['total'];

if ($stats['total_atendimentos'] > 0) {
    $stats['taxa_conclusao'] = round(($stats['concluidos'] / $stats['total_atendimentos']) * 100, 1);
}

// Tempo médio de conclusão (em horas)
$stmt = $db->prepare("
    SELECT AVG(TIMESTAMPDIFF(HOUR, criado_em, concluido_em)) as media_horas
    FROM boas_vindas
    WHERE status = 'concluido'
    AND concluido_em IS NOT NULL
    AND DATE(criado_em) BETWEEN :inicio AND :fim
");
$stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
$result = $stmt->fetch();
$stats['tempo_medio_conclusao'] = round($result['media_horas'] ?? 0, 1);

// Média de tentativas
$stmt = $db->prepare("
    SELECT AVG(tentativas_contato) as media
    FROM boas_vindas
    WHERE status = 'concluido'
    AND DATE(criado_em) BETWEEN :inicio AND :fim
");
$stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
$result = $stmt->fetch();
$stats['media_tentativas'] = round($result['media'] ?? 0, 1);

// Estatísticas de satisfação (baseado nos radios de resposta)
$satisfacao_stats = ['positivo' => 0, 'neutro' => 0, 'negativo' => 0, 'total' => 0];
try {
    $stmt = $db->prepare("
        SELECT
            SUM(CASE WHEN ti.valor_texto = 'Positivo' THEN 1 ELSE 0 END) as positivo,
            SUM(CASE WHEN ti.valor_texto = 'Neutro' THEN 1 ELSE 0 END) as neutro,
            SUM(CASE WHEN ti.valor_texto = 'Negativo' THEN 1 ELSE 0 END) as negativo,
            COUNT(*) as total
        FROM titulo_interacoes ti
        INNER JOIN boas_vindas bv ON ti.boas_vindas_id = bv.id
        WHERE ti.valor_texto IN ('Positivo', 'Neutro', 'Negativo')
        AND DATE(bv.criado_em) BETWEEN :inicio AND :fim
    ");
    $stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
    $result = $stmt->fetch();
    if ($result) {
        $satisfacao_stats = [
            'positivo' => intval($result['positivo']),
            'neutro' => intval($result['neutro']),
            'negativo' => intval($result['negativo']),
            'total' => intval($result['total'])
        ];
    }
} catch (Exception $e) {}

// Resultados de contato
$resultados_contato = [];
try {
    $stmt = $db->prepare("
        SELECT resultado_ultimo_contato, COUNT(*) as total
        FROM boas_vindas
        WHERE resultado_ultimo_contato IS NOT NULL
        AND DATE(criado_em) BETWEEN :inicio AND :fim
        GROUP BY resultado_ultimo_contato
        ORDER BY total DESC
    ");
    $stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
    $resultados_contato = $stmt->fetchAll();
} catch (Exception $e) {}

// Estatísticas por atendente COM FILTRO DE DATA
$stmt = $db->prepare("
    SELECT
        u.id as usuario_id,
        u.nome,
        COUNT(bv.id) as total,
        SUM(CASE WHEN bv.status = 'concluido' THEN 1 ELSE 0 END) as concluidos,
        SUM(CASE WHEN bv.status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
        SUM(CASE WHEN bv.status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
        AVG(bv.tentativas_contato) as media_tentativas,
        AVG(bv.nota_atendimento_consultor) as media_nota,
        AVG(TIMESTAMPDIFF(HOUR, bv.criado_em, bv.concluido_em)) as tempo_medio_horas
    FROM usuarios u
    LEFT JOIN boas_vindas bv ON u.id = bv.usuario_id
        AND DATE(bv.criado_em) BETWEEN :inicio AND :fim
    WHERE u.tipo = 'atendente' AND u.ativo = 1
    GROUP BY u.id, u.nome
    ORDER BY concluidos DESC
");
$stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
$atendentes_stats = $stmt->fetchAll();

// Atendimentos por mês (últimos 6 meses independente do filtro)
$stmt = $db->query("
    SELECT
        DATE_FORMAT(criado_em, '%Y-%m') as mes,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos
    FROM boas_vindas
    WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(criado_em, '%Y-%m')
    ORDER BY mes ASC
");
$atendimentos_mes = $stmt->fetchAll();

// Avaliações dos consultores COM FILTRO DE DATA
$stmt = $db->prepare("
    SELECT
        promotor,
        COUNT(*) as total_avaliacoes,
        AVG(nota_atendimento_consultor) as media_nota,
        SUM(CASE WHEN nota_atendimento_consultor >= 4 THEN 1 ELSE 0 END) as notas_boas,
        SUM(CASE WHEN nota_atendimento_consultor < 3 THEN 1 ELSE 0 END) as notas_ruins
    FROM boas_vindas
    WHERE nota_atendimento_consultor IS NOT NULL AND promotor IS NOT NULL
    AND DATE(criado_em) BETWEEN :inicio AND :fim
    GROUP BY promotor
    ORDER BY media_nota DESC
    LIMIT 10
");
$stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
$avaliacoes_consultores = $stmt->fetchAll();

// Tipos de tentativas COM FILTRO DE DATA
$stmt = $db->prepare("
    SELECT
        tc.tipo_tentativa,
        tc.resultado,
        COUNT(*) as total
    FROM tentativas_contato tc
    INNER JOIN boas_vindas bv ON tc.boas_vindas_id = bv.id
    WHERE DATE(bv.criado_em) BETWEEN :inicio AND :fim
    GROUP BY tc.tipo_tentativa, tc.resultado
    ORDER BY total DESC
");
$stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
$tentativas_stats = $stmt->fetchAll();

// Top clientes sem ciência do título (respostas negativas)
$clientes_sem_ciencia = [];
try {
    $stmt = $db->prepare("
        SELECT
            bv.numero_titulo,
            bv.nome_cliente,
            bv.promotor,
            COUNT(ti.id) as respostas_negativas
        FROM boas_vindas bv
        INNER JOIN titulo_interacoes ti ON bv.id = ti.boas_vindas_id
        WHERE ti.valor_texto = 'Negativo'
        AND DATE(bv.criado_em) BETWEEN :inicio AND :fim
        GROUP BY bv.id, bv.numero_titulo, bv.nome_cliente, bv.promotor
        HAVING COUNT(ti.id) >= 2
        ORDER BY respostas_negativas DESC
        LIMIT 10
    ");
    $stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
    $clientes_sem_ciencia = $stmt->fetchAll();
} catch (Exception $e) {}

// Preparar dados para exportação CSV (se solicitado)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=relatorio_boas_vindas_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

    // Cabeçalho
    fputcsv($output, ['Relatório Boas-Vindas - Período: ' . $data_inicio . ' a ' . $data_fim], ';');
    fputcsv($output, [], ';');

    // Resumo geral
    fputcsv($output, ['RESUMO GERAL'], ';');
    fputcsv($output, ['Total Atendimentos', $stats['total_atendimentos']], ';');
    fputcsv($output, ['Concluídos', $stats['concluidos']], ';');
    fputcsv($output, ['Em Andamento', $stats['em_andamento']], ';');
    fputcsv($output, ['Pendentes', $stats['pendentes']], ';');
    fputcsv($output, ['Taxa Conclusão', $stats['taxa_conclusao'] . '%'], ';');
    fputcsv($output, ['Tempo Médio (horas)', $stats['tempo_medio_conclusao']], ';');
    fputcsv($output, [], ';');

    // Performance atendentes
    fputcsv($output, ['PERFORMANCE ATENDENTES'], ';');
    fputcsv($output, ['Atendente', 'Total', 'Concluídos', 'Em Andamento', 'Pendentes', 'Média Tentativas', 'Nota Média'], ';');
    foreach ($atendentes_stats as $at) {
        fputcsv($output, [
            $at['nome'],
            $at['total'],
            $at['concluidos'],
            $at['em_andamento'],
            $at['pendentes'],
            number_format($at['media_tentativas'] ?? 0, 1),
            number_format($at['media_nota'] ?? 0, 1)
        ], ';');
    }
    fputcsv($output, [], ';');

    // Satisfação
    fputcsv($output, ['SATISFAÇÃO CLIENTES'], ';');
    fputcsv($output, ['Positivo', $satisfacao_stats['positivo']], ';');
    fputcsv($output, ['Neutro', $satisfacao_stats['neutro']], ';');
    fputcsv($output, ['Negativo', $satisfacao_stats['negativo']], ';');

    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Boas-Vindas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        body {
            background: #f5f7fa;
        }
        .navbar {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }
        .content-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .stats-card {
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .stats-card .card-body {
            padding: 20px;
        }
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <!-- Filtros de Data -->
        <div class="content-section mb-4">
            <div class="row align-items-end">
                <div class="col-md-8">
                    <form method="GET" class="row g-2" id="formFiltro">
                        <div class="col-auto">
                            <label class="form-label small">Período</label>
                            <select name="periodo" class="form-select form-select-sm" onchange="toggleDatasPersonalizadas(this)">
                                <option value="mes_atual" <?= $periodo === 'mes_atual' ? 'selected' : '' ?>>Mês Atual</option>
                                <option value="mes_anterior" <?= $periodo === 'mes_anterior' ? 'selected' : '' ?>>Mês Anterior</option>
                                <option value="ultimos_30" <?= $periodo === 'ultimos_30' ? 'selected' : '' ?>>Últimos 30 dias</option>
                                <option value="ultimos_90" <?= $periodo === 'ultimos_90' ? 'selected' : '' ?>>Últimos 90 dias</option>
                                <option value="ano_atual" <?= $periodo === 'ano_atual' ? 'selected' : '' ?>>Ano Atual</option>
                                <option value="personalizado" <?= $periodo === 'personalizado' ? 'selected' : '' ?>>Personalizado</option>
                            </select>
                        </div>
                        <div class="col-auto datas-personalizadas" style="<?= $periodo !== 'personalizado' ? 'display:none;' : '' ?>">
                            <label class="form-label small">De</label>
                            <input type="date" name="data_inicio" class="form-control form-control-sm" value="<?= $data_inicio ?>">
                        </div>
                        <div class="col-auto datas-personalizadas" style="<?= $periodo !== 'personalizado' ? 'display:none;' : '' ?>">
                            <label class="form-label small">Até</label>
                            <input type="date" name="data_fim" class="form-control form-control-sm" value="<?= $data_fim ?>">
                        </div>
                        <div class="col-auto">
                            <label class="form-label small">&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-sm d-block">
                                <i class="bi bi-funnel"></i> Filtrar
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-md-4 text-end">
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success btn-sm">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV
                    </a>
                </div>
            </div>
            <div class="mt-2">
                <small class="text-muted">
                    <i class="bi bi-calendar3"></i>
                    Exibindo dados de <strong><?= date('d/m/Y', strtotime($data_inicio)) ?></strong>
                    até <strong><?= date('d/m/Y', strtotime($data_fim)) ?></strong>
                </small>
            </div>
        </div>

        <!-- Cards de Estatísticas Gerais -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center p-3">
                        <div class="stats-icon bg-primary bg-opacity-10 text-primary mx-auto mb-2" style="width:50px;height:50px;">
                            <i class="bi bi-people" style="font-size:20px;"></i>
                        </div>
                        <h6 class="text-muted mb-1 small">Usuários Ativos</h6>
                        <h4 class="mb-0"><?= $stats['total_usuarios'] ?></h4>
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center p-3">
                        <div class="stats-icon bg-info bg-opacity-10 text-info mx-auto mb-2" style="width:50px;height:50px;">
                            <i class="bi bi-clipboard-check" style="font-size:20px;"></i>
                        </div>
                        <h6 class="text-muted mb-1 small">Total Atendimentos</h6>
                        <h4 class="mb-0"><?= $stats['total_atendimentos'] ?></h4>
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center p-3">
                        <div class="stats-icon bg-success bg-opacity-10 text-success mx-auto mb-2" style="width:50px;height:50px;">
                            <i class="bi bi-check-circle" style="font-size:20px;"></i>
                        </div>
                        <h6 class="text-muted mb-1 small">Concluídos</h6>
                        <h4 class="mb-0"><?= $stats['concluidos'] ?></h4>
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center p-3">
                        <div class="stats-icon bg-warning bg-opacity-10 text-warning mx-auto mb-2" style="width:50px;height:50px;">
                            <i class="bi bi-percent" style="font-size:20px;"></i>
                        </div>
                        <h6 class="text-muted mb-1 small">Taxa Conclusão</h6>
                        <h4 class="mb-0"><?= $stats['taxa_conclusao'] ?>%</h4>
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center p-3">
                        <div class="stats-icon bg-secondary bg-opacity-10 text-secondary mx-auto mb-2" style="width:50px;height:50px;">
                            <i class="bi bi-clock-history" style="font-size:20px;"></i>
                        </div>
                        <h6 class="text-muted mb-1 small">Tempo Médio</h6>
                        <h4 class="mb-0"><?= $stats['tempo_medio_conclusao'] ?>h</h4>
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center p-3">
                        <div class="stats-icon bg-danger bg-opacity-10 text-danger mx-auto mb-2" style="width:50px;height:50px;">
                            <i class="bi bi-telephone" style="font-size:20px;"></i>
                        </div>
                        <h6 class="text-muted mb-1 small">Méd. Tentativas</h6>
                        <h4 class="mb-0"><?= $stats['media_tentativas'] ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card de Satisfação -->
        <?php if ($satisfacao_stats['total'] > 0): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="content-section">
                    <h5 class="mb-3"><i class="bi bi-emoji-smile"></i> Satisfação dos Clientes (Respostas do Checklist)</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="d-flex align-items-center mb-2">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-success"><i class="bi bi-emoji-smile"></i> Positivo</span>
                                        <strong><?= $satisfacao_stats['positivo'] ?></strong>
                                    </div>
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar bg-success" style="width: <?= $satisfacao_stats['total'] > 0 ? round($satisfacao_stats['positivo'] / $satisfacao_stats['total'] * 100) : 0 ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center mb-2">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-warning"><i class="bi bi-emoji-neutral"></i> Neutro</span>
                                        <strong><?= $satisfacao_stats['neutro'] ?></strong>
                                    </div>
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar bg-warning" style="width: <?= $satisfacao_stats['total'] > 0 ? round($satisfacao_stats['neutro'] / $satisfacao_stats['total'] * 100) : 0 ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center mb-2">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-danger"><i class="bi bi-emoji-frown"></i> Negativo</span>
                                        <strong><?= $satisfacao_stats['negativo'] ?></strong>
                                    </div>
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar bg-danger" style="width: <?= $satisfacao_stats['total'] > 0 ? round($satisfacao_stats['negativo'] / $satisfacao_stats['total'] * 100) : 0 ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Gráficos -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="content-section">
                    <h5 class="mb-3"><i class="bi bi-bar-chart-fill"></i> Atendimentos por Mês</h5>
                    <div class="chart-container">
                        <canvas id="chartAtendimentosMes"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="content-section">
                    <h5 class="mb-3"><i class="bi bi-pie-chart-fill"></i> Status dos Atendimentos</h5>
                    <div class="chart-container">
                        <canvas id="chartStatus"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance dos Atendentes -->
        <div class="content-section mb-4">
            <h5 class="mb-3"><i class="bi bi-trophy-fill"></i> Performance dos Atendentes</h5>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Atendente</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Concluídos</th>
                            <th class="text-center">Em Andamento</th>
                            <th class="text-center">Pendentes</th>
                            <th class="text-center">Méd. Tentativas</th>
                            <th class="text-center">Tempo Méd.</th>
                            <th class="text-center">Nota Méd.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($atendentes_stats as $atendente): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($atendente['nome']) ?></strong></td>
                            <td class="text-center"><?= $atendente['total'] ?></td>
                            <td class="text-center">
                                <span class="badge bg-success"><?= $atendente['concluidos'] ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info"><?= $atendente['em_andamento'] ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-warning"><?= $atendente['pendentes'] ?></span>
                            </td>
                            <td class="text-center"><?= number_format($atendente['media_tentativas'] ?? 0, 1) ?></td>
                            <td class="text-center">
                                <?php if ($atendente['tempo_medio_horas']): ?>
                                    <?= number_format($atendente['tempo_medio_horas'], 0) ?>h
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($atendente['media_nota']): ?>
                                    <span class="badge bg-primary"><?= number_format($atendente['media_nota'] ?? 0, 1) ?>/5</span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Avaliações dos Consultores -->
        <?php if (count($avaliacoes_consultores) > 0): ?>
        <div class="content-section mb-4">
            <h5 class="mb-3"><i class="bi bi-star-fill"></i> Top 10 Consultores Melhor Avaliados</h5>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Posição</th>
                            <th>Consultor</th>
                            <th class="text-center">Total Avaliações</th>
                            <th class="text-center">Nota Média</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $posicao = 1;
                        foreach ($avaliacoes_consultores as $consultor): 
                        ?>
                        <tr>
                            <td>
                                <?php if ($posicao <= 3): ?>
                                    <span class="badge bg-warning">🏆 <?= $posicao ?>º</span>
                                <?php else: ?>
                                    <?= $posicao ?>º
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($consultor['promotor']) ?></strong></td>
                            <td class="text-center"><?= $consultor['total_avaliacoes'] ?></td>
                            <td class="text-center">
                                <?php 
                                $nota = round($consultor['media_nota'], 1);
                                $cor = $nota >= 4 ? 'success' : ($nota >= 3 ? 'warning' : 'danger');
                                ?>
                                <span class="badge bg-<?= $cor ?>"><?= $nota ?>/5</span>
                            </td>
                        </tr>
                        <?php 
                        $posicao++;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Resultados de Contato -->
        <?php if (count($resultados_contato) > 0): ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="content-section">
                    <h5 class="mb-3"><i class="bi bi-telephone-fill"></i> Resultados de Contato</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Resultado</th>
                                    <th class="text-center">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultados_contato as $rc): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $labels = [
                                            'atendeu' => '<span class="text-success"><i class="bi bi-check-circle"></i> Atendeu</span>',
                                            'nao_atendeu' => '<span class="text-danger"><i class="bi bi-x-circle"></i> Não Atendeu</span>',
                                            'ocupado' => '<span class="text-warning"><i class="bi bi-telephone-minus"></i> Ocupado</span>',
                                            'caixa_postal' => '<span class="text-secondary"><i class="bi bi-voicemail"></i> Caixa Postal</span>',
                                            'numero_errado' => '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> Número Errado</span>'
                                        ];
                                        echo $labels[$rc['resultado_ultimo_contato']] ?? ucfirst(str_replace('_', ' ', $rc['resultado_ultimo_contato']));
                                        ?>
                                    </td>
                                    <td class="text-center"><strong><?= $rc['total'] ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if (count($tentativas_stats) > 0): ?>
            <div class="col-md-6">
                <div class="content-section">
                    <h5 class="mb-3"><i class="bi bi-list-check"></i> Estatísticas de Tentativas</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Tipo</th>
                                    <th>Resultado</th>
                                    <th class="text-center">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tentativas_stats as $tentativa): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $icones = [
                                            'ligacao' => 'bi-telephone',
                                            'whatsapp' => 'bi-whatsapp',
                                            'email' => 'bi-envelope'
                                        ];
                                        ?>
                                        <i class="bi <?= $icones[$tentativa['tipo_tentativa']] ?? 'bi-chat' ?>"></i>
                                        <?= ucfirst($tentativa['tipo_tentativa'] ?? 'N/A') ?>
                                    </td>
                                    <td><?= str_replace('_', ' ', ucfirst($tentativa['resultado'] ?? 'N/A')) ?></td>
                                    <td class="text-center"><strong><?= $tentativa['total'] ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Clientes com Múltiplas Respostas Negativas -->
        <?php if (count($clientes_sem_ciencia) > 0): ?>
        <div class="content-section mb-4">
            <h5 class="mb-3"><i class="bi bi-exclamation-triangle-fill text-danger"></i> Clientes com Múltiplas Respostas Negativas</h5>
            <p class="text-muted small">Clientes que tiveram 2 ou mais respostas negativas no checklist (possível falta de ciência do título)</p>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Título</th>
                            <th>Cliente</th>
                            <th>Consultor/Promotor</th>
                            <th class="text-center">Respostas Negativas</th>
                            <th class="text-center">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes_sem_ciencia as $cliente): ?>
                        <tr class="table-danger">
                            <td><strong><?= htmlspecialchars($cliente['numero_titulo']) ?></strong></td>
                            <td><?= htmlspecialchars($cliente['nome_cliente']) ?></td>
                            <td><?= htmlspecialchars($cliente['promotor'] ?? '-') ?></td>
                            <td class="text-center">
                                <span class="badge bg-danger"><?= $cliente['respostas_negativas'] ?></span>
                            </td>
                            <td class="text-center">
                                <a href="titulo?numero=<?= urlencode($cliente['numero_titulo']) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> Ver
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Gráfico de Atendimentos por Mês
    const ctxMes = document.getElementById('chartAtendimentosMes').getContext('2d');
    new Chart(ctxMes, {
        type: 'line',
        data: {
            labels: [
                <?php foreach ($atendimentos_mes as $mes): ?>
                    '<?= date('m/Y', strtotime($mes['mes'] . '-01')) ?>',
                <?php endforeach; ?>
            ],
            datasets: [
                {
                    label: 'Total',
                    data: [
                        <?php foreach ($atendimentos_mes as $mes): ?>
                            <?= $mes['total'] ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: 'rgb(102, 126, 234)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Concluídos',
                    data: [
                        <?php foreach ($atendimentos_mes as $mes): ?>
                            <?= $mes['concluidos'] ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: 'rgb(40, 167, 69)',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Gráfico de Status
    const ctxStatus = document.getElementById('chartStatus').getContext('2d');
    new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: ['Concluídos', 'Em Andamento', 'Pendentes'],
            datasets: [{
                data: [
                    <?= $stats['concluidos'] ?>,
                    <?= $stats['em_andamento'] ?>,
                    <?= $stats['pendentes'] ?>
                ],
                backgroundColor: [
                    'rgb(40, 167, 69)',
                    'rgb(23, 162, 184)',
                    'rgb(255, 193, 7)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Toggle campos de data personalizada
    function toggleDatasPersonalizadas(select) {
        const campos = document.querySelectorAll('.datas-personalizadas');
        campos.forEach(campo => {
            campo.style.display = select.value === 'personalizado' ? '' : 'none';
        });
    }
    </script>
</body>
</html>
