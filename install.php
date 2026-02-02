<?php
/**
 * INSTALADOR AUTOMÁTICO - SISTEMA DE BOAS-VINDAS AQUABEAT
 * 
 * Este arquivo verifica os requisitos, cria o banco de dados,
 * configura as credenciais e prepara o sistema para uso.
 */

// Desabilitar timeout
set_time_limit(0);

// Iniciar sessão para manter dados entre etapas
session_start();

// Etapa atual
$etapa = $_GET['etapa'] ?? 1;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador - Sistema Boas-Vindas Aquabeat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .installer-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
        }
        .installer-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
        }
        .installer-body {
            padding: 40px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        .step:not(:last-child):after {
            content: '';
            position: absolute;
            top: 15px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: #e9ecef;
            z-index: -1;
        }
        .step.active:after {
            background: #667eea;
        }
        .step.completed:after {
            background: #28a745;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .step.active .step-circle {
            background: #667eea;
            color: white;
        }
        .step.completed .step-circle {
            background: #28a745;
            color: white;
        }
        .check-item {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .check-item.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        .check-item.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .check-item.warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .btn-installer {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 8px;
        }
        .btn-installer:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }
        .code-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            font-family: monospace;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="installer-card">
        <div class="installer-header text-center">
            <h1><i class="bi bi-hand-thumbs-up-fill"></i></h1>
            <h2 class="mb-0">Instalador do Sistema</h2>
            <p class="mb-0 mt-2">Sistema de Boas-Vindas Aquabeat</p>
        </div>

        <div class="installer-body">
            <!-- Indicador de Etapas -->
            <div class="step-indicator">
                <div class="step <?= $etapa >= 1 ? ($etapa == 1 ? 'active' : 'completed') : '' ?>">
                    <div class="step-circle"><?= $etapa > 1 ? '✓' : '1' ?></div>
                    <small>Requisitos</small>
                </div>
                <div class="step <?= $etapa >= 2 ? ($etapa == 2 ? 'active' : 'completed') : '' ?>">
                    <div class="step-circle"><?= $etapa > 2 ? '✓' : '2' ?></div>
                    <small>Configuração</small>
                </div>
                <div class="step <?= $etapa >= 3 ? ($etapa == 3 ? 'active' : 'completed') : '' ?>">
                    <div class="step-circle"><?= $etapa > 3 ? '✓' : '3' ?></div>
                    <small>Banco de Dados</small>
                </div>
                <div class="step <?= $etapa >= 4 ? 'active' : '' ?>">
                    <div class="step-circle">4</div>
                    <small>Concluído</small>
                </div>
            </div>

            <?php if ($etapa == 1): ?>
                <!-- ETAPA 1: Verificação de Requisitos -->
                <h4 class="mb-4"><i class="bi bi-check-circle"></i> Verificação de Requisitos</h4>
                
                <?php
                $requisitos_ok = true;
                
                // Verificar versão do PHP
                $php_version = phpversion();
                $php_ok = version_compare($php_version, '7.4.0', '>=');
                ?>
                
                <div class="check-item <?= $php_ok ? 'success' : 'error' ?>">
                    <span>
                        <strong>PHP <?= $php_ok ? '7.4+' : '< 7.4' ?></strong>
                        <br><small>Versão atual: <?= $php_version ?></small>
                    </span>
                    <i class="bi bi-<?= $php_ok ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' ?> fs-4"></i>
                </div>
                
                <?php
                // Verificar extensões do PHP
                $extensoes = [
                    'pdo' => 'PDO',
                    'pdo_mysql' => 'PDO MySQL',
                    'mysqli' => 'MySQLi',
                    'json' => 'JSON',
                    'mbstring' => 'Multibyte String',
                    'zip' => 'ZIP'
                ];
                
                foreach ($extensoes as $ext => $nome):
                    $ext_ok = extension_loaded($ext);
                    if (!$ext_ok && $ext != 'zip') $requisitos_ok = false;
                ?>
                    <div class="check-item <?= $ext_ok ? 'success' : ($ext == 'zip' ? 'warning' : 'error') ?>">
                        <span>
                            <strong>Extensão <?= $nome ?></strong>
                            <?php if ($ext == 'zip'): ?>
                                <br><small>Opcional - Necessário apenas para backups automáticos</small>
                            <?php endif; ?>
                        </span>
                        <i class="bi bi-<?= $ext_ok ? 'check-circle-fill text-success' : ($ext == 'zip' ? 'exclamation-triangle-fill text-warning' : 'x-circle-fill text-danger') ?> fs-4"></i>
                    </div>
                <?php endforeach; ?>
                
                <?php
                // Verificar permissões de escrita
                $writable = is_writable(__DIR__);
                if (!$writable) $requisitos_ok = false;
                ?>
                
                <div class="check-item <?= $writable ? 'success' : 'error' ?>">
                    <span>
                        <strong>Permissão de Escrita</strong>
                        <br><small>Diretório: <?= __DIR__ ?></small>
                    </span>
                    <i class="bi bi-<?= $writable ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' ?> fs-4"></i>
                </div>
                
                <?php if (!$requisitos_ok): ?>
                    <div class="alert alert-danger mt-4">
                        <h5><i class="bi bi-exclamation-triangle-fill"></i> Ação Necessária</h5>
                        <p class="mb-2">Alguns requisitos não foram atendidos. Por favor, corrija os itens em vermelho antes de continuar.</p>
                        
                        <?php if (!$php_ok): ?>
                            <p class="mb-0"><strong>PHP:</strong> Atualize para versão 7.4 ou superior</p>
                        <?php endif; ?>
                        
                        <?php if (!$writable): ?>
                            <p class="mb-0"><strong>Permissões:</strong> Execute: <code>chmod 755 <?= __DIR__ ?></code></p>
                        <?php endif; ?>
                    </div>
                    
                    <button class="btn btn-secondary" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Verificar Novamente
                    </button>
                <?php else: ?>
                    <div class="alert alert-success mt-4">
                        <i class="bi bi-check-circle-fill"></i> Todos os requisitos foram atendidos! Você pode prosseguir.
                    </div>
                    
                    <a href="?etapa=2" class="btn btn-installer">
                        Próxima Etapa <i class="bi bi-arrow-right"></i>
                    </a>
                <?php endif; ?>

            <?php elseif ($etapa == 2): ?>
                <!-- ETAPA 2: Configuração -->
                <h4 class="mb-4"><i class="bi bi-gear-fill"></i> Configuração do Sistema</h4>
                
                <form method="POST" action="?etapa=3">
                    <h5 class="mb-3">Banco de Dados do Sistema (Boas-Vindas)</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Host do Banco</label>
                            <input type="text" class="form-control" name="bv_host" value="localhost" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome do Banco</label>
                            <input type="text" class="form-control" name="bv_name" value="aquabeat_boasvindas" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Usuário</label>
                            <input type="text" class="form-control" name="bv_user" value="root" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Senha</label>
                            <input type="password" class="form-control" name="bv_pass" placeholder="Deixe em branco se não tiver senha">
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h5 class="mb-3">Banco de Dados Externo (API - Vendas)</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Host do Banco</label>
                            <input type="text" class="form-control" name="api_host" value="localhost" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome do Banco</label>
                            <input type="text" class="form-control" name="api_name" value="mcaq_auditoria" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Usuário</label>
                            <input type="text" class="form-control" name="api_user" value="mcaq_auditoria" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Senha</label>
                            <input type="password" class="form-control" name="api_pass" value="sign@2023DS">
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h5 class="mb-3">Usuário Administrador Inicial</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-control" name="admin_nome" value="Administrador" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">E-mail</label>
                            <input type="email" class="form-control" name="admin_email" value="admin@aquabeat.com.br" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Senha</label>
                            <input type="password" class="form-control" name="admin_senha" value="admin123" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirmar Senha</label>
                            <input type="password" class="form-control" name="admin_senha_confirm" value="admin123" required>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill"></i> 
                        <strong>Importante:</strong> Altere a senha do administrador após o primeiro acesso!
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="?etapa=1" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Voltar
                        </a>
                        <button type="submit" class="btn btn-installer">
                            Próxima Etapa <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </form>

            <?php elseif ($etapa == 3): ?>
                <!-- ETAPA 3: Instalação do Banco -->
                <h4 class="mb-4"><i class="bi bi-database-fill"></i> Instalação do Banco de Dados</h4>
                
                <?php
                // Processar dados do formulário
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $_SESSION['config'] = [
                        'bv_host' => $_POST['bv_host'],
                        'bv_name' => $_POST['bv_name'],
                        'bv_user' => $_POST['bv_user'],
                        'bv_pass' => $_POST['bv_pass'],
                        'api_host' => $_POST['api_host'],
                        'api_name' => $_POST['api_name'],
                        'api_user' => $_POST['api_user'],
                        'api_pass' => $_POST['api_pass'],
                        'admin_nome' => $_POST['admin_nome'],
                        'admin_email' => $_POST['admin_email'],
                        'admin_senha' => $_POST['admin_senha']
                    ];
                }
                
                $config = $_SESSION['config'];
                $erros = [];
                $sucesso = [];
                
                try {
                    // Conectar ao MySQL
                    $dsn = "mysql:host={$config['bv_host']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $config['bv_user'], $config['bv_pass']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    $sucesso[] = "✓ Conexão com MySQL estabelecida";
                    
                    // Criar banco de dados
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS {$config['bv_name']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $sucesso[] = "✓ Banco de dados '{$config['bv_name']}' criado";
                    
                    // Selecionar banco
                    $pdo->exec("USE {$config['bv_name']}");
                    
                    // Ler e executar SQL
                    if (file_exists('database.sql')) {
                        $sql = file_get_contents('database.sql');
                        
                        // Remover comentários e linhas vazias
                        $sql = preg_replace('/--.*$/m', '', $sql);
                        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
                        
                        // Separar por ponto e vírgula
                        $statements = array_filter(array_map('trim', explode(';', $sql)));
                        
                        foreach ($statements as $statement) {
                            if (!empty($statement)) {
                                try {
                                    $pdo->exec($statement);
                                } catch (PDOException $e) {
                                    // Ignorar erros de "já existe"
                                    if (strpos($e->getMessage(), 'already exists') === false) {
                                        throw $e;
                                    }
                                }
                            }
                        }
                        
                        $sucesso[] = "✓ Estrutura do banco criada com sucesso";
                    } else {
                        throw new Exception("Arquivo database.sql não encontrado!");
                    }
                    
                    // Criar usuário admin
                    $stmt = $pdo->prepare("
                        INSERT INTO usuarios (nome, email, senha, tipo, ativo) 
                        VALUES (:nome, :email, MD5(:senha), 'admin', 1)
                        ON DUPLICATE KEY UPDATE nome = :nome
                    ");
                    
                    $stmt->execute([
                        ':nome' => $config['admin_nome'],
                        ':email' => $config['admin_email'],
                        ':senha' => $config['admin_senha']
                    ]);
                    
                    $sucesso[] = "✓ Usuário administrador criado";
                    
                    // Criar arquivo config.php
                    $config_content = "<?php
/**
 * SISTEMA DE BOAS-VINDAS AQUABEAT
 * Arquivo de Configuração
 * Gerado automaticamente pelo instalador
 */

// Configurações do Sistema de Boas-Vindas
define('BV_DB_HOST', '{$config['bv_host']}');
define('BV_DB_NAME', '{$config['bv_name']}');
define('BV_DB_USER', '{$config['bv_user']}');
define('BV_DB_PASS', '{$config['bv_pass']}');
define('BV_DB_CHARSET', 'utf8mb4');

// Configurações da API Externa (banco de auditoria)
define('API_DB_HOST', '{$config['api_host']}');
define('API_DB_NAME', '{$config['api_name']}');
define('API_DB_USER', '{$config['api_user']}');
define('API_DB_PASS', '{$config['api_pass']}');
define('API_DB_CHARSET', 'utf8mb4');

// Configurações Gerais
define('SITE_URL', 'http://' . \$_SERVER['HTTP_HOST'] . dirname(\$_SERVER['PHP_SELF']));
define('TIMEZONE', 'America/Sao_Paulo');
date_default_timezone_set(TIMEZONE);

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ... [resto do arquivo config.php original] ...
";
                    
                    // Ler o config.php original e pegar o resto do conteúdo
                    if (file_exists('config.php.bak')) {
                        $original_config = file_get_contents('config.php.bak');
                        // Pegar tudo depois das configurações
                        $pos = strpos($original_config, '/**');
                        if ($pos !== false) {
                            $pos = strpos($original_config, 'class Database', $pos);
                            if ($pos !== false) {
                                $config_content = substr($config_content, 0, -30) . "\n" . substr($original_config, $pos);
                            }
                        }
                    }
                    
                    file_put_contents('config.php', $config_content);
                    $sucesso[] = "✓ Arquivo config.php criado";
                    
                    // Renomear instalador
                    @rename('install.php', 'install.php.bak');
                    $sucesso[] = "✓ Instalador desativado por segurança";
                    
                } catch (Exception $e) {
                    $erros[] = "Erro: " . $e->getMessage();
                }
                ?>
                
                <?php if (count($erros) > 0): ?>
                    <div class="alert alert-danger">
                        <h5><i class="bi bi-x-circle-fill"></i> Erros na Instalação</h5>
                        <?php foreach ($erros as $erro): ?>
                            <p class="mb-1">• <?= htmlspecialchars($erro) ?></p>
                        <?php endforeach; ?>
                    </div>
                    
                    <a href="?etapa=2" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar e Corrigir
                    </a>
                <?php else: ?>
                    <div class="alert alert-success">
                        <h5><i class="bi bi-check-circle-fill"></i> Instalação Concluída!</h5>
                        <?php foreach ($sucesso as $msg): ?>
                            <p class="mb-1"><?= htmlspecialchars($msg) ?></p>
                        <?php endforeach; ?>
                    </div>
                    
                    <a href="?etapa=4" class="btn btn-installer">
                        Finalizar <i class="bi bi-arrow-right"></i>
                    </a>
                <?php endif; ?>

            <?php elseif ($etapa == 4): ?>
                <!-- ETAPA 4: Conclusão -->
                <div class="text-center">
                    <div class="mb-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 80px;"></i>
                    </div>
                    
                    <h3 class="mb-4">🎉 Instalação Concluída com Sucesso! 🎉</h3>
                    
                    <p class="lead">O Sistema de Boas-Vindas Aquabeat está pronto para uso!</p>
                    
                    <div class="card mt-4 mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Credenciais de Acesso</h5>
                            <div class="code-box text-start">
                                <strong>E-mail:</strong> <?= htmlspecialchars($_SESSION['config']['admin_email'] ?? 'admin@aquabeat.com.br') ?><br>
                                <strong>Senha:</strong> <?= htmlspecialchars($_SESSION['config']['admin_senha'] ?? 'admin123') ?>
                            </div>
                            <div class="alert alert-warning mt-3 mb-0">
                                <i class="bi bi-exclamation-triangle-fill"></i> 
                                <strong>IMPORTANTE:</strong> Altere esta senha após o primeiro acesso!
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info text-start">
                        <h6><i class="bi bi-lightbulb-fill"></i> Próximos Passos:</h6>
                        <ol class="mb-0">
                            <li>Faça login no sistema</li>
                            <li>Altere a senha do administrador</li>
                            <li>Crie usuários para a equipe de atendentes</li>
                            <li>Configure as mensagens de template (se necessário)</li>
                            <li>Faça um teste completo do fluxo de boas-vindas</li>
                        </ol>
                    </div>
                    
                    <a href="login.php" class="btn btn-installer btn-lg">
                        <i class="bi bi-box-arrow-in-right"></i> Acessar o Sistema
                    </a>
                </div>
                
                <?php
                // Limpar sessão
                session_destroy();
                ?>

            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
