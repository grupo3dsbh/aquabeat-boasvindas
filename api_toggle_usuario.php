<?php
require_once 'config.php';
Auth::requireAdmin();

header('Content-Type: application/json');

try {
    $db = Database::getConnectionBV();
    
    $id = $_POST['id'] ?? null;
    $ativo = $_POST['ativo'] ?? 0;
    
    if (!$id) {
        throw new Exception('ID não informado');
    }
    
    // Não permitir desativar o próprio usuário
    if ($id == Auth::getUserId() && $ativo == 0) {
        throw new Exception('Você não pode desativar seu próprio usuário');
    }
    
    $stmt = $db->prepare("UPDATE usuarios SET ativo = :ativo WHERE id = :id");
    $stmt->execute([
        ':ativo' => $ativo,
        ':id' => $id
    ]);
    
    jsonResponse([
        'success' => true,
        'message' => $ativo ? 'Usuário ativado com sucesso!' : 'Usuário desativado com sucesso!'
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
