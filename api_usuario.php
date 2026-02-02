<?php
require_once 'config.php';
Auth::requireAdmin();

header('Content-Type: application/json');

try {
    $db = Database::getConnectionBV();
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        throw new Exception('ID não informado');
    }
    
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        throw new Exception('Usuário não encontrado');
    }
    
    jsonResponse([
        'success' => true,
        'data' => $usuario
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
