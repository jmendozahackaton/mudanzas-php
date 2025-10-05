<?php
//require_once __DIR__ . '/config/database.php';  // Esto ya no es necesario porque database.php se carga automáticamente

// Configuración de headers para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Obtener la ruta solicitada
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Simple router para la API
try {
    switch (true) {
        case $path === '/api/test' && $method === 'GET':
            // Endpoint básico de prueba
            echo json_encode([
                'status' => 'success',
                'service' => 'API Mudanzas',
                'message' => 'API funcionando correctamente en Cloud Run',
                'timestamp' => date('Y-m-d H:i:s'),
                'environment' => getenv('ENVIRONMENT') ?: 'production'
            ]);
            break;
            
        case $path === '/api/db-test' && $method === 'GET':
            // Endpoint de prueba de base de datos
            $dbStatus = checkDatabaseStatus();
            
            if ($dbStatus['status'] === 'connected') {
                echo json_encode([
                    'status' => 'success',
                    'database' => 'Conexión exitosa a Cloud SQL',
                    'mysql_version' => $dbStatus['mysql_version'],
                    'server_time' => $dbStatus['server_time'],
                    'database_name' => 'plataforma_mudanzas'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Error de conexión a la base de datos',
                    'error' => $dbStatus['message']
                ]);
            }
            break;
            
        case $path === '/api/health' && $method === 'GET':
            // Endpoint de health check para Cloud Run
            $dbStatus = checkDatabaseStatus();
            
            if ($dbStatus['status'] === 'connected') {
                echo json_encode([
                    'status' => 'healthy',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'database' => 'connected',
                    'service' => 'api-mudanzas'
                ]);
            } else {
                http_response_code(503);
                echo json_encode([
                    'status' => 'unhealthy',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'database' => 'disconnected',
                    'error' => $dbStatus['message']
                ]);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Endpoint no encontrado',
                'requested_path' => $path,
                'available_endpoints' => [
                    '/api/test',
                    '/api/db-test', 
                    '/api/health'
                ]
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
