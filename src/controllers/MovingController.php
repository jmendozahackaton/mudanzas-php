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

    // Crear solicitud de mudanza
    public function createRequest() {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true);

        // Validaciones básicas
        $required = ['direccion_origen', 'direccion_destino', 'fecha_programada'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                Response::error("El campo $field es requerido", 400);
            }
        }

        // Obtener cliente_id del usuario
        $cliente = $this->userModel->getClienteByUserId($user['user_id']);
        if (!$cliente) {
            Response::error('Cliente no encontrado', 404);
        }

        $requestData = [
            'cliente_id' => $cliente['id'],
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

        $solicitud = $this->movingModel->createRequest($requestData);

        Response::success('Solicitud de mudanza creada exitosamente', [
            'solicitud' => $solicitud
        ]);
    }

    // Obtener solicitudes del cliente
    public function getClientRequests() {
        $user = AuthMiddleware::authenticate();
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

        // Obtener cliente_id del usuario
        $cliente = $this->userModel->getClienteByUserId($user['user_id']);
        if (!$cliente) {
            Response::error('Cliente no encontrado', 404);
        }

        $solicitudes = $this->movingModel->getClientRequests($cliente['id'], $page, $limit);
        $total = $this->movingModel->countRequestsByStatus();

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

        if (empty($input['solicitud_id']) || empty($input['proveedor_id'])) {
            Response::error('solicitud_id y proveedor_id son requeridos', 400);
        }

        // Verificar que la solicitud existe y está pendiente
        $solicitud = $this->movingModel->getRequestById($input['solicitud_id']);
        if (!$solicitud) {
            Response::error('Solicitud no encontrada', 404);
        }

        if ($solicitud['estado'] !== 'pendiente') {
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

        $mudanza = $this->movingModel->createMoving($movingData);

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
