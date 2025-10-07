<?php
// Cargar configuración básica
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/utils/Response.php';

// Router principal
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Routing para las APIs
    switch (true) {
        // Health check (importante para Cloud Run)
        case $path === '/api/health' && $method === 'GET':
            Response::json([
                'status' => 'healthy',
                'service' => 'API Mudanzas',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        // Auth endpoints
        case $path === '/api/auth/register' && $method === 'POST':
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController($pdo);
            $controller->register();
            break;

        case $path === '/api/auth/login' && $method === 'POST':
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController($pdo);
            $controller->login();
            break;

        // User endpoints
        case $path === '/api/user/profile' && $method === 'GET':
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController($pdo);
            $controller->getProfile();
            break;

        case $path === '/api/user/profile' && $method === 'PUT':
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController($pdo);
            $controller->updateProfile();
            break;

        // Admin endpoints
        case $path === '/api/admin/users' && $method === 'GET':
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController($pdo);
            $controller->listUsers();
            break;

        case $path === '/api/admin/users/status' && $method === 'PUT':
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController($pdo);
            $controller->changeUserStatus();
            break;

        case $path === '/api/admin/users/role' && $method === 'PUT':
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController($pdo);
            $controller->changeUserRole();
            break;

        // Test endpoint
        case $path === '/api/test' && $method === 'GET':
            Response::success('API funcionando correctamente', [
                'service' => 'Mudanzas API',
                'version' => '1.0',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        // Default - Endpoint not found
        default:
            Response::error('Endpoint no encontrado: ' . $path, 404);
    }
} catch (Exception $e) {
    error_log("Error en router: " . $e->getMessage());
    Response::error('Error interno del servidor: ' . $e->getMessage(), 500);
}
?>
