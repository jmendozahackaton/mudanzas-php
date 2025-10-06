<?php
// Redirigir a /src/ o mostrar contenido directo
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success', 
    'message' => 'API Root - Redirige a /src/',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
