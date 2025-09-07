<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../cnx/db_connect.php';

// Verificar que se proporcione un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['mensaje'] = 'ID de ausencia no válido';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: index.php');
    exit();
}

$ausencia_id = $_GET['id'];

// Obtener datos completos de la ausencia
$sql_ausencia = "SELECT a.*, p.nombre, p.apellido, p.cin, p.legajo, g.nombre as grado, 
                        ta.nombre as tipo_ausencia, ta.descripcion as tipo_descripcion,
                        u_aprobado.nombre_completo as aprobado_por_nombre,
                        lg_principal.nombre as lugar_principal
                 FROM ausencias a
                 JOIN policias p ON a.policia_id = p.id
                 LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
                 LEFT JOIN grados g ON tg.grado_id = g.id
                 JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id
                 LEFT JOIN usuarios u_aprobado ON a.aprobado_por = u_aprobado.id
                 LEFT JOIN lugares_guardias lg_principal ON p.lugar_guardia_id = lg_principal.id
                 WHERE a.id = ?";
$stmt_ausencia = $conn->prepare($sql_ausencia);
$stmt_ausencia->execute([$ausencia_id]);
$ausencia = $stmt_ausencia->fetch(PDO::FETCH_ASSOC);

if (!$ausencia) {
    $_SESSION['mensaje'] = 'Ausencia no encontrada';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: index.php');
    exit();
}

// Calcular duración de la ausencia
$fecha_inicio = new DateTime($ausencia['fecha_inicio']);
$fecha_fin = $ausencia['fecha_fin'] ? new DateTime($ausencia['fecha_fin']) : $fecha_inicio;
$duracion = $fecha_inicio->diff($fecha_fin)->days + 1;

// Información de posición no disponible
$posicion_info = null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Ausencia - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <style>
        .info-card {
            border-left: 4px solid #007bff;
            background-color: #f8f9fa;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pendiente {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-aprobada {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-rechazada {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-completada {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .timeline-item {
            border-left: 3px solid #dee2e6;
            padding-left: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #007bff;
        }
        
        .print-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .print-btn {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="no-print">
                <?php include '../inc/sidebar.php'; ?>
            </div>
            
            <!-- Contenido Principal -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Header -->
                    <div class="page-header no-print">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1><i class="fas fa-eye me-2"></i>Detalles de Ausencia</h1>
                                <p>Informe completo de la ausencia</p>
                            </div>
                            <div>
                                <a href="index.php" class="btn btn-secondary me-2">
                                    <i class="fas fa-arrow-left me-1"></i>Volver
                                </a>
                                <a href="editar_ausencia.php?id=<?php echo $ausencia_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-edit me-1"></i>Editar
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Información del Policía -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Información del Policía</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Nombre Completo:</strong> <?php echo htmlspecialchars($ausencia['apellido'] . ', ' . $ausencia['nombre']); ?></p>
                                    <p><strong>Cédula de Identidad:</strong> <?php echo htmlspecialchars($ausencia['cin']); ?></p>
                                    <p><strong>Legajo:</strong> <?php echo htmlspecialchars($ausencia['legajo']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Grado:</strong> <?php echo htmlspecialchars($ausencia['grado']); ?></p>
                                    <p><strong>Lugar de Guardia:</strong> <?php echo htmlspecialchars($ausencia['lugar_principal']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detalles de la Ausencia -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Detalles de la Ausencia</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Tipo de Ausencia:</strong> <?php echo htmlspecialchars($ausencia['tipo_ausencia']); ?></p>
                                    <?php if ($ausencia['tipo_descripcion']): ?>
                                    <p><strong>Descripción del Tipo:</strong> <?php echo htmlspecialchars($ausencia['tipo_descripcion']); ?></p>
                                    <?php endif; ?>
                                    <p><strong>Estado:</strong> 
                                        <span class="status-badge status-<?php echo strtolower($ausencia['estado']); ?>">
                                            <?php echo $ausencia['estado']; ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Fecha de Inicio:</strong> <?php echo date('d/m/Y', strtotime($ausencia['fecha_inicio'])); ?></p>
                                    <?php if ($ausencia['fecha_fin']): ?>
                                    <p><strong>Fecha de Fin:</strong> <?php echo date('d/m/Y', strtotime($ausencia['fecha_fin'])); ?></p>
                                    <?php else: ?>
                                    <p><strong>Fecha de Fin:</strong> <span class="text-muted">Ausencia de un solo día</span></p>
                                    <?php endif; ?>
                                    <p><strong>Duración:</strong> <?php echo $duracion; ?> día<?php echo $duracion > 1 ? 's' : ''; ?></p>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row">
                                <div class="col-12">
                                    <p><strong>Descripción:</strong></p>
                                    <div class="info-card p-3 mb-3">
                                        <?php echo nl2br(htmlspecialchars($ausencia['descripcion'] ?? '')); ?>
                                    </div>
                                    
                                    <?php if (!empty($ausencia['justificacion'])): ?>
                                    <p><strong>Justificación:</strong></p>
                                    <div class="info-card p-3">
                                        <?php echo nl2br(htmlspecialchars($ausencia['justificacion'])); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>



                    <!-- Historial de Estados -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Historial de Estados</h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline-item">
                                <h6>Ausencia Creada</h6>
                                <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($ausencia['created_at'])); ?></p>
                            </div>
                            
                            <?php if ($ausencia['estado'] != 'PENDIENTE'): ?>
                            <div class="timeline-item">
                                <h6>Estado: <?php echo $ausencia['estado']; ?></h6>
                                <?php if ($ausencia['aprobado_por_nombre']): ?>
                                <p><strong>Procesado por:</strong> <?php echo htmlspecialchars($ausencia['aprobado_por_nombre']); ?></p>
                                <?php endif; ?>
                                <?php if ($ausencia['updated_at']): ?>
                                <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($ausencia['updated_at'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Resumen del Informe -->
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Resumen del Informe</h5>
                        </div>
                        <div class="card-body">
                            <p>El <?php echo htmlspecialchars($ausencia['grado'] . ' ' . $ausencia['apellido'] . ', ' . $ausencia['nombre']); ?> 
                            (CI: <?php echo htmlspecialchars($ausencia['cin']); ?>, Legajo: <?php echo htmlspecialchars($ausencia['legajo']); ?>) 
                            solicitó una ausencia de tipo "<?php echo htmlspecialchars($ausencia['tipo_ausencia']); ?>" 
                            desde el <?php echo date('d/m/Y', strtotime($ausencia['fecha_inicio'])); ?>
                            <?php if ($ausencia['fecha_fin']): ?>
                            hasta el <?php echo date('d/m/Y', strtotime($ausencia['fecha_fin'])); ?>
                            <?php endif; ?>
                            por el siguiente motivo: <?php echo htmlspecialchars($ausencia['descripcion'] ?? 'No especificado'); ?>.</p>
                            
                            <p>La ausencia fue registrada el <?php echo date('d/m/Y', strtotime($ausencia['created_at'])); ?> 
                            y actualmente se encuentra en estado <strong><?php echo $ausencia['estado']; ?></strong>.</p>
                            

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Botón de Imprimir -->
    <button class="btn btn-primary print-btn" onclick="window.print()">
        <i class="fas fa-print me-1"></i>Imprimir
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>