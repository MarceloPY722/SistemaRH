<?php
session_start();
require_once '../../cnx/db_connect.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit();
}

// Verificar si el usuario es superadmin
$es_superadmin = false;
try {
    $stmt = $conn->prepare("SELECT rol FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario && $usuario['rol'] === 'SUPERADMIN') {
        $es_superadmin = true;
    }
} catch (Exception $e) {
    // En caso de error, redirigir con mensaje
    $_SESSION['mensaje'] = "Error al verificar permisos: " . $e->getMessage();
    $_SESSION['tipo_mensaje'] = "danger";
    header("Location: ../index.php");
    exit();
}

// Si no es superadmin, redirigir
if (!$es_superadmin) {
    $_SESSION['mensaje'] = "Acceso denegado. Solo usuarios SUPERADMIN pueden realizar esta acción.";
    $_SESSION['tipo_mensaje'] = "danger";
    header("Location: ../index.php");
    exit();
}

// Función para registrar en auditoría
function registrarAuditoriaEliminacion($conn, $accion, $detalles = '') {
    if (file_exists('../../admin/inc/auditoria_functions.php')) {
        include_once '../../admin/inc/auditoria_functions.php';
        registrarAuditoria($accion, null, null, null, $detalles);
    }
}

// Procesar eliminación de servicios
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_servicio = $_POST['fecha_servicio'] ?? '';
    $numero_orden = $_POST['numero_orden'] ?? '';
    
    try {
        $conn->beginTransaction();
        
        if (!empty($fecha_servicio)) {
            // Eliminar servicios por fecha
            $stmt = $conn->prepare("DELETE FROM servicios WHERE fecha_servicio = ?");
            $stmt->execute([$fecha_servicio]);
            $servicios_eliminados = $stmt->rowCount();
            
            registrarAuditoriaEliminacion($conn, 'ELIMINAR_SERVICIOS', "Fecha: $fecha_servicio, Servicios eliminados: $servicios_eliminados");
            
            $mensaje .= "Servicios eliminados: $servicios_eliminados. ";
        }
        
        if (!empty($numero_orden)) {
            // Eliminar órdenes del día por número
            $stmt = $conn->prepare("DELETE FROM orden_dia WHERE numero_orden = ?");
            $stmt->execute([$numero_orden]);
            $ordenes_eliminadas = $stmt->rowCount();
            
            registrarAuditoriaEliminacion($conn, 'ELIMINAR_ORDEN_DIA', "Número: $numero_orden, Órdenes eliminadas: $ordenes_eliminadas");
            
            $mensaje .= "Órdenes del día eliminadas: $ordenes_eliminadas. ";
        }
        
        $conn->commit();
        $tipo_mensaje = 'success';
        
        if (empty($mensaje)) {
            $mensaje = 'No se especificaron criterios de eliminación.';
            $tipo_mensaje = 'warning';
        }
        
    } catch (Exception $e) {
        $conn->rollBack();
        $mensaje = "Error al eliminar: " . $e->getMessage();
        $tipo_mensaje = 'danger';
    }
}

// Obtener servicios recientes
$servicios_recientes = [];
try {
    $stmt = $conn->prepare("SELECT fecha_servicio, COUNT(*) as total FROM servicios GROUP BY fecha_servicio ORDER BY fecha_servicio DESC LIMIT 10");
    $stmt->execute();
    $servicios_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignorar error si la tabla no existe
}

// Obtener órdenes del día recientes
$ordenes_recientes = [];
try {
    $stmt = $conn->prepare("SELECT numero_orden, fecha_creacion FROM orden_dia ORDER BY fecha_creacion DESC LIMIT 10");
    $stmt->execute();
    $ordenes_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignorar error si la tabla no existe
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Servicios y Órdenes - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
        }
        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333, #a71e2a);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../inc/sidebar.php'; ?>
            
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <h1 class="page-title">
                        <i class="fas fa-trash-alt text-danger"></i> Eliminar Servicios y Órdenes
                    </h1>

                    <?php if (!empty($mensaje)): ?>
                        <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show">
                            <?= htmlspecialchars($mensaje) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0"><i class="fas fa-calendar-times"></i> Eliminar por Fecha de Servicio</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label for="fecha_servicio" class="form-label">Fecha de Servicio</label>
                                            <input type="date" class="form-control" id="fecha_servicio" name="fecha_servicio" required>
                                        </div>
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('¿Está seguro de eliminar todos los servicios de esta fecha?')">
                                            <i class="fas fa-trash"></i> Eliminar Servicios
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0"><i class="fas fa-file-alt"></i> Eliminar por Número de Orden</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label for="numero_orden" class="form-label">Número de Orden</label>
                                            <input type="text" class="form-control" id="numero_orden" name="numero_orden" 
                                                   placeholder="Ej: 27/2025" required>
                                        </div>
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('¿Está seguro de eliminar esta orden del día?')">
                                            <i class="fas fa-trash"></i> Eliminar Orden
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-history"></i> Servicios Recientes</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($servicios_recientes)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Fecha</th>
                                                        <th>Servicios</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($servicios_recientes as $servicio): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($servicio['fecha_servicio']) ?></td>
                                                            <td><?= htmlspecialchars($servicio['total']) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">No hay servicios registrados.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-list-ol"></i> Órdenes del Día Recientes</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($ordenes_recientes)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Número</th>
                                                        <th>Fecha Creación</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($ordenes_recientes as $orden): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($orden['numero_orden']) ?></td>
                                                            <td><?= htmlspecialchars($orden['fecha_creacion']) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">No hay órdenes del día registradas.</p>
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