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
                $direccion = trim($_POST['direccion']);
                $zona = $_POST['zona'];
                $activo = isset($_POST['activo']) ? 1 : 0;
                
                if (!empty($nombre)) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO lugares_guardias (nombre, descripcion, direccion, zona, activo) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$nombre, $descripcion, $direccion, $zona, $activo]);
                        $mensaje = 'Lugar de guardia agregado exitosamente.';
                        $tipo_mensaje = 'success';
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            $mensaje = 'Error: Ya existe un lugar de guardia con ese nombre.';
                        } else {
                            $mensaje = 'Error al agregar el lugar de guardia: ' . $e->getMessage();
                        }
                        $tipo_mensaje = 'danger';
                    }
                } else {
                    $mensaje = 'El nombre del lugar de guardia es obligatorio.';
                    $tipo_mensaje = 'warning';
                }
                break;
                
            case 'editar':
                $id = $_POST['id'];
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion']);
                $direccion = trim($_POST['direccion']);
                $zona = $_POST['zona'];
                $activo = isset($_POST['activo']) ? 1 : 0;
                
                if (!empty($nombre)) {
                    try {
                        $stmt = $pdo->prepare("UPDATE lugares_guardias SET nombre = ?, descripcion = ?, direccion = ?, zona = ?, activo = ? WHERE id = ?");
                        $stmt->execute([$nombre, $descripcion, $direccion, $zona, $activo, $id]);
                        $mensaje = 'Lugar de guardia actualizado exitosamente.';
                        $tipo_mensaje = 'success';
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            $mensaje = 'Error: Ya existe un lugar de guardia con ese nombre.';
                        } else {
                            $mensaje = 'Error al actualizar el lugar de guardia: ' . $e->getMessage();
                        }
                        $tipo_mensaje = 'danger';
                    }
                } else {
                    $mensaje = 'El nombre del lugar de guardia es obligatorio.';
                    $tipo_mensaje = 'warning';
                }
                break;
                
            case 'eliminar':
                $id = $_POST['id'];
                try {
                    // Verificar si hay policías asignados a este lugar
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM policias WHERE lugar_guardia_id = ?");
                    $stmt->execute([$id]);
                    $count_policias = $stmt->fetchColumn();
                    
                    // Verificar si hay guardias realizadas en este lugar
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM guardias_realizadas WHERE lugar_guardia_id = ?");
                    $stmt->execute([$id]);
                    $count_guardias = $stmt->fetchColumn();
                    
                    if ($count_policias > 0 || $count_guardias > 0) {
                        $mensaje = "No se puede eliminar el lugar de guardia porque hay {$count_policias} policía(s) asignado(s) y {$count_guardias} guardia(s) registrada(s).";
                        $tipo_mensaje = 'warning';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM lugares_guardias WHERE id = ?");
                        $stmt->execute([$id]);
                        $mensaje = 'Lugar de guardia eliminado exitosamente.';
                        $tipo_mensaje = 'success';
                    }
                } catch (PDOException $e) {
                    $mensaje = 'Error al eliminar el lugar de guardia: ' . $e->getMessage();
                    $tipo_mensaje = 'danger';
                }
                break;
        }
    }
}

// Obtener todos los lugares de guardias
$stmt = $pdo->query("SELECT lg.*, 
                            (SELECT COUNT(*) FROM policias p WHERE p.lugar_guardia_id = lg.id) as total_policias,
                            (SELECT COUNT(*) FROM guardias_realizadas gr WHERE gr.lugar_guardia_id = lg.id) as total_guardias
                     FROM lugares_guardias lg 
                     ORDER BY lg.zona, lg.nombre");
$lugares = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Lugares de Guardias - Sistema RH</title>
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
            background: linear-gradient(135deg, #fd7e14 0%, #e83e8c 100%);
            color: white;
            border: none;
            font-weight: 600;
        }
        .btn-warning {
            background: linear-gradient(135deg, #fd7e14 0%, #e83e8c 100%);
            border: none;
            color: white;
        }
        .btn-warning:hover {
            background: linear-gradient(135deg, #e96b00 0%, #d91a72 100%);
            color: white;
        }
        .zona-badge {
            font-size: 0.85em;
            padding: 0.4em 0.7em;
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
                            <i class="fas fa-map-marker-alt me-2"></i>Gestión de Lugares de Guardias
                        </span>
                    </div>
                </nav>

                <div class="container-fluid px-4">
                    <h2 class="page-title">
                        <i class="fas fa-map-marker-alt me-3"></i>Lugares de Guardias
                    </h2>

                    <?php if ($mensaje): ?>
                        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : ($tipo_mensaje === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                            <?php echo htmlspecialchars($mensaje); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Formulario para agregar lugar de guardia -->
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-white">
                            <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Agregar Nuevo Lugar de Guardia</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="accion" value="agregar">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="nombre" class="form-label">Nombre del Lugar *</label>
                                            <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="100">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="zona" class="form-label">Zona *</label>
                                            <select class="form-control" id="zona" name="zona" required>
                                                <option value="">Seleccionar zona...</option>
                                                <option value="Central">Central</option>
                                                <option value="Regional">Regional</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label for="direccion" class="form-label">Dirección</label>
                                            <input type="text" class="form-control" id="direccion" name="direccion" maxlength="200">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-10">
                                        <div class="mb-3">
                                            <label for="descripcion" class="form-label">Descripción</label>
                                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label class="form-label">Estado</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="activo" name="activo" checked>
                                                <label class="form-check-label" for="activo">
                                                    Activo
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-save me-2"></i>Agregar Lugar
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Lista de lugares de guardias -->
                    <div class="card">
                        <div class="card-header bg-warning text-white">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Lista de Lugares de Guardias</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Zona</th>
                                            <th>Dirección</th>
                                            <th>Policías</th>
                                            <th>Guardias</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lugares as $lugar): ?>
                                            <tr>
                                                <td><?php echo $lugar['id']; ?></td>
                                                <td><strong><?php echo htmlspecialchars($lugar['nombre']); ?></strong></td>
                                                <td>
                                                    <span class="badge zona-badge <?php echo $lugar['zona'] === 'Central' ? 'bg-primary' : 'bg-secondary'; ?>">
                                                        <?php echo htmlspecialchars($lugar['zona']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($lugar['direccion'] ?: 'Sin dirección'); ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $lugar['total_policias']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success"><?php echo $lugar['total_guardias']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $lugar['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo $lugar['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary me-1" onclick="verDetalles(<?php echo $lugar['id']; ?>)" title="Ver detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning me-1" onclick="editarLugar(<?php echo $lugar['id']; ?>, '<?php echo addslashes($lugar['nombre']); ?>', '<?php echo addslashes($lugar['descripcion']); ?>', '<?php echo addslashes($lugar['direccion']); ?>', '<?php echo $lugar['zona']; ?>', <?php echo $lugar['activo']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($lugar['total_policias'] == 0 && $lugar['total_guardias'] == 0): ?>
                                                        <button class="btn btn-sm btn-danger" onclick="eliminarLugar(<?php echo $lugar['id']; ?>, '<?php echo addslashes($lugar['nombre']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-secondary" disabled title="No se puede eliminar: tiene registros asociados">
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

    <!-- Modal para editar lugar -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Lugar de Guardia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_nombre" class="form-label">Nombre del Lugar *</label>
                                    <input type="text" class="form-control" id="edit_nombre" name="nombre" required maxlength="100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_zona" class="form-label">Zona *</label>
                                    <select class="form-control" id="edit_zona" name="zona" required>
                                        <option value="Central">Central</option>
                                        <option value="Regional">Regional</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_direccion" class="form-label">Dirección</label>
                            <input type="text" class="form-control" id="edit_direccion" name="direccion" maxlength="200">
                        </div>
                        <div class="row">
                            <div class="col-md-10">
                                <div class="mb-3">
                                    <label for="edit_descripcion" class="form-label">Descripción</label>
                                    <textarea class="form-control" id="edit_descripcion" name="descripcion" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-3">
                                    <label class="form-label">Estado</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_activo" name="activo">
                                        <label class="form-check-label" for="edit_activo">
                                            Activo
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para ver detalles -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Detalles del Lugar de Guardia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent">
                    <!-- Contenido cargado dinámicamente -->
                </div>
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
                    <p>¿Está seguro de que desea eliminar el lugar de guardia <strong id="deleteNombre"></strong>?</p>
                    <p class="text-danger"><i class="fas fa-warning me-2"></i>Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" id="deleteId">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editarLugar(id, nombre, descripcion, direccion, zona, activo) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_descripcion').value = descripcion;
            document.getElementById('edit_direccion').value = direccion;
            document.getElementById('edit_zona').value = zona;
            document.getElementById('edit_activo').checked = activo == 1;
            
            var editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }

        function eliminarLugar(id, nombre) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteNombre').textContent = nombre;
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        function verDetalles(id) {
            // Hacer una petición AJAX para obtener los detalles
            fetch('detalles_lugar.php?id=' + id)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('detailContent').innerHTML = data;
                    var detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
                    detailModal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('detailContent').innerHTML = '<p class="text-danger">Error al cargar los detalles.</p>';
                    var detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
                    detailModal.show();
                });
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });

        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>
</body>
</html>