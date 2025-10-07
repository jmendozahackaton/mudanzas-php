<?php
class AdminMiddleware {
    public static function check() {
        $user = AuthMiddleware::authenticate();
        
        if ($user['rol'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acceso denegado. Se requiere rol de administrador']);
            exit;
        }

        return $user;
    }
}
?>
