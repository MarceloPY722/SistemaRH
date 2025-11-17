<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../cnx/db_connect.php';
require_once __DIR__ . '/../../lib/fpdf/fpdf.php';

$stmt_user = $conn->prepare("SELECT rol FROM usuarios WHERE id = ?");
$stmt_user->execute([$_SESSION['usuario_id']]);
$usuario_actual = $stmt_user->fetch();
$es_superadmin = ($usuario_actual['rol'] === 'SUPERADMIN');

// Función para convertir UTF-8 a ISO-8859-1
function convertToLatin1($text) {
    return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
}

// Obtener la fecha de la guardia (por defecto hoy)
$fecha_guardia = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$generar_pdf = isset($_GET['pdf']) && $_GET['pdf'] == '1';
$solo_asistentes = isset($_GET['asistentes']) && $_GET['asistentes'] == '1';
$es_domingo = (date('w', strtotime($fecha_guardia)) == 0) || (isset($_GET['feriado']) && $_GET['feriado'] == '1');

$stmt = $conn->prepare("
    SELECT gg.*
    FROM guardias_generadas gg
    WHERE gg.fecha_guardia = ?
");
$stmt->execute([$fecha_guardia]);
$guardia = $stmt->fetch();

if (!$guardia) {
    $personal_asignado = [];
} else {
    
    $stmt = $conn->prepare("
        SELECT 
            ggd.id as detalle_id,
            ggd.posicion_asignacion,
            p.legajo,
            p.nombre,
            p.apellido,
            p.cin,
            p.telefono,
            tg.nombre as grado,
            tg.abreviatura as grado_abreviatura,
            tg.nivel_jerarquia,
            e.nombre as especialidad,
            p.comisionamiento,
            lg.nombre as lugar_guardia,
            lg.zona as region,
            COALESCE(ga.asistio, 1) as asistio,
            ga.hora_llegada,
            ga.hora_salida,
            ga.observaciones as observaciones_asistencia
        FROM guardias_generadas_detalle ggd
        JOIN guardias_generadas gg ON ggd.guardia_generada_id = gg.id
        JOIN policias p ON ggd.policia_id = p.id
        LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
        LEFT JOIN especialidades e ON p.especialidad_id = e.id
        LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
        LEFT JOIN guardias_asistencia ga ON ggd.id = ga.guardia_generada_detalle_id
        WHERE gg.fecha_guardia = ?" . ($solo_asistentes ? " AND COALESCE(ga.asistio, 1) = 1" : "") . "
        ORDER BY tg.nivel_jerarquia DESC, p.legajo DESC
    ");
    $stmt->execute([$fecha_guardia]);
    $personal_asignado = $stmt->fetchAll();
}

// Definir los puestos según el día
if ($es_domingo) {
    $puestos_requeridos = [
        1 => 'JEFE DE SERVICIO',
        2 => 'JEFE DE CUARTEL', 
        3 => 'OFICIAL DE GUARDIA',
        4 => 'NÚMERO DE GUARDIA 1',
        5 => 'NÚMERO DE GUARDIA 2',
        6 => 'NÚMERO DE GUARDIA 3',
        7 => 'NÚMERO DE GUARDIA 4',
        8 => 'NÚMERO DE GUARDIA 5',
        9 => 'CONDUCTOR DE GUARDIA',
        10 => 'DE 06:30 HORAS A 22:00 HS GUARDIA Y 22:00 HS AL LLAMADO HASTA 07:00 HS DEL DÍA SIGUIENTE',
        11 => 'TENIDA: DE REGLAMENTO CON PLACA IDENTIFICATORIA',
        12 => 'SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE 1',
        13 => 'SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE 2',
        14 => 'SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE 3'
    ];
} else {
    $puestos_requeridos = [
        1 => 'JEFE DE SERVICIO',
        2 => 'JEFE DE CUARTEL', 
        3 => 'OFICIAL DE GUARDIA',
        4 => 'ATENCIÓN TELEFÓNICA EXCLUSIVA',
        5 => 'NÚMERO DE GUARDIA 1',
        6 => 'NÚMERO DE GUARDIA 2',
        7 => 'NÚMERO DE GUARDIA 3',
        8 => 'NÚMERO DE GUARDIA 4',
        9 => 'NÚMERO DE GUARDIA 5',
        10 => 'CONDUCTOR DE GUARDIA',
        11 => 'DE 06:30 HORAS A 22:00 HS GUARDIA Y 22:00 HS AL LLAMADO HASTA 07:00 HS DEL DÍA SIGUIENTE',
        12 => 'TENIDA: DE REGLAMENTO CON PLACA IDENTIFICATORIA',
        13 => 'SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE 1',
        14 => 'SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE 2',
        15 => 'SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE 3'
    ];
}

// Filtrar puestos nulos
$puestos_requeridos = array_filter($puestos_requeridos);

// Organizar personal por posición
$personal_por_posicion = [];
foreach ($personal_asignado as $persona) {
    $personal_por_posicion[$persona['posicion_asignacion']][] = $persona;
}

// ========== GENERACIÓN DE PDF - DEBE EJECUTARSE ANTES DE CUALQUIER SALIDA HTML ==========
if ($generar_pdf && $fecha_guardia && $guardia) {
    // Limpiar cualquier salida previa
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetMargins(20, 10, 20); // Aumentar márgenes laterales: izquierdo, superior, derecho
    $pdf->SetAutoPageBreak(true, 10); // Mantener margen inferior
    
    // Fecha en la esquina superior derecha (pequeña)
    $pdf->SetFont('Times', '', 9);
    
    // Traducir meses al español
    $meses_es = [
        'January' => 'enero',
        'February' => 'febrero',
        'March' => 'marzo',
        'April' => 'abril',
        'May' => 'mayo',
        'June' => 'junio',
        'July' => 'julio',
        'August' => 'agosto',
        'September' => 'septiembre',
        'October' => 'octubre',
        'November' => 'noviembre',
        'December' => 'diciembre'
    ];
    
    $dia = date('d', strtotime($fecha_guardia));
    $mes_en = date('F', strtotime($fecha_guardia));
    $año = date('Y', strtotime($fecha_guardia));
    $mes_es = $meses_es[$mes_en] ?? strtolower($mes_en);
    
    $pdf->Cell(0, 5, convertToLatin1('Asunción, ' . $dia . ' de ' . $mes_es . ' de ' . $año . '.-'), 0, 1, 'R');
    $pdf->Ln(3);
    
    // SEDE CENTRAL (centrado y subrayado)
    $pdf->SetFont('Times', 'BU', 9);
    $pdf->Cell(0, 6, convertToLatin1('SEDE CENTRAL'), 0, 1, 'C');
    $pdf->Ln(1);
    
    // ORDEN DEL DÍA con número
    $pdf->SetFont('Times', 'BU', 9);
    $orden_numero = $guardia['orden_dia'] ?? 'N/A';
    $pdf->Cell(0, 6, convertToLatin1('ORDEN DEL DÍA N° ' . $orden_numero), 0, 1, 'C');
    $pdf->Ln(2);
    
    $pdf->SetFont('Times', '', 9);
    
    $dia_inicio = date('d', strtotime($fecha_guardia));
    $mes_inicio_en = date('F', strtotime($fecha_guardia));
    $año_inicio = date('Y', strtotime($fecha_guardia));
    $mes_inicio_es = $meses_es[$mes_inicio_en] ?? strtolower($mes_inicio_en);
    $fecha_inicio = $dia_inicio . ' DE ' . strtoupper($mes_inicio_es) . ' DE ' . $año_inicio;
    
    $dia_fin = date('d', strtotime($fecha_guardia . ' +1 day'));
    $mes_fin_en = date('F', strtotime($fecha_guardia . ' +1 day'));
    $año_fin = date('Y', strtotime($fecha_guardia . ' +1 day'));
    $mes_fin_es = $meses_es[$mes_fin_en] ?? strtolower($mes_fin_en);
    $fecha_fin = $dia_fin . ' DE ' . strtoupper($mes_fin_es) . ' DE ' . $año_fin;
    
    $dia_semana = date('l', strtotime($fecha_guardia));
    $dia_siguiente = date('l', strtotime($fecha_guardia . ' +1 day'));
    
    $dias_es = [
        'Monday' => 'LUNES',
        'Tuesday' => 'MARTES', 
        'Wednesday' => 'MIÉRCOLES',
        'Thursday' => 'JUEVES',
        'Friday' => 'VIERNES',
        'Saturday' => 'SÁBADO',
        'Sunday' => 'DOMINGO'
    ];
    $dia_es = $dias_es[$dia_semana] ?? strtoupper($dia_semana);
    $dia_siguiente_es = $dias_es[$dia_siguiente] ?? strtoupper($dia_siguiente);
    
    $texto_parte1 = "POR LA QUE SE DESIGNA PERSONAL DE GUARDIA Y PERSONAL PARA CUMPLIR FUNCIONES ADMINISTRATIVAS (TELEFONISTA) PARA EL ";
    $texto_parte2 = "DÍA " . $dia_es . " " . $fecha_inicio . " DESDE LAS 07:00 HS. HASTA EL DÍA " . $dia_siguiente_es . " " . $fecha_fin . "; 07:00 HS.";
    $texto_parte3 = " A LOS SIGUIENTES:";
    
    // Dividir el texto en partes para aplicar formato diferente
    $pdf->SetFont('Times', '', 9); // Texto normal
    $pdf->Write(5, convertToLatin1($texto_parte1));
    
    $pdf->SetFont('Times', 'B', 9); // Negrita para la parte del día y fecha
    $pdf->Write(5, convertToLatin1($texto_parte2));
    
    $pdf->SetFont('Times', '', 9); // Volver a texto normal
    $pdf->Write(5, convertToLatin1($texto_parte3));
    
    $pdf->Ln(3);

    $personal_por_lugar = [];
    
    foreach ($personal_asignado as $persona) {
        $posicion = $persona['posicion_asignacion'];
        $lugar = $puestos_requeridos[$posicion] ?? 'PUESTO DESCONOCIDO';
        
        if (strpos($lugar, 'NÚMERO DE GUARDIA') !== false) {
            $lugar = 'NÚMERO DE GUARDIA';
        }
        
        if (strpos($lugar, 'SANIDAD DE GUARDIA') !== false) {
            $lugar = 'SANIDAD DE GUARDIA';
        }
        
        if (!isset($personal_por_lugar[$lugar])) {
            $personal_por_lugar[$lugar] = [];
        }
        $personal_por_lugar[$lugar][] = $persona;
    }
    
    // Definir el orden de los lugares
    $orden_lugares = [
        'JEFE DE SERVICIO',
        'JEFE DE CUARTEL', 
        'OFICIAL DE GUARDIA',
        'ATENCIÓN TELEFÓNICA EXCLUSIVA',
        'NÚMERO DE GUARDIA',
        'CONDUCTOR DE GUARDIA',
        'DE 06:30 HORAS A 22:00 HS GUARDIA Y 22:00 HS AL LLAMADO HASTA 07:00 HS DEL DÍA SIGUIENTE',
        'TENIDA: DE REGLAMENTO CON PLACA IDENTIFICATORIA',
        'SANIDAD DE GUARDIA'
    ];
    
    // Agregar separación antes de comenzar con las guardias
    $pdf->Ln(1);
    
    // Generar contenido por lugar de guardia
    foreach ($orden_lugares as $lugar) {
        if (isset($personal_por_lugar[$lugar]) && !empty($personal_por_lugar[$lugar])) {
            
            // Agregar separación adicional antes del JEFE DE SERVICIO para distinguir el inicio
            if ($lugar === 'JEFE DE SERVICIO') {
                $pdf->Ln(2); // Separación adicional antes del JEFE DE SERVICIO
            }
            
            // Verificar si es una de las tres primeras posiciones especiales
            $es_posicion_especial = in_array($lugar, ['JEFE DE SERVICIO', 'JEFE DE CUARTEL', 'OFICIAL DE GUARDIA']);
            
            if ($es_posicion_especial) {
                // Formato especial para las tres primeras posiciones
                foreach ($personal_por_lugar[$lugar] as $persona) {
                    // Usar abreviatura del grado
                    $grado_abreviado = $persona['grado_abreviatura'] ?: $persona['grado'] ?: '';
                    $nombre_completo = ($persona['nombre'] ?: '') . ' ' . ($persona['apellido'] ?: '');
                    $comisionamiento = $persona['comisionamiento'] ? '(' . $persona['comisionamiento'] . ')' : '';
                    $telefono = $persona['telefono'] ?: 'Sin teléfono';
                    
                    // Construir la línea con puntos de separación
                    $lugar_texto = strtoupper($lugar) . ':';
                    $resto_texto = ' ' . strtoupper($grado_abreviado ?: '') . ' ' . strtoupper(trim($nombre_completo)) . ' ' . strtoupper($comisionamiento ?: '');
                    
                    // Calcular espacio para puntos
                    $pdf->SetFont('Times', '', 9);
                    $ancho_disponible = 170; // Ancho de página menos márgenes laterales aumentados
                    $ancho_lugar = $pdf->GetStringWidth(convertToLatin1($lugar_texto));
                    $ancho_resto = $pdf->GetStringWidth(convertToLatin1($resto_texto));
                    $ancho_telefono = $pdf->GetStringWidth(convertToLatin1(' ' . $telefono));
                    $ancho_puntos = $ancho_disponible - $ancho_lugar - $ancho_resto - $ancho_telefono;
                    $num_puntos = max(3, min(15, floor($ancho_puntos / $pdf->GetStringWidth('.')))); // Mínimo 3, máximo 15 puntos
                    $puntos = str_repeat('.', $num_puntos);
                    
                    // Escribir lugar con negrita y subrayado de fuente
                    $pdf->SetFont('Times', 'BU', 9);
                    $pdf->Write(5, convertToLatin1($lugar_texto));
                    
                    // Escribir resto en formato normal
                    $pdf->SetFont('Times', '', 9);
                    $linea_resto = $resto_texto . $puntos . ' ' . $telefono;
                    $pdf->Write(5, convertToLatin1($linea_resto));
                    $pdf->Ln(3);
                }
                
                $pdf->Ln(2); // Espacio entre lugares
            } else {
                // Formato normal para el resto de posiciones
                
                // Título del lugar en negrita y subrayado de fuente
                $pdf->SetFont('Times', 'BU', 9);
                $pdf->Cell(0, 5, convertToLatin1($lugar), 0, 1, 'L');
                $pdf->Ln(2);
                
                // Personal asignado a este lugar
                foreach ($personal_por_lugar[$lugar] as $persona) {
                    $pdf->SetFont('Times', '', 9);
                    
                    // Usar abreviatura del grado
                    $grado_abreviado = $persona['grado_abreviatura'] ?: $persona['grado'] ?: '';
                    $nombre_completo = ($persona['nombre'] ?: '') . ' ' . ($persona['apellido'] ?: '');
                    $comisionamiento = $persona['comisionamiento'] ? '(' . $persona['comisionamiento'] . ')' : '';
                    $telefono = $persona['telefono'] ?: 'Sin teléfono';
                    
                    // Formato con puntos de separación: GRADO NOMBRE (COMISIONAMIENTO) ..... TELÉFONO
                    $linea_persona = strtoupper($grado_abreviado ?: '') . ' ' . strtoupper(trim($nombre_completo)) . ' ' . strtoupper($comisionamiento ?: '');
                    
                    // Calcular espacio para puntos (reducido)
                    $pdf->SetFont('Times', '', 9);
                    $ancho_disponible = 170; // Ancho de página menos márgenes laterales aumentados
                    $ancho_persona = $pdf->GetStringWidth(convertToLatin1($linea_persona));
                    $ancho_telefono = $pdf->GetStringWidth(convertToLatin1(' ' . $telefono));
                    $ancho_puntos = $ancho_disponible - $ancho_persona - $ancho_telefono;
                    $num_puntos = max(3, min(15, floor($ancho_puntos / $pdf->GetStringWidth('.')))); // Mínimo 3, máximo 15 puntos
                    $puntos = str_repeat('.', $num_puntos);
                    
                    $linea_completa = $linea_persona . $puntos . ' ' . $telefono;
                    
                    $pdf->Cell(0, 4, convertToLatin1($linea_completa), 0, 1, 'L');
                }
                
                $pdf->Ln(2); // Espacio entre lugares
            }
        }
    }
    
    // Agregar lugares que no están en el orden predefinido
    foreach ($personal_por_lugar as $lugar => $personal) {
        if (!in_array($lugar, $orden_lugares)) {
            // Título del lugar en negrita y subrayado de fuente
            $pdf->SetFont('Times', 'BU', 9);
            $pdf->Cell(0, 5, convertToLatin1($lugar), 0, 1, 'L');
            $pdf->Ln(2);
            
            // Personal asignado a este lugar
            foreach ($personal as $persona) {
                $pdf->SetFont('Times', '', 9);
                
                // Usar abreviatura del grado
                $grado_abreviado = $persona['grado_abreviatura'] ?: $persona['grado'] ?: '';
                $nombre_completo = ($persona['nombre'] ?: '') . ' ' . ($persona['apellido'] ?: '');
                $comisionamiento = $persona['comisionamiento'] ? '(' . $persona['comisionamiento'] . ')' : '';
                $telefono = $persona['telefono'] ?: 'Sin teléfono';
                
                // Formato con puntos de separación: GRADO NOMBRE (COMISIONAMIENTO) ..... TELÉFONO
                $linea_persona = strtoupper($grado_abreviado ?: '') . ' ' . strtoupper(trim($nombre_completo)) . ' ' . strtoupper($comisionamiento ?: '');
                
                // Calcular espacio para puntos
                $pdf->SetFont('Times', '', 9);
                $ancho_disponible = 170; // Ancho de página menos márgenes laterales aumentados
                $ancho_persona = $pdf->GetStringWidth(convertToLatin1($linea_persona));
                $ancho_telefono = $pdf->GetStringWidth(convertToLatin1(' ' . $telefono));
                $ancho_puntos = $ancho_disponible - $ancho_persona - $ancho_telefono;
                $num_puntos = max(3, min(15, floor($ancho_puntos / $pdf->GetStringWidth('.')))); // Mínimo 3, máximo 15 puntos
                $puntos = str_repeat('.', $num_puntos);
                
                $linea_completa = $linea_persona . $puntos . ' ' . $telefono;
                
                $pdf->Cell(0, 4, convertToLatin1($linea_completa), 0, 1, 'L');
            }
            
            $pdf->Ln(2); // Espacio entre lugares
        }
    }
    
    // Agregar texto de formación de guardia
    $pdf->Ln(6); // Espacio antes del texto
    
    // FORMACIÓN GUARDIA ENTRANTE 06:30 HS.-
    $pdf->SetFont('Times', 'B', 8);
    $pdf->Write(4, convertToLatin1('FORMACIÓN GUARDIA ENTRANTE'));
    $pdf->Write(4, convertToLatin1(' 06:30 HS.-'));
    $pdf->Ln(4);
    
    // JEFE DE SERVICIO : UNIFORME DE SERVICIO "B" , BIRRETE Y ARMA REGLAMENTARIA.-
    $pdf->SetFont('Times', 'B', 8);
    $pdf->Write(4, convertToLatin1('JEFE DE SERVICIO'));
    $pdf->Write(4, convertToLatin1(' : UNIFORME DE SERVICIO "B" , BIRRETE Y ARMA REGLAMENTARIA.-'));
    $pdf->Ln(4);
    
    // JEFE DE CUARTEL Y DEMAS COMPONENTES DE LA GUARDIA: CON UNIFORME DE SERVICIO "C" Y TODOS LOS ACCESORIOS (ARMA REGLAMENTARIA, PORTANOMBRE, PLACA, BOLÍGRAFO Y AGENDA.-
    $pdf->SetFont('Times', 'B', 8);
    $pdf->Write(4, convertToLatin1('JEFE DE CUARTEL Y DEMAS COMPONENTES DE LA GUARDIA'));
    $pdf->Write(4, convertToLatin1(': CON UNIFORME DE SERVICIO "C" Y TODOS LOS ACCESORIOS (ARMA REGLAMENTARIA, PORTANOMBRE, PLACA, BOLÍGRAFO Y AGENDA.-'));
    $pdf->Ln(4);
    
    // EL JEFE DE SERVICIO Y JEFE DE CUARTEL, SON RESPONSABLES DIRECTO ANTE EL JEFE DEL DEPARTAMENTO, DEL CONTROL, DISTRIBUCIÓN Y VERIFICACIÓN EN FORMA PERMANENTE DE LA GUARDIA.-
    $pdf->SetFont('Times', 'B', 8);
    $pdf->Write(4, convertToLatin1('EL JEFE DE SERVICIO Y JEFE DE CUARTEL'));
    $pdf->Write(4, convertToLatin1(', SON RESPONSABLES DIRECTO ANTE EL JEFE DEL DEPARTAMENTO, DEL CONTROL, DISTRIBUCIÓN Y VERIFICACIÓN EN FORMA PERMANENTE DE LA GUARDIA.-'));
    $pdf->Ln(4);
    
    // EL JEFE DE SERVICIO DESIGNARÁ UN PERSONAL DE LA GUARDIA,  QUIEN SERÁ EL ENCARGADO DEL CONTROL DE LA LLAVE DE LA DIVISIÓN DE ARCHIVOS DE LA SEDE CENTRAL DEL DEPARTAMENTO Y ASENTARA EN EL LIBRO DE LA GUARDIA.-
    $pdf->SetFont('Times', 'B', 8);
    $pdf->Write(4, convertToLatin1('EL JEFE DE SERVICIO'));
    $pdf->Write(4, convertToLatin1(' DESIGNARÁ UN PERSONAL DE LA GUARDIA,  QUIEN SERÁ EL ENCARGADO DEL CONTROL DE LA LLAVE DE LA DIVISIÓN DE ARCHIVOS DE LA SEDE CENTRAL DEL DEPARTAMENTO Y ASENTARA EN EL LIBRO DE LA GUARDIA.-'));
    $pdf->Ln(4);
    
    // PROHIBIR EL INGRESO A PERSONAS AJENAS AL DEPARTAMENTO DE IDENTIFICACIONES FUERA DEL HORARIO DE ATENCIÓN AL PÚBLICO, SALVO CASO DEBIDAMENTE JUSTIFICADA Y AUTORIZADA POR EL JEFE DE SERVICIO, LA CUAL DEBERÁ SER ASENTADA EN EL LIBRO DE NOVEDADES DE LA OFICINA DE GUARDIA.-
    $pdf->SetFont('Times', 'B', 8);
    $pdf->Write(4, convertToLatin1('PROHIBIR EL INGRESO A PERSONAS AJENAS AL DEPARTAMENTO DE IDENTIFICACIONES'));
    $pdf->Write(4, convertToLatin1(' FUERA DEL HORARIO DE ATENCIÓN AL PÚBLICO, SALVO CASO DEBIDAMENTE JUSTIFICADA Y AUTORIZADA POR EL JEFE DE SERVICIO, LA CUAL DEBERÁ SER ASENTADA EN EL LIBRO DE NOVEDADES DE LA OFICINA DE GUARDIA.-'));
    $pdf->Ln(6);
    
    // CUMPLIDO ARCHIVAR
    $pdf->SetFont('Times', 'B', 8);
    $pdf->Cell(0, 4, convertToLatin1('CUMPLIDO ARCHIVAR.'), 0, 1, 'L');
    $pdf->Ln(6);
    
    // Posicionar la firma al final de la página
    // Calcular la posición Y para colocar la firma al final
    $altura_pagina = 297; // Altura de página A4 en mm
    $margen_inferior = 20; // Margen inferior
    $altura_firma = 20; // Espacio estimado para las 3 líneas de firma
    $posicion_y_firma = $altura_pagina - $margen_inferior - $altura_firma;
    
    // Mover a la posición calculada para la firma
    $pdf->SetY($posicion_y_firma);
    
    // Firma centrada al final de la página
    $pdf->SetFont('Times', 'B', 12);
    $pdf->Cell(0, 5, convertToLatin1('SILVIA ACOSTA DE GIMENEZ'), 0, 1, 'C');
    $pdf->SetFont('Times', '', 10);
    $pdf->Cell(0, 5, convertToLatin1('Comisario MGAP.'), 0, 1, 'C');
    $pdf->Cell(0, 5, convertToLatin1('Jefa División RR.HH. - Dpto. de Identificaciones'), 0, 1, 'C');
    
    // Descargar PDF
    $nombre_archivo = 'Guardia_' . date('Y-m-d', strtotime($fecha_guardia)) . '.pdf';
    $pdf->Output('D', $nombre_archivo);
    
    // Si se solicitó redirección a index.php después de descargar PDF
    if (isset($_GET['redirect_to_index']) && $_GET['redirect_to_index'] == '1') {
        // Redirigir a index.php después de que el navegador termine la descarga
        echo '<script>
            setTimeout(function() {
                window.location.href = "index.php";
            }, 1000); // Redirigir después de 1 segundo
        </script>';
        exit();
    }
    
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guardia del <?php echo date('d/m/Y', strtotime($fecha_guardia)); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .puesto-card {
            border-left: 4px solid #007bff;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .puesto-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .puesto-titulo {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
            padding: 8px 12px;
            margin: -1px -1px 10px -1px;
        }
        .policia-info {
            padding: 15px;
        }
        .badge-grado {
            background: #28a745;
            color: white;
            font-size: 0.8rem;
        }
        .badge-region {
            background: #6c757d;
            color: white;
            font-size: 0.8rem;
        }
        .sin-asignar {
            background: #f8f9fa;
            border-left-color: #dc3545;
            opacity: 0.7;
        }
        .sin-asignar .puesto-titulo {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        .header-guardia {
            background: linear-gradient(135deg, #343a40, #495057);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .fecha-selector {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="header-guardia text-center">
            <h1><i class="fas fa-shield-alt me-2"></i>Sistema de Guardias Policiales</h1>
            <p class="mb-0">Visualización de Personal Asignado</p>
        </div>

        <!-- Selector de Fecha -->
        <div class="fecha-selector">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h4 class="mb-0">
                        <i class="fas fa-calendar-day me-2 text-primary"></i>
                        Guardia del <?php echo date('d/m/Y', strtotime($fecha_guardia)); ?>
                        <?php if ($es_domingo): ?>
                            <span class="badge bg-warning text-dark ms-2">DOMINGO</span>
                        <?php endif; ?>
                    </h4>
                </div>
                <div class="col-md-6">
                    <form method="GET" class="d-flex gap-2">
                        <input type="date" name="fecha" value="<?php echo $fecha_guardia; ?>" class="form-control">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Ver
                        </button>
                        <a href="generar_guardia_interface.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Generar
                        </a>
                    </form>
                </div>
                <div class="col-md-6 text-end">
                    <?php if ($guardia): ?>
                        <div class="btn-group" role="group">
                            <a href="?fecha=<?php echo $fecha_guardia; ?>" 
                               class="btn btn-outline-secondary <?php echo !$solo_asistentes ? 'active' : ''; ?>">
                                <i class="fas fa-users"></i> Todos
                            </a>
                            <a href="?fecha=<?php echo $fecha_guardia; ?>&asistentes=1" 
                               class="btn btn-outline-success <?php echo $solo_asistentes ? 'active' : ''; ?>">
                                <i class="fas fa-user-check"></i> Solo Asistentes
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($guardia): ?>
                <div class="mt-3">
                    <div class="row">
                        <div class="col-md-4">
                            <small class="text-muted">Orden del Día:</small>
                            <strong class="d-block"><?php echo $guardia['orden_dia'] ?? 'N/A'; ?></strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Región:</small>
                            <strong class="d-block"><?php echo $guardia['region']; ?></strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Generada:</small>
                            <strong class="d-block"><?php echo date('d/m/Y H:i', strtotime($guardia['created_at'])); ?></strong>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$guardia): ?>
            <!-- No hay guardia -->
            <div class="alert alert-warning text-center py-5">
                <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                <h4>No hay guardia asignada para esta fecha</h4>
                <p class="mb-3">No se ha generado una guardia para el <?php echo date('d/m/Y', strtotime($fecha_guardia)); ?></p>
                <a href="generar_guardia_interface.php?fecha=<?php echo $fecha_guardia; ?>" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus me-2"></i>Generar Guardia
                </a>
            </div>
        <?php else: ?>
            <!-- Personal Asignado -->
            <div class="row">
                <?php foreach ($puestos_requeridos as $posicion => $puesto_nombre): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="card puesto-card <?php echo !isset($personal_por_posicion[$posicion]) ? 'sin-asignar' : ''; ?>">
                            <div class="puesto-titulo">
                                <i class="fas fa-user-tie me-2"></i>
                                <?php echo $puesto_nombre; ?>
                            </div>
                            
                            <?php if (isset($personal_por_posicion[$posicion])): ?>
                                <?php $persona = $personal_por_posicion[$posicion][0]; // Tomar el primer elemento del array ?>
                                <div class="policia-info">
                                    <h6 class="mb-2">
                                        <i class="fas fa-user me-2 text-primary"></i>
                                        <?php echo $persona['nombre'] . ' ' . $persona['apellido']; ?>
                                        <?php if ($persona['asistio'] == 0): ?>
                                            <span class="badge bg-danger ms-2">
                                                <i class="fas fa-times"></i> No asistió
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success ms-2">
                                                <i class="fas fa-check"></i> Asistió
                                            </span>
                                        <?php endif; ?>
                                    </h6>
                                    
                                    <div class="mb-2">
                                        <span class="badge badge-grado me-2">
                                            <i class="fas fa-star me-1"></i>
                                            <?php echo $persona['grado']; ?>
                                        </span>
                                        <span class="badge badge-region">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo $persona['region']; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="small text-muted">
                                        <div><strong>Legajo:</strong> <?php echo $persona['legajo']; ?></div>
                                        <div><strong>CIN:</strong> <?php echo $persona['cin']; ?></div>
                                        <div><strong>Teléfono:</strong> <?php echo $persona['telefono'] ?: 'No registrado'; ?></div>
                                        <?php if ($persona['especialidad']): ?>
                                            <div><strong>Especialidad:</strong> <?php echo $persona['especialidad']; ?></div>
                                        <?php endif; ?>
                                        <?php if ($persona['comisionamiento']): ?>
                                            <div><strong>Comisionamiento:</strong> <?php echo $persona['comisionamiento']; ?></div>
                                        <?php endif; ?>
                                        <div><strong>Sector:</strong> <?php echo $puesto_nombre; ?></div>
                                        <?php if ($persona['hora_llegada'] || $persona['hora_salida']): ?>
                                            <div class="mt-2 p-2 bg-light rounded">
                                                <?php if ($persona['hora_llegada']): ?>
                                                    <div><strong>Llegada:</strong> <?php echo $persona['hora_llegada']; ?></div>
                                                <?php endif; ?>
                                                <?php if ($persona['hora_salida']): ?>
                                                    <div><strong>Salida:</strong> <?php echo $persona['hora_salida']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($persona['observaciones_asistencia']): ?>
                                            <div class="mt-2 p-2 bg-warning bg-opacity-10 rounded">
                                                <strong>Observaciones:</strong> <?php echo $persona['observaciones_asistencia']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="policia-info text-center text-muted">
                                    <i class="fas fa-user-slash fa-2x mb-2"></i>
                                    <p class="mb-0">Sin asignar</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Resumen -->
            <div class="mt-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Resumen de la Guardia</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h3 class="text-primary mb-0"><?php echo count($personal_asignado); ?></h3>
                                    <small class="text-muted">Efectivos Asignados</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h3 class="text-success mb-0"><?php echo count($puestos_requeridos); ?></h3>
                                    <small class="text-muted">Puestos Requeridos</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h3 class="text-warning mb-0"><?php echo count($puestos_requeridos) - count($personal_asignado); ?></h3>
                                    <small class="text-muted">Puestos Vacantes</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <h3 class="text-info mb-0"><?php echo round((count($personal_asignado) / count($puestos_requeridos)) * 100); ?>%</h3>
                                <small class="text-muted">Cobertura</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Botones de Acción -->
        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Volver al Inicio
            </a>
            <?php if ($guardia): ?>
                <a href="ver_guardias.php?fecha=<?php echo $fecha_guardia; ?>&pdf=1" class="btn btn-info me-2">
                    <i class="fas fa-file-pdf me-2"></i>Descargar PDF
                </a>
                <?php if ($es_superadmin): ?>
                <button class="btn btn-warning me-2" onclick="regenerarGuardia()">
                    <i class="fas fa-sync-alt me-2"></i>Regenerar Guardia
                </button>
                <button class="btn btn-success" onclick="mostrarModalAsistencia()">
                    <i class="fas fa-user-check me-2"></i>Gestionar Asistencia
                </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para gestionar asistencia -->
    <?php if ($guardia && $es_superadmin): ?>
    <div class="modal fade" id="modalAsistencia" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-check me-2"></i>Gestionar Asistencia - <?php echo date('d/m/Y', strtotime($fecha_guardia)); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Policía</th>
                                    <th>Puesto</th>
                                    <th>Asistió</th>
                                    <th>Llegada</th>
                                    <th>Salida</th>
                                    <th>Observaciones</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($puestos_requeridos as $posicion => $puesto_nombre): ?>
                                    <?php if (isset($personal_por_posicion[$posicion])): ?>
                                        <?php $persona = $personal_por_posicion[$posicion][0]; ?>
                                        <tr id="fila-<?php echo $persona['detalle_id']; ?>">
                                            <td>
                                                <strong><?php echo $persona['nombre'] . ' ' . $persona['apellido']; ?></strong><br>
                                                <small class="text-muted">Legajo: <?php echo $persona['legajo']; ?></small>
                                            </td>
                                            <td><?php echo $puesto_nombre; ?></td>
                                            <td>
                                                <select class="form-select form-select-sm" id="asistio-<?php echo $persona['detalle_id']; ?>">
                                                    <option value="1" <?php echo $persona['asistio'] == 1 ? 'selected' : ''; ?>>Sí</option>
                                                    <option value="0" <?php echo $persona['asistio'] == 0 ? 'selected' : ''; ?>>No</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="time" class="form-control form-control-sm" 
                                                       id="llegada-<?php echo $persona['detalle_id']; ?>"
                                                       value="<?php echo $persona['hora_llegada']; ?>">
                                            </td>
                                            <td>
                                                <input type="time" class="form-control form-control-sm" 
                                                       id="salida-<?php echo $persona['detalle_id']; ?>"
                                                       value="<?php echo $persona['hora_salida']; ?>">
                                            </td>
                                            <td>
                                                <textarea class="form-control form-control-sm" rows="2" 
                                                          id="observaciones-<?php echo $persona['detalle_id']; ?>"
                                                          placeholder="Observaciones..."><?php echo $persona['observaciones_asistencia']; ?></textarea>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="actualizarAsistencia(<?php echo $persona['detalle_id']; ?>)">
                                                    <i class="fas fa-save"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-success" onclick="guardarTodaAsistencia()">
                        <i class="fas fa-save me-2"></i>Guardar Todo
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function regenerarGuardia() {
            if (confirm('¿Está seguro de que desea regenerar la guardia? Esto eliminará la asignación actual.')) {
                window.location.href = 'generar_guardia_interface.php?fecha=<?php echo $fecha_guardia; ?>&regenerar=1';
            }
        }

        function mostrarModalAsistencia() {
            const modal = new bootstrap.Modal(document.getElementById('modalAsistencia'));
            modal.show();
        }

        function actualizarAsistencia(detalleId) {
            const asistio = document.getElementById('asistio-' + detalleId).value;
            const llegada = document.getElementById('llegada-' + detalleId).value;
            const salida = document.getElementById('salida-' + detalleId).value;
            const observaciones = document.getElementById('observaciones-' + detalleId).value;

            const formData = new FormData();
            formData.append('detalle_id', detalleId);
            formData.append('asistio', asistio);
            formData.append('hora_llegada', llegada);
            formData.append('hora_salida', salida);
            formData.append('observaciones', observaciones);

            fetch('api/actualizar_asistencia.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Asistencia actualizada correctamente');
                    // Actualizar la página para reflejar los cambios
                    location.reload();
                } else {
                    alert('Error al actualizar asistencia: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al procesar la solicitud');
            });
        }

        function guardarTodaAsistencia() {
            const filas = document.querySelectorAll('#modalAsistencia tbody tr');
            let promesas = [];

            filas.forEach(fila => {
                const detalleId = fila.id.replace('fila-', '');
                const asistio = document.getElementById('asistio-' + detalleId).value;
                const llegada = document.getElementById('llegada-' + detalleId).value;
                const salida = document.getElementById('salida-' + detalleId).value;
                const observaciones = document.getElementById('observaciones-' + detalleId).value;

                const formData = new FormData();
                formData.append('detalle_id', detalleId);
                formData.append('asistio', asistio);
                formData.append('hora_llegada', llegada);
                formData.append('hora_salida', salida);
                formData.append('observaciones', observaciones);

                promesas.push(
                    fetch('api/actualizar_asistencia.php', {
                        method: 'POST',
                        body: formData
                    }).then(response => response.json())
                );
            });

            Promise.all(promesas)
                .then(resultados => {
                    const errores = resultados.filter(r => !r.success);
                    if (errores.length === 0) {
                        alert('Toda la asistencia se guardó correctamente');
                        location.reload();
                    } else {
                        alert('Se guardaron algunos registros, pero hubo errores en ' + errores.length + ' registros');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al procesar las solicitudes');
                });
        }
    </script>
</body>
</html>