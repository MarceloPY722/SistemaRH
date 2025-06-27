<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

require_once '../../../cnx/db_connect.php';

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$lugar_id = $_GET['lugar_id'] ?? null;
$limite = $_GET['limite'] ?? 50;

if (!$lugar_id) {
    echo json_encode(['error' => 'ID de lugar requerido']);
    exit();
}

try {
    $sql = "
        SELECT 
            lg.posicion,
            p.id,
            p.legajo,
            p.nombre,
            p.apellido,
            p.cin,
            p.telefono,
            g.nombre as grado,
            g.jerarquia,
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM ausencias a 
                    WHERE a.policia_id = p.id 
                    AND a.estado = 'APROBADA'
                    AND CURDATE() BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, CURDATE())
                ) THEN 'NO DISPONIBLE'
                ELSE 'DISPONIBLE'
            END as disponibilidad,
            CASE 
                WHEN p.region = 'CENTRAL' THEN 'CENTRAL'
                ELSE 'REGIONAL'
            END as region
        FROM lista_guardias lg
        JOIN policias p ON lg.policia_id = p.id
        JOIN grados g ON p.grado_id = g.id
        WHERE p.activo = TRUE 
        AND p.lugar_guardia_id = ?
    ";
    
    $params = [$lugar_id];
    
    if (!empty($query)) {
        $sql .= " AND (
            p.nombre LIKE ? OR 
            p.apellido LIKE ? OR 
            p.cin LIKE ? OR 
            p.legajo LIKE ? OR 
            g.nombre LIKE ?
        )";
        $searchTerm = "%$query%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    $sql .= " ORDER BY g.jerarquia DESC, p.legajo DESC LIMIT ?";
    $params[] = (int)$limite;
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($params) - 1) . 'i', ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $policias = [];
    $posicion = 1;
    while ($row = $result->fetch_assoc()) {
        $row['posicion_display'] = $posicion++;
        $policias[] = $row;
    }
    
    echo json_encode($policias);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Error en la consulta: ' . $e->getMessage()]);
}
?>