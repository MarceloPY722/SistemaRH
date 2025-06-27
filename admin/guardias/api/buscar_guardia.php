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
    $stmt = $conn->prepare("
        SELECT 
            gr.id,
            p.id as policia_id,
            p.legajo,
            p.nombre,
            p.apellido,
            g.nombre as grado
        FROM guardias_realizadas gr
        JOIN policias p ON gr.policia_id = p.id
        JOIN grados g ON p.grado_id = g.id
        WHERE DATE(gr.fecha_inicio) = ? AND gr.lugar_guardia_id = ?
    ");
    $stmt->bind_param("si", $fecha, $lugar_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($policia = $result->fetch_assoc()) {
        echo json_encode([
            'encontrado' => true,
            'policia' => $policia
        ]);
    } else {
        echo json_encode([
            'encontrado' => false
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Error en la consulta']);
}
?>