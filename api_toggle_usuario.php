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
    
    // Buscar nome do usuario antes de alterar
    $stmt_nome = $db->prepare("SELECT nome FROM usuarios WHERE id = :id");
    $stmt_nome->execute([':id' => $id]);
    $usuario_info = $stmt_nome->fetch();
    $nome_usuario = $usuario_info ? $usuario_info['nome'] : 'ID ' . $id;

    $stmt = $db->prepare("UPDATE usuarios SET ativo = :ativo WHERE id = :id");
    $stmt->execute([
        ':ativo' => $ativo,
        ':id' => $id
    ]);

    // Registrar log de atividade
    Logger::log(
        null,
        $ativo ? 'usuario_ativado' : 'usuario_desativado',
        ($ativo ? 'Ativou' : 'Desativou') . ' o usuário: ' . $nome_usuario,
        ['usuario_id' => $id, 'ativo' => $ativo]
    );

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
