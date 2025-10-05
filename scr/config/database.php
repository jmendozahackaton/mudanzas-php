<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Obtener credenciales desde variables de entorno/Secret Manager
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db_name = getenv('DB_NAME') ?: 'plataforma_mudanzas';
        $this->username = getenv('DB_USERNAME') ?: 'jmendoza';
        $this->password = getenv('DB_PASSWORD') ?: '';
    }

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            throw new Exception("Error de conexión: " . $exception->getMessage());
        }
        
        return $this->conn;
    }
}
?>
