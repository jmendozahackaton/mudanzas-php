<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/utils/Response.php';

// Headers CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Router principal
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Routing para las APIs
    switch (true) {
        // Rutas de Usuario
        case preg_match('#^/api/auth/register/?$#', $path) && $method === 'POST':
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController();
            $controller->register();
            break;

        case preg_match('#^/api/auth/login/?$#', $path) && $method === 'POST':
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController();
            $controller->login();
            break;

        case preg_match('#^/api/user/profile/?$#', $path) && $method === 'GET':
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController();
            $controller->getProfile();
            break;

        case preg_match('#^/api/user/profile/?$#', $path) && $method === 'PUT':
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController();
            $controller->updateProfile();
            break;

        case preg_match('#^/api/admin/users/?$#', $path) && $method === 'GET':
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController();
            $controller->listUsers();
            break;

        case preg_match('#^/api/admin/users/status/?$#', $path) && $method === 'PUT':
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController();
            $controller->changeUserStatus();
            break;

        case preg_match('#^/api/admin/users/role/?$#', $path) && $method === 'PUT':
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController();
            $controller->changeUserRole();
            break;

        default:
            Response::error('Endpoint no encontrado', 404);
    }
} catch (Exception $e) {
    error_log("Error en router: " . $e->getMessage());
    Response::error('Error interno del servidor', 500);
}
?>
