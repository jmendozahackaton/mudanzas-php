<?php
require_once __DIR__ . '/../utils/JWT.php';

class AuthMiddleware {
    public static function authenticate() {
        $headers = getallheaders();
        $token = null;

        error_log("=== INICIANDO AUTENTICACIÓN ===");

        // Obtener token de diferentes fuentes
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            error_log("Header Authorization encontrado: " . $authHeader);
            
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
                error_log("Token extraído: " . substr($token, 0, 30) . "...");
            }
        }

        // También verificar en $_SERVER por si acaso
        if (!$token && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            error_log("HTTP_AUTHORIZATION encontrado: " . $authHeader);
            
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
                error_log("Token extraído (fallback): " . substr($token, 0, 30) . "...");
            }
        }

        if (!$token) {
            error_log("❌ No se encontró token en la solicitud");
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
