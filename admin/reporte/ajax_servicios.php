<?php
session_start();
require_once '../../cnx/db_connect.php';

header('Content-Type: application/json');

// Obtener parámetros
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$tipo_evento = $_GET['tipo_evento'] ?? '';

if (empty($fecha_inicio) || empty($fecha_fin)) {
    echo json_encode(['error' => 'Fechas no proporcionadas']);
    exit;
}

// Construir consulta con filtros
$sql = "
    SELECT 
        s.id,
        s.fecha,
        s.hora,
        s.lugar,
        s.observaciones,
        ts.nombre as tipo_servicio,
        COUNT(sp.policia_id) as personal_asignado
    FROM servicios s
    JOIN tipos_servicios ts ON s.tipo_servicio_id = ts.id
    LEFT JOIN servicios_policias sp ON s.id = sp.servicio_id
    WHERE s.fecha BETWEEN ? AND ?
";

$params = [$fecha_inicio, $fecha_fin];

if (!empty($tipo_evento)) {
    $sql .= " AND s.tipo_servicio_id = ?";
    $params[] = $tipo_evento;
}

$sql .= " GROUP BY s.id, s.fecha, s.hora, s.lugar, s.observaciones, ts.nombre
          ORDER BY s.fecha DESC, s.hora DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$servicios = $stmt->fetchAll();

$html = '<div class="table-responsive">';
$html .= '<table class="table table-hover table-striped">';
$html .= '<thead class="table-dark">';
$html .= '<tr>';
$html .= '<th>Fecha</th>';
$html .= '<th>Hora</th>';
$html .= '<th>Tipo Servicio</th>';
$html .= '<th>Lugar</th>';
$html .= '<th>Personal</th>';
$html .= '<th>Observaciones</th>';
$html .= '</tr>';
$html .= '</thead>';
$html .= '<tbody>';

if (count($servicios) > 0) {
    foreach ($servicios as $servicio) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars(date('d/m/Y', strtotime($servicio['fecha']))) . '</td>';
        $html .= '<td>' . htmlspecialchars($servicio['hora']) . '</td>';
        $html .= '<td>' . htmlspecialchars($servicio['tipo_servicio']) . '</td>';
        $html .= '<td>' . htmlspecialchars($servicio['lugar']) . '</td>';
        $html .= '<td><span class="badge bg-primary">' . $servicio['personal_asignado'] . ' policías</span></td>';
        $html .= '<td>' . htmlspecialchars($servicio['observaciones'] ?? '-') . '</td>';
        $html .= '</tr>';
    }
    
    // Resumen
    $total_servicios = count($servicios);
    $html .= '<tr class="table-info">';
    $html .= '<td colspan="6"><strong>TOTAL DE SERVICIOS EN EL PERIODO: ' . $total_servicios . '</strong></td>';
    $html .= '</tr>';
} else {
    $html .= '<tr><td colspan="6" class="text-center text-muted">No se encontraron servicios en el período seleccionado</td></tr>';
}

$html .= '</tbody>';
$html .= '</table>';
$html .= '</div>';

echo json_encode(['html' => $html]);
?>