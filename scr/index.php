<?php
header('Content-Type: application/json');

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

if ($path === '/api/health' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'status' => 'healthy',
        'service' => 'API Mudanzas',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Para cualquier otra ruta
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'message' => 'API funcionando',
    'endpoint' => $path,
    'available' => '/api/health'
]);
?>
