<?php
/**
 * API para atualização em massa de títulos
 */

require_once 'config.php';
Auth::requireLogin();

header('Content-Type: application/json');

try {
    $db = Database::getConnectionBV();
    $usuario_id = Auth::getUserId();

    // Ler JSON do body
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['ids']) || empty($input['action'])) {
        throw new Exception('Dados inválidos');
    }

    $ids = $input['ids'];
    $action = $input['action'];

    $processados = 0;
    $erros = 0;
    $detalhes = [];

    foreach ($ids as $numero_titulo) {
        try {
            $numero_titulo = trim($numero_titulo);
            if (empty($numero_titulo)) continue;

            // Buscar o boas_vindas pelo numero_titulo
            $stmt = $db->prepare("SELECT id FROM boas_vindas WHERE numero_titulo = :numero_titulo");
            $stmt->execute([':numero_titulo' => $numero_titulo]);
            $bv = $stmt->fetch();

            if (!$bv) {
                // Tentar criar a partir da API se não existe
                try {
                    $db_api = Database::getConnectionAPI();
                    $stmt_api = $db_api->prepare("SELECT * FROM titulos WHERE numero_titulo = :numero_titulo");
                    $stmt_api->execute([':numero_titulo' => $numero_titulo]);
                    $titulo_api = $stmt_api->fetch();

                    if ($titulo_api) {
                        $stmt_insert = $db->prepare("
                            INSERT INTO boas_vindas (numero_titulo, usuario_id, status, nome_cliente, documento_cliente, telefone, email, data_venda, tipo_titulo, valor_total, forma_pagamento, promotor)
                            VALUES (:numero_titulo, :usuario_id, 'pendente', :nome_cliente, :documento_cliente, :telefone, :email, :data_venda, :tipo_titulo, :valor_total, :forma_pagamento, :promotor)
                        ");
                        $stmt_insert->execute([
                            ':numero_titulo' => $titulo_api['numero_titulo'],
                            ':usuario_id' => $usuario_id,
                            ':nome_cliente' => $titulo_api['nome_titular'] ?? null,
                            ':documento_cliente' => $titulo_api['documento_titular'] ?? null,
                            ':telefone' => $titulo_api['telefone_residencial'] ?? null,
                            ':email' => $titulo_api['email'] ?? null,
                            ':data_venda' => $titulo_api['data_primeira_venda'] ?? null,
                            ':tipo_titulo' => $titulo_api['nome_produto_atual'] ?? null,
                            ':valor_total' => $titulo_api['valor_total_plano'] ?? null,
                            ':forma_pagamento' => $titulo_api['forma_pagamento'] ?? null,
                            ':promotor' => $titulo_api['promotor'] ?? null
                        ]);
                        $bv = ['id' => $db->lastInsertId()];
                    }
                } catch (Exception $e) {
                    // Ignorar erro de API
                }
            }

            if (!$bv) {
                $erros++;
                $detalhes[] = "$numero_titulo: não encontrado";
                continue;
            }

            $bv_id = $bv['id'];

            switch ($action) {
                case 'concluir':
                    $stmt_update = $db->prepare("UPDATE boas_vindas SET status = 'concluido', concluido_em = NOW() WHERE id = :id");
                    $stmt_update->execute([':id' => $bv_id]);
                    break;

                case 'pendente':
                    $stmt_update = $db->prepare("UPDATE boas_vindas SET status = 'pendente' WHERE id = :id");
                    $stmt_update->execute([':id' => $bv_id]);
                    break;

                case 'em_andamento':
                    $stmt_update = $db->prepare("UPDATE boas_vindas SET status = 'em_andamento', atualizado_em = NOW() WHERE id = :id");
                    $stmt_update->execute([':id' => $bv_id]);
                    break;

                case 'marcar_item':
                    $item_codigo = $input['item_codigo'] ?? '';
                    $resposta = $input['resposta'] ?? 'Positivo';

                    if (empty($item_codigo)) {
                        throw new Exception('Código do item não informado');
                    }

                    // Verificar se já existe interação
                    $stmt_check = $db->prepare("SELECT id FROM titulo_interacoes WHERE boas_vindas_id = :bv_id AND etapa_codigo = :codigo");
                    $stmt_check->execute([':bv_id' => $bv_id, ':codigo' => $item_codigo]);
                    $exists = $stmt_check->fetch();

                    if ($exists) {
                        $stmt_int = $db->prepare("UPDATE titulo_interacoes SET valor_checkbox = 1, valor_texto = :resposta, atualizado_em = NOW() WHERE id = :id");
                        $stmt_int->execute([':resposta' => $resposta, ':id' => $exists['id']]);
                    } else {
                        $stmt_int = $db->prepare("
                            INSERT INTO titulo_interacoes (boas_vindas_id, etapa_codigo, valor_checkbox, valor_texto, criado_em)
                            VALUES (:bv_id, :codigo, 1, :resposta, NOW())
                        ");
                        $stmt_int->execute([':bv_id' => $bv_id, ':codigo' => $item_codigo, ':resposta' => $resposta]);
                    }
                    break;

                default:
                    throw new Exception('Ação inválida');
            }

            $processados++;
            $detalhes[] = "$numero_titulo: OK";

        } catch (Exception $e) {
            $erros++;
            $detalhes[] = "$numero_titulo: " . $e->getMessage();
        }
    }

    // Log da operação em massa
    try {
        Logger::log(null, 'bulk_update', "Atualização em massa: $action - $processados processados, $erros erros");
    } catch (Exception $e) {}

    jsonResponse([
        'success' => true,
        'processados' => $processados,
        'erros' => $erros,
        'detalhes' => $detalhes
    ]);

} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
