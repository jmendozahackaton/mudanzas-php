<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $this->host = getenv('DB_HOST');
        $this->db_name = getenv('DB_NAME'); 
        $this->username = getenv('DB_USERNAME');
        $this->password = getenv('DB_PASSWORD');
        
        // Debug
        error_log("=== DB CONFIG ===");
        error_log("Host: " . $this->host);
        error_log("DB Name: " . $this->db_name);
        error_log("Username: " . $this->username);
        error_log("Password: " . ($this->password ? "***SET***" : "NOT SET"));
    }

    public function getConnection() {
        $this->conn = null;
        
        try {
            // Para Cloud SQL usa socket UNIX
            $dsn = "mysql:unix_socket=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8";
            
            error_log("DSN: " . $dsn);
            
            $this->conn = new PDO(
                $dsn,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_PERSISTENT => false,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            
            error_log("✅ Conexión exitosa a la base de datos");
            
        } catch(PDOException $exception) {
            error_log("❌ Error PDO: " . $exception->getMessage());
            throw new Exception("Error de conexión: " . $exception->getMessage());
        }
        
        return $this->conn;
    }
}
?>
