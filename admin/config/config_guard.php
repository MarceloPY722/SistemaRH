<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

$mensaje = '';

// Procesar eliminación de guardias
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] == 'eliminar_todas') {
        $sql = "DELETE FROM guardias_semanales_generadas";
        if ($conn->query($sql)) {
            $mensaje = "<div class='alert alert-success'>Todas las guardias semanales generadas han sido eliminadas exitosamente.</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al eliminar las guardias: " . $conn->error . "</div>";
        }
    } elseif ($_POST['action'] == 'eliminar_por_fecha') {
        $fecha_inicio = $_POST['fecha_inicio'];
        $fecha_fin = $_POST['fecha_fin'];
        
        $sql = "DELETE FROM guardias_semanales_generadas 
                WHERE fecha_inicio_semana BETWEEN ? AND ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
        
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            $mensaje = "<div class='alert alert-success'>Se eliminaron $affected_rows guardias semanales del período seleccionado.</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al eliminar las guardias: " . $conn->error . "</div>";
        }
    } elseif ($_POST['action'] == 'eliminar_por_id') {
        $guardia_id = $_POST['guardia_id'];
        
        $sql = "DELETE FROM guardias_semanales_generadas WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $guardia_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $mensaje = "<div class='alert alert-success'>Guardia eliminada exitosamente.</div>";
            } else {
                $mensaje = "<div class='alert alert-warning'>No se encontró la guardia especificada.</div>";
            }
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al eliminar la guardia: " . $conn->error . "</div>";
        }
    } elseif ($_POST['action'] == 'resetear_guardias') {
        // Nueva funcionalidad: Resetear guardias (reorganizar lista)
        if ($conn->query("CALL ReorganizarListaGuardias()")) {
            $mensaje = "<div class='alert alert-success'>Lista de guardias reorganizada exitosamente. Las posiciones han sido restablecidas.</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al reorganizar la lista de guardias: " . $conn->error . "</div>";
        }
    }
}

$guardias_sql = "SELECT id, fecha_inicio_semana, fecha_fin_semana, tipo_guardia, 
                        fecha_generacion, usuario_id 
                 FROM guardias_semanales_generadas 
                 ORDER BY fecha_inicio_semana DESC";
$guardias_result = $conn->query($guardias_sql);

$fecha_actual = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Guardias - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 bg-dark text-white p-0">
                <?php include '../inc/sidebar.php'; ?>
            </div>
            
            <div class="col-md-10">
                <div class="container mt-4">
                    <div class="row">
                        <div class="col-12">
                            <h2><i class="fas fa-cogs"></i> Configuración de Guardias</h2>
                            <hr>                            
                            <?php echo $mensaje; ?>
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card border-primary">
                                        <div class="card-header bg-primary text-white">
                                            <h5><i class="fas fa-sync-alt"></i> Resetear Guardias</h5>
                                        </div>
                                        <div class="card-body">
                                            <p>Reorganiza la lista de guardias restableciendo las posiciones originales.</p>
                                            <form method="POST" onsubmit="return confirm('¿Está seguro de resetear la lista de guardias? Esto restablecerá las posiciones originales.')">
                                                <input type="hidden" name="action" value="resetear_guardias">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-sync-alt"></i> Resetear Lista
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="card border-danger">
                                        <div class="card-header bg-danger text-white">
                                            <h5><i class="fas fa-exclamation-triangle"></i> Eliminar Todas</h5>
                                        </div>
                                        <div class="card-body">
                                            <p>Elimina TODAS las guardias semanales generadas.</p>
                                            <form method="POST" onsubmit="return confirm('¿Está seguro de eliminar TODAS las guardias generadas? Esta acción no se puede deshacer.')">
                                                <input type="hidden" name="action" value="eliminar_todas">
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="fas fa-trash"></i> Eliminar Todas
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="card border-warning">
                                        <div class="card-header bg-warning text-dark">
                                            <h5><i class="fas fa-calendar-alt"></i> Eliminar por Fechas</h5>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST" onsubmit="return confirm('¿Está seguro de eliminar las guardias del período seleccionado?')">
                                                <input type="hidden" name="action" value="eliminar_por_fecha">
                                                <div class="mb-3">
                                                    <label class="form-label">Fecha Inicio:</label>
                                                    <input type="date" name="fecha_inicio" class="form-control" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Fecha Fin:</label>
                                                    <input type="date" name="fecha_fin" class="form-control" required>
                                                </div>
                                                <button type="submit" class="btn btn-warning">
                                                    <i class="fas fa-calendar-times"></i> Eliminar por Fechas
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Información -->
                                <div class="col-md-3">
                                    <div class="card border-info">
                                        <div class="card-header bg-info text-white">
                                            <h5><i class="fas fa-info-circle"></i> Información</h5>
                                        </div>
                                        <div class="card-body">
                                            <p><strong>Resetear:</strong> Reorganiza las posiciones de la lista de guardias.</p>
                                            <p><strong>Eliminar:</strong> Borra registros de control para permitir regenerar.</p>
                                            <a href="../guardias/index.php" class="btn btn-info">
                                                <i class="fas fa-arrow-left"></i> Volver a Guardias
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Lista de guardias generadas -->
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-list"></i> Guardias Semanales Generadas</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($guardias_result && $guardias_result->num_rows > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Fecha Inicio</th>
                                                        <th>Fecha Fin</th>
                                                        <th>Tipo</th>
                                                        <th>Fecha Generación</th>
                                                        <th>Usuario ID</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while ($guardia = $guardias_result->fetch_assoc()): ?>
                                                        <tr>
                                                            <td><?php echo $guardia['id']; ?></td>
                                                            <td><?php echo date('d/m/Y', strtotime($guardia['fecha_inicio_semana'])); ?></td>
                                                            <td><?php echo date('d/m/Y', strtotime($guardia['fecha_fin_semana'])); ?></td>
                                                            <td>
                                                                <span class="badge <?php echo $guardia['tipo_guardia'] == 'SEMANAL' ? 'bg-primary' : 'bg-secondary'; ?>">
                                                                    <?php echo $guardia['tipo_guardia']; ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo date('d/m/Y H:i', strtotime($guardia['fecha_generacion'])); ?></td>
                                                            <td><?php echo $guardia['usuario_id']; ?></td>
                                                            <td>
                                                                <form method="POST" style="display: inline;" 
                                                                      onsubmit="return confirm('¿Está seguro de eliminar esta guardia?')">
                                                                    <input type="hidden" name="action" value="eliminar_por_id">
                                                                    <input type="hidden" name="guardia_id" value="<?php echo $guardia['id']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> No hay guardias semanales generadas en el sistema.
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>