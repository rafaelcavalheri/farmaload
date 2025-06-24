<?php
// config.php

/* ===================== SESSÃO SEGURA ===================== */
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure'   => true,
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
define('ENVIRONMENT', 'development');

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
    'user'      => getenv('DB_USER') ?: 'admin',
    'pass'      => getenv('DB_PASSWORD') ?: 'HakETodLEfRe', 
    'charset'   => 'utf8mb4',
    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]
];

/* ===================== CONFIGURAÇÕES JWT ===================== */
define('JWT_SECRET_KEY', getenv('JWT_SECRET_KEY') ?: 'CHAVE-MUITO-SEGURA-AQUI-ALTERE-ISSO');
define('JWT_ISSUER', 'farmacia.mogimirim.sp.gov.br');
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

/* ===================== VERIFICAÇÕES FINAIS ===================== */
try {
    $pdo->query('SELECT 1');
} catch (PDOException $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Verificação inicial falhou: " . $e->getMessage());
    die("Sistema temporariamente indisponível. Tente novamente mais tarde.");
}

date_default_timezone_set('America/Sao_Paulo');