-- ==========================================
-- SISTEMA DE BOAS-VINDAS AQUABEAT
-- Banco de Dados Completo
-- ==========================================

-- Criar banco de dados
CREATE DATABASE IF NOT EXISTS aquabeat_boasvindas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aquabeat_boasvindas;

-- ==========================================
-- TABELA DE USUÁRIOS DO SISTEMA
-- ==========================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('admin', 'atendente') NOT NULL DEFAULT 'atendente',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_tipo (tipo),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- TABELA DE BOAS-VINDAS
-- ==========================================
CREATE TABLE IF NOT EXISTS boas_vindas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_titulo VARCHAR(100) NOT NULL,
    usuario_id INT NOT NULL,
    status ENUM('pendente', 'em_andamento', 'concluido') NOT NULL DEFAULT 'pendente',
    
    -- Dados do cliente (cache da API)
    nome_cliente VARCHAR(255),
    documento_cliente VARCHAR(50),
    telefone VARCHAR(50),
    email VARCHAR(255),
    data_venda DATETIME,
    tipo_titulo VARCHAR(255),
    valor_total DECIMAL(10,2),
    forma_pagamento VARCHAR(150),
    promotor VARCHAR(255),
    
    -- Controle de tentativas
    tentativas_contato INT DEFAULT 0,
    ultima_tentativa DATETIME,
    
    -- Checklist de boas-vindas (JSON)
    checklist_preparacao TEXT,
    checklist_abertura TEXT,
    checklist_validacao TEXT,
    checklist_portal TEXT,
    checklist_atendimento TEXT,
    checklist_financeiro TEXT,
    checklist_informacoes TEXT,
    checklist_duvidas TEXT,
    checklist_ofertas TEXT,
    checklist_encerramento TEXT,
    checklist_registro TEXT,
    
    -- Informações coletadas
    dados_validados TINYINT(1) DEFAULT 0,
    acessou_portal TINYINT(1) DEFAULT 0,
    resetou_senha TINYINT(1) DEFAULT 0,
    cadastrou_dependentes TINYINT(1) DEFAULT 0,
    feedback_consultor TEXT,
    nota_atendimento_consultor INT,
    problema_pagamento_entrada TEXT,
    sabia_anuidade TINYINT(1) DEFAULT 0,
    agendou_primeira_visita TINYINT(1) DEFAULT 0,
    data_agendamento DATETIME,
    adicionou_grupo_whatsapp TINYINT(1) DEFAULT 0,
    enviou_resumo_whatsapp TINYINT(1) DEFAULT 0,
    
    -- Observações
    observacoes TEXT,
    
    -- Timestamps
    iniciado_em DATETIME,
    concluido_em DATETIME,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_numero_titulo (numero_titulo),
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_status (status),
    INDEX idx_data_venda (data_venda),
    INDEX idx_concluido_em (concluido_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- TABELA DE LOGS DE ATIVIDADES
-- ==========================================
CREATE TABLE IF NOT EXISTS logs_atividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    boas_vindas_id INT NOT NULL,
    usuario_id INT NOT NULL,
    tipo_acao ENUM(
        'criacao',
        'inicio',
        'tentativa_contato',
        'update_checklist',
        'adicao_observacao',
        'conclusao',
        'alteracao'
    ) NOT NULL,
    descricao TEXT,
    dados_alterados TEXT, -- JSON com antes/depois
    ip_usuario VARCHAR(50),
    user_agent TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (boas_vindas_id) REFERENCES boas_vindas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_boas_vindas_id (boas_vindas_id),
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_tipo_acao (tipo_acao),
    INDEX idx_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- TABELA DE TENTATIVAS DE CONTATO
-- ==========================================
CREATE TABLE IF NOT EXISTS tentativas_contato (
    id INT AUTO_INCREMENT PRIMARY KEY,
    boas_vindas_id INT NOT NULL,
    usuario_id INT NOT NULL,
    tipo_tentativa ENUM('ligacao', 'whatsapp', 'email') NOT NULL,
    resultado ENUM('atendeu', 'nao_atendeu', 'caixa_postal', 'whatsapp_enviado', 'email_enviado') NOT NULL,
    observacao TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (boas_vindas_id) REFERENCES boas_vindas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_boas_vindas_id (boas_vindas_id),
    INDEX idx_tipo_tentativa (tipo_tentativa),
    INDEX idx_resultado (resultado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- TABELA DE TEMPLATES DE MENSAGENS
-- ==========================================
CREATE TABLE IF NOT EXISTS templates_mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    tipo ENUM('whatsapp', 'email', 'sms') NOT NULL,
    assunto VARCHAR(255),
    conteudo TEXT NOT NULL,
    variaveis TEXT, -- JSON com variáveis disponíveis
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_tipo (tipo),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- TABELA DE CONFIGURAÇÕES DO SISTEMA
-- ==========================================
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    descricao TEXT,
    tipo ENUM('texto', 'numero', 'boolean', 'json') DEFAULT 'texto',
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- INSERIR CONFIGURAÇÕES PADRÃO
-- ==========================================
INSERT INTO configuracoes (chave, valor, descricao, tipo) VALUES
('api_endpoint', 'http://localhost/api', 'Endpoint da API externa', 'texto'),
('dias_para_boasvindas', '1', 'Dias após venda para fazer boas-vindas (D+X)', 'numero'),
('tentativas_maximas', '3', 'Número máximo de tentativas de contato', 'numero'),
('intervalo_tentativas_horas', '2', 'Intervalo entre tentativas em horas', 'numero'),
('portal_url', 'https://portal.aquabeat.com.br', 'URL do portal do cliente', 'texto'),
('whatsapp_numero', '31999999999', 'Número do WhatsApp de atendimento', 'texto'),
('telefone_atendimento', '3133333333', 'Telefone de atendimento', 'texto'),
('email_atendimento', 'atendimento@aquabeat.com.br', 'E-mail de atendimento', 'texto');

-- ==========================================
-- INSERIR TEMPLATES PADRÃO
-- ==========================================
INSERT INTO templates_mensagens (nome, tipo, conteudo, variaveis) VALUES
(
    'WhatsApp - Não atendeu',
    'whatsapp',
    'Olá {NOME}! 👋\n\nAqui é {ATENDENTE} do setor de Boas-Vindas do Aquabeat!\n\nTentei ligar mas não consegui contato. Quando tiver um tempinho, me chama aqui que quero te dar as boas-vindas e tirar qualquer dúvida sobre seu título! 😊\n\nEstou por aqui!',
    '["NOME", "ATENDENTE"]'
),
(
    'WhatsApp - Resumo pós-ligação',
    'whatsapp',
    'Oi {NOME}!\n\nObrigado pelo papo de hoje! 😊\n\nResumindo o que conversamos:\n\n✅ Seu ID de Cota: {NUMERO_TITULO}\n✅ Portal: {PORTAL_URL}\n✅ WhatsApp: {WHATSAPP}\n✅ Telefone: {TELEFONE}\n✅ Anuidade: R$ {VALOR_ANUIDADE} - Vence dia {DIA_VENCIMENTO}\n✅ Prazo primeira visita: {PRAZO_VISITA}\n\nQualquer dúvida, estou por aqui!\n\nBem-vindo(a) à família Aquabeat! 🎉',
    '["NOME", "NUMERO_TITULO", "PORTAL_URL", "WHATSAPP", "TELEFONE", "VALOR_ANUIDADE", "DIA_VENCIMENTO", "PRAZO_VISITA"]'
),
(
    'WhatsApp - Alteração de vagas',
    'whatsapp',
    'Oi {NOME}! 👋\n\nVi aqui que você solicitou alteração de vagas. Está correto?\n\nLembrando que:\n✅ Primeira alteração: GRATUITA\n⚠️ Próximas alterações: R$ {VALOR_ALTERACAO}\n\nConfirma para eu processar?',
    '["NOME", "VALOR_ALTERACAO"]'
);

-- ==========================================
-- CRIAR USUÁRIO ADMIN PADRÃO
-- Senha: admin123 (hash MD5 para exemplo - TROCAR em produção!)
-- ==========================================
INSERT INTO usuarios (nome, email, senha, tipo) VALUES
('Administrador', 'admin@aquabeat.com.br', MD5('admin123'), 'admin');

-- ==========================================
-- VIEWS ÚTEIS
-- ==========================================

-- View de boas-vindas pendentes
CREATE OR REPLACE VIEW view_boasvindas_pendentes AS
SELECT 
    bv.*,
    u.nome as nome_atendente,
    u.email as email_atendente,
    DATEDIFF(NOW(), bv.data_venda) as dias_desde_venda
FROM boas_vindas bv
LEFT JOIN usuarios u ON bv.usuario_id = u.id
WHERE bv.status IN ('pendente', 'em_andamento')
ORDER BY bv.data_venda ASC;

-- View de estatísticas por atendente
CREATE OR REPLACE VIEW view_estatisticas_atendente AS
SELECT 
    u.id,
    u.nome,
    COUNT(bv.id) as total_atendimentos,
    SUM(CASE WHEN bv.status = 'concluido' THEN 1 ELSE 0 END) as concluidos,
    SUM(CASE WHEN bv.status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
    SUM(CASE WHEN bv.status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
    AVG(bv.tentativas_contato) as media_tentativas,
    AVG(bv.nota_atendimento_consultor) as media_nota_consultor
FROM usuarios u
LEFT JOIN boas_vindas bv ON u.id = bv.usuario_id
WHERE u.tipo = 'atendente' AND u.ativo = 1
GROUP BY u.id, u.nome;

-- ==========================================
-- STORED PROCEDURES
-- ==========================================

DELIMITER //

-- Procedure para criar boas-vindas a partir da API
CREATE PROCEDURE sp_criar_boasvindas_vendas_mes()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_numero_titulo VARCHAR(100);
    DECLARE v_nome_cliente VARCHAR(255);
    DECLARE v_documento VARCHAR(50);
    DECLARE v_telefone VARCHAR(50);
    DECLARE v_data_venda DATETIME;
    DECLARE v_tipo_titulo VARCHAR(255);
    DECLARE v_valor_total DECIMAL(10,2);
    DECLARE v_forma_pagamento VARCHAR(150);
    DECLARE v_promotor VARCHAR(255);
    
    -- Esta procedure seria chamada periodicamente
    -- Aqui você faria a integração com a API externa
    
END//

DELIMITER ;

-- ==========================================
-- TRIGGERS
-- ==========================================

DELIMITER //

-- Trigger para criar log ao criar boas-vindas
CREATE TRIGGER trg_boasvindas_after_insert
AFTER INSERT ON boas_vindas
FOR EACH ROW
BEGIN
    INSERT INTO logs_atividades (boas_vindas_id, usuario_id, tipo_acao, descricao)
    VALUES (NEW.id, NEW.usuario_id, 'criacao', CONCAT('Boas-vindas criado para título ', NEW.numero_titulo));
END//

-- Trigger para criar log ao atualizar status
CREATE TRIGGER trg_boasvindas_after_update
AFTER UPDATE ON boas_vindas
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO logs_atividades (boas_vindas_id, usuario_id, tipo_acao, descricao, dados_alterados)
        VALUES (
            NEW.id, 
            NEW.usuario_id, 
            'alteracao',
            CONCAT('Status alterado de ', OLD.status, ' para ', NEW.status),
            JSON_OBJECT('antes', OLD.status, 'depois', NEW.status)
        );
    END IF;
    
    IF NEW.status = 'concluido' AND OLD.status != 'concluido' THEN
        INSERT INTO logs_atividades (boas_vindas_id, usuario_id, tipo_acao, descricao)
        VALUES (NEW.id, NEW.usuario_id, 'conclusao', 'Boas-vindas concluído');
    END IF;
END//

DELIMITER ;
