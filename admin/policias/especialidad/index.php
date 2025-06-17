<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../../cnx/db_connect.php';

$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'agregar':
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion']);
                
                if (!empty($nombre)) {
                    $stmt = $conn->prepare("INSERT INTO especialidades (nombre, descripcion) VALUES (?, ?)");
                    $stmt->bind_param("ss", $nombre, $descripcion);
                    
                    if ($stmt->execute()) {
                        $mensaje = 'Especialidad agregada exitosamente.';
                        $tipo_mensaje = 'success';
                    } else {
                        if ($conn->errno == 1062) { // Duplicate entry
                            $mensaje = 'Error: Ya existe una especialidad con ese nombre.';
                        } else {
                            $mensaje = 'Error al agregar la especialidad: ' . $conn->error;
                        }
                        $tipo_mensaje = 'danger';
                    }
                    $stmt->close();
                } else {
                    $mensaje = 'El nombre de la especialidad es obligatorio.';
                    $tipo_mensaje = 'warning';
                }
                break;
                
            case 'editar':
                $id = $_POST['id'];
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion']);
                
                if (!empty($nombre)) {
                    $stmt = $conn->prepare("UPDATE especialidades SET nombre = ?, descripcion = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $nombre, $descripcion, $id);
                    
                    if ($stmt->execute()) {
                        $mensaje = 'Especialidad actualizada exitosamente.';
                        $tipo_mensaje = 'success';
                    } else {
                        if ($conn->errno == 1062) { // Duplicate entry
                            $mensaje = 'Error: Ya existe una especialidad con ese nombre.';
                        } else {
                            $mensaje = 'Error al actualizar la especialidad: ' . $conn->error;
                        }
                        $tipo_mensaje = 'danger';
                    }
                    $stmt->close();
                } else {
                    $mensaje = 'El nombre de la especialidad es obligatorio.';
                    $tipo_mensaje = 'warning';
                }
                break;
                
            case 'eliminar':
                $id = $_POST['id'];
                
                // Verificar si hay policías con esta especialidad
                $stmt = $conn->prepare("SELECT COUNT(*) FROM policias WHERE especialidad_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $count = $result->fetch_row()[0];
                $stmt->close();
                
                if ($count > 0) {
                    $mensaje = "No se puede eliminar la especialidad porque hay {$count} policía(s) asignado(s) a ella.";
                    $tipo_mensaje = 'warning';
                } else {
                    $stmt = $conn->prepare("DELETE FROM especialidades WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    
                    if ($stmt->execute()) {
                        $mensaje = 'Especialidad eliminada exitosamente.';
                        $tipo_mensaje = 'success';
                    } else {
                        $mensaje = 'Error al eliminar la especialidad: ' . $conn->error;
                        $tipo_mensaje = 'danger';
                    }
                    $stmt->close();
                }
                break;
        }
    }
}

// Obtener todas las especialidades
$query = "SELECT e.*, 
                 (SELECT COUNT(*) FROM policias p WHERE p.especialidad_id = e.id) as total_policias
          FROM especialidades e 
          ORDER BY e.nombre";
$result = $conn->query($query);
$especialidades = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $especialidades[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Especialidades - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #104c75 0%, #0d3d5c 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,.1);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .page-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 30px;
            text-align: center;
        }
        .table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-weight: 600;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../../inc/sidebar.php'; ?>
            
            <!-- Main Content - CORREGIDO -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
                        <div class="container-fluid">
                            <span class="navbar-brand mb-0 h1">
                                <i class="fas fa-star me-2"></i>Gestión de Especialidades
                            </span>
                        </div>
                    </nav>

                    <div class="container-fluid px-4">
                        <h2 class="page-title">
                            <i class="fas fa-star me-3"></i>Especialidades Policiales
                        </h2>

                        <?php if ($mensaje): ?>
                            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                                <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : ($tipo_mensaje === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                                <?php echo htmlspecialchars($mensaje); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Formulario para agregar especialidad -->
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Agregar Nueva Especialidad</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="accion" value="agregar">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="nombre" class="form-label">Nombre de la Especialidad *</label>
                                                <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="150">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="descripcion" class="form-label">Descripción</label>
                                                <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Agregar Especialidad
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Lista de especialidades -->
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Lista de Especialidades</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Nombre</th>
                                                <th>Descripción</th>
                                                <th>Policías Asignados</th>
                                                <th>Fecha Creación</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($especialidades as $especialidad): ?>
                                                <tr>
                                                    <td><?php echo $especialidad['id']; ?></td>
                                                    <td><strong><?php echo htmlspecialchars($especialidad['nombre']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($especialidad['descripcion'] ?: 'Sin descripción'); ?></td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo $especialidad['total_policias']; ?> policía(s)</span>
                                                    </td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($especialidad['created_at'])); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-warning me-1" onclick="editarEspecialidad(<?php echo $especialidad['id']; ?>, '<?php echo addslashes($especialidad['nombre']); ?>', '<?php echo addslashes($especialidad['descripcion']); ?>')">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if ($especialidad['total_policias'] == 0): ?>
                                                            <button class="btn btn-sm btn-danger" onclick="eliminarEspecialidad(<?php echo $especialidad['id']; ?>, '<?php echo addslashes($especialidad['nombre']); ?>')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-secondary" disabled title="No se puede eliminar: tiene policías asignados">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para editar especialidad -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Especialidad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_nombre" class="form-label">Nombre de la Especialidad *</label>
                            <input type="text" class="form-control" id="edit_nombre" name="nombre" required maxlength="150">
                        </div>
                        <div class="mb-3">
                            <label for="edit_descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="edit_descripcion" name="descripcion" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para confirmar eliminación -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar la especialidad <strong id="delete_nombre"></strong>?</p>
                    <p class="text-danger"><small>Esta acción no se puede deshacer.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" id="delete_id">
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editarEspecialidad(id, nombre, descripcion) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_descripcion').value = descripcion;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function eliminarEspecialidad(id, nombre) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_nombre').textContent = nombre;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>