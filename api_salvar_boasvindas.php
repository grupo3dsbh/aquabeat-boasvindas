<?php
require_once 'config.php';
Auth::requireLogin();

header('Content-Type: application/json');

try {
    $db = Database::getConnectionBV();
    
    $boas_vindas_id = $_POST['boas_vindas_id'] ?? null;
    $concluir = $_POST['concluir'] ?? '0';
    
    if (!$boas_vindas_id) {
        throw new Exception('ID do boas-vindas não informado');
    }
    
    // Processar checklists
    $checklists = [
        'preparacao' => json_encode($_POST['preparacao'] ?? []),
        'abertura' => json_encode($_POST['abertura'] ?? []),
        'validacao' => json_encode($_POST['validacao'] ?? []),
        'portal' => json_encode($_POST['portal'] ?? []),
        'atendimento' => json_encode($_POST['atendimento'] ?? []),
        'financeiro' => json_encode($_POST['financeiro'] ?? []),
        'informacoes' => json_encode($_POST['informacoes'] ?? []),
        'duvidas' => json_encode($_POST['duvidas'] ?? []),
        'ofertas' => json_encode($_POST['ofertas'] ?? []),
        'encerramento' => json_encode($_POST['encerramento'] ?? []),
        'registro' => json_encode($_POST['registro'] ?? [])
    ];
    
    // Atualizar registro
    $sql = "
        UPDATE boas_vindas SET
            checklist_preparacao = :checklist_preparacao,
            checklist_abertura = :checklist_abertura,
            checklist_validacao = :checklist_validacao,
            checklist_portal = :checklist_portal,
            checklist_atendimento = :checklist_atendimento,
            checklist_financeiro = :checklist_financeiro,
            checklist_informacoes = :checklist_informacoes,
            checklist_duvidas = :checklist_duvidas,
            checklist_ofertas = :checklist_ofertas,
            checklist_encerramento = :checklist_encerramento,
            checklist_registro = :checklist_registro,
            acessou_portal = :acessou_portal,
            resetou_senha = :resetou_senha,
            feedback_consultor = :feedback_consultor,
            nota_atendimento_consultor = :nota_atendimento_consultor,
            problema_pagamento_entrada = :problema_pagamento_entrada,
            sabia_anuidade = :sabia_anuidade,
            agendou_primeira_visita = :agendou_primeira_visita,
            data_agendamento = :data_agendamento,
            adicionou_grupo_whatsapp = :adicionou_grupo_whatsapp,
            enviou_resumo_whatsapp = :enviou_resumo_whatsapp,
            observacoes = :observacoes
    ";
    
    $params = [
        ':checklist_preparacao' => $checklists['preparacao'],
        ':checklist_abertura' => $checklists['abertura'],
        ':checklist_validacao' => $checklists['validacao'],
        ':checklist_portal' => $checklists['portal'],
        ':checklist_atendimento' => $checklists['atendimento'],
        ':checklist_financeiro' => $checklists['financeiro'],
        ':checklist_informacoes' => $checklists['informacoes'],
        ':checklist_duvidas' => $checklists['duvidas'],
        ':checklist_ofertas' => $checklists['ofertas'],
        ':checklist_encerramento' => $checklists['encerramento'],
        ':checklist_registro' => $checklists['registro'],
        ':acessou_portal' => $_POST['acessou_portal'] ?? 0,
        ':resetou_senha' => $_POST['resetou_senha'] ?? 0,
        ':feedback_consultor' => $_POST['feedback_consultor'] ?? null,
        ':nota_atendimento_consultor' => $_POST['nota_atendimento_consultor'] ?? null,
        ':problema_pagamento_entrada' => $_POST['problema_pagamento_entrada'] ?? null,
        ':sabia_anuidade' => $_POST['sabia_anuidade'] ?? 0,
        ':agendou_primeira_visita' => $_POST['agendou_primeira_visita'] ?? 0,
        ':data_agendamento' => $_POST['data_agendamento'] ?: null,
        ':adicionou_grupo_whatsapp' => $_POST['adicionou_grupo_whatsapp'] ?? 0,
        ':enviou_resumo_whatsapp' => $_POST['enviou_resumo_whatsapp'] ?? 0,
        ':observacoes' => $_POST['observacoes'] ?? null
    ];
    
    if ($concluir === '1') {
        $sql .= ", status = 'concluido', concluido_em = NOW()";
    }
    
    $sql .= " WHERE id = :id";
    $params[':id'] = $boas_vindas_id;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    // Log
    Logger::log(
        $boas_vindas_id,
        $concluir === '1' ? 'conclusao' : 'update_checklist',
        $concluir === '1' ? 'Boas-vindas concluído' : 'Checklist atualizado'
    );
    
    jsonResponse([
        'success' => true,
        'message' => $concluir === '1' ? 'Boas-vindas concluído com sucesso!' : 'Progresso salvo com sucesso!'
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
