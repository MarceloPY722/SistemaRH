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
            $stmt_prev = $conn->prepare("SELECT * FROM ausencias WHERE id = ?");
            $stmt_prev->execute([$ausencia_id]);
            $ausencia_prev = $stmt_prev->fetch(PDO::FETCH_ASSOC);
            $sql = "UPDATE ausencias SET estado = 'APROBADA', aprobado_por = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            
            if ($stmt->execute([$_SESSION['usuario_id'], $ausencia_id])) {
                if (function_exists('auditoriaActualizar')) {
                    auditoriaActualizar('ausencias', $ausencia_id, $ausencia_prev ?: null, [
                        'estado' => 'APROBADA',
                        'aprobado_por' => $_SESSION['usuario_id']
                    ]);
                }
                $_SESSION['mensaje'] = 'Ausencia aprobada exitosamente';
                $_SESSION['tipo_mensaje'] = 'success';
            }
            break;
            
        case 'rechazar':
            $ausencia_id = $_POST['ausencia_id'];
            $stmt_prev = $conn->prepare("SELECT * FROM ausencias WHERE id = ?");
            $stmt_prev->execute([$ausencia_id]);
            $ausencia_prev = $stmt_prev->fetch(PDO::FETCH_ASSOC);
            $sql = "UPDATE ausencias SET estado = 'RECHAZADA' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            
            if ($stmt->execute([$ausencia_id])) {
                if (function_exists('auditoriaActualizar')) {
                    auditoriaActualizar('ausencias', $ausencia_id, $ausencia_prev ?: null, [
                        'estado' => 'RECHAZADA'
                    ]);
                }
                $_SESSION['mensaje'] = 'Ausencia rechazada';
                $_SESSION['tipo_mensaje'] = 'warning';
            }
            break;
            
        case 'marcar_completado':
            $ausencia_id = $_POST['ausencia_id'];
            
            try {
                $conn->beginTransaction();
                
                // Obtener información de la ausencia
                $stmt_info = $conn->prepare("SELECT policia_id FROM ausencias WHERE id = ?");
                $stmt_info->execute([$ausencia_id]);
                $ausencia_info = $stmt_info->fetch(PDO::FETCH_ASSOC);
                
                if ($ausencia_info) {
                    $policia_id = $ausencia_info['policia_id'];
                    $stmt_prev_aus = $conn->prepare("SELECT * FROM ausencias WHERE id = ?");
                    $stmt_prev_aus->execute([$ausencia_id]);
                    $ausencia_prev = $stmt_prev_aus->fetch(PDO::FETCH_ASSOC);
                    
                    // Actualizar el estado de la ausencia a COMPLETADA
                    $sql = "UPDATE ausencias SET estado = 'COMPLETADA', updated_at = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$ausencia_id]);
                    if (function_exists('auditoriaActualizar')) {
                        auditoriaActualizar('ausencias', $ausencia_id, $ausencia_prev ?: null, [
                            'estado' => 'COMPLETADA'
                        ]);
                    }
                    
                    // Verificar si el policía tiene otras ausencias activas
                    $stmt_check_otras = $conn->prepare("SELECT COUNT(*) as count FROM ausencias WHERE policia_id = ? AND estado = 'APROBADA' AND id != ? AND (fecha_fin IS NULL OR fecha_fin >= CURDATE()) AND fecha_inicio <= CURDATE()");
                    $stmt_check_otras->execute([$policia_id, $ausencia_id]);
                    $otras_ausencias = $stmt_check_otras->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    // Si no tiene otras ausencias activas, restaurar estado a DISPONIBLE
                    if ($otras_ausencias == 0) {
                        $stmt_restore_estado = $conn->prepare("UPDATE policias SET estado = 'DISPONIBLE' WHERE id = ?");
                        $stmt_restore_estado->execute([$policia_id]);
                        if (function_exists('auditoriaActualizar')) {
                            $stmt_prev_pol = $conn->prepare("SELECT * FROM policias WHERE id = ?");
                            $stmt_prev_pol->execute([$policia_id]);
                            $policia_prev = $stmt_prev_pol->fetch(PDO::FETCH_ASSOC);
                            auditoriaActualizar('policias', $policia_id, $policia_prev ?: null, [
                                'estado' => 'DISPONIBLE'
                            ]);
                        }
                    }
                }
                
                $conn->commit();
                
                $_SESSION['mensaje'] = 'Ausencia marcada como completada exitosamente. Estado del policía actualizado.';
                $_SESSION['tipo_mensaje'] = 'success';
                
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['mensaje'] = 'Error al marcar ausencia como completada: ' . $e->getMessage();
                $_SESSION['tipo_mensaje'] = 'danger';
            }
            break;
            

    }
    
    header('Location: index.php');
    exit();
}

// Obtener ausencias activas (excluyendo Junta Médica)
$sql_ausencias = "SELECT a.*, p.nombre, p.apellido, p.cin, p.legajo, g.nombre as grado, ta.nombre as tipo_ausencia,
                         u.nombre_completo as aprobado_por_nombre,
                         lg_principal.nombre as lugar_principal
                  FROM ausencias a
                  JOIN policias p ON a.policia_id = p.id
                  LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
                  LEFT JOIN grados g ON tg.grado_id = g.id
                  JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id
                  LEFT JOIN usuarios u ON a.aprobado_por = u.id
                  LEFT JOIN lugares_guardias lg_principal ON p.lugar_guardia_id = lg_principal.id
                  WHERE a.estado IN ('PENDIENTE', 'APROBADA', 'COMPLETADA')
                  AND ta.nombre != 'Junta Medica'
                  ORDER BY a.created_at DESC";
$result_ausencias = $conn->prepare($sql_ausencias);
$result_ausencias->execute();

// Obtener estadísticas (excluyendo Junta Médica)
$sql_stats = "SELECT 
                (SELECT COUNT(*) FROM ausencias a JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id WHERE a.estado = 'PENDIENTE' AND ta.nombre != 'Junta Medica') as pendientes,
                (SELECT COUNT(*) FROM ausencias a JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id WHERE a.estado = 'APROBADA' AND ta.nombre != 'Junta Medica') as aprobadas,
                (SELECT COUNT(*) FROM ausencias a JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id WHERE a.estado = 'COMPLETADA' AND ta.nombre != 'Junta Medica') as completadas,
                (SELECT COUNT(*) FROM ausencias a JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id WHERE a.estado = 'APROBADA' AND ta.nombre != 'Junta Medica' AND CURDATE() BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, a.fecha_inicio)) as ausentes_activos";
$result_stats = $conn->prepare($sql_stats);
$result_stats->execute();
$stats = $result_stats->fetch(PDO::FETCH_ASSOC);
?>

    <title>Gestión de Ausencias (Generales) - Sistema RH</title>
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

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
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

        .action-btn {
            margin: 0 2px;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
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
                                <h1><i class="fas fa-calendar-alt me-2"></i>Gestión de Ausencias</h1>
                                <p>Administra las ausencias del personal policial</p>
                            </div>
                            <div>
                                <a href="agregar_ausencia.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>Nueva Ausencia
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Estadísticas -->
                    <div class="row mb-4">
                        <div class="col-md-2 mb-3">
                            <div class="stats-card text-center">
                                <div class="stats-number"><?php echo $stats['pendientes']; ?></div>
                                <div class="text-muted">Pendientes</div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="stats-card text-center">
                                <div class="stats-number"><?php echo $stats['aprobadas']; ?></div>
                                <div class="text-muted">Aprobadas</div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="stats-card text-center">
                                <div class="stats-number"><?php echo $stats['completadas']; ?></div>
                                <div class="text-muted">Completadas</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stats-card text-center">
                                <div class="stats-number"><?php echo $stats['ausentes_activos']; ?></div>
                                <div class="text-muted">Ausentes Activos</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stats-card text-center">
                                <a href="historial_ausencias.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-history me-1"></i>Ver Historial
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="table-container">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Policía</th>
                                        <th>Tipo</th>
                                        <th>Fechas</th>
                                        <th>Lugar de Guardia</th>
                                        <th>Estado</th>
                                        <th>Registrado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($ausencia = $result_ausencias->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($ausencia['apellido'] . ', ' . $ausencia['nombre']); ?></strong><br>
                                            <small class="text-muted">CI: <?php echo htmlspecialchars($ausencia['cin']); ?> - Legajo: <?php echo htmlspecialchars($ausencia['legajo']); ?> - <?php echo htmlspecialchars($ausencia['grado']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($ausencia['tipo_ausencia']); ?></td>
                                        <td>
                                            <small>
                                                Inicio: <?php echo date('d/m/Y', strtotime($ausencia['fecha_inicio'])); ?><br>
                                                <?php if ($ausencia['fecha_fin']): ?>
                                                Fin: <?php echo date('d/m/Y', strtotime($ausencia['fecha_fin'])); ?>
                                                <?php else: ?>
                                                <span class="text-muted">Fecha única</span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small>
                                                <strong>Lugar de Guardia:</strong> <?php echo htmlspecialchars($ausencia['lugar_principal']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($ausencia['estado']); ?>">
                                                <?php echo $ausencia['estado']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo date('d/m/Y H:i', strtotime($ausencia['created_at'])); ?><br>
                                                <?php if ($ausencia['aprobado_por_nombre']): ?>
                                                Aprobó: <?php echo htmlspecialchars($ausencia['aprobado_por_nombre']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if ($ausencia['estado'] == 'PENDIENTE'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="aprobar">
                                                    <input type="hidden" name="ausencia_id" value="<?php echo $ausencia['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success action-btn" title="Aprobar">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="rechazar">
                                                    <input type="hidden" name="ausencia_id" value="<?php echo $ausencia['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-warning action-btn" title="Rechazar">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($ausencia['estado'] == 'APROBADA'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="marcar_completado">
                                                    <input type="hidden" name="ausencia_id" value="<?php echo $ausencia['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-info action-btn" title="Marcar como Completado">
                                                        <i class="fas fa-check-double"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                
                                                <a href="editar_ausencia.php?id=<?php echo $ausencia['id']; ?>" class="btn btn-sm btn-primary action-btn" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="ver_ausencia.php?id=<?php echo $ausencia['id']; ?>" class="btn btn-sm btn-info action-btn" title="Ver Detalles">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($result_ausencias->rowCount() == 0): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-check text-muted" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-3">No hay ausencias registradas</h5>
                            <p class="text-muted">Comienza registrando una nueva ausencia.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

   
</body>

 <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh cada 30 segundos para actualizar la lista de ausentes
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>