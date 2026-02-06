<?php
/**
 * API para migrar atendimentos entre usuários
 */

require_once 'config.php';
Auth::requireAdmin();

header('Content-Type: application/json');

try {
    $db = Database::getConnectionBV();

    $origem_id = $_POST['origem_id'] ?? null;
    $destino_id = $_POST['destino_id'] ?? null;
    $tipo = $_POST['tipo'] ?? 'todos';

    if (!$origem_id || !$destino_id) {
        throw new Exception('IDs de origem e destino são obrigatórios');
    }

    if ($origem_id == $destino_id) {
        throw new Exception('Origem e destino não podem ser iguais');
    }

    // Verificar se usuários existem
    $stmt_orig = $db->prepare("SELECT id, nome FROM usuarios WHERE id = :id");
    $stmt_orig->execute([':id' => $origem_id]);
    $usuario_origem = $stmt_orig->fetch();

    $stmt_dest = $db->prepare("SELECT id, nome FROM usuarios WHERE id = :id");
    $stmt_dest->execute([':id' => $destino_id]);
    $usuario_destino = $stmt_dest->fetch();

    if (!$usuario_origem || !$usuario_destino) {
        throw new Exception('Usuário de origem ou destino não encontrado');
    }

    // Construir query de atualização
    $sql = "UPDATE boas_vindas SET usuario_id = :destino_id WHERE usuario_id = :origem_id";
    $params = [
        ':destino_id' => $destino_id,
        ':origem_id' => $origem_id
    ];

    if ($tipo === 'abertos') {
        $sql .= " AND status IN ('pendente', 'em_andamento')";
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $migrados = $stmt->rowCount();

    // Log da migração
    try {
        $log_msg = sprintf(
            'Migração de %d atendimentos de %s para %s (%s)',
            $migrados,
            $usuario_origem['nome'],
            $usuario_destino['nome'],
            $tipo === 'abertos' ? 'apenas abertos' : 'todos'
        );

        $stmt_log = $db->prepare("
            INSERT INTO titulo_logs (boas_vindas_id, usuario_id, tipo_log, descricao, criado_em)
            SELECT id, :user_id, 'migracao', :descricao, NOW()
            FROM boas_vindas WHERE usuario_id = :destino_id
            LIMIT 1
        ");
        $stmt_log->execute([
            ':user_id' => Auth::getUserId(),
            ':descricao' => $log_msg,
            ':destino_id' => $destino_id
        ]);
    } catch (Exception $e) {
        // Log não é crítico
    }

    jsonResponse([
        'success' => true,
        'migrados' => $migrados,
        'message' => "Migração realizada com sucesso! $migrados atendimentos foram transferidos."
    ]);

} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
