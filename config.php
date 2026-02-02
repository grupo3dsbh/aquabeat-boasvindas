<?php
/**
 * SISTEMA DE BOAS-VINDAS AQUABEAT
 * Arquivo de Configuração
 * Gerado automaticamente pelo instalador
 */

// Configurações do Sistema de Boas-Vindas
define('BV_DB_HOST', 'localhost');
define('BV_DB_NAME', 'mcaq_uaboasvindas');
define('BV_DB_USER', 'mcaq_uaboasvindas');
define('BV_DB_PASS', 'sign@3DS');
define('BV_DB_CHARSET', 'utf8mb4');

// Configurações da API Externa (banco de auditoria)
define('API_DB_HOST', 'localhost');
define('API_DB_NAME', 'mcaq_auditoria');
define('API_DB_USER', 'mcaq_auditoria');
define('API_DB_PASS', 'sign@2023DS');
define('API_DB_CHARSET', 'utf8mb4');

// Configurações Gerais
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']));
define('TIMEZONE', 'America/Sao_Paulo');
date_default_timezone_set(TIMEZONE);

// Modo de Desenvolvimento (true = desenvolvimento, false = produção)
// ALTERE PARA false EM PRODUÇÃO!
define('DESENVOLVIMENTO', true);

// Configurar exibição de erros baseado no modo
if (DESENVOLVIMENTO) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Classe de Conexão com Banco de Dados
 */
class Database {
    private static $instance_bv = null;
    private static $instance_api = null;

    /**
     * Conexão com banco de Boas-Vindas
     */
    public static function getConnectionBV() {
        if (self::$instance_bv === null) {
            try {
                $dsn = "mysql:host=" . BV_DB_HOST . ";dbname=" . BV_DB_NAME . ";charset=" . BV_DB_CHARSET;
                self::$instance_bv = new PDO($dsn, BV_DB_USER, BV_DB_PASS);
                self::$instance_bv->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance_bv->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                die("Erro na conexão com banco de Boas-Vindas: " . $e->getMessage());
            }
        }
        return self::$instance_bv;
    }

    /**
     * Conexão com banco da API (auditoria)
     */
    public static function getConnectionAPI() {
        if (self::$instance_api === null) {
            try {
                $dsn = "mysql:host=" . API_DB_HOST . ";dbname=" . API_DB_NAME . ";charset=" . API_DB_CHARSET;
                self::$instance_api = new PDO($dsn, API_DB_USER, API_DB_PASS);
                self::$instance_api->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance_api->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                die("Erro na conexão com banco de API: " . $e->getMessage());
            }
        }
        return self::$instance_api;
    }
}

/**
 * Classe de Autenticação
 */
class Auth {

    /**
     * Verificar se usuário está logado
     */
    public static function check() {
        return isset($_SESSION['usuario_id']) && isset($_SESSION['usuario_tipo']);
    }

    /**
     * Verificar se é admin
     */
    public static function isAdmin() {
        return self::check() && $_SESSION['usuario_tipo'] === 'admin';
    }

    /**
     * Obter ID do usuário logado
     */
    public static function getUserId() {
        return $_SESSION['usuario_id'] ?? null;
    }

    /**
     * Obter nome do usuário logado
     */
    public static function getUserName() {
        return $_SESSION['usuario_nome'] ?? null;
    }

    /**
     * Obter tipo do usuário logado
     */
    public static function getUserType() {
        return $_SESSION['usuario_tipo'] ?? null;
    }

    /**
     * Fazer login
     */
    public static function login($email, $senha) {
        $db = Database::getConnectionBV();

        $stmt = $db->prepare("
            SELECT id, nome, email, tipo, ativo
            FROM usuarios
            WHERE email = :email AND senha = MD5(:senha) AND ativo = 1
        ");

        $stmt->execute([
            ':email' => $email,
            ':senha' => $senha
        ]);

        $usuario = $stmt->fetch();

        if ($usuario) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_tipo'] = $usuario['tipo'];
            return true;
        }

        return false;
    }

    /**
     * Fazer logout
     */
    public static function logout() {
        session_destroy();
    }

    /**
     * Redirecionar se não estiver logado
     */
    public static function requireLogin() {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    /**
     * Redirecionar se não for admin
     */
    public static function requireAdmin() {
        self::requireLogin();
        if (!self::isAdmin()) {
            header('Location: index.php');
            exit;
        }
    }
}

/**
 * Classe de Log de Atividades
 */
class Logger {

    /**
     * Registrar log de atividade
     */
    public static function log($boas_vindas_id, $tipo_acao, $descricao, $dados_alterados = null) {
        $db = Database::getConnectionBV();

        $stmt = $db->prepare("
            INSERT INTO logs_atividades
            (boas_vindas_id, usuario_id, tipo_acao, descricao, dados_alterados, ip_usuario, user_agent)
            VALUES
            (:boas_vindas_id, :usuario_id, :tipo_acao, :descricao, :dados_alterados, :ip_usuario, :user_agent)
        ");

        $stmt->execute([
            ':boas_vindas_id' => $boas_vindas_id,
            ':usuario_id' => Auth::getUserId(),
            ':tipo_acao' => $tipo_acao,
            ':descricao' => $descricao,
            ':dados_alterados' => $dados_alterados ? json_encode($dados_alterados) : null,
            ':ip_usuario' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
}

/**
 * Funções auxiliares
 */

/**
 * Formatar data para exibição
 */
function formatarData($data, $formato = 'd/m/Y H:i') {
    if (!$data) return '-';
    return date($formato, strtotime($data));
}

/**
 * Formatar moeda
 */
function formatarMoeda($valor) {
    if ($valor === null) return '-';
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Formatar telefone
 */
function formatarTelefone($telefone) {
    if (!$telefone) return '-';
    $telefone = preg_replace('/[^0-9]/', '', $telefone);

    if (strlen($telefone) == 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7);
    } elseif (strlen($telefone) == 10) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6);
    }

    return $telefone;
}

/**
 * Formatar CPF/CNPJ
 */
function formatarDocumento($doc) {
    if (!$doc) return '-';
    $doc = preg_replace('/[^0-9]/', '', $doc);

    if (strlen($doc) == 11) {
        // CPF
        return substr($doc, 0, 3) . '.' . substr($doc, 3, 3) . '.' . substr($doc, 6, 3) . '-' . substr($doc, 9, 2);
    } elseif (strlen($doc) == 14) {
        // CNPJ
        return substr($doc, 0, 2) . '.' . substr($doc, 2, 3) . '.' . substr($doc, 5, 3) . '/' . substr($doc, 8, 4) . '-' . substr($doc, 12, 2);
    }

    return $doc;
}

/**
 * Gerar badge de status
 */
function badgeStatus($status) {
    $badges = [
        'pendente' => '<span class="badge bg-warning">Pendente</span>',
        'em_andamento' => '<span class="badge bg-info">Em Andamento</span>',
        'concluido' => '<span class="badge bg-success">Concluído</span>'
    ];

    return $badges[$status] ?? '<span class="badge bg-secondary">-</span>';
}

/**
 * Calcular dias desde a venda
 */
function diasDesdeVenda($data_venda) {
    if (!$data_venda) return 0;
    $data1 = new DateTime($data_venda);
    $data2 = new DateTime();
    return $data1->diff($data2)->days;
}

/**
 * Enviar resposta JSON
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
