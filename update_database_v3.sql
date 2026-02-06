-- Atualização v3: Adicionar campos extras na tabela boas_vindas
-- Execute este script para adicionar as novas colunas

-- Adicionar colunas para observações extras
ALTER TABLE boas_vindas
    ADD COLUMN IF NOT EXISTS pagamento_obs VARCHAR(255) DEFAULT NULL COMMENT 'Observação extra sobre pagamento',
    ADD COLUMN IF NOT EXISTS promotor_obs VARCHAR(255) DEFAULT NULL COMMENT 'Observação extra sobre o consultor/promotor';

-- Índice para pesquisa por documento
ALTER TABLE boas_vindas
    ADD INDEX IF NOT EXISTS idx_documento_cliente (documento_cliente);

-- Atualizar coluna de status se ainda não tiver os valores corretos
-- UPDATE boas_vindas SET status = 'pendente' WHERE status IS NULL;
