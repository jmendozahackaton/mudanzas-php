<?php
require_once __DIR__ . '/../models/User.php';

class UserController {
    private $userModel;

    public function __construct($db) {
        $this->userModel = new User($db);
    }

    public function getUsers() {
        try {
            // Para demostración, creamos la tabla si no existe
            $this->userModel->createTableIfNotExists();
            
            // Test de conexión
            $test = $this->userModel->testConnection();
            
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Controlador de usuarios funcionando',
                'database_test' => $test,
                'next_steps' => 'Implementar lógica completa de usuarios'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function createUser() {
        try {
            $data = json_decode(file_get_contents("php://input"));
            
            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'message' => 'Endpoint para crear usuario listo',
                'received_data' => $data
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}
?>
