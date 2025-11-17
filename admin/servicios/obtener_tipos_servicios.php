<?php
header('Content-Type: application/json');
require_once '../../cnx/db_connect.php';

try {
    $sql = "SELECT 
                ts.id,
                ts.nombre,
                ts.descripcion,
                ts.activo,
                COUNT(rs.id) as total_requisitos
            FROM tipos_servicios ts
            LEFT JOIN requisitos_servicios rs ON ts.id = rs.tipo_servicio_id
            WHERE ts.activo = 1
            GROUP BY ts.id, ts.nombre, ts.descripcion, ts.activo
            ORDER BY ts.nombre";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $tipos_servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tipos_servicios as &$tipo) {
        $sql_requisitos = "SELECT 
                            rs.id,
                            rs.grado_id,
                            rs.genero,
                            rs.region_id,
                            rs.cantidad_requerida,
                            rs.descripcion_puesto,
                            rs.es_obligatorio,
                            g.nombre as grado_nombre,
                            g.abreviatura as grado_abreviatura,
                            r.nombre as region_nombre
                        FROM requisitos_servicios rs
                        INNER JOIN grados g ON rs.grado_id = g.id
                        LEFT JOIN regiones r ON rs.region_id = r.id
                        WHERE rs.tipo_servicio_id = ?
                        ORDER BY g.nivel_jerarquia DESC, rs.genero, rs.cantidad_requerida DESC";
        
        $stmt_req = $conn->prepare($sql_requisitos);
        $stmt_req->execute([$tipo['id']]);
        $tipo['requisitos'] = $stmt_req->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular total de personal requerido
        $tipo['total_personal_requerido'] = array_sum(array_column($tipo['requisitos'], 'cantidad_requerida'));
    }
    
    echo json_encode([
        'success' => true,
        'data' => $tipos_servicios
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener tipos de servicios: ' . $e->getMessage()
    ]);
}
?>