<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';
require_once '../../config/config_fecha_sistema.php'; // Incluir el nuevo sistema de fechas
require_once '../../lib/fpdf/fpdf.php'; // Incluir la librería FPDF

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

// Eliminar o comentar las secciones de procesamiento de PDF (líneas 130-280 aproximadamente)
// Mantener solo el procesamiento de reorganización y rotación

// Procesar reorganización de lista
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'reorganizar') {
    $conn->query("CALL ReorganizarListaGuardias()");
    $mensaje = "<div class='alert alert-success'>Lista de guardias reorganizada exitosamente</div>";
}

// Procesar rotación de guardia
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'rotar') {
    $policia_id = $_POST['policia_id'];
    $stmt = $conn->prepare("CALL RotarGuardia(?)");
    $stmt->bind_param("i", $policia_id);
    $stmt->execute();
    $mensaje = "<div class='alert alert-success'>Guardia rotada exitosamente</div>";
}

// Procesar generación de guardia actual
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'generar_guardia_actual') {
    // Obtener el primer policía disponible de cada lugar de guardia
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
    
    // Generar PDF si hay datos
    if (!empty($guardia_actual)) {
        generarPDFGuardia($guardia_actual, 'actual');
    }
    
    $mensaje = "<div class='alert alert-success'>Guardia actual generada exitosamente</div>";
}

// Procesar generación de guardia semanal
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'generar_guardia_semanal') {
    $guardia_semanal = [];
    
    $lugares = $conn->query("SELECT id, nombre FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");
    
    while ($lugar = $lugares->fetch_assoc()) {
        $policias_semana = [];
        
        // Obtener 7 policías disponibles de este lugar
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
            
            // Rotar cada policía al final de la lista
            $stmt_rotar = $conn->prepare("CALL RotarGuardia(?)");
            $stmt_rotar->bind_param("i", $policia['policia_id']);
            $stmt_rotar->execute();
            
            $contador_dia++;
        }
        
        if (!empty($policias_semana)) {
            $guardia_semanal[$lugar['nombre']] = $policias_semana;
        }
    }
    
    // Generar PDF si hay datos
    if (!empty($guardia_semanal)) {
        generarPDFGuardiaSemanal($guardia_semanal);
    }
    
    $mensaje = "<div class='alert alert-success'>Guardia semanal generada exitosamente (7 días por lugar)</div>";
}

// Procesar generación de guardia general
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'generar_guardia_general') {
    $guardia_general = [];
    
    $lugares = $conn->query("SELECT id, nombre FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");
    
    while ($lugar = $lugares->fetch_assoc()) {
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
            LIMIT 1
        ");
        $policias_lugar->bind_param("i", $lugar['id']);
        $policias_lugar->execute();
        $result = $policias_lugar->get_result();
        
        if ($policia = $result->fetch_assoc()) {
            $guardia_general[$lugar['nombre']] = $policia;
            
            // Opcional: Rotar al policía seleccionado al final de la lista
            $stmt_rotar = $conn->prepare("CALL RotarGuardia(?)");
            $stmt_rotar->bind_param("i", $policia['policia_id']);
            $stmt_rotar->execute();
        }
    }
    
    // Generar PDF si hay datos
    if (!empty($guardia_general)) {
        generarPDFGuardia($guardia_general, 'general');
    }
    
    $mensaje = "<div class='alert alert-success'>Guardia general generada exitosamente (1 persona por lugar)</div>";
}

// Procesar generación de guardia con restricciones de región
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'generar_guardia') {
    $fechaActual = FechaSistema::obtenerFechaSQL();
    
    $sql = "SELECT 
            lg.nombre as lugar,
            p.id,
            p.nombre,
            p.apellido,
            p.cin,
            p.telefono,
            g.nombre as grado,
            p.region,
            lg.posicion,
            lg.ultima_guardia_fecha
        FROM (
            SELECT 
                lguar.id as lugar_id,
                lguar.nombre,
                MIN(lista.posicion) as min_posicion
            FROM lugares_guardias lguar
            JOIN lista_guardias lista ON lguar.id = lista.policia_id
            JOIN policias pol ON lista.policia_id = pol.id
            WHERE lguar.activo = 1
            AND pol.activo = 1
            AND NOT EXISTS (
                SELECT 1 FROM ausencias a 
                WHERE a.policia_id = pol.id 
                AND a.estado = 'APROBADA' 
                AND '$fechaActual' BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, '$fechaActual')
            )
            AND (
                (pol.region = 'REGIONAL' AND (
                    lista.ultima_guardia_fecha IS NULL OR 
                    DATE_FORMAT(lista.ultima_guardia_fecha, '%Y-%m') != DATE_FORMAT('$fechaActual', '%Y-%m')
                ))
                OR 
                (pol.region = 'CENTRAL' AND (
                    lista.ultima_guardia_fecha IS NULL OR 
                    DATEDIFF('$fechaActual', lista.ultima_guardia_fecha) >= 15
                ))
            )
            GROUP BY lguar.id, lguar.nombre
        ) lg
        JOIN lista_guardias lista ON lg.min_posicion = lista.posicion
        JOIN policias p ON lista.policia_id = p.id
        JOIN grados g ON p.grado_id = g.id
        WHERE p.lugar_guardia_id = lg.lugar_id";
    
    $result = $conn->query($sql);
    $guardiaGenerada = [];
    $guardiaParaPDF = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if ($row['id']) {
                $guardiaGenerada[] = $row;
                $guardiaParaPDF[$row['lugar']] = $row;
                
                // Actualizar fecha de última guardia
                $updateSql = "UPDATE lista_guardias 
                             SET ultima_guardia_fecha = '$fechaActual' 
                             WHERE policia_id = " . $row['id'];
                $conn->query($updateSql);
                
                // Rotar la lista
                $rotateSql = "CALL RotarGuardia(" . $row['id'] . ")";
                $conn->query($rotateSql);
            }
        }
        
        if (!empty($guardiaGenerada)) {
            // Generar PDF
            generarPDFGuardia($guardiaParaPDF, 'restricciones');
            $mensaje = "Guardia generada exitosamente para el " . FechaSistema::obtenerFechaFormateada();
        } else {
            $mensaje = "No hay policías disponibles para generar guardia en este momento.";
        }
    }
}

// Obtener lugares de guardias con sus policías
$lugares_guardias = $conn->query("SELECT id, nombre, zona, descripcion FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");

// Función para obtener policías por lugar con restricciones de región
function obtenerPoliciasPorLugar($conn, $lugar_id, $limite = 5) {
    $fechaActual = FechaSistema::obtenerFechaSQL();
    
    $sql = "SELECT 
                p.id as policia_id,
                p.nombre,
                p.apellido,
                p.cin,
                p.telefono,
                g.nombre as grado,
                g.nivel_jerarquia,
                p.antiguedad_dias,
                p.region,
                lg.posicion,
                lg.ultima_guardia_fecha,
                CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM ausencias a 
                        WHERE a.policia_id = p.id 
                        AND a.estado = 'APROBADA' 
                        AND '$fechaActual' BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, '$fechaActual')
                    ) THEN 'AUSENTE'
                    WHEN p.region = 'REGIONAL' AND lg.ultima_guardia_fecha IS NOT NULL 
                         AND DATE_FORMAT(lg.ultima_guardia_fecha, '%Y-%m') = DATE_FORMAT('$fechaActual', '%Y-%m') 
                    THEN 'NO_DISPONIBLE_MES'
                    WHEN p.region = 'CENTRAL' AND lg.ultima_guardia_fecha IS NOT NULL 
                         AND DATEDIFF('$fechaActual', lg.ultima_guardia_fecha) < 15 
                    THEN 'NO_DISPONIBLE_15_DIAS'
                    ELSE 'DISPONIBLE'
                END as disponibilidad,
                CASE 
                    WHEN p.region = 'REGIONAL' AND lg.ultima_guardia_fecha IS NOT NULL 
                    THEN DATE_FORMAT(DATE_ADD(lg.ultima_guardia_fecha, INTERVAL 1 MONTH), '%d/%m/%Y')
                    WHEN p.region = 'CENTRAL' AND lg.ultima_guardia_fecha IS NOT NULL 
                    THEN DATE_FORMAT(DATE_ADD(lg.ultima_guardia_fecha, INTERVAL 15 DAY), '%d/%m/%Y')
                    ELSE 'Disponible ahora'
                END as proxima_fecha_disponible
            FROM lista_guardias lg
            JOIN policias p ON lg.policia_id = p.id
            JOIN grados g ON p.grado_id = g.id
            WHERE p.activo = TRUE 
            AND p.lugar_guardia_id = ?
            ORDER BY lg.posicion
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $lugar_id, $limite);
    $stmt->execute();
    return $stmt->get_result();
}

// Función para contar policías por lugar
function contarPoliciasPorLugar($conn, $lugar_id) {
    $sql = "SELECT COUNT(*) as total 
            FROM lista_guardias lg
            JOIN policias p ON lg.policia_id = p.id
            WHERE p.activo = TRUE 
            AND p.lugar_guardia_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $lugar_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Guardias - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .main-content {
            padding: 20px;
        }
        .page-title {
            color: #2c3e50;
            margin-bottom: 30px;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        .guardia-actual-card .card-header {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        .guardia-general-card .card-header {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        .guardia-semanal-card .card-header {
            background: linear-gradient(135deg, #27ae60, #229954);
        }
        .posicion-badge {
            font-size: 0.9em;
            font-weight: bold;
        }
        .collapse-toggle {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .collapse-toggle:hover {
            background-color: #f8f9fa;
        }
        .collapse-content {
            display: none;
        }
        .collapse-content.show {
            display: block;
        }
        .badge-ausente {
            background-color: #dc3545;
        }
        .badge-no-disponible-mes {
            background-color: #fd7e14;
        }
        .badge-no-disponible-15-dias {
            background-color: #ffc107;
            color: #000;
        }
        .badge-disponible {
            background-color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php 
            $_GET['page'] = 'guardias';
            include '../inc/sidebar.php'; 
            ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <h1 class="page-title">
                        <i class="fas fa-list-ul"></i> Lista de Guardias por Lugares
                    </h1>

                    <?php if (isset($mensaje)) echo $mensaje; ?>

                    <!-- Botones de Acción -->
                    <div class="mb-4 no-print">
                        <button type="button" class="btn btn-danger btn-lg" onclick="generarGuardia('generar_guardia_actual')">
                            <i class="fas fa-shield-alt"></i> Generar Guardia
                        </button>
                        
                        <button type="button" class="btn btn-success btn-lg" onclick="generarGuardia('generar_guardia_semanal')" style="margin-left: 10px;">
                            <i class="fas fa-calendar-week"></i> Generar Guardia Semanal
                        </button>
                        
                        <form method="POST" style="display: inline; margin-left: 10px;">
                            <input type="hidden" name="action" value="reorganizar">
                            <button type="submit" class="btn btn-info" onclick="return confirm('¿Está seguro de reorganizar la lista de guardias?')">
                                <i class="fas fa-sync-alt"></i> Resetear Guardias
                            </button>
                        </form>
                    </div>

                    <!-- Mostrar Guardia Actual si fue generada -->
                    <?php if (isset($guardia_actual) && !empty($guardia_actual)): ?>
                    <div class="card guardia-actual-card mb-4">
                        <div class="card-header text-white">
                            <h5><i class="fas fa-shield-alt"></i> Guardia Actual Generada - <?php echo date('d/m/Y H:i'); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($guardia_actual as $lugar_nombre => $policia): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card border-warning">
                                        <div class="card-header bg-warning text-dark">
                                            <h6 class="mb-0"><i class="fas fa-map-marker-alt"></i> <?php echo $lugar_nombre; ?></h6>
                                        </div>
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo $policia['apellido'] . ', ' . $policia['nombre']; ?></h5>
                                            <p class="card-text">
                                                <strong>Grado:</strong> <?php echo $policia['grado']; ?><br>
                                                <strong>CIN:</strong> <?php echo $policia['cin']; ?><br>
                                                <strong>Teléfono:</strong> <?php echo $policia['telefono'] ?: 'No registrado'; ?><br>
                                                <strong>Posición anterior:</strong> #<?php echo $policia['posicion']; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($guardia_general) && !empty($guardia_general)): ?>
                    <div class="card guardia-general-card mb-4">
                        <div class="card-header text-white">
                            <h5><i class="fas fa-star"></i> Guardia General Generada</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($guardia_general as $lugar_nombre => $policias): ?>
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-dark"><i class="fas fa-map-marker-alt"></i> <?php echo $lugar_nombre; ?></h6>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($policias as $policia): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <strong><?php echo $policia['apellido'] . ', ' . $policia['nombre']; ?></strong><br>
                                                <small class="text-muted"><?php echo $policia['grado'] . ' - CIN: ' . $policia['cin']; ?></small>
                                            </span>
                                            <span class="badge bg-primary posicion-badge">#<?php echo $policia['posicion']; ?></span>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php while ($lugar = $lugares_guardias->fetch_assoc()): ?>
                    <?php 
                        $policias_lugar = obtenerPoliciasPorLugar($conn, $lugar['id'], 5);
                        $total_policias = contarPoliciasPorLugar($conn, $lugar['id']);
                    ?>
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?php echo $lugar['nombre']; ?>
                                <?php if ($lugar['zona']): ?>
                                <small class="text-muted"> - <?php echo $lugar['zona']; ?></small>
                                <?php endif; ?>
                            </h5>
                            <span class="badge bg-secondary"><?php echo $total_policias; ?> policías</span>
                        </div>
                        <div class="card-body">
                            <?php if ($lugar['descripcion']): ?>
                            <p class="text-muted mb-3"><?php echo $lugar['descripcion']; ?></p>
                            <?php endif; ?>
                            
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Pos.</th>
                                            <th>CIN</th>
                                            <th>Nombre Completo</th>
                                            <th>Grado</th>
                                            <th>Región</th>
                                            <th>Teléfono</th>
                                            <th>Antigüedad</th>
                                            <th>Última Guardia</th>
                                            <th>Estado</th>
                                            <th>Próxima Disponible</th>
                                            <th class="no-print">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $contador_fila = 0;
                                        while ($policia = $policias_lugar->fetch_assoc()): 
                                            $contador_fila++;
                                            
                                            // Determinar si es guardia activa (primera posición disponible)
                                            $es_guardia_activa = ($contador_fila == 1 && $policia['disponibilidad'] == 'DISPONIBLE');
                                            
                                            // Determinar clase CSS para el estado
                                            $badge_class = '';
                                            switch($policia['disponibilidad']) {
                                                case 'AUSENTE':
                                                    $badge_class = 'badge-ausente';
                                                    break;
                                                case 'NO_DISPONIBLE_MES':
                                                    $badge_class = 'badge-no-disponible-mes';
                                                    break;
                                                case 'NO_DISPONIBLE_15_DIAS':
                                                    $badge_class = 'badge-no-disponible-15-dias';
                                                    break;
                                                case 'DISPONIBLE':
                                                    $badge_class = 'badge-disponible';
                                                    break;
                                            }
                                        ?>
                                        <tr class="<?php echo $es_guardia_activa ? 'table-warning' : ''; ?>">
                                            <td>
                                                <span class="badge <?php echo $es_guardia_activa ? 'bg-warning text-dark' : 'bg-primary'; ?> posicion-badge">
                                                    #<?php echo $policia['posicion']; ?>
                                                    <?php if ($es_guardia_activa): ?>
                                                    <i class="fas fa-star ms-1"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $policia['cin']; ?></td>
                                            <td>
                                                <?php echo $policia['apellido'] . ', ' . $policia['nombre']; ?>
                                                <?php if ($es_guardia_activa): ?>
                                                <span class="badge bg-warning text-dark ms-2">GUARDIA ACTUAL</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $policia['grado']; ?></td>
                                            <td>
                                                <span class="badge <?php echo $policia['region'] == 'REGIONAL' ? 'bg-info' : 'bg-secondary'; ?>">
                                                    <?php echo $policia['region']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $policia['telefono'] ?: '<span class="text-muted">No registrado</span>'; ?></td>
                                            <td><?php echo number_format($policia['antiguedad_dias']); ?> días</td>
                                            <td>
                                                <?php if ($policia['ultima_guardia_fecha']): ?>
                                                    <?php echo date('d/m/Y', strtotime($policia['ultima_guardia_fecha'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Nunca</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php 
                                                    switch($policia['disponibilidad']) {
                                                        case 'AUSENTE':
                                                            echo 'AUSENTE';
                                                            break;
                                                        case 'NO_DISPONIBLE_MES':
                                                            echo 'NO DISP. MES';
                                                            break;
                                                        case 'NO_DISPONIBLE_15_DIAS':
                                                            echo 'NO DISP. 15D';
                                                            break;
                                                        case 'DISPONIBLE':
                                                            echo 'DISPONIBLE';
                                                            break;
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?php echo $policia['proxima_fecha_disponible']; ?></small>
                                            </td>
                                            <td class="no-print">
                                                <?php if ($policia['disponibilidad'] == 'DISPONIBLE'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="rotar">
                                                    <input type="hidden" name="policia_id" value="<?php echo $policia['policia_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success" 
                                                            onclick="return confirm('¿Confirma que este policía realizó la guardia?')"
                                                            title="Marcar como guardia realizada">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <?php else: ?>
                                                <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if ($total_policias > 5): ?>
                            <div id="lugar_<?php echo $lugar['id']; ?>" class="collapse-content">
                                <hr>
                                <h6 class="text-muted">Policías adicionales:</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <tbody>
                                            <?php 
                                            $policias_adicionales = obtenerPoliciasPorLugar($conn, $lugar['id'], 1000);
                                            $contador = 0;
                                            while ($policia = $policias_adicionales->fetch_assoc()): 
                                                $contador++;
                                                if ($contador <= 5) continue;
                                                
                                                // Determinar clase CSS para el estado
                                                $badge_class = '';
                                                switch($policia['disponibilidad']) {
                                                    case 'AUSENTE':
                                                        $badge_class = 'badge-ausente';
                                                        break;
                                                    case 'NO_DISPONIBLE_MES':
                                                        $badge_class = 'badge-no-disponible-mes';
                                                        break;
                                                    case 'NO_DISPONIBLE_15_DIAS':
                                                        $badge_class = 'badge-no-disponible-15-dias';
                                                        break;
                                                    case 'DISPONIBLE':
                                                        $badge_class = 'badge-disponible';
                                                        break;
                                                }
                                            ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-primary posicion-badge">#<?php echo $policia['posicion']; ?></span>
                                                </td>
                                                <td><?php echo $policia['cin']; ?></td>
                                                <td><?php echo $policia['apellido'] . ', ' . $policia['nombre']; ?></td>
                                                <td><?php echo $policia['grado']; ?></td>
                                                <td>
                                                    <span class="badge <?php echo $policia['region'] == 'REGIONAL' ? 'bg-info' : 'bg-secondary'; ?>">
                                                        <?php echo $policia['region']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $policia['telefono'] ?: '<span class="text-muted">No registrado</span>'; ?></td>
                                                <td><?php echo number_format($policia['antiguedad_dias']); ?> días</td>
                                                <td>
                                                    <?php if ($policia['ultima_guardia_fecha']): ?>
                                                        <?php echo date('d/m/Y', strtotime($policia['ultima_guardia_fecha'])); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Nunca</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $badge_class; ?>">
                                                        <?php 
                                                        switch($policia['disponibilidad']) {
                                                            case 'AUSENTE':
                                                                echo 'AUSENTE';
                                                                break;
                                                            case 'NO_DISPONIBLE_MES':
                                                                echo 'NO DISP. MES';
                                                                break;
                                                            case 'NO_DISPONIBLE_15_DIAS':
                                                                echo 'NO DISP. 15D';
                                                                break;
                                                            case 'DISPONIBLE':
                                                                echo 'DISPONIBLE';
                                                                break;
                                                        }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo $policia['proxima_fecha_disponible']; ?></small>
                                                </td>
                                                <td class="no-print">
                                                    <?php if ($policia['disponibilidad'] == 'DISPONIBLE'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="rotar">
                                                        <input type="hidden" name="policia_id" value="<?php echo $policia['policia_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success" 
                                                                onclick="return confirm('¿Confirma que este policía realizó la guardia?')"
                                                                title="Marcar como guardia realizada">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="text-center mt-3">
                                <button class="btn btn-outline-secondary btn-sm collapse-toggle" 
                                        onclick="toggleCollapse('lugar_<?php echo $lugar['id']; ?>')">
                                    <i class="fas fa-chevron-down"></i> Ver todos los policías (<?php echo $total_policias; ?>)
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>

                   
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleCollapse(elementId) {
            const element = document.getElementById(elementId);
            const button = element.nextElementSibling.querySelector('.collapse-toggle');
            const icon = button.querySelector('i');
            
            if (element.classList.contains('show')) {
                element.classList.remove('show');
                icon.className = 'fas fa-chevron-down';
                button.innerHTML = '<i class="fas fa-chevron-down"></i> Ver todos los policías';
            } else {
                element.classList.add('show');
                icon.className = 'fas fa-chevron-up';
                button.innerHTML = '<i class="fas fa-chevron-up"></i> Ocultar policías adicionales';
            }
        }
    </script>
</body>
</html>

<!-- Botones de Acción -->
<div class="mb-4 no-print">
    <button type="button" class="btn btn-danger btn-lg" onclick="generarGuardia('generar_guardia_actual')">
        <i class="fas fa-shield-alt"></i> Generar Guardia
    </button>
    
    <button type="button" class="btn btn-success btn-lg" onclick="generarGuardia('generar_guardia_semanal')" style="margin-left: 10px;">
        <i class="fas fa-calendar-week"></i> Generar Guardia Semanal
    </button>
    
    <form method="POST" style="display: inline; margin-left: 10px;">
        <input type="hidden" name="action" value="reorganizar">
        <button type="submit" class="btn btn-info" onclick="return confirm('¿Está seguro de reorganizar la lista de guardias?')">
            <i class="fas fa-sync-alt"></i> Resetear Guardias
        </button>
    </form>
</div>

<script>
function generarGuardia(action) {
    let mensaje = '';
    if (action === 'generar_guardia_actual') {
        mensaje = '¿Está seguro de generar la guardia actual? Esto seleccionará 1 policía de cada lugar de guardia y los moverá al final de su lista.';
    } else if (action === 'generar_guardia_semanal') {
        mensaje = '¿Está seguro de generar la guardia semanal? Esto seleccionará 7 policías de cada lugar de guardia (uno por día) y los moverá al final de su lista.';
    }
    
    if (confirm(mensaje)) {
        // Crear formulario temporal
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'generar_pdf.php';
        form.target = '_blank'; // Abrir en nueva ventana
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'action';
        input.value = action;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
        
        // Mostrar mensaje de éxito
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
}
</script>