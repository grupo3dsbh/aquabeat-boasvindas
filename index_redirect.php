<?php
/**
 * INDEX PRINCIPAL - SISTEMA DE BOAS-VINDAS AQUABEAT
 * 
 * Este arquivo verifica se o sistema está instalado.
 * Se não estiver, redireciona para o instalador.
 * Se estiver, redireciona para o login.
 */

// Verificar se já está instalado
$instalado = false;

// Verificar se config.php existe e tem as configurações
if (file_exists('config.php')) {
    $config_content = file_get_contents('config.php');
    
    // Verificar se tem as constantes necessárias
    if (strpos($config_content, "define('BV_DB_HOST'") !== false &&
        strpos($config_content, "define('BV_DB_NAME'") !== false) {
        
        // Tentar incluir config
        try {
            require_once 'config.php';
            
            // Tentar conectar ao banco
            try {
                $db = Database::getConnectionBV();
                
                // Verificar se tabela usuarios existe
                $stmt = $db->query("SHOW TABLES LIKE 'usuarios'");
                if ($stmt->rowCount() > 0) {
                    $instalado = true;
                }
            } catch (Exception $e) {
                // Banco não existe ou não consegue conectar
                $instalado = false;
            }
        } catch (Exception $e) {
            $instalado = false;
        }
    }
}

// Redirecionar
if (!$instalado) {
    // Sistema não instalado - ir para instalador
    if (file_exists('install.php')) {
        header('Location: install.php');
        exit;
    } else {
        die('
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Erro - Sistema não instalado</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .error-card {
                    background: white;
                    border-radius: 15px;
                    padding: 40px;
                    max-width: 500px;
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <div class="error-card">
                <h1 class="text-danger mb-4">⚠️ Erro</h1>
                <p class="lead">Sistema não instalado e instalador não encontrado!</p>
                <p>Por favor, faça upload do arquivo <code>install.php</code> para instalar o sistema.</p>
            </div>
        </body>
        </html>
        ');
    }
} else {
    // Sistema já instalado - ir para login
    header('Location: login.php');
    exit;
}
