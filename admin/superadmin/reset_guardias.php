<?php
session_start();

// Verificar autenticación y rol de superadmin
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../cnx/db_connect.php';

// Verificar rol de superadmin
$stmt = $conn->prepare("SELECT rol FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();

if ($usuario['rol'] !== 'SUPERADMIN') {
    header('Location: ../../index.php');
    exit();
}

$mensaje = '';
$tipo_mensaje = '';

// Función para resetear guardias
function resetearGuardias($conn) {
    try {
        $conn->beginTransaction();
        
        // 1. Eliminar todas las guardias generadas y sus detalles
        $conn->exec("DELETE FROM guardias_generadas_detalle");
        $conn->exec("DELETE FROM guardias_asignaciones");
        $conn->exec("DELETE FROM guardias_generadas");
        $conn->exec("DELETE FROM guardias_realizadas");
        $conn->exec("DELETE FROM historial_guardias_policia");
        
        // 2. Resetear la lista de guardias manteniendo solo policías activos
        $conn->exec("DELETE FROM lista_guardias");
        
        // 3. Reinsertar policías activos en lista_guardias con posiciones secuenciales
        $policias = $conn->query("SELECT id FROM policias WHERE activo = 1 ORDER BY grado_id, legajo")->fetchAll();
        
        $posicion = 1;
        foreach ($policias as $policia) {
            $stmt = $conn->prepare("INSERT INTO lista_guardias (policia_id, posicion, ultima_guardia_fecha) 
                                   VALUES (?, ?, NULL)");
            $stmt->execute([$policia['id'], $posicion]);
            $posicion++;
        }
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

// Función para eliminar fechas de servicios
function eliminarFechasServicios($conn) {
    try {
        $conn->beginTransaction();
        
        // Eliminar todas las fechas de servicios
        $conn->exec("DELETE FROM servicios");
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

// Función para eliminar órdenes del día
function eliminarOrdenesDia($conn) {
    try {
        $conn->beginTransaction();
        
        // Eliminar todas las órdenes del día
        $conn->exec("DELETE FROM orden_dia");
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['reset_guardias'])) {
            resetearGuardias($conn);
            $mensaje = '✅ Guardias reseteadas exitosamente. Todas las guardias han sido eliminadas y recreadas según los grados.';
            $tipo_mensaje = 'success';
            
        } elseif (isset($_POST['eliminar_fechas_servicios'])) {
            eliminarFechasServicios($conn);
            $mensaje = '✅ Fechas de servicios eliminadas exitosamente.';
            $tipo_mensaje = 'success';
            
        } elseif (isset($_POST['eliminar_ordenes_dia'])) {
            eliminarOrdenesDia($conn);
            $mensaje = '✅ Órdenes del día eliminadas exitosamente.';
            $tipo_mensaje = 'success';
            
        } elseif (isset($_POST['reset_completo'])) {
            resetearGuardias($conn);
            eliminarFechasServicios($conn);
            eliminarOrdenesDia($conn);
            $mensaje = '✅ Reset completo realizado exitosamente. Guardias, fechas de servicios y órdenes del día han sido reseteadas.';
            $tipo_mensaje = 'success';
        }
        
    } catch (Exception $e) {
        $mensaje = '❌ Error: ' . $e->getMessage();
        $tipo_mensaje = 'danger';
    }
}

// Obtener estadísticas actuales
$total_guardias = $conn->query("SELECT COUNT(*) as total FROM guardias_generadas")->fetch()['total'];
$total_fechas_servicios = $conn->query("SELECT COUNT(*) as total FROM servicios")->fetch()['total'];
$total_ordenes_dia = $conn->query("SELECT COUNT(*) as total FROM orden_dia")->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset de Guardias - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .reset-card {
            border: 2px solid #dc3545;
            border-radius: 15px;
        }
        .stats-card {
            border: 2px solid #6f42c1;
            border-radius: 15px;
        }
        .btn-reset {
            font-size: 1.1em;
            font-weight: bold;
        }
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../inc/sidebar.php'; ?>
            
            <main class="col-md-9 col-lg-10 px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-undo-alt me-2"></i>Reset de Guardias y Servicios</h2>
                    <span class="badge bg-danger">Super Admin</span>
                </div>

                <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Advertencia -->
                <div class="alert alert-warning mb-4">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>ADVERTENCIA</h5>
                    <p class="mb-0">
                        Estas acciones son <strong>IRREVERSIBLES</strong> y afectarán todos los datos del sistema. 
                        Asegúrate de tener un backup antes de proceder.
                    </p>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card stats-card text-white bg-primary mb-3">
                            <div class="card-body text-center">
                                <h5><i class="fas fa-shield-alt me-2"></i>Guardias Activas</h5>
                                <h3><?php echo $total_guardias; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card text-white bg-info mb-3">
                            <div class="card-body text-center">
                                <h5><i class="fas fa-calendar me-2"></i>Fechas de Servicios</h5>
                                <h3><?php echo $total_fechas_servicios; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card text-white bg-success mb-3">
                            <div class="card-body text-center">
                                <h5><i class="fas fa-file-alt me-2"></i>Órdenes del Día</h5>
                                <h3><?php echo $total_ordenes_dia; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Acciones de Reset -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card reset-card mb-4">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Reset de Guardias</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">
                                    Elimina todas las guardias existentes y las recrea según los grados de los policías activos.
                                </p>
                                <form method="POST">
                                    <div class="d-grid">
                                        <button type="submit" name="reset_guardias" class="btn btn-danger btn-reset"
                                                onclick="return confirm('¿ESTÁS SEGURO? Esta acción eliminará TODAS las guardias y las recreará desde cero.')">
                                            <i class="fas fa-bomb me-2"></i>Resetear Guardias
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card reset-card mb-4">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="fas fa-calendar-times me-2"></i>Eliminar Fechas de Servicios</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">
                                    Elimina todas las fechas de servicios programadas en el sistema.
                                </p>
                                <form method="POST">
                                    <div class="d-grid">
                                        <button type="submit" name="eliminar_fechas_servicios" class="btn btn-warning btn-reset"
                                                onclick="return confirm('¿ESTÁS SEGURO? Esta acción eliminará TODAS las fechas de servicios.')">
                                            <i class="fas fa-trash me-2"></i>Eliminar Fechas Servicios
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card reset-card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-file-medical me-2"></i>Eliminar Órdenes del Día</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">
                                    Elimina todas las órdenes del día registradas en el sistema.
                                </p>
                                <form method="POST">
                                    <div class="d-grid">
                                        <button type="submit" name="eliminar_ordenes_dia" class="btn btn-info btn-reset"
                                                onclick="return confirm('¿ESTÁS SEGURO? Esta acción eliminará TODAS las órdenes del día.')">
                                            <i class="fas fa-file-alt me-2"></i>Eliminar Órdenes Día
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card reset-card mb-4">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0"><i class="fas fa-nuclear me-2"></i>Reset Completo</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">
                                    Ejecuta todas las acciones anteriores simultáneamente (máxima precaución).
                                </p>
                                <form method="POST">
                                    <div class="d-grid">
                                        <button type="submit" name="reset_completo" class="btn btn-dark btn-reset"
                                                onclick="return confirm('⚠️ ¿ESTÁS ABSOLUTAMENTE SEGURO? Esta acción realizará un RESET COMPLETO del sistema. TODOS los datos de guardias, servicios y órdenes serán ELIMINADOS.')">
                                            <i class="fas fa-radiation me-2"></i>Reset Completo
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información adicional -->
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>¿Qué hace cada acción?</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Reset Guardias:</strong> Elimina y recrea guardias según grados</li>
                                    <li><strong>Eliminar Fechas:</strong> Borra todas las fechas de servicios</li>
                                    <li><strong>Eliminar Órdenes:</strong> Borra todas las órdenes del día</li>
                                    <li><strong>Reset Completo:</strong> Ejecuta las 3 acciones anteriores</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Recomendaciones</h6>
                                <ul class="list-unstyled">
                                    <li>✅ Realiza backup antes de cualquier acción</li>
                                    <li>✅ Verifica que no haya usuarios activos</li>
                                    <li>✅ Ejecuta fuera del horario laboral</li>
                                    <li>✅ Notifica al personal afectado</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>