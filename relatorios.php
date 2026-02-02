<?php
require_once 'config.php';
Auth::requireAdmin();

$db = Database::getConnectionBV();

// Estatísticas gerais
$stats = [
    'total_usuarios' => 0,
    'total_atendimentos' => 0,
    'concluidos' => 0,
    'em_andamento' => 0,
    'pendentes' => 0,
    'taxa_conclusao' => 0
];

$stmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE ativo = 1");
$stats['total_usuarios'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM boas_vindas");
$stats['total_atendimentos'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM boas_vindas WHERE status = 'concluido'");
$stats['concluidos'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM boas_vindas WHERE status = 'em_andamento'");
$stats['em_andamento'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM boas_vindas WHERE status = 'pendente'");
$stats['pendentes'] = $stmt->fetch()['total'];

if ($stats['total_atendimentos'] > 0) {
    $stats['taxa_conclusao'] = round(($stats['concluidos'] / $stats['total_atendimentos']) * 100, 1);
}

// Estatísticas por atendente
$stmt = $db->query("
    SELECT 
        u.nome,
        COUNT(bv.id) as total,
        SUM(CASE WHEN bv.status = 'concluido' THEN 1 ELSE 0 END) as concluidos,
        SUM(CASE WHEN bv.status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
        SUM(CASE WHEN bv.status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
        AVG(bv.tentativas_contato) as media_tentativas,
        AVG(bv.nota_atendimento_consultor) as media_nota
    FROM usuarios u
    LEFT JOIN boas_vindas bv ON u.id = bv.usuario_id
    WHERE u.tipo = 'atendente' AND u.ativo = 1
    GROUP BY u.id, u.nome
    ORDER BY concluidos DESC
");
$atendentes_stats = $stmt->fetchAll();

// Atendimentos por mês
$stmt = $db->query("
    SELECT 
        DATE_FORMAT(data_venda, '%Y-%m') as mes,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos
    FROM boas_vindas
    WHERE data_venda >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(data_venda, '%Y-%m')
    ORDER BY mes ASC
");
$atendimentos_mes = $stmt->fetchAll();

// Avaliações dos consultores
$stmt = $db->query("
    SELECT 
        promotor,
        COUNT(*) as total_avaliacoes,
        AVG(nota_atendimento_consultor) as media_nota
    FROM boas_vindas
    WHERE nota_atendimento_consultor IS NOT NULL AND promotor IS NOT NULL
    GROUP BY promotor
    ORDER BY media_nota DESC
    LIMIT 10
");
$avaliacoes_consultores = $stmt->fetchAll();

// Tipos de tentativas
$stmt = $db->query("
    SELECT 
        tipo_tentativa,
        resultado,
        COUNT(*) as total
    FROM tentativas_contato
    GROUP BY tipo_tentativa, resultado
    ORDER BY total DESC
");
$tentativas_stats = $stmt->fetchAll();
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
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
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
                            <i class="bi bi-house-fill"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="usuarios.php">
                            <i class="bi bi-people-fill"></i> Usuários
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="relatorios.php">
                            <i class="bi bi-graph-up"></i> Relatórios
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars(Auth::getUserName()) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Cards de Estatísticas Gerais -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="stats-icon bg-primary bg-opacity-10 text-primary me-3">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Usuários Ativos</h6>
                            <h3 class="mb-0"><?= $stats['total_usuarios'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="stats-icon bg-info bg-opacity-10 text-info me-3">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Atendimentos</h6>
                            <h3 class="mb-0"><?= $stats['total_atendimentos'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="stats-icon bg-success bg-opacity-10 text-success me-3">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Concluídos</h6>
                            <h3 class="mb-0"><?= $stats['concluidos'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="stats-icon bg-warning bg-opacity-10 text-warning me-3">
                            <i class="bi bi-percent"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Taxa de Conclusão</h6>
                            <h3 class="mb-0"><?= $stats['taxa_conclusao'] ?>%</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                            <th class="text-center">Média Tentativas</th>
                            <th class="text-center">Nota Média</th>
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
                            <td class="text-center"><?= number_format($atendente['media_tentativas'], 1) ?></td>
                            <td class="text-center">
                                <?php if ($atendente['media_nota']): ?>
                                    <span class="badge bg-primary"><?= number_format($atendente['media_nota'], 1) ?>/5</span>
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

        <!-- Estatísticas de Tentativas -->
        <div class="content-section mb-4">
            <h5 class="mb-3"><i class="bi bi-telephone-fill"></i> Estatísticas de Tentativas de Contato</h5>
            
            <div class="table-responsive">
                <table class="table table-hover">
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
                                <i class="bi <?= $icones[$tentativa['tipo_tentativa']] ?>"></i>
                                <?= ucfirst($tentativa['tipo_tentativa']) ?>
                            </td>
                            <td><?= str_replace('_', ' ', ucfirst($tentativa['resultado'])) ?></td>
                            <td class="text-center"><strong><?= $tentativa['total'] ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
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
    </script>
</body>
</html>
