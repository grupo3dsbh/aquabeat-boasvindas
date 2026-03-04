<?php
/**
 * API para registrar pagamento de parcela
 */
require_once 'config.php';

header('Content-Type: application/json');

// Verificar autenticação
Auth::requireLogin();

// Verificar permissão
if (!Auth::isAdmin() && !Auth::temPermissao('editar_pagamento')) {
    jsonResponse(['success' => false, 'error' => 'Sem permissão para esta ação'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Método não permitido'], 405);
}

try {
    $db = Database::getConnectionBV();

    $boas_vindas_id = $_POST['boas_vindas_id'] ?? null;
    $numero_titulo = $_POST['numero_titulo'] ?? null;
    $valor_parcela = floatval($_POST['valor_parcela'] ?? 0);
    $data_pagamento = $_POST['data_pagamento'] ?? null;
    $forma_pagamento = $_POST['forma_pagamento'] ?? null;
    $observacao = trim($_POST['observacao'] ?? '');

    // Validações
    if (!$boas_vindas_id || !$numero_titulo) {
        jsonResponse(['success' => false, 'error' => 'Dados do título não informados']);
    }
    if ($valor_parcela <= 0) {
        jsonResponse(['success' => false, 'error' => 'Valor da parcela inválido']);
    }
    if (!$data_pagamento) {
        jsonResponse(['success' => false, 'error' => 'Data do pagamento não informada']);
    }
    if (!$forma_pagamento) {
        jsonResponse(['success' => false, 'error' => 'Forma de pagamento não informada']);
    }

    // Registrar o pagamento na tabela titulo_pagamentos
    $stmt = $db->prepare("
        INSERT INTO titulo_pagamentos
        (boas_vindas_id, numero_titulo, valor, data_pagamento, forma_pagamento, observacao, registrado_por, criado_em)
        VALUES
        (:boas_vindas_id, :numero_titulo, :valor, :data_pagamento, :forma_pagamento, :observacao, :registrado_por, NOW())
    ");

    $stmt->execute([
        ':boas_vindas_id' => $boas_vindas_id,
        ':numero_titulo' => $numero_titulo,
        ':valor' => $valor_parcela,
        ':data_pagamento' => $data_pagamento,
        ':forma_pagamento' => $forma_pagamento,
        ':observacao' => $observacao,
        ':registrado_por' => Auth::getUserId()
    ]);

    $pagamento_id = $db->lastInsertId();

    // Registrar log
    $stmt_log = $db->prepare("
        INSERT INTO titulo_logs (boas_vindas_id, usuario_id, tipo_log, valor_novo, observacao, criado_em)
        VALUES (:boas_vindas_id, :usuario_id, 'pagamento_registrado', :valor_novo, :observacao, NOW())
    ");
    $stmt_log->execute([
        ':boas_vindas_id' => $boas_vindas_id,
        ':usuario_id' => Auth::getUserId(),
        ':valor_novo' => json_encode([
            'valor' => $valor_parcela,
            'data' => $data_pagamento,
            'forma' => $forma_pagamento
        ]),
        ':observacao' => $observacao
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Pagamento registrado com sucesso',
        'pagamento_id' => $pagamento_id
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Erro ao salvar: ' . $e->getMessage()]);
}
