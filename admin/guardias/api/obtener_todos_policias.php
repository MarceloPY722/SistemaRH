<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../../cnx/db_connect.php';

header('Content-Type: application/json');

if ($_POST && isset($_POST['lugar_id'])) {
    $lugar_id = intval($_POST['lugar_id']);
    
    try {
        // Obtener todos los policías para el lugar especificado (sin límite)
        $stmt = $conn->prepare("
            SELECT 
                p.id,
                p.legajo,
                p.nombre,
                p.apellido,
                p.cin,
                p.telefono,
                g.nombre as grado,
                g.nivel_jerarquia,
                r.nombre as region,
                lg.posicion,
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
                END as proxima_fecha_disponible
            FROM lista_guardias lg
            JOIN policias p ON lg.policia_id = p.id
            JOIN grados g ON p.grado_id = g.id
            JOIN regiones r ON p.region_id = r.id
            WHERE p.activo = TRUE AND p.lugar_guardia_id = ?
            ORDER BY lg.posicion ASC
        ");
        
        $stmt->bind_param("i", $lugar_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $policias = [];
        while ($row = $result->fetch_assoc()) {
            $policias[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'policias' => $policias,
            'total' => count($policias)
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Error al obtener los policías: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Parámetros faltantes'
    ]);
}
?>