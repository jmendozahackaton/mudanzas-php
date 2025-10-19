<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Provider.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Password.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/AdminMiddleware.php';

class ProviderController {
    private $providerModel;
    private $userModel;

    public function __construct($pdo) {
        $this->providerModel = new Provider($pdo);
        $this->userModel = new User($pdo);
    }

    // Registrar proveedor
    public function register() {
        $input = json_decode(file_get_contents('php://input'), true);

        // Validaciones básicas
        $required = ['nombre', 'apellido', 'email', 'password', 'tipo_cuenta', 
                    'documento_identidad', 'licencia_conducir', 'categoria_licencia'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                Response::error("El campo $field es requerido", 400);
            }
        }

        // Verificar si el email ya existe
        if ($this->userModel->findByEmail($input['email'])) {
            Response::error('El email ya está registrado', 409);
        }

        $providerData = [
            'nombre' => $input['nombre'],
            'apellido' => $input['apellido'],
            'email' => $input['email'],
            'telefono' => $input['telefono'] ?? null,
            'password_hash' => Password::hash($input['password']),
            'tipo_cuenta' => $input['tipo_cuenta'],
            'razon_social' => $input['razon_social'] ?? null,
            'documento_identidad' => $input['documento_identidad'],
            'licencia_conducir' => $input['licencia_conducir'],
            'categoria_licencia' => $input['categoria_licencia'],
            'seguro_vehicular' => $input['seguro_vehicular'] ?? null,
            'radio_servicio' => $input['radio_servicio'] ?? 10,
            'tarifa_base' => $input['tarifa_base'] ?? 0,
            'tarifa_por_km' => $input['tarifa_por_km'] ?? 0,
            'tarifa_hora' => $input['tarifa_hora'] ?? 0,
            'tarifa_minima' => $input['tarifa_minima'] ?? 0,
            'metodos_pago_aceptados' => $input['metodos_pago_aceptados'] ?? []
        ];

        $proveedor = $this->providerModel->register($providerData);

        Response::success('Proveedor registrado exitosamente', [
            'proveedor' => $proveedor
        ]);
    }

    // Obtener perfil de proveedor
    public function getProfile() {
        $user = AuthMiddleware::authenticate();
        
        $proveedor = $this->providerModel->getByUserId($user['user_id']);
        if (!$proveedor) {
            Response::error('Proveedor no encontrado', 404);
        }

        Response::success('Perfil de proveedor obtenido', [
            'proveedor' => $proveedor
        ]);
    }

    // Actualizar perfil de proveedor
    public function updateProfile() {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true);

        $proveedor = $this->providerModel->getByUserId($user['user_id']);
        if (!$proveedor) {
            Response::error('Proveedor no encontrado', 404);
        }

        $updateData = [];
        $allowedFields = [
            'razon_social', 'documento_identidad', 'licencia_conducir', 
            'categoria_licencia', 'seguro_vehicular', 'poliza_seguro',
            'radio_servicio', 'tarifa_base', 'tarifa_por_km', 'tarifa_hora',
            'tarifa_minima', 'metodos_pago_aceptados'
        ];

        foreach ($input as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updateData[$field] = $value;
            }
        }

        if (empty($updateData)) {
            Response::error('No hay datos válidos para actualizar', 400);
        }

        if ($this->providerModel->updateProfile($proveedor['id'], $updateData)) {
            $proveedorActualizado = $this->providerModel->getByUserId($user['user_id']);
            Response::success('Perfil actualizado exitosamente', [
                'proveedor' => $proveedorActualizado
            ]);
        } else {
            Response::error('Error al actualizar el perfil', 500);
        }
    }

    // Actualizar disponibilidad
    public function updateAvailability() {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true);

        $proveedor = $this->providerModel->getByUserId($user['user_id']);
        if (!$proveedor) {
            Response::error('Proveedor no encontrado', 404);
        }

        $disponible = $input['disponible'] ?? true;
        $modoOcupado = $input['modo_ocupado'] ?? false;

        if ($this->providerModel->updateAvailability($proveedor['id'], $disponible, $modoOcupado)) {
            Response::success('Disponibilidad actualizada', [
                'disponible' => (bool)$disponible,
                'modo_ocupado' => (bool)$modoOcupado
            ]);
        } else {
            Response::error('Error al actualizar disponibilidad', 500);
        }
    }

    // Actualizar ubicación
    public function updateLocation() {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['lat']) || empty($input['lng'])) {
            Response::error('lat y lng son requeridos', 400);
        }

        $proveedor = $this->providerModel->getByUserId($user['user_id']);
        if (!$proveedor) {
            Response::error('Proveedor no encontrado', 404);
        }

        if ($this->providerModel->updateLocation($proveedor['id'], $input['lat'], $input['lng'])) {
            Response::success('Ubicación actualizada');
        } else {
            Response::error('Error al actualizar ubicación', 500);
        }
    }

    // Listar proveedores (admin)
    public function listProviders() {
        $admin = AdminMiddleware::check();
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $estadoVerificacion = $_GET['estado_verificacion'] ?? null;

        $proveedores = $this->providerModel->getAll($page, $limit, $estadoVerificacion);
        $total = $this->providerModel->countByVerificationStatus($estadoVerificacion);

        Response::success('Proveedores obtenidos', [
            'proveedores' => $proveedores,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    // Actualizar estado de verificación (admin)
    public function updateVerificationStatus() {
        $admin = AdminMiddleware::check();
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['proveedor_id']) || empty($input['estado'])) {
            Response::error('proveedor_id y estado son requeridos', 400);
        }

        $proveedor = $this->providerModel->getById($input['proveedor_id']);
        if (!$proveedor) {
            Response::error('Proveedor no encontrado', 404);
        }

        if ($this->providerModel->updateVerificationStatus(
            $input['proveedor_id'], 
            $input['estado'],
            $input['notas'] ?? null
        )) {
            $proveedorActualizado = $this->providerModel->getById($input['proveedor_id']);
            Response::success('Estado de verificación actualizado', [
                'proveedor' => $proveedorActualizado
            ]);
        } else {
            Response::error('Error al actualizar estado de verificación', 500);
        }
    }

    // Obtener estadísticas de proveedor
    public function getStatistics() {
        $user = AuthMiddleware::authenticate();
        
        $proveedor = $this->providerModel->getByUserId($user['user_id']);
        if (!$proveedor) {
            Response::error('Proveedor no encontrado', 404);
        }

        $estadisticas = $this->providerModel->getStatistics($proveedor['id']);

        Response::success('Estadísticas obtenidas', [
            'estadisticas' => $estadisticas
        ]);
    }

    // Buscar proveedores por ubicación
    public function searchByLocation() {
        $lat = $_GET['lat'] ?? null;
        $lng = $_GET['lng'] ?? null;
        $radius = $_GET['radius'] ?? 10;
        $limit = $_GET['limit'] ?? 10;

        if (empty($lat) || empty($lng)) {
            Response::error('lat y lng son requeridos', 400);
        }

        $proveedores = $this->providerModel->searchByLocation($lat, $lng, $radius, $limit);

        Response::success('Proveedores encontrados', [
            'proveedores' => $proveedores,
            'ubicacion' => ['lat' => $lat, 'lng' => $lng],
            'radio' => $radius
        ]);
    }
    
    // Convertir usuario a proveedor
    public function convertToProvider() {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true);

        // Verificar si ya es proveedor
        $existingProvider = $this->providerModel->getByUserId($user['user_id']);
        if ($existingProvider) {
            Response::error('El usuario ya es proveedor', 409);
        }

        // Validar campos requeridos
        $required = [
            'tipo_cuenta', 'documento_identidad', 
            'licencia_conducir', 'categoria_licencia'
        ];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                Response::error("El campo $field es requerido", 400);
            }
        }

        // Crear registro de proveedor
        $providerData = [
            'user_id' => $user['user_id'],
            'tipo_cuenta' => $input['tipo_cuenta'],
            'razon_social' => $input['razon_social'] ?? null,
            'documento_identidad' => $input['documento_identidad'],
            'licencia_conducir' => $input['licencia_conducir'],
            'categoria_licencia' => $input['categoria_licencia'],
            'seguro_vehicular' => $input['seguro_vehicular'] ?? null,
            'radio_servicio' => $input['radio_servicio'] ?? 10,
            'tarifa_base' => $input['tarifa_base'] ?? 0,
            'tarifa_por_km' => $input['tarifa_por_km'] ?? 0,
            'tarifa_hora' => $input['tarifa_hora'] ?? 0,
            'tarifa_minima' => $input['tarifa_minima'] ?? 0,
            'metodos_pago_aceptados' => $input['metodos_pago_aceptados'] ?? []
        ];

        $proveedor = $this->providerModel->createForExistingUser($providerData);

        Response::success('Usuario convertido a proveedor exitosamente', [
            'proveedor' => $proveedor
        ]);
    }
}
?>
