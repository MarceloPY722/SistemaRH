<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../cnx/db_connect.php';

// Obtener estadísticas
$total_policias = $conn->query("SELECT COUNT(*) as total FROM policias WHERE activo = 1")->fetch_assoc()['total'];
$total_servicios = $conn->query("SELECT COUNT(*) as total FROM servicios WHERE estado = 'PROGRAMADO'")->fetch_assoc()['total'];
$total_ausencias = $conn->query("SELECT COUNT(*) as total FROM ausencias WHERE estado = 'PENDIENTE'")->fetch_assoc()['total'];
$total_guardias = $conn->query("SELECT COUNT(*) as total FROM lista_guardias")->fetch_assoc()['total'];

// Obtener ausencias activas (APROBADA y PENDIENTE)
$ausencias_activas = $conn->query("
    SELECT a.id, a.fecha_inicio, a.fecha_fin, a.estado, a.descripcion,
           p.nombre, p.apellido, p.cin, g.nombre as grado, ta.nombre as tipo_ausencia
    FROM ausencias a
    JOIN policias p ON a.policia_id = p.id
    JOIN grados g ON p.grado_id = g.id
    JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id
    WHERE a.estado IN ('APROBADA', 'PENDIENTE')
    ORDER BY a.fecha_inicio ASC
    LIMIT 10
");

// Procesar completar ausencias
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'completar') {
    $ausencia_id = $_POST['ausencia_id'];
    
    // Verificar que la ausencia esté en estado APROBADA
    $stmt_verificar = $conn->prepare("SELECT estado FROM ausencias WHERE id = ?");
    $stmt_verificar->bind_param("i", $ausencia_id);
    $stmt_verificar->execute();
    $resultado = $stmt_verificar->get_result()->fetch_assoc();
    
    if ($resultado && $resultado['estado'] == 'APROBADA') {
        $sql = "UPDATE ausencias SET estado = 'COMPLETADA' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $ausencia_id);
        
        if ($stmt->execute()) {
            // Reinicializar la cola FIFO para actualizar disponibilidad
            try {
                $stmt_fifo = $conn->prepare("CALL InicializarColaFIFO()");
                $stmt_fifo->execute();
                $stmt_fifo->close();
                $mensaje = "<div class='alert alert-success'>Ausencia completada exitosamente. La disponibilidad del policía ha sido actualizada.</div>";
            } catch (Exception $e) {
                $mensaje = "<div class='alert alert-warning'>Ausencia completada, pero hubo un error al actualizar la cola de guardias: " . $e->getMessage() . "</div>";
            }
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al completar ausencia: " . $conn->error . "</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-danger'>Solo se pueden completar ausencias que estén en estado APROBADA</div>";
    }
    $stmt_verificar->close();
    
    // Recargar la página para mostrar los cambios
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
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

                    <!-- Acciones Rápidas -->
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
                                    <?php if ($ausencias_activas->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <tbody>
                                                <?php while ($ausencia = $ausencias_activas->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="py-2">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
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