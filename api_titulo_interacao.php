<?php
/**
 * API para salvar interações do título
 * Salva checkboxes, textos e números do checklist de boas-vindas
 */
require_once 'config.php';
Auth::requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Método não permitido'], 405);
}

$db = Database::getConnectionBV();

$boas_vindas_id = $_POST['boas_vindas_id'] ?? null;

if (!$boas_vindas_id) {
    jsonResponse(['success' => false, 'error' => 'ID do boas-vindas não informado'], 400);
}

// Verificar se é para salvar observações gerais
if (isset($_POST['observacoes_gerais'])) {
    $stmt = $db->prepare("UPDATE boas_vindas SET observacoes = :observacoes WHERE id = :id");
    $stmt->execute([
        ':observacoes' => $_POST['observacoes_gerais'],
        ':id' => $boas_vindas_id
    ]);

    // Log
    try {
        $stmt_log = $db->prepare("
            INSERT INTO titulo_logs (boas_vindas_id, usuario_id, tipo_log, valor_novo, ip_usuario)
            VALUES (:boas_vindas_id, :usuario_id, 'observacao_salva', :valor_novo, :ip)
        ");
        $stmt_log->execute([
            ':boas_vindas_id' => $boas_vindas_id,
            ':usuario_id' => Auth::getUserId(),
            ':valor_novo' => substr($_POST['observacoes_gerais'], 0, 500),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        // Ignorar erro de log
    }

    jsonResponse(['success' => true]);
}

// Salvar interação do checklist
$etapa_codigo = $_POST['etapa_codigo'] ?? null;

if (!$etapa_codigo) {
    jsonResponse(['success' => false, 'error' => 'Código da etapa não informado'], 400);
}

$valor_checkbox = isset($_POST['valor_checkbox']) ? (int)$_POST['valor_checkbox'] : null;
$valor_texto = $_POST['valor_texto'] ?? null;
$valor_numero = isset($_POST['valor_numero']) && $_POST['valor_numero'] !== '' ? (float)$_POST['valor_numero'] : null;

try {
    // Verificar se a tabela existe
    $stmt_check = $db->query("SHOW TABLES LIKE 'titulo_interacoes'");
    if ($stmt_check->rowCount() === 0) {
        // Criar tabela se não existir
        $db->exec("
            CREATE TABLE IF NOT EXISTS titulo_interacoes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                boas_vindas_id INT NOT NULL,
                etapa_codigo VARCHAR(50) NOT NULL,
                valor_checkbox TINYINT(1) DEFAULT 0,
                valor_texto TEXT,
                valor_numero DECIMAL(10,2),
                usuario_id INT NOT NULL,
                telefone_contato VARCHAR(50),
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_interacao (boas_vindas_id, etapa_codigo)
            )
        ");
    }

    // Verificar se já existe registro
    $stmt = $db->prepare("
        SELECT id, valor_checkbox, valor_texto, valor_numero
        FROM titulo_interacoes
        WHERE boas_vindas_id = :boas_vindas_id AND etapa_codigo = :etapa_codigo
    ");
    $stmt->execute([
        ':boas_vindas_id' => $boas_vindas_id,
        ':etapa_codigo' => $etapa_codigo
    ]);
    $existente = $stmt->fetch();

    $valor_anterior = null;
    $valor_novo = null;
    $tipo_log = 'interacao';

    if ($existente) {
        // Atualizar
        $updates = [];
        $params = [
            ':boas_vindas_id' => $boas_vindas_id,
            ':etapa_codigo' => $etapa_codigo,
            ':usuario_id' => Auth::getUserId()
        ];

        if ($valor_checkbox !== null) {
            $updates[] = 'valor_checkbox = :valor_checkbox';
            $params[':valor_checkbox'] = $valor_checkbox;
            $valor_anterior = $existente['valor_checkbox'];
            $valor_novo = $valor_checkbox;
            $tipo_log = $valor_checkbox ? 'checkbox_marcado' : 'checkbox_desmarcado';
        }
        if ($valor_texto !== null) {
            $updates[] = 'valor_texto = :valor_texto';
            $params[':valor_texto'] = $valor_texto;
            $valor_anterior = $existente['valor_texto'];
            $valor_novo = $valor_texto;
            $tipo_log = 'texto_salvo';
        }
        if ($valor_numero !== null) {
            $updates[] = 'valor_numero = :valor_numero';
            $params[':valor_numero'] = $valor_numero;
            $valor_anterior = $existente['valor_numero'];
            $valor_novo = $valor_numero;
            $tipo_log = 'numero_salvo';
        }

        $updates[] = 'usuario_id = :usuario_id';

        $stmt = $db->prepare("
            UPDATE titulo_interacoes
            SET " . implode(', ', $updates) . "
            WHERE boas_vindas_id = :boas_vindas_id AND etapa_codigo = :etapa_codigo
        ");
        $stmt->execute($params);
    } else {
        // Inserir
        $stmt = $db->prepare("
            INSERT INTO titulo_interacoes (boas_vindas_id, etapa_codigo, valor_checkbox, valor_texto, valor_numero, usuario_id)
            VALUES (:boas_vindas_id, :etapa_codigo, :valor_checkbox, :valor_texto, :valor_numero, :usuario_id)
        ");
        $stmt->execute([
            ':boas_vindas_id' => $boas_vindas_id,
            ':etapa_codigo' => $etapa_codigo,
            ':valor_checkbox' => $valor_checkbox ?? 0,
            ':valor_texto' => $valor_texto,
            ':valor_numero' => $valor_numero,
            ':usuario_id' => Auth::getUserId()
        ]);

        $valor_novo = $valor_checkbox ?? $valor_texto ?? $valor_numero;
        $tipo_log = $valor_checkbox ? 'checkbox_marcado' : ($valor_texto ? 'texto_salvo' : 'numero_salvo');
    }

    // Verificar se tabela de logs existe
    $stmt_check_log = $db->query("SHOW TABLES LIKE 'titulo_logs'");
    if ($stmt_check_log->rowCount() === 0) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS titulo_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                boas_vindas_id INT NOT NULL,
                usuario_id INT NOT NULL,
                tipo_log VARCHAR(50) NOT NULL,
                etapa_codigo VARCHAR(50),
                valor_anterior TEXT,
                valor_novo TEXT,
                observacao TEXT,
                ip_usuario VARCHAR(50),
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    // Registrar log
    $stmt_log = $db->prepare("
        INSERT INTO titulo_logs (boas_vindas_id, usuario_id, tipo_log, etapa_codigo, valor_anterior, valor_novo, ip_usuario)
        VALUES (:boas_vindas_id, :usuario_id, :tipo_log, :etapa_codigo, :valor_anterior, :valor_novo, :ip)
    ");
    $stmt_log->execute([
        ':boas_vindas_id' => $boas_vindas_id,
        ':usuario_id' => Auth::getUserId(),
        ':tipo_log' => $tipo_log,
        ':etapa_codigo' => $etapa_codigo,
        ':valor_anterior' => $valor_anterior,
        ':valor_novo' => $valor_novo,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    // Atualizar status para em_andamento se estava pendente
    $stmt_status = $db->prepare("
        UPDATE boas_vindas
        SET status = 'em_andamento', iniciado_em = COALESCE(iniciado_em, NOW())
        WHERE id = :id AND status = 'pendente'
    ");
    $stmt_status->execute([':id' => $boas_vindas_id]);

    // Calcular e atualizar progresso
    $stmt_progresso = $db->prepare("
        SELECT COUNT(*) as total FROM titulo_interacoes
        WHERE boas_vindas_id = :id AND valor_checkbox = 1
    ");
    $stmt_progresso->execute([':id' => $boas_vindas_id]);
    $progresso = $stmt_progresso->fetch()['total'];

    $stmt_update_prog = $db->prepare("
        UPDATE boas_vindas SET checklist_progresso = :progresso WHERE id = :id
    ");
    try {
        $stmt_update_prog->execute([':progresso' => $progresso, ':id' => $boas_vindas_id]);
    } catch (Exception $e) {
        // Coluna pode não existir ainda
    }

    jsonResponse([
        'success' => true,
        'message' => 'Interação salva',
        'tipo_log' => $tipo_log
    ]);

} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => 'Erro ao salvar: ' . $e->getMessage()
    ], 500);
}
