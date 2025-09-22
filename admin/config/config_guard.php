<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

// Verificar rol del usuario
$stmt = $conn->prepare("SELECT rol FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario_actual = $stmt->fetch();
$es_superadmin = ($usuario_actual['rol'] === 'SUPERADMIN');

$mensaje = '';

// Procesar eliminación de guardias
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] == 'eliminar_todas') {
        // Eliminar guardias semanales
        $sql1 = "DELETE FROM guardias_semanales";
        // Eliminar guardias realizadas
        $sql2 = "DELETE FROM guardias_realizadas";
        
        $success1 = $conn->query($sql1);
        $success2 = $conn->query($sql2);
        
        if ($success1 && $success2) {
            $mensaje = "<div class='alert alert-success'>Todas las guardias semanales y realizadas han sido eliminadas exitosamente.</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al eliminar las guardias.</div>";
        }
    } elseif ($_POST['action'] == 'eliminar_guardias_realizadas') {
        // Nueva opción: eliminar solo guardias realizadas
        $sql = "DELETE FROM guardias_realizadas";
        $stmt = $conn->prepare($sql);
        if ($stmt->execute()) {
            $affected_rows = $stmt->rowCount();
            $mensaje = "<div class='alert alert-success'>Se eliminaron $affected_rows guardias realizadas exitosamente.</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al eliminar las guardias realizadas.</div>";
        }
    } elseif ($_POST['action'] == 'eliminar_por_fecha') {
        $fecha_inicio = $_POST['fecha_inicio'];
        $fecha_fin = $_POST['fecha_fin'];
        
        $sql = "DELETE FROM guardias_semanales 
                WHERE fecha_inicio BETWEEN ? AND ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([$fecha_inicio, $fecha_fin])) {
            $affected_rows = $stmt->rowCount();
            $mensaje = "<div class='alert alert-success'>Se eliminaron $affected_rows guardias semanales del período seleccionado.</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al eliminar las guardias.</div>";
        }
    } elseif ($_POST['action'] == 'eliminar_por_id') {
        $guardia_id = $_POST['guardia_id'];
        
        $sql = "DELETE FROM guardias_semanales WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([$guardia_id])) {
            if ($stmt->rowCount() > 0) {
                $mensaje = "<div class='alert alert-success'>Guardia eliminada exitosamente.</div>";
            } else {
                $mensaje = "<div class='alert alert-warning'>No se encontró la guardia especificada.</div>";
            }
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al eliminar la guardia.</div>";
        }
    } elseif ($_POST['action'] == 'resetear_guardias') {
        // Nueva funcionalidad: Resetear guardias (reorganizar lista)
        try {
            // Comenzar transacción
            $conn->beginTransaction();
            
            // Limpiar lista actual
            $conn->exec("DELETE FROM lista_guardias");
            
            // Obtener policías ordenados por jerarquía y legajo (como proxy de antigüedad)
            $policias_sql = "SELECT p.id
                            FROM policias p
                            JOIN grados g ON p.grado_id = g.id
                            WHERE p.activo = TRUE
                            ORDER BY g.nivel_jerarquia ASC, p.legajo ASC, p.id ASC";
            
            // Nota: Se usa legajo como proxy de antigüedad (legajo menor = más antiguo)
            
            $result = $conn->query($policias_sql);
            $posicion = 1;
            
            // Insertar policías en nueva lista ordenada
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $stmt = $conn->prepare("INSERT INTO lista_guardias (policia_id, posicion) VALUES (?, ?)");
                $stmt->execute([$row['id'], $posicion]);
                $posicion++;
            }
            
            // Confirmar transacción
            $conn->commit();
            
            $mensaje = "<div class='alert alert-success'>Lista de guardias reorganizada exitosamente. Las posiciones han sido restablecidas.</div>";
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $conn->rollBack();
            $mensaje = "<div class='alert alert-danger'>Error al reorganizar la lista de guardias: " . $e->getMessage() . "</div>";
        }
    }
}

$guardias_sql = "SELECT id, fecha_inicio, fecha_fin, usuario_id, created_at 
                 FROM guardias_semanales 
                 ORDER BY fecha_inicio DESC";
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
                                <?php if ($es_superadmin): ?>
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
                                <?php endif; ?>
                                
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
                                            <h5><i class="fas fa-broom"></i> Limpiar Guardias Realizadas</h5>
                                        </div>
                                        <div class="card-body">
                                            <p>Elimina todas las guardias realizadas.</p>
                                            <form method="POST" onsubmit="return confirm('¿Está seguro de eliminar TODAS las guardias realizadas? Esta acción no se puede deshacer.')">
                                                <input type="hidden" name="action" value="eliminar_guardias_realizadas">
                                                <button type="submit" class="btn btn-warning">
                                                    <i class="fas fa-broom"></i> Limpiar Guardias Realizadas
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
                                
                        
                            
                            <!-- Lista de guardias generadas -->
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-list"></i> Guardias Semanales Generadas</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($guardias_result && $guardias_result->rowCount() > 0): ?>
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
                                                    <tbody>
                                                        <?php while ($guardia = $guardias_result->fetch()): ?>
                                                            <tr>
                                                                <td><?php echo $guardia['id']; ?></td>
                                                                <td><?php echo date('d/m/Y', strtotime($guardia['fecha_inicio'])); ?></td>
                                                                <td><?php echo date('d/m/Y', strtotime($guardia['fecha_fin'])); ?></td>
                                                                <td><span class="badge bg-primary">SEMANAL</span></td>
                                                                <td><?php echo date('d/m/Y H:i', strtotime($guardia['created_at'])); ?></td>
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