-- ==========================================
-- SISTEMA DE BOAS-VINDAS AQUABEAT
-- Atualização do Banco de Dados v2
-- Novas funcionalidades: Checklist interativo, filtros de data, notificações
-- ==========================================

-- Adicionar foto na tabela usuarios (se não existir)
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS foto VARCHAR(255) NULL AFTER email;

-- Modificar logs_atividades para permitir boas_vindas_id NULL
ALTER TABLE logs_atividades MODIFY COLUMN boas_vindas_id INT NULL;

-- Modificar tipo_acao para VARCHAR para ser mais flexível
ALTER TABLE logs_atividades MODIFY COLUMN tipo_acao VARCHAR(50) NOT NULL;

-- ==========================================
-- TABELA DE CHECKLIST ITEMS (estrutura do checklist)
-- ==========================================
CREATE TABLE IF NOT EXISTS checklist_etapas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    ordem INT DEFAULT 0,
    obrigatorio TINYINT(1) DEFAULT 0,
    tipo_campo ENUM('checkbox', 'texto', 'numero', 'select', 'textarea', 'telefone', 'rating') DEFAULT 'checkbox',
    opcoes_campo TEXT, -- JSON para opções de select ou configurações extras
    script_template TEXT, -- Template de script para copiar
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- TABELA DE INTERAÇÕES DO TÍTULO (checklist preenchido)
-- ==========================================
CREATE TABLE IF NOT EXISTS titulo_interacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    boas_vindas_id INT NOT NULL,
    checklist_etapa_id INT NULL,
    etapa_codigo VARCHAR(50) NOT NULL,
    valor_checkbox TINYINT(1) DEFAULT 0,
    valor_texto TEXT,
    valor_numero DECIMAL(10,2),
    usuario_id INT NOT NULL,
    telefone_contato VARCHAR(50), -- Telefone usado na tentativa
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (boas_vindas_id) REFERENCES boas_vindas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_boas_vindas_id (boas_vindas_id),
    INDEX idx_etapa_codigo (etapa_codigo),
    INDEX idx_criado_em (criado_em),
    UNIQUE KEY unique_interacao (boas_vindas_id, etapa_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- TABELA DE LOGS DETALHADOS DE CADA INTERAÇÃO
-- ==========================================
CREATE TABLE IF NOT EXISTS titulo_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    boas_vindas_id INT NOT NULL,
    usuario_id INT NOT NULL,
    tipo_log VARCHAR(50) NOT NULL, -- 'checkbox_marcado', 'texto_salvo', 'ligacao', 'whatsapp', etc
    etapa_codigo VARCHAR(50),
    valor_anterior TEXT,
    valor_novo TEXT,
    observacao TEXT,
    ip_usuario VARCHAR(50),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (boas_vindas_id) REFERENCES boas_vindas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_boas_vindas_id (boas_vindas_id),
    INDEX idx_tipo_log (tipo_log),
    INDEX idx_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- INSERIR CONFIGURAÇÕES DE FILTRO DE DATA
-- ==========================================
INSERT INTO configuracoes (chave, valor, descricao, tipo) VALUES
('filtro_data_padrao', 'mes_atual', 'Filtro de período padrão (mes_atual, mes_anterior, ultimos_30_dias, intervalo_personalizado)', 'texto'),
('filtro_data_inicio', NULL, 'Data início para intervalo personalizado', 'texto'),
('filtro_data_fim', NULL, 'Data fim para intervalo personalizado', 'texto'),
('mostrar_selector_periodo', '1', 'Mostrar selector de período no dashboard', 'boolean')
ON DUPLICATE KEY UPDATE chave = VALUES(chave);

-- ==========================================
-- INSERIR ETAPAS DO CHECKLIST DE BOAS-VINDAS
-- ==========================================

-- PREPARAÇÃO
INSERT INTO checklist_etapas (codigo, titulo, descricao, ordem, obrigatorio, tipo_campo, opcoes_campo, script_template) VALUES
('prep_verificar_dados', 'Verificar dados do cliente no sistema', 'ID Cota, CPF, nome, telefone', 10, 1, 'checkbox', NULL, NULL),
('prep_verificar_consultor', 'Conferir dados do consultor que vendeu', NULL, 20, 0, 'checkbox', NULL, NULL),
('prep_verificar_pagamento', 'Verificar se pagamento da entrada foi confirmado', NULL, 30, 0, 'checkbox', NULL, NULL),
('prep_acesso_portal', 'Ter acesso ao portal para suporte', NULL, 40, 0, 'checkbox', NULL, NULL)
ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), descricao = VALUES(descricao), ordem = VALUES(ordem);

-- ABERTURA
INSERT INTO checklist_etapas (codigo, titulo, descricao, ordem, obrigatorio, tipo_campo, opcoes_campo, script_template) VALUES
('abert_cumprimentar', 'Cumprimentar e se identificar', 'Com energia e cordialidade', 110, 1, 'checkbox', NULL, 'Bom dia/Boa tarde!\n\nMeu nome é {ATENDENTE}, estou ligando do setor de Boas-Vindas do Aquabeat. Posso falar alguns minutos com você?'),
('abert_explicar_motivo', 'Explicar o motivo da ligação', NULL, 120, 1, 'checkbox', NULL, 'Que ótimo! Estou ligando para te dar as boas-vindas como novo titular e ajudar com qualquer dúvida sobre seu título. Tudo bem para você agora?')
ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), descricao = VALUES(descricao), ordem = VALUES(ordem), opcoes_campo = VALUES(opcoes_campo), script_template = VALUES(script_template);

-- VALIDAÇÃO DO TITULAR
INSERT INTO checklist_etapas (codigo, titulo, descricao, ordem, obrigatorio, tipo_campo, opcoes_campo, script_template) VALUES
('valid_solicitar_confirmacao', 'Solicitar confirmação de dados', 'Para sua segurança', 210, 1, 'checkbox', NULL, 'Perfeito! Para sua segurança, preciso confirmar alguns dados rapidinho.'),
('valid_confirmar_id_cota', 'Informar e confirmar ID da Cota', NULL, 220, 1, 'checkbox', NULL, 'Seu ID de Cota é {NUMERO_TITULO}, está correto?'),
('valid_confirmar_cpf', 'Solicitar os 4 últimos dígitos do CPF', NULL, 230, 1, 'checkbox', NULL, 'E pode me confirmar os 4 últimos dígitos do seu CPF?'),
('valid_confirmacao_recebida', 'Confirmação positiva recebida', NULL, 240, 1, 'select', '{"opcoes": ["Sim, confirmado", "Dados incorretos"]}', 'Perfeito, confirmado!')
ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), descricao = VALUES(descricao), ordem = VALUES(ordem), opcoes_campo = VALUES(opcoes_campo), script_template = VALUES(script_template);

-- PORTAL
INSERT INTO checklist_etapas (codigo, titulo, descricao, ordem, obrigatorio, tipo_campo, opcoes_campo, script_template) VALUES
('portal_informar', 'Perguntou se já acessou o portal', 'E informou sobre gerenciamento pelo portal', 310, 1, 'checkbox', NULL, '{NOME}, você já acessou o portal do titular?'),
('portal_acessou', 'Cliente já acessou o portal?', NULL, 320, 0, 'select', '{"opcoes": ["Sim", "Não", "Não informou"]}', NULL),
('portal_dificuldade', 'Teve dificuldade no acesso?', 'Descreva se houver', 330, 0, 'textarea', NULL, NULL),
('portal_ofereceu_ajuda', 'Ofereceu ajuda para primeiro acesso', NULL, 340, 0, 'checkbox', NULL, 'Sem problemas! O portal é bem simples. Lá você consegue gerenciar tudo do seu título, cadastrar dependentes, fazer agendamentos... Quer que eu te ajude a fazer o primeiro acesso agora?'),
('portal_resetou_senha', 'Ofereceu/fez reset de senha', NULL, 350, 0, 'checkbox', NULL, 'Tranquilo! Posso resetar sua senha agora mesmo e você recebe por e-mail/SMS. Pode ser?'),
('portal_instruiu_dependentes', 'Instruiu sobre cadastro de dependentes', NULL, 360, 0, 'checkbox', NULL, NULL),
('portal_informou_link', 'Informou o link do portal', NULL, 370, 0, 'checkbox', NULL, 'O endereço do portal é {PORTAL_URL}')
ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), descricao = VALUES(descricao), ordem = VALUES(ordem), opcoes_campo = VALUES(opcoes_campo), script_template = VALUES(script_template);

-- VALIDAÇÃO DO ATENDIMENTO
INSERT INTO checklist_etapas (codigo, titulo, descricao, ordem, obrigatorio, tipo_campo, opcoes_campo, script_template) VALUES
('atend_confirmou_consultor', 'Confirmou nome do consultor que vendeu', 'E perguntou sobre qualidade do atendimento', 410, 1, 'checkbox', NULL, '{NOME}, vi aqui que quem te atendeu foi o(a) {PROMOTOR}. Ele(a) deu um bom atendimento para você?'),
('atend_nota_consultor', 'Nota do atendimento do consultor (1-5)', NULL, 420, 0, 'rating', '{"min": 1, "max": 5}', NULL),
('atend_feedback_positivo', 'Feedback foi positivo?', NULL, 430, 0, 'select', '{"opcoes": ["Positivo", "Negativo", "Neutro"]}', NULL),
('atend_feedback_texto', 'Feedback do cliente sobre o consultor', 'Preencha se houver observações', 440, 0, 'textarea', NULL, NULL)
ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), descricao = VALUES(descricao), ordem = VALUES(ordem), opcoes_campo = VALUES(opcoes_campo), script_template = VALUES(script_template);

-- VALIDAÇÃO FINANCEIRA
INSERT INTO checklist_etapas (codigo, titulo, descricao, ordem, obrigatorio, tipo_campo, opcoes_campo, script_template) VALUES
('financ_perguntou_entrada', 'Perguntou sobre pagamento da entrada', NULL, 510, 1, 'checkbox', NULL, 'E me conta, como foi para você fazer o pagamento da entrada? Teve alguma dificuldade?'),
('financ_problema_entrada', 'Problema no pagamento (descrever)', 'Preencha se houver problema', 520, 0, 'textarea', NULL, NULL),
('financ_perguntou_anuidade', 'Perguntou se sabia sobre anuidade', NULL, 530, 1, 'checkbox', NULL, 'E o consultor te informou sobre a anuidade do título?'),
('financ_sabia_anuidade', 'Cliente sabia sobre anuidade?', NULL, 540, 0, 'select', '{"opcoes": ["Sim", "Não"]}', NULL),
('financ_explicou_anuidade', 'Explicou detalhes da anuidade', 'Valor e vencimento', 550, 0, 'checkbox', NULL, 'Deixa eu te explicar então: seu título tem uma anuidade de R$ {VALOR} que vence todo dia {DIA_VENCIMENTO}. Você vai receber boleto/cobrança automaticamente. Tudo bem?')
ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), descricao = VALUES(descricao), ordem = VALUES(ordem), opcoes_campo = VALUES(opcoes_campo), script_template = VALUES(script_template);

-- INFORMAÇÕES IMPORTANTES
INSERT INTO checklist_etapas (codigo, titulo, descricao, ordem, obrigatorio, tipo_campo, opcoes_campo, script_template) VALUES
('info_confirmou_email', 'Confirmou e-mail cadastrado', NULL, 610, 1, 'select', '{"opcoes": ["Sim, correto", "Não, corrigido"]}', 'O e-mail cadastrado é {EMAIL}, está correto?'),
('info_confirmou_telefone', 'Confirmou telefone/WhatsApp', NULL, 620, 1, 'select', '{"opcoes": ["Sim, correto", "Não, corrigido"]}', 'E o telefone {TELEFONE}, está correto?'),
('info_confirmou_endereco', 'Confirmou endereço', NULL, 630, 0, 'checkbox', NULL, NULL),
('info_informou_canais', 'Informou canais de atendimento', 'WhatsApp, Telefone, E-mail, Portal', 640, 1, 'checkbox', NULL, 'Você pode falar com a gente pelo WhatsApp {WHATSAPP} ou pelo portal {PORTAL_URL}.'),
('info_mencionou_beneficios', 'Mencionou benefícios exclusivos', NULL, 650, 0, 'checkbox', NULL, NULL),
('info_programa_indicacao', 'Informou sobre programa de indicação', NULL, 660, 0, 'checkbox', NULL, NULL)
ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), descricao = VALUES(descricao), ordem = VALUES(ordem), opcoes_campo = VALUES(opcoes_campo), script_template = VALUES(script_template);

-- DÚVIDAS GERAIS
INSERT INTO checklist_etapas (codigo, titulo, descricao, ordem, obrigatorio, tipo_campo, opcoes_campo, script_template) VALUES
('duvidas_perguntou', 'Perguntou se tem dúvidas', NULL, 710, 1, 'checkbox', NULL, 'Tem alguma dúvida que eu possa esclarecer para você?'),
('duvidas_teve', 'Cliente teve dúvidas?', NULL, 720, 0, 'select', '{"opcoes": ["Sim", "Não"]}', NULL),
('duvidas_anotadas', 'Dúvidas/observações', 'Anote as dúvidas do cliente', 730, 0, 'textarea', NULL, NULL)
ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), descricao = VALUES(descricao), ordem = VALUES(ordem), opcoes_campo = VALUES(opcoes_campo), script_template = VALUES(script_template);

-- OFERTAS
INSERT INTO checklist_etapas (codigo, titulo, descricao, ordem, obrigatorio, tipo_campo, script_template) VALUES
('oferta_primeira_visita', 'Ofereceu agendar primeira visita', NULL, 810, 0, 'checkbox', 'Já que estamos aqui, quer aproveitar e agendar sua primeira visita agora?'),
('oferta_agendou_visita', 'Agendou primeira visita?', NULL, 820, 0, 'select', '{"opcoes": ["Sim", "Não, deixou para depois"]}'),
('oferta_data_agendamento', 'Data/horário do agendamento', NULL, 830, 0, 'texto', NULL),
('oferta_grupo_whatsapp', 'Ofereceu grupo de WhatsApp', NULL, 840, 0, 'checkbox', 'Posso te adicionar no nosso grupo de WhatsApp onde a gente manda novidades e promoções?'),
('oferta_aceitou_grupo', 'Aceitou entrar no grupo?', NULL, 850, 0, 'select', '{"opcoes": ["Sim", "Não"]}'),
('oferta_resumo_whatsapp', 'Ofereceu enviar resumo por WhatsApp', NULL, 860, 0, 'checkbox', 'Quer que eu te envie um resumo de tudo que conversamos por WhatsApp?'),
('oferta_aceitou_resumo', 'Aceitou receber resumo?', NULL, 870, 0, 'select', '{"opcoes": ["Sim", "Não"]}')
ON DUPLICATE KEY UPDATE codigo = VALUES(codigo);

-- ENCERRAMENTO
INSERT INTO checklist_etapas (codigo, titulo, descricao, ordem, obrigatorio, tipo_campo, script_template) VALUES
('encerr_agradeceu', 'Agradeceu pela atenção', NULL, 910, 1, 'checkbox', NULL),
('encerr_reforcou_disposicao', 'Reforçou estar à disposição', NULL, 920, 1, 'checkbox', NULL),
('encerr_desejou_boasvindas', 'Desejou boas-vindas novamente', NULL, 930, 1, 'checkbox', '{NOME}, foi um prazer te atender! Seja muito bem-vindo(a) à família Aquabeat!\n\nQualquer coisa que precisar, estamos à disposição, tá bom?\n\nAproveite muito seu título e até logo!'),
('encerr_finalizou_cordial', 'Finalizou com cordialidade', NULL, 940, 1, 'checkbox', NULL)
ON DUPLICATE KEY UPDATE codigo = VALUES(codigo);

-- REGISTRO PÓS-LIGAÇÃO
INSERT INTO checklist_etapas (codigo, titulo, descricao, ordem, obrigatorio, tipo_campo, script_template) VALUES
('reg_data_hora', 'Data/hora da ligação registrada', NULL, 1010, 1, 'checkbox', NULL),
('reg_status_ligacao', 'Status da ligação', NULL, 1020, 1, 'select', '{"opcoes": ["Atendido", "Não atendeu", "Retornar"]}'),
('reg_duracao', 'Tempo de duração (minutos)', NULL, 1030, 0, 'numero', NULL),
('reg_observacoes', 'Principais observações', NULL, 1040, 0, 'textarea', NULL),
('reg_satisfacao', 'Nível de satisfação do cliente', NULL, 1050, 0, 'select', '{"opcoes": ["Muito satisfeito", "Satisfeito", "Neutro", "Insatisfeito"]}'),
('reg_problemas', 'Problemas identificados', NULL, 1060, 0, 'textarea', NULL),
('reg_followup', 'Ações necessárias (follow-up)', NULL, 1070, 0, 'textarea', NULL),
('reg_crm_atualizado', 'CRM atualizado com informações', NULL, 1080, 0, 'checkbox', NULL)
ON DUPLICATE KEY UPDATE codigo = VALUES(codigo);

-- ==========================================
-- ADICIONAR CAMPOS NA TABELA BOAS_VINDAS
-- ==========================================
ALTER TABLE boas_vindas
ADD COLUMN IF NOT EXISTS telefone_atendimento VARCHAR(50) NULL COMMENT 'Telefone que atendente usou para contato',
ADD COLUMN IF NOT EXISTS data_ultimo_contato DATETIME NULL,
ADD COLUMN IF NOT EXISTS resultado_ultimo_contato ENUM('atendido', 'nao_atendeu', 'caixa_postal', 'retornar') NULL,
ADD COLUMN IF NOT EXISTS proxima_tentativa DATETIME NULL COMMENT 'Agendar próxima tentativa',
ADD COLUMN IF NOT EXISTS nivel_satisfacao ENUM('muito_satisfeito', 'satisfeito', 'neutro', 'insatisfeito') NULL,
ADD COLUMN IF NOT EXISTS checklist_progresso INT DEFAULT 0 COMMENT 'Percentual do checklist completo';

-- ==========================================
-- VIEW DE TÍTULOS PENDENTES DE ATENÇÃO
-- ==========================================
CREATE OR REPLACE VIEW view_titulos_atencao AS
SELECT
    bv.*,
    u.nome as nome_atendente,
    DATEDIFF(NOW(), bv.data_venda) as dias_desde_venda,
    DATEDIFF(NOW(), COALESCE(bv.data_ultimo_contato, bv.data_venda)) as dias_sem_contato,
    CASE
        WHEN bv.status = 'pendente' AND DATEDIFF(NOW(), bv.data_venda) > 1 THEN 'urgente'
        WHEN bv.resultado_ultimo_contato = 'nao_atendeu' THEN 'retornar'
        WHEN bv.proxima_tentativa IS NOT NULL AND bv.proxima_tentativa <= NOW() THEN 'agendado'
        ELSE 'normal'
    END as prioridade
FROM boas_vindas bv
LEFT JOIN usuarios u ON bv.usuario_id = u.id
WHERE bv.status IN ('pendente', 'em_andamento')
ORDER BY
    CASE
        WHEN bv.status = 'pendente' AND DATEDIFF(NOW(), bv.data_venda) > 1 THEN 1
        WHEN bv.resultado_ultimo_contato = 'nao_atendeu' THEN 2
        WHEN bv.proxima_tentativa IS NOT NULL AND bv.proxima_tentativa <= NOW() THEN 3
        ELSE 4
    END,
    bv.data_venda ASC;
