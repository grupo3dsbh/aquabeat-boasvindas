<?php
/**
 * API para buscar vendas do mês atual
 * Endpoint: api_vendas.php
 */

require_once 'config.php';

header('Content-Type: application/json');

// Verificar autenticação
Auth::requireLogin();

try {
    $db_api = Database::getConnectionAPI();
    $db_bv = Database::getConnectionBV();

    // Verificar se a tabela titulos existe
    $stmt_check = $db_api->query("SHOW TABLES LIKE 'titulos'");
    if ($stmt_check->rowCount() === 0) {
        // Listar tabelas disponíveis para debug
        $stmt_tables = $db_api->query("SHOW TABLES");
        $tabelas = $stmt_tables->fetchAll(PDO::FETCH_COLUMN);

        jsonResponse([
            'success' => false,
            'error' => 'Tabela "titulos" não encontrada no banco ' . API_DB_NAME . '. Tabelas disponíveis: ' . implode(', ', $tabelas),
            'tabelas_disponiveis' => $tabelas
        ], 404);
    }

    // Buscar vendas - aceitar filtro de data e status via GET
    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $data_fim = $_GET['data_fim'] ?? date('Y-m-t');
    $filtro_status = $_GET['status'] ?? 'abertos'; // abertos, concluidos, todos
    $pagina = max(1, intval($_GET['pagina'] ?? 1));
    $por_pagina = min(200, max(20, intval($_GET['por_pagina'] ?? 50)));
    $offset = ($pagina - 1) * $por_pagina;
    $busca = trim($_GET['busca'] ?? '');

    // Validar datas
    $primeiro_dia_mes = date('Y-m-d 00:00:00', strtotime($data_inicio));
    $ultimo_dia_mes = date('Y-m-d 23:59:59', strtotime($data_fim));

    // Verificar quais colunas existem na tabela
    $stmt_cols = $db_api->query("DESCRIBE titulos");
    $colunas = $stmt_cols->fetchAll(PDO::FETCH_COLUMN);

    // Mapeamento de colunas possíveis
    $campo_map = [
        'numero_titulo' => ['numero_titulo', 'num_titulo', 'titulo', 'codigo'],
        'nome_cliente' => ['nome_titular', 'nome_cliente', 'cliente', 'nome'],
        'documento' => ['documento_titular', 'cpf', 'documento', 'cpf_cnpj'],
        'telefone_residencial' => ['telefone_residencial', 'telefone', 'celular', 'fone'],
        'data_venda' => ['data_primeira_venda', 'data_venda', 'data_cadastro', 'created_at'],
        'tipo_titulo' => ['nome_produto_atual', 'produto', 'tipo_titulo', 'plano'],
        'valor_total' => ['valor_total_plano', 'valor_total', 'valor', 'preco'],
        'forma_pagamento' => ['forma_pagamento', 'pagamento'],
        'promotor' => ['promotor', 'vendedor', 'consultor']
    ];

    $campos_encontrados = [];
    foreach ($campo_map as $alias => $possiveis) {
        foreach ($possiveis as $campo) {
            if (in_array($campo, $colunas)) {
                $campos_encontrados[$alias] = $campo;
                break;
            }
        }
    }

    // Verificar se temos os campos mínimos
    if (!isset($campos_encontrados['numero_titulo']) || !isset($campos_encontrados['nome_cliente'])) {
        jsonResponse([
            'success' => false,
            'error' => 'Colunas obrigatórias não encontradas. Colunas disponíveis: ' . implode(', ', $colunas),
            'colunas_disponiveis' => $colunas
        ], 400);
    }

    // Construir SELECT
    $select_parts = ['id'];
    foreach ($campos_encontrados as $alias => $campo) {
        $select_parts[] = "$campo as $alias";
    }

    // Determinar campo de data para filtro
    $campo_data = $campos_encontrados['data_venda'] ?? null;
    $tem_usado_relatorios = in_array('usado_relatorios', $colunas);

    $sql = "SELECT " . implode(', ', $select_parts) . " FROM titulos";

    // Adicionar filtros
    $where = [];
    $params = [];

    if ($campo_data) {
        $where[] = "$campo_data >= :primeiro_dia";
        $where[] = "$campo_data <= :ultimo_dia";
        $params[':primeiro_dia'] = $primeiro_dia_mes;
        $params[':ultimo_dia'] = $ultimo_dia_mes;
    }

    if ($tem_usado_relatorios) {
        $where[] = "usado_relatorios = 1";
    }

    // Adicionar busca por texto
    if (!empty($busca)) {
        $busca_parts = [];
        $tem_busca_doc = false;
        if (isset($campos_encontrados['numero_titulo'])) {
            $busca_parts[] = $campos_encontrados['numero_titulo'] . " LIKE :busca";
        }
        if (isset($campos_encontrados['nome_cliente'])) {
            $busca_parts[] = $campos_encontrados['nome_cliente'] . " LIKE :busca";
        }
        if (isset($campos_encontrados['documento'])) {
            $busca_parts[] = $campos_encontrados['documento'] . " LIKE :busca_doc";
            $tem_busca_doc = true;
        }
        if (!empty($busca_parts)) {
            $where[] = "(" . implode(' OR ', $busca_parts) . ")";
            $params[':busca'] = "%$busca%";
            if ($tem_busca_doc) {
                $params[':busca_doc'] = preg_replace('/[^0-9]/', '', $busca) . '%';
            }
        }
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    // Primeiro, contar total de registros no período (com filtros aplicados)
    $sql_count = "SELECT COUNT(*) as total FROM titulos";
    if (!empty($where)) {
        $sql_count .= " WHERE " . implode(' AND ', $where);
    }
    $stmt_count = $db_api->prepare($sql_count);
    $stmt_count->execute($params);
    $total_registros = $stmt_count->fetch()['total'];

    if ($campo_data) {
        $sql .= " ORDER BY $campo_data DESC";
    } else {
        $sql .= " ORDER BY id DESC";
    }

    // Sem limite para poder filtrar por status depois (o filtro de status é em PHP)
    // Mas com limite razoável para não sobrecarregar
    $sql .= " LIMIT 500";

    $stmt = $db_api->prepare($sql);
    $stmt->execute($params);

    $vendas = $stmt->fetchAll();

    // Para cada venda, verificar se já existe boas-vindas
    $vendas_filtradas = [];
    foreach ($vendas as &$venda) {
        $numero = $venda['numero_titulo'] ?? null;
        if (!$numero) continue;

        $stmt_bv = $db_bv->prepare("
            SELECT id, status, usuario_id, concluido_em
            FROM boas_vindas
            WHERE numero_titulo = :numero_titulo
            ORDER BY criado_em DESC
            LIMIT 1
        ");

        $stmt_bv->execute([':numero_titulo' => $numero]);
        $boas_vindas = $stmt_bv->fetch();

        if ($boas_vindas) {
            $venda['boas_vindas_id'] = $boas_vindas['id'];
            $venda['boas_vindas_status'] = $boas_vindas['status'];
            $venda['boas_vindas_concluido_em'] = $boas_vindas['concluido_em'];

            if ($boas_vindas['usuario_id']) {
                $stmt_user = $db_bv->prepare("SELECT nome FROM usuarios WHERE id = :id");
                $stmt_user->execute([':id' => $boas_vindas['usuario_id']]);
                $user = $stmt_user->fetch();
                $venda['atendente'] = $user['nome'] ?? null;
            }
        } else {
            $venda['boas_vindas_id'] = null;
            $venda['boas_vindas_status'] = null;
            $venda['boas_vindas_concluido_em'] = null;
            $venda['atendente'] = null;
        }

        // Filtrar por status
        $status = $venda['boas_vindas_status'];
        if ($filtro_status === 'abertos') {
            if ($status !== 'concluido') {
                $vendas_filtradas[] = $venda;
            }
        } elseif ($filtro_status === 'concluidos') {
            if ($status === 'concluido') {
                $vendas_filtradas[] = $venda;
            }
        } else {
            // todos
            $vendas_filtradas[] = $venda;
        }
    }

    // Aplicar paginação nos resultados filtrados
    $total_filtrado = count($vendas_filtradas);
    $total_paginas = ceil($total_filtrado / $por_pagina);
    $vendas_paginadas = array_slice($vendas_filtradas, $offset, $por_pagina);

    jsonResponse([
        'success' => true,
        'data' => $vendas_paginadas,
        'total' => $total_filtrado,
        'total_registros_periodo' => $total_registros,
        'pagina' => $pagina,
        'por_pagina' => $por_pagina,
        'total_paginas' => $total_paginas,
        'periodo' => [
            'inicio' => $primeiro_dia_mes,
            'fim' => $ultimo_dia_mes
        ]
    ]);

} catch (PDOException $e) {
    jsonResponse([
        'success' => false,
        'error' => 'Erro ao buscar vendas: ' . $e->getMessage()
    ], 500);
}
