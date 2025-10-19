<?php
class Provider {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Registrar proveedor
    public function register($providerData) {
        // Primero crear el usuario
        $userSql = "INSERT INTO usuarios (
            uuid, nombre, apellido, email, telefono, password_hash, 
            fecha_registro, estado, rol
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'activo', 'proveedor')";

        $userStmt = $this->pdo->prepare($userSql);
        $uuid = uniqid('user_', true);
        
        $userStmt->execute([
            $uuid,
            $providerData['nombre'],
            $providerData['apellido'],
            $providerData['email'],
            $providerData['telefono'],
            $providerData['password_hash']
        ]);

        $userId = $this->pdo->lastInsertId();

        // Luego crear el registro de proveedor
        $providerSql = "INSERT INTO proveedores (
            usuario_id, tipo_cuenta, razon_social, documento_identidad,
            licencia_conducir, categoria_licencia, seguro_vehicular,
            estado_verificacion, radio_servicio, tarifa_base, tarifa_por_km,
            tarifa_hora, tarifa_minima, metodos_pago_aceptados
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?, ?, ?, ?, ?)";

        $providerStmt = $this->pdo->prepare($providerSql);
        
        $providerStmt->execute([
            $userId,
            $providerData['tipo_cuenta'],
            $providerData['razon_social'] ?? null,
            $providerData['documento_identidad'],
            $providerData['licencia_conducir'],
            $providerData['categoria_licencia'],
            $providerData['seguro_vehicular'] ?? null,
            $providerData['radio_servicio'] ?? 10,
            $providerData['tarifa_base'] ?? 0,
            $providerData['tarifa_por_km'] ?? 0,
            $providerData['tarifa_hora'] ?? 0,
            $providerData['tarifa_minima'] ?? 0,
            json_encode($providerData['metodos_pago_aceptados'] ?? [])
        ]);

        return $this->getByUserId($userId);
    }

    // Obtener proveedor por ID de usuario
    public function getByUserId($userId) {
        $sql = "SELECT p.*, u.nombre, u.apellido, u.email, u.telefono, u.foto_perfil
                FROM proveedores p
                JOIN usuarios u ON p.usuario_id = u.id
                WHERE p.usuario_id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Obtener proveedor por ID
    public function getById($id) {
        $sql = "SELECT p.*, u.nombre, u.apellido, u.email, u.telefono, u.foto_perfil
                FROM proveedores p
                JOIN usuarios u ON p.usuario_id = u.id
                WHERE p.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Actualizar perfil de proveedor
    public function updateProfile($providerId, $updateData) {
        $allowedFields = [
            'razon_social', 'documento_identidad', 'licencia_conducir', 
            'categoria_licencia', 'seguro_vehicular', 'poliza_seguro',
            'radio_servicio', 'tarifa_base', 'tarifa_por_km', 'tarifa_hora',
            'tarifa_minima', 'metodos_pago_aceptados'
        ];
        
        $setParts = [];
        $params = [];

        foreach ($updateData as $field => $value) {
            if (in_array($field, $allowedFields)) {
                if ($field === 'metodos_pago_aceptados') {
                    $setParts[] = "$field = ?";
                    $params[] = json_encode($value);
                } else {
                    $setParts[] = "$field = ?";
                    $params[] = $value;
                }
            }
        }

        if (empty($setParts)) {
            return false;
        }

        $params[] = $providerId;
        $sql = "UPDATE proveedores SET " . implode(', ', $setParts) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // Actualizar estado de verificación
    public function updateVerificationStatus($providerId, $status, $notas = null) {
        $sql = "UPDATE proveedores 
                SET estado_verificacion = ?, fecha_verificacion = NOW()";
        
        $params = [$status];
        
        if ($notas) {
            $sql .= ", notas_verificacion = ?";
            $params[] = $notas;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $providerId;
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // Actualizar disponibilidad
    public function updateAvailability($providerId, $disponible, $modoOcupado = false) {
        $sql = "UPDATE proveedores 
                SET disponible = ?, modo_ocupado = ?, ultima_actualizacion = NOW() 
                WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$disponible, $modoOcupado, $providerId]);
    }

    // Actualizar ubicación
    public function updateLocation($providerId, $lat, $lng) {
        $sql = "UPDATE proveedores 
                SET ultima_ubicacion_lat = ?, ultima_ubicacion_lng = ?, 
                    ultima_actualizacion = NOW() 
                WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$lat, $lng, $providerId]);
    }

    // Listar proveedores (admin)
    public function getAll($page = 1, $limit = 10, $estadoVerificacion = null) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT p.*, u.nombre, u.apellido, u.email, u.telefono,
                       COUNT(v.id) as total_vehiculos,
                       (SELECT COUNT(*) FROM mudanzas m WHERE m.proveedor_id = p.id AND m.estado = 'completada') as servicios_completados
                FROM proveedores p
                JOIN usuarios u ON p.usuario_id = u.id
                LEFT JOIN vehiculos v ON p.id = v.proveedor_id AND v.estado = 'activo'
                WHERE 1=1";
        
        $params = [];
        
        if ($estadoVerificacion) {
            $sql .= " AND p.estado_verificacion = ?";
            $params[] = $estadoVerificacion;
        }
        
        $sql .= " GROUP BY p.id
                  ORDER BY p.fecha_registro DESC 
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $index => $param) {
            $stmt->bindValue($index + 1, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Contar proveedores por estado de verificación
    public function countByVerificationStatus($estado = null) {
        $sql = "SELECT COUNT(*) as total FROM proveedores";
        
        $params = [];
        if ($estado) {
            $sql .= " WHERE estado_verificacion = ?";
            $params[] = $estado;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    // Buscar proveedores por ubicación
    public function searchByLocation($lat, $lng, $radius = 10, $limit = 10) {
        $sql = "SELECT p.*, u.nombre, u.apellido, u.telefono,
                       (6371 * acos(cos(radians(?)) * cos(radians(p.ultima_ubicacion_lat)) 
                        * cos(radians(p.ultima_ubicacion_lng) - radians(?)) 
                        + sin(radians(?)) * sin(radians(p.ultima_ubicacion_lat)))) as distancia
                FROM proveedores p
                JOIN usuarios u ON p.usuario_id = u.id
                WHERE p.estado_verificacion = 'verificado'
                AND p.disponible = TRUE
                AND p.en_servicio = FALSE
                HAVING distancia <= ?
                ORDER BY distancia ASC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $lat);
        $stmt->bindValue(2, $lng);
        $stmt->bindValue(3, $lat);
        $stmt->bindValue(4, $radius);
        $stmt->bindValue(5, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener estadísticas de proveedor
    public function getStatistics($providerId) {
        $sql = "SELECT 
                COUNT(*) as total_servicios,
                SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as servicios_completados,
                SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) as servicios_cancelados,
                AVG(CASE WHEN estado = 'completada' THEN calificacion_proveedor ELSE NULL END) as puntuacion_promedio,
                SUM(CASE WHEN estado = 'completada' THEN costo_total ELSE 0 END) as ingresos_totales,
                SUM(CASE WHEN estado = 'completada' THEN comision_plataforma ELSE 0 END) as comision_acumulada
                FROM mudanzas 
                WHERE proveedor_id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$providerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Crear proveedor para usuario existente
    public function createForExistingUser($providerData) {
        $sql = "INSERT INTO proveedores (
            usuario_id, tipo_cuenta, razon_social, documento_identidad,
            licencia_conducir, categoria_licencia, seguro_vehicular,
            estado_verificacion, radio_servicio, tarifa_base, tarifa_por_km,
            tarifa_hora, tarifa_minima, metodos_pago_aceptados
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        
        $stmt->execute([
            $providerData['user_id'],
            $providerData['tipo_cuenta'],
            $providerData['razon_social'] ?? null,
            $providerData['documento_identidad'],
            $providerData['licencia_conducir'],
            $providerData['categoria_licencia'],
            $providerData['seguro_vehicular'] ?? null,
            $providerData['radio_servicio'] ?? 10,
            $providerData['tarifa_base'] ?? 0,
            $providerData['tarifa_por_km'] ?? 0,
            $providerData['tarifa_hora'] ?? 0,
            $providerData['tarifa_minima'] ?? 0,
            json_encode($providerData['metodos_pago_aceptados'] ?? [])
        ]);

        // Actualizar rol del usuario
        $updateUserSql = "UPDATE usuarios SET rol = 'proveedor' WHERE id = ?";
        $updateStmt = $this->pdo->prepare($updateUserSql);
        $updateStmt->execute([$providerData['user_id']]);

        return $this->getByUserId($providerData['user_id']);
    }
}
?>
