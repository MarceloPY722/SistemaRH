<?php
session_start();
require_once '../../cnx/db_connect.php';

header('Content-Type: application/json');

// Obtener policías deshabilitados
$stmt = $conn->prepare("
    SELECT 
        p.id,
        p.nombre_apellido,
        p.legajo,
        p.dni,
        p.fecha_nacimiento,
        p.fecha_ingreso,
        g.nombre as grado,
        e.nombre as especialidad,
        p.motivo_inactivo,
        p.fecha_inactivo
    FROM policias p
    LEFT JOIN grados g ON p.grado_id = g.id
    LEFT JOIN especialidades e ON p.especialidad_id = e.id
    WHERE p.estado = 'INACTIVO'
    ORDER BY p.fecha_inactivo DESC, p.nombre_apellido
");

$stmt->execute();
$policias = $stmt->fetchAll();

$html = '<div class="table-responsive">';
$html .= '<table class="table table-hover table-striped">';
$html .= '<thead class="table-dark">';
$html .= '<tr>';
$html .= '<th>Nombre</th>';
$html .= '<th>Legajo</th>';
$html .= '<th>DNI</th>';
$html .= '<th>Grado</th>';
$html .= '<th>Especialidad</th>';
$html .= '<th>Fecha Inactivo</th>';
$html .= '<th>Motivo</th>';
$html .= '</tr>';
$html .= '</thead>';
$html .= '<tbody>';

if (count($policias) > 0) {
    foreach ($policias as $policia) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($policia['nombre_apellido']) . '</td>';
        $html .= '<td>' . htmlspecialchars($policia['legajo']) . '</td>';
        $html .= '<td>' . htmlspecialchars($policia['dni']) . '</td>';
        $html .= '<td>' . htmlspecialchars($policia['grado'] ?? '-') . '</td>';
        $html .= '<td>' . htmlspecialchars($policia['especialidad'] ?? '-') . '</td>';
        $html .= '<td>' . htmlspecialchars($policia['fecha_inactivo'] ? date('d/m/Y', strtotime($policia['fecha_inactivo'])) : '-') . '</td>';
        $html .= '<td>' . htmlspecialchars($policia['motivo_inactivo'] ?? '-') . '</td>';
        $html .= '</tr>';
    }
} else {
    $html .= '<tr><td colspan="7" class="text-center text-muted">No hay policías deshabilitados</td></tr>';
}

$html .= '</tbody>';
$html .= '</table>';
$html .= '</div>';

echo json_encode(['html' => $html]);
?>