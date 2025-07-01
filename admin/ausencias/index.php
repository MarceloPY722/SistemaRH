<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../cnx/db_connect.php';

// Procesar acciones del formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'aprobar':
            $ausencia_id = $_POST['ausencia_id'];
            $sql = "UPDATE ausencias SET estado = 'APROBADA', aprobado_por = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $_SESSION['usuario_id'], $ausencia_id);
            
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = 'Ausencia aprobada exitosamente';
                $_SESSION['tipo_mensaje'] = 'success';
            }
            break;
            
        case 'rechazar':
            $ausencia_id = $_POST['ausencia_id'];
            $sql = "UPDATE ausencias SET estado = 'RECHAZADA' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $ausencia_id);
            
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = 'Ausencia rechazada';
                $_SESSION['tipo_mensaje'] = 'warning';
            }
            break;
            
        case 'completar':
            $ausencia_id = $_POST['ausencia_id'];
            $sql = "UPDATE ausencias SET estado = 'COMPLETADA' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $ausencia_id);
            
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = 'Ausencia marcada como completada';
                $_SESSION['tipo_mensaje'] = 'success';
            }
            break;
            
        case 'eliminar':
            $ausencia_id = $_POST['ausencia_id'];
            $sql = "DELETE FROM ausencias WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $ausencia_id);
            
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = 'Ausencia eliminada exitosamente';
                $_SESSION['tipo_mensaje'] = 'success';
            }
            break;
    }
    
    header('Location: index.php');
    exit();
}

// Obtener ausencias activas
$sql_ausencias = "SELECT a.*, p.nombre, p.apellido, p.cin, g.nombre as grado, ta.nombre as tipo_ausencia,
                         u.nombre_completo as aprobado_por_nombre
                  FROM ausencias a
                  JOIN policias p ON a.policia_id = p.id
                  JOIN grados g ON p.grado_id = g.id
                  JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id
                  LEFT JOIN usuarios u ON a.aprobado_por = u.id
                  WHERE a.estado IN ('PENDIENTE', 'APROBADA')
                  ORDER BY a.created_at DESC";
$result_ausencias = $conn->query($sql_ausencias);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ausencias - Sistema RH</title>
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

        .btn-outline-info {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 500;
        }

        .btn-outline-info:hover {
            background: var(--primary-color);
            color: white;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            background: white;
            border-bottom: 2px solid var(--primary-color);
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead {
            background: var(--primary-color);
            color: white;
        }

        .table thead th {
            border: none;
            font-weight: 500;
            padding: 1rem 0.75rem;
        }

        .table tbody td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }

        .badge {
            font-size: 0.8em;
            padding: 6px 12px;
            border-radius: 6px;
        }

        .badge-warning {
            background: var(--warning-color);
        }

        .badge-success {
            background: var(--success-color);
        }

        .badge-danger {
            background: var(--danger-color);
        }

        .btn-sm {
            padding: 6px 12px;
            margin: 0 2px;
            border-radius: 6px;
        }

        .no-results {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .no-results i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }
            
            .page-header {
                padding: 1.5rem;
                text-align: center;
            }
            
            .d-flex {
                flex-direction: column;
                gap: 10px;
            }
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
                                <h1 class="mb-2"><i class="fas fa-calendar-times me-3"></i>Gestión de Ausencias</h1>
                                <p class="mb-0 opacity-75">Administra las ausencias del personal policial</p>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="d-flex gap-3 mb-4">
                        <a href="agregar_ausencia.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Nueva Ausencia
                        </a>
                        <a href="historial_ausencias.php" class="btn btn-outline-info">
                            <i class="fas fa-history me-2"></i>Ver Historial
                        </a>
                    </div>

                    <!-- Lista de Ausencias Activas -->
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-list me-2"></i>Personal Ausente Activo
                                </h5>
                                <span class="badge bg-primary">
                                    <?php echo $result_ausencias->num_rows; ?> ausencias activas
                                </span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($result_ausencias->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Policía</th>
                                            <th>Grado</th>
                                            <th>Tipo</th>
                                            <th>Fechas</th>
                                            <th>Estado</th>
                                            <th>Descripción</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($ausencia = $result_ausencias->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $ausencia['apellido'] . ', ' . $ausencia['nombre']; ?></strong>
                                                <br><small class="text-muted">CI: <?php echo $ausencia['cin']; ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $ausencia['grado']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $ausencia['tipo_ausencia']; ?></span>
                                            </td>
                                            <td>
                                                <small>
                                                    <strong>Inicio:</strong> <?php echo date('d/m/Y', strtotime($ausencia['fecha_inicio'])); ?><br>
                                                    <strong>Fin:</strong> <?php echo $ausencia['fecha_fin'] ? date('d/m/Y', strtotime($ausencia['fecha_fin'])) : 'Sin fecha fin'; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php
                                                $badge_class = '';
                                                switch($ausencia['estado']) {
                                                    case 'PENDIENTE': $badge_class = 'badge-warning'; break;
                                                    case 'APROBADA': $badge_class = 'badge-success'; break;
                                                    case 'RECHAZADA': $badge_class = 'badge-danger'; break;
                                                    case 'COMPLETADA': $badge_class = 'badge-secondary'; break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>"><?php echo $ausencia['estado']; ?></span>
                                            </td>
                                            <td>
                                                <small><?php echo $ausencia['descripcion'] ? substr($ausencia['descripcion'], 0, 40) . '...' : 'Sin descripción'; ?></small>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <?php if ($ausencia['estado'] == 'PENDIENTE'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="aprobar">
                                                        <input type="hidden" name="ausencia_id" value="<?php echo $ausencia['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-success" title="Aprobar">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="rechazar">
                                                        <input type="hidden" name="ausencia_id" value="<?php echo $ausencia['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-warning" title="Rechazar">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($ausencia['estado'] == 'APROBADA'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="completar">
                                                        <input type="hidden" name="ausencia_id" value="<?php echo $ausencia['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-info" title="Completar">
                                                            <i class="fas fa-check-double"></i>
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                    
                                                    <form method="POST" style="display: inline;" 
                                                          onsubmit="return confirm('¿Está seguro de eliminar esta ausencia?')">
                                                        <input type="hidden" name="action" value="eliminar">
                                                        <input type="hidden" name="ausencia_id" value="<?php echo $ausencia['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="no-results">
                                <i class="fas fa-calendar-check"></i>
                                <h4>No hay ausencias activas</h4>
                                <p class="mb-0">Actualmente no hay personal con ausencias pendientes o aprobadas.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>