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
    $stmt = $conn->prepare("
        SELECT 
            gr.id,
            gr.fecha_inicio,
            gr.fecha_fin,
            gr.lugar_guardia_id,
            lg.nombre as lugar_nombre,
            p.id as policia_id,
            p.legajo,
            p.nombre,
            p.apellido,
            p.cin,
            g.nombre as grado,
            r.nombre as region
        FROM guardias_realizadas gr
        JOIN lugares_guardias lg ON gr.lugar_guardia_id = lg.id
        JOIN policias p ON gr.policia_id = p.id
        JOIN grados g ON p.grado_id = g.id
        JOIN regiones r ON p.region_id = r.id
        WHERE DATE(gr.fecha_inicio) = ?
        ORDER BY lg.nombre ASC, gr.fecha_inicio ASC
    ");
    $stmt->bind_param("s", $fecha);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $guardias = [];
    while ($row = $result->fetch_assoc()) {
        $guardias[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'guardias' => $guardias
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Error en la consulta: ' . $e->getMessage()]);
}
?>