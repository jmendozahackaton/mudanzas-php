<?php
class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $name;
    public $email;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Método para probar la conexión y estructura
    public function testConnection() {
        try {
            $query = "SELECT 1 as test";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            throw new Exception("Error en test de conexión: " . $e->getMessage());
        }
    }

    // Crear tabla de usuarios si no existe (para pruebas)
    public function createTableIfNotExists() {
        $query = "
        CREATE TABLE IF NOT EXISTS " . $this->table_name . " (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute();
    }
}
?>
