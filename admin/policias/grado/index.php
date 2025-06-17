<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit();
}
require_once '../../../cnx/db_connect.php';

$mensaje = '';
$tipo_mensaje = '';
$grados = [];

// Obtener lista de grados
$result_grados = $conn->query("SELECT * FROM grados ORDER BY nivel_jerarquia ASC, nombre ASC");
if ($result_grados) {
    while ($row = $result_grados->fetch_assoc()) {
        $grados[] = $row;
    }
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'agregar_grado':
                $nombre = trim($_POST['nombre_grado']);
                $nivel_jerarquia = intval($_POST['nivel_grado']);
                $abreviatura = trim($_POST['abreviatura_grado']);
                
                if (!empty($nombre) && $nivel_jerarquia > 0) {
                    // Verificar si la consulta se prepara correctamente
                    $stmt = $conn->prepare("INSERT INTO grados (nombre, nivel_jerarquia, abreviatura) VALUES (?, ?, ?)");
                    
                    if ($stmt === false) {
                        $mensaje = "Error al preparar la consulta: " . $conn->error;
                        $tipo_mensaje = "danger";
                    } else {
                        $stmt->bind_param("sis", $nombre, $nivel_jerarquia, $abreviatura);
                        
                        if ($stmt->execute()) {
                            $mensaje = "Grado agregado exitosamente.";
                            $tipo_mensaje = "success";
                        } else {
                            $mensaje = "Error al agregar el grado: " . $stmt->error;
                            $tipo_mensaje = "danger";
                        }
                        $stmt->close();
                    }
                } else {
                    $mensaje = "Por favor, complete todos los campos obligatorios.";
                    $tipo_mensaje = "warning";
                }
                break;
                
            case 'editar_grado':
                $id = intval($_POST['id']);
                $nombre = trim($_POST['nombre']);
                $nivel_jerarquia = intval($_POST['nivel_jerarquia']);
                $abreviatura = trim($_POST['abreviatura']);
                
                if (!empty($nombre) && $nivel_jerarquia > 0 && $id > 0) {
                    $stmt = $conn->prepare("UPDATE grados SET nombre = ?, nivel_jerarquia = ?, abreviatura = ? WHERE id = ?");
                    
                    if ($stmt === false) {
                        $mensaje = "Error al preparar la consulta de actualización: " . $conn->error;
                        $tipo_mensaje = "danger";
                    } else {
                        $stmt->bind_param("sisi", $nombre, $nivel_jerarquia, $abreviatura, $id);
                        
                        if ($stmt->execute()) {
                            $mensaje = "Grado actualizado exitosamente.";
                            $tipo_mensaje = "success";
                        } else {
                            $mensaje = "Error al actualizar el grado: " . $stmt->error;
                            $tipo_mensaje = "danger";
                        }
                        $stmt->close();
                    }
                } else {
                    $mensaje = "Por favor, complete todos los campos obligatorios.";
                    $tipo_mensaje = "warning";
                }
                break;
                
            case 'eliminar_grado':
                $grado_id = intval($_POST['grado_id']);
                if ($grado_id > 0) {
                    $stmt = $conn->prepare("DELETE FROM grados WHERE id = ?");
                    
                    if ($stmt === false) {
                        $mensaje = "Error al preparar la consulta de eliminación: " . $conn->error;
                        $tipo_mensaje = "danger";
                    } else {
                        $stmt->bind_param("i", $grado_id);
                        
                        if ($stmt->execute()) {
                            $mensaje = "Grado eliminado exitosamente.";
                            $tipo_mensaje = "success";
                        } else {
                            $mensaje = "Error al eliminar el grado: " . $stmt->error;
                            $tipo_mensaje = "danger";
                        }
                        $stmt->close();
                    }
                }
                break;
        }
        
        // Recargar la lista después de cualquier operación
        $grados = [];
        $result_grados = $conn->query("SELECT * FROM grados ORDER BY nivel_jerarquia ASC, nombre ASC");
        if ($result_grados) {
            while ($row = $result_grados->fetch_assoc()) {
                $grados[] = $row;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Grados Policiales - Sistema RH</title>
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
        .nivel-badge {
            font-size: 0.9em;
            padding: 0.4em 0.8em;
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
                                <i class="fas fa-star me-2"></i>Gestión de Grados Policiales
                            </span>
                        </div>
                    </nav>
                    
                    <div class="container-fluid px-4">
                        <h2 class="page-title-custom">
                            <i class="fas fa-medal me-3"></i>Grados Policiales
                        </h2>
                        
                        <?php if ($mensaje): ?>
                            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                                <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : ($tipo_mensaje === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                                <?php echo htmlspecialchars($mensaje); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Formulario para agregar nuevo grado -->
                        <div class="card mb-4">
                            <div class="card-header card-header-custom">
                                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Agregar Nuevo Grado Policial</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                    <input type="hidden" name="accion" value="agregar_grado">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="nombre_grado" class="form-label">Nombre del Grado *</label>
                                            <input type="text" class="form-control" id="nombre_grado" name="nombre_grado" required placeholder="Ej: Cabo, Sargento, Teniente...">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="nivel_grado" class="form-label">Nivel Jerárquico *</label>
                                            <input type="number" class="form-control" id="nivel_grado" name="nivel_grado" required min="1" max="20" placeholder="1-20 (1=más alto, 20=más bajo)">
                                            <div class="form-text">Nivel jerárquico del grado (1 = más alto, 20 = más bajo)</div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="abreviatura_grado" class="form-label">Abreviatura</label>
                                            <input type="text" class="form-control" id="abreviatura_grado" name="abreviatura_grado" placeholder="Ej: Cabo, Sgto, Tte...">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-custom-primary">
                                        <i class="fas fa-save me-2"></i>Guardar Grado
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Listado de grados -->
                        <div class="card">
                            <div class="card-header card-header-custom">
                                <h5 class="mb-0"><i class="fas fa-list-ul me-2"></i>Listado de Grados Policiales</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Nombre</th>
                                                <th>Nivel Jerárquico</th>
                                                <th>Abreviatura</th>
                                                <th>Fecha Creación</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($grados) > 0): ?>
                                                <?php foreach ($grados as $grado): ?>
                                                <tr>
                                                    <td><?php echo $grado['id']; ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($grado['nombre']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary nivel-badge">
                                                            Nivel <?php echo $grado['nivel_jerarquia']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($grado['abreviatura'] ?: 'N/A'); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo date('d/m/Y', strtotime($grado['created_at'])); ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-warning me-1" onclick="editarGrado(<?php echo $grado['id']; ?>, '<?php echo addslashes($grado['nombre']); ?>', <?php echo $grado['nivel_jerarquia']; ?>, '<?php echo addslashes($grado['abreviatura']); ?>')" title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="confirmarEliminacion(<?php echo $grado['id']; ?>,'<?php echo htmlspecialchars(addslashes($grado['nombre'])); ?>')" title="Eliminar">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <a href="detalles_grado.php?id=<?php echo $grado['id']; ?>" class="btn btn-sm btn-info ms-1" title="Detalles">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">
                                                        <i class="fas fa-info-circle me-2"></i>No hay grados policiales registrados.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
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
                        <input type="hidden" name="accion" value="editar_grado">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_nombre" class="form-label">Nombre del Grado *</label>
                            <input type="text" class="form-control" id="edit_nombre" name="nombre" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="edit_nivel_jerarquia" class="form-label">Nivel Jerárquico *</label>
                            <input type="number" class="form-control" id="edit_nivel_jerarquia" name="nivel_jerarquia" required min="1" max="20">
                            <div class="form-text">Nivel jerárquico del grado (1 = más alto, 20 = más bajo)</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_abreviatura" class="form-label">Abreviatura</label>
                            <input type="text" class="form-control" id="edit_abreviatura" name="abreviatura" maxlength="10">
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editarGrado(id, nombre, nivel_jerarquia, abreviatura) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_nivel_jerarquia').value = nivel_jerarquia;
            document.getElementById('edit_abreviatura').value = abreviatura || '';
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }
        
        function confirmarEliminacion(id, nombre) {
            if (confirm(`¿Está seguro de que desea eliminar el grado "${nombre}" (ID: ${id})? Esta acción no se puede deshacer.`)) {
                let form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>';
                
                let accionInput = document.createElement('input');
                accionInput.type = 'hidden';
                accionInput.name = 'accion';
                accionInput.value = 'eliminar_grado';
                form.appendChild(accionInput);
                
                let idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'grado_id';
                idInput.value = id;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>