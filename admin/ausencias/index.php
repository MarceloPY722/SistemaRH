<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

// Obtener tipos de ausencias y policías
$tipos_ausencias = $conn->query("SELECT * FROM tipos_ausencias ORDER BY nombre ASC");
$policias = $conn->query("
    SELECT p.id, p.nombre, p.apellido, g.nombre as grado
    FROM policias p
    JOIN grados g ON p.grado_id = g.id
    WHERE p.activo = 1
    ORDER BY p.apellido ASC
");

// Procesar formulario de nueva ausencia
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'crear') {
    $policia_id = $_POST['policia_id'];
    $tipo_ausencia_id = $_POST['tipo_ausencia_id'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'] ?: null;
    $descripcion = trim($_POST['descripcion']);
    $justificacion = trim($_POST['justificacion']);
    
    $sql = "INSERT INTO ausencias (policia_id, tipo_ausencia_id, fecha_inicio, fecha_fin, descripcion, justificacion) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iissss", $policia_id, $tipo_ausencia_id, $fecha_inicio, $fecha_fin, $descripcion, $justificacion);
    
    if ($stmt->execute()) {
        $mensaje = "<div class='alert alert-success'>Ausencia registrada exitosamente</div>";
    } else {
        $mensaje = "<div class='alert alert-danger'>Error al registrar ausencia: " . $conn->error . "</div>";
    }
}

// Procesar aprobación/rechazo de ausencias
if ($_POST && isset($_POST['action']) && in_array($_POST['action'], ['aprobar', 'rechazar'])) {
    $ausencia_id = $_POST['ausencia_id'];
    $estado = $_POST['action'] == 'aprobar' ? 'APROBADA' : 'RECHAZADA';
    $aprobado_por = $_SESSION['usuario_id'];
    
    $sql = "UPDATE ausencias SET estado = ?, aprobado_por = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $estado, $aprobado_por, $ausencia_id);
    
    if ($stmt->execute()) {
        $mensaje = "<div class='alert alert-success'>Ausencia " . strtolower($estado) . " exitosamente</div>";
    } else {
        $mensaje = "<div class='alert alert-danger'>Error al procesar ausencia: " . $conn->error . "</div>";
    }
}

// Obtener ausencias
$ausencias = $conn->query("
    SELECT a.*, p.nombre, p.apellido, p.cin, g.nombre as grado, ta.nombre as tipo_ausencia,
           ap.nombre as aprobado_nombre, ap.apellido as aprobado_apellido
    FROM ausencias a
    JOIN policias p ON a.policia_id = p.id
    JOIN grados g ON p.grado_id = g.id
    JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id
    LEFT JOIN policias ap ON a.aprobado_por = ap.id
    ORDER BY a.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ausencias - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(45deg, #2c3e50, #34495e) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            <?php include '../inc/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <h1 class="page-title">
                        <i class="fas fa-user-times"></i> Gestión de Ausencias
                    </h1>

                    <?php if (isset($mensaje)) echo $mensaje; ?>

                    <!-- Formulario para nueva ausencia -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5><i class="fas fa-plus-circle"></i> Registrar Nueva Ausencia</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="crear">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Policía *</label>
                                        <select class="form-select" name="policia_id" required>
                                            <option value="">Seleccionar policía...</option>
                                            <?php while ($policia = $policias->fetch_assoc()): ?>
                                            <option value="<?php echo $policia['id']; ?>">
                                                <?php echo $policia['grado'] . ' ' . $policia['apellido'] . ', ' . $policia['nombre']; ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tipo de Ausencia *</label>
                                        <select class="form-select" name="tipo_ausencia_id" required>
                                            <option value="">Seleccionar tipo...</option>
                                            <?php while ($tipo = $tipos_ausencias->fetch_assoc()): ?>
                                            <option value="<?php echo $tipo['id']; ?>">
                                                <?php echo $tipo['nombre']; ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Fecha de Inicio *</label>
                                        <input type="date" class="form-control" name="fecha_inicio" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Fecha de Fin</label>
                                        <input type="date" class="form-control" name="fecha_fin">
                                        <div class="form-text">Dejar vacío para ausencias indefinidas</div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Descripción *</label>
                                        <textarea class="form-control" name="descripcion" rows="3" required></textarea>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Justificación</label>
                                        <textarea class="form-control" name="justificacion" rows="3"></textarea>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Registrar Ausencia
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Lista de ausencias -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5><i class="fas fa-list"></i> Lista de Ausencias</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Policía</th>
                                            <th>CIN</th>
                                            <th>Grado</th>
                                            <th>Tipo</th>
                                            <th>Fecha Inicio</th>
                                            <th>Fecha Fin</th>
                                            <th>Estado</th>
                                            <th>Descripción</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($ausencias->num_rows > 0): ?>
                                        <?php while ($ausencia = $ausencias->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $ausencia['apellido'] . ', ' . $ausencia['nombre']; ?></td>
                                            <td><?php echo $ausencia['cin']; ?></td>
                                            <td><?php echo $ausencia['grado']; ?></td>
                                            <td><?php echo $ausencia['tipo_ausencia']; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($ausencia['fecha_inicio'])); ?></td>
                                            <td>
                                                <?php echo $ausencia['fecha_fin'] ? date('d/m/Y', strtotime($ausencia['fecha_fin'])) : 'Indefinida'; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $ausencia['estado'] == 'APROBADA' ? 'success' : 
                                                        ($ausencia['estado'] == 'RECHAZADA' ? 'danger' : 'warning'); 
                                                ?>">
                                                    <?php echo $ausencia['estado']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo substr($ausencia['descripcion'], 0, 50) . '...'; ?></td>
                                            <td>
                                                <?php if ($ausencia['estado'] == 'PENDIENTE'): ?>
                                                <div class="btn-group" role="group">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="aprobar">
                                                        <input type="hidden" name="ausencia_id" value="<?php echo $ausencia['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-success" 
                                                                onclick="return confirm('¿Aprobar esta ausencia?')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="rechazar">
                                                        <input type="hidden" name="ausencia_id" value="<?php echo $ausencia['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" 
                                                                onclick="return confirm('¿Rechazar esta ausencia?')">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                                <?php else: ?>
                                                <small class="text-muted">
                                                    <?php if ($ausencia['aprobado_nombre']): ?>
                                                        Por: <?php echo $ausencia['aprobado_apellido'] . ', ' . $ausencia['aprobado_nombre']; ?>
                                                    <?php endif; ?>
                                                </small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                        <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
                                                <i class="fas fa-info-circle"></i> No hay ausencias registradas
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
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