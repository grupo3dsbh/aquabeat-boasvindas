# 🎉 SISTEMA DE BOAS-VINDAS AQUABEAT

Sistema completo de gestão de boas-vindas para novos clientes com checklist interativo, scripts de atendimento e registro de atividades.

## 📋 FUNCIONALIDADES

### Para Atendentes:
- ✅ Lista de vendas do mês atual
- ✅ Checklist completo com todos os passos do atendimento
- ✅ Scripts prontos para cada etapa da ligação
- ✅ Registro de tentativas de contato (ligação, WhatsApp, e-mail)
- ✅ Campo de observações
- ✅ Salvamento automático do progresso
- ✅ Visualização do relatório final

### Para Administradores:
- ✅ Gestão de usuários (criar, editar, desativar)
- ✅ Visualização de todos os atendimentos
- ✅ Logs completos de atividades
- ✅ Relatórios e estatísticas
- ✅ Visualização de histórico completo

## 🚀 INSTALAÇÃO

### 1. Requisitos
- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Apache ou Nginx
- Extensões PHP: PDO, PDO_MySQL

### 2. Configuração do Banco de Dados

#### a) Criar o banco de dados do sistema:
```bash
mysql -u root -p < database.sql
```

Ou execute manualmente:
```sql
CREATE DATABASE aquabeat_boasvindas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Depois execute o arquivo `database.sql` completo.

#### b) Configurar acesso ao banco:
Edite o arquivo `config.php` e ajuste as credenciais:

```php
// Banco do Sistema de Boas-Vindas
define('BV_DB_HOST', 'localhost');
define('BV_DB_NAME', 'aquabeat_boasvindas');
define('BV_DB_USER', 'seu_usuario');
define('BV_DB_PASS', 'sua_senha');

// Banco da API Externa (auditoria)
define('API_DB_HOST', 'localhost');
define('API_DB_NAME', 'mcaq_auditoria');
define('API_DB_USER', 'mcaq_auditoria');
define('API_DB_PASS', 'sign@2023DS');
```

### 3. Copiar arquivos para o servidor

Copie todos os arquivos para o diretório do seu servidor web:

```bash
cp -r * /var/www/html/boasvindas/
```

Ou para o XAMPP/WAMP:
```bash
cp -r * C:\xampp\htdocs\boasvindas\
```

### 4. Configurar permissões (Linux)

```bash
chmod 755 /var/www/html/boasvindas
chmod 644 /var/www/html/boasvindas/*.php
```

### 5. Acessar o sistema

Abra no navegador:
```
http://localhost/boasvindas/
```

**Credenciais padrão:**
- **E-mail:** admin@aquabeat.com.br
- **Senha:** admin123

⚠️ **IMPORTANTE:** Altere a senha do administrador após o primeiro acesso!

## 📂 ESTRUTURA DE ARQUIVOS

```
boasvindas/
├── database.sql                 # Script SQL completo
├── config.php                   # Configurações e conexão
├── login.php                    # Página de login
├── logout.php                   # Logout
├── index.php                    # Dashboard principal
├── boasvindas.php              # Página de atendimento
├── visualizar.php              # Visualizar boas-vindas concluído
├── usuarios.php                # Gestão de usuários (admin)
├── relatorios.php              # Relatórios (admin)
├── api_vendas.php              # API: listar vendas
├── api_salvar_boasvindas.php   # API: salvar progresso
├── api_salvar_tentativa.php    # API: registrar tentativa
├── api_tentativas.php          # API: listar tentativas
└── manual-boas-vindas-aquabeat.html  # Manual do atendente
```

## 🎯 COMO USAR

### Para Atendentes:

1. **Login**
   - Acesse o sistema com suas credenciais

2. **Dashboard**
   - Visualize todas as vendas do mês
   - Vendas pendentes aparecem em destaque
   - Use os filtros para visualizar apenas pendentes ou concluídos

3. **Iniciar Boas-Vindas**
   - Clique em "Iniciar" na venda desejada
   - O sistema carrega automaticamente os dados do cliente

4. **Durante o Atendimento**
   - Siga o checklist passo a passo
   - Leia os scripts fornecidos
   - Marque os itens conforme for completando
   - Use "Registrar Tentativa" se não conseguir contato
   - Preencha as informações coletadas
   - Adicione observações importantes

5. **Salvar Progresso**
   - Clique em "Salvar Progresso" para não perder o trabalho
   - Você pode voltar depois para continuar

6. **Concluir**
   - Quando terminar, clique em "Concluir Boas-Vindas"
   - O atendimento ficará marcado como concluído

### Para Administradores:

1. **Gestão de Usuários**
   - Acesse "Usuários" no menu
   - Crie novos atendentes
   - Ative/desative usuários
   - Redefina senhas se necessário

2. **Relatórios**
   - Visualize estatísticas gerais
   - Acompanhe performance por atendente
   - Veja logs de atividades
   - Exporte relatórios

3. **Auditoria**
   - Todos os logs são salvos automaticamente
   - Veja quem fez o quê e quando
   - Acompanhe tentativas de contato

## 🔧 CONFIGURAÇÕES AVANÇADAS

### Alterar Senha de Usuário (via SQL)

```sql
UPDATE usuarios 
SET senha = MD5('nova_senha') 
WHERE email = 'usuario@email.com';
```

⚠️ **ATENÇÃO:** Em produção, use bcrypt ao invés de MD5!

### Criar Novo Usuário Admin (via SQL)

```sql
INSERT INTO usuarios (nome, email, senha, tipo) 
VALUES ('Nome Admin', 'admin@email.com', MD5('senha123'), 'admin');
```

### Backup do Banco de Dados

```bash
mysqldump -u usuario -p aquabeat_boasvindas > backup_boasvindas.sql
```

## 🐛 TROUBLESHOOTING

### Erro: "Não consegue conectar ao banco"
- Verifique as credenciais em `config.php`
- Certifique-se que o MySQL está rodando
- Teste a conexão manualmente

### Erro: "Vendas não aparecem"
- Verifique se o banco `mcaq_auditoria` está acessível
- Confirme que a tabela `titulos_analise` existe
- Verifique as credenciais da API em `config.php`

### Erro: "Página em branco"
- Ative display_errors no PHP
- Verifique o log de erros do PHP
- Confirme permissões dos arquivos

### Erro 500
- Veja o log de erros do servidor
- Verifique sintaxe dos arquivos PHP
- Confirme extensões PHP instaladas

## 📊 BANCO DE DADOS

### Tabelas Principais:

- **usuarios** - Usuários do sistema
- **boas_vindas** - Registros de atendimentos
- **tentativas_contato** - Histórico de tentativas
- **logs_atividades** - Auditoria completa
- **templates_mensagens** - Templates WhatsApp/Email
- **configuracoes** - Configurações do sistema

### Conexões:

O sistema usa DUAS conexões de banco:
1. `aquabeat_boasvindas` - Sistema de boas-vindas
2. `mcaq_auditoria` - API externa (somente leitura)

## 🔐 SEGURANÇA

### Recomendações:

1. **Altere senhas padrão**
2. **Use HTTPS em produção**
3. **Implemente bcrypt para senhas** (ao invés de MD5)
4. **Configure backup automático**
5. **Limite tentativas de login**
6. **Use firewall para MySQL**

### Implementar bcrypt (recomendado):

```php
// Ao criar usuário:
$senha_hash = password_hash($senha, PASSWORD_BCRYPT);

// Ao fazer login:
if (password_verify($senha, $senha_hash_db)) {
    // Login OK
}
```

## 📞 SUPORTE

Para dúvidas ou problemas:
1. Consulte este README
2. Verifique os logs do sistema
3. Entre em contato com o desenvolvedor

## 📝 CHANGELOG

### Versão 1.0.0 (21/01/2026)
- ✅ Lançamento inicial
- ✅ Sistema completo de boas-vindas
- ✅ Integração com banco externo
- ✅ Checklist interativo com scripts
- ✅ Gestão de usuários
- ✅ Logs e auditoria completos

## 📜 LICENÇA

Sistema desenvolvido exclusivamente para Aquabeat.
Todos os direitos reservados.

---

**Desenvolvido com ❤️ para Aquabeat**

---

## 🎉 VERSÃO 2.0 - SISTEMA COMPLETO

### ✨ Novas Páginas Adicionadas:

#### 📄 visualizar.php - Relatório Final
- Visualização completa do atendimento concluído
- Informações do cliente e da venda
- Resultados coletados durante o atendimento
- Avaliação do consultor com notas
- Timeline de tentativas de contato
- Logs de atividades (para admin)
- Botão de impressão
- **Ideal para:** Revisar atendimentos finalizados e gerar relatórios

#### 👥 usuarios.php - Gestão de Usuários
- Criar novos usuários (atendentes e admins)
- Editar informações de usuários
- Ativar/Desativar usuários
- Excluir usuários (com validação)
- Visualizar estatísticas por usuário
- Cards visuais com performance
- **Ideal para:** Administradores gerenciarem a equipe

#### 📊 relatorios.php - Relatórios e Gráficos
- Dashboard completo com estatísticas
- Gráfico de atendimentos por mês
- Gráfico de status (pizza)
- Performance detalhada dos atendentes
- Top 10 consultores melhor avaliados
- Estatísticas de tentativas de contato
- Exportação visual de dados
- **Ideal para:** Análise de performance e tomada de decisões

### 🔌 Novas APIs:

- **api_usuario.php** - Buscar dados de um usuário
- **api_salvar_usuario.php** - Criar/Editar usuário
- **api_toggle_usuario.php** - Ativar/Desativar usuário
- **api_excluir_usuario.php** - Excluir usuário

### 📦 Arquivos do Sistema (Total):

```
📁 Sistema Completo:
├── 📄 Páginas Principais (6)
│   ├── login.php
│   ├── index.php (Dashboard)
│   ├── boasvindas.php (Atendimento)
│   ├── visualizar.php (Relatório)
│   ├── usuarios.php (Gestão)
│   └── relatorios.php (Estatísticas)
│
├── 🔌 APIs (9)
│   ├── api_vendas.php
│   ├── api_salvar_boasvindas.php
│   ├── api_salvar_tentativa.php
│   ├── api_tentativas.php
│   ├── api_usuario.php
│   ├── api_salvar_usuario.php
│   ├── api_toggle_usuario.php
│   └── api_excluir_usuario.php
│
├── ⚙️ Core (3)
│   ├── config.php
│   ├── logout.php
│   └── database.sql
│
└── 📚 Documentação (2)
    ├── README.md
    └── manual-boas-vindas-aquabeat.html
```

### 🎯 FUNCIONALIDADES COMPLETAS

#### Para Atendentes:
✅ Dashboard com vendas do mês
✅ Iniciar/continuar atendimentos
✅ Checklist completo com scripts
✅ Registro de tentativas
✅ Salvamento automático
✅ Visualizar relatórios finalizados

#### Para Administradores:
✅ Tudo que atendentes têm +
✅ Criar/editar/excluir usuários
✅ Ativar/desativar usuários
✅ Relatórios completos com gráficos
✅ Estatísticas de performance
✅ Rankings de consultores
✅ Logs de auditoria completos
✅ Análise de tentativas de contato

### 📈 Gráficos e Visualizações

O sistema agora inclui:
- 📊 Gráfico de linha: Atendimentos por mês
- 🥧 Gráfico de pizza: Status dos atendimentos
- 📋 Tabelas: Performance dos atendentes
- ⭐ Rankings: Top 10 consultores
- 📞 Estatísticas: Tentativas de contato

### 🔐 Segurança Aprimorada

- ✅ Validação de permissões em todas as páginas
- ✅ Proteção contra auto-exclusão de admin
- ✅ Validação de e-mails duplicados
- ✅ Logs de todas as ações
- ✅ Controle de sessão robusto

### 💾 Backup Recomendado

Para fazer backup completo:

```bash
# Backup do banco de dados
mysqldump -u usuario -p aquabeat_boasvindas > backup_$(date +%Y%m%d).sql

# Backup dos arquivos
tar -czf backup_arquivos_$(date +%Y%m%d).tar.gz /caminho/do/sistema/*
```

### 🚀 Performance

O sistema foi otimizado para:
- ⚡ Carregamento rápido de páginas
- 💾 Consultas otimizadas ao banco
- 📊 Renderização eficiente de gráficos
- 🔄 Atualização em tempo real

### 📱 Responsividade

Todas as páginas são 100% responsivas:
- 💻 Desktop
- 📱 Tablet
- 📲 Mobile

### 🎨 Interface

Design moderno com:
- Gradientes elegantes
- Cards interativos
- Animações suaves
- Icons do Bootstrap
- Paleta de cores profissional

---

## 📝 CHANGELOG DETALHADO

### Versão 2.0.0 (23/01/2026)
**SISTEMA COMPLETO IMPLEMENTADO**

#### ✨ Novas Funcionalidades:
- ✅ Página de visualização de relatório final
- ✅ Gestão completa de usuários
- ✅ Dashboard de relatórios com gráficos
- ✅ Sistema de rankings
- ✅ Análise de performance
- ✅ Exportação e impressão

#### 🔧 Melhorias:
- ✅ Interface mais intuitiva
- ✅ Performance otimizada
- ✅ Segurança reforçada
- ✅ Documentação completa

#### 🐛 Correções:
- ✅ Validações aprimoradas
- ✅ Tratamento de erros
- ✅ Proteções contra SQL Injection
- ✅ Escape de HTML/XSS

### Versão 1.0.0 (21/01/2026)
- ✅ Lançamento inicial
- ✅ Sistema base de boas-vindas

---

## 🎓 TREINAMENTO

### Para Atendentes:

1. **Primeiro Acesso**
   - Faça login com suas credenciais
   - Familiarize-se com o dashboard
   - Leia o manual impresso

2. **Realizando Atendimento**
   - Escolha uma venda pendente
   - Siga o checklist passo a passo
   - Use os scripts fornecidos
   - Salve o progresso regularmente
   - Conclua quando terminar

3. **Boas Práticas**
   - Seja cordial e empático
   - Siga os scripts
   - Marque todos os checkboxes
   - Adicione observações importantes
   - Registre tentativas de contato

### Para Administradores:

1. **Gestão de Usuários**
   - Crie usuários com senhas fortes
   - Defina permissões adequadas
   - Monitore atividades
   - Desative usuários inativos

2. **Análise de Relatórios**
   - Acesse relatórios semanalmente
   - Identifique padrões
   - Reconheça bons atendentes
   - Corrija problemas rapidamente

3. **Manutenção**
   - Faça backups regulares
   - Monitore logs de erro
   - Atualize senhas periodicamente
   - Revise configurações

---

## 🆘 FAQ - Perguntas Frequentes

**Q: Como redefinir a senha de um usuário?**
A: Acesse Usuários > Editar > Digite nova senha > Salvar

**Q: Posso excluir um atendimento?**
A: Não. Por questões de auditoria, atendimentos não podem ser excluídos. Apenas admins podem visualizar logs.

**Q: Como exportar relatórios?**
A: Use o botão "Imprimir" e escolha "Salvar como PDF"

**Q: Posso ter múltiplos admins?**
A: Sim! Crie novos usuários e defina tipo como "Administrador"

**Q: O que acontece se eu fechar a página durante o atendimento?**
A: Use "Salvar Progresso" regularmente. Dados não salvos serão perdidos.

**Q: Como ver atendimentos antigos?**
A: No dashboard, remova os filtros de status para ver todos.

---

## 🎁 EXTRAS INCLUÍDOS

- ✅ Manual visual imprimível
- ✅ Scripts prontos personalizados
- ✅ Templates de mensagens
- ✅ Sistema de logs completo
- ✅ Backup automático via triggers
- ✅ Documentação técnica completa

---

## 📞 SUPORTE TÉCNICO

### Logs do Sistema

Em caso de erro, verifique:

**PHP:**
```bash
tail -f /var/log/apache2/error.log
```

**MySQL:**
```bash
tail -f /var/log/mysql/error.log
```

**Aplicação:**
Todos os erros são registrados em `logs_atividades`

### Troubleshooting Avançado

**Problema: Gráficos não aparecem**
- Verifique se Chart.js está carregando
- Veja o console do navegador (F12)
- Confirme conexão com internet (CDN)

**Problema: Relatórios vazios**
- Verifique se há dados no período
- Confirme permissões do usuário
- Veja logs de erro do PHP

**Problema: Vendas não aparecem**
- Teste conexão com banco externo
- Verifique credenciais em config.php
- Confirme estrutura da tabela titulos_analise

---

## 🏆 CRÉDITOS

**Sistema desenvolvido com:**
- PHP 7.4+
- MySQL 5.7+
- Bootstrap 5.3
- Chart.js 4.4
- jQuery 3.7

**Desenvolvido com ❤️ para Aquabeat**

---

**🎉 SISTEMA 100% COMPLETO E FUNCIONAL! 🎉**
