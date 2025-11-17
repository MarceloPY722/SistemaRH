<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    // Permite acceso directo si está en entorno de impresión bajo sesión.
    // Si prefieres bloquear, descomenta:
    // header("Location: ../../index.php");
    // exit();
}

require_once '../../cnx/db_connect.php';
require_once '../../lib/fpdf/fpdf.php';

// Función helper para convertir texto UTF-8 a ISO-8859-1 (Latin-1) sin usar utf8_decode
function pdf_text($s) {
    if ($s === null) { return ''; }
    $s = (string)$s;
    $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
    if ($converted === false) {
        $converted = mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
    }
    return $converted;
}

$servicioId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($servicioId <= 0) {
    die('ID de servicio inválido.');
}

// Obtener datos del servicio
$stmt = $conn->prepare("SELECT s.*, ts.nombre AS tipo_servicio
                        FROM servicios s
                        LEFT JOIN tipos_servicios ts ON ts.id = s.tipo_servicio_id
                        WHERE s.id = ?");
$stmt->execute([$servicioId]);
$servicio = $stmt->fetch();
if (!$servicio) {
    die('Servicio no encontrado.');
}

// Determinar fecha a mostrar
$fechaMostrar = '';
if (!empty($servicio['fecha_servicio'])) {
    $fechaMostrar = substr($servicio['fecha_servicio'], 0, 10);
} elseif (!empty($servicio['fecha_inicio'])) {
    $fechaMostrar = substr($servicio['fecha_inicio'], 0, 10);
}

// Obtener personal asignado
$stmtA = $conn->prepare("SELECT a.puesto,
                                p.nombre, p.apellido,
                                tg.abreviatura AS grado_abrev, tg.nombre AS grado_nombre, tg.nivel_jerarquia,
                                ts.nombre AS tipo_servicio_nombre
                         FROM asignaciones_servicios a
                         JOIN policias p ON p.id = a.policia_id
                         LEFT JOIN tipo_grados tg ON tg.id = p.grado_id
                         JOIN servicios s ON s.id = a.servicio_id
                         LEFT JOIN tipos_servicios ts ON ts.id = s.tipo_servicio_id
                         WHERE a.servicio_id = ?
                         ORDER BY tg.nivel_jerarquia ASC, p.apellido ASC");
$stmtA->execute([$servicioId]);
$personal = $stmtA->fetchAll();

class PDF_Servicio extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, pdf_text('Sistema RH - Detalle de Servicio'), 0, 1, 'C');
        $this->Ln(2);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, pdf_text('Página ') . $this->PageNo().'/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF_Servicio('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(12, 15, 12);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

// Bloque de datos del servicio
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, pdf_text('Servicio'), 0, 1);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(100, 6, pdf_text('Nombre: ') . pdf_text($servicio['nombre'] ?? ''), 0, 0);
$pdf->Cell(0, 6, pdf_text('Fecha: ') . pdf_text($fechaMostrar), 0, 1);
$pdf->Cell(100, 6, pdf_text('Tipo: ') . pdf_text($servicio['tipo_servicio'] ?? ''), 0, 0);
$pdf->Cell(0, 6, pdf_text('Estado: ') . pdf_text($servicio['estado'] ?? ''), 0, 1);

$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, pdf_text('Orden del día:'), 0, 1);
$pdf->SetFont('Arial', '', 11);
$orden = trim($servicio['orden_del_dia'] ?? '');
if ($orden !== '') {
    $pdf->MultiCell(0, 6, pdf_text($orden));
} else {
    $pdf->Cell(0, 6, pdf_text('No asignado'), 0, 1);
}

$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, pdf_text('Descripción:'), 0, 1);
$pdf->SetFont('Arial', '', 11);
$desc = trim($servicio['descripcion'] ?? '');
if ($desc !== '') {
    $pdf->MultiCell(0, 6, pdf_text($desc));
} else {
    $pdf->Cell(0, 6, pdf_text('Sin descripción'), 0, 1);
}

$pdf->Ln(4);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, pdf_text('Personal Asignado'), 0, 1);

// Encabezado de tabla (sin Lugar ni Horario)
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(10, 8, '#', 1, 0, 'C', true);
$pdf->Cell(30, 8, pdf_text('Grado'), 1, 0, 'C', true);
$pdf->Cell(80, 8, pdf_text('Nombre y Apellido'), 1, 0, 'C', true);
$pdf->Cell(66, 8, pdf_text('Puesto'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 10);
if (!empty($personal)) {
    $i = 1;
    foreach ($personal as $p) {
        $grado = $p['grado_abrev'] ?: ($p['grado_nombre'] ?: '');
        $nombreCompleto = trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''));
        $tipoServicio = $p['tipo_servicio_nombre'] ?? 'Servicio';

        $pdf->Cell(10, 7, $i, 1, 0, 'C');
        $pdf->Cell(30, 7, pdf_text($grado), 1, 0);
        $pdf->Cell(80, 7, pdf_text($nombreCompleto), 1, 0);
        $pdf->Cell(66, 7, pdf_text($tipoServicio), 1, 1);
        $i++;
    }
} else {
    $pdf->Cell(186, 8, pdf_text('No hay personal asignado.'), 1, 1, 'C');
}

// Asegurar que no hay salida previa al PDF
if (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/pdf');
$pdf->Output('I', 'servicio_'.$servicioId.'.pdf');
exit;
?>