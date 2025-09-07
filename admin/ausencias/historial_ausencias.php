<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../cnx/db_connect.php';

// Obtener historial de ausencias (ausencias que no están activas y NO son por Junta Médica)
$sql_historial = "SELECT 
    a.id,
    a.estado,
    a.fecha_inicio,
    a.fecha_fin,
    a.descripcion,
    a.created_at,
    a.updated_at,
    p.nombre,
    p.apellido,
    p.cin,
    g.nombre as grado,
    ta.nombre as tipo_ausencia,
    u.nombre_completo as aprobado_por,
    CASE 
        WHEN a.estado = 'APROBADA' THEN 'Completada'
        WHEN a.estado = 'RECHAZADA' THEN 'Rechazada'
        ELSE a.estado
    END as estado_final,
    CASE 
        WHEN a.fecha_fin < CURDATE() AND a.estado = 'APROBADA' THEN 'Finalizada'
        WHEN a.estado = 'RECHAZADA' THEN 'Rechazada'
        ELSE 'Completada'
    END as estado_historial
FROM ausencias a
JOIN policias p ON a.policia_id = p.id
LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
LEFT JOIN grados g ON tg.grado_id = g.id
JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id
LEFT JOIN usuarios u ON a.aprobado_por = u.id
WHERE a.estado IN ('APROBADA', 'RECHAZADA') AND ta.nombre != 'Junta Medica'
ORDER BY a.updated_at DESC, a.created_at DESC";

$result_historial = $conn->prepare($sql_historial);
$result_historial->execute();

// Obtener estadísticas del historial (excluyendo Junta Médica)
$sql_stats = "SELECT 
    (SELECT COUNT(*) FROM ausencias a JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id WHERE a.estado = 'APROBADA' AND ta.nombre != 'Junta Medica') as completadas,
    (SELECT COUNT(*) FROM ausencias a JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id WHERE a.estado = 'RECHAZADA' AND ta.nombre != 'Junta Medica') as rechazadas,
    (SELECT COUNT(*) FROM ausencias a JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id WHERE a.estado = 'APROBADA' AND a.fecha_fin < CURDATE() AND ta.nombre != 'Junta Medica') as finalizadas";

$result_stats = $conn->prepare($sql_stats);
$result_stats->execute();
$stats = $result_stats->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Ausencias - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #104c75;
            --secondary-color: #0d3d5c;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #17a2b8;
            --light-bg: #f8f9fa;
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            padding: 20px;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(16, 76, 117, 0.2);
        }

        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
        }

        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-completada {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rechazada {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-finalizada {
            background-color: #cce5ff;
            color: #004085;
        }

        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../inc/sidebar.php'; ?>
            
            <!-- Contenido Principal -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Mensajes de alerta -->
                    <?php if (isset($_SESSION['mensaje'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['tipo_mensaje']; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle me-2"></i><?php echo $_SESSION['mensaje']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php 
                    unset($_SESSION['mensaje']);
                    unset($_SESSION['tipo_mensaje']);
                    endif; ?>

                    <!-- Header -->
                    <div class="page-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1><i class="fas fa-history me-2"></i>Historial de Ausencias</h1>
                                <p>Registro completo de ausencias finalizadas, rechazadas o completadas (excluyendo Junta Médica)</p>
                            </div>
                            <div>
                                <a href="index.php" class="btn btn-primary me-2">
                                    <i class="fas fa-arrow-left me-1"></i>Volver a Ausencias
                                </a>
                                <a href="historial_junta.php" class="btn btn-warning">
                                    <i class="fas fa-user-md me-1"></i>Junta Médica
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Estadísticas -->
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <div class="stats-card text-center">
                                <div class="stats-number"><?php echo $stats['completadas']; ?></div>
                                <div class="text-muted">Ausencias Completadas</div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="stats-card text-center">
                                <div class="stats-number"><?php echo $stats['rechazadas']; ?></div>
                                <div class="text-muted">Ausencias Rechazadas</div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="stats-card text-center">
                                <div class="stats-number"><?php echo $stats['finalizadas']; ?></div>
                                <div class="text-muted">Ausencias Finalizadas</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de Historial -->
                    <div class="table-container">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Policía</th>
                                        <th>Grado</th>
                                        <th>Tipo</th>
                                        <th>Fechas</th>
                                        <th>Duración</th>
                                        <th>Estado Final</th>
                                        <th>Registrado</th>
                                        <th>Aprobado Por</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($historial = $result_historial->fetch(PDO::FETCH_ASSOC)): 
                                        $duracion = '';
                                        if ($historial['fecha_fin']) {
                                            $inicio = new DateTime($historial['fecha_inicio']);
                                            $fin = new DateTime($historial['fecha_fin']);
                                            $diff = $inicio->diff($fin);
                                            $duracion = $diff->days . ' día(s)';
                                        } else {
                                            $duracion = 'Fecha única';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($historial['apellido'] . ', ' . $historial['nombre']); ?></strong>
                                            <br>
                                            <small class="text-muted">CI: <?php echo htmlspecialchars($historial['cin']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($historial['grado']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($historial['tipo_ausencia']); ?></td>
                                        <td>
                                            <small>
                                                Inicio: <?php echo date('d/m/Y', strtotime($historial['fecha_inicio'])); ?><br>
                                                <?php if ($historial['fecha_fin']): ?>
                                                Fin: <?php echo date('d/m/Y', strtotime($historial['fecha_fin'])); ?>
                                                <?php else: ?>
                                                <span class="text-muted">Fecha única</span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $duracion; ?></span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($historial['estado_historial']); ?>">
                                                <?php echo $historial['estado_historial']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('d/m/Y H:i', strtotime($historial['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <small><?php echo $historial['aprobado_por'] ? htmlspecialchars($historial['aprobado_por']) : '<span class="text-muted">-</span>'; ?></small>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($result_historial->rowCount() == 0): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-check text-muted" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-3">No hay registros en el historial</h5>
                            <p class="text-muted">El historial se llenará a medida que se completen o rechacen ausencias.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>