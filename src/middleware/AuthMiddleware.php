<?php
require_once __DIR__ . '/../utils/JWT.php';

class AuthMiddleware {
    public static function authenticate() {
        $token = null;

        error_log("=== INICIANDO AUTENTICACIÓN ===");

        // Método 1: Headers normales
        $headers = getallheaders();
        error_log("Headers disponibles: " . json_encode(array_keys($headers)));

        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            error_log("Header Authorization encontrado: " . $authHeader);
            
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
                error_log("Token extraído de Authorization: " . substr($token, 0, 30) . "...");
            }
        }

        // Método 2: $_SERVER['HTTP_AUTHORIZATION'] (cuando Apache lo pasa)
        if (!$token && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            error_log("HTTP_AUTHORIZATION encontrado: " . $authHeader);
            
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
                error_log("Token extraído de HTTP_AUTHORIZATION: " . substr($token, 0, 30) . "...");
            }
        }

        // Método 3: $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] (alternativa)
        if (!$token && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            error_log("REDIRECT_HTTP_AUTHORIZATION encontrado: " . $authHeader);
            
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
                error_log("Token extraído de REDIRECT_HTTP_AUTHORIZATION: " . substr($token, 0, 30) . "...");
            }
        }

        // Método 4: apache_request_headers() como fallback
        if (!$token && function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            error_log("Apache headers: " . json_encode(array_keys($apacheHeaders)));
            
            if (isset($apacheHeaders['Authorization'])) {
                $authHeader = $apacheHeaders['Authorization'];
                error_log("Authorization de apache_request_headers: " . $authHeader);
                
                if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                    $token = $matches[1];
                    error_log("Token extraído de apache headers: " . substr($token, 0, 30) . "...");
                }
            }
        }

        if (!$token) {
            error_log("❌ No se encontró token en ninguna fuente");
            error_log("$_SERVER keys: " . json_encode(array_keys($_SERVER)));
            http_response_code(401);
            echo json_encode(['error' => 'Token de acceso requerido']);
            exit;
        }

        $payload = JWT::verify($token);
        if (!$payload) {
            error_log("❌ Verificación de token fallida");
            http_response_code(401);
            echo json_encode(['error' => 'Token inválido o expirado']);
            exit;
        }

        error_log("✅ Autenticación exitosa para: " . $payload['email']);
        return $payload;
    }
}
?>
