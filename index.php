<?php
/**
 * SISTEMA DE BOAS-VINDAS AQUABEAT
 * Dashboard Principal
 */

require_once 'config.php';
Auth::requireLogin();

$pagina_atual = 'dashboard';
$db_bv = Database::getConnectionBV();

// Estatisticas do usuario
$usuario_id = Auth::getUserId();

// Buscar configurações de filtro
$filtro_padrao = 'mes_atual';
$mostrar_selector = true;
try {
    $stmt_config = $db_bv->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('filtro_data_padrao', 'mostrar_selector_periodo', 'filtro_data_inicio', 'filtro_data_fim')");
    $configs_result = $stmt_config->fetchAll();
    foreach ($configs_result as $cfg) {
        if ($cfg['chave'] === 'filtro_data_padrao' && $cfg['valor']) $filtro_padrao = $cfg['valor'];
        if ($cfg['chave'] === 'mostrar_selector_periodo') $mostrar_selector = $cfg['valor'] == '1';
    }
} catch (Exception $e) {
    // Configs não existem ainda
}

// Permitir override do filtro via GET
$filtro_atual = $_GET['periodo'] ?? ($_GET['filtro'] ?? $filtro_padrao);
$filtro_notificacao = $_GET['filtro'] ?? null;

// Calcular datas baseado no filtro
$data_inicio = date('Y-m-01');
$data_fim = date('Y-m-t');
$filtro_label = 'Mês Atual';

switch ($filtro_atual) {
    case 'mes_anterior':
        $data_inicio = date('Y-m-01', strtotime('first day of last month'));
        $data_fim = date('Y-m-t', strtotime('last day of last month'));
        $filtro_label = 'Mês Anterior';
        break;
    case 'ultimos_30_dias':
        $data_inicio = date('Y-m-d', strtotime('-30 days'));
        $data_fim = date('Y-m-d');
        $filtro_label = 'Últimos 30 dias';
        break;
    case 'ultimos_60_dias':
        $data_inicio = date('Y-m-d', strtotime('-60 days'));
        $data_fim = date('Y-m-d');
        $filtro_label = 'Últimos 60 dias';
        break;
    case 'personalizado':
        $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
        $data_fim = $_GET['data_fim'] ?? date('Y-m-d');
        $filtro_label = 'Período Personalizado';
        break;
    default:
        $filtro_atual = 'mes_atual';
}

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
$where_extra = '';
if ($filtro_notificacao === 'pendentes') {
    $where_extra = " AND DATEDIFF(NOW(), bv.data_venda) > 1 AND bv.status = 'pendente'";
} elseif ($filtro_notificacao === 'retornos') {
    $where_extra = " AND bv.resultado_ultimo_contato = 'nao_atendeu'";
} elseif ($filtro_notificacao === 'agendados') {
    $where_extra = " AND DATE(bv.proxima_tentativa) = CURDATE()";
}

$stmt_andamento = $db_bv->prepare("
    SELECT bv.*, u.nome as atendente_nome
    FROM boas_vindas bv
    LEFT JOIN usuarios u ON bv.usuario_id = u.id
    WHERE (bv.usuario_id = :usuario_id OR :is_admin = 1)
    AND bv.status IN ('pendente', 'em_andamento')
    $where_extra
    ORDER BY
        CASE WHEN bv.status = 'em_andamento' THEN 0 ELSE 1 END,
        bv.data_venda ASC
    LIMIT 20
");
$stmt_andamento->execute([
    ':usuario_id' => $usuario_id,
    ':is_admin' => Auth::isAdmin() ? 1 : 0
]);
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
        body { background: #f5f7fa; }
        .navbar { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); }
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
        .stat-card .icon { font-size: 2.5rem; opacity: 0.8; }
        .stat-card .number { font-size: 2rem; font-weight: bold; }
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
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .venda-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
            color: inherit;
        }
        .venda-card.concluido { border-left: 4px solid #28a745; background: #f8fff8; }
        .venda-card.em-andamento { border-left: 4px solid #ffc107; background: #fffef8; }
        .venda-card.pendente { border-left: 4px solid #6c757d; }
        .venda-card.urgente { border-left: 4px solid #dc3545; background: #fff8f8; }
        .badge-status { font-size: 0.75rem; padding: 5px 10px; }
        .filter-bar {
            background: white;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .filter-btn {
            border: 1px solid #dee2e6;
            background: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            transition: all 0.2s;
        }
        .filter-btn:hover, .filter-btn.active {
            background: #1e3c72;
            color: white;
            border-color: #1e3c72;
        }
        .dias-badge {
            font-size: 10px;
            padding: 2px 6px;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

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
                        <div class="icon text-primary"><i class="bi bi-clipboard-check"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted mb-1">Concluídos</div>
                            <div class="number text-success"><?= $stats_usuario['concluidos'] ?? 0 ?></div>
                        </div>
                        <div class="icon text-success"><i class="bi bi-check-circle"></i></div>
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
                        <div class="icon text-warning"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (Auth::isAdmin() && $stats_geral): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle"></i>
                    <strong>Últimos 30 dias:</strong>
                    <?= $stats_geral['total'] ?> atendimentos |
                    <?= $stats_geral['concluidos'] ?> concluídos |
                    <?= $stats_geral['atendentes_ativos'] ?> atendentes ativos
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($filtro_notificacao): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-warning d-flex justify-content-between align-items-center mb-0">
                    <span>
                        <i class="bi bi-funnel"></i>
                        <strong>Filtro ativo:</strong>
                        <?php
                        switch($filtro_notificacao) {
                            case 'pendentes': echo 'Vendas pendentes há mais de 1 dia'; break;
                            case 'retornos': echo 'Clientes que não atenderam'; break;
                            case 'agendados': echo 'Retornos agendados para hoje'; break;
                        }
                        ?>
                    </span>
                    <a href="index" class="btn btn-sm btn-outline-warning">
                        <i class="bi bi-x-lg"></i> Limpar Filtro
                    </a>
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
                        Meus Atendimentos
                        <?php if ($filtro_notificacao): ?>
                        <span class="badge bg-warning"><?= count($em_andamento) ?></span>
                        <?php endif; ?>
                    </h5>

                    <?php if (count($em_andamento) > 0): ?>
                        <?php foreach ($em_andamento as $bv):
                            $dias_desde_venda = diasDesdeVenda($bv['data_venda']);
                            $urgente = $bv['status'] === 'pendente' && $dias_desde_venda > 1;
                        ?>
                        <a href="titulo/<?= htmlspecialchars($bv['numero_titulo']) ?>" class="venda-card <?= $urgente ? 'urgente' : ($bv['status'] === 'em_andamento' ? 'em-andamento' : 'pendente') ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($bv['nome_cliente']) ?></h6>
                                    <small class="text-muted">
                                        <i class="bi bi-card-text"></i> <?= htmlspecialchars($bv['numero_titulo']) ?> |
                                        <i class="bi bi-telephone"></i> <?= formatarTelefone($bv['telefone']) ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <?php if ($urgente): ?>
                                    <span class="badge bg-danger badge-status">Urgente</span>
                                    <?php elseif ($bv['status'] === 'em_andamento'): ?>
                                    <span class="badge bg-warning badge-status">Em Andamento</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary badge-status">Pendente</span>
                                    <?php endif; ?>
                                    <br>
                                    <span class="badge bg-light text-dark dias-badge mt-1">
                                        <?= $dias_desde_venda ?> dia<?= $dias_desde_venda != 1 ? 's' : '' ?>
                                    </span>
                                </div>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="bi bi-calendar"></i> Venda: <?= formatarData($bv['data_venda'], 'd/m/Y') ?>
                                    <?php if ($bv['promotor']): ?>
                                    | <i class="bi bi-person"></i> <?= htmlspecialchars($bv['promotor']) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                            <p class="mt-2">Nenhum atendimento em andamento</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Vendas do Periodo -->
            <div class="col-lg-6">
                <div class="content-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">
                            <i class="bi bi-cart-check text-primary"></i>
                            Vendas - <?= $filtro_label ?>
                        </h5>
                        <?php if ($mostrar_selector): ?>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-calendar3"></i> Período
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item <?= $filtro_atual === 'mes_atual' ? 'active' : '' ?>" href="?periodo=mes_atual">Mês Atual</a></li>
                                <li><a class="dropdown-item <?= $filtro_atual === 'mes_anterior' ? 'active' : '' ?>" href="?periodo=mes_anterior">Mês Anterior</a></li>
                                <li><a class="dropdown-item <?= $filtro_atual === 'ultimos_30_dias' ? 'active' : '' ?>" href="?periodo=ultimos_30_dias">Últimos 30 dias</a></li>
                                <li><a class="dropdown-item <?= $filtro_atual === 'ultimos_60_dias' ? 'active' : '' ?>" href="?periodo=ultimos_60_dias">Últimos 60 dias</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalPeriodo">
                                        <i class="bi bi-calendar-range"></i> Personalizado...
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>

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

    <!-- Modal Período Personalizado -->
    <div class="modal fade" id="modalPeriodo" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Período Personalizado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="GET">
                    <input type="hidden" name="periodo" value="personalizado">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Data Início</label>
                            <input type="date" class="form-control" name="data_inicio" value="<?= $data_inicio ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data Fim</label>
                            <input type="date" class="form-control" name="data_fim" value="<?= $data_fim ?>" required>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="salvarPadrao" name="salvar_padrao">
                            <label class="form-check-label" for="salvarPadrao">
                                Salvar como período padrão
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Aplicar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    const dataInicio = '<?= $data_inicio ?>';
    const dataFim = '<?= $data_fim ?>';

    $(document).ready(function() {
        carregarVendas();
    });

    function carregarVendas() {
        $.ajax({
            url: 'api_vendas.php',
            method: 'GET',
            data: {
                data_inicio: dataInicio,
                data_fim: dataFim
            },
            success: function(response) {
                if (response.success) {
                    renderizarVendas(response.data);
                } else {
                    $('#listaVendas').html('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ' + response.error + '</div>');
                }
            },
            error: function(xhr) {
                let msg = 'Não foi possível carregar as vendas.';
                try {
                    const resp = JSON.parse(xhr.responseText);
                    if (resp.error) msg = resp.error;
                } catch(e) {}
                $('#listaVendas').html('<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> ' + msg + '</div>');
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
                    <p class="mt-2">Nenhuma venda encontrada neste período</p>
                </div>
            `);
            return;
        }

        vendas.forEach(function(venda) {
            let statusClass = 'pendente';
            let statusBadge = '<span class="badge bg-secondary badge-status">Pendente</span>';

            if (venda.boas_vindas_status === 'concluido') {
                statusClass = 'concluido';
                statusBadge = '<span class="badge bg-success badge-status">Concluído</span>';
            } else if (venda.boas_vindas_status === 'em_andamento') {
                statusClass = 'em-andamento';
                statusBadge = '<span class="badge bg-warning badge-status">Em Andamento</span>';
            }

            // Calcular dias desde venda
            let diasVenda = '';
            if (venda.data_venda) {
                const dataVenda = new Date(venda.data_venda);
                const hoje = new Date();
                const diffTime = Math.abs(hoje - dataVenda);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                diasVenda = `<span class="badge bg-light text-dark dias-badge">${diffDays} dia${diffDays != 1 ? 's' : ''}</span>`;

                if (diffDays > 1 && venda.boas_vindas_status !== 'concluido') {
                    statusClass = 'urgente';
                    statusBadge = '<span class="badge bg-danger badge-status">Urgente</span>';
                }
            }

            lista.append(`
                <a href="titulo/${escapeHtml(venda.numero_titulo)}" class="venda-card ${statusClass}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">${escapeHtml(venda.nome_cliente)}</h6>
                            <small class="text-muted">
                                <i class="bi bi-card-text"></i> ${escapeHtml(venda.numero_titulo)} |
                                <i class="bi bi-telephone"></i> ${escapeHtml(venda.telefone_residencial || '-')}
                            </small>
                        </div>
                        <div class="text-end">
                            ${statusBadge}
                            <br>
                            ${diasVenda}
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="bi bi-tag"></i> ${escapeHtml(venda.tipo_titulo || '-')} |
                            <i class="bi bi-currency-dollar"></i> ${formatarMoeda(venda.valor_total)}
                        </small>
                    </div>
                    ${venda.atendente ? '<div class="mt-1"><small class="text-info"><i class="bi bi-person"></i> ' + escapeHtml(venda.atendente) + '</small></div>' : ''}
                </a>
            `);
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
