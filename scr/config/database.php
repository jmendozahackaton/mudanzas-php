<?php
function getDatabaseConfig() {
    // Obtener configuración de variables de entorno
    $config = [
        'host' => getenv('DB_HOST') ?: ':/cloudsql/mudanzas-app-474101:us-central1:mudanzas-mysql',
        'dbname' => getenv('DB_NAME') ?: 'plataforma_mudanzas',
        'username' => getenv('DB_USER') ?: '',
        'password' => getenv('DB_PASSWORD') ?: '',
    ];
    
    // Log para debugging (solo en desarrollo)
    if (getenv('ENVIRONMENT') === 'development') {
        error_log("DB Config - Host: " . $config['host']);
        error_log("DB Config - Database: " . $config['dbname']);
        error_log("DB Config - User: " . $config['username']);
        error_log("DB Config - Password length: " . strlen($config['password']));
    }
    
    return $config;
}

function connectToDatabase() {
    $config = getDatabaseConfig();
    
    try {
        // Para Cloud SQL usando socket de Unix
        $dsn = "mysql:unix_socket={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => 10,
        ]);
        
        // Verificar conexión
        $pdo->query("SELECT 1")->fetch();
        
        if (getenv('ENVIRONMENT') === 'development') {
            error_log("Conexión a la base de datos establecida exitosamente");
        }
        
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Error de conexión a la base de datos: " . $e->getMessage());
        error_log("DSN utilizado: mysql:unix_socket={$config['host']};dbname={$config['dbname']}");
        throw new Exception("Error al conectar con la base de datos: " . $e->getMessage());
    }
}

// Función para verificar el estado de la base de datos
function checkDatabaseStatus() {
    try {
        $pdo = connectToDatabase();
        $stmt = $pdo->query("SELECT NOW() as server_time, VERSION() as mysql_version");
        $status = $stmt->fetch();
        
        return [
            'status' => 'connected',
            'server_time' => $status['server_time'],
            'mysql_version' => $status['mysql_version']
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}
?>
