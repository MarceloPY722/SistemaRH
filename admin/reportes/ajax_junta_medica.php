<?php
session_start();
require_once '../../cnx/db_connect.php';

header('Content-Type: application/json');

// Obtener policías con junta médica activa
$stmt = $conn->prepare("
    SELECT 
        p.id,
        p.nombre_apellido,
        p.legajo,
        p.dni,
        ta.nombre as tipo_ausencia,
        a.fecha_inicio,
        a.fecha_fin,
        a.observaciones,
        DATEDIFF(COALESCE(a.fecha_fin, CURDATE()), a.fecha_inicio) + 1 as dias_transcurridos,
        g.nombre as grado,
        e.nombre as especialidad
    FROM policias p
    JOIN ausencias a ON p.id = a.policia_id
    JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id
    LEFT JOIN grados g ON p.grado_id = g.id
    LEFT JOIN especialidades e ON p.especialidad_id = e.id
    WHERE ta.nombre LIKE '%junta medica%'
    AND a.estado = 'APROBADA'
    AND (a.fecha_fin IS NULL OR a.fecha_fin >= CURDATE())
    AND a.fecha_inicio <= CURDATE()
    ORDER BY a.fecha_inicio DESC, p.nombre_apellido
");

$stmt->execute();
$junta_medica = $stmt->fetchAll();

$html = '<div class="table-responsive">';
$html .= '<table class="table table-hover table-striped">';
$html .= '<thead class="table-dark">';
$html .= '<tr>';
$html .= '<th>Nombre</th>';
$html .= '<th>Legajo</th>';
$html .= '<th>DNI</th>';
$html .= '<th>Grado</th>';
$html .= '<th>Especialidad</th>';
$html .= '<th>Fecha Inicio</th>';
$html .= '<th>Fecha Fin</th>';
$html .= '<th>Días Transcurridos</th>';
$html .= '<th>Tipo</th>';
$html .= '<th>Observaciones</th>';
$html .= '</tr>';
$html .= '</thead>';
$html .= '<tbody>';

if (count($junta_medica) > 0) {
    foreach ($junta_medica as $ausencia) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($ausencia['nombre_apellido']) . '</td>';
        $html .= '<td>' . htmlspecialchars($ausencia['legajo']) . '</td>';
        $html .= '<td>' . htmlspecialchars($ausencia['dni']) . '</td>';
        $html .= '<td>' . htmlspecialchars($ausencia['grado'] ?? '-') . '</td>';
        $html .= '<td>' . htmlspecialchars($ausencia['especialidad'] ?? '-') . '</td>';
        $html .= '<td>' . htmlspecialchars(date('d/m/Y', strtotime($ausencia['fecha_inicio']))) . '</td>';
        $html .= '<td>' . htmlspecialchars($ausencia['fecha_fin'] ? date('d/m/Y', strtotime($ausencia['fecha_fin'])) : 'Indefinida') . '</td>';
        
        // Color coding for duration
        $dias = $ausencia['dias_transcurridos'];
        $badge_class = $dias > 30 ? 'bg-danger' : ($dias > 15 ? 'bg-warning' : 'bg-info');
        $html .= '<td><span class="badge ' . $badge_class . '">' . $dias . ' días</span></td>';
        
        $html .= '<td><span class="badge bg-danger">Junta Médica</span></td>';
        $html .= '<td>' . htmlspecialchars($ausencia['observaciones'] ?? '-') . '</td>';
        $html .= '</tr>';
    }
} else {
    $html .= '<tr><td colspan="10" class="text-center text-muted">No hay policías con junta médica activa actualmente</td></tr>';
}

$html .= '</tbody>';
$html .= '</table>';
$html .= '</div>';

echo json_encode(['html' => $html]);
?>