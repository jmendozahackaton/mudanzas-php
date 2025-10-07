<?php
class Response {
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function success($message, $data = null, $statusCode = 200) {
        self::json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], $statusCode);
    }

    public static function error($message, $statusCode = 400) {
        self::json([
            'status' => 'error',
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ], $statusCode);
    }

    public static function notFound($message = 'Recurso no encontrado') {
        self::error($message, 404);
    }

    public static function unauthorized($message = 'No autorizado') {
        self::error($message, 401);
    }

    public static function serverError($message = 'Error interno del servidor') {
        self::error($message, 500);
    }
}
?>
