<?php
require 'vendor/autoload.php';
require_once 'config.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTAuth {
    private static $key;
    private static $issuer;
    private static $expiry;

    public static function init() {
        self::$key = JWT_SECRET_KEY;
        self::$issuer = JWT_ISSUER;
        self::$expiry = JWT_EXPIRY;
    }

    public static function generateToken($userId, $userData = []) {
        self::init();
        $payload = [
            "iss" => self::$issuer,
            "iat" => time(),
            "exp" => time() + self::$expiry,
            "uid" => $userId,
            "data" => $userData
        ];

        return JWT::encode($payload, self::$key, 'HS256');
    }

    public static function validateToken($token) {
        self::init();
        try {
            $decoded = JWT::decode($token, new Key(self::$key, 'HS256'));
            return [
                'valid' => true,
                'data' => $decoded
            ];
        } catch (Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public static function getTokenFromHeader() {
        $headers = apache_request_headers();
        if (!isset($headers['Authorization'])) {
            return null;
        }

        $authHeader = $headers['Authorization'];
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function requireAuth() {
        $token = self::getTokenFromHeader();
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'Token não fornecido']);
            exit;
        }

        $validation = self::validateToken($token);
        if (!$validation['valid']) {
            http_response_code(401);
            echo json_encode(['error' => 'Token inválido ou expirado']);
            exit;
        }

        return $validation['data'];
    }
}
?> 