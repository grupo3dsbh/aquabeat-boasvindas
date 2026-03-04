<?php
require_once 'config.php';
Auth::requireAdmin();

header('Content-Type: application/json');

try {
    $db = Database::getConnectionBV();
    
    $id = $_POST['id'] ?? null;
    $nome = $_POST['nome'] ?? null;
    $email = $_POST['email'] ?? null;
    $tipo = $_POST['tipo'] ?? 'atendente';
    $senha = $_POST['senha'] ?? null;
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $permissoes = $_POST['permissoes'] ?? [];

    // Para admins, permissões é sempre null (eles têm todas)
    $permissoesJson = ($tipo === 'admin') ? null : json_encode(array_values($permissoes));
    
    if (!$nome || !$email) {
        throw new Exception('Nome e e-mail são obrigatórios');
    }
    
    if ($id) {
        // Atualizar
        $sql = "UPDATE usuarios SET nome = :nome, email = :email, tipo = :tipo, ativo = :ativo, permissoes = :permissoes";
        $params = [
            ':nome' => $nome,
            ':email' => $email,
            ':tipo' => $tipo,
            ':ativo' => $ativo,
            ':permissoes' => $permissoesJson,
            ':id' => $id
        ];

        if ($senha) {
            $sql .= ", senha = MD5(:senha)";
            $params[':senha'] = $senha;
        }

        $sql .= " WHERE id = :id";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $mensagem = 'Usuário atualizado com sucesso!';
        
    } else {
        // Criar novo
        if (!$senha) {
            throw new Exception('Senha é obrigatória para novos usuários');
        }
        
        // Verificar se e-mail já existe
        $stmt_check = $db->prepare("SELECT id FROM usuarios WHERE email = :email");
        $stmt_check->execute([':email' => $email]);
        if ($stmt_check->fetch()) {
            throw new Exception('Este e-mail já está cadastrado');
        }
        
        $stmt = $db->prepare("
            INSERT INTO usuarios (nome, email, senha, tipo, ativo, permissoes)
            VALUES (:nome, :email, MD5(:senha), :tipo, :ativo, :permissoes)
        ");

        $stmt->execute([
            ':nome' => $nome,
            ':email' => $email,
            ':senha' => $senha,
            ':tipo' => $tipo,
            ':ativo' => $ativo,
            ':permissoes' => $permissoesJson
        ]);
        
        $mensagem = 'Usuário criado com sucesso!';
    }
    
    jsonResponse([
        'success' => true,
        'message' => $mensagem
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
