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

        // ==================== PROVEEDOR ENDPOINTS ====================
        case $path === '/api/provider/register' && $method === 'POST':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/ProviderController.php';
            $controller = new ProviderController($pdo);
            $controller->register();
            break;

        case $path === '/api/provider/convert' && $method === 'POST':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/ProviderController.php';
            $controller = new ProviderController($pdo);
            $controller->convertToProvider();
            break;

        case $path === '/api/provider/profile' && $method === 'GET':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/ProviderController.php';
            $controller = new ProviderController($pdo);
            $controller->getProfile();
            break;

        case $path === '/api/provider/profile' && $method === 'PUT':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/ProviderController.php';
            $controller = new ProviderController($pdo);
            $controller->updateProfile();
            break;

        case $path === '/api/provider/availability' && $method === 'PUT':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/ProviderController.php';
            $controller = new ProviderController($pdo);
            $controller->updateAvailability();
            break;

        case $path === '/api/provider/location' && $method === 'PUT':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/ProviderController.php';
            $controller = new ProviderController($pdo);
            $controller->updateLocation();
            break;

        case $path === '/api/provider/statistics' && $method === 'GET':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/ProviderController.php';
            $controller = new ProviderController($pdo);
            $controller->getStatistics();
            break;

        // ==================== MUDANZA ENDPOINTS ====================
        case $path === '/api/moving/request' && $method === 'POST':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/MovingController.php';
            $controller = new MovingController($pdo);
            $controller->createRequest();
            break;

        case $path === '/api/moving/requests' && $method === 'GET':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/MovingController.php';
            $controller = new MovingController($pdo);
            $controller->getClientRequests();
            break;

        case $path === '/api/moving/movings' && $method === 'GET':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/MovingController.php';
            $controller = new MovingController($pdo);
            $controller->getClientMovings();
            break;

        case $path === '/api/moving/status' && $method === 'PUT':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/MovingController.php';
            $controller = new MovingController($pdo);
            $controller->updateMovingStatus();
            break;

        // ==================== ADMIN MUDANZAS/PROVEEDORES ====================
        case $path === '/api/admin/providers' && $method === 'GET':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/ProviderController.php';
            $controller = new ProviderController($pdo);
            $controller->listProviders();
            break;

        case $path === '/api/admin/providers/verification' && $method === 'PUT':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/ProviderController.php';
            $controller = new ProviderController($pdo);
            $controller->updateVerificationStatus();
            break;

        case $path === '/api/admin/moving/requests' && $method === 'GET':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/MovingController.php';
            $controller = new MovingController($pdo);
            $controller->getAllRequests();
            break;

        case $path === '/api/admin/moving/assign' && $method === 'POST':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/MovingController.php';
            $controller = new MovingController($pdo);
            $controller->assignProvider();
            break;

        case $path === '/api/provider/search/location' && $method === 'GET':
            if (!$pdo) Response::error('Servicio no disponible', 503);
            require_once __DIR__ . '/controllers/ProviderController.php';
            $controller = new ProviderController($pdo);
            $controller->searchByLocation();
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
