<?php
/**
 * API de Pesquisa Global
 * Pesquisa por CPF, ID do Título ou Nome do Cliente
 */

require_once 'config.php';
Auth::requireLogin();

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'error' => 'Termo muito curto']);
    exit;
}

try {
    $db = Database::getConnectionBV();

    // Limpar query para pesquisa
    $queryLimpa = preg_replace('/[^a-zA-Z0-9]/', '', $query);
    $queryLike = '%' . $query . '%';
    $queryLikeNumeros = '%' . $queryLimpa . '%';

    $stmt = $db->prepare("
        SELECT
            bv.numero_titulo,
            bv.nome_cliente,
            bv.documento_cliente,
            bv.telefone,
            bv.status
        FROM boas_vindas bv
        WHERE bv.numero_titulo LIKE :q1
           OR bv.nome_cliente LIKE :q2
           OR REPLACE(REPLACE(REPLACE(bv.documento_cliente, '.', ''), '-', ''), '/', '') LIKE :q3
           OR bv.telefone LIKE :q4
        ORDER BY
            CASE WHEN bv.numero_titulo LIKE :q5 THEN 0 ELSE 1 END,
            bv.criado_em DESC
        LIMIT 10
    ");

    $stmt->execute([
        ':q1' => $queryLike,
        ':q2' => $queryLike,
        ':q3' => $queryLikeNumeros,
        ':q4' => $queryLikeNumeros,
        ':q5' => $queryLike
    ]);

    $resultados = $stmt->fetchAll();

    // Se não encontrou no boas_vindas, buscar na API (titulos)
    if (empty($resultados)) {
        try {
            $db_api = Database::getConnectionAPI();
            $stmt_api = $db_api->prepare("
                SELECT
                    numero_titulo,
                    nome_titular as nome_cliente,
                    documento_titular as documento_cliente,
                    telefone_residencial as telefone,
                    'pendente' as status
                FROM titulos
                WHERE numero_titulo LIKE :q1
                   OR nome_titular LIKE :q2
                   OR REPLACE(REPLACE(REPLACE(documento_titular, '.', ''), '-', ''), '/', '') LIKE :q3
                ORDER BY data_primeira_venda DESC
                LIMIT 10
            ");
            $stmt_api->execute([
                ':q1' => $queryLike,
                ':q2' => $queryLike,
                ':q3' => $queryLikeNumeros
            ]);
            $resultados = $stmt_api->fetchAll();
        } catch (Exception $e) {
            // API database not available
        }
    }

    echo json_encode(['success' => true, 'data' => $resultados]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
