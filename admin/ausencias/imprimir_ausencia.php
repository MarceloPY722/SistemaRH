<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../cnx/db_connect.php';
require_once '../../lib/fpdf/fpdf.php';

function pdf_text($s) {
    if ($s === null) { return ''; }
    $s = (string)$s;
    $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
    if ($converted === false) {
        $converted = mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
    }
    return $converted;
}

$ausenciaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ausenciaId <= 0) {
    die('ID de ausencia inválido');
}

$sql = "SELECT a.*, p.nombre, p.apellido, p.cin, p.legajo, g.nombre AS grado,
                ta.nombre AS tipo_ausencia, ta.descripcion AS tipo_descripcion,
                u_aprobado.nombre_completo AS aprobado_por_nombre,
                lg_principal.nombre AS lugar_principal
         FROM ausencias a
         JOIN policias p ON a.policia_id = p.id
         LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
         LEFT JOIN grados g ON tg.grado_id = g.id
         JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id
         LEFT JOIN usuarios u_aprobado ON a.aprobado_por = u_aprobado.id
         LEFT JOIN lugares_guardias lg_principal ON p.lugar_guardia_id = lg_principal.id
         WHERE a.id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$ausenciaId]);
$a = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$a) {
    die('Ausencia no encontrada');
}

$fi = new DateTime($a['fecha_inicio']);
$ff = $a['fecha_fin'] ? new DateTime($a['fecha_fin']) : $fi;
$dur = $fi->diff($ff)->days + 1;

class PDF_Ausencia extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, pdf_text('Sistema RH - Detalle de Ausencia'), 0, 1, 'C');
        $this->Ln(2);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, pdf_text('Página ').$this->PageNo().'/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF_Ausencia('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(12, 15, 12);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, pdf_text('Información del Policía'), 0, 1);
$pdf->SetFont('Arial', '', 11);
$nombreCompleto = trim(($a['grado'] ? $a['grado'].' ' : '').($a['apellido'] ?? '').', '.($a['nombre'] ?? ''));
$pdf->Cell(100, 6, pdf_text('Nombre: ').pdf_text($nombreCompleto), 0, 0);
$pdf->Cell(0, 6, pdf_text('Legajo: ').pdf_text($a['legajo']), 0, 1);
$pdf->Cell(100, 6, pdf_text('CI: ').pdf_text($a['cin']), 0, 0);
$pdf->Cell(0, 6, pdf_text('Lugar de Guardia: ').pdf_text($a['lugar_principal'] ?? ''), 0, 1);

$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, pdf_text('Detalles de la Ausencia'), 0, 1);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(100, 6, pdf_text('Tipo: ').pdf_text($a['tipo_ausencia']), 0, 0);
$pdf->Cell(0, 6, pdf_text('Estado: ').pdf_text($a['estado']), 0, 1);
$pdf->Cell(100, 6, pdf_text('Fecha Inicio: ').pdf_text(date('d/m/Y', strtotime($a['fecha_inicio']))), 0, 0);
$pdf->Cell(0, 6, pdf_text('Fecha Fin: ').pdf_text($a['fecha_fin'] ? date('d/m/Y', strtotime($a['fecha_fin'])) : 'Ausencia de un día'), 0, 1);
$pdf->Cell(0, 6, pdf_text('Duración: ').pdf_text($dur.' día'.($dur>1?'s':'')), 0, 1);

$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, pdf_text('Descripción'), 0, 1);
$pdf->SetFont('Arial', '', 11);
$desc = trim($a['descripcion'] ?? '');
if ($desc !== '') { $pdf->MultiCell(0, 6, pdf_text($desc)); } else { $pdf->Cell(0, 6, pdf_text('Sin descripción'), 0, 1); }

$just = trim($a['justificacion'] ?? '');
if ($just !== '') {
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, pdf_text('Justificación'), 0, 1);
    $pdf->SetFont('Arial', '', 11);
    $pdf->MultiCell(0, 6, pdf_text($just));
}

$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, pdf_text('Historial'), 0, 1);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 6, pdf_text('Creada: ').pdf_text(date('d/m/Y H:i', strtotime($a['created_at']))), 0, 1);
if (!empty($a['aprobado_por_nombre'])) { $pdf->Cell(0, 6, pdf_text('Procesado por: ').pdf_text($a['aprobado_por_nombre']), 0, 1); }
if (!empty($a['updated_at'])) { $pdf->Cell(0, 6, pdf_text('Actualizado: ').pdf_text(date('d/m/Y H:i', strtotime($a['updated_at']))), 0, 1); }

if (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/pdf');
$pdf->Output('I', 'ausencia_'.$ausenciaId.'.pdf');
exit;
?>