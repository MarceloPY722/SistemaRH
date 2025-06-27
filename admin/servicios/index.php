<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

$policias_jefe = $conn->query("
    SELECT p.id, p.nombre, p.apellido, g.nombre as grado
    FROM policias p
    JOIN grados g ON p.grado_id = g.id
    WHERE p.activo = 1 AND g.nivel_jerarquia <= 5
    ORDER BY g.nivel_jerarquia ASC, p.apellido ASC
");

// Procesar formulario de nuevo servicio
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'crear') {
    $nombre = trim($_POST['nombre']);
    $fecha_servicio = $_POST['fecha_servicio'];
    $descripcion = trim($_POST['descripcion']);
    $orden_del_dia = trim($_POST['orden_del_dia']);
    $jefe_servicio_id = $_POST['jefe_servicio_id'] ?: null;
    
    $sql = "INSERT INTO servicios (nombre, fecha_servicio, descripcion, orden_del_dia, jefe_servicio_id) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $nombre, $fecha_servicio, $descripcion, $orden_del_dia, $jefe_servicio_id);
    
    if ($stmt->execute()) {
        $mensaje = "<div class='alert alert-success'>Servicio programado exitosamente</div>";
    } else {
        $mensaje = "<div class='alert alert-danger'>Error al programar servicio: " . $conn->error . "</div>";
    }
}

// Obtener servicios programados
$servicios = $conn->query("
    SELECT s.*, p.nombre as jefe_nombre, p.apellido as jefe_apellido, g.nombre as jefe_grado
    FROM servicios s
    LEFT JOIN policias p ON s.jefe_servicio_id = p.id
    LEFT JOIN grados g ON p.grado_id = g.id
    ORDER BY s.fecha_servicio DESC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programar Servicios - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(45deg, #104c75, #0d3d5c) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background: linear-gradient(45deg, #104c75, #0d3d5c);
            border: none;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .main-content {
            padding: 30px;
        }
        .page-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 30px;
        }
        .table th {
            background-color: #34495e;
            color: white;
            border: none;
        }
    </style>
</head>
<body>
   

    <div class="container-fluid">
        <div class="row">
            <?php 
            $_GET['page'] = 'servicios';
            include '../inc/sidebar.php'; 
            ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <h1 class="page-title">
                        <i class="fas fa-calendar-alt"></i> Programar Servicios
                    </h1>

                    <?php if (isset($mensaje)) echo $mensaje; ?>

                    <!-- Botón para programar nuevo servicio -->
                    <div class="mb-3">
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNuevoServicio">
                            <i class="fas fa-calendar-plus"></i> Programar Nuevo Servicio
                        </button>
                    </div>

                    <!-- Tabla de servicios -->
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5><i class="fas fa-list"></i> Servicios Programados</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Nombre del Servicio</th>
                                            <th>Orden del Día</th>
                                            <th>Jefe de Servicio</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($servicio = $servicios->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($servicio['fecha_servicio'])); ?></td>
                                            <td><?php echo $servicio['nombre']; ?></td>
                                            <td><?php echo $servicio['orden_del_dia']; ?></td>
                                            <td>
                                                <?php if ($servicio['jefe_nombre']): ?>
                                                    <?php echo $servicio['jefe_grado'] . ' ' . $servicio['jefe_apellido'] . ', ' . $servicio['jefe_nombre']; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin asignar</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $estado_class = [
                                                    'PROGRAMADO' => 'primary',
                                                    'EN_CURSO' => 'warning',
                                                    'COMPLETADO' => 'success',
                                                    'CANCELADO' => 'danger'
                                                ];
                                                ?>
                                                <span class="badge bg-<?php echo $estado_class[$servicio['estado']]; ?>">
                                                    <?php echo $servicio['estado']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" title="Ver Detalles">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" title="Asignar Personal">
                                                    <i class="fas fa-users"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo Servicio -->
    <div class="modal fade" id="modalNuevoServicio" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-calendar-plus"></i> Programar Nuevo Servicio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="crear">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Nombre del Servicio *</label>
                                <input type="text" class="form-control" name="nombre" required placeholder="Ej: Servicio de Seguridad Plaza de Armas">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha del Servicio *</label>
                                <input type="date" class="form-control" name="fecha_servicio" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Orden del Día</label>
                                <input type="text" class="form-control" name="orden_del_dia" placeholder="Ej: 322/2025">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Jefe de Servicio</label>
                                <select class="form-select" name="jefe_servicio_id">
                                    <option value="">Seleccionar jefe de servicio...</option>
                                    <?php 
                                    $policias_jefe->data_seek(0);
                                    while ($policia = $policias_jefe->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $policia['id']; ?>">
                                        <?php echo $policia['grado'] . ' ' . $policia['apellido'] . ', ' . $policia['nombre']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Descripción</label>
                                <textarea class="form-control" name="descripcion" rows="3" placeholder="Descripción detallada del servicio..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Programar Servicio</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>