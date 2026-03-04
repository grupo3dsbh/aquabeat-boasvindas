-- Atualização v6: Sistema de permissões e registro de pagamentos
-- Execute: mysql -u mcaq_uaboasvindas -p mcaq_uaboasvindas < update_database_v6.sql

-- ==========================================
-- ADICIONAR COLUNA DE PERMISSÕES NA TABELA USUARIOS
-- ==========================================
ALTER TABLE usuarios
ADD COLUMN IF NOT EXISTS permissoes JSON DEFAULT NULL COMMENT 'Lista de permissões específicas do usuário';

-- ==========================================
-- CRIAR TABELA DE PAGAMENTOS REGISTRADOS
-- ==========================================
CREATE TABLE IF NOT EXISTS titulo_pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    boas_vindas_id INT NOT NULL,
    numero_titulo VARCHAR(50) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_pagamento DATE NOT NULL,
    forma_pagamento VARCHAR(50) NOT NULL,
    observacao TEXT,
    registrado_por INT NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (boas_vindas_id) REFERENCES boas_vindas(id) ON DELETE CASCADE,
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
    INDEX idx_boas_vindas (boas_vindas_id),
    INDEX idx_numero_titulo (numero_titulo),
    INDEX idx_data_pagamento (data_pagamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- LISTA DE PERMISSÕES DISPONÍVEIS (referência)
-- ==========================================
-- As permissões são armazenadas como JSON na coluna 'permissoes'
-- Exemplo: ["editar_pagamento", "ver_relatorios", "exportar_dados"]
--
-- Permissões disponíveis:
-- - editar_pagamento: Permite registrar pagamentos de parcelas
-- - ver_relatorios: Permite acessar relatórios
-- - exportar_dados: Permite exportar dados
-- - editar_usuarios: Permite editar outros usuários
-- - ver_todos_titulos: Permite ver títulos de todos os atendentes
-- - concluir_atendimento: Permite marcar atendimentos como concluídos

-- Dar permissão de editar_pagamento para todos os admins (já têm por padrão)
-- Para atendentes específicos, usar a interface de usuários

-- Verificar se a coluna foi criada
SELECT 'Coluna permissoes' as item, COUNT(*) as existe
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'usuarios'
AND COLUMN_NAME = 'permissoes';

-- Verificar se a tabela foi criada
SELECT 'Tabela titulo_pagamentos' as item, COUNT(*) as existe
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'titulo_pagamentos';
