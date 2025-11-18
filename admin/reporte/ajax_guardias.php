<?php
session_start();
require_once '../../cnx/db_connect.php';

header('Content-Type: application/json');

if (!isset($_GET['fecha'])) {
    echo json_encode(['error' => 'Fecha no proporcionada']);
    exit;
}

$fecha = $_GET['fecha'];

// Obtener guardias del día con detalles
$stmt = $conn->prepare("
    SELECT 
        g.id,
        g.fecha,
        g.hora_inicio,
        g.hora_fin,
        g.asistio,
        g.observaciones,
        p.nombre_apellido as policía,
        p.legajo,
        lg.nombre as lugar_guardia,
        z.nombre as zona
    FROM guardias g
    JOIN policias p ON g.policia_id = p.id
    JOIN lugares_guardia lg ON g.lugar_guardia_id = lg.id
    JOIN zonas z ON lg.zona_id = z.id
    WHERE g.fecha = ?
    ORDER BY g.hora_inicio, p.nombre_apellido
");

$stmt->execute([$fecha]);
$guardias = $stmt->fetchAll();

$html = '<div class="table-responsive">';
$html .= '<table class="table table-hover table-striped">';
$html .= '<thead class="table-dark">';
$html .= '<tr>';
$html .= '<th>Hora</th>';
$html .= '<th>Policía</th>';
$html .= '<th>Legajo</th>';
$html .= '<th>Lugar</th>';
$html .= '<th>Zona</th>';
$html .= '<th>Asistencia</th>';
$html .= '<th>Observaciones</th>';
$html .= '</tr>';
$html .= '</thead>';
$html .= '<tbody>';

if (count($guardias) > 0) {
    $asistieron = 0;
    $faltaron = 0;
    
    foreach ($guardias as $guardia) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($guardia['hora_inicio']) . ' - ' . htmlspecialchars($guardia['hora_fin']) . '</td>';
        $html .= '<td>' . htmlspecialchars($guardia['policía']) . '</td>';
        $html .= '<td>' . htmlspecialchars($guardia['legajo']) . '</td>';
        $html .= '<td>' . htmlspecialchars($guardia['lugar_guardia']) . '</td>';
        $html .= '<td>' . htmlspecialchars($guardia['zona']) . '</td>';
        
        if ($guardia['asistio'] == 1) {
            $html .= '<td><span class="badge bg-success">Asistió</span></td>';
            $asistieron++;
        } else {
            $html .= '<td><span class="badge bg-danger">No asistió</span></td>';
            $faltaron++;
        }
        
        $html .= '<td>' . htmlspecialchars($guardia['observaciones'] ?? '-') . '</td>';
        $html .= '</tr>';
    }
    
    // Resumen
    $html .= '<tr class="table-info">';
    $html .= '<td colspan="5"><strong>RESUMEN:</strong></td>';
    $html .= '<td><span class="badge bg-success">' . $asistieron . ' Asistieron</span></td>';
    $html .= '<td><span class="badge bg-danger">' . $faltaron . ' Faltaron</span></td>';
    $html .= '</tr>';
} else {
    $html .= '<tr><td colspan="7" class="text-center text-muted">No hay guardias asignadas para esta fecha</td></tr>';
}

$html .= '</tbody>';
$html .= '</table>';
$html .= '</div>';

echo json_encode(['html' => $html]);
?>