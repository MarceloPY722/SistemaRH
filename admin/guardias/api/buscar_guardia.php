<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

require_once '../../../cnx/db_connect.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$fecha = $input['fecha'] ?? null;
$lugar_id = $input['lugar_id'] ?? null;

if (!$fecha || !$lugar_id) {
    echo json_encode(['error' => 'Fecha y lugar requeridos']);
    exit();
}

try {
    // La tabla guardias_realizadas ha sido eliminada
    // Retornando que no se encontró guardia hasta que se implemente nueva funcionalidad
    echo json_encode([
        'encontrado' => false,
        'message' => 'Funcionalidad temporalmente deshabilitada - tabla guardias_realizadas eliminada'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Error en la consulta']);
}
?>