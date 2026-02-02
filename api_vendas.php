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
    
    // Buscar vendas do mês atual
    $primeiro_dia_mes = date('Y-m-01 00:00:00');
    $ultimo_dia_mes = date('Y-m-t 23:59:59');
    
    $sql = "
        SELECT 
            id,
            numero_titulo,
            nome_titular as nome_cliente,
            documento_titular,
            telefone_residencial,
            data_primeira_venda as data_venda,
            nome_produto_atual as tipo_titulo,
            valor_total_plano as valor_total,
            forma_pagamento,
            tipo_pagamento,
            promotor,
            status_titulo,
            status_inadimplencia,
            dias_desde_venda
        FROM titulos_analise
        WHERE data_primeira_venda >= :primeiro_dia
        AND data_primeira_venda <= :ultimo_dia
        AND usado_relatorios = 1
        ORDER BY data_primeira_venda DESC
    ";
    
    $stmt = $db_api->prepare($sql);
    $stmt->execute([
        ':primeiro_dia' => $primeiro_dia_mes,
        ':ultimo_dia' => $ultimo_dia_mes
    ]);
    
    $vendas = $stmt->fetchAll();
    
    // Para cada venda, verificar se já existe boas-vindas
    foreach ($vendas as &$venda) {
        $stmt_bv = $db_bv->prepare("
            SELECT id, status, usuario_id, concluido_em
            FROM boas_vindas 
            WHERE numero_titulo = :numero_titulo
            ORDER BY criado_em DESC
            LIMIT 1
        ");
        
        $stmt_bv->execute([':numero_titulo' => $venda['numero_titulo']]);
        $boas_vindas = $stmt_bv->fetch();
        
        if ($boas_vindas) {
            $venda['boas_vindas_id'] = $boas_vindas['id'];
            $venda['boas_vindas_status'] = $boas_vindas['status'];
            $venda['boas_vindas_concluido_em'] = $boas_vindas['concluido_em'];
            
            // Buscar nome do atendente
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
    }
    
    jsonResponse([
        'success' => true,
        'data' => $vendas,
        'total' => count($vendas),
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
