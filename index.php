<?php
/**
 * SISTEMA DE BOAS-VINDAS AQUABEAT
 * Dashboard Principal
 */

require_once 'config.php';
Auth::requireLogin();

$db_bv = Database::getConnectionBV();

// Estatisticas do usuario
$usuario_id = Auth::getUserId();

$stmt_stats = $db_bv->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos,
        SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento
    FROM boas_vindas
    WHERE usuario_id = :usuario_id
");
$stmt_stats->execute([':usuario_id' => $usuario_id]);
$stats_usuario = $stmt_stats->fetch();

// Estatisticas gerais (para admin)
$stats_geral = null;
if (Auth::isAdmin()) {
    $stmt_geral = $db_bv->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos,
            SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
            COUNT(DISTINCT usuario_id) as atendentes_ativos
        FROM boas_vindas
        WHERE DATE(criado_em) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stats_geral = $stmt_geral->fetch();
}

// Boas-vindas em andamento do usuario
$stmt_andamento = $db_bv->prepare("
    SELECT bv.*, u.nome as atendente_nome
    FROM boas_vindas bv
    LEFT JOIN usuarios u ON bv.usuario_id = u.id
    WHERE bv.usuario_id = :usuario_id AND bv.status = 'em_andamento'
    ORDER BY bv.criado_em DESC
    LIMIT 10
");
$stmt_andamento->execute([':usuario_id' => $usuario_id]);
$em_andamento = $stmt_andamento->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Boas-Vindas Aquabeat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: #f5f7fa;
        }
        .navbar {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .stat-card .icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
        }
        .content-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .venda-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .venda-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .venda-card.concluido {
            border-left: 4px solid #28a745;
            background: #f8fff8;
        }
        .venda-card.em-andamento {
            border-left: 4px solid #ffc107;
            background: #fffef8;
        }
        .venda-card.pendente {
            border-left: 4px solid #6c757d;
        }
        .badge-status {
            font-size: 0.75rem;
            padding: 5px 10px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index">
                <i class="bi bi-hand-thumbs-up-fill"></i> Boas-Vindas Aquabeat
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index">
                            <i class="bi bi-house-fill"></i> Dashboard
                        </a>
                    </li>
                    <?php if (Auth::isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="usuarios">
                            <i class="bi bi-people-fill"></i> Usuarios
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="relatorios">
                            <i class="bi bi-graph-up"></i> Relatorios
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="configuracoes">
                            <i class="bi bi-gear-fill"></i> Configuracoes
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars(Auth::getUserName()) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="perfil"><i class="bi bi-person"></i> Meu Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Cards de Estatisticas -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted mb-1">Meus Atendimentos</div>
                            <div class="number text-primary"><?= $stats_usuario['total'] ?? 0 ?></div>
                        </div>
                        <div class="icon text-primary">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted mb-1">Concluidos</div>
                            <div class="number text-success"><?= $stats_usuario['concluidos'] ?? 0 ?></div>
                        </div>
                        <div class="icon text-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted mb-1">Em Andamento</div>
                            <div class="number text-warning"><?= $stats_usuario['em_andamento'] ?? 0 ?></div>
                        </div>
                        <div class="icon text-warning">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (Auth::isAdmin() && $stats_geral): ?>
        <!-- Estatisticas Gerais (Admin) -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>Ultimos 30 dias:</strong>
                    <?= $stats_geral['total'] ?> atendimentos |
                    <?= $stats_geral['concluidos'] ?> concluidos |
                    <?= $stats_geral['atendentes_ativos'] ?> atendentes ativos
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Boas-vindas em andamento -->
            <div class="col-lg-6">
                <div class="content-section">
                    <h5 class="mb-4">
                        <i class="bi bi-hourglass-split text-warning"></i>
                        Meus Atendimentos em Andamento
                    </h5>

                    <?php if (count($em_andamento) > 0): ?>
                        <?php foreach ($em_andamento as $bv): ?>
                        <div class="venda-card em-andamento">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($bv['nome_cliente']) ?></h6>
                                    <small class="text-muted">
                                        <i class="bi bi-card-text"></i> <?= htmlspecialchars($bv['numero_titulo']) ?> |
                                        <i class="bi bi-telephone"></i> <?= formatarTelefone($bv['telefone']) ?>
                                    </small>
                                </div>
                                <span class="badge bg-warning badge-status">Em Andamento</span>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i> Iniciado em: <?= formatarData($bv['iniciado_em']) ?>
                                </small>
                            </div>
                            <div class="mt-2">
                                <a href="boasvindas.php?id=<?= $bv['id'] ?>" class="btn btn-sm btn-warning">
                                    <i class="bi bi-arrow-right-circle"></i> Continuar
                                </a>
                                <a href="visualizar.php?id=<?= $bv['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i> Ver
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                            <p class="mt-2">Nenhum atendimento em andamento</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Vendas do Mes -->
            <div class="col-lg-6">
                <div class="content-section">
                    <h5 class="mb-4">
                        <i class="bi bi-cart-check text-primary"></i>
                        Vendas do Mes
                    </h5>

                    <div id="listaVendas">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                            <p class="mt-2 text-muted">Carregando vendas...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    $(document).ready(function() {
        carregarVendas();
    });

    function carregarVendas() {
        $.ajax({
            url: 'api_vendas.php',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    renderizarVendas(response.data);
                } else {
                    $('#listaVendas').html('<div class="alert alert-danger">Erro ao carregar vendas: ' + response.error + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#listaVendas').html('<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Nao foi possivel carregar as vendas do sistema externo. Verifique a conexao com o banco de dados da API.</div>');
            }
        });
    }

    function renderizarVendas(vendas) {
        const lista = $('#listaVendas');
        lista.empty();

        if (vendas.length === 0) {
            lista.html(`
                <div class="text-center text-muted py-4">
                    <i class="bi bi-calendar-x" style="font-size: 3rem;"></i>
                    <p class="mt-2">Nenhuma venda encontrada neste mes</p>
                </div>
            `);
            return;
        }

        vendas.forEach(function(venda) {
            let statusClass = 'pendente';
            let statusBadge = '<span class="badge bg-secondary badge-status">Pendente</span>';
            let actionBtn = `<a href="boasvindas.php?titulo=${venda.numero_titulo}" class="btn btn-sm btn-primary"><i class="bi bi-play-circle"></i> Iniciar</a>`;

            if (venda.boas_vindas_status === 'concluido') {
                statusClass = 'concluido';
                statusBadge = '<span class="badge bg-success badge-status">Concluido</span>';
                actionBtn = `<a href="visualizar.php?id=${venda.boas_vindas_id}" class="btn btn-sm btn-outline-success"><i class="bi bi-eye"></i> Ver</a>`;
            } else if (venda.boas_vindas_status === 'em_andamento') {
                statusClass = 'em-andamento';
                statusBadge = '<span class="badge bg-warning badge-status">Em Andamento</span>';
                actionBtn = `<a href="boasvindas.php?id=${venda.boas_vindas_id}" class="btn btn-sm btn-warning"><i class="bi bi-arrow-right-circle"></i> Continuar</a>`;
            }

            const html = `
                <div class="venda-card ${statusClass}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">${escapeHtml(venda.nome_cliente)}</h6>
                            <small class="text-muted">
                                <i class="bi bi-card-text"></i> ${escapeHtml(venda.numero_titulo)} |
                                <i class="bi bi-telephone"></i> ${escapeHtml(venda.telefone_residencial || '-')}
                            </small>
                        </div>
                        ${statusBadge}
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="bi bi-tag"></i> ${escapeHtml(venda.tipo_titulo || '-')} |
                            <i class="bi bi-currency-dollar"></i> ${formatarMoeda(venda.valor_total)}
                        </small>
                    </div>
                    ${venda.atendente ? '<div class="mt-1"><small class="text-info"><i class="bi bi-person"></i> ' + escapeHtml(venda.atendente) + '</small></div>' : ''}
                    <div class="mt-2">
                        ${actionBtn}
                    </div>
                </div>
            `;

            lista.append(html);
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatarMoeda(valor) {
        if (!valor) return 'R$ 0,00';
        return 'R$ ' + parseFloat(valor).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    </script>
</body>
</html>
