<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';
require_once '../../config/config_fecha_sistema.php';
require_once '../../lib/fpdf/fpdf.php';

// Función para generar PDF de guardia
function generarPDFGuardia($guardiaData, $tipo = 'actual') {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    
    // Título
    $pdf->Cell(0, 10, 'LISTA DE GUARDIA - ' . strtoupper($tipo), 0, 1, 'C');
    $pdf->Cell(0, 10, 'Fecha: ' . date('d/m/Y H:i'), 0, 1, 'C');
    $pdf->Ln(10);
    
    // Datos con formato de líneas
    $pdf->SetFont('Arial', '', 12);
    foreach ($guardiaData as $lugar => $policia) {
        // Lugar de guardia
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, utf8_decode($lugar), 0, 1, 'L');
        
        // Nombre completo y teléfono con puntos
        $pdf->SetFont('Arial', '', 11);
        $nombreCompleto = utf8_decode($policia['apellido'] . ', ' . $policia['nombre']);
        $telefono = $policia['telefono'] ?: 'No registrado';
        
        // Calcular el ancho disponible
        $anchoDisponible = 190;
        $anchoNombre = $pdf->GetStringWidth($nombreCompleto);
        $anchoTelefono = $pdf->GetStringWidth($telefono);
        $anchoPuntos = $anchoDisponible - $anchoNombre - $anchoTelefono - 4;
        
        // Calcular número de puntos
        $anchoPunto = $pdf->GetStringWidth('.');
        $numeroPuntos = floor($anchoPuntos / $anchoPunto);
        $puntos = str_repeat('.', $numeroPuntos);
        
        // Imprimir la línea
        $pdf->Cell($anchoNombre + 2, 6, $nombreCompleto . ' ', 0, 0, 'L');
        $pdf->Cell($anchoPuntos, 6, $puntos, 0, 0, 'C');
        $pdf->Cell($anchoTelefono + 2, 6, ' ' . $telefono, 0, 1, 'R');
        
        // Espacio entre lugares
        $pdf->Ln(3);
    }
    
    // Pie de página
    $pdf->Ln(20);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 10, 'Documento generado automaticamente por Sistema RH - ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    
    // Generar nombre de archivo
    $filename = 'guardia_' . $tipo . '_' . date('Y-m-d_H-i-s') . '.pdf';
    
    // Descargar PDF
    $pdf->Output('D', $filename);
    exit();
}

// Función para generar PDF de guardia semanal
function generarPDFGuardiaSemanal($guardiaData) {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    
    // Título
    $pdf->Cell(0, 10, 'LISTA DE GUARDIA SEMANAL', 0, 1, 'C');
    $pdf->Cell(0, 10, 'Semana del: ' . date('d/m/Y') . ' al ' . date('d/m/Y', strtotime('+6 days')), 0, 1, 'C');
    $pdf->Ln(10);
    
    // Datos por lugar
    $pdf->SetFont('Arial', '', 10);
    foreach ($guardiaData as $lugar => $policias) {
        // Lugar de guardia
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, utf8_decode($lugar), 0, 1, 'L');
        $pdf->Ln(2);
        
        // Tabla de policías por día
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(25, 6, 'DIA', 1, 0, 'C');
        $pdf->Cell(60, 6, 'NOMBRE COMPLETO', 1, 0, 'C');
        $pdf->Cell(30, 6, 'GRADO', 1, 0, 'C');
        $pdf->Cell(20, 6, 'CIN', 1, 0, 'C');
        $pdf->Cell(30, 6, 'TELEFONO', 1, 1, 'C');
        
        $pdf->SetFont('Arial', '', 8);
        foreach ($policias as $policia) {
            $pdf->Cell(25, 5, utf8_decode($policia['dia_asignado']), 1, 0, 'C');
            $pdf->Cell(60, 5, utf8_decode($policia['apellido'] . ', ' . $policia['nombre']), 1, 0, 'L');
            $pdf->Cell(30, 5, utf8_decode($policia['grado']), 1, 0, 'C');
            $pdf->Cell(20, 5, $policia['cin'], 1, 0, 'C');
            $pdf->Cell(30, 5, $policia['telefono'] ?: 'No reg.', 1, 1, 'C');
        }
        
        $pdf->Ln(5);
    }
    
    // Pie de página
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 10, 'Documento generado automaticamente por Sistema RH - ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    
    // Generar nombre de archivo
    $filename = 'guardia_semanal_' . date('Y-m-d_H-i-s') . '.pdf';
    
    // Descargar PDF
    $pdf->Output('D', $filename);
    exit();
}

// Procesar la solicitud
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'generar_guardia_actual') {
        // Lógica para generar guardia actual
        $guardia_actual = [];
        
        $lugares = $conn->query("SELECT id, nombre FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");
        
        while ($lugar = $lugares->fetch_assoc()) {
            $policia_lugar = $conn->prepare("
                SELECT 
                    lg.posicion,
                    p.id as policia_id,
                    p.nombre,
                    p.apellido,
                    p.cin,
                    p.telefono,
                    g.nombre as grado,
                    lguar.nombre as lugar_guardia
                FROM lista_guardias lg
                JOIN policias p ON lg.policia_id = p.id
                JOIN grados g ON p.grado_id = g.id
                LEFT JOIN lugares_guardias lguar ON p.lugar_guardia_id = lguar.id
                WHERE p.activo = TRUE 
                AND p.lugar_guardia_id = ?
                AND NOT EXISTS (
                    SELECT 1 FROM ausencias a 
                    WHERE a.policia_id = p.id 
                    AND a.estado = 'APROBADA'
                    AND CURDATE() BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, CURDATE())
                )
                ORDER BY lg.posicion
                LIMIT 1
            ");
            $policia_lugar->bind_param("i", $lugar['id']);
            $policia_lugar->execute();
            $result = $policia_lugar->get_result();
            
            if ($policia = $result->fetch_assoc()) {
                $guardia_actual[$lugar['nombre']] = $policia;
                
                // Mover al final de la lista de su sector
                $stmt_rotar = $conn->prepare("CALL RotarGuardia(?)");
                $stmt_rotar->bind_param("i", $policia['policia_id']);
                $stmt_rotar->execute();
            }
        }
        
        if (!empty($guardia_actual)) {
            generarPDFGuardia($guardia_actual, 'actual');
        }
    }
    
    if ($action == 'generar_guardia_semanal') {
        // Lógica para generar guardia semanal
        $guardia_semanal = [];
        
        $lugares = $conn->query("SELECT id, nombre FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");
        
        while ($lugar = $lugares->fetch_assoc()) {
            $policias_semana = [];
            
            $policias_lugar = $conn->prepare("
                SELECT 
                    lg.posicion,
                    p.id as policia_id,
                    p.nombre,
                    p.apellido,
                    p.cin,
                    p.telefono,
                    g.nombre as grado,
                    lguar.nombre as lugar_guardia
                FROM lista_guardias lg
                JOIN policias p ON lg.policia_id = p.id
                JOIN grados g ON p.grado_id = g.id
                LEFT JOIN lugares_guardias lguar ON p.lugar_guardia_id = lguar.id
                WHERE p.activo = TRUE 
                AND p.lugar_guardia_id = ?
                AND NOT EXISTS (
                    SELECT 1 FROM ausencias a 
                    WHERE a.policia_id = p.id 
                    AND a.estado = 'APROBADA'
                    AND CURDATE() BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, CURDATE())
                )
                ORDER BY lg.posicion
                LIMIT 7
            ");
            $policias_lugar->bind_param("i", $lugar['id']);
            $policias_lugar->execute();
            $result = $policias_lugar->get_result();
            
            $dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
            $contador_dia = 0;
            
            while (($policia = $result->fetch_assoc()) && $contador_dia < 7) {
                $policia['dia_asignado'] = $dias_semana[$contador_dia];
                $policias_semana[] = $policia;
                
                $stmt_rotar = $conn->prepare("CALL RotarGuardia(?)");
                $stmt_rotar->bind_param("i", $policia['policia_id']);
                $stmt_rotar->execute();
                
                $contador_dia++;
            }
            
            if (!empty($policias_semana)) {
                $guardia_semanal[$lugar['nombre']] = $policias_semana;
            }
        }
        
        if (!empty($guardia_semanal)) {
            generarPDFGuardiaSemanal($guardia_semanal);
        }
    }
}
?>