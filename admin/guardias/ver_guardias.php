<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../cnx/db_connect.php';
require_once __DIR__ . '/../../lib/fpdf/fpdf.php';

// Verificar rol del usuario
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

// Si se accede directamente con fecha (sin otros parámetros), generar PDF automáticamente
if ($fecha_guardia && $fecha_guardia != date('Y-m-d') && !isset($_GET['pdf']) && !isset($_GET['view'])) {
    $generar_pdf = true;
}
$es_domingo = (date('w', strtotime($fecha_guardia)) == 0);

// IMPORTANTE: Obtener datos ANTES de cualquier salida HTML para PDF

// Verificar si existe una guardia para esta fecha
$stmt = $conn->prepare("
    SELECT gg.*
    FROM guardias_generadas gg
    WHERE gg.fecha_guardia = ?
");
$stmt->execute([$fecha_guardia]);
$guardia = $stmt->fetch();

if (!$guardia) {
    // No hay guardia para esta fecha
    $personal_asignado = [];
} else {
    // Obtener el personal asignado
    $stmt = $conn->prepare("
        SELECT 
            ggd.posicion_asignacion,
            p.legajo,
            p.nombre,
            p.apellido,
            p.cin,
            g.nombre as grado,
            g.nivel_jerarquia,
            e.nombre as especialidad,
            p.comisionamiento,
            lg.nombre as lugar_guardia,
            lg.zona as region
        FROM guardias_generadas_detalle ggd
        JOIN guardias_generadas gg ON ggd.guardia_generada_id = gg.id
        JOIN policias p ON ggd.policia_id = p.id
        LEFT JOIN grados g ON p.grado_id = g.id
        LEFT JOIN especialidades e ON p.especialidad_id = e.id
        LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
        WHERE gg.fecha_guardia = ?
        ORDER BY ggd.posicion_asignacion
    ");
    $stmt = $conn->prepare("
        SELECT 
            ggd.posicion_asignacion,
            p.legajo,
            p.nombre,
            p.apellido,
            p.cin,
            p.telefono,
            g.nombre as grado,
            g.nivel_jerarquia,
            e.nombre as especialidad,
            p.comisionamiento,
            lg.nombre as lugar_guardia,
            lg.zona as region
        FROM guardias_generadas_detalle ggd
        JOIN guardias_generadas gg ON ggd.guardia_generada_id = gg.id
        JOIN policias p ON ggd.policia_id = p.id
        LEFT JOIN grados g ON p.grado_id = g.id
        LEFT JOIN especialidades e ON p.especialidad_id = e.id
        LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
        WHERE gg.fecha_guardia = ?
        ORDER BY g.nivel_jerarquia DESC, p.legajo DESC
    ");
    $stmt->execute([$fecha_guardia]);
    $personal_asignado = $stmt->fetchAll();
}

// Definir los puestos según el día
$puestos_requeridos = [
    1 => 'JEFE DE SERVICIO',
    2 => 'JEFE DE CUARTEL', 
    3 => 'OFICIAL DE GUARDIA',
    4 => $es_domingo ? 'NÚMERO DE GUARDIA 1' : 'ATENCIÓN TELEFÓNICA EXCLUSIVA',
    5 => 'NÚMERO DE GUARDIA ' . ($es_domingo ? '2' : '1'),
    6 => 'NÚMERO DE GUARDIA ' . ($es_domingo ? '3' : '2'),
    7 => 'NÚMERO DE GUARDIA ' . ($es_domingo ? '4' : '3'),
    8 => $es_domingo ? 'CONDUCTOR DE GUARDIA' : 'NÚMERO DE GUARDIA 4',
    9 => $es_domingo ? 'DE 06:30 HORAS A 22:00 HS GUARDIA Y 22:00 HS AL LLAMADO HASTA 07:00 HS DEL DÍA SIGUIENTE' : 'CONDUCTOR DE GUARDIA',
    10 => $es_domingo ? 'TENIDA: DE REGLAMENTO CON PLACA IDENTIFICATORIA' : 'DE 06:30 HORAS A 22:00 HS GUARDIA Y 22:00 HS AL LLAMADO HASTA 07:00 HS DEL DÍA SIGUIENTE',
    11 => $es_domingo ? 'SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE 1' : 'TENIDA: DE REGLAMENTO CON PLACA IDENTIFICATORIA',
    12 => $es_domingo ? 'SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE 2' : 'SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE 1',
    13 => $es_domingo ? 'SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE 3' : 'SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE 2',
    14 => !$es_domingo ? 'SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE 3' : null
];

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
    $pdf->SetFont('Arial', 'B', 16);
    
    // Encabezado
    $pdf->Cell(0, 10, convertToLatin1('ORDEN DEL DÍA'), 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, convertToLatin1('Fecha: ' . date('d/m/Y', strtotime($fecha_guardia))), 0, 1, 'C');
    $pdf->Cell(0, 8, convertToLatin1('Región: ' . $guardia['region']), 0, 1, 'C');
    $pdf->Cell(0, 8, convertToLatin1('Generada: ' . date('d/m/Y H:i')), 0, 1, 'C');
    $pdf->Ln(10);

    // Generar contenido para cada puesto individual (igual que la vista previa)
    foreach ($puestos_requeridos as $posicion => $puesto_nombre) {
        // Título del puesto
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(0, 123, 255); // Color azul como en la vista previa
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 8, convertToLatin1($puesto_nombre), 1, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        
        if (isset($personal_por_posicion[$posicion]) && !empty($personal_por_posicion[$posicion])) {
            $persona = $personal_por_posicion[$posicion][0]; // Tomar el primer elemento del array
            
            // Información del oficial
            $pdf->SetFont('Arial', 'B', 9);
            $nombre_completo = $persona['nombre'] . ' ' . $persona['apellido'];
            $pdf->Cell(0, 6, convertToLatin1('  ' . $nombre_completo), 0, 1, 'L');
            
            // Grado y región
            $pdf->SetFont('Arial', '', 8);
            $grado_region = 'Grado: ' . $persona['grado'] . ' | Region: ' . $persona['region'];
            $pdf->Cell(0, 5, convertToLatin1('  ' . $grado_region), 0, 1, 'L');
            
            // Detalles
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(0, 4, convertToLatin1('  Legajo: ' . $persona['legajo']), 0, 1, 'L');
            $pdf->Cell(0, 4, convertToLatin1('  CIN: ' . $persona['cin']), 0, 1, 'L');
            $pdf->Cell(0, 4, convertToLatin1('  Telefono: ' . ($persona['telefono'] ?: 'No registrado')), 0, 1, 'L');
            
            if ($persona['especialidad']) {
                $pdf->Cell(0, 4, convertToLatin1('  Especialidad: ' . $persona['especialidad']), 0, 1, 'L');
            }
            
            if ($persona['comisionamiento']) {
                $pdf->Cell(0, 4, convertToLatin1('  Comisionamiento: ' . $persona['comisionamiento']), 0, 1, 'L');
            }
            
            $pdf->Cell(0, 4, convertToLatin1('  Sector: ' . $puesto_nombre), 0, 1, 'L');
            
        } else {
            // Sin asignar
            $pdf->SetFont('Arial', 'I', 9);
            $pdf->SetFillColor(248, 249, 250); // Color gris claro
            $pdf->Cell(0, 20, convertToLatin1('Sin asignar'), 1, 1, 'C', true);
        }
        
        $pdf->Ln(3); // Espacio entre puestos
    }
    
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
                <button class="btn btn-warning" onclick="regenerarGuardia()">
                    <i class="fas fa-sync-alt me-2"></i>Regenerar Guardia
                </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function regenerarGuardia() {
            if (confirm('¿Está seguro de que desea regenerar la guardia? Esto eliminará la asignación actual.')) {
                window.location.href = 'generar_guardia_interface.php?fecha=<?php echo $fecha_guardia; ?>&regenerar=1';
            }
        }
    </script>
</body>
</html>