<?php
require_once '../../../cnx/db_connect.php';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion']);
                $activo = isset($_POST['activo']) ? 1 : 0;
                
                if (!empty($nombre)) {
                    try {
                        $stmt = $conn->prepare("INSERT INTO regiones (nombre, descripcion, activo) VALUES (?, ?, ?)");
                        if ($stmt->execute([$nombre, $descripcion, $activo])) {
                            $success_message = "Región creada exitosamente.";
                        } else {
                            $error_message = "Error al crear la región.";
                        }
                    } catch (PDOException $e) {
                        $error_message = "Error al crear la región: " . $e->getMessage();
                    }
                } else {
                    $error_message = "El nombre de la región es obligatorio.";
                }
                break;
                
            case 'update':
                $id = intval($_POST['id']);
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion']);
                $activo = isset($_POST['activo']) ? 1 : 0;
                
                if (!empty($nombre) && $id > 0) {
                    try {
                        $stmt = $conn->prepare("UPDATE regiones SET nombre = ?, descripcion = ?, activo = ? WHERE id = ?");
                        if ($stmt->execute([$nombre, $descripcion, $activo, $id])) {
                            $success_message = "Región actualizada exitosamente.";
                        } else {
                            $error_message = "Error al actualizar la región.";
                        }
                    } catch (PDOException $e) {
                        $error_message = "Error al actualizar la región: " . $e->getMessage();
                    }
                } else {
                    $error_message = "Datos inválidos para actualizar.";
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                
                if ($id > 0) {
                    // Verificar si la región está siendo usada
                    try {
                        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM policias WHERE region_id = ?");
                        $check_stmt->execute([$id]);
                        $row = $check_stmt->fetch();
                        
                        if ($row['count'] > 0) {
                            $error_message = "No se puede eliminar la región porque está siendo utilizada por " . $row['count'] . " policía(s).";
                        } else {
                            $stmt = $conn->prepare("DELETE FROM regiones WHERE id = ?");
                            if ($stmt->execute([$id])) {
                                $success_message = "Región eliminada exitosamente.";
                            } else {
                                $error_message = "Error al eliminar la región.";
                            }
                        }
                    } catch (PDOException $e) {
                        $error_message = "Error al eliminar la región: " . $e->getMessage();
                    }
                } else {
                    $error_message = "ID de región inválido.";
                }
                break;
        }
    }
}

// Obtener todas las regiones
$regiones_query = "SELECT r.*, 
                   (SELECT COUNT(*) FROM policias p WHERE p.region_id = r.id) as policias_count 
                   FROM regiones r ORDER BY r.nombre";
$regiones_result = $conn->query($regiones_query);
$regiones = $regiones_result ? $regiones_result->fetchAll() : [];

// Obtener región para editar si se especifica
$edit_region = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_stmt = $conn->prepare("SELECT * FROM regiones WHERE id = ?");
    $edit_stmt->execute([$edit_id]);
    $edit_region = $edit_stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Regiones - Sistema RH Policía</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-dark text-white p-0">
                <?php include '../../inc/sidebar.php'; ?>
            </div>
            
            <!-- Main content -->
            <div class="col-md-10">
                <div class="container-fluid py-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-map-marker-alt"></i> Gestión de Regiones</h2>
                        <a href="../index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver a Policías
                        </a>
                    </div>

                    <!-- Mensajes -->
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Formulario -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-<?php echo $edit_region ? 'edit' : 'plus'; ?>"></i>
                                        <?php echo $edit_region ? 'Editar Región' : 'Nueva Región'; ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="<?php echo $edit_region ? 'update' : 'create'; ?>">
                                        <?php if ($edit_region): ?>
                                            <input type="hidden" name="id" value="<?php echo $edit_region['id']; ?>">
                                        <?php endif; ?>
                                        
                                        <div class="mb-3">
                                            <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="nombre" name="nombre" 
                                                   value="<?php echo $edit_region ? htmlspecialchars($edit_region['nombre']) : ''; ?>" 
                                                   required maxlength="50">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="descripcion" class="form-label">Descripción</label>
                                            <textarea class="form-control" id="descripcion" name="descripcion" 
                                                      rows="3"><?php echo $edit_region ? htmlspecialchars($edit_region['descripcion']) : ''; ?></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="activo" name="activo" 
                                                       <?php echo (!$edit_region || $edit_region['activo']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="activo">
                                                    Activo
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-<?php echo $edit_region ? 'warning' : 'primary'; ?>">
                                                <i class="fas fa-<?php echo $edit_region ? 'save' : 'plus'; ?>"></i>
                                                <?php echo $edit_region ? 'Actualizar' : 'Crear'; ?> Región
                                            </button>
                                            <?php if ($edit_region): ?>
                                                <a href="index.php" class="btn btn-secondary">
                                                    <i class="fas fa-times"></i> Cancelar
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Lista de regiones -->
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-list"></i> Lista de Regiones</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($regiones)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Nombre</th>
                                                        <th>Descripción</th>
                                                        <th>Estado</th>
                                                        <th>Policías</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($regiones as $region): ?>
                                                        <tr>
                                                            <td><?php echo $region['id']; ?></td>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($region['nombre']); ?></strong>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($region['descripcion']); ?></td>
                                                            <td>
                                                                <?php if ($region['activo']): ?>
                                                                    <span class="badge bg-success">Activo</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary">Inactivo</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-info"><?php echo $region['policias_count']; ?></span>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group" role="group">
                                                                    <a href="?edit=<?php echo $region['id']; ?>" 
                                                                       class="btn btn-sm btn-warning" title="Editar">
                                                                        <i class="fas fa-edit"></i>
                                                                    </a>
                                                                    <?php if ($region['policias_count'] == 0): ?>
                                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                                onclick="confirmarEliminacion(<?php echo $region['id']; ?>, '<?php echo htmlspecialchars($region['nombre']); ?>')" 
                                                                                title="Eliminar">
                                                                            <i class="fas fa-trash"></i>
                                                                        </button>
                                                                    <?php else: ?>
                                                                        <button type="button" class="btn btn-sm btn-secondary" 
                                                                                title="No se puede eliminar (tiene policías asignados)" disabled>
                                                                            <i class="fas fa-trash"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No hay regiones registradas.</p>
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

    <!-- Modal de confirmación para eliminar -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar la región <strong id="regionName"></strong>?</p>
                    <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteId">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmarEliminacion(id, nombre) {
            document.getElementById('deleteId').value = id;
            document.getElementById('regionName').textContent = nombre;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>