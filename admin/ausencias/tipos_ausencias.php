<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'crear':
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion']);
                $requiere_justificacion = isset($_POST['requiere_justificacion']) ? 1 : 0;
                
                if (!empty($nombre)) {
                    // Obtener el siguiente ID correlativo
                    $stmt_next = $conn->prepare("SELECT COALESCE(MAX(id), 0) + 1 as next_id FROM tipos_ausencias");
                    $stmt_next->execute();
                    $next_id = $stmt_next->fetch(PDO::FETCH_ASSOC)['next_id'];
                    
                    $stmt = $conn->prepare("INSERT INTO tipos_ausencias (id, nombre, descripcion, requiere_justificacion) VALUES (?, ?, ?, ?)");
                    
                    if ($stmt->execute([$next_id, $nombre, $descripcion, $requiere_justificacion])) {
                        $mensaje = "Tipo de ausencia creado exitosamente.";
                        $tipo_mensaje = "success";
                    } else {
                        $mensaje = "Error al crear el tipo de ausencia.";
                        $tipo_mensaje = "danger";
                    }
                } else {
                    $mensaje = "El nombre del tipo de ausencia es obligatorio.";
                    $tipo_mensaje = "warning";
                }
                break;
                
            case 'editar':
                $id = $_POST['id'];
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion']);
                $requiere_justificacion = isset($_POST['requiere_justificacion']) ? 1 : 0;
                
                if (!empty($nombre) && !empty($id)) {
                    $stmt = $conn->prepare("UPDATE tipos_ausencias SET nombre = ?, descripcion = ?, requiere_justificacion = ? WHERE id = ?");
                    
                    if ($stmt->execute([$nombre, $descripcion, $requiere_justificacion, $id])) {
                        $mensaje = "Tipo de ausencia actualizado exitosamente.";
                        $tipo_mensaje = "success";
                    } else {
                        $mensaje = "Error al actualizar el tipo de ausencia.";
                        $tipo_mensaje = "danger";
                    }
                } else {
                    $mensaje = "Datos incompletos para actualizar.";
                    $tipo_mensaje = "warning";
                }
                break;
                
            case 'eliminar':
                $id = $_POST['id'];
                
                if (!empty($id)) {
                  
                    $check = $conn->prepare("SELECT COUNT(*) as count FROM ausencias WHERE tipo_ausencia_id = ?");
                    $check->execute([$id]);
                    $count = $check->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    if ($count > 0) {
                        $mensaje = "No se puede eliminar este tipo de ausencia porque está siendo utilizado en registros existentes.";
                        $tipo_mensaje = "warning";
                    } else {
                        $stmt = $conn->prepare("DELETE FROM tipos_ausencias WHERE id = ?");
                        
                        if ($stmt->execute([$id])) {
                            $mensaje = "Tipo de ausencia eliminado exitosamente.";
                            $tipo_mensaje = "success";
                        } else {
                            $mensaje = "Error al eliminar el tipo de ausencia.";
                            $tipo_mensaje = "danger";
                        }
                    }
                } else {
                    $mensaje = "ID de tipo de ausencia no válido.";
                    $tipo_mensaje = "warning";
                }
                break;
        }
    }
}

$stmt_tipos = $conn->prepare("SELECT * FROM tipos_ausencias ORDER BY nombre");
$stmt_tipos->execute();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tipos de Ausencias - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #104c75;
            --secondary-color: #0d3d5c;
            --accent-color: #1a5a8a;
            --light-bg: #f8f9fa;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(16, 76, 117, 0.2);
            margin: 20px auto;
            padding: 30px;
            max-width: 1200px;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .page-header h1 {
            margin: 0;
            font-weight: 700;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 76, 117, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            border: none;
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border: none;
        }
        
        .table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .table thead {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .table tbody tr:hover {
            background-color: rgba(16, 76, 117, 0.05);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(16, 76, 117, 0.25);
        }
        
        .back-btn {
            background: linear-gradient(135deg, var(--text-secondary), #5a6268);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .back-btn:hover {
            background: linear-gradient(135deg, #5a6268, var(--primary-color));
            color: white;
            transform: translateY(-2px);
        }
        
        .badge {
            font-size: 0.75em;
            padding: 0.5em 0.75em;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Botón de regreso -->
        <a href="../config/config_policias.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Volver a Configuración
        </a>
        
        <div class="main-container">
            <!-- Encabezado -->
            <div class="page-header">
                <h1><i class="fas fa-calendar-times"></i> Gestión de Tipos de Ausencias</h1>
                <p class="mb-0">Administra los tipos de ausencias disponibles en el sistema</p>
            </div>
            
            <!-- Mensajes -->
            <?php if (isset($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Botón para agregar nuevo tipo -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Lista de Tipos de Ausencias</h3>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
                    <i class="fas fa-plus"></i> Nuevo Tipo de Ausencia
                </button>
            </div>
            
            <!-- Tabla de tipos de ausencias -->
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Requiere Justificación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($stmt_tipos && $stmt_tipos->rowCount() > 0): ?>
                            <?php while ($tipo = $stmt_tipos->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td><?php echo $tipo['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($tipo['nombre']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($tipo['descripcion'] ?? 'Sin descripción'); ?></td>
                                    <td>
                                        <?php if ($tipo['requiere_justificacion']): ?>
                                            <span class="badge bg-warning">Sí</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-warning btn-sm" 
                                                onclick="editarTipo(<?php echo $tipo['id']; ?>, '<?php echo addslashes($tipo['nombre']); ?>', '<?php echo addslashes($tipo['descripcion'] ?? ''); ?>', <?php echo $tipo['requiere_justificacion']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" 
                                                onclick="confirmarEliminar(<?php echo $tipo['id']; ?>, '<?php echo addslashes($tipo['nombre']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                    No hay tipos de ausencias registrados
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal para crear tipo de ausencia -->
    <div class="modal fade" id="modalCrear" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Nuevo Tipo de Ausencia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="crear">
                        
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="requiere_justificacion" name="requiere_justificacion">
                                <label class="form-check-label" for="requiere_justificacion">
                                    Requiere justificación
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Tipo de Ausencia</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para editar tipo de ausencia -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Editar Tipo de Ausencia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="editar">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="mb-3">
                            <label for="edit_nombre" class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="edit_descripcion" name="descripcion" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_requiere_justificacion" name="requiere_justificacion">
                                <label class="form-check-label" for="edit_requiere_justificacion">
                                    Requiere justificación
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar Tipo de Ausencia</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para confirmar eliminación -->
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="eliminar">
                        <input type="hidden" name="id" id="delete_id">
                        
                        <p>¿Está seguro que desea eliminar el tipo de ausencia <strong id="delete_nombre"></strong>?</p>
                        <p class="text-danger"><small><i class="fas fa-info-circle"></i> Esta acción no se puede deshacer.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editarTipo(id, nombre, descripcion, requiere_justificacion) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_descripcion').value = descripcion;
            document.getElementById('edit_requiere_justificacion').checked = requiere_justificacion == 1;
            
            new bootstrap.Modal(document.getElementById('modalEditar')).show();
        }
        
        function confirmarEliminar(id, nombre) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_nombre').textContent = nombre;
            
            new bootstrap.Modal(document.getElementById('modalEliminar')).show();
        }
    </script>
</body>
</html>