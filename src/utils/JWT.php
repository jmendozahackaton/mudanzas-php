<?php
class JWT {
    private static $secret_key;

    public static function init() {
        self::$secret_key = getenv('JWT_SECRET');
        
        // Debug para verificar si la variable está cargada
        if (!self::$secret_key) {
            error_log("❌ JWT_SECRET no está configurado en variables de entorno");
            // Usar un valor por defecto solo para desarrollo
            self::$secret_key = 'clave_por_defecto_solo_desarrollo';
        } else {
            error_log("✅ JWT_SECRET cargado correctamente");
        }
    }

    public static function generate($payload) {
        self::init();
        
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['exp'] = time() + (60 * 60 * 24 * 7); // 7 días en lugar de 1
        $payload['iat'] = time(); // Fecha de emisión
        $payload_json = json_encode($payload);

        error_log("Generando JWT para usuario: " . ($payload['email'] ?? 'unknown'));

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload_json);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret_key, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
        
        error_log("JWT generado exitosamente");
        return $jwt;
    }

    public static function verify($token) {
        self::init();
        
        error_log("Verificando token: " . substr($token, 0, 30) . "...");

        $parts = explode('.', $token);
        if (count($parts) != 3) {
            error_log("❌ Formato de token inválido");
            return false;
        }

        list($header, $payload, $signature) = $parts;

        $valid_signature = hash_hmac('sha256', $header . "." . $payload, self::$secret_key, true);
        $valid_signature = self::base64UrlEncode($valid_signature);

        if (!hash_equals($signature, $valid_signature)) {
            error_log("❌ Firma del token inválida");
            return false;
        }

        $decoded_payload = json_decode(self::base64UrlDecode($payload), true);
        
        if (!$decoded_payload) {
            error_log("❌ Payload del token corrupto");
            return false;
        }

        if (isset($decoded_payload['exp']) && $decoded_payload['exp'] < time()) {
            error_log("❌ Token expirado");
            return false;
        }

        error_log("✅ Token válido para: " . ($decoded_payload['email'] ?? 'unknown'));
        return $decoded_payload;
    }

    private static function base64UrlEncode($data) {
        if (is_array($data) || is_object($data)) {
            $data = json_encode($data);
        }
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private static function base64UrlDecode($data) {
        $padding = strlen($data) % 4;
        if ($padding) {
            $data .= str_repeat('=', 4 - $padding);
        }
        $data = str_replace(['-', '_'], ['+', '/'], $data);
        return base64_decode($data);
    }
}
?>
