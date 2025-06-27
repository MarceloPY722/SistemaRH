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
$fecha_inicio = $input['fecha_inicio'] ?? null;
$fecha_fin = $input['fecha_fin'] ?? null;

if (!$fecha_inicio || !$fecha_fin) {
    echo json_encode(['error' => 'Fechas requeridas']);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM guardias_semanales 
        WHERE fecha_inicio = ? AND fecha_fin = ?
    ");
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'existe' => $result['total'] > 0
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Error en la consulta']);
}
?>