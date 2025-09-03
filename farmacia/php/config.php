<?php
// config.php

/* ===================== HEADERS DE SEGURANÇA ===================== */
// Headers devem ser enviados antes de qualquer saída
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    // HSTS apenas se estiver usando HTTPS
    // header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}

/* ===================== SESSÃO SEGURA ===================== */
if (session_status() === PHP_SESSION_NONE) {
    // Verificar se está usando HTTPS
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure'   => $isSecure,
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
    
    // Regeneração de ID
    if (!isset($_SESSION['last_regeneration'])) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

/* ===================== AMBIENTE E ERROS ===================== */
define('ENVIRONMENT', getenv('ENVIRONMENT') ?: 'production');

if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/error.log');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/logs/erro_prod.log');
}

/* ===================== CONFIGURAÇÕES DO BANCO DE DADOS ===================== */
$dbConfig = [
    'host'      => getenv('DB_HOST') ?: 'db',
    'database'  => getenv('DB_NAME') ?: 'farmacia',
    'user'      => getenv('DB_USER') ?: '',
    'pass'      => getenv('DB_PASSWORD') ?: '', 
    'charset'   => 'utf8mb4',
    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]
];

// Validar configurações obrigatórias
if (empty($dbConfig['user']) || empty($dbConfig['pass'])) {
    error_log("ERRO: Credenciais do banco de dados não estão definidas nas variáveis de ambiente!");
    die("Erro de configuração: Credenciais do banco de dados não definidas");
}

/* ===================== CONFIGURAÇÕES JWT ===================== */
define('JWT_SECRET_KEY', getenv('JWT_SECRET_KEY') ?: '');
if (empty(JWT_SECRET_KEY)) {
    error_log("ERRO: JWT_SECRET_KEY não está definida nas variáveis de ambiente!");
    die("Erro de configuração: JWT_SECRET_KEY não definida");
}
define('JWT_ISSUER', 'farmacia.com');
define('JWT_EXPIRY', 3600); // 1 hour in seconds

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    error_log("Tentando conectar ao banco de dados com DSN: " . $dsn);
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], $dbConfig['options']);
    $pdo->exec("SET time_zone = '-03:00';");
    error_log("Conexão com o banco de dados estabelecida com sucesso");
} catch (PDOException $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Erro de conexão: " . $e->getMessage());
    error_log("Detalhes da configuração: " . print_r($dbConfig, true));
    die("Sistema temporariamente indisponível. Código: DB503");
}

/* ===================== FUNÇÕES ESSENCIAIS ===================== */

// Autenticação
function verificarAutenticacao(array $perfisPermitidos = []) {
    if (!isset($_SESSION['usuario'])) {
        header('Location: login.php?redir=' . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
    
    if (!empty($perfisPermitidos)) {
        $perfilUsuario = $_SESSION['usuario']['perfil'] ?? '';
        if (!in_array($perfilUsuario, $perfisPermitidos)) {
            header('Location: index.php?erro=Acesso%20negado');
            exit();
        }
    }
}

// Sanitização
function sanitizar($dado) {
    if (is_array($dado)) {
        return array_map('sanitizar', $dado);
    }
    return htmlspecialchars(
        trim($dado ?? ''),
        ENT_QUOTES | ENT_HTML5, 
        'UTF-8',
        true
    );
}

// Validação de entrada mais robusta
function validarEntrada($dado, $tipo = 'string', $opcoes = []) {
    switch ($tipo) {
        case 'int':
            return filter_var($dado, FILTER_VALIDATE_INT) !== false ? (int)$dado : null;
        case 'float':
            return filter_var($dado, FILTER_VALIDATE_FLOAT) !== false ? (float)$dado : null;
        case 'email':
            return filter_var($dado, FILTER_VALIDATE_EMAIL) ? $dado : null;
        case 'url':
            return filter_var($dado, FILTER_VALIDATE_URL) ? $dado : null;
        case 'ip':
            return filter_var($dado, FILTER_VALIDATE_IP) ? $dado : null;
        case 'boolean':
            return filter_var($dado, FILTER_VALIDATE_BOOLEAN);
        case 'regex':
            if (isset($opcoes['pattern'])) {
                return preg_match($opcoes['pattern'], $dado) ? $dado : null;
            }
            return null;
        case 'in':
            if (isset($opcoes['values'])) {
                return in_array($dado, $opcoes['values']) ? $dado : null;
            }
            return null;
        default:
            return sanitizar($dado);
    }
}

// CSRF Protection
function gerarTokenCsrf() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarTokenCsrf($token) {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// Formatação de CPF
function formatarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) !== 11) return 'Inválido';
    
    return substr($cpf, 0, 3) . '.' . 
           substr($cpf, 3, 3) . '.' . 
           substr($cpf, 6, 3) . '-' . 
           substr($cpf, 9, 2);
}

function verificarCsrf($token) {
    if (!validarTokenCsrf($token)) {
        die('Erro: Token CSRF inválido.');
    }
}

// Proteção contra força bruta
function verificarTentativasLogin($email) {
    global $pdo;
    
    // Limpar tentativas antigas (mais de 1 hora)
    $stmt = $pdo->prepare("DELETE FROM tentativas_login WHERE timestamp < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute();
    
    // Verificar tentativas recentes
    $stmt = $pdo->prepare("SELECT COUNT(*) as tentativas FROM tentativas_login WHERE email = ? AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute([$email]);
    $resultado = $stmt->fetch();
    
    if ($resultado['tentativas'] >= 5) {
        return false; // Muitas tentativas
    }
    
    return true;
}

function registrarTentativaLogin($email, $sucesso) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO tentativas_login (email, sucesso, timestamp) VALUES (?, ?, NOW())");
    $stmt->execute([$email, $sucesso ? 1 : 0]);
}

/* ===================== VERIFICAÇÕES FINAIS ===================== */
try {
    $pdo->query('SELECT 1');
} catch (PDOException $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Verificação inicial falhou: " . $e->getMessage());
    die("Sistema temporariamente indisponível. Tente novamente mais tarde.");
}

date_default_timezone_set('America/Sao_Paulo');
