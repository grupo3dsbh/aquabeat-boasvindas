<?php
require_once 'config.php';
Auth::requireLogin();

header('Content-Type: application/json');

try {
    $db = Database::getConnectionBV();
    
    $boas_vindas_id = $_GET['boas_vindas_id'] ?? null;
    
    if (!$boas_vindas_id) {
        throw new Exception('ID não informado');
    }
    
    $stmt = $db->prepare("
        SELECT * FROM tentativas_contato
        WHERE boas_vindas_id = :boas_vindas_id
        ORDER BY criado_em DESC
    ");
    
    $stmt->execute([':boas_vindas_id' => $boas_vindas_id]);
    $tentativas = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $tentativas
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
