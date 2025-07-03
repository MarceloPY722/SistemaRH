<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';
require_once '../../lib/fpdf/fpdf.php';

// Parámetros
$fecha_orden = $_GET['fecha_orden'] ?? date('Y-m-d');
$fecha_guardia = $_GET['fecha_guardia'] ?? '';
$numero_orden = $_GET['numero_orden'] ?? '';
$generar_pdf = isset($_GET['generar_pdf']);

$guardias_data = [];
$lugares_guardias = [];

// Obtener lugares de guardias
$lugares_result = $conn->query("SELECT id, nombre FROM lugares_guardias ORDER BY nombre");
while ($lugar = $lugares_result->fetch_assoc()) {
    $lugares_guardias[] = $lugar;
}

// Si se especificó fecha de guardia, obtener el personal
if ($fecha_guardia) {
    $sql = "
        SELECT 
            gr.fecha_inicio,
            gr.fecha_fin,
            CONCAT(p.nombre, ' ', p.apellido) as policia,
            p.telefono,
            g.abreviatura as grado_abrev,
            g.nombre as grado,
            p.comisionamiento,
            lg.nombre as lugar_guardia,
            gr.puesto,
            gr.observaciones
        FROM guardias_realizadas gr
        JOIN policias p ON gr.policia_id = p.id
        JOIN grados g ON p.grado_id = g.id
        JOIN lugares_guardias lg ON gr.lugar_guardia_id = lg.id
        WHERE DATE(gr.fecha_inicio) = ?
        ORDER BY 
            CASE 
                WHEN gr.puesto LIKE '%JEFE DE SERVICIO%' THEN 1
                WHEN gr.puesto LIKE '%JEFE DE CUARTEL%' THEN 2
                WHEN gr.puesto LIKE '%OFICIAL DE GUARDIA%' THEN 3
                WHEN gr.puesto LIKE '%TELEFON%' THEN 4
                WHEN gr.puesto LIKE '%CONDUCTOR%' THEN 5
                WHEN gr.puesto LIKE '%SANIDAD%' THEN 6
                ELSE 7
            END,
            g.nivel_jerarquia ASC,
            p.apellido ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $fecha_guardia);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $guardias_data[] = $row;
    }
}

// Función helper para formatear fechas en español
function formatearFechaEspanol($fecha) {
    $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
    ];
    $dias = [
        'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles',
        'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado', 'Sunday' => 'domingo'
    ];
    
    $fecha_dt = new DateTime($fecha);
    $dia_semana = $dias[$fecha_dt->format('l')];
    $dia = $fecha_dt->format('d');
    $mes = $meses[(int)$fecha_dt->format('n')];
    $año = $fecha_dt->format('Y');
    
    return [$dia_semana, $dia, $mes, $año];
}

// Función mejorada para convertir texto UTF-8 para FPDF
function convertirTextoParaPDF($texto) {
    if (empty($texto)) {
        return '';
    }
    
    // Usar mb_convert_encoding como primera opción
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
    }
    
    // Intentar conversión con iconv
    if (function_exists('iconv')) {
        $resultado = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $texto);
        if ($resultado !== false) {
            return $resultado;
        }
    }
    
    // Fallback manual - remover acentos
    $caracteres = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
        'ñ' => 'n', 'Ñ' => 'N', 'ü' => 'u', 'Ü' => 'U',
        '°' => 'o'
    ];
    
    return strtr($texto, $caracteres);
}

/**
 * Imprime una línea de personal con formato:
 * TITULO: Grado NOMBRE (Comisionamiento)..........(Telefono)
 */
function imprimirLineaPersonal($pdf, $titulo, $grado, $nombre, $comisionamiento, $telefono, $extra_info = '') {
    $pageWidth = $pdf->GetPageWidth();
    $margin = 20;
    
    // Título del puesto en negrita seguido de dos puntos
    $pdf->SetFont('Arial', 'B', 10);
    if (!empty($titulo)) {
        $pdf->Cell($pdf->GetStringWidth($titulo . ': '), 6, convertirTextoParaPDF($titulo . ': '));
    }
    
    // Resto del texto en normal
    $pdf->SetFont('Arial', '', 10);
    // Solo mostrar grado, nombre y comisionamiento
    $linea_izquierda = convertirTextoParaPDF(strtoupper("$grado $nombre")) . " " . convertirTextoParaPDF("($comisionamiento)");
    $pdf->Cell($pdf->GetStringWidth($linea_izquierda), 6, $linea_izquierda);

    // Teléfono alineado a la derecha con puntos
    if ($telefono) {
        $telefono_formateado = convertirTextoParaPDF("($telefono)");
        $ancho_telefono = $pdf->GetStringWidth($telefono_formateado);
        $pos_actual_x = $pdf->GetX();
        $pos_telefono_x = $pageWidth - $margin - $ancho_telefono;

        // Dibujar los puntos
        $pdf->SetX($pos_actual_x);
        for ($i = $pos_actual_x; $i < $pos_telefono_x - 1; $i += $pdf->GetStringWidth('.')) {
            $pdf->Cell($pdf->GetStringWidth('.'), 6, '.');
        }

        $pdf->SetX($pos_telefono_x);
        $pdf->Cell($ancho_telefono, 6, $telefono_formateado);
    }
    
    $pdf->Ln(6);
}

// Función principal para generar el documento PDF (Versión Corregida)
function generarDocumentoPDF($fecha_orden, $fecha_guardia, $numero_orden, $guardias_data) {
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetMargins(20, 15, 20);
    $pdf->SetAutoPageBreak(true, 25);

    // --- ENCABEZADO ---
    $pdf->SetFont('Arial', '', 10);
    list(,, $mes_orden,) = formatearFechaEspanol($fecha_orden);
    $fecha_dt_orden = new DateTime($fecha_orden);
    $fecha_formateada = "Asunción, " . $fecha_dt_orden->format('d') . " de " . ucfirst($mes_orden) . " de " . $fecha_dt_orden->format('Y') . ".-";
    $pdf->Cell(0, 5, convertirTextoParaPDF($fecha_formateada), 0, 1, 'R');
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, 'SEDE CENTRAL', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->Cell(0, 6, convertirTextoParaPDF("ORDEN DEL DÍA Nº $numero_orden/2025"), 0, 1, 'C');
    $pdf->Ln(5);

    // --- DESCRIPCIÓN ---
    $pdf->SetFont('Arial', '', 10);
    list($dia_semana, $dia, $mes, $año) = formatearFechaEspanol($fecha_guardia);
    $fecha_guardia_formateada = strtoupper("$dia_semana $dia DE $mes DE $año");
    
    $fecha_siguiente_dt = new DateTime($fecha_guardia);
    $fecha_siguiente_dt->modify('+1 day');
    list($dia_semana_sig, $dia_sig, $mes_sig, $año_sig) = formatearFechaEspanol($fecha_siguiente_dt->format('Y-m-d'));
    $fecha_siguiente_formateada = strtoupper("$dia_semana_sig $dia_sig DE $mes_sig DE $año_sig");

    $descripcion = "POR LA QUE SE DESIGNA PERSONAL DE GUARDIA Y PERSONAL PARA CUMPLIR FUNCIONES ADMINISTRATIVAS (TELEFONISTA) PARA EL DÍA $fecha_guardia_formateada DESDE LAS 07:00 HS. HASTA EL DÍA $fecha_siguiente_formateada; 07:00 HS. A LOS SIGUIENTES :";
    $pdf->MultiCell(0, 5, convertirTextoParaPDF($descripcion), 0, 'J');
    $pdf->Ln(8);

    // --- PERSONAL DE GUARDIA (agrupado) ---
    $grupos_impresos = [];

    foreach ($guardias_data as $guardia) {
        $puesto_original = strtoupper($guardia['puesto'] ?? '');
        $grado = $guardia['grado_abrev'] ?: $guardia['grado'];
        $nombre = $guardia['policia'] ?? '';
        $comisionamiento = $guardia['comisionamiento'] ?? '';
        $telefono = $guardia['telefono'] ?? '';
        $observaciones = $guardia['observaciones'] ?? '';

        // Lógica para imprimir encabezados de grupo
        if (strpos($puesto_original, 'TELEFON') !== false && !in_array('ATENCIÓN TELEFÓNICA EXCLUSIVA', $grupos_impresos)) {
            $pdf->Ln(4);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, convertirTextoParaPDF('ATENCIÓN TELEFÓNICA EXCLUSIVA (DE 06:00 HORAS A 18:00 HORAS)'), 0, 1);
            $grupos_impresos[] = 'ATENCIÓN TELEFÓNICA EXCLUSIVA';
        } elseif (strpos($puesto_original, 'NUMERO DE GUARDIA') !== false && !in_array('NUMERO DE GUARDIA', $grupos_impresos)) {
            $pdf->Ln(4);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, convertirTextoParaPDF('NUMERO DE GUARDIA'), 0, 1);
            $grupos_impresos[] = 'NUMERO DE GUARDIA';
        }

        $titulo_puesto = $puesto_original;
        
        // Simplificamos el título del puesto para la línea
        $titulo_puesto = str_replace(" (DE 06:00 HORAS A 18:00 HORAS)","",$puesto_original);

        imprimirLineaPersonal($pdf, $titulo_puesto, $grado, $nombre, $comisionamiento, $telefono, $observaciones);
    }

    // --- INSTRUCCIONES FINALES ---
    $pdf->Ln(8);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 5, convertirTextoParaPDF('FORMACIÓN GUARDIA ENTRANTE 06:30 HS.-'), 0, 'J');
    $pdf->MultiCell(0, 5, convertirTextoParaPDF('JEFE DE SERVICIO : UNIFORME DE SERVICIO "B" , BIRRETE Y ARMA REGLAMENTARIA.-'), 0, 'J');
    $pdf->MultiCell(0, 5, convertirTextoParaPDF('JEFE DE CUARTEL Y DEMAS COMPONENTES DE LA GUARDIA: CON UNIFORME DE SERVICIO "C" Y TODOS LOS ACCESORIOS (ARMA REGLAMENTARIA, PORTANOMBRE, PLACA, BOLÍGRAFO Y AGENDA.-'), 0, 'J');
    $pdf->MultiCell(0, 5, convertirTextoParaPDF('EL JEFE DE SERVICIO Y JEFE DE CUARTEL, SON RESPONSABLES DIRECTO ANTE EL JEFE DEL DEPARTAMENTO, DEL CONTROL, DISTRIBUCIÓN Y VERIFICACIÓN EN FORMA PERMANENTE DE LA GUARDIA.-'), 0, 'J');
    $pdf->MultiCell(0, 5, convertirTextoParaPDF('EL JEFE DE SERVICIO DESIGNARÁ UN PERSONAL DE LA GUARDIA, QUIEN SERÁ EL ENCARGADO DEL CONTROL DE LA LLAVE DE LA DIVISIÓN DE ARCHIVOS DE LA SEDE CENTRAL DEL DEPARTAMENTO Y ASENTARA EN EL LIBRO DE LA GUARDIA.-'), 0, 'J');
    $pdf->MultiCell(0, 5, convertirTextoParaPDF('PROHIBIR EL INGRESO A PERSONAS AJENAS AL DEPARTAMENTO DE IDENTIFICACIONES FUERA DEL HORARIO DE ATENCIÓN AL PÚBLICO, SALVO CASO DEBIDAMENTE JUSTIFICADA Y AUTORIZADA POR EL JEFE DE SERVICIO, LA CUAL DEBERÁ SER ASENTADA EN EL LIBRO DE NOVEDADES DE LA OFICINA DE GUARDIA.-'), 0, 'J');
    $pdf->Ln(8);

    // --- CIERRE Y FIRMA ---
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'CUMPLIDO ARCHIVAR.', 0, 1, 'C');
    $pdf->Ln(15);
    
    $pdf->Cell(0, 6, 'V.Bº', 0, 1, 'L');
    $pdf->Ln(15);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, convertirTextoParaPDF('SILVIA ACOSTA DE GIMENEZ'), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, convertirTextoParaPDF('Comisario MGAP.'), 0, 1, 'C');
    $pdf->Cell(0, 6, convertirTextoParaPDF('Jefa División RR.HH. - Dpto. de Identificaciones'), 0, 1, 'C');

    return $pdf;
}

// Generar y descargar documento PDF
if ($generar_pdf && $fecha_guardia && $numero_orden) {
    // Limpiar cualquier salida previa
    ob_clean();
    
    $pdf = generarDocumentoPDF($fecha_orden, $fecha_guardia, $numero_orden, $guardias_data);
    
    $filename = "Orden_del_Dia_" . str_replace('-', '_', $fecha_guardia) . "_" . $numero_orden . ".pdf";
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $pdf->Output('D', $filename);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden del Día - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(45deg, #104c75, #0d3d5c) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .preview-document {
            background: white;
            padding: 40px;
            margin: 20px 0;
            border: 1px solid #ddd;
            font-family: Arial, sans-serif;
            line-height: 1.6;
        }
        .document-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .document-title {
            font-weight: bold;
            font-size: 14px;
            margin: 10px 0;
        }
        .personal-line {
            margin: 8px 0;
            font-size: 12px;
        }
        .instrucciones {
            margin-top: 30px;
            font-size: 11px;
        }
        .firma {
            text-align: center;
            margin-top: 40px;
        }
        @media print {
            .no-print { display: none !important; }
            .preview-document { 
                box-shadow: none; 
                border: none;
                margin: 0;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../inc/sidebar.php'; ?>
            
            <div class="col-md-9 col-lg-10">
                <div class="main-content p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 text-primary">
                            <i class="fas fa-file-pdf"></i> Orden del Día
                        </h1>
                        <a href="index.php" class="btn btn-outline-secondary no-print">
                            <i class="fas fa-arrow-left"></i> Volver a Reportes
                        </a>
                    </div>

                    <!-- Formulario de configuración -->
                    <div class="card mb-4 no-print">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-cog"></i> Configuración de la Orden</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label for="fecha_orden" class="form-label">Fecha de la Orden</label>
                                    <input type="date" class="form-control" id="fecha_orden" name="fecha_orden" 
                                           value="<?= htmlspecialchars($fecha_orden) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="fecha_guardia" class="form-label">Fecha de Guardia</label>
                                    <input type="date" class="form-control" id="fecha_guardia" name="fecha_guardia" 
                                           value="<?= htmlspecialchars($fecha_guardia) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="numero_orden" class="form-label">Número de Orden</label>
                                    <input type="text" class="form-control" id="numero_orden" name="numero_orden" 
                                           value="<?= htmlspecialchars($numero_orden) ?>" placeholder="Ej: 322" required>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search"></i> Generar Vista Previa
                                    </button>
                                    <?php if ($fecha_guardia && $numero_orden): ?>
                                    <a href="?fecha_orden=<?= urlencode($fecha_orden) ?>&fecha_guardia=<?= urlencode($fecha_guardia) ?>&numero_orden=<?= urlencode($numero_orden) ?>&generar_pdf=1" 
                                       class="btn btn-success">
                                        <i class="fas fa-download"></i> Descargar PDF
                                    </a>
                                    <button type="button" onclick="window.print()" class="btn btn-info">
                                        <i class="fas fa-print"></i> Imprimir
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if ($fecha_guardia && $numero_orden): ?>
                    <!-- Vista previa del documento -->
                    <div class="card">
                        <div class="card-header no-print">
                            <h5 class="mb-0"><i class="fas fa-eye"></i> Vista Previa del Documento</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="preview-document">
                                <div class="document-header">
                                    <?php 
                                    list($dia_semana_orden, $dia_orden, $mes_orden, $año_orden) = formatearFechaEspanol($fecha_orden);
                                    $fecha_formateada = $dia_orden . ' de ' . $mes_orden . ' de ' . $año_orden;
                                    ?>
                                    <div>Asunción, <?= $fecha_formateada ?>.-</div>
                                    <div class="document-title">SEDE CENTRAL</div>
                                    <br>
                                    <div class="document-title">ORDEN DEL DÍA Nº <?= htmlspecialchars($numero_orden) ?>/2025</div>
                                    <br>
                                    <?php 
                                    list($dia_semana, $dia, $mes, $año) = formatearFechaEspanol($fecha_guardia);
                                    $fecha_guardia_formateada = ucfirst($dia_semana) . ' ' . $dia . ' de ' . $mes . ' de ' . $año;
                                    
                                    $fecha_siguiente = date('Y-m-d', strtotime($fecha_guardia . ' +1 day'));
                                    list($dia_semana_sig, $dia_sig, $mes_sig, $año_sig) = formatearFechaEspanol($fecha_siguiente);
                                    $fecha_siguiente_formateada = ucfirst($dia_semana_sig) . ' ' . $dia_sig . ' de ' . $mes_sig . ' de ' . $año_sig;
                                    ?>
                                    <div style="text-align: justify; margin: 20px 0;">
                                        POR LA QUE SE DESIGNA PERSONAL DE GUARDIA Y PERSONAL PARA CUMPLIR FUNCIONES ADMINISTRATIVAS (TELEFONISTA) 
                                        PARA EL DÍA <?= strtoupper($fecha_guardia_formateada) ?> DESDE LAS 07:00 HS. HASTA EL DÍA 
                                        <?= strtoupper($fecha_siguiente_formateada) ?>; 07:00 HS. A LOS SIGUIENTES :
                                    </div>
                                </div>

                                <?php if (!empty($guardias_data)): ?>
                                <div style="margin: 30px 0;">
                                    <?php foreach ($guardias_data as $guardia): ?>
                                    <div class="personal-line">
                                        <?php 
                                        $grado = $guardia['grado_abrev'] ?: $guardia['grado'];
                                        $nombre = $guardia['policia'];
                                        $comisionamiento = $guardia['comisionamiento'];
                                        $telefono = $guardia['telefono'];
                                        $puesto = strtoupper($guardia['puesto']);
                                        ?>
                                        <strong><?= htmlspecialchars($puesto) ?>:</strong> 
                                        <?= htmlspecialchars(strtoupper($grado . ' ' . $nombre)) ?> 
                                        (<?= htmlspecialchars($comisionamiento) ?>)
                                        <?php if ($telefono): ?>
                                        ..............................(<?= htmlspecialchars($telefono) ?>)
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning">
                                    No se encontraron guardias para la fecha seleccionada.
                                </div>
                                <?php endif; ?>

                                <div class="instrucciones">
                                    <p>FORMACIÓN GUARDIA ENTRANTE 06:30 HS.-</p>
                                    <p>JEFE DE SERVICIO : UNIFORME DE SERVICIO "B" , BIRRETE Y ARMA REGLAMENTARIA.-</p>
                                    <p>JEFE DE CUARTEL Y DEMAS COMPONENTES DE LA GUARDIA: CON UNIFORME DE SERVICIO "C" Y TODOS LOS ACCESORIOS (ARMA REGLAMENTARIA, PORTANOMBRE, PLACA, BOLÍGRAFO Y AGENDA.-</p>
                                    <p>EL JEFE DE SERVICIO Y JEFE DE CUARTEL, SON RESPONSABLES DIRECTO ANTE EL JEFE DEL DEPARTAMENTO, DEL CONTROL, DISTRIBUCIÓN Y VERIFICACIÓN EN FORMA PERMANENTE DE LA GUARDIA.-</p>
                                    <p>EL JEFE DE SERVICIO DESIGNARÁ UN PERSONAL DE LA GUARDIA, QUIEN SERÁ EL ENCARGADO DEL CONTROL DE LA LLAVE DE LA DIVISIÓN DE ARCHIVOS DE LA SEDE CENTRAL DEL DEPARTAMENTO Y ASENTARA EN EL LIBRO DE LA GUARDIA.-</p>
                                    <p>PROHIBIR EL INGRESO A PERSONAS AJENAS AL DEPARTAMENTO DE IDENTIFICACIONES FUERA DEL HORARIO DE ATENCIÓN AL PÚBLICO, SALVO CASO DEBIDAMENTE JUSTIFICADA Y AUTORIZADA POR EL JEFE DE SERVICIO, LA CUAL DEBERÁ SER ASENTADA EN EL LIBRO DE NOVEDADES DE LA OFICINA DE GUARDIA.-</p>
                                </div>

                                <div class="firma">
                                    <p>CUMPLIDO ARCHIVAR.</p>
                                    <br><br>
                                    <p>V.Bº</p>
                                    <br><br><br>
                                    <p><strong>SILVIA ACOSTA DE GIMENEZ</strong></p>
                                    <p>Comisario MGAP.</p>
                                    <p>Jefa División RR.HH. - Dpto. de Identificaciones</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>