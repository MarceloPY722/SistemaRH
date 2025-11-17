<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

require_once '../../../cnx/db_connect.php';

header('Content-Type: application/json');

$fecha = $_GET['fecha'] ?? null;

if (!$fecha) {
    echo json_encode(['error' => 'Fecha requerida']);
    exit();
}

try {
    // La tabla guardias_realizadas ha sido eliminada
    // Retornando array vacío hasta que se implemente nueva funcionalidad
    echo json_encode([
        'success' => true,
        'guardias' => [],
        'message' => 'Funcionalidad temporalmente deshabilitada - tabla guardias_realizadas eliminada'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Error en la consulta: ' . $e->getMessage()]);
}
?>