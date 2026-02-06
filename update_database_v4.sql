-- Atualização v4: Corrigir tipos de campos no Registro Pós-Ligação
-- Execute: mysql -u mcaq_uaboasvindas -p mcaq_uaboasvindas < update_database_v4.sql

-- Primeiro, alterar o ENUM para incluir 'datetime'
ALTER TABLE checklist_etapas
MODIFY COLUMN tipo_campo ENUM('checkbox', 'texto', 'numero', 'select', 'textarea', 'telefone', 'rating', 'datetime') DEFAULT 'checkbox';

-- Atualizar tipo de campo para Data/hora da ligação (datetime)
UPDATE checklist_etapas
SET tipo_campo = 'datetime',
    opcoes_campo = NULL
WHERE codigo = 'reg_data_hora' OR titulo LIKE '%Data/hora%ligação%';

-- Atualizar tipo de campo para Status da ligação (select com opções corretas)
UPDATE checklist_etapas
SET tipo_campo = 'select',
    opcoes_campo = '{"opcoes":["Atendeu","Só chamou","Não atendeu","Desligou","Caixa postal"]}'
WHERE codigo = 'reg_status' OR titulo LIKE '%Status%ligação%';

-- Atualizar tipo de campo para Nível de satisfação do cliente (rating)
UPDATE checklist_etapas
SET tipo_campo = 'rating',
    opcoes_campo = NULL
WHERE codigo = 'reg_satisfacao' OR titulo LIKE '%Nível de satisfação%';

-- Verificar se os campos foram atualizados
SELECT codigo, titulo, tipo_campo, opcoes_campo
FROM checklist_etapas
WHERE codigo LIKE 'reg_%'
ORDER BY ordem;
