<?php
require_once 'config.php';
Auth::requireAdmin();

header('Content-Type: application/json');

try {
    $db = Database::getConnectionBV();
    
    $id = $_POST['id'] ?? null;
    
    if (!$id) {
        throw new Exception('ID não informado');
    }
    
    // Não permitir excluir o próprio usuário
    if ($id == Auth::getUserId()) {
        throw new Exception('Você não pode excluir seu próprio usuário');
    }
    
    // Verificar se tem atendimentos
    $stmt_check = $db->prepare("SELECT COUNT(*) as total FROM boas_vindas WHERE usuario_id = :id");
    $stmt_check->execute([':id' => $id]);
    $check = $stmt_check->fetch();
    
    if ($check['total'] > 0) {
        throw new Exception('Este usuário possui ' . $check['total'] . ' atendimento(s) vinculado(s) e não pode ser excluído. Você pode desativá-lo.');
    }
    
    $stmt = $db->prepare("DELETE FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Usuário excluído com sucesso!'
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
