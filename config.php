<?php
/**
 * SISTEMA DE BOAS-VINDAS AQUABEAT
 * Arquivo de Configuracao
 */

// Configuracoes do Sistema de Boas-Vindas
define('BV_DB_HOST', 'localhost');
define('BV_DB_NAME', 'mcaq_uaboasvindas');
define('BV_DB_USER', 'root');
define('BV_DB_PASS', '');
define('BV_DB_CHARSET', 'utf8mb4');

// Configuracoes da API Externa (banco de auditoria)
define('API_DB_HOST', 'localhost');
define('API_DB_NAME', 'mcaq_uabeat');
define('API_DB_USER', 'root');
define('API_DB_PASS', '');
define('API_DB_CHARSET', 'utf8mb4');

// Configuracoes Gerais
define('SITE_URL', 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF'] ?? ''));
define('TIMEZONE', 'America/Sao_Paulo');
date_default_timezone_set(TIMEZONE);

// Iniciar sessao
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Classe de Conexao com Banco de Dados
 */
class Database {
    private static $connectionBV = null;
    private static $connectionAPI = null;

    /**
     * Obter conexao com o banco de Boas-Vindas
     */
    public static function getConnectionBV() {
        if (self::$connectionBV === null) {
            try {
                $dsn = "mysql:host=" . BV_DB_HOST . ";dbname=" . BV_DB_NAME . ";charset=" . BV_DB_CHARSET;
                self::$connectionBV = new PDO($dsn, BV_DB_USER, BV_DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            } catch (PDOException $e) {
                die("Erro de conexao com banco BV: " . $e->getMessage());
            }
        }
        return self::$connectionBV;
    }

    /**
     * Obter conexao com o banco da API Externa
     */
    public static function getConnectionAPI() {
        if (self::$connectionAPI === null) {
            try {
                $dsn = "mysql:host=" . API_DB_HOST . ";dbname=" . API_DB_NAME . ";charset=" . API_DB_CHARSET;
                self::$connectionAPI = new PDO($dsn, API_DB_USER, API_DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            } catch (PDOException $e) {
                die("Erro de conexao com banco API: " . $e->getMessage());
            }
        }
        return self::$connectionAPI;
    }
}

/**
 * Classe de Autenticacao
 */
class Auth {
    /**
     * Verificar se usuario esta logado
     */
    public static function check() {
        return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
    }

    /**
     * Fazer login
     */
    public static function login($email, $senha) {
        try {
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

                // Registrar log de login
                Logger::log('login', 'Usuario fez login');

                return true;
            }

            return false;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Fazer logout
     */
    public static function logout() {
        if (self::check()) {
            Logger::log('logout', 'Usuario fez logout');
        }

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();
    }

    /**
     * Exigir login - redireciona se nao estiver logado
     */
    public static function requireLogin() {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    /**
     * Exigir admin - redireciona se nao for admin
     */
    public static function requireAdmin() {
        self::requireLogin();

        if (!self::isAdmin()) {
            header('Location: index.php');
            exit;
        }
    }

    /**
     * Obter ID do usuario logado
     */
    public static function getUserId() {
        return $_SESSION['usuario_id'] ?? null;
    }

    /**
     * Obter nome do usuario logado
     */
    public static function getUserName() {
        return $_SESSION['usuario_nome'] ?? '';
    }

    /**
     * Obter email do usuario logado
     */
    public static function getUserEmail() {
        return $_SESSION['usuario_email'] ?? '';
    }

    /**
     * Verificar se usuario e admin
     */
    public static function isAdmin() {
        return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'admin';
    }
}

/**
 * Classe de Log de Atividades
 */
class Logger {
    /**
     * Registrar log de atividade
     */
    public static function log($acao, $descricao, $entidade_tipo = null, $entidade_id = null) {
        try {
            $db = Database::getConnectionBV();

            $stmt = $db->prepare("
                INSERT INTO logs_atividades (usuario_id, acao, descricao, entidade_tipo, entidade_id, ip, criado_em)
                VALUES (:usuario_id, :acao, :descricao, :entidade_tipo, :entidade_id, :ip, NOW())
            ");

            $stmt->execute([
                ':usuario_id' => Auth::getUserId(),
                ':acao' => $acao,
                ':descricao' => $descricao,
                ':entidade_tipo' => $entidade_tipo,
                ':entidade_id' => $entidade_id,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (PDOException $e) {
            // Silenciar erros de log para nao interromper a aplicacao
        }
    }
}

/**
 * Funcoes Auxiliares
 */

/**
 * Enviar resposta JSON
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Formatar data
 */
function formatarData($data, $formato = 'd/m/Y H:i') {
    if (empty($data)) {
        return '-';
    }

    try {
        $dt = new DateTime($data);
        return $dt->format($formato);
    } catch (Exception $e) {
        return $data;
    }
}

/**
 * Formatar valor monetario
 */
function formatarMoeda($valor) {
    if ($valor === null || $valor === '') {
        return 'R$ 0,00';
    }
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

/**
 * Formatar CPF/CNPJ
 */
function formatarDocumento($documento) {
    if (empty($documento)) {
        return '-';
    }

    // Remover caracteres nao numericos
    $doc = preg_replace('/[^0-9]/', '', $documento);

    if (strlen($doc) == 11) {
        // CPF: 000.000.000-00
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc);
    } elseif (strlen($doc) == 14) {
        // CNPJ: 00.000.000/0000-00
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
    }

    return $documento;
}

/**
 * Formatar telefone
 */
function formatarTelefone($telefone) {
    if (empty($telefone)) {
        return '-';
    }

    // Remover caracteres nao numericos
    $tel = preg_replace('/[^0-9]/', '', $telefone);

    if (strlen($tel) == 11) {
        // Celular: (00) 00000-0000
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $tel);
    } elseif (strlen($tel) == 10) {
        // Fixo: (00) 0000-0000
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $tel);
    }

    return $telefone;
}
