<?php
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Prueba de conexión exitosa
    if ($db) {
        echo json_encode([
            'status' => 'success',
            'message' => '✅ API de Mudanzas funcionando correctamente',
            'database' => 'Conexión a MySQL establecida satisfactoriamente',
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoints' => [
                'GET /' => 'Estado del sistema',
                'GET /users' => 'Listar usuarios',
                'POST /users' => 'Crear usuario'
            ]
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error de conexión a la base de datos',
        'error' => $e->getMessage()
    ]);
}
?>
