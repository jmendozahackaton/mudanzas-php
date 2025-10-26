<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Moving.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/AdminMiddleware.php';

class MovingController {
    private $movingModel;
    private $userModel;

    public function __construct($pdo) {
        $this->movingModel = new Moving($pdo);
        $this->userModel = new User($pdo);
    }

    public function createRequest() {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true);

        // Validaciones
        if (empty($input['direccion_origen']) || empty($input['direccion_destino'])) {
            Response::error('Dirección de origen y destino son requeridas', 400);
        }

        if (empty($input['fecha_programada'])) {
            Response::error('Fecha programada es requerida', 400);
        }

        try {
            // ✅ ASEGURAR QUE EL CLIENTE EXISTA
            $clienteId = $this->userModel->ensureClientExists($user['user_id']);
            
            // Preparar datos para la solicitud
            $requestData = [
                'cliente_id' => $clienteId,
                'direccion_origen' => $input['direccion_origen'],
                'direccion_destino' => $input['direccion_destino'],
                'lat_origen' => $input['lat_origen'] ?? null,
                'lng_origen' => $input['lng_origen'] ?? null,
                'lat_destino' => $input['lat_destino'] ?? null,
                'lng_destino' => $input['lng_destino'] ?? null,
                'descripcion_items' => $input['descripcion_items'] ?? '',
                'tipo_items' => $input['tipo_items'] ?? [],
                'volumen_estimado' => $input['volumen_estimado'] ?? 0,
                'servicios_adicionales' => $input['servicios_adicionales'] ?? [],
                'urgencia' => $input['urgencia'] ?? 'normal',
                'fecha_programada' => $input['fecha_programada'],
                'cotizacion_estimada' => $input['cotizacion_estimada'] ?? 0,
                'distancia_estimada' => $input['distancia_estimada'] ?? 0,
                'tiempo_estimado' => $input['tiempo_estimado'] ?? 0
            ];

            // Crear la solicitud
            $solicitud = $this->movingModel->createRequest($requestData);
            
            Response::success('Solicitud de mudanza creada exitosamente', [
                'solicitud' => $solicitud
            ]);
            
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    // Obtener solicitudes del cliente
    public function getClientRequests() {
        $user = AuthMiddleware::authenticate();
        
        try {
            $clienteId = $this->userModel->ensureClientExists($user['user_id']);
            
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            
            $requests = $this->movingModel->getClientRequests($clienteId, $page, $limit);
            
            Response::success('Solicitudes obtenidas', [
                'solicitudes' => $requests
            ]);
            
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    // Obtener todas las solicitudes (admin)
    public function getAllRequests() {
        $admin = AdminMiddleware::check();
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $estado = $_GET['estado'] ?? null;

        $solicitudes = $this->movingModel->getAllRequests($page, $limit, $estado);
        $total = $this->movingModel->countRequestsByStatus($estado);

        Response::success('Solicitudes obtenidas', [
            'solicitudes' => $solicitudes,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    // Asignar proveedor a solicitud
    public function assignProvider() {
        $admin = AdminMiddleware::check();
        $input = json_decode(file_get_contents('php://input'), true);
        error_log("📦 Assign Provider - Input recibido: " . json_encode($input));
        if (empty($input['solicitud_id']) || empty($input['proveedor_id'])) {
            Response::error('solicitud_id y proveedor_id son requeridos', 400);
        }

        // Verificar que la solicitud existe y está pendiente
        $solicitud = $this->movingModel->getRequestById($input['solicitud_id']);
        if (!$solicitud) {
            error_log("❌ Solicitud no encontrada: " . $input['solicitud_id']);
            Response::error('Solicitud no encontrada', 404);
        }
        error_log("📦 Solicitud encontrada - Estado: " . $solicitud['estado']);
        if ($solicitud['estado'] !== 'pendiente') {
            error_log("❌ Solicitud ya asignada - Estado actual: " . $solicitud['estado']);
            Response::error('La solicitud ya ha sido asignada', 400);
        }

        // Crear la mudanza
        $movingData = [
            'solicitud_id' => $input['solicitud_id'],
            'cliente_id' => $solicitud['cliente_id'],
            'proveedor_id' => $input['proveedor_id'],
            'costo_base' => $input['costo_base'] ?? $solicitud['cotizacion_estimada'],
            'costo_total' => $input['costo_total'] ?? $solicitud['cotizacion_estimada'],
            'comision_plataforma' => $input['comision_plataforma'] ?? 0
        ];
        error_log("📦 Datos para crear mudanza: " . json_encode($movingData));
        $mudanza = $this->movingModel->createMoving($movingData);
        error_log("📦 Resultado de createMoving: " . json_encode($mudanza));
        if (!$mudanza) {
            error_log("❌ createMoving retornó false");
            Response::error('Error al crear la mudanza en la base de datos', 500);
        }

        Response::success('Proveedor asignado exitosamente', [
            'mudanza' => $mudanza
        ]);
    }

    // Obtener mudanzas del cliente
    public function getClientMovings() {
        $user = AuthMiddleware::authenticate();
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

        // Obtener cliente_id del usuario
        $cliente = $this->userModel->getClienteByUserId($user['user_id']);
        if (!$cliente) {
            Response::error('Cliente no encontrado', 404);
        }

        $mudanzas = $this->movingModel->getClientMovings($cliente['id'], $page, $limit);
        $total = $this->movingModel->countMovingsByStatus();

        Response::success('Mudanzas obtenidas', [
            'mudanzas' => $mudanzas,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    // Actualizar estado de mudanza
    public function updateMovingStatus() {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['mudanza_id']) || empty($input['estado'])) {
            Response::error('mudanza_id y estado son requeridos', 400);
        }

        // Verificar permisos (solo proveedor asignado puede actualizar)
        $mudanza = $this->movingModel->getMovingById($input['mudanza_id']);
        if (!$mudanza) {
            Response::error('Mudanza no encontrada', 404);
        }

        // Obtener proveedor del usuario
        $proveedor = $this->userModel->getProveedorByUserId($user['user_id']);
        if (!$proveedor || $proveedor['id'] != $mudanza['proveedor_id']) {
            Response::error('No tienes permisos para actualizar esta mudanza', 403);
        }

        if ($this->movingModel->updateMovingStatus($input['mudanza_id'], $input['estado'])) {
            $mudanzaActualizada = $this->movingModel->getMovingById($input['mudanza_id']);
            Response::success('Estado de mudanza actualizado', [
                'mudanza' => $mudanzaActualizada
            ]);
        } else {
            Response::error('Error al actualizar el estado', 500);
        }
    }
}
?>
