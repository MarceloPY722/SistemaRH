<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';
require_once '../inc/header.php';

$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

$stmt = $conn->prepare("
    SELECT 
        g.id,
        g.fecha,
        g.hora_inicio,
        g.hora_fin,
        g.asistio,
        g.observaciones,
        p.nombre_apellido as policía,
        p.legajo,
        p.dni,
        gr.nombre as grado,
        esp.nombre as especialidad,
        lg.nombre as lugar_guardia,
        z.nombre as zona,
        r.nombre as region
    FROM guardias g
    JOIN policias p ON g.policia_id = p.id
    LEFT JOIN grados gr ON p.grado_id = gr.id
    LEFT JOIN especialidades esp ON p.especialidad_id = esp.id
    JOIN lugares_guardia lg ON g.lugar_guardia_id = lg.id
    JOIN zonas z ON lg.zona_id = z.id
    JOIN regiones r ON z.region_id = r.id
    WHERE g.fecha = ?
    ORDER BY g.hora_inicio, p.nombre_apellido
");

$stmt->execute([$fecha]);
$guardias = $stmt->fetchAll();

$stats = $conn->prepare("
    SELECT 
        COUNT(*) as total_guardias,
        SUM(CASE WHEN asistio = 1 THEN 1 ELSE 0 END) as asistieron,
        SUM(CASE WHEN asistio = 0 THEN 1 ELSE 0 END) as faltaron,
        COUNT(DISTINCT lugar_guardia_id) as lugares_cubiertos
    FROM guardias 
    WHERE fecha = ?
");
$stats->execute([$fecha]);
$estadisticas = $stats->fetch();

$lugares_sin_cubrir = $conn->prepare("
    SELECT lg.nombre, z.nombre as zona, r.nombre as region
    FROM lugares_guardia lg
    JOIN zonas z ON lg.zona_id = z.id
    JOIN regiones r ON z.region_id = r.id
    WHERE lg.id NOT IN (
        SELECT DISTINCT lugar_guardia_id 
        FROM guardias 
        WHERE fecha = ?
    )
    ORDER BY r.nombre, z.nombre, lg.nombre
");
$lugares_sin_cubrir->execute([$fecha]);
$lugares_vacios = $lugares_sin_cubrir->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden del Día - <?php echo date('d/m/Y', strtotime($fecha)); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #104c75;
            --secondary-color: #1a6a9c;
            --accent-color: #ff6b6b;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .orden-dia-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .orden-dia-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .orden-dia-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 0;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
            transition: transform 0.2s ease-in-out;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0;
        }

        .guardia-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .guardia-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .guardia-header {
            background: linear-gradient(135deg, var(--info-color) 0%, #138496 100%);
            color: white;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }

        .guardia-body {
            padding: 1.5rem;
        }

        .policia-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .policia-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .policia-details h5 {
            margin-bottom: 0.25rem;
            color: var(--primary-color);
            font-weight: 600;
        }

        .policia-details p {
            margin-bottom: 0.25rem;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .guardia-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-item i {
            color: var(--primary-color);
            width: 20px;
        }

        .asistio-badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
        }

        .badge-success-custom {
            background: linear-gradient(135deg, var(--success-color) 0%, #218838 100%);
            color: white;
        }

        .badge-danger-custom {
            background: linear-gradient(135deg, var(--accent-color) 0%, #dc3545 100%);
            color: white;
        }

        .lugares-vacios {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffc107;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .lugares-vacios h5 {
            color: #856404;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .lugar-vacio-item {
            background: rgba(255, 255, 255, 0.7);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            border-left: 4px solid #ffc107;
        }

        .btn-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-custom:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            color: white;
        }

        .date-selector {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            
            .guardia-card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #dee2e6;
            }
        }
    </style>
</head>
<body>
    <?php require_once '../inc/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-4">
            <!-- Selector de Fecha -->
            <div class="date-selector no-print">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <label for="fechaSelector" class="form-label fw-bold">
                            <i class="fas fa-calendar me-2"></i>Seleccionar Fecha:
                        </label>
                        <input type="date" class="form-control" id="fechaSelector" value="<?php echo $fecha; ?>">
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-custom mt-4" onclick="actualizarFecha()">
                            <i class="fas fa-search me-2"></i>Actualizar
                        </button>
                        <button class="btn btn-outline-secondary mt-4 ms-2" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Imprimir
                        </button>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="index.php" class="btn btn-outline-primary mt-4">
                            <i class="fas fa-chart-line me-2"></i>Ver Reportes
                        </a>
                    </div>
                </div>
            </div>

            <!-- Encabezado del Orden del Día -->
            <div class="orden-dia-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="orden-dia-title">
                            <i class="fas fa-clipboard-list me-3"></i>Orden del Día
                        </h1>
                        <p class="orden-dia-subtitle">
                            <i class="fas fa-calendar-day me-2"></i><?php echo date('d/m/Y', strtotime($fecha)); ?>
                            <span class="ms-4">
                                <i class="fas fa-clock me-2"></i><?php echo date('H:i'); ?> hs
                            </span>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="orden-dia-logo">
                            <i class="fas fa-shield-alt" style="font-size: 4rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estadísticas Generales -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stats-card text-center">
                        <div class="stat-number text-primary"><?php echo $estadisticas['total_guardias']; ?></div>
                        <div class="stat-label">Total Guardias</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card text-center">
                        <div class="stat-number text-success"><?php echo $estadisticas['asistieron']; ?></div>
                        <div class="stat-label">Asistieron</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card text-center">
                        <div class="stat-number text-danger"><?php echo $estadisticas['faltaron']; ?></div>
                        <div class="stat-label">Faltaron</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card text-center">
                        <div class="stat-number text-info"><?php echo $estadisticas['lugares_cubiertos']; ?></div>
                        <div class="stat-label">Lugares Cubiertos</div>
                    </div>
                </div>
            </div>

            <!-- Guardias del Día -->
            <div class="mb-4">
                <h3 class="mb-4 text-primary">
                    <i class="fas fa-users me-2"></i>Guardias Asignadas
                </h3>

                <?php if (count($guardias) > 0): ?>
                    <?php foreach ($guardias as $guardia): ?>
                        <div class="guardia-card">
                            <div class="guardia-header">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <i class="fas fa-map-marker-alt me-2"></i>
                                        <strong><?php echo htmlspecialchars($guardia['lugar_guardia']); ?></strong>
                                        <small class="ms-2">(<?php echo htmlspecialchars($guardia['zona']); ?>)</small>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <i class="fas fa-clock me-2"></i>
                                        <?php echo htmlspecialchars($guardia['hora_inicio']); ?> - <?php echo htmlspecialchars($guardia['hora_fin']); ?> hs
                                        <?php if ($guardia['asistio'] == 1): ?>
                                            <span class="asistio-badge badge-success-custom ms-2">ASISTIÓ</span>
                                        <?php else: ?>
                                            <span class="asistio-badge badge-danger-custom ms-2">NO ASISTIÓ</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="guardia-body">
                                <div class="policia-info">
                                    <div class="policia-avatar">
                                        <?php echo strtoupper(substr($guardia['policía'], 0, 1)); ?>
                                    </div>
                                    <div class="policia-details">
                                        <h5><?php echo htmlspecialchars($guardia['policía']); ?></h5>
                                        <p><i class="fas fa-id-card me-2"></i>DNI: <?php echo htmlspecialchars($guardia['dni']); ?></p>
                                        <p><i class="fas fa-hashtag me-2"></i>Legajo: <?php echo htmlspecialchars($guardia['legajo']); ?></p>
                                    </div>
                                </div>
                                <div class="guardia-details">
                                    <div class="detail-item">
                                        <i class="fas fa-star"></i>
                                        <span><strong>Grado:</strong> <?php echo htmlspecialchars($guardia['grado'] ?? 'No especificado'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-cogs"></i>
                                        <span><strong>Especialidad:</strong> <?php echo htmlspecialchars($guardia['especialidad'] ?? 'No especificada'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-map-marked-alt"></i>
                                        <span><strong>Región:</strong> <?php echo htmlspecialchars($guardia['region']); ?></span>
                                    </div>
                                </div>
                                <?php if (!empty($guardia['observaciones'])): ?>
                                    <div class="mt-3 p-3 bg-light rounded">
                                        <small class="text-muted">
                                            <i class="fas fa-comment me-2"></i>
                                            <strong>Observaciones:</strong> <?php echo htmlspecialchars($guardia['observaciones']); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No hay guardias asignadas para esta fecha.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Lugares sin Cubrir -->
            <?php if (count($lugares_vacios) > 0): ?>
                <div class="lugares-vacios">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Lugares sin Cubrir</h5>
                    <div class="row">
                        <?php foreach ($lugares_vacios as $lugar): ?>
                            <div class="col-md-4">
                                <div class="lugar-vacio-item">
                                    <strong><?php echo htmlspecialchars($lugar['nombre']); ?></strong><br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($lugar['zona']); ?> - <?php echo htmlspecialchars($lugar['region']); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Pie de página -->
            <div class="mt-5 text-center text-muted">
                <hr>
                <small>
                    <i class="fas fa-file-alt me-2"></i>
                    Orden del Día generado por Sistema RH - <?php echo date('d/m/Y H:i'); ?> hs
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function actualizarFecha() {
            const fecha = document.getElementById('fechaSelector').value;
            if (fecha) {
                window.location.href = `orden_dia.php?fecha=${fecha}`;
            }
        }

        // Enter key support for date selector
        document.getElementById('fechaSelector').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                actualizarFecha();
            }
        });

        // Auto-update on date change
        document.getElementById('fechaSelector').addEventListener('change', function() {
            actualizarFecha();
        });
    </script>
</body>
</html>