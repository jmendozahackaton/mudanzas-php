<?php
// Cargar configuración básica
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/utils/Response.php';

// Crear conexión a la base de datos
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    error_log("Error de conexión a BD: " . $e->getMessage());
    // No salir aquí, permitir que endpoints sin BD funcionen
    $pdo = null;
}

// Router principal
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Routing para las APIs
    switch (true) {
        // Health check (sin BD)
        case $path === '/api/health' && $method === 'GET':
            Response::json([
                'status' => 'healthy', 
                'service' => 'API Mudanzas',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        // Test endpoint (sin BD)
        case $path === '/api/test' && $method === 'GET':
            Response::success('API funcionando correctamente', [
                'service' => 'Mudanzas API',
                'version' => '1.0',
                'database' => $pdo ? 'connected' : 'disconnected'
            ]);
            break;

        // ==================== AUTH ENDPOINTS ====================
        case $path === '/api/auth/register' && $method === 'POST':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController($pdo);
            $controller->register();
            break;

        case $path === '/api/auth/login' && $method === 'POST':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController($pdo);
            $controller->login();
            break;

        // ==================== USER ENDPOINTS ====================
        case $path === '/api/user/profile' && $method === 'GET':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController($pdo);
            $controller->getProfile();
            break;

        case $path === '/api/user/profile' && $method === 'PUT':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController($pdo);
            $controller->updateProfile();
            break;

        // ==================== ADMIN ENDPOINTS ====================
        case $path === '/api/admin/users' && $method === 'GET':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController($pdo);
            $controller->listUsers();
            break;

        case $path === '/api/admin/users/status' && $method === 'PUT':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController($pdo);
            $controller->changeUserStatus();
            break;

        case $path === '/api/admin/users/role' && $method === 'PUT':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController($pdo);
            $controller->changeUserRole();
            break;

        // Búsqueda de usuarios
        case $path === '/api/admin/users/search' && $method === 'GET':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController($pdo);
            $controller->searchUsers();
            break;

        // Obtener usuario específico
        case $path === '/api/admin/users/single' && $method === 'GET':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController($pdo);
            $controller->getUserById();
            break;

        // Actualizar perfil de usuario (admin)
        case $path === '/api/admin/users/profile' && $method === 'PUT':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController($pdo);
            $controller->updateUserProfile();
            break;

        // ==================== DEFAULT ====================
        default:
            Response::error('Endpoint no encontrado: ' . $path, 404);
    }
} catch (Throwable $e) {
    error_log("Error en router: " . $e->getMessage());
    Response::error('Error interno del servidor: ' . $e->getMessage(), 500);
}
?>
