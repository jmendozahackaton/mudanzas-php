<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Password.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Upload.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/AdminMiddleware.php';

class UserController {
    private $userModel;

    public function __construct($pdo) {
        $this->userModel = new User($pdo);
    }

    // 1. Registro de Usuario
    public function register() {
        $input = json_decode(file_get_contents('php://input'), true);

        // Validación básica
        if (empty($input['nombre']) || empty($input['apellido']) || 
            empty($input['email']) || empty($input['password'])) {
            Response::error('Todos los campos son requeridos', 400);
        }

        // Verificar si el email ya existe
        if ($this->userModel->findByEmail($input['email'])) {
            Response::error('El email ya está registrado', 409);
        }

        // Crear usuario
        $userData = [
            'nombre' => $input['nombre'],
            'apellido' => $input['apellido'],
            'email' => $input['email'],
            'telefono' => $input['telefono'] ?? null,
            'password_hash' => Password::hash($input['password'])
        ];

        $user = $this->userModel->create($userData);

        // Generar JWT
        $token = JWT::generate([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'rol' => $user['rol']
        ]);

        Response::success('Usuario registrado exitosamente', [
            'user' => [
                'id' => $user['id'],
                'nombre' => $user['nombre'],
                'apellido' => $user['apellido'],
                'email' => $user['email'],
                'rol' => $user['rol']
            ],
            'token' => $token
        ]);
    }

    // 2. Login de Usuarios
    public function login() {
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['email']) || empty($input['password'])) {
            Response::error('Email y password son requeridos', 400);
        }

        $user = $this->userModel->findByEmail($input['email']);
        if (!$user || !Password::verify($input['password'], $user['password_hash'])) {
            Response::error('Credenciales inválidas', 401);
        }

        // Actualizar último acceso
        $this->userModel->updateLastAccess($user['id']);

        // Generar JWT
        $token = JWT::generate([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'rol' => $user['rol']
        ]);

        Response::success('Login exitoso', [
            'user' => [
                'id' => $user['id'],
                'nombre' => $user['nombre'],
                'apellido' => $user['apellido'],
                'email' => $user['email'],
                'telefono' => $user['telefono'],
                'foto_perfil' => $user['foto_perfil'],
                'rol' => $user['rol']
            ],
            'token' => $token
        ]);
    }

    // 3. Actualizar Perfil
    public function updateProfile() {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true);

        $updateData = [];
        if (isset($input['nombre'])) $updateData['nombre'] = $input['nombre'];
        if (isset($input['apellido'])) $updateData['apellido'] = $input['apellido'];
        if (isset($input['email'])) $updateData['email'] = $input['email'];
        if (isset($input['telefono'])) $updateData['telefono'] = $input['telefono'];
        if (isset($input['password'])) $updateData['password_hash'] = Password::hash($input['password']);

        // Manejar upload de imagen
        if (isset($_FILES['foto_perfil'])) {
            try {
                $updateData['foto_perfil'] = Upload::handleProfileImage($_FILES['foto_perfil']);
            } catch (Exception $e) {
                Response::error($e->getMessage(), 400);
            }
        }

        if ($this->userModel->update($user['user_id'], $updateData)) {
            $updatedUser = $this->userModel->findById($user['user_id']);
            Response::success('Perfil actualizado exitosamente', ['user' => $updatedUser]);
        } else {
            Response::error('Error al actualizar el perfil', 500);
        }
    }

    // 4. Obtener perfil actual
    public function getProfile() {
        $user = AuthMiddleware::authenticate();
        $userData = $this->userModel->findById($user['user_id']);
        
        if ($userData) {
            Response::success('Perfil obtenido', ['user' => $userData]);
        } else {
            Response::error('Usuario no encontrado', 404);
        }
    }

    // 5. Listar usuarios (Admin)
    public function listUsers() {
        $admin = AdminMiddleware::check();
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 10;

        $users = $this->userModel->getAll($page, $limit);
        $total = $this->userModel->count();

        Response::success('Lista de usuarios', [
            'users' => $users,
            'pagination' => [
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    // 6. Cambiar estado de usuario (Admin)
    public function changeUserStatus() {
        $admin = AdminMiddleware::check();
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['user_id']) || empty($input['estado'])) {
            Response::error('user_id y estado son requeridos', 400);
        }

        if ($this->userModel->changeStatus($input['user_id'], $input['estado'])) {
            Response::success('Estado del usuario actualizado');
        } else {
            Response::error('Error al actualizar el estado', 500);
        }
    }

    // 7. Cambiar rol de usuario (Admin)
    public function changeUserRole() {
        $admin = AdminMiddleware::check();
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['user_id']) || empty($input['rol'])) {
            Response::error('user_id y rol son requeridos', 400);
        }

        if ($this->userModel->changeRole($input['user_id'], $input['rol'])) {
            Response::success('Rol del usuario actualizado');
        } else {
            Response::error('Error al actualizar el rol', 500);
        }
    }
}
?>
