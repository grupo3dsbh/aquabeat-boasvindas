<?php
require_once 'config.php';
Auth::requireLogin();

header('Content-Type: application/json');

try {
    $db = Database::getConnectionBV();

    $id = $_POST['id'] ?? $_POST['boas_vindas_id'] ?? null;

    if (!$id) {
        throw new Exception('ID do boas-vindas não informado');
    }

    // Verificar se o registro existe
    $stmt_check = $db->prepare("SELECT id FROM boas_vindas WHERE id = :id");
    $stmt_check->execute([':id' => $id]);
    if (!$stmt_check->fetch()) {
        throw new Exception('Registro não encontrado');
    }

    // Campos permitidos para atualização
    $campos_permitidos = [
        'status', 'telefone', 'email', 'observacoes',
        'pagamento_obs', 'promotor_obs',
        'acessou_portal', 'resetou_senha', 'feedback_consultor',
        'nota_atendimento_consultor', 'problema_pagamento_entrada',
        'sabia_anuidade', 'agendou_primeira_visita', 'data_agendamento',
        'adicionou_grupo_whatsapp', 'enviou_resumo_whatsapp'
    ];

    $updates = [];
    $params = [':id' => $id];

    foreach ($_POST as $campo => $valor) {
        if ($campo === 'id' || $campo === 'boas_vindas_id') continue;

        if (in_array($campo, $campos_permitidos)) {
            $updates[] = "$campo = :$campo";
            $params[":$campo"] = $valor === '' ? null : $valor;
        }
    }

    // Concluir atendimento
    if (isset($_POST['status']) && $_POST['status'] === 'concluido') {
        $updates[] = "concluido_em = NOW()";
    }

    // Se status mudou para em_andamento e estava pendente
    if (isset($_POST['status']) && $_POST['status'] === 'em_andamento') {
        $updates[] = "atualizado_em = NOW()";
    }

    if (empty($updates)) {
        throw new Exception('Nenhum campo para atualizar');
    }

    $sql = "UPDATE boas_vindas SET " . implode(', ', $updates) . " WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    // Log
    $tipo_log = 'update';
    if (isset($_POST['status'])) {
        $tipo_log = $_POST['status'] === 'concluido' ? 'conclusao' : 'status_alterado';
    } elseif (isset($_POST['telefone'])) {
        $tipo_log = 'telefone_atualizado';
    }

    try {
        Logger::log($id, $tipo_log, 'Dados atualizados: ' . implode(', ', array_keys(array_filter($_POST, fn($k) => $k !== 'id' && $k !== 'boas_vindas_id', ARRAY_FILTER_USE_KEY))));
    } catch (Exception $e) {}

    jsonResponse([
        'success' => true,
        'message' => 'Salvo com sucesso!'
    ]);

} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
