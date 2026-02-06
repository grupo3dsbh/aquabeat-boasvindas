-- Atualização v5: Adicionar pergunta "Ciência da Cota" no checklist
-- Execute: mysql -u mcaq_uaboasvindas -p mcaq_uaboasvindas < update_database_v5.sql

-- Adicionar pergunta sobre ciência da cota na seção de Validação
-- Ordem 235 (entre confirmação de CPF e confirmação recebida)
INSERT INTO checklist_etapas (codigo, titulo, descricao, ordem, obrigatorio, tipo_campo, opcoes_campo, script_template)
VALUES (
    'valid_ciencia_cota',
    'Cliente tem ciência da cota?',
    'Verificar se o cliente entende que adquiriu uma cota/título de associação',
    235,
    1,
    'select',
    '{"opcoes": ["Sim, tem ciência", "Não tinha ciência", "Ficou com dúvida"]}',
    'Você está ciente de que adquiriu uma cota de associação do Aquabeat, correto?'
)
ON DUPLICATE KEY UPDATE
    titulo = VALUES(titulo),
    descricao = VALUES(descricao),
    ordem = VALUES(ordem),
    obrigatorio = VALUES(obrigatorio),
    tipo_campo = VALUES(tipo_campo),
    opcoes_campo = VALUES(opcoes_campo),
    script_template = VALUES(script_template);

-- Alterar "grupo de WhatsApp" para "link da comunidade"
UPDATE checklist_etapas
SET titulo = 'Ofereceu link da comunidade',
    script_template = 'Posso te enviar o link da nossa comunidade no WhatsApp onde a gente manda novidades e promoções?'
WHERE codigo = 'oferta_grupo_whatsapp';

UPDATE checklist_etapas
SET titulo = 'Aceitou entrar na comunidade?'
WHERE codigo = 'oferta_aceitou_grupo';

-- Verificar atualizações
SELECT codigo, titulo
FROM checklist_etapas
WHERE codigo IN ('valid_ciencia_cota', 'oferta_grupo_whatsapp', 'oferta_aceitou_grupo');
