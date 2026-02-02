-- ==========================================
-- ATUALIZAÇÕES DO BANCO DE DADOS
-- Execute estas queries no phpMyAdmin
-- ==========================================

-- Adicionar coluna foto na tabela usuarios
ALTER TABLE usuarios ADD COLUMN foto VARCHAR(255) NULL AFTER email;

-- Alterar tabela logs_atividades para permitir NULL no boas_vindas_id
-- (para logs de ações que não são relacionadas a boas-vindas específicos)
ALTER TABLE logs_atividades MODIFY COLUMN boas_vindas_id INT NULL;

-- Remover a foreign key se existir
ALTER TABLE logs_atividades DROP FOREIGN KEY IF EXISTS logs_atividades_ibfk_1;

-- Adicionar novos tipos de ação no enum (se necessário)
ALTER TABLE logs_atividades MODIFY COLUMN tipo_acao VARCHAR(50) NOT NULL;

-- Criar pasta de uploads (execute no servidor via SSH/terminal)
-- mkdir -p /home/mcaquabeat.hotlead.es/public_html/boasvindas/uploads/fotos
-- chmod 755 /home/mcaquabeat.hotlead.es/public_html/boasvindas/uploads/fotos
