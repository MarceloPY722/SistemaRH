<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../cnx/db_connect.php';

// Verificar que se proporcione un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['mensaje'] = 'ID de ausencia no válido';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: index.php');
    exit();
}

$ausencia_id = $_GET['id'];

// Obtener datos de la ausencia
$sql_ausencia = "SELECT a.*, p.nombre, p.apellido, p.cin, p.legajo, g.nombre as grado, ta.nombre as tipo_ausencia
                 FROM ausencias a
                 JOIN policias p ON a.policia_id = p.id
                 LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
                 LEFT JOIN grados g ON tg.grado_id = g.id
                 JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id
                 WHERE a.id = ?";
$stmt_ausencia = $conn->prepare($sql_ausencia);
$stmt_ausencia->execute([$ausencia_id]);
$ausencia = $stmt_ausencia->fetch(PDO::FETCH_ASSOC);

if (!$ausencia) {
    $_SESSION['mensaje'] = 'Ausencia no encontrada';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: index.php');
    exit();
}

// Obtener tipos de ausencias
$sql_tipos = "SELECT * FROM tipos_ausencias ORDER BY nombre";
$result_tipos = $conn->query($sql_tipos);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipo_ausencia_id = $_POST['tipo_ausencia_id'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
    $descripcion = $_POST['descripcion'];
    $justificacion = $_POST['justificacion'];
    
    try {
        $conn->beginTransaction();
        
        $sql_update = "UPDATE ausencias SET 
                       tipo_ausencia_id = ?, 
                       fecha_inicio = ?, 
                       fecha_fin = ?, 
                       descripcion = ?, 
                       justificacion = ?,
                       updated_at = NOW()
                       WHERE id = ?";
        
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->execute([
            $tipo_ausencia_id,
            $fecha_inicio,
            $fecha_fin,
            $descripcion,
            $justificacion,
            $ausencia_id
        ]);
        
        $conn->commit();
        
        $_SESSION['mensaje'] = 'Ausencia actualizada exitosamente';
        $_SESSION['tipo_mensaje'] = 'success';
        header('Location: index.php');
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['mensaje'] = 'Error al actualizar ausencia: ' . $e->getMessage();
        $_SESSION['tipo_mensaje'] = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Ausencia - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../inc/sidebar.php'; ?>
            
            <!-- Contenido Principal -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Mensajes de alerta -->
                    <?php if (isset($_SESSION['mensaje'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['tipo_mensaje']; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle me-2"></i><?php echo $_SESSION['mensaje']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php 
                    unset($_SESSION['mensaje']);
                    unset($_SESSION['tipo_mensaje']);
                    endif; ?>

                    <!-- Header -->
                    <div class="page-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1><i class="fas fa-edit me-2"></i>Editar Ausencia</h1>
                                <p>Modifica los datos de la ausencia</p>
                            </div>
                            <div>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>Volver
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Información del Policía -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Información del Policía</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($ausencia['apellido'] . ', ' . $ausencia['nombre']); ?></p>
                                    <p><strong>CI:</strong> <?php echo htmlspecialchars($ausencia['cin']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Legajo:</strong> <?php echo htmlspecialchars($ausencia['legajo']); ?></p>
                                    <p><strong>Grado:</strong> <?php echo htmlspecialchars($ausencia['grado']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario de Edición -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Datos de la Ausencia</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="tipo_ausencia_id" class="form-label">Tipo de Ausencia *</label>
                                        <select class="form-select" id="tipo_ausencia_id" name="tipo_ausencia_id" required>
                                            <option value="">Seleccionar tipo...</option>
                                            <?php while ($tipo = $result_tipos->fetch(PDO::FETCH_ASSOC)): ?>
                                            <option value="<?php echo $tipo['id']; ?>" <?php echo ($tipo['id'] == $ausencia['tipo_ausencia_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($tipo['nombre']); ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <label for="fecha_inicio" class="form-label">Fecha de Inicio *</label>
                                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                                               value="<?php echo $ausencia['fecha_inicio']; ?>" required>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <label for="fecha_fin" class="form-label">Fecha de Fin</label>
                                        <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                                               value="<?php echo $ausencia['fecha_fin']; ?>">
                                        <small class="form-text text-muted">Opcional para ausencias de un solo día</small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="descripcion" class="form-label">Descripción *</label>
                                    <textarea class="form-control" id="descripcion" name="descripcion" rows="3" required><?php echo htmlspecialchars($ausencia['descripcion'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="justificacion" class="form-label">Justificación</label>
                                    <textarea class="form-control" id="justificacion" name="justificacion" rows="3"><?php echo htmlspecialchars($ausencia['justificacion'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <a href="index.php" class="btn btn-secondary me-2">
                                        <i class="fas fa-times me-1"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Guardar Cambios
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>