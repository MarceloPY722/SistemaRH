<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../cnx/db_connect.php';

// Procesar completar ausencias
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] == 'completar') {
        $ausencia_id = $_POST['ausencia_id'];
        $policia_id = isset($_POST['policia_id']) ? $_POST['policia_id'] : null;
        
        // Verificar que la ausencia esté en estado APROBADA
        $stmt_verificar = $conn->prepare("SELECT estado, policia_id FROM ausencias WHERE id = ?");
        $stmt_verificar->execute([$ausencia_id]);
        $resultado = $stmt_verificar->fetch();
        
        if ($resultado && $resultado['estado'] == 'APROBADA') {
            $policia_id = $resultado['policia_id'];
            
            $sql = "UPDATE ausencias SET estado = 'COMPLETADA' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            
            if ($stmt->execute([$ausencia_id])) {
                // Verificar si el policía tiene otras ausencias activas
                $stmt_check_otras = $conn->prepare("SELECT COUNT(*) as count FROM ausencias WHERE policia_id = ? AND estado = 'APROBADA' AND id != ? AND (fecha_fin IS NULL OR fecha_fin >= CURDATE()) AND fecha_inicio <= CURDATE()");
                $stmt_check_otras->execute([$policia_id, $ausencia_id]);
                $otras_ausencias = $stmt_check_otras->fetch()['count'];
                
                // Si no tiene otras ausencias activas, restaurar estado a DISPONIBLE
                if ($otras_ausencias == 0) {
                    $stmt_restore_estado = $conn->prepare("UPDATE policias SET estado = 'DISPONIBLE' WHERE id = ?");
                    $stmt_restore_estado->execute([$policia_id]);
                }
                
                // Restaurar lugar de guardia original si hay intercambio activo
                $stmt_check = $conn->prepare("SELECT lugar_original_id, lugar_intercambio_id FROM intercambios_guardias WHERE policia_id = ? AND ausencia_id = ? AND activo = 1");
                $stmt_check->execute([$policia_id, $ausencia_id]);
                $intercambio = $stmt_check->fetch();
                
                if ($intercambio) {
                   
                    $stmt_restore = $conn->prepare("UPDATE policias SET lugar_guardia_id = ?, lugar_guardia_reserva_id = ? WHERE id = ?");
                    $stmt_restore->execute([$intercambio['lugar_original_id'], $intercambio['lugar_intercambio_id'], $policia_id]);
                    
                    $stmt_deactivate = $conn->prepare("UPDATE intercambios_guardias SET activo = 0, fecha_restauracion = NOW() WHERE policia_id = ? AND ausencia_id = ?");
                    $stmt_deactivate->execute([$policia_id, $ausencia_id]);
                    
                    $mensaje = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Ausencia completada y lugar de guardia restaurado exitosamente. Estado del policía actualizado.</div>";
                } else {
                    $mensaje = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Ausencia completada exitosamente. Estado del policía actualizado.</div>";
                }
                
                try {
                    $stmt_restore_pos = $conn->prepare("CALL RestaurarPosicionPorLegajo(?)");
                    $stmt_restore_pos->execute([$policia_id]);
                } catch (Exception $e) {
                    // Si no existe el procedimiento, reorganizar toda la lista
                    try {
                        $stmt_reorg = $conn->prepare("CALL ReorganizarListaGuardias()");
                        $stmt_reorg->execute();
                    } catch (Exception $e2) {
                        // Silenciar error si los procedimientos no existen
                    }
                }
            } else {
                $mensaje = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error al completar ausencia: " . $conn->error . "</div>";
            }
        } else {
            $mensaje = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Solo se pueden completar ausencias que estén en estado APROBADA</div>";
        }

        
        // Recargar la página para mostrar los cambios
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Obtener estadísticas
$total_policias = $conn->query("SELECT COUNT(*) as total FROM policias WHERE activo = 1")->fetch()['total'];
$total_servicios = $conn->query("SELECT COUNT(*) as total FROM servicios WHERE estado = 'PROGRAMADO'")->fetch()['total'];
$total_ausencias = $conn->query("SELECT COUNT(*) as total FROM ausencias WHERE estado = 'PENDIENTE'")->fetch()['total'];
$total_guardias = $conn->query("SELECT COUNT(*) as total FROM lista_guardias")->fetch()['total'];

// Obtener ausencias activas (excluyendo Junta Médica)
$ausencias_activas = $conn->query("
    SELECT 
        a.id, 
        a.fecha_inicio, 
        a.fecha_fin, 
        a.estado, 
        a.descripcion,
        p.id as policia_id,
        p.nombre, 
        p.apellido, 
        p.cin, 
        p.legajo,
        tg.nombre as grado, 
        ta.nombre as tipo_ausencia,
        DATEDIFF(COALESCE(a.fecha_fin, CURDATE()), a.fecha_inicio) + 1 as dias_ausencia,
        CASE 
            WHEN a.fecha_fin IS NULL THEN 'Indefinida'
            WHEN a.fecha_fin < CURDATE() THEN 'Vencida'
            WHEN a.fecha_fin = CURDATE() THEN 'Termina hoy'
            ELSE CONCAT(DATEDIFF(a.fecha_fin, CURDATE()), ' días restantes')
        END as tiempo_restante
    FROM ausencias a
    JOIN policias p ON a.policia_id = p.id
    LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
    JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id
    WHERE a.estado IN ('APROBADA', 'PENDIENTE') AND ta.nombre != 'Junta Medica'
    ORDER BY a.fecha_inicio ASC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrativo - Sistema RH Policía Nacional</title>
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
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .stat-card {
            background: linear-gradient(135deg, #104c75 0%, #0d3d5c 100%);
            color: white;
        }
        .stat-card.green {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .stat-card.orange {
            background: linear-gradient(135deg, #fd7e14 0%, #e83e8c 100%);
        }
        .stat-card.red {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        .main-content {
            padding: 30px;
        }
        .page-title {
            color: #104c75;
            font-weight: 600;
            margin-bottom: 30px;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        .status-normal {
            background: #e3f2fd;
            color: #1976d2;
        }
        .status-intercambiado {
            background: #fff3e0;
            color: #f57c00;
        }
        .btn-intercambio {
            background: linear-gradient(45deg, #ff9800, #f57c00);
            border: none;
            color: white;
            font-size: 11px;
            padding: 4px 8px;
        }
        .btn-intercambio:hover {
            background: linear-gradient(45deg, #f57c00, #ff9800);
            color: white;
        }
        .lugar-info {
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
  
    <div class="container-fluid">
        <div class="row">
            <?php include 'inc/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <h1 class="page-title">
                        <i class="fas fa-tachometer-alt"></i> Panel de Control
                    </h1>

                    <?php if (isset($mensaje)) echo $mensaje; ?>

                    <!-- Estadísticas -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-2x mb-3"></i>
                                    <h3><?php echo $total_policias; ?></h3>
                                    <p class="mb-0">Policías Activos</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card green">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-check fa-2x mb-3"></i>
                                    <h3><?php echo $total_servicios; ?></h3>
                                    <p class="mb-0">Servicios Programados</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card orange">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-clock fa-2x mb-3"></i>
                                    <h3><?php echo $total_ausencias; ?></h3>
                                    <p class="mb-0">Ausencias Pendientes</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card red">
                                <div class="card-body text-center">
                                    <i class="fas fa-list-ol fa-2x mb-3"></i>
                                    <h3><?php echo $total_guardias; ?></h3>
                                    <p class="mb-0">En Lista de Guardias</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5><i class="fas fa-plus-circle"></i> Acciones Rápidas</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="servicios/index.php" class="btn btn-outline-success">
                                            <i class="fas fa-calendar-plus"></i> Programar Servicio
                                        </a>
                                        <a href="ausencias/agregar_ausencia.php" class="btn btn-outline-warning">
                                            <i class="fas fa-user-times"></i> Registrar Ausencia
                                        </a>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-warning text-white">
                                    <h5><i class="fas fa-user-clock"></i> Ausencias Activas</h5>
                                </div>
                                <div class="card-body p-0">
                                    <?php if ($ausencias_activas->rowCount() > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <tbody>
                                                <?php while ($ausencia = $ausencias_activas->fetch()): ?>
                                                <tr>
                                                    <td class="py-2">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div class="flex-grow-1">
                                                                <strong><?php echo $ausencia['grado'] . ' ' . $ausencia['apellido'] . ', ' . $ausencia['nombre']; ?></strong>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-calendar"></i> 
                                                                    <?php echo date('d/m/Y', strtotime($ausencia['fecha_inicio'])); ?>
                                                                    <?php if ($ausencia['fecha_fin']): ?>
                                                                        - <?php echo date('d/m/Y', strtotime($ausencia['fecha_fin'])); ?>
                                                                    <?php else: ?>
                                                                        - Indefinida
                                                                    <?php endif; ?>
                                                                    <span class="ms-2 badge bg-info"><?php echo $ausencia['tiempo_restante']; ?></span>
                                                                </small>
                                                                <br>
                                                                <span class="badge bg-<?php 
                                                                    echo $ausencia['estado'] == 'APROBADA' ? 'success' : 
                                                                        ($ausencia['estado'] == 'PENDIENTE' ? 'warning' : 'info'); 
                                                                ?> badge-sm">
                                                                    <?php echo $ausencia['estado']; ?>
                                                                </span>
                                                                <small class="text-muted ms-1"><?php echo $ausencia['tipo_ausencia']; ?></small>
                                                                

                                                            </div>
                                                            <div class="btn-group-vertical" role="group">
                                                                <?php if ($ausencia['estado'] == 'APROBADA'): ?>
                                                                    <form method="POST" style="display: inline;" class="mb-1">
                                                                        <input type="hidden" name="action" value="completar">
                                                                        <input type="hidden" name="ausencia_id" value="<?php echo $ausencia['id']; ?>">
                                                                        <input type="hidden" name="policia_id" value="<?php echo $ausencia['policia_id']; ?>">
                                                                        <button type="submit" class="btn btn-sm btn-success" 
                                                                                onclick="return confirm('¿Marcar como completada?')" 
                                                                                title="Completar ausencia">
                                                                            <i class="fas fa-check"></i>
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                                <a href="ausencias/index.php" class="btn btn-sm btn-outline-primary" title="Ver/Editar">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="card-footer text-center py-2">
                                        <a href="ausencias/index.php" class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-list"></i> Ver todas las ausencias
                                        </a>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                                        <p class="mb-0">No hay ausencias activas</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>