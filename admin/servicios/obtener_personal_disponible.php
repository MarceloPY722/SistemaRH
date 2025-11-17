<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once '../../cnx/db_connect.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['tipo_servicio_id']) || !isset($input['fecha_servicio'])) {
        throw new Exception('Datos incompletos');
    }
    
    $tipo_servicio_id = $input['tipo_servicio_id'];
    $fecha_servicio = $input['fecha_servicio'];
    
    // Obtener requisitos del tipo de servicio desde la tabla requisitos_servicios
    $sql_tipo = "SELECT rs.*, g.nombre as grado_nombre, g.nivel_jerarquia 
                 FROM requisitos_servicios rs 
                 INNER JOIN grados g ON rs.grado_id = g.id 
                 WHERE rs.tipo_servicio_id = ?";
    $stmt_tipo = $conn->prepare($sql_tipo);
    $stmt_tipo->execute([$tipo_servicio_id]);
    $requisitos_raw = $stmt_tipo->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($requisitos_raw)) {
        throw new Exception('No se encontraron requisitos para este tipo de servicio');
    }
    
    // Convertir requisitos al formato esperado (normalizando género)
    $requisitos = ['grados' => []];
    foreach ($requisitos_raw as $req) {
        // Normalizar género de requisitos del backend
        $generoReq = $req['genero'];
        if ($generoReq === 'MASCULINO') $generoReq = 'M';
        elseif ($generoReq === 'FEMENINO') $generoReq = 'F';
        elseif ($generoReq === 'AMBOS') $generoReq = 'AMBOS';

        $requisitos['grados'][] = [
            'grado_nombre' => $req['grado_nombre'],
            'genero' => $generoReq,
            'cantidad' => (int)$req['cantidad_requerida'],
            'region_id' => $req['region_id'],
            'descripcion' => $req['descripcion_puesto']
        ];
    }

    // Calcular ventana de bloqueo: 2 días antes, el día del servicio y 2 días después
    $fechaObj = new DateTime($fecha_servicio);
    $inicioVentana = clone $fechaObj; $inicioVentana->modify('-2 days');
    $finVentana = clone $fechaObj; $finVentana->modify('+2 days');
    $ventanaInicio = $inicioVentana->format('Y-m-d');
    $ventanaFin = $finVentana->format('Y-m-d');

    // Consulta de personal activo con últimos registros y disponibilidad según ventana
    $sql_final = "
        SELECT 
            p.id,
            p.legajo,
            p.nombre,
            p.apellido,
            p.cin,
            p.genero,
            p.region_id,
            g.id AS grado_id,
            g.nombre AS grado_nombre,
            g.nivel_jerarquia,
            (
                SELECT MAX(gg.fecha_guardia)
                FROM guardias_generadas_detalle ggd
                JOIN guardias_generadas gg ON gg.id = ggd.guardia_generada_id
                WHERE ggd.policia_id = p.id
            ) AS ultima_guardia,
            (
                SELECT MAX(s.fecha_inicio)
                FROM asignaciones_servicios asg
                JOIN servicios s ON s.id = asg.servicio_id
                WHERE asg.policia_id = p.id
            ) AS ultima_servicio,
            EXISTS (
                SELECT 1
                FROM guardias_generadas_detalle ggd
                JOIN guardias_generadas gg ON gg.id = ggd.guardia_generada_id
                WHERE ggd.policia_id = p.id
                  AND DATE(gg.fecha_guardia) BETWEEN ? AND ?
            ) AS tiene_guardia_en_ventana,
            EXISTS (
                SELECT 1
                FROM asignaciones_servicios asg
                JOIN servicios s ON s.id = asg.servicio_id
                WHERE asg.policia_id = p.id
                  AND DATE(s.fecha_inicio) BETWEEN ? AND ?
            ) AS tiene_servicio_en_ventana,
            EXISTS (
                SELECT 1
                FROM ausencias a
                WHERE a.policia_id = p.id
                  AND (a.estado IS NULL OR a.estado IN ('APROBADA','ACTIVA'))
                  AND DATE(?) BETWEEN DATE(a.fecha_inicio) AND DATE(IFNULL(a.fecha_fin, a.fecha_inicio))
            ) AS en_ausencia
        FROM policias p
        JOIN grados g ON p.grado_id = g.id
        WHERE p.activo = 1
          AND p.grado_id NOT IN (
              SELECT tg.id FROM tipo_grados tg
              WHERE UPPER(tg.nombre) LIKE '%FUNCIONARIO%'
                 OR UPPER(tg.abreviatura) = 'FUNC.'
          )
        ORDER BY g.nivel_jerarquia DESC, p.apellido, p.nombre
    ";

    $stmt = $conn->prepare($sql_final);
    $stmt->execute([$ventanaInicio, $ventanaFin, $ventanaInicio, $ventanaFin, $fecha_servicio]);
    $personal = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Post-proceso: mapear disponibilidad y normalizar género
    foreach ($personal as &$persona) {
        $tieneGuardia = (int)$persona['tiene_guardia_en_ventana'] === 1;
        $tieneServicio = (int)$persona['tiene_servicio_en_ventana'] === 1;
        $enAusencia = (int)$persona['en_ausencia'] === 1;
        $persona['disponible'] = !($tieneGuardia || $tieneServicio || $enAusencia);
        // Normalizar genero si viene como enum completo
        if ($persona['genero'] === 'MASCULINO') $persona['genero'] = 'M';
        if ($persona['genero'] === 'FEMENINO') $persona['genero'] = 'F';
    }
    
    echo json_encode([
        'success' => true,
        'personal' => $personal,
        'total' => count($personal),
        'disponibles' => count(array_filter($personal, fn($p) => $p['disponible'])),
        'requisitos' => $requisitos
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>