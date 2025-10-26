<?php
class Moving {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Crear solicitud de mudanza
    public function createRequest($requestData) {
        $sql = "INSERT INTO solicitudes_mudanza (
            cliente_id, codigo_solicitud, direccion_origen, direccion_destino,
            lat_origen, lng_origen, lat_destino, lng_destino,
            descripcion_items, tipo_items, volumen_estimado, servicios_adicionales,
            urgencia, fecha_programada, estado, cotizacion_estimada,
            distancia_estimada, tiempo_estimado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $codigo = 'MZ-' . date('Ymd') . '-' . uniqid();
        
        $stmt->execute([
            $requestData['cliente_id'],
            $codigo,
            $requestData['direccion_origen'],
            $requestData['direccion_destino'],
            $requestData['lat_origen'] ?? null,
            $requestData['lng_origen'] ?? null,
            $requestData['lat_destino'] ?? null,
            $requestData['lng_destino'] ?? null,
            $requestData['descripcion_items'] ?? '',
            json_encode($requestData['tipo_items'] ?? []),
            $requestData['volumen_estimado'] ?? 0,
            json_encode($requestData['servicios_adicionales'] ?? []),
            $requestData['urgencia'] ?? 'normal',
            $requestData['fecha_programada'],
            $requestData['cotizacion_estimada'] ?? 0,
            $requestData['distancia_estimada'] ?? 0,
            $requestData['tiempo_estimado'] ?? 0
        ]);

        return $this->getRequestById($this->pdo->lastInsertId());
    }

    // Obtener solicitud por ID
    public function getRequestById($id) {
        $sql = "SELECT sm.*, 
                       c.usuario_id as cliente_usuario_id,
                       u.nombre as cliente_nombre,
                       u.apellido as cliente_apellido,
                       u.telefono as cliente_telefono
                FROM solicitudes_mudanza sm
                JOIN clientes c ON sm.cliente_id = c.id
                JOIN usuarios u ON c.usuario_id = u.id
                WHERE sm.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Listar solicitudes de un cliente
    public function getClientRequests($clienteId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT sm.*, 
                       COUNT(m.id) as tiene_mudanza_asignada
                FROM solicitudes_mudanza sm
                LEFT JOIN mudanzas m ON sm.id = m.solicitud_id
                WHERE sm.cliente_id = ?
                GROUP BY sm.id
                ORDER BY sm.fecha_solicitud DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $clienteId);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Listar todas las solicitudes (admin)
    public function getAllRequests($page = 1, $limit = 10, $estado = null) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT sm.*, 
                       u.nombre as cliente_nombre,
                       u.apellido as cliente_apellido,
                       u.email as cliente_email,
                       COUNT(m.id) as tiene_mudanza_asignada
                FROM solicitudes_mudanza sm
                JOIN clientes c ON sm.cliente_id = c.id
                JOIN usuarios u ON c.usuario_id = u.id
                LEFT JOIN mudanzas m ON sm.id = m.solicitud_id
                WHERE 1=1";
        
        $params = [];
        
        if ($estado) {
            $sql .= " AND sm.estado = ?";
            $params[] = $estado;
        }
        
        $sql .= " GROUP BY sm.id
                  ORDER BY sm.fecha_solicitud DESC 
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        
        // Bind parameters
        foreach ($params as $index => $param) {
            $stmt->bindValue($index + 1, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Actualizar estado de solicitud
    public function updateRequestStatus($id, $estado) {
        $sql = "UPDATE solicitudes_mudanza SET estado = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$estado, $id]);
    }

    // Buscar proveedores disponibles para una solicitud
    public function findAvailableProviders($solicitudId) {
        $solicitud = $this->getRequestById($solicitudId);
        if (!$solicitud) return [];

        $sql = "SELECT p.*, u.nombre, u.apellido, u.telefono,
                       v.tipo as tipo_vehiculo, v.capacidad_carga,
                       (6371 * acos(cos(radians(?)) * cos(radians(p.ultima_ubicacion_lat)) 
                        * cos(radians(p.ultima_ubicacion_lng) - radians(?)) 
                        + sin(radians(?)) * sin(radians(p.ultima_ubicacion_lat)))) as distancia
                FROM proveedores p
                JOIN usuarios u ON p.usuario_id = u.id
                LEFT JOIN vehiculos v ON p.id = v.proveedor_id AND v.estado = 'activo'
                WHERE p.estado_verificacion = 'verificado'
                AND p.disponible = TRUE
                AND p.en_servicio = FALSE
                AND (p.radio_servicio >= (6371 * acos(cos(radians(?)) * cos(radians(p.ultima_ubicacion_lat)) 
                    * cos(radians(p.ultima_ubicacion_lng) - radians(?)) 
                    + sin(radians(?)) * sin(radians(p.ultima_ubicacion_lat)))) OR p.radio_servicio = 0)
                HAVING distancia <= p.radio_servicio OR p.radio_servicio = 0
                ORDER BY distancia ASC
                LIMIT 10";

        $stmt = $this->pdo->prepare($sql);
        $params = [
            $solicitud['lat_origen'], $solicitud['lng_origen'], $solicitud['lat_origen'],
            $solicitud['lat_origen'], $solicitud['lng_origen'], $solicitud['lat_origen']
        ];
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Crear mudanza a partir de solicitud
    public function createMoving($movingData) {
        try {
            $sql = "INSERT INTO mudanzas (
                solicitud_id, cliente_id, proveedor_id, codigo_mudanza,
                estado, fecha_solicitud, costo_base, costo_total, comision_plataforma
            ) VALUES (?, ?, ?, ?, 'asignada', NOW(), ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $codigo = 'MOV-' . date('Ymd') . '-' . uniqid();
            
            $success = $stmt->execute([
                $movingData['solicitud_id'],
                $movingData['cliente_id'],
                $movingData['proveedor_id'],
                $codigo,
                $movingData['costo_base'],
                $movingData['costo_total'],
                $movingData['comision_plataforma']
            ]);

            if (!$success) {
                error_log("❌ Error en execute: " . implode(", ", $stmt->errorInfo()));
                return false;
            }

            // Obtener el ID de forma más robusta
            $movingId = $this->pdo->lastInsertId();
            
            if (!$movingId || $movingId == 0) {
                error_log("❌ lastInsertId retornó: " . $movingId);
                
                // Intentar obtener el ID de otra forma
                $sql = "SELECT id FROM mudanzas WHERE solicitud_id = ? AND proveedor_id = ? ORDER BY id DESC LIMIT 1";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$movingData['solicitud_id'], $movingData['proveedor_id']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    $movingId = $result['id'];
                } else {
                    error_log("❌ No se pudo obtener el ID de la mudanza insertada");
                    return false;
                }
            }

            // Actualizar estado de la solicitud
            $updateSuccess = $this->updateRequestStatus($movingData['solicitud_id'], 'asignada');
            if (!$updateSuccess) {
                error_log("⚠️ No se pudo actualizar el estado de la solicitud");
            }

            // Obtener la mudanza creada
            $mudanza = $this->getMovingById($movingId);
            
            if (!$mudanza) {
                error_log("❌ No se pudo obtener la mudanza con ID: " . $movingId);
                return false;
            }

            error_log("✅ Mudanza creada exitosamente - ID: " . $movingId);
            return $mudanza;
            
        } catch (Exception $e) {
            error_log("❌ Excepción en createMoving: " . $e->getMessage());
            return false;
        }
    }

    // Obtener mudanza por ID
    public function getMovingById($id) {
        $sql = "SELECT m.*, 
                       sm.direccion_origen, sm.direccion_destino,
                       c.usuario_id as cliente_usuario_id,
                       uc.nombre as cliente_nombre, uc.apellido as cliente_apellido,
                       p.usuario_id as proveedor_usuario_id,
                       up.nombre as proveedor_nombre, up.apellido as proveedor_apellido
                FROM mudanzas m
                JOIN solicitudes_mudanza sm ON m.solicitud_id = sm.id
                JOIN clientes c ON m.cliente_id = c.id
                JOIN usuarios uc ON c.usuario_id = uc.id
                JOIN proveedores p ON m.proveedor_id = p.id
                JOIN usuarios up ON p.usuario_id = up.id
                WHERE m.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Actualizar estado de mudanza
    public function updateMovingStatus($id, $estado) {
        $sql = "UPDATE mudanzas SET estado = ?";
        
        $params = [$estado];
        
        if ($estado === 'en_camino') {
            $sql .= ", fecha_asignacion = NOW()";
        } elseif ($estado === 'en_proceso') {
            $sql .= ", fecha_inicio = NOW()";
        } elseif ($estado === 'completada') {
            $sql .= ", fecha_completacion = NOW()";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $id;
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // Obtener mudanzas de un cliente
    public function getClientMovings($clienteId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT m.*, 
                       sm.direccion_origen, sm.direccion_destino,
                       up.nombre as proveedor_nombre, up.apellido as proveedor_apellido
                FROM mudanzas m
                JOIN solicitudes_mudanza sm ON m.solicitud_id = sm.id
                JOIN proveedores p ON m.proveedor_id = p.id
                JOIN usuarios up ON p.usuario_id = up.id
                WHERE m.cliente_id = ?
                ORDER BY m.fecha_solicitud DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $clienteId);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener mudanzas de un proveedor
    public function getProviderMovings($proveedorId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT m.*, 
                       sm.direccion_origen, sm.direccion_destino,
                       uc.nombre as cliente_nombre, uc.apellido as cliente_apellido
                FROM mudanzas m
                JOIN solicitudes_mudanza sm ON m.solicitud_id = sm.id
                JOIN clientes c ON m.cliente_id = c.id
                JOIN usuarios uc ON c.usuario_id = uc.id
                WHERE m.proveedor_id = ?
                ORDER BY m.fecha_solicitud DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $proveedorId);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Contar solicitudes por estado
    public function countRequestsByStatus($estado = null) {
        $sql = "SELECT COUNT(*) as total FROM solicitudes_mudanza";
        
        $params = [];
        if ($estado) {
            $sql .= " WHERE estado = ?";
            $params[] = $estado;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    // Contar mudanzas por estado
    public function countMovingsByStatus($estado = null) {
        $sql = "SELECT COUNT(*) as total FROM mudanzas";
        
        $params = [];
        if ($estado) {
            $sql .= " WHERE estado = ?";
            $params[] = $estado;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
}
?>
