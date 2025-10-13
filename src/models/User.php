<?php
class User {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Crear usuario
    public function create($userData) {
        $sql = "INSERT INTO usuarios (
            uuid, nombre, apellido, email, telefono, password_hash, 
            fecha_registro, estado, rol
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'activo', 'cliente')";

        $stmt = $this->pdo->prepare($sql);
        $uuid = uniqid('user_', true);
        
        $stmt->execute([
            $uuid,
            $userData['nombre'],
            $userData['apellido'],
            $userData['email'],
            $userData['telefono'] ?? null,
            $userData['password_hash']
        ]);

        return $this->findByEmail($userData['email']);
    }

    // Buscar por email
    public function findByEmail($email) {
        $sql = "SELECT * FROM usuarios WHERE email = ? AND estado = 'activo'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Buscar por ID
    public function findById($id) {
        $sql = "SELECT id, uuid, nombre, apellido, email, telefono, foto_perfil, fecha_registro, ultimo_acceso, estado, rol 
                FROM usuarios WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Actualizar perfil
    public function update($id, $userData) {
        $allowedFields = ['nombre', 'apellido', 'email', 'telefono', 'password_hash', 'foto_perfil'];
        $updates = [];
        $params = [];

        foreach ($userData as $field => $value) {
            if (in_array($field, $allowedFields) && $value !== null) {
                $updates[] = "$field = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE usuarios SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // Listar usuarios (para admin)
    public function getAll($page = 1, $limit = 10) {
        // ✅ ASEGURAR QUE SON ENTEROS
        $page = (int)$page;
        $limit = (int)$limit;
        
        $offset = ($page - 1) * $limit;
        
        error_log("📊 SQL - LIMIT: $limit, OFFSET: $offset"); // Para debug
        
        $sql = "SELECT id, uuid, nombre, apellido, email, telefono, foto_perfil, 
                    fecha_registro, ultimo_acceso, estado, rol 
                FROM usuarios 
                ORDER BY fecha_registro DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        
        // ✅ ESPECIFICAR EXPLÍCITAMENTE EL TIPO DE DATO
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        error_log("🎯 Tipo de datos:");
        error_log("🎯 limit type: " . gettype($limit));
        error_log("🎯 offset type: " . gettype($offset));
        error_log("🎯 SQL final: " . $sql);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Contar total de usuarios
    public function count() {
        $sql = "SELECT COUNT(*) as total FROM usuarios";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    // Actualizar último acceso
    public function updateLastAccess($id) {
        $sql = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }

    // Cambiar estado de usuario (admin)
    public function changeStatus($id, $status) {
        $sql = "UPDATE usuarios SET estado = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, $id]);
    }

    // Cambiar rol de usuario (admin)
    public function changeRole($id, $role) {
        $sql = "UPDATE usuarios SET rol = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$role, $id]);
    }

    // Buscar usuarios
    public function search($searchTerm, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT id, uuid, nombre, apellido, email, telefono, foto_perfil, 
                    fecha_registro, ultimo_acceso, estado, rol 
                FROM usuarios 
                WHERE nombre LIKE ? OR apellido LIKE ? OR email LIKE ? OR telefono LIKE ?
                ORDER BY fecha_registro DESC 
                LIMIT ? OFFSET ?";  // ← Cambiar a parámetros posicionales
        
        $stmt = $this->pdo->prepare($sql);
        $searchPattern = "%$searchTerm%";
        
        // Pasar todos los parámetros en el execute
        $stmt->execute([
            $searchPattern, 
            $searchPattern, 
            $searchPattern, 
            $searchPattern,
            $limit,
            $offset
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Contar resultados de búsqueda
    public function countSearch($searchTerm) {
        $sql = "SELECT COUNT(*) as total FROM usuarios 
                WHERE nombre LIKE ? OR apellido LIKE ? OR email LIKE ? OR telefono LIKE ?";
        
        $stmt = $this->pdo->prepare($sql);
        $searchPattern = "%$searchTerm%";
        $stmt->execute([$searchPattern, $searchPattern, $searchPattern, $searchPattern]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    // Obtener usuario por ID
    public function getById($id) {
        $sql = "SELECT id, uuid, nombre, apellido, email, telefono, foto_perfil, 
                       fecha_registro, ultimo_acceso, estado, rol 
                FROM usuarios 
                WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Obtener usuario por email
    public function getByEmail($email) {
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Actualizar perfil de usuario
    public function updateProfile($userId, $data) {
        $allowedFields = ['nombre', 'apellido', 'email', 'telefono', 'rol', 'estado'];
        $setParts = [];
        $params = [];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $setParts[] = "$field = ?";
                $params[] = $value;
            }
        }

        if (empty($setParts)) {
            return false;
        }

        $params[] = $userId; // Para el WHERE

        $sql = "UPDATE usuarios SET " . implode(', ', $setParts) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

}
?>
