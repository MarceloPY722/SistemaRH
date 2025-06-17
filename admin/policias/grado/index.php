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
                $nivel_jerarquia = intval($_POST['nivel_jerarquia']);
                $abreviatura = trim($_POST['abreviatura']);
                
                if (!empty($nombre) && $nivel_jerarquia > 0) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO grados (nombre, nivel_jerarquia, abreviatura) VALUES (?, ?, ?)");
                        $stmt->execute([$nombre, $nivel_jerarquia, $abreviatura]);
                        $mensaje = 'Grado agregado exitosamente.';
                        $tipo_mensaje = 'success';
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            $mensaje = 'Error: Ya existe un grado con ese nombre o nivel de jerarquía.';
                        } else {
                            $mensaje = 'Error al agregar el grado: ' . $e->getMessage();
                        }
                        $tipo_mensaje = 'danger';
                    }
                } else {
                    $mensaje = 'El nombre y nivel de jerarquía son obligatorios.';
                    $tipo_mensaje = 'warning';
                }
                break;
                
            case 'editar':
                $id = $_POST['id'];
                $nombre = trim($_POST['nombre']);
                $nivel_jerarquia = intval($_POST['nivel_jerarquia']);
                $abreviatura = trim($_POST['abreviatura']);
                
                if (!empty($nombre) && $nivel_jerarquia > 0) {
                    try {
                        $stmt = $pdo->prepare("UPDATE grados SET nombre = ?, nivel_jerarquia = ?, abreviatura = ? WHERE id = ?");
                        $stmt->execute([$nombre, $nivel_jerarquia, $abreviatura, $id]);
                        $mensaje = 'Grado actualizado exitosamente.';
                        $tipo_mensaje = 'success';
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            $mensaje = 'Error: Ya existe un grado con ese nombre o nivel de jerarquía.';
                        } else {
                            $mensaje = 'Error al actualizar el grado: ' . $e->getMessage();
                        }
                        $tipo_mensaje = 'danger';
                    }
                } else {
                    $mensaje = 'El nombre y nivel de jerarquía son obligatorios.';
                    $tipo_mensaje = 'warning';
                }
                break;
                
            case 'eliminar':
                $id = $_POST['id'];
                try {
                    // Verificar si hay policías con este grado
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM policias WHERE grado_id = ?");
                    $stmt->execute([$id]);
                    $count = $stmt->fetchColumn();
                    
                    if ($count > 0) {
                        $mensaje = "No se puede eliminar el grado porque hay {$count} policía(s) asignado(s) a él.";
                        $tipo_mensaje = 'warning';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM grados WHERE id = ?");
                        $stmt->execute([$id]);
                        $mensaje = 'Grado eliminado exitosamente.';
                        $tipo_mensaje = 'success';
                    }
                } catch (PDOException $e) {
                    $mensaje = 'Error al eliminar el grado: ' . $e->getMessage();
                    $tipo_mensaje = 'danger';
                }
                break;
        }
    }
}

// Obtener todos los grados
$stmt = $pdo->query("SELECT g.*, 
                            (SELECT COUNT(*) FROM policias p WHERE p.grado_id = g.id) as total_policias
                     FROM grados g 
                     ORDER BY g.nivel_jerarquia ASC");
$grados = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Grados - Sistema RH</title>
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            font-weight: 600;
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
        }
        .btn-success:hover {
            background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
        }
        .jerarquia-badge {
            font-size: 0.9em;
            padding: 0.5em 0.8em;
        }
    </style>
</head>
<body>
    <?php include '../../inc/sidebar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-10 offset-md-2 main-content">
                <nav class="navbar navbar-expand-lg navbar-dark mb-4">
                    <div class="container-fluid">
                        <span class="navbar-brand mb-0 h1">
                            <i class="fas fa-medal me-2"></i>Gestión de Grados
                        </span>
                    </div>
                </nav>

                <div class="container-fluid px-4">
                    <h2 class="page-title">
                        <i class="fas fa-medal me-3"></i>Grados Policiales
                    </h2>

                    <?php if ($mensaje): ?>
                        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : ($tipo_mensaje === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                            <?php echo htmlspecialchars($mensaje); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Formulario para agregar grado -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Agregar Nuevo Grado</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="accion" value="agregar">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="nombre" class="form-label">Nombre del Grado *</label>
                                            <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="100">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="nivel_jerarquia" class="form-label">Nivel de Jerarquía *</label>
                                            <input type="number" class="form-control" id="nivel_jerarquia" name="nivel_jerarquia" required min="1" max="100">
                                            <div class="form-text">1 = Mayor jerarquía, números mayores = menor jerarquía</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="abreviatura" class="form-label">Abreviatura</label>
                                            <input type="text" class="form-control" id="abreviatura" name="abreviatura" maxlength="20">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-2"></i>Agregar Grado
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Lista de grados -->
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Lista de Grados (Ordenados por Jerarquía)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Jerarquía</th>
                                            <th>Nombre</th>
                                            <th>Abreviatura</th>
                                            <th>Policías Asignados</th>
                                            <th>Fecha Creación</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grados as $grado): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge jerarquia-badge <?php echo $grado['nivel_jerarquia'] <= 5 ? 'bg-danger' : ($grado['nivel_jerarquia'] <= 10 ? 'bg-warning' : 'bg-primary'); ?>">
                                                        Nivel <?php echo $grado['nivel_jerarquia']; ?>
                                                    </span>
                                                </td>
                                                <td><strong><?php echo htmlspecialchars($grado['nombre']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($grado['abreviatura'] ?: 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $grado['total_policias']; ?> policía(s)</span>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($grado['created_at'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning me-1" onclick="editarGrado(<?php echo $grado['id']; ?>, '<?php echo addslashes($grado['nombre']); ?>', <?php echo $grado['nivel_jerarquia']; ?>, '<?php echo addslashes($grado['abreviatura']); ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($grado['total_policias'] == 0): ?>
                                                        <button class="btn btn-sm btn-danger" onclick="eliminarGrado(<?php echo $grado['id']; ?>, '<?php echo addslashes($grado['nombre']); ?>')">
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

    <!-- Modal para editar grado -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Grado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_nombre" class="form-label">Nombre del Grado *</label>
                            <input type="text" class="form-control" id="edit_nombre" name="nombre" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="edit_nivel_jerarquia" class="form-label">Nivel de Jerarquía *</label>
                            <input type="number" class="form-control" id="edit_nivel_jerarquia" name="nivel_jerarquia" required min="1" max="100">
                            <div class="form-text">1 = Mayor jerarquía, números mayores = menor jerarquía</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_abreviatura" class="form-label">Abreviatura</label>
                            <input type="text" class="form-control" id="edit_abreviatura" name="abreviatura" maxlength="20">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Guardar Cambios</button>
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
                    <p>¿Está seguro que desea eliminar el grado <strong id="delete_nombre"></strong>?</p>
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
        function editarGrado(id, nombre, nivel_jerarquia, abreviatura) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_nivel_jerarquia').value = nivel_jerarquia;
            document.getElementById('edit_abreviatura').value = abreviatura;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function eliminarGrado(id, nombre) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_nombre').textContent = nombre;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>