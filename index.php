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
} catch (Exception $e) {}

// Filtros ativos
// IMPORTANTE: 'periodo' é para datas, 'filtro' é para tipo de atendimento - são parâmetros separados!
$filtro_atual = $_GET['periodo'] ?? $filtro_padrao;
$filtro_status_atend = $_GET['status_atend'] ?? 'abertos'; // abertos, concluidos, todos
$filtro_status_vendas = $_GET['status_vendas'] ?? 'abertos'; // abertos, concluidos, todos

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
        SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
        SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes
    FROM boas_vindas
    WHERE usuario_id = :usuario_id
");
$stmt_stats->execute([':usuario_id' => $usuario_id]);
$stats_usuario = $stmt_stats->fetch();

// Estatisticas gerais (para todos verem)
$stats_geral = null;
try {
    $stmt_geral = $db_bv->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos,
            SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
            COUNT(DISTINCT usuario_id) as atendentes_ativos
        FROM boas_vindas
        WHERE DATE(criado_em) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stats_geral = $stmt_geral->fetch();
} catch (Exception $e) {}

// Buscar CPFs duplicados (mesmo CPF em títulos diferentes)
$cpfs_duplicados = [];
try {
    $stmt_dup = $db_bv->query("
        SELECT documento_cliente, COUNT(*) as qtd, GROUP_CONCAT(numero_titulo SEPARATOR ', ') as titulos
        FROM boas_vindas
        WHERE documento_cliente IS NOT NULL AND documento_cliente != ''
        GROUP BY documento_cliente
        HAVING COUNT(*) > 1
        ORDER BY qtd DESC
        LIMIT 20
    ");
    $cpfs_duplicados = $stmt_dup->fetchAll();
} catch (Exception $e) {}

// Filtro de status para atendimentos
$status_condition = '';
$filtro_notificacao = $_GET['filtro'] ?? '';
$filtro_label_atend = 'Abertos';

// Mapear filtros para labels amigáveis
$filtros_labels = [
    'abertos' => 'Abertos',
    'concluidos' => 'Concluídos',
    'pendentes' => 'Pendentes',
    'em_andamento' => 'Em Andamento',
    'todos' => 'Todos',
    'followup' => 'Follow-up',
    'retornos' => 'Retornos',
    'agendados' => 'Agendados Hoje',
    'duplicados' => 'CPFs Duplicados'
];

// Verificar se há filtro especial da barra de notificações
if ($filtro_notificacao === 'retornos') {
    $status_condition = "AND bv.status IN ('pendente', 'em_andamento') AND bv.resultado_ultimo_contato IN ('nao_atendeu', 'ocupado', 'caixa_postal')";
    $filtro_label_atend = 'Retornos';
    $filtro_status_atend = 'retornos';
} elseif ($filtro_notificacao === 'agendados') {
    $status_condition = "AND bv.status IN ('pendente', 'em_andamento') AND DATE(bv.proxima_tentativa) = CURDATE()";
    $filtro_label_atend = 'Agendados Hoje';
    $filtro_status_atend = 'agendados';
} elseif ($filtro_notificacao === 'pendentes') {
    $status_condition = "AND bv.status = 'pendente' AND DATEDIFF(NOW(), bv.data_venda) > 1";
    $filtro_label_atend = 'Pendentes (+1 dia)';
    $filtro_status_atend = 'pendentes_urgentes';
} elseif ($filtro_notificacao === 'followup') {
    $status_condition = "AND bv.status IN ('pendente', 'em_andamento') AND (
        bv.resultado_ultimo_contato IN ('nao_atendeu', 'ocupado', 'caixa_postal')
        OR (bv.resultado_ultimo_contato IS NULL AND DATEDIFF(NOW(), bv.criado_em) > 0)
        OR DATE(bv.proxima_tentativa) <= CURDATE()
    )";
    $filtro_label_atend = 'Follow-up';
    $filtro_status_atend = 'followup';
} elseif ($filtro_notificacao === 'duplicados') {
    // Filtro especial para CPFs duplicados - buscar os números de título
    $titulos_duplicados = [];
    foreach ($cpfs_duplicados as $dup) {
        $tits = explode(', ', $dup['titulos']);
        foreach ($tits as $t) {
            $titulos_duplicados[] = trim($t);
        }
    }
    if (!empty($titulos_duplicados)) {
        $placeholders = implode(',', array_fill(0, count($titulos_duplicados), '?'));
        $status_condition = "AND bv.numero_titulo IN ($placeholders)";
    }
    $filtro_label_atend = 'CPFs Duplicados';
    $filtro_status_atend = 'duplicados';
} else {
    switch ($filtro_status_atend) {
        case 'abertos':
            $status_condition = "AND bv.status IN ('pendente', 'em_andamento')";
            $filtro_label_atend = 'Abertos';
            break;
        case 'concluidos':
            $status_condition = "AND bv.status = 'concluido'";
            $filtro_label_atend = 'Concluídos';
            break;
        case 'pendentes':
            $status_condition = "AND bv.status = 'pendente'";
            $filtro_label_atend = 'Pendentes';
            break;
        case 'em_andamento':
            $status_condition = "AND bv.status = 'em_andamento'";
            $filtro_label_atend = 'Em Andamento';
            break;
        case 'todos':
            $status_condition = "";
            $filtro_label_atend = 'Todos';
            break;
        case 'followup':
            $status_condition = "AND bv.status IN ('pendente', 'em_andamento') AND (
                bv.resultado_ultimo_contato IN ('nao_atendeu', 'ocupado', 'caixa_postal')
                OR (bv.resultado_ultimo_contato IS NULL AND DATEDIFF(NOW(), bv.criado_em) > 0)
                OR DATE(bv.proxima_tentativa) <= CURDATE()
            )";
            $filtro_label_atend = 'Follow-up';
            break;
    }
}

// Paginação para Meus Atendimentos
$pagina_atend = max(1, intval($_GET['pagina_atend'] ?? 1));
$por_pagina_atend = 20;
$offset_atend = ($pagina_atend - 1) * $por_pagina_atend;

// Busca textual em Meus Atendimentos
$busca_atend = trim($_GET['busca_atend'] ?? '');
$busca_condition = '';
if (!empty($busca_atend)) {
    $busca_condition = "AND (bv.numero_titulo LIKE :busca OR bv.nome_cliente LIKE :busca OR bv.documento_cliente LIKE :busca_doc)";
}

// Contar total de atendimentos
$sql_count = "SELECT COUNT(*) as total FROM boas_vindas bv WHERE (bv.usuario_id = :usuario_id OR :is_admin = 1) $status_condition $busca_condition";

// Preparar parâmetros
$params_base = [
    ':usuario_id' => $usuario_id,
    ':is_admin' => Auth::isAdmin() ? 1 : 0
];
if (!empty($busca_atend)) {
    $params_base[':busca'] = "%$busca_atend%";
    $params_base[':busca_doc'] = preg_replace('/[^0-9]/', '', $busca_atend) . '%';
}

// Caso especial para duplicados (com placeholders numéricos)
if ($filtro_notificacao === 'duplicados' && !empty($titulos_duplicados)) {
    $sql_count = "SELECT COUNT(*) as total FROM boas_vindas bv WHERE (bv.usuario_id = ? OR ? = 1) AND bv.numero_titulo IN (" . implode(',', array_fill(0, count($titulos_duplicados), '?')) . ")";
    $params_count = [$usuario_id, Auth::isAdmin() ? 1 : 0];
    $params_count = array_merge($params_count, $titulos_duplicados);
    $stmt_count = $db_bv->prepare($sql_count);
    $stmt_count->execute($params_count);
} else {
    $stmt_count = $db_bv->prepare($sql_count);
    $stmt_count->execute($params_base);
}
$total_atend = $stmt_count->fetch()['total'] ?? 0;
$total_paginas_atend = max(1, ceil($total_atend / $por_pagina_atend));

// Buscar atendimentos com paginação
if ($filtro_notificacao === 'duplicados' && !empty($titulos_duplicados)) {
    $sql_atend = "SELECT bv.*, u.nome as atendente_nome
        FROM boas_vindas bv
        LEFT JOIN usuarios u ON bv.usuario_id = u.id
        WHERE (bv.usuario_id = ? OR ? = 1)
        AND bv.numero_titulo IN (" . implode(',', array_fill(0, count($titulos_duplicados), '?')) . ")
        ORDER BY bv.data_venda DESC
        LIMIT $por_pagina_atend OFFSET $offset_atend";
    $params_atend = [$usuario_id, Auth::isAdmin() ? 1 : 0];
    $params_atend = array_merge($params_atend, $titulos_duplicados);
    $stmt_andamento = $db_bv->prepare($sql_atend);
    $stmt_andamento->execute($params_atend);
} else {
    $stmt_andamento = $db_bv->prepare("
        SELECT bv.*, u.nome as atendente_nome
        FROM boas_vindas bv
        LEFT JOIN usuarios u ON bv.usuario_id = u.id
        WHERE (bv.usuario_id = :usuario_id OR :is_admin = 1)
        $status_condition
        $busca_condition
        ORDER BY
            CASE WHEN bv.status = 'em_andamento' THEN 0
                 WHEN bv.status = 'pendente' THEN 1
                 ELSE 2 END,
            bv.data_venda DESC
        LIMIT $por_pagina_atend OFFSET $offset_atend
    ");
    $stmt_andamento->execute($params_base);
}
$em_andamento = $stmt_andamento->fetchAll();

// Preservar parâmetros da URL para links
$url_params = http_build_query(array_filter([
    'periodo' => $filtro_atual,
    'status_vendas' => $filtro_status_vendas,
    'data_inicio' => $filtro_atual === 'personalizado' ? $data_inicio : null,
    'data_fim' => $filtro_atual === 'personalizado' ? $data_fim : null
]));
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
        .dias-badge { font-size: 10px; padding: 2px 6px; }
        .filter-tabs { display: flex; gap: 5px; margin-bottom: 15px; flex-wrap: wrap; }
        .filter-tab {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            border: 1px solid #dee2e6;
            background: white;
            color: #6c757d;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .filter-tab:hover, .filter-tab.active {
            background: #1e3c72;
            color: white;
            border-color: #1e3c72;
        }
        .search-global {
            position: relative;
            margin-bottom: 20px;
        }
        .search-global input {
            padding-left: 40px;
            border-radius: 25px;
        }
        .search-global i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            max-height: 300px;
            overflow-y: auto;
            display: none;
        }
        .search-results.show { display: block; }
        .search-result-item {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .search-result-item:hover { background: #f8f9fa; }
        .search-result-item:last-child { border-bottom: none; }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <!-- Pesquisa Global + Ações -->
        <div class="row mb-3">
            <div class="col-md-8 col-lg-9">
                <div class="search-global">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control form-control-lg" id="searchGlobal" placeholder="Pesquisar por CPF, ID do Título ou Nome do Cliente...">
                    <div class="search-results" id="searchResults"></div>
                </div>
            </div>
            <div class="col-md-4 col-lg-3 d-flex gap-2 align-items-center justify-content-end mt-2 mt-md-0">
                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalBulk">
                    <i class="bi bi-collection"></i> Em Massa
                </button>
                <?php if (!empty($cpfs_duplicados)): ?>
                <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalDuplicados">
                    <i class="bi bi-exclamation-triangle"></i> <?= count($cpfs_duplicados) ?> Duplicados
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cards de Estatisticas Gerais -->
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted mb-1">Total Geral</div>
                            <div class="number text-primary"><?= $stats_geral['total'] ?? 0 ?></div>
                            <small class="text-muted" style="font-size: 10px;">Meus: <?= $stats_usuario['total'] ?? 0 ?></small>
                        </div>
                        <div class="icon text-primary"><i class="bi bi-clipboard-check"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted mb-1">Concluídos</div>
                            <div class="number text-success"><?= $stats_geral['concluidos'] ?? 0 ?></div>
                            <small class="text-muted" style="font-size: 10px;">Meus: <?= $stats_usuario['concluidos'] ?? 0 ?></small>
                        </div>
                        <div class="icon text-success"><i class="bi bi-check-circle"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted mb-1">Em Andamento</div>
                            <div class="number text-warning"><?= $stats_geral['em_andamento'] ?? 0 ?></div>
                            <small class="text-muted" style="font-size: 10px;">Meus: <?= $stats_usuario['em_andamento'] ?? 0 ?></small>
                        </div>
                        <div class="icon text-warning"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted mb-1">Pendentes</div>
                            <div class="number text-secondary"><?= $stats_geral['pendentes'] ?? 0 ?></div>
                            <small class="text-muted" style="font-size: 10px;">Meus: <?= $stats_usuario['pendentes'] ?? 0 ?></small>
                        </div>
                        <div class="icon text-secondary"><i class="bi bi-clock-history"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-12">
                <small class="text-muted">
                    <i class="bi bi-info-circle"></i> Últimos 30 dias |
                    <?= $stats_geral['atendentes_ativos'] ?? 0 ?> atendentes ativos
                </small>
            </div>
        </div>

        <div class="row">
            <!-- Meus Atendimentos -->
            <div class="col-lg-6">
                <div class="content-section">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">
                            <i class="bi bi-hourglass-split text-warning"></i>
                            Meus Atendimentos
                            <small class="text-muted" style="font-size: 12px;">(<?= $filtro_label_atend ?>)</small>
                        </h5>
                        <span class="badge bg-primary"><?= $total_atend ?> título<?= $total_atend != 1 ? 's' : '' ?></span>
                    </div>

                    <!-- Busca -->
                    <div class="mb-2">
                        <form method="GET" class="d-flex gap-2">
                            <input type="hidden" name="status_atend" value="<?= htmlspecialchars($filtro_status_atend) ?>">
                            <input type="hidden" name="periodo" value="<?= htmlspecialchars($filtro_atual) ?>">
                            <input type="hidden" name="status_vendas" value="<?= htmlspecialchars($filtro_status_vendas) ?>">
                            <?php if ($filtro_notificacao): ?>
                            <input type="hidden" name="filtro" value="<?= htmlspecialchars($filtro_notificacao) ?>">
                            <?php endif; ?>
                            <input type="text" name="busca_atend" class="form-control form-control-sm" placeholder="Buscar por ID, CPF ou nome..." value="<?= htmlspecialchars($busca_atend) ?>" style="border-radius: 20px;">
                            <button type="submit" class="btn btn-sm btn-outline-primary" style="border-radius: 20px;">
                                <i class="bi bi-search"></i>
                            </button>
                            <?php if (!empty($busca_atend)): ?>
                            <a href="?status_atend=<?= $filtro_status_atend ?>&periodo=<?= $filtro_atual ?>&status_vendas=<?= $filtro_status_vendas ?><?= $filtro_notificacao ? '&filtro=' . $filtro_notificacao : '' ?>" class="btn btn-sm btn-outline-secondary" style="border-radius: 20px;">
                                <i class="bi bi-x"></i>
                            </a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Filtros de Status -->
                    <div class="filter-tabs">
                        <a href="?status_atend=abertos&<?= $url_params ?>" class="filter-tab <?= $filtro_status_atend === 'abertos' ? 'active' : '' ?>">
                            <i class="bi bi-folder2-open"></i> Abertos
                        </a>
                        <a href="?status_atend=pendentes&<?= $url_params ?>" class="filter-tab <?= $filtro_status_atend === 'pendentes' ? 'active' : '' ?>">
                            <i class="bi bi-clock"></i> Pendentes
                        </a>
                        <a href="?status_atend=em_andamento&<?= $url_params ?>" class="filter-tab <?= $filtro_status_atend === 'em_andamento' ? 'active' : '' ?>">
                            <i class="bi bi-play-circle"></i> Em Andamento
                        </a>
                        <a href="?status_atend=concluidos&<?= $url_params ?>" class="filter-tab <?= $filtro_status_atend === 'concluidos' ? 'active' : '' ?>">
                            <i class="bi bi-check-circle"></i> Concluídos
                        </a>
                        <a href="?status_atend=followup&<?= $url_params ?>" class="filter-tab <?= $filtro_status_atend === 'followup' ? 'active' : '' ?>">
                            <i class="bi bi-bell"></i> Follow-up
                        </a>
                        <?php if (!empty($cpfs_duplicados)): ?>
                        <a href="?filtro=duplicados&<?= $url_params ?>" class="filter-tab <?= $filtro_notificacao === 'duplicados' ? 'active' : '' ?>">
                            <i class="bi bi-people"></i> Duplicados
                        </a>
                        <?php endif; ?>
                        <a href="?status_atend=todos&<?= $url_params ?>" class="filter-tab <?= $filtro_status_atend === 'todos' ? 'active' : '' ?>">
                            <i class="bi bi-list"></i> Todos
                        </a>
                    </div>

                    <?php if (count($em_andamento) > 0): ?>
                        <?php foreach ($em_andamento as $bv):
                            $dias_desde_venda = diasDesdeVenda($bv['data_venda']);
                            $urgente = $bv['status'] === 'pendente' && $dias_desde_venda > 1;
                        ?>
                        <a href="titulo?id=<?= htmlspecialchars($bv['numero_titulo']) ?>" class="venda-card <?= $bv['status'] === 'concluido' ? 'concluido' : ($urgente ? 'urgente' : ($bv['status'] === 'em_andamento' ? 'em-andamento' : 'pendente')) ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($bv['nome_cliente']) ?></h6>
                                    <small class="text-muted">
                                        <i class="bi bi-card-text"></i> <?= htmlspecialchars($bv['numero_titulo']) ?> |
                                        <i class="bi bi-telephone"></i> <?= formatarTelefone($bv['telefone']) ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <?php if ($bv['status'] === 'concluido'): ?>
                                    <span class="badge bg-success badge-status">Concluído</span>
                                    <?php elseif ($urgente): ?>
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

                        <!-- Paginação Meus Atendimentos -->
                        <?php if ($total_paginas_atend > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                            <small class="text-muted">
                                Página <?= $pagina_atend ?> de <?= $total_paginas_atend ?>
                            </small>
                            <div class="btn-group btn-group-sm">
                                <?php if ($pagina_atend > 1): ?>
                                <a href="?pagina_atend=<?= $pagina_atend - 1 ?>&status_atend=<?= $filtro_status_atend ?>&<?= $url_params ?><?= $filtro_notificacao ? '&filtro=' . $filtro_notificacao : '' ?><?= !empty($busca_atend) ? '&busca_atend=' . urlencode($busca_atend) : '' ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-chevron-left"></i> Anterior
                                </a>
                                <?php endif; ?>
                                <?php if ($pagina_atend < $total_paginas_atend): ?>
                                <a href="?pagina_atend=<?= $pagina_atend + 1 ?>&status_atend=<?= $filtro_status_atend ?>&<?= $url_params ?><?= $filtro_notificacao ? '&filtro=' . $filtro_notificacao : '' ?><?= !empty($busca_atend) ? '&busca_atend=' . urlencode($busca_atend) : '' ?>" class="btn btn-outline-primary">
                                    Próxima <i class="bi bi-chevron-right"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                            <p class="mt-2">Nenhum atendimento encontrado</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Vendas do Periodo -->
            <div class="col-lg-6">
                <div class="content-section">
                    <div class="d-flex justify-content-between align-items-center mb-2">
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
                                <li><a class="dropdown-item <?= $filtro_atual === 'mes_atual' ? 'active' : '' ?>" href="?periodo=mes_atual&status_atend=<?= $filtro_status_atend ?>&status_vendas=<?= $filtro_status_vendas ?>">Mês Atual</a></li>
                                <li><a class="dropdown-item <?= $filtro_atual === 'mes_anterior' ? 'active' : '' ?>" href="?periodo=mes_anterior&status_atend=<?= $filtro_status_atend ?>&status_vendas=<?= $filtro_status_vendas ?>">Mês Anterior</a></li>
                                <li><a class="dropdown-item <?= $filtro_atual === 'ultimos_30_dias' ? 'active' : '' ?>" href="?periodo=ultimos_30_dias&status_atend=<?= $filtro_status_atend ?>&status_vendas=<?= $filtro_status_vendas ?>">Últimos 30 dias</a></li>
                                <li><a class="dropdown-item <?= $filtro_atual === 'ultimos_60_dias' ? 'active' : '' ?>" href="?periodo=ultimos_60_dias&status_atend=<?= $filtro_status_atend ?>&status_vendas=<?= $filtro_status_vendas ?>">Últimos 60 dias</a></li>
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

                    <!-- Busca Vendas -->
                    <div class="mb-2">
                        <div class="d-flex gap-2">
                            <input type="text" id="buscaVendas" class="form-control form-control-sm" placeholder="Buscar por ID, CPF ou nome..." style="border-radius: 20px;">
                            <button type="button" class="btn btn-sm btn-outline-primary" style="border-radius: 20px;" onclick="buscarVendas()">
                                <i class="bi bi-search"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" style="border-radius: 20px; display: none;" id="btnLimparBuscaVendas" onclick="limparBuscaVendas()">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Filtros de Status Vendas -->
                    <div class="filter-tabs">
                        <a href="?status_vendas=abertos&periodo=<?= $filtro_atual ?>&status_atend=<?= $filtro_status_atend ?>" class="filter-tab <?= $filtro_status_vendas === 'abertos' ? 'active' : '' ?>" onclick="filtrarVendas('abertos'); return false;">
                            <i class="bi bi-folder2-open"></i> Abertos
                        </a>
                        <a href="?status_vendas=concluidos&periodo=<?= $filtro_atual ?>&status_atend=<?= $filtro_status_atend ?>" class="filter-tab <?= $filtro_status_vendas === 'concluidos' ? 'active' : '' ?>" onclick="filtrarVendas('concluidos'); return false;">
                            <i class="bi bi-check-circle"></i> Concluídos
                        </a>
                        <a href="?status_vendas=todos&periodo=<?= $filtro_atual ?>&status_atend=<?= $filtro_status_atend ?>" class="filter-tab <?= $filtro_status_vendas === 'todos' ? 'active' : '' ?>" onclick="filtrarVendas('todos'); return false;">
                            <i class="bi bi-list"></i> Todos
                        </a>
                    </div>

                    <div id="vendas_info" class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">
                            Exibindo <span id="vendas_count">0</span> de <span id="vendas_total">0</span>
                            <span id="vendas_filtro_label"></span>
                        </small>
                        <span class="badge bg-primary" id="vendas_badge">0</span>
                    </div>

                    <div id="listaVendas">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                            <p class="mt-2 text-muted">Carregando vendas...</p>
                        </div>
                    </div>

                    <div id="paginacao_vendas" class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top" style="display: none;">
                        <small class="text-muted">
                            Página <span id="pagina_atual">1</span> de <span id="total_paginas">1</span>
                        </small>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" id="btnVendasAnterior" onclick="paginaVendasAnterior()" disabled>
                                <i class="bi bi-chevron-left"></i> Anterior
                            </button>
                            <button class="btn btn-outline-primary" id="btnVendasProxima" onclick="paginaVendasProxima()">
                                Próxima <i class="bi bi-chevron-right"></i>
                            </button>
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
                    <input type="hidden" name="status_atend" value="<?= $filtro_status_atend ?>">
                    <input type="hidden" name="status_vendas" value="<?= $filtro_status_vendas ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Data Início</label>
                            <input type="date" class="form-control" name="data_inicio" value="<?= $data_inicio ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data Fim</label>
                            <input type="date" class="form-control" name="data_fim" value="<?= $data_fim ?>" required>
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

    <!-- Modal CPFs Duplicados -->
    <div class="modal fade" id="modalDuplicados" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-warning"></i> CPFs Duplicados</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Clientes com mesmo CPF em múltiplos títulos:</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>CPF/CNPJ</th>
                                    <th>Qtd</th>
                                    <th>Títulos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cpfs_duplicados as $dup): ?>
                                <tr>
                                    <td><?= formatarDocumento($dup['documento_cliente']) ?></td>
                                    <td><span class="badge bg-warning"><?= $dup['qtd'] ?></span></td>
                                    <td>
                                        <?php
                                        $titulos = explode(', ', $dup['titulos']);
                                        foreach ($titulos as $t):
                                        ?>
                                        <a href="titulo?id=<?= htmlspecialchars(trim($t)) ?>" class="badge bg-primary text-decoration-none me-1"><?= htmlspecialchars(trim($t)) ?></a>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Atualização em Massa -->
    <div class="modal fade" id="modalBulk" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-collection"></i> Atualização em Massa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Cole os IDs dos títulos (um por linha ou separados por espaço/vírgula)</label>
                        <textarea class="form-control" id="bulk_ids" rows="5" placeholder="SFA-11057&#10;SFA-11039&#10;SFA-11046&#10;ou: SFA-11057, SFA-11039, SFA-11046"></textarea>
                        <small class="text-muted"><span id="bulk_count">0</span> títulos identificados</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ação a executar</label>
                        <select class="form-select" id="bulk_action">
                            <option value="">Selecione a ação...</option>
                            <option value="concluir">✅ Concluir atendimentos</option>
                            <option value="pendente">⏸️ Marcar como pendente</option>
                            <option value="em_andamento">▶️ Marcar como em andamento</option>
                            <option value="marcar_item">📋 Marcar item do checklist</option>
                        </select>
                    </div>
                    <div class="mb-3" id="bulk_item_options" style="display: none;">
                        <label class="form-label">Item do checklist</label>
                        <select class="form-select" id="bulk_item_codigo">
                            <option value="">Selecione o item...</option>
                            <option value="abert_cumprimentar">Cumprimentou o cliente</option>
                            <option value="valid_confirmar_cpf">Confirmou CPF</option>
                            <option value="portal_informar">Informou sobre o portal</option>
                            <option value="financ_explicou_anuidade">Explicou anuidade</option>
                            <option value="encerr_despedida">Despediu-se do cliente</option>
                        </select>
                        <div class="mt-2">
                            <label class="form-label">Resposta do item</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="bulk_resposta" id="bulk_resp_pos" value="Positivo" checked>
                                    <label class="form-check-label text-success" for="bulk_resp_pos"><i class="bi bi-emoji-smile"></i> Positivo</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="bulk_resposta" id="bulk_resp_neu" value="Neutro">
                                    <label class="form-check-label text-warning" for="bulk_resp_neu"><i class="bi bi-emoji-neutral"></i> Neutro</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="bulk_resposta" id="bulk_resp_neg" value="Negativo">
                                    <label class="form-check-label text-danger" for="bulk_resp_neg"><i class="bi bi-emoji-frown"></i> Negativo</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="bulk_preview" class="alert alert-info" style="display: none;">
                        <i class="bi bi-info-circle"></i> <span id="bulk_preview_text"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnExecutarBulk" onclick="executarBulk()">
                        <i class="bi bi-lightning"></i> Executar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    const dataInicio = '<?= $data_inicio ?>';
    const dataFim = '<?= $data_fim ?>';
    let filtroStatusVendas = '<?= $filtro_status_vendas ?>';
    let paginaAtual = 1;
    let totalPaginas = 1;
    let vendasCarregadas = [];
    let buscaVendasTexto = '';

    $(document).ready(function() {
        carregarVendas();

        // Busca vendas com Enter
        $('#buscaVendas').on('keypress', function(e) {
            if (e.which === 13) {
                buscarVendas();
            }
        });

        // Pesquisa global
        let searchTimeout;
        $('#searchGlobal').on('input', function() {
            clearTimeout(searchTimeout);
            const query = $(this).val().trim();

            if (query.length < 2) {
                $('#searchResults').removeClass('show').empty();
                return;
            }

            searchTimeout = setTimeout(function() {
                pesquisarGlobal(query);
            }, 300);
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.search-global').length) {
                $('#searchResults').removeClass('show');
            }
        });
    });

    function pesquisarGlobal(query) {
        $.ajax({
            url: 'api_pesquisa.php',
            method: 'GET',
            data: { q: query },
            success: function(response) {
                const results = $('#searchResults');
                results.empty();

                if (response.success && response.data.length > 0) {
                    response.data.forEach(function(item) {
                        results.append(`
                            <a href="titulo?id=${escapeHtml(item.numero_titulo)}" class="search-result-item">
                                <div class="d-flex justify-content-between">
                                    <strong>${escapeHtml(item.nome_cliente)}</strong>
                                    <span class="badge bg-${item.status === 'concluido' ? 'success' : (item.status === 'em_andamento' ? 'warning' : 'secondary')}">${item.status}</span>
                                </div>
                                <small class="text-muted">
                                    <i class="bi bi-card-text"></i> ${escapeHtml(item.numero_titulo)} |
                                    <i class="bi bi-person-vcard"></i> ${escapeHtml(item.documento_cliente || '-')}
                                </small>
                            </a>
                        `);
                    });
                    results.addClass('show');
                } else {
                    results.html('<div class="text-center text-muted p-3">Nenhum resultado encontrado</div>');
                    results.addClass('show');
                }
            }
        });
    }

    function filtrarVendas(status) {
        filtroStatusVendas = status;
        paginaAtual = 1;
        vendasCarregadas = [];
        // Update active class
        $('.filter-tabs a[onclick*="filtrarVendas"]').removeClass('active');
        $(`.filter-tabs a[onclick*="filtrarVendas('${status}')"]`).addClass('active');
        carregarVendas();
    }

    function buscarVendas() {
        buscaVendasTexto = $('#buscaVendas').val().trim();
        paginaAtual = 1;
        vendasCarregadas = [];
        if (buscaVendasTexto) {
            $('#btnLimparBuscaVendas').show();
        }
        carregarVendas();
    }

    function limparBuscaVendas() {
        buscaVendasTexto = '';
        $('#buscaVendas').val('');
        $('#btnLimparBuscaVendas').hide();
        paginaAtual = 1;
        vendasCarregadas = [];
        carregarVendas();
    }

    function carregarVendas() {
        $('#listaVendas').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted">Carregando vendas...</p>
            </div>
        `);

        $.ajax({
            url: 'api_vendas.php',
            method: 'GET',
            data: {
                data_inicio: dataInicio,
                data_fim: dataFim,
                status: filtroStatusVendas,
                pagina: paginaAtual,
                por_pagina: 50,
                busca: buscaVendasTexto
            },
            success: function(response) {
                if (response.success) {
                    totalPaginas = response.total_paginas || 1;

                    // Labels para filtro
                    const filtroLabels = {
                        'abertos': 'abertos',
                        'concluidos': 'concluídos',
                        'todos': 'títulos'
                    };
                    const filtroLabel = filtroLabels[filtroStatusVendas] || 'títulos';

                    // Atualizar contadores
                    $('#vendas_count').text(Math.min(paginaAtual * 50, response.total));
                    $('#vendas_total').text(response.total);
                    $('#vendas_filtro_label').text(filtroLabel);
                    $('#vendas_badge').text(response.total + ' ' + filtroLabel);
                    $('#vendas_info').css('display', 'flex');

                    // Atualizar paginação
                    $('#pagina_atual').text(paginaAtual);
                    $('#total_paginas').text(totalPaginas);

                    vendasCarregadas = response.data;
                    renderizarVendas(response.data);

                    // Atualizar botões de paginação
                    if (totalPaginas > 1) {
                        $('#paginacao_vendas').css('display', 'flex');
                        $('#btnVendasAnterior').prop('disabled', paginaAtual <= 1);
                        $('#btnVendasProxima').prop('disabled', paginaAtual >= totalPaginas);
                    } else {
                        $('#paginacao_vendas').hide();
                    }
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

    function paginaVendasAnterior() {
        if (paginaAtual > 1) {
            paginaAtual--;
            carregarVendas();
        }
    }

    function paginaVendasProxima() {
        if (paginaAtual < totalPaginas) {
            paginaAtual++;
            carregarVendas();
        }
    }

    function renderizarVendas(vendas) {
        const lista = $('#listaVendas');
        lista.empty();

        if (vendas.length === 0) {
            lista.html(`
                <div class="text-center text-muted py-4">
                    <i class="bi bi-calendar-x" style="font-size: 3rem;"></i>
                    <p class="mt-2">Nenhuma venda encontrada</p>
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
                <a href="titulo?id=${escapeHtml(venda.numero_titulo)}" class="venda-card ${statusClass}">
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

    // Bulk Update Functions
    $('#bulk_ids').on('input', function() {
        const ids = parseBulkIds($(this).val());
        $('#bulk_count').text(ids.length);
        atualizarPreviewBulk();
    });

    $('#bulk_action').on('change', function() {
        const action = $(this).val();
        if (action === 'marcar_item') {
            $('#bulk_item_options').show();
        } else {
            $('#bulk_item_options').hide();
        }
        atualizarPreviewBulk();
    });

    function parseBulkIds(text) {
        if (!text) return [];
        // Separar por vírgula, espaço ou nova linha
        const ids = text.split(/[\s,\n]+/).filter(id => id.trim().length > 0);
        return [...new Set(ids)]; // Remove duplicatas
    }

    function atualizarPreviewBulk() {
        const ids = parseBulkIds($('#bulk_ids').val());
        const action = $('#bulk_action').val();

        if (ids.length === 0 || !action) {
            $('#bulk_preview').hide();
            return;
        }

        let actionText = '';
        switch(action) {
            case 'concluir': actionText = 'concluir'; break;
            case 'pendente': actionText = 'marcar como pendente'; break;
            case 'em_andamento': actionText = 'marcar como em andamento'; break;
            case 'marcar_item':
                const item = $('#bulk_item_codigo option:selected').text();
                const resposta = $('input[name="bulk_resposta"]:checked').val();
                actionText = `marcar "${item}" como ${resposta}`;
                break;
        }

        $('#bulk_preview_text').text(`Você irá ${actionText} em ${ids.length} título(s)`);
        $('#bulk_preview').show();
    }

    function executarBulk() {
        const ids = parseBulkIds($('#bulk_ids').val());
        const action = $('#bulk_action').val();

        if (ids.length === 0) {
            alert('Cole pelo menos um ID de título!');
            return;
        }

        if (!action) {
            alert('Selecione a ação a executar!');
            return;
        }

        const data = {
            ids: ids,
            action: action
        };

        if (action === 'marcar_item') {
            const itemCodigo = $('#bulk_item_codigo').val();
            const resposta = $('input[name="bulk_resposta"]:checked').val();

            if (!itemCodigo) {
                alert('Selecione o item do checklist!');
                return;
            }

            data.item_codigo = itemCodigo;
            data.resposta = resposta;
        }

        if (!confirm(`Confirma a execução em ${ids.length} título(s)?`)) {
            return;
        }

        $('#btnExecutarBulk').prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Processando...');

        $.ajax({
            url: 'api_bulk_update.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(response) {
                if (response.success) {
                    alert(`Operação concluída!\n\n${response.processados} título(s) processado(s)\n${response.erros} erro(s)`);
                    bootstrap.Modal.getInstance(document.getElementById('modalBulk')).hide();
                    location.reload();
                } else {
                    alert('Erro: ' + response.error);
                }
                $('#btnExecutarBulk').prop('disabled', false).html('<i class="bi bi-lightning"></i> Executar');
            },
            error: function() {
                alert('Erro ao processar a requisição');
                $('#btnExecutarBulk').prop('disabled', false).html('<i class="bi bi-lightning"></i> Executar');
            }
        });
    }
    </script>
</body>
</html>
