<?php
require_once '../../lib/fpdf/fpdf.php';

// Función para convertir UTF-8 a ISO-8859-1 (reemplazo de utf8_decode)
function convertToLatin1($text) {
    return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
}

// Función para generar PDF de guardia semanal
function generarPDFGuardiaSemanal($guardias) {
    // Limpiar cualquier salida previa
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    
    // Título
    $pdf->Cell(0, 10, 'PROGRAMACION DE GUARDIAS SEMANAL', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, 'Periodo: ' . date('d/m/Y', strtotime($guardias[0]['fecha'])) . ' al ' . date('d/m/Y', strtotime($guardias[count($guardias)-1]['fecha'])), 0, 1, 'C');
    $pdf->Cell(0, 8, 'Generado: ' . date('d/m/Y H:i'), 0, 1, 'C');
    $pdf->Ln(10);
    
    // Agrupar por lugar
    $guardias_por_lugar = [];
    foreach ($guardias as $guardia) {
        $guardias_por_lugar[$guardia['lugar']][] = $guardia;
    }
    
    // Generar contenido por lugar
    foreach ($guardias_por_lugar as $lugar => $guardias) {
        // Título del lugar
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(0, 10, convertToLatin1($lugar), 1, 1, 'C', true);
        $pdf->Ln(2);
        
        // Encabezados de tabla
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(25, 8, 'Fecha', 1, 0, 'C');
        $pdf->Cell(20, 8, 'Dia', 1, 0, 'C');
        $pdf->Cell(75, 8, 'Apellido y Nombre', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Telefono', 1, 0, 'C');
        $pdf->Cell(20, 8, 'Region', 1, 1, 'C');
        
        // Datos de guardias
        $pdf->SetFont('Arial', '', 9);
        foreach ($guardias as $guardia) {
            $dias_semana = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'];
            $dia_texto = $dias_semana[$guardia['dia_semana']];
            
            // Alternar colores de fila
            $fill = (array_search($guardia, $guardias) % 2 == 0);
            if ($fill) {
                $pdf->SetFillColor(248, 249, 250);
            }
            
            $telefono = isset($guardia['policia']['telefono']) && !empty($guardia['policia']['telefono']) ? $guardia['policia']['telefono'] : 'No registrado';
            
            // Formatear nombre con abreviatura del grado
            $nombre_completo = '';
            if (isset($guardia['policia']['grado_abreviatura']) && !empty($guardia['policia']['grado_abreviatura'])) {
                $nombre_completo = $guardia['policia']['grado_abreviatura'] . ' ' . $guardia['policia']['apellido'] . ', ' . $guardia['policia']['nombre'];
            } else {
                $nombre_completo = $guardia['policia']['apellido'] . ', ' . $guardia['policia']['nombre'];
            }
            
            $pdf->Cell(25, 6, date('d/m/Y', strtotime($guardia['fecha'])), 1, 0, 'C', $fill);
            $pdf->Cell(20, 6, $dia_texto, 1, 0, 'C', $fill);
            $pdf->Cell(75, 6, convertToLatin1($nombre_completo), 1, 0, 'L', $fill);
            $pdf->Cell(30, 6, $telefono, 1, 0, 'C', $fill);
            $pdf->Cell(20, 6, $guardia['policia']['region'], 1, 1, 'C', $fill);
        }
        
        $pdf->Ln(8);
    }
    
   
  
    // Generar nombre de archivo
    $filename = 'guardia_semanal_' . date('Y-m-d_H-i-s') . '.pdf';
    
    // Descargar PDF
    $pdf->Output('D', $filename);
    exit();
}

// Función para generar PDF de guardia individual (mantener compatibilidad)
function generarPDFGuardia($guardiaData, $tipo = 'actual') {
    // Limpiar cualquier salida previa
    if (ob_get_level()) {
        ob_end_clean();
    }
    
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
        $pdf->Cell(0, 8, convertToLatin1($lugar), 0, 1, 'L');
        
        // Nombre completo y teléfono con puntos
        $pdf->SetFont('Arial', '', 11);
        $nombreCompleto = convertToLatin1($policia['apellido'] . ', ' . $policia['nombre']);
        $telefono = $policia['telefono'] ?: 'No registrado';
        
        // Calcular el ancho disponible
        $anchoDisponible = 190; // Ancho de página menos márgenes
        $anchoNombre = $pdf->GetStringWidth($nombreCompleto);
        $anchoTelefono = $pdf->GetStringWidth($telefono);
        $anchoPuntos = $anchoDisponible - $anchoNombre - $anchoTelefono - 4; // 4 para espacios
        
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
    
   
    // Generar nombre de archivo
    $filename = 'guardia_' . $tipo . '_' . date('Y-m-d_H-i-s') . '.pdf';
    
    // Descargar PDF
    $pdf->Output('D', $filename);
    exit();
}
?>