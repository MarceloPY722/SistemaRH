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
            lg_lista.ultima_guardia_fecha,
            lg_lista.posicion
        FROM lugares_guardias lg
        LEFT JOIN (
            SELECT 
                p.id,
                p.nombre,
                p.apellido,
                p.cin,
                p.telefono,
                p.region,
                g.nombre as grado,
                p.lugar_guardia_id,
                lg.posicion,
                lg.ultima_guardia_fecha,
                ROW_NUMBER() OVER (PARTITION BY p.lugar_guardia_id ORDER BY lg.posicion) as rn
            FROM lista_guardias lg
            JOIN policias p ON lg.policia_id = p.id
            JOIN grados g ON p.grado_id = g.id
            WHERE p.activo = TRUE
            AND p.lugar_guardia_id IS NOT NULL
            AND NOT EXISTS (
                SELECT 1 FROM ausencias a 
                WHERE a.policia_id = p.id 
                AND a.estado = 'APROBADA' 
                AND '$fechaActual' BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, '$fechaActual')
            )
            AND (
                (p.region = 'CENTRAL' AND (
                    lg.ultima_guardia_fecha IS NULL OR 
                    DATEDIFF('$fechaActual', lg.ultima_guardia_fecha) >= 15
                ))
                OR
                (p.region = 'REGIONAL' AND (
                    lg.ultima_guardia_fecha IS NULL OR 
                    DATE_FORMAT(lg.ultima_guardia_fecha, '%Y-%m') != DATE_FORMAT('$fechaActual', '%Y-%m')
                ))
            )
        ) lg_lista ON lg.id = lg_lista.lugar_guardia_id AND lg_lista.rn = 1
        WHERE lg.activo = TRUE
        ORDER BY lg.id";
    
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
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM lista_guardias lg
        JOIN policias p ON lg.policia_id = p.id
        WHERE p.activo = TRUE AND p.lugar_guardia_id = ?
    ");
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
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(45deg, #104c75, #0d3d5c) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background: linear-gradient(45deg, #104c75, #0d3d5c);
            border: none;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .main-content {
            padding: 30px;
        }
        .page-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 30px;
        }
        .table th {
            background-color: #34495e;
            color: white;
            border: none;
        }
        .posicion-badge {
            font-size: 1rem;
            padding: 6px 10px;
        }
        .lugar-card {
            border-left: 4px solid #104c75;
        }
        .lugar-header {
            background: linear-gradient(45deg, #104c75, #0d3d5c);
            color: white;
        }
        .guardia-general-card {
            border: 2px solid #28a745;
            background: linear-gradient(45deg, #28a745, #20c997);
        }
        .guardia-actual-card {
            border: 2px solid #dc3545;
            background: linear-gradient(45deg, #dc3545, #fd7e14);
        }
        .collapse-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        .collapse-content.show {
            max-height: 1000px;
        }
        .guardia-activa {
            background-color: #fff3cd !important;
            border-left: 4px solid #ffc107 !important;
        }
        .guardia-activa .posicion-badge {
            background-color: #ffc107 !important;
            color: #000 !important;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
        }
        .print-only { display: none; }
        
        /* Estilos para estados de disponibilidad */
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
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="generar_guardia_actual">
                            <button type="submit" class="btn btn-danger btn-lg" onclick="return confirm('¿Está seguro de generar la guardia actual? Esto seleccionará 1 policía de cada lugar de guardia y los moverá al final de su lista.')">
                                <i class="fas fa-shield-alt"></i> Generar Guardia
                            </button>
                        </form>
                        
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
                    <div class="card lugar-card">
                        <div class="card-header lugar-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo $lugar['nombre']; ?>
                                    <span class="badge bg-light text-dark ms-2"><?php echo $total_policias; ?> policías</span>
                                </h5>
                                <?php if ($lugar['zona']): ?>
                                <small class="text-light"><i class="fas fa-location-arrow"></i> Zona: <?php echo $lugar['zona']; ?></small>
                                <?php endif; ?>
                            </div>
                            <?php if ($total_policias > 5): ?>
                            <button class="btn btn-outline-light btn-sm no-print" type="button" onclick="toggleCollapse('lugar_<?php echo $lugar['id']; ?>')">
                                <i class="fas fa-eye" id="icon_lugar_<?php echo $lugar['id']; ?>"></i> Ver más
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ($lugar['descripcion']): ?>
                            <p class="text-muted mb-3"><i class="fas fa-info-circle"></i> <?php echo $lugar['descripcion']; ?></p>
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
                                        <tr class="<?php echo $policia['disponibilidad'] != 'DISPONIBLE' ? 'table-warning' : ($es_guardia_activa ? 'guardia-activa' : ''); ?>">
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
                                            <tr class="<?php echo $policia['disponibilidad'] != 'DISPONIBLE' ? 'table-warning' : ''; ?>">
                                                <td>
                                                    <span class="badge bg-secondary posicion-badge">
                                                        #<?php echo $policia['posicion']; ?>
                                                    </span>
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
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>

                    <!-- Información adicional -->
                    <div class="row mt-4 no-print">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-secondary text-white">
                                    <h6><i class="fas fa-info-circle"></i> Información del Sistema</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="mb-0">
                                        <li><strong>Guardia Actual:</strong> La persona destacada en amarillo es quien tiene la guardia actualmente (primera en la lista disponible).</li>
                                        <li><strong>Restricciones por Región:</strong> 
                                            <ul>
                                                <li><span class="badge bg-info">REGIONAL</span>: Máximo 1 guardia por mes calendario</li>
                                                <li><span class="badge bg-secondary">CENTRAL</span>: Mínimo 15 días entre guardias</li>
                                            </ul>
                                        </li>
                                        <li><strong>Estados de Disponibilidad:</strong>
                                            <ul>
                                                <li><span class="badge badge-disponible">DISPONIBLE</span>: Puede realizar guardia</li>
                                                <li><span class="badge badge-ausente">AUSENTE</span>: Con ausencia aprobada</li>
                                                <li><span class="badge badge-no-disponible-mes">NO DISP. MES</span>: Ya realizó guardia este mes (REGIONAL)</li>
                                                <li><span class="badge badge-no-disponible-15-dias">NO DISP. 15D</span>: Menos de 15 días desde última guardia (CENTRAL)</li>
                                            </ul>
                                        </li>
                                        <li><strong>Organización por Lugares:</strong> Los policías están organizados por sus lugares de guardia asignados.</li>
                                        <li><strong>Rotación Automática:</strong> Al generar guardia, las personas seleccionadas pasan automáticamente al final de su lista.</li>
                                        <li><strong>Límite de Visualización:</strong> Se muestran máximo 5 policías por lugar inicialmente.</li>
                                        <li><strong>Orden FIFO:</strong> Mantiene el orden por jerarquía y antigüedad dentro de cada lugar.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleCollapse(elementId) {
            const element = document.getElementById(elementId);
            const icon = document.getElementById('icon_' + elementId);
            
            if (element.classList.contains('show')) {
                element.classList.remove('show');
                icon.className = 'fas fa-eye';
                icon.parentElement.innerHTML = '<i class="fas fa-eye"></i> Ver más';
            } else {
                element.classList.add('show');
                icon.className = 'fas fa-eye-slash';
                icon.parentElement.innerHTML = '<i class="fas fa-eye-slash"></i> Ver menos';
            }
        }
    </script>
</body>
</html>