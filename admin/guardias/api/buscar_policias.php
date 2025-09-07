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
            g.nivel_jerarquia,
            r.nombre as region,
            lg.ultima_guardia_fecha,
            lg.fecha_disponible,
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM ausencias a 
                    WHERE a.policia_id = p.id 
                    AND a.estado = 'APROBADA'
                    AND CURDATE() BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, CURDATE())
                ) THEN 'AUSENTE'
                WHEN lg.fecha_disponible IS NOT NULL AND lg.fecha_disponible > CURDATE() THEN 'NO DISPONIBLE'
                ELSE 'DISPONIBLE'
            END as disponibilidad,
            CASE 
                WHEN lg.fecha_disponible IS NOT NULL AND lg.fecha_disponible > CURDATE() 
                THEN lg.fecha_disponible
                ELSE NULL
            END as proxima_fecha_disponible,
            (SELECT MAX(gr.fecha_inicio)
                FROM guardias_realizadas gr 
                WHERE gr.policia_id = p.id
            ) as ultima_guardia_realizada,
            (SELECT a.fecha_fin 
                FROM ausencias a 
                WHERE a.policia_id = p.id 
                AND a.estado = 'APROBADA'
                AND CURDATE() BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, CURDATE())
                ORDER BY a.fecha_fin DESC
                LIMIT 1
            ) as fecha_fin_ausencia,
            (
                SELECT a.descripcion 
                FROM ausencias a 
                WHERE a.policia_id = p.id 
                AND a.estado = 'APROBADA'
                AND CURDATE() BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, CURDATE())
                ORDER BY a.fecha_fin DESC
                LIMIT 1
            ) as motivo_ausencia,
            (
                SELECT DATEDIFF(a.fecha_fin, CURDATE()) 
                FROM ausencias a 
                WHERE a.policia_id = p.id 
                AND a.estado = 'APROBADA'
                AND CURDATE() BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, CURDATE())
                ORDER BY a.fecha_fin DESC
                LIMIT 1
            ) as dias_restantes_ausencia
        FROM lista_guardias lg
        JOIN policias p ON lg.policia_id = p.id
        JOIN grados g ON p.grado_id = g.id
        JOIN regiones r ON p.region_id = r.id
        WHERE p.activo = TRUE 
        AND p.estado = 'DISPONIBLE'
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
    
    $sql .= " ORDER BY 
        CASE WHEN lg.fecha_disponible IS NULL OR lg.fecha_disponible <= CURDATE() THEN 0 ELSE 1 END,
        lg.posicion ASC, 
        g.nivel_jerarquia DESC, 
        p.legajo ASC 
        LIMIT ?";
    $params[] = (int)$limite;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    $policias = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $policias[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'policias' => $policias
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Error en la consulta: ' . $e->getMessage()]);
}
?>