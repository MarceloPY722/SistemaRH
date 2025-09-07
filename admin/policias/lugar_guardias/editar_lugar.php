<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit();
}
require_once '../../../cnx/db_connect.php';

$mensaje = '';
$tipo_mensaje = '';
$lugar = null;

// Verificar que se proporcione un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$lugar_id = intval($_GET['id']);

// Obtener datos del lugar
$stmt = $conn->prepare("SELECT * FROM lugares_guardias WHERE id = ?");
if ($stmt) {
    $stmt->execute([$lugar_id]);
    
    if ($stmt->rowCount() === 0) {
        header('Location: index.php');
        exit();
    }
    
    $lugar = $stmt->fetch();
} else {
    header('Location: index.php');
    exit();
}

// Procesar formulario de actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre_lugar'] ?? '');
    $zona = trim($_POST['zona_lugar'] ?? '');
    $direccion = trim($_POST['direccion_lugar'] ?? '');
    $descripcion = trim($_POST['descripcion_lugar'] ?? '');
    $activo = isset($_POST['activo_lugar']) ? 1 : 0;
    
    if (!empty($nombre) && !empty($zona)) {
        // Verificar si ya existe otro lugar con el mismo nombre
        $stmt_check = $conn->prepare("SELECT id FROM lugares_guardias WHERE nombre = ? AND id != ?");
        if ($stmt_check) {
            $stmt_check->execute([$nombre, $lugar_id]);
            
            if ($stmt_check->rowCount() > 0) {
                $mensaje = "Ya existe otro lugar de guardia con ese nombre.";
                $tipo_mensaje = "warning";
            } else {
                // Actualizar lugar
                $stmt_update = $conn->prepare("UPDATE lugares_guardias SET nombre = ?, zona = ?, direccion = ?, descripcion = ?, activo = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt_update) {
                    $stmt_update->bindParam(1, $nombre, PDO::PARAM_STR);
                    $stmt_update->bindParam(2, $zona, PDO::PARAM_STR);
                    $stmt_update->bindParam(3, $direccion, PDO::PARAM_STR);
                    $stmt_update->bindParam(4, $descripcion, PDO::PARAM_STR);
                    $stmt_update->bindParam(5, $activo, PDO::PARAM_INT);
                    $stmt_update->bindParam(6, $lugar_id, PDO::PARAM_INT);
                    
                    if ($stmt_update->execute()) {
                        if ($stmt_update->rowCount() > 0) {
                            $mensaje = "Lugar de guardia actualizado exitosamente.";
                            $tipo_mensaje = "success";
                            
                            // Actualizar datos del lugar para mostrar en el formulario
                            $lugar['nombre'] = $nombre;
                            $lugar['zona'] = $zona;
                            $lugar['direccion'] = $direccion;
                            $lugar['descripcion'] = $descripcion;
                            $lugar['activo'] = $activo;
                        } else {
                            $mensaje = "No se realizaron cambios en el lugar de guardia.";
                            $tipo_mensaje = "info";
                        }
                    } else {
                        $mensaje = "Error al actualizar el lugar de guardia.";
                        $tipo_mensaje = "danger";
                    }
                    $stmt_update->close();
                } else {
                    $mensaje = "Error en la preparación de la consulta.";
                    $tipo_mensaje = "danger";
                }
            }
            $stmt_check->close();
        } else {
            $mensaje = "Error al verificar duplicados.";
            $tipo_mensaje = "danger";
        }
    } else {
        $mensaje = "El nombre y la zona son campos obligatorios.";
        $tipo_mensaje = "warning";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Lugar de Guardia - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-custom-color {
            background: linear-gradient(135deg, #104c75 0%, #0d3d5c 100%);
        }
        .main-content {
            padding: 20px;
        }
        .page-title-custom {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .card-header-custom {
            background-color: #104c75;
            color: white;
        }
        .btn-custom-primary {
            background-color: #104c75;
            border-color: #104c75;
            color: white;
        }
        .btn-custom-primary:hover {
            background-color: #0d3d5c;
            border-color: #0d3d5c;
        }
        .btn-custom-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        .btn-custom-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row g-0">
            <?php include '../../inc/sidebar.php'; ?>
            <div class="col-md-9 col-lg-10">
                <main class="main-content">
                    <nav class="navbar navbar-expand-lg navbar-custom-color navbar-dark mb-4">
                        <div class="container-fluid">
                            <span class="navbar-brand mb-0 h1">
                                <i class="fas fa-edit me-2"></i>Editar Lugar de Guardia
                            </span>
                        </div>
                    </nav>
                    <div class="container-fluid px-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2 class="page-title-custom">
                                <i class="fas fa-map-marker-alt me-3"></i>Editar Lugar de Guardia
                            </h2>
                            <a href="index.php" class="btn btn-custom-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Volver al Listado
                            </a>
                        </div>
                        
                        <?php if ($mensaje): ?>
                            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                                <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : ($tipo_mensaje === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                                <?php echo htmlspecialchars($mensaje); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card">
                            <div class="card-header card-header-custom">
                                <h5 class="mb-0">
                                    <i class="fas fa-edit me-2"></i>Datos del Lugar de Guardia
                                    <small class="ms-2">(ID: <?php echo $lugar['id']; ?>)</small>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $lugar_id); ?>">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="nombre_lugar" class="form-label">Nombre del Lugar *</label>
                                            <input type="text" class="form-control" id="nombre_lugar" name="nombre_lugar" 
                                                   value="<?php echo htmlspecialchars($lugar['nombre']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="zona_lugar" class="form-label">Zona *</label>
                                            <input type="text" class="form-control" id="zona_lugar" name="zona_lugar" 
                                                   value="<?php echo htmlspecialchars($lugar['zona']); ?>" required placeholder="Escribe la zona...">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="direccion_lugar" class="form-label">Dirección</label>
                                        <input type="text" class="form-control" id="direccion_lugar" name="direccion_lugar" 
                                               value="<?php echo htmlspecialchars($lugar['direccion'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="descripcion_lugar" class="form-label">Descripción</label>
                                        <textarea class="form-control" id="descripcion_lugar" name="descripcion_lugar" rows="3"><?php echo htmlspecialchars($lugar['descripcion'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" value="1" id="activo_lugar" name="activo_lugar" 
                                               <?php echo $lugar['activo'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="activo_lugar">Activo</label>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-custom-primary">
                                            <i class="fas fa-save me-2"></i>Actualizar Lugar
                                        </button>
                                        <a href="index.php" class="btn btn-custom-secondary">
                                            <i class="fas fa-times me-2"></i>Cancelar
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Información adicional -->
                        <div class="card mt-4">
                            <div class="card-header card-header-custom">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información del Registro</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Fecha de Creación:</strong> 
                                           <?php echo date('d/m/Y H:i:s', strtotime($lugar['created_at'])); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Última Actualización:</strong> 
                                           <?php echo date('d/m/Y H:i:s', strtotime($lugar['updated_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre_lugar').value.trim();
            const zona = document.getElementById('zona_lugar').value.trim();
            
            if (!nombre || !zona) {
                e.preventDefault();
                alert('El nombre y la zona son campos obligatorios.');
                return false;
            }
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>