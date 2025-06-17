<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit();
}
require_once '../../../cnx/db_connect.php';

$mensaje = '';
$tipo_mensaje = '';

// Procesar formularios POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        case 'agregar_lugar':
            $nombre = trim($_POST['nombre_lugar'] ?? '');
            $zona = trim($_POST['zona_lugar'] ?? '');
            $direccion = trim($_POST['direccion_lugar'] ?? '');
            $descripcion = trim($_POST['descripcion_lugar'] ?? '');
            $activo = isset($_POST['activo_lugar']) ? 1 : 0;
            
            if (!empty($nombre) && !empty($zona)) {
                // Verificar si ya existe un lugar con el mismo nombre
                $stmt_check = $conn->prepare("SELECT id FROM lugares_guardias WHERE nombre = ?");
                if ($stmt_check) {
                    $stmt_check->bind_param("s", $nombre);
                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();
                    
                    if ($result_check->num_rows > 0) {
                        $mensaje = "Ya existe un lugar de guardia con ese nombre.";
                        $tipo_mensaje = "warning";
                    } else {
                        // Insertar nuevo lugar
                        $stmt = $conn->prepare("INSERT INTO lugares_guardias (nombre, zona, direccion, descripcion, activo) VALUES (?, ?, ?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param("ssssi", $nombre, $zona, $direccion, $descripcion, $activo);
                            
                            if ($stmt->execute()) {
                                $mensaje = "Lugar de guardia agregado exitosamente.";
                                $tipo_mensaje = "success";
                            } else {
                                $mensaje = "Error al agregar el lugar de guardia: " . $stmt->error;
                                $tipo_mensaje = "danger";
                            }
                            $stmt->close();
                        } else {
                            $mensaje = "Error en la preparación de la consulta: " . $conn->error;
                            $tipo_mensaje = "danger";
                        }
                    }
                    $stmt_check->close();
                } else {
                    $mensaje = "Error al verificar duplicados: " . $conn->error;
                    $tipo_mensaje = "danger";
                }
            } else {
                $mensaje = "El nombre y la zona son campos obligatorios.";
                $tipo_mensaje = "warning";
            }
            break;
            
        case 'eliminar_lugar':
            $lugar_id = intval($_POST['lugar_id'] ?? 0);
            
            if ($lugar_id > 0) {
                $stmt = $conn->prepare("DELETE FROM lugares_guardias WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $lugar_id);
                    
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            $mensaje = "Lugar de guardia eliminado exitosamente.";
                            $tipo_mensaje = "success";
                        } else {
                            $mensaje = "No se encontró el lugar de guardia a eliminar.";
                            $tipo_mensaje = "warning";
                        }
                    } else {
                        $mensaje = "Error al eliminar el lugar de guardia: " . $stmt->error;
                        $tipo_mensaje = "danger";
                    }
                    $stmt->close();
                } else {
                    $mensaje = "Error en la preparación de la consulta: " . $conn->error;
                    $tipo_mensaje = "danger";
                }
            } else {
                $mensaje = "ID de lugar inválido.";
                $tipo_mensaje = "warning";
            }
            break;
    }
}
$lugares_guardia = [];
$result_lugares = $conn->query("SELECT * FROM lugares_guardias ORDER BY nombre ASC");
if ($result_lugares) {
    while ($row = $result_lugares->fetch_assoc()) {
        $lugares_guardia[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Lugares de Guardia - Sistema RH</title>
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
                                <i class="fas fa-map-marker-alt me-2"></i>Gestión de Lugares de Guardia
                            </span>
                        </div>
                    </nav>
                    <div class="container-fluid px-4">
                        <h2 class="page-title-custom">
                            <i class="fas fa-map-marked-alt me-3"></i>Lugares de Guardia
                        </h2>
                        <?php if ($mensaje): ?>
                            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                                <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : ($tipo_mensaje === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                                <?php echo htmlspecialchars($mensaje); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <div class="card mb-4">
                            <div class="card-header card-header-custom">
                                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Agregar Nuevo Lugar de Guardia</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                    <input type="hidden" name="accion" value="agregar_lugar">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="nombre_lugar" class="form-label">Nombre del Lugar *</label>
                                            <input type="text" class="form-control" id="nombre_lugar" name="nombre_lugar" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="zona_lugar" class="form-label">Zona *</label>
                                            <input type="text" class="form-control" id="zona_lugar" name="zona_lugar" required placeholder="Escribe la zona...">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="direccion_lugar" class="form-label">Dirección</label>
                                        <input type="text" class="form-control" id="direccion_lugar" name="direccion_lugar">
                                    </div>
                                    <div class="mb-3">
                                        <label for="descripcion_lugar" class="form-label">Descripción</label>
                                        <textarea class="form-control" id="descripcion_lugar" name="descripcion_lugar" rows="3"></textarea>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" value="1" id="activo_lugar" name="activo_lugar" checked>
                                        <label class="form-check-label" for="activo_lugar">Activo</label>
                                    </div>
                                    <button type="submit" class="btn btn-custom-primary"><i class="fas fa-save me-2"></i>Guardar Lugar</button>
                                </form>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header card-header-custom">
                                <h5 class="mb-0"><i class="fas fa-list-ul me-2"></i>Listado de Lugares de Guardia</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Nombre</th>
                                                <th>Zona</th>
                                                <th>Dirección</th>
                                                <th>Activo</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($lugares_guardia) > 0): ?>
                                                <?php foreach ($lugares_guardia as $lugar): ?>
                                                <tr>
                                                    <td><?php echo $lugar['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($lugar['nombre']); ?></td>
                                                    <td><?php echo htmlspecialchars($lugar['zona']); ?></td>
                                                    <td><?php echo htmlspecialchars($lugar['direccion'] ?: 'N/A'); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $lugar['activo'] ? 'success' : 'danger'; ?>">
                                                            <?php echo $lugar['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="editar_lugar.php?id=<?php echo $lugar['id']; ?>" class="btn btn-sm btn-warning me-1" title="Editar"><i class="fas fa-edit"></i></a>
                                                        <a href="#" onclick="confirmarEliminacion(<?php echo $lugar['id']; ?>,'<?php echo htmlspecialchars(addslashes($lugar['nombre'])); ?>')" class="btn btn-sm btn-danger" title="Eliminar"><i class="fas fa-trash"></i></a>
                                                        <a href="detalles_lugar.php?id=<?php echo $lugar['id']; ?>" class="btn btn-sm btn-info ms-1" title="Detalles"><i class="fas fa-eye"></i></a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">No hay lugares de guardia registrados.</td>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmarEliminacion(id, nombre) {
            if (confirm(`¿Está seguro de que desea eliminar el lugar de guardia "${nombre}" (ID: ${id})? Esta acción no se puede deshacer.`)) {
                let form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>';
                let accionInput = document.createElement('input');
                accionInput.type = 'hidden';
                accionInput.name = 'accion';
                accionInput.value = 'eliminar_lugar';
                form.appendChild(accionInput);
                let idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'lugar_id';
                idInput.value = id;
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>