<?php
require_once 'config.php';
Auth::requireLogin();

$db_bv = Database::getConnectionBV();
$db_api = Database::getConnectionAPI();

// Verificar se é novo ou continuação
$boas_vindas_id = $_GET['id'] ?? null;
$numero_titulo = $_GET['titulo'] ?? null;

$boas_vindas = null;
$venda_dados = null;

if ($boas_vindas_id) {
    // Carregar boas-vindas existente
    $stmt = $db_bv->prepare("SELECT * FROM boas_vindas WHERE id = :id");
    $stmt->execute([':id' => $boas_vindas_id]);
    $boas_vindas = $stmt->fetch();
    
    if (!$boas_vindas) {
        die("Boas-vindas não encontrado!");
    }
    
    $numero_titulo = $boas_vindas['numero_titulo'];
}

// Buscar dados da venda
$stmt_venda = $db_api->prepare("
    SELECT * FROM titulos_analise 
    WHERE numero_titulo = :numero_titulo 
    LIMIT 1
");
$stmt_venda->execute([':numero_titulo' => $numero_titulo]);
$venda_dados = $stmt_venda->fetch();

if (!$venda_dados) {
    die("Venda não encontrada!");
}

// Se é novo, criar registro
if (!$boas_vindas_id) {
    $stmt_insert = $db_bv->prepare("
        INSERT INTO boas_vindas (
            numero_titulo, usuario_id, status,
            nome_cliente, documento_cliente, telefone, data_venda,
            tipo_titulo, valor_total, forma_pagamento, promotor,
            iniciado_em
        ) VALUES (
            :numero_titulo, :usuario_id, 'em_andamento',
            :nome_cliente, :documento_cliente, :telefone, :data_venda,
            :tipo_titulo, :valor_total, :forma_pagamento, :promotor,
            NOW()
        )
    ");
    
    $stmt_insert->execute([
        ':numero_titulo' => $numero_titulo,
        ':usuario_id' => Auth::getUserId(),
        ':nome_cliente' => $venda_dados['nome_titular'],
        ':documento_cliente' => $venda_dados['documento_titular'],
        ':telefone' => $venda_dados['telefone_residencial'],
        ':data_venda' => $venda_dados['data_primeira_venda'],
        ':tipo_titulo' => $venda_dados['nome_produto_atual'],
        ':valor_total' => $venda_dados['valor_total_plano'],
        ':forma_pagamento' => $venda_dados['forma_pagamento'],
        ':promotor' => $venda_dados['promotor']
    ]);
    
    $boas_vindas_id = $db_bv->lastInsertId();
    
    // Recarregar
    $stmt = $db_bv->prepare("SELECT * FROM boas_vindas WHERE id = :id");
    $stmt->execute([':id' => $boas_vindas_id]);
    $boas_vindas = $stmt->fetch();
}

// Decodificar checklists
$checklists = [
    'preparacao' => json_decode($boas_vindas['checklist_preparacao'] ?? '[]', true) ?: [],
    'abertura' => json_decode($boas_vindas['checklist_abertura'] ?? '[]', true) ?: [],
    'validacao' => json_decode($boas_vindas['checklist_validacao'] ?? '[]', true) ?: [],
    'portal' => json_decode($boas_vindas['checklist_portal'] ?? '[]', true) ?: [],
    'atendimento' => json_decode($boas_vindas['checklist_atendimento'] ?? '[]', true) ?: [],
    'financeiro' => json_decode($boas_vindas['checklist_financeiro'] ?? '[]', true) ?: [],
    'informacoes' => json_decode($boas_vindas['checklist_informacoes'] ?? '[]', true) ?: [],
    'duvidas' => json_decode($boas_vindas['checklist_duvidas'] ?? '[]', true) ?: [],
    'ofertas' => json_decode($boas_vindas['checklist_ofertas'] ?? '[]', true) ?: [],
    'encerramento' => json_decode($boas_vindas['checklist_encerramento'] ?? '[]', true) ?: [],
    'registro' => json_decode($boas_vindas['checklist_registro'] ?? '[]', true) ?: []
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boas-Vindas - <?= htmlspecialchars($venda_dados['nome_titular']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: #f5f7fa;
        }
        .header-cliente {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .checklist-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .checklist-section h5 {
            color: #667eea;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .checklist-item {
            display: flex;
            align-items: flex-start;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 10px;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        .checklist-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        .checklist-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 15px;
            cursor: pointer;
        }
        .checklist-item.checked {
            background: #d4edda;
        }
        .script-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            font-style: italic;
        }
        .script-box strong {
            color: #1565c0;
            font-style: normal;
        }
        .btn-salvar {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .info-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 10px;
        }
        .obs-section {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header com dados do cliente -->
        <div class="header-cliente">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h3><i class="bi bi-person-circle"></i> <?= htmlspecialchars($venda_dados['nome_titular']) ?></h3>
                    <div class="mt-3">
                        <span class="info-badge bg-white text-dark">
                            <i class="bi bi-card-text"></i> ID: <?= htmlspecialchars($numero_titulo) ?>
                        </span>
                        <span class="info-badge bg-white text-dark">
                            <i class="bi bi-telephone"></i> <?= formatarTelefone($venda_dados['telefone_residencial']) ?>
                        </span>
                        <span class="info-badge bg-white text-dark">
                            <i class="bi bi-calendar"></i> <?= formatarData($venda_dados['data_primeira_venda'], 'd/m/Y') ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <a href="index.php" class="btn btn-light">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </a>
                    <button type="button" class="btn btn-warning" onclick="registrarTentativa()">
                        <i class="bi bi-telephone"></i> Registrar Tentativa
                    </button>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <!-- Dados da Venda -->
                <div class="checklist-section">
                    <h5><i class="bi bi-info-circle"></i> Dados da Venda</h5>
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">Título:</th>
                            <td><?= htmlspecialchars($venda_dados['nome_produto_atual']) ?></td>
                        </tr>
                        <tr>
                            <th>Valor:</th>
                            <td><?= formatarMoeda($venda_dados['valor_total_plano']) ?></td>
                        </tr>
                        <tr>
                            <th>Forma Pgto:</th>
                            <td><?= htmlspecialchars($venda_dados['forma_pagamento']) ?></td>
                        </tr>
                        <tr>
                            <th>Promotor:</th>
                            <td><?= htmlspecialchars($venda_dados['promotor']) ?></td>
                        </tr>
                        <tr>
                            <th>CPF:</th>
                            <td><?= formatarDocumento($venda_dados['documento_titular']) ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Tentativas de Contato -->
                <div class="checklist-section">
                    <h5><i class="bi bi-telephone-fill"></i> Tentativas de Contato</h5>
                    <div id="listaTentativas">
                        <p class="text-muted">Carregando...</p>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <form id="formBoasVindas">
                    <input type="hidden" name="boas_vindas_id" value="<?= $boas_vindas_id ?>">
                    
                    <!-- PREPARAÇÃO -->
                    <div class="checklist-section">
                        <h5><i class="bi bi-wrench"></i> 1. PREPARAÇÃO ANTES DA LIGAÇÃO</h5>
                        <?php
                        $itens_preparacao = [
                            'Verificar dados do cliente no sistema',
                            'Conferir dados do consultor que vendeu',
                            'Verificar se pagamento da entrada foi confirmado',
                            'Ter acesso ao portal para suporte'
                        ];
                        foreach ($itens_preparacao as $i => $item): ?>
                            <div class="checklist-item">
                                <input type="checkbox" name="preparacao[]" value="<?= $i ?>" 
                                       <?= in_array($i, $checklists['preparacao']) ? 'checked' : '' ?>>
                                <span><?= $item ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- ABERTURA -->
                    <div class="checklist-section">
                        <h5><i class="bi bi-chat-dots"></i> 2. ABERTURA DA LIGAÇÃO</h5>
                        
                        <div class="script-box">
                            <strong>Script:</strong><br>
                            "Bom dia/Boa tarde!<br><br>
                            Meu nome é <strong><?= htmlspecialchars(Auth::getUserName()) ?></strong>, estou ligando do setor de Boas-Vindas do Aquabeat. 
                            Posso falar alguns minutos com você?<br><br>
                            <em>[AGUARDAR RESPOSTA]</em><br><br>
                            Que ótimo! Estou ligando para te dar as boas-vindas como novo titular e 
                            ajudar com qualquer dúvida sobre seu título. Tudo bem para você agora?"
                        </div>
                        
                        <?php
                        $itens_abertura = [
                            'Cumprimentar com energia e cordialidade',
                            'Identificar-se do setor de Boas-Vindas',
                            'Perguntar se pode falar alguns minutos',
                            'Explicar o motivo da ligação'
                        ];
                        foreach ($itens_abertura as $i => $item): ?>
                            <div class="checklist-item">
                                <input type="checkbox" name="abertura[]" value="<?= $i ?>"
                                       <?= in_array($i, $checklists['abertura']) ? 'checked' : '' ?>>
                                <span><?= $item ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- VALIDAÇÃO -->
                    <div class="checklist-section">
                        <h5><i class="bi bi-shield-check"></i> 3. VALIDAÇÃO DO TITULAR</h5>
                        
                        <div class="script-box">
                            <strong>Script:</strong><br>
                            "Perfeito! Para sua segurança, preciso confirmar alguns dados rapidinho.<br><br>
                            Seu ID de Cota é <strong><?= htmlspecialchars($numero_titulo) ?></strong>, está correto?<br><br>
                            <em>[AGUARDAR]</em><br><br>
                            Ótimo! E pode me confirmar os 4 últimos dígitos do seu CPF?<br>
                            (Últimos 4: <strong><?= substr(preg_replace('/[^0-9]/', '', $venda_dados['documento_titular']), -4) ?></strong>)<br><br>
                            <em>[AGUARDAR CONFIRMAÇÃO]</em><br><br>
                            Perfeito, confirmado!"
                        </div>
                        
                        <?php
                        $itens_validacao = [
                            'Solicitar confirmação dos dados',
                            'Informar ID da Cota e pedir confirmação',
                            'Solicitar 4 últimos dígitos do CPF',
                            'Aguardar confirmação positiva'
                        ];
                        foreach ($itens_validacao as $i => $item): ?>
                            <div class="checklist-item">
                                <input type="checkbox" name="validacao[]" value="<?= $i ?>"
                                       <?= in_array($i, $checklists['validacao']) ? 'checked' : '' ?>>
                                <span><?= $item ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- PORTAL -->
                    <div class="checklist-section">
                        <h5><i class="bi bi-pc-display"></i> 4. INFORMAÇÕES SOBRE O PORTAL</h5>
                        
                        <div class="script-box">
                            <strong>Script:</strong><br>
                            "<?= htmlspecialchars($venda_dados['nome_titular']) ?>, você já acessou o portal do titular?<br><br>
                            <strong>[SE SIM]</strong><br>
                            'Que ótimo! Conseguiu acessar sem problemas?'<br><br>
                            <strong>[SE NÃO]</strong><br>
                            'Sem problemas! O portal é bem simples. Quer que eu te ajude a fazer o primeiro acesso agora?'<br><br>
                            <strong>[SE TIVER PROBLEMA COM SENHA]</strong><br>
                            'Tranquilo! Posso resetar sua senha agora mesmo. Pode ser?'"
                        </div>
                        
                        <?php
                        $itens_portal = [
                            'Informar que o título pode ser gerenciado pelo portal',
                            'Perguntar se já acessou o portal',
                            'Verificar se teve dificuldades',
                            'Se necessário, oferecer reset de senha',
                            'Instruir sobre cadastro de dependentes',
                            'Informar o link do portal'
                        ];
                        foreach ($itens_portal as $i => $item): ?>
                            <div class="checklist-item">
                                <input type="checkbox" name="portal[]" value="<?= $i ?>"
                                       <?= in_array($i, $checklists['portal']) ? 'checked' : '' ?>>
                                <span><?= $item ?></span>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="mt-3">
                            <label class="form-label">Cliente acessou portal?</label>
                            <select class="form-select" name="acessou_portal">
                                <option value="0" <?= $boas_vindas['acessou_portal'] == 0 ? 'selected' : '' ?>>Não</option>
                                <option value="1" <?= $boas_vindas['acessou_portal'] == 1 ? 'selected' : '' ?>>Sim</option>
                            </select>
                        </div>
                        
                        <div class="mt-2">
                            <label class="form-label">Resetou senha?</label>
                            <select class="form-select" name="resetou_senha">
                                <option value="0" <?= $boas_vindas['resetou_senha'] == 0 ? 'selected' : '' ?>>Não</option>
                                <option value="1" <?= $boas_vindas['resetou_senha'] == 1 ? 'selected' : '' ?>>Sim</option>
                            </select>
                        </div>
                    </div>

                    <!-- VALIDAÇÃO DO ATENDIMENTO -->
                    <div class="checklist-section">
                        <h5><i class="bi bi-star"></i> 5. VALIDAÇÃO DO ATENDIMENTO DO CONSULTOR</h5>
                        
                        <div class="script-box">
                            <strong>Script:</strong><br>
                            "Vi aqui que quem te atendeu foi o(a) <strong><?= htmlspecialchars($venda_dados['promotor']) ?></strong>. 
                            Ele(a) deu um bom atendimento para você?<br><br>
                            <em>[AGUARDAR FEEDBACK]</em><br><br>
                            <strong>[SE POSITIVO]</strong> 'Que ótimo! Vou repassar esse feedback para ele(a)!'<br>
                            <strong>[SE NEGATIVO]</strong> 'Entendo... pode me contar o que aconteceu para melhorarmos?'"
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Feedback do Atendimento:</label>
                            <textarea class="form-control" name="feedback_consultor" rows="3"><?= htmlspecialchars($boas_vindas['feedback_consultor'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nota do Atendimento (1-5):</label>
                            <select class="form-select" name="nota_atendimento_consultor">
                                <option value="">Não avaliado</option>
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <option value="<?= $i ?>" <?= $boas_vindas['nota_atendimento_consultor'] == $i ? 'selected' : '' ?>>
                                        <?= $i ?> - <?= ['Péssimo', 'Ruim', 'Regular', 'Bom', 'Excelente'][$i-1] ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <!-- VALIDAÇÃO FINANCEIRA -->
                    <div class="checklist-section">
                        <h5><i class="bi bi-currency-dollar"></i> 6. VALIDAÇÃO FINANCEIRA</h5>
                        
                        <div class="script-box">
                            <strong>Script:</strong><br>
                            "E me conta, como foi para você fazer o pagamento da entrada? Teve alguma dificuldade?<br><br>
                            <em>[AGUARDAR]</em><br><br>
                            E o consultor te informou sobre a anuidade do título?<br><br>
                            <strong>[SE SIM]</strong><br>
                            'Perfeito! Só para confirmar: a anuidade é de R$ [VALOR] e vence todo dia [DIA] de [MÊS], certo?'<br><br>
                            <strong>[SE NÃO]</strong><br>
                            'Deixa eu te explicar então: seu título tem uma anuidade de R$ [VALOR] que vence todo dia [DIA] de [MÊS]. 
                            Você vai receber boleto automaticamente. Tudo bem?'"
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Teve problema com pagamento da entrada?</label>
                            <textarea class="form-control" name="problema_pagamento_entrada" rows="2"><?= htmlspecialchars($boas_vindas['problema_pagamento_entrada'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Cliente sabia sobre a anuidade?</label>
                            <select class="form-select" name="sabia_anuidade">
                                <option value="0" <?= $boas_vindas['sabia_anuidade'] == 0 ? 'selected' : '' ?>>Não</option>
                                <option value="1" <?= $boas_vindas['sabia_anuidade'] == 1 ? 'selected' : '' ?>>Sim</option>
                            </select>
                        </div>
                    </div>

                    <!-- OFERTAS -->
                    <div class="checklist-section">
                        <h5><i class="bi bi-gift"></i> 7. OFERTAS E AGENDAMENTO</h5>
                        
                        <div class="script-box">
                            <strong>Script:</strong><br>
                            "Já que estamos aqui, quer aproveitar e agendar sua primeira visita agora?"<br><br>
                            "Posso te adicionar no nosso grupo de WhatsApp onde mandamos novidades?"<br><br>
                            "Quer que eu te envie um resumo de tudo que conversamos por WhatsApp?"
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Agendou primeira visita?</label>
                            <select class="form-select" name="agendou_primeira_visita">
                                <option value="0" <?= $boas_vindas['agendou_primeira_visita'] == 0 ? 'selected' : '' ?>>Não</option>
                                <option value="1" <?= $boas_vindas['agendou_primeira_visita'] == 1 ? 'selected' : '' ?>>Sim</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Data do Agendamento:</label>
                            <input type="datetime-local" class="form-control" name="data_agendamento" 
                                   value="<?= $boas_vindas['data_agendamento'] ? date('Y-m-d\TH:i', strtotime($boas_vindas['data_agendamento'])) : '' ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Adicionou no grupo WhatsApp?</label>
                            <select class="form-select" name="adicionou_grupo_whatsapp">
                                <option value="0" <?= $boas_vindas['adicionou_grupo_whatsapp'] == 0 ? 'selected' : '' ?>>Não</option>
                                <option value="1" <?= $boas_vindas['adicionou_grupo_whatsapp'] == 1 ? 'selected' : '' ?>>Sim</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Enviou resumo por WhatsApp?</label>
                            <select class="form-select" name="enviou_resumo_whatsapp">
                                <option value="0" <?= $boas_vindas['enviou_resumo_whatsapp'] == 0 ? 'selected' : '' ?>>Não</option>
                                <option value="1" <?= $boas_vindas['enviou_resumo_whatsapp'] == 1 ? 'selected' : '' ?>>Sim</option>
                            </select>
                        </div>
                    </div>

                    <!-- ENCERRAMENTO -->
                    <div class="checklist-section">
                        <h5><i class="bi bi-check-circle"></i> 8. ENCERRAMENTO</h5>
                        
                        <div class="script-box">
                            <strong>Script:</strong><br>
                            "<?= htmlspecialchars($venda_dados['nome_titular']) ?>, foi um prazer te atender! 
                            Seja muito bem-vindo(a) à família Aquabeat! 🎉<br><br>
                            Qualquer coisa que precisar, estamos à disposição, tá bom?<br><br>
                            Aproveite muito seu título e até logo!"
                        </div>
                        
                        <?php
                        $itens_encerramento = [
                            'Agradecer pela atenção',
                            'Reforçar que estamos à disposição',
                            'Desejar boas-vindas novamente',
                            'Finalizar com cordialidade'
                        ];
                        foreach ($itens_encerramento as $i => $item): ?>
                            <div class="checklist-item">
                                <input type="checkbox" name="encerramento[]" value="<?= $i ?>"
                                       <?= in_array($i, $checklists['encerramento']) ? 'checked' : '' ?>>
                                <span><?= $item ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- OBSERVAÇÕES -->
                    <div class="obs-section">
                        <h5><i class="bi bi-pencil-square"></i> Observações Gerais</h5>
                        <textarea class="form-control" name="observacoes" rows="5" 
                                  placeholder="Anote aqui qualquer observação importante sobre o atendimento..."><?= htmlspecialchars($boas_vindas['observacoes'] ?? '') ?></textarea>
                    </div>

                    <!-- Botões de Ação -->
                    <div class="text-center mt-4 mb-5">
                        <button type="button" class="btn btn-secondary btn-lg" onclick="salvar(false)">
                            <i class="bi bi-save"></i> Salvar Progresso
                        </button>
                        <button type="button" class="btn btn-success btn-lg" onclick="salvar(true)">
                            <i class="bi bi-check-circle"></i> Concluir Boas-Vindas
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Botão Flutuante de Salvar -->
    <button type="button" class="btn btn-primary btn-lg btn-salvar" onclick="salvar(false)">
        <i class="bi bi-save"></i> Salvar
    </button>

    <!-- Modal de Tentativa de Contato -->
    <div class="modal fade" id="modalTentativa" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar Tentativa de Contato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formTentativa">
                        <input type="hidden" name="boas_vindas_id" value="<?= $boas_vindas_id ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Tentativa:</label>
                            <select class="form-select" name="tipo_tentativa" required>
                                <option value="ligacao">Ligação</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="email">E-mail</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Resultado:</label>
                            <select class="form-select" name="resultado" required>
                                <option value="atendeu">Atendeu</option>
                                <option value="nao_atendeu">Não Atendeu</option>
                                <option value="caixa_postal">Caixa Postal</option>
                                <option value="whatsapp_enviado">WhatsApp Enviado</option>
                                <option value="email_enviado">E-mail Enviado</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Observação:</label>
                            <textarea class="form-control" name="observacao" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarTentativa()">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    let modalTentativa;
    
    $(document).ready(function() {
        modalTentativa = new bootstrap.Modal(document.getElementById('modalTentativa'));
        carregarTentativas();
        
        // Marcar item como checked visualmente
        $('input[type="checkbox"]').on('change', function() {
            if ($(this).is(':checked')) {
                $(this).closest('.checklist-item').addClass('checked');
            } else {
                $(this).closest('.checklist-item').removeClass('checked');
            }
        });
        
        // Inicializar checked items
        $('input[type="checkbox"]:checked').each(function() {
            $(this).closest('.checklist-item').addClass('checked');
        });
    });
    
    function salvar(concluir = false) {
        const formData = new FormData(document.getElementById('formBoasVindas'));
        formData.append('concluir', concluir ? '1' : '0');
        
        if (concluir) {
            if (!confirm('Tem certeza que deseja CONCLUIR este boas-vindas? Após concluído não poderá mais ser editado.')) {
                return;
            }
        }
        
        $.ajax({
            url: 'api_salvar_boasvindas.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(concluir ? 'Boas-vindas concluído com sucesso!' : 'Progresso salvo com sucesso!');
                    if (concluir) {
                        window.location.href = 'visualizar.php?id=<?= $boas_vindas_id ?>';
                    }
                } else {
                    alert('Erro ao salvar: ' + response.error);
                }
            },
            error: function() {
                alert('Erro na requisição');
            }
        });
    }
    
    function registrarTentativa() {
        modalTentativa.show();
    }
    
    function salvarTentativa() {
        const formData = new FormData(document.getElementById('formTentativa'));
        
        $.ajax({
            url: 'api_salvar_tentativa.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    modalTentativa.hide();
                    document.getElementById('formTentativa').reset();
                    carregarTentativas();
                    alert('Tentativa registrada com sucesso!');
                } else {
                    alert('Erro ao registrar: ' + response.error);
                }
            },
            error: function() {
                alert('Erro na requisição');
            }
        });
    }
    
    function carregarTentativas() {
        $.ajax({
            url: 'api_tentativas.php?boas_vindas_id=<?= $boas_vindas_id ?>',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    renderizarTentativas(response.data);
                }
            }
        });
    }
    
    function renderizarTentativas(tentativas) {
        const lista = $('#listaTentativas');
        lista.empty();
        
        if (tentativas.length === 0) {
            lista.html('<p class="text-muted">Nenhuma tentativa registrada</p>');
            return;
        }
        
        tentativas.forEach(function(t) {
            const icones = {
                'ligacao': 'bi-telephone',
                'whatsapp': 'bi-whatsapp',
                'email': 'bi-envelope'
            };
            
            const badges = {
                'atendeu': 'success',
                'nao_atendeu': 'warning',
                'caixa_postal': 'secondary',
                'whatsapp_enviado': 'info',
                'email_enviado': 'info'
            };
            
            const html = `
                <div class="mb-2 p-2 border-start border-3 border-${badges[t.resultado]} bg-light">
                    <div class="d-flex justify-content-between">
                        <span><i class="bi ${icones[t.tipo_tentativa]}"></i> ${t.tipo_tentativa}</span>
                        <small class="text-muted">${formatarData(t.criado_em)}</small>
                    </div>
                    <div class="mt-1">
                        <span class="badge bg-${badges[t.resultado]}">${t.resultado}</span>
                        ${t.observacao ? '<br><small>' + t.observacao + '</small>' : ''}
                    </div>
                </div>
            `;
            lista.append(html);
        });
    }
    
    function formatarData(data) {
        const d = new Date(data);
        return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
    }
    </script>
</body>
</html>
