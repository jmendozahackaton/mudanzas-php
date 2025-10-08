<?php
require_once __DIR__ . '/../utils/JWT.php';

class AuthMiddleware {
    public static function authenticate() {
        $token = null;

        error_log("=== INICIANDO AUTENTICACIÓN ===");

        // Para servidor PHP integrado, los headers vienen en $_SERVER
        $headers = getallheaders();
        error_log("Todos los headers: " . json_encode($headers));

        // Método principal: Header Authorization
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            error_log("Authorization header: " . $authHeader);
            
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
                error_log("✅ Token extraído: " . substr($token, 0, 30) . "...");
            }
        }

        // Método alternativo: Desde $_SERVER (cuando viene en minúsculas)
        if (!$token) {
            foreach ($_SERVER as $key => $value) {
                if (strtoupper($key) === 'HTTP_AUTHORIZATION') {
                    $authHeader = $value;
                    error_log("HTTP_AUTHORIZATION from _SERVER: " . $authHeader);
                    
                    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                        $token = $matches[1];
                        error_log("✅ Token extraído de _SERVER: " . substr($token, 0, 30) . "...");
                        break;
                    }
                }
            }
        }

        if (!$token) {
            error_log("❌ No token found in any source");
            error_log("Available _SERVER keys: " . implode(', ', array_keys($_SERVER)));
            http_response_code(401);
            echo json_encode(['error' => 'Token de acceso requerido']);
            exit;
        }

        $payload = JWT::verify($token);
        if (!$payload) {
            error_log("❌ Token verification failed");
            http_response_code(401);
            echo json_encode(['error' => 'Token inválido o expirado']);
            exit;
        }

        error_log("✅ Authentication successful for: " . $payload['email']);
        return $payload;
    }
}
?>
