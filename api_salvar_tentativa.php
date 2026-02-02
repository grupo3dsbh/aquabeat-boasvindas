<?php
require_once 'config.php';
Auth::requireLogin();

header('Content-Type: application/json');

try {
    $db = Database::getConnectionBV();
    
    $boas_vindas_id = $_POST['boas_vindas_id'] ?? null;
    $tipo_tentativa = $_POST['tipo_tentativa'] ?? null;
    $resultado = $_POST['resultado'] ?? null;
    $observacao = $_POST['observacao'] ?? null;
    
    if (!$boas_vindas_id || !$tipo_tentativa || !$resultado) {
        throw new Exception('Dados incompletos');
    }
    
    // Inserir tentativa
    $stmt = $db->prepare("
        INSERT INTO tentativas_contato 
        (boas_vindas_id, usuario_id, tipo_tentativa, resultado, observacao)
        VALUES 
        (:boas_vindas_id, :usuario_id, :tipo_tentativa, :resultado, :observacao)
    ");
    
    $stmt->execute([
        ':boas_vindas_id' => $boas_vindas_id,
        ':usuario_id' => Auth::getUserId(),
        ':tipo_tentativa' => $tipo_tentativa,
        ':resultado' => $resultado,
        ':observacao' => $observacao
    ]);
    
    // Atualizar contadores
    $stmt_update = $db->prepare("
        UPDATE boas_vindas 
        SET tentativas_contato = tentativas_contato + 1,
            ultima_tentativa = NOW()
        WHERE id = :id
    ");
    $stmt_update->execute([':id' => $boas_vindas_id]);
    
    // Log
    Logger::log(
        $boas_vindas_id,
        'tentativa_contato',
        "Tentativa de contato: {$tipo_tentativa} - {$resultado}"
    );
    
    jsonResponse([
        'success' => true,
        'message' => 'Tentativa registrada com sucesso!'
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
