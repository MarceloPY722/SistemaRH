<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../cnx/db_connect.php';

// Las ausencias por Junta Médica no pueden ser revertidas una vez aprobadas

// Obtener ausencias por Junta Médica
$sql_junta = "SELECT a.*, p.nombre, p.apellido, p.cin, p.legajo, g.nombre as grado,
                     u.nombre_completo as aprobado_por_nombre,
                     lg_principal.nombre as lugar_principal,
                     lg_reserva.nombre as lugar_reserva,
                     ojmt.orden_anotacion,
                     CASE 
                         WHEN a.estado = 'APROBADA' AND ojmt.activo = 1 THEN 'ACTIVO EN TELEFONISTA'
                         WHEN a.estado = 'COMPLETADA' THEN 'COMPLETADA'
                         ELSE a.estado
                     END as estado_junta
              FROM ausencias a
              JOIN policias p ON a.policia_id = p.id
              LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
              LEFT JOIN grados g ON tg.grado_id = g.id
              JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id
              LEFT JOIN usuarios u ON a.aprobado_por = u.id
              LEFT JOIN lugares_guardias lg_principal ON p.lugar_guardia_id = lg_principal.id
              LEFT JOIN lugares_guardias lg_reserva ON p.lugar_guardia_reserva_id = lg_reserva.id
              LEFT JOIN orden_junta_medica_telefonista ojmt ON a.id = ojmt.ausencia_id
              WHERE ta.nombre = 'Junta Medica'
              ORDER BY a.created_at DESC";
$result_junta = $conn->prepare($sql_junta);
$result_junta->execute();

// Obtener estadísticas de Junta Médica
$sql_stats_junta = "SELECT 
                    (SELECT COUNT(*) FROM ausencias a JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id WHERE ta.nombre = 'Junta Medica' AND a.estado = 'PENDIENTE') as pendientes,
                    (SELECT COUNT(*) FROM ausencias a JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id WHERE ta.nombre = 'Junta Medica' AND a.estado = 'APROBADA') as activos,
                    (SELECT COUNT(*) FROM ausencias a JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id WHERE ta.nombre = 'Junta Medica' AND a.estado = 'COMPLETADA') as completados,
                    (SELECT COUNT(*) FROM orden_junta_medica_telefonista WHERE activo = 1) as en_telefonista";
$result_stats_junta = $conn->prepare($sql_stats_junta);
$result_stats_junta->execute();
$stats_junta = $result_stats_junta->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial Junta Médica - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #104c75;
            --secondary-color: #0d3d5c;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-bg: #f8f9fa;
            --medical-color: #8e44ad;
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            background: var(--light-bg);
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, var(--medical-color) 0%, #9b59b6 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(142, 68, 173, 0.3);
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .pendientes { color: var(--warning-color); }
        .activos { color: var(--medical-color); }
        .completados { color: var(--success-color); }
        .telefonista { color: var(--primary-color); }

        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 20px;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8f9fa;
            border: none;
            font-weight: 600;
            color: #495057;
            padding: 15px;
        }

        .table td {
            padding: 15px;
            vertical-align: middle;
            border-color: #e9ecef;
        }

        .badge {
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-pendiente {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-activo {
            background-color: #e7e3ff;
            color: #6f42c1;
        }

        .badge-completado {
            background-color: #d1edff;
            color: #0c63e4;
        }

        .btn {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .orden-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../inc/sidebar.php'; ?>
            
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Mensajes -->
                <?php if (isset($_SESSION['mensaje'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['tipo_mensaje']; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $_SESSION['tipo_mensaje'] == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo $_SESSION['mensaje']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
                <?php endif; ?>

                <!-- Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="mb-2">
                                <i class="fas fa-user-md me-3"></i>
                                Historial Junta Médica
                            </h1>
                            <p class="mb-0 opacity-75">Gestión de ausencias por Junta Médica y asignación a Telefonista</p>
                        </div>
                        <div>
                            <a href="index.php" class="btn btn-light me-2">
                                <i class="fas fa-arrow-left me-1"></i>
                                Volver a Ausencias
                            </a>
                            <a href="agregar_ausencia.php" class="btn btn-warning">
                                <i class="fas fa-plus me-1"></i>
                                Nueva Ausencia
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-icon pendientes">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number pendientes"><?php echo $stats_junta['pendientes']; ?></div>
                        <div class="stat-label">Pendientes</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon activos">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-number activos"><?php echo $stats_junta['activos']; ?></div>
                        <div class="stat-label">Activos</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon telefonista">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="stat-number telefonista"><?php echo $stats_junta['en_telefonista']; ?></div>
                        <div class="stat-label">En Telefonista</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon completados">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-number completados"><?php echo $stats_junta['completados']; ?></div>
                        <div class="stat-label">Completados</div>
                    </div>
                </div>

                <!-- Tabla de Ausencias por Junta Médica -->
                <div class="table-container">
                    <div class="table-header">
                        <h4 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Registro de Ausencias por Junta Médica
                        </h4>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Policía</th>
                                    <th>Grado</th>
                                    <th>Lugar Original</th>
                                    <th>Fecha Inicio</th>
                                    <th>Orden Telefonista</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result_junta->rowCount() > 0): ?>
                                    <?php while ($ausencia = $result_junta->fetch(PDO::FETCH_ASSOC)): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($ausencia['apellido'] . ', ' . $ausencia['nombre']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">CI: <?php echo htmlspecialchars($ausencia['cin']); ?> | Legajo: <?php echo htmlspecialchars($ausencia['legajo']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($ausencia['grado'] ?? 'N/A'); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($ausencia['lugar_principal'] ?? 'N/A'); ?></td>
                                            <td>
                                                <i class="fas fa-calendar-alt me-1"></i>
                                                <?php echo date('d/m/Y', strtotime($ausencia['fecha_inicio'])); ?>
                                            </td>
                                            <td>
                                                <?php if ($ausencia['orden_anotacion']): ?>
                                                    <span class="orden-badge">#<?php echo $ausencia['orden_anotacion']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $estado_class = '';
                                                switch ($ausencia['estado_junta']) {
                                                    case 'PENDIENTE':
                                                        $estado_class = 'badge-pendiente';
                                                        break;
                                                    case 'ACTIVO EN TELEFONISTA':
                                                        $estado_class = 'badge-activo';
                                                        break;
                                                    case 'COMPLETADA':
                                                        $estado_class = 'badge-completado';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $estado_class; ?>">
                                                    <?php echo $ausencia['estado_junta']; ?>
                                                </span>
                                            </td>

                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-muted mb-0">No hay ausencias por Junta Médica registradas</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>