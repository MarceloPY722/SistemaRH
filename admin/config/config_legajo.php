<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

$mensaje = '';

// Procesar acciones
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] == 'cambiar_legajo') {
        $policia_id = $_POST['policia_id'];
        $nuevo_legajo = $_POST['nuevo_legajo'];
        
        // Verificar que el nuevo legajo no esté en uso
        $check_legajo = $conn->prepare("SELECT id FROM policias WHERE legajo = ? AND id != ?");
        $check_legajo->bind_param("ii", $nuevo_legajo, $policia_id);
        $check_legajo->execute();
        $result_check = $check_legajo->get_result();
        
        if ($result_check->num_rows > 0) {
            $mensaje = "<div class='alert alert-danger'>Error: El legajo $nuevo_legajo ya está en uso por otro policía.</div>";
        } else {
            // Actualizar el legajo
            $update_stmt = $conn->prepare("UPDATE policias SET legajo = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $nuevo_legajo, $policia_id);
            
            if ($update_stmt->execute()) {
                $mensaje = "<div class='alert alert-success'>Legajo actualizado exitosamente.</div>";
            } else {
                $mensaje = "<div class='alert alert-danger'>Error al actualizar el legajo: " . $conn->error . "</div>";
            }
        }
    } elseif ($_POST['action'] == 'intercambiar_legajos') {
        // Intercambiar legajos entre dos policías
        $policia1_id = $_POST['policia1_id'];
        $policia2_id = $_POST['policia2_id'];
        
        if ($policia1_id == $policia2_id) {
            $mensaje = "<div class='alert alert-danger'>Error: No se puede intercambiar el legajo de un policía consigo mismo.</div>";
        } else {
            // Obtener los legajos actuales
            $get_legajos = $conn->prepare("SELECT id, legajo FROM policias WHERE id IN (?, ?)");
            $get_legajos->bind_param("ii", $policia1_id, $policia2_id);
            $get_legajos->execute();
            $result_legajos = $get_legajos->get_result();
            
            if ($result_legajos->num_rows == 2) {
                $legajos = [];
                while ($row = $result_legajos->fetch_assoc()) {
                    $legajos[$row['id']] = $row['legajo'];
                }
                
                $conn->begin_transaction();
                
                try {
                    $temp_legajo_query = $conn->prepare("SELECT MAX(legajo) + 1000 as temp_legajo FROM policias");
                    $temp_legajo_query->execute();
                    $temp_result = $temp_legajo_query->get_result();
                    $temp_legajo = $temp_result->fetch_assoc()['temp_legajo'];
                    
                    $update_temp = $conn->prepare("UPDATE policias SET legajo = ? WHERE id = ?");
                    $update_temp->bind_param("ii", $temp_legajo, $policia1_id);
                    $update_temp->execute();
                    
                    $update1 = $conn->prepare("UPDATE policias SET legajo = ? WHERE id = ?");
                    $update1->bind_param("ii", $legajos[$policia1_id], $policia2_id);
                    $update1->execute();
                    
                    $update2 = $conn->prepare("UPDATE policias SET legajo = ? WHERE id = ?");
                    $update2->bind_param("ii", $legajos[$policia2_id], $policia1_id);
                    $update2->execute();
                    
                    $conn->commit();
                    $mensaje = "<div class='alert alert-success'>Legajos intercambiados exitosamente. El policía 1 ahora tiene el legajo {$legajos[$policia2_id]} y el policía 2 tiene el legajo {$legajos[$policia1_id]}.</div>";
                } catch (Exception $e) {
                    $conn->rollback();
                    $mensaje = "<div class='alert alert-danger'>Error al intercambiar legajos: " . $e->getMessage() . "</div>";
                }
            } else {
                $mensaje = "<div class='alert alert-danger'>Error: No se pudieron encontrar los policías seleccionados.</div>";
            }
        }
    }
}

// Obtener lista de policías
$policias_sql = "SELECT p.id, p.legajo, p.nombre, p.apellido, g.nombre as grado 
                 FROM policias p 
                 LEFT JOIN grados g ON p.grado_id = g.id 
                 ORDER BY p.legajo ASC";
$policias_result = $conn->query($policias_sql);

// Obtener estadísticas
$stats_sql = "SELECT 
                COUNT(*) as total_policias,
                MIN(legajo) as legajo_min,
                MAX(legajo) as legajo_max,
                COUNT(DISTINCT legajo) as legajos_unicos
              FROM policias";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

$gaps_sql = "WITH RECURSIVE secuencia AS (
    SELECT 1 as numero
    UNION ALL
    SELECT numero + 1
    FROM secuencia
    WHERE numero < (SELECT MAX(legajo) FROM policias)
)
SELECT 
    s.numero as legajo_faltante,
    CASE 
        WHEN LAG(s.numero) OVER (ORDER BY s.numero) = s.numero - 1 
             AND LEAD(s.numero) OVER (ORDER BY s.numero) = s.numero + 1 
        THEN 'individual'
        WHEN LAG(s.numero) OVER (ORDER BY s.numero) != s.numero - 1 
             OR LAG(s.numero) OVER (ORDER BY s.numero) IS NULL
        THEN 'inicio_rango'
        WHEN LEAD(s.numero) OVER (ORDER BY s.numero) != s.numero + 1 
             OR LEAD(s.numero) OVER (ORDER BY s.numero) IS NULL
        THEN 'fin_rango'
        ELSE 'medio_rango'
    END as tipo_gap
FROM secuencia s
LEFT JOIN policias p ON s.numero = p.legajo
WHERE p.legajo IS NULL
ORDER BY s.numero";
$gaps_result = $conn->query($gaps_sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Legajos - Sistema RH</title>
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
                            <h2><i class="fas fa-id-badge"></i> Configuración de Legajos</h2>
                            <hr>
                            <?php echo $mensaje; ?>
                            
                            <!-- Estadísticas -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body">
                                            <h5><i class="fas fa-users"></i> Total Policías</h5>
                                            <h3><?php echo $stats['total_policias']; ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-info text-white">
                                        <div class="card-body">
                                            <h5><i class="fas fa-sort-numeric-down"></i> Rango Legajos</h5>
                                            <h3><?php echo $stats['legajo_min'] . ' - ' . $stats['legajo_max']; ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success text-white">
                                        <div class="card-body">
                                            <h5><i class="fas fa-check-circle"></i> Legajos Únicos</h5>
                                            <h3><?php echo $stats['legajos_unicos']; ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-warning text-dark">
                                        <div class="card-body">
                                            <h5><i class="fas fa-exclamation-triangle"></i> Próximo Legajo</h5>
                                            <h3><?php echo ($stats['legajo_max'] + 1); ?></h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Acciones de configuración -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card border-warning">
                                        <div class="card-header bg-warning text-dark">
                                            <h5><i class="fas fa-exchange-alt"></i> Intercambiar Legajos</h5>
                                        </div>
                                        <div class="card-body">
                                            <p>Intercambia los legajos entre dos policías seleccionados.</p>
                                            <p class="text-muted"><small><i class="fas fa-info-circle"></i> El policía 1 obtendrá el legajo del policía 2 y viceversa.</small></p>
                                            <form method="POST" onsubmit="return confirm('¿Está seguro de intercambiar los legajos de estos dos policías?')">
                                                <input type="hidden" name="action" value="intercambiar_legajos">
                                                <div class="mb-3">
                                                    <label class="form-label">Policía 1:</label>
                                                    <select name="policia1_id" class="form-select" required>
                                                        <option value="">Seleccione el primer policía...</option>
                                                        <?php 
                                                        $policias_result->data_seek(0);
                                                        while ($policia = $policias_result->fetch_assoc()): 
                                                        ?>
                                                            <option value="<?php echo $policia['id']; ?>">
                                                                Legajo <?php echo $policia['legajo']; ?> - <?php echo $policia['apellido'] . ', ' . $policia['nombre']; ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Policía 2:</label>
                                                    <select name="policia2_id" class="form-select" required>
                                                        <option value="">Seleccione el segundo policía...</option>
                                                        <?php 
                                                        $policias_result->data_seek(0);
                                                        while ($policia = $policias_result->fetch_assoc()): 
                                                        ?>
                                                            <option value="<?php echo $policia['id']; ?>">
                                                                Legajo <?php echo $policia['legajo']; ?> - <?php echo $policia['apellido'] . ', ' . $policia['nombre']; ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                                <button type="submit" class="btn btn-warning">
                                                    <i class="fas fa-exchange-alt"></i> Intercambiar Legajos
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card border-info">
                                        <div class="card-header bg-info text-white">
                                            <h5><i class="fas fa-edit"></i> Cambiar Legajo Individual</h5>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST" onsubmit="return confirm('¿Está seguro de cambiar el legajo de este policía?')">
                                                <input type="hidden" name="action" value="cambiar_legajo">
                                                <div class="mb-3">
                                                    <label class="form-label">Seleccionar Policía:</label>
                                                    <select name="policia_id" class="form-select" required>
                                                        <option value="">Seleccione un policía...</option>
                                                        <?php 
                                                        $policias_result->data_seek(0);
                                                        while ($policia = $policias_result->fetch_assoc()): 
                                                        ?>
                                                            <option value="<?php echo $policia['id']; ?>">
                                                                Legajo <?php echo $policia['legajo']; ?> - <?php echo $policia['apellido'] . ', ' . $policia['nombre']; ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Nuevo Legajo:</label>
                                                    <input type="number" name="nuevo_legajo" class="form-control" min="1" required>
                                                </div>
                                                <button type="submit" class="btn btn-info">
                                                    <i class="fas fa-save"></i> Cambiar Legajo
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Legajos faltantes en la secuencia -->
                            <?php if ($gaps_result && $gaps_result->num_rows > 0): ?>
                            <?php 
                                // Procesar los gaps para agrupar rangos
                                $gaps_result->data_seek(0);
                                $legajos_individuales = [];
                                $rangos = [];
                                $rango_actual = null;
                                
                                while ($gap = $gaps_result->fetch_assoc()) {
                                    if ($gap['tipo_gap'] == 'individual') {
                                        $legajos_individuales[] = $gap['legajo_faltante'];
                                    } elseif ($gap['tipo_gap'] == 'inicio_rango') {
                                        $rango_actual = ['inicio' => $gap['legajo_faltante']];
                                    } elseif ($gap['tipo_gap'] == 'fin_rango' && $rango_actual) {
                                        $rango_actual['fin'] = $gap['legajo_faltante'];
                                        $rangos[] = $rango_actual;
                                        $rango_actual = null;
                                    }
                                }
                            ?>
                            <div class="alert alert-warning">
                                <h5><i class="fas fa-exclamation-triangle"></i> Legajos Faltantes en la Secuencia</h5>
                                <p>Se encontraron los siguientes legajos disponibles en la numeración:</p>
                                
                                <?php if (!empty($legajos_individuales)): ?>
                                <div class="mb-3">
                                    <strong><i class="fas fa-circle"></i> Legajos individuales disponibles:</strong>
                                    <div class="mt-2">
                                        <?php foreach ($legajos_individuales as $legajo): ?>
                                            <span class="badge bg-success me-1 mb-1"><?php echo $legajo; ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($rangos)): ?>
                                <div class="mb-3">
                                    <strong><i class="fas fa-arrows-alt-h"></i> Rangos de legajos disponibles:</strong>
                                    <ul class="mt-2">
                                        <?php foreach ($rangos as $rango): ?>
                                            <li>Del <span class="badge bg-info"><?php echo $rango['inicio']; ?></span> al <span class="badge bg-info"><?php echo $rango['fin']; ?></span></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                                
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Estos legajos están disponibles para asignar a nuevos policías o para intercambios.
                                </small>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Lista de policías -->
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-list"></i> Lista de Policías y sus Legajos</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($policias_result && $policias_result->num_rows > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>Legajo</th>
                                                        <th>Apellido</th>
                                                        <th>Nombre</th>
                                                        <th>Grado</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $policias_result->data_seek(0);
                                                    while ($policia = $policias_result->fetch_assoc()): 
                                                    ?>
                                                        <tr>
                                                            <td><span class="badge bg-primary"><?php echo $policia['legajo']; ?></span></td>
                                                            <td><?php echo $policia['apellido']; ?></td>
                                                            <td><?php echo $policia['nombre']; ?></td>
                                                            <td><?php echo $policia['grado'] ?? 'Sin grado'; ?></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-info" 
                                                                        onclick="cambiarLegajo(<?php echo $policia['id']; ?>, '<?php echo $policia['apellido'] . ', ' . $policia['nombre']; ?>', <?php echo $policia['legajo']; ?>)">
                                                                    <i class="fas fa-edit"></i> Cambiar
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> No hay policías registrados en el sistema.
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
    
    <!-- Modal para cambio rápido de legajo -->
    <div class="modal fade" id="cambiarLegajoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Cambiar Legajo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formCambiarLegajo">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="cambiar_legajo">
                        <input type="hidden" name="policia_id" id="modalPoliciaId">
                        
                        <div class="mb-3">
                            <label class="form-label">Policía:</label>
                            <input type="text" class="form-control" id="modalPoliciaNombre" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Legajo Actual:</label>
                            <input type="text" class="form-control" id="modalLegajoActual" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nuevo Legajo:</label>
                            <input type="number" name="nuevo_legajo" class="form-control" min="1" required id="modalNuevoLegajo">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Cambiar Legajo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function cambiarLegajo(policiaId, policiaNombre, legajoActual) {
            document.getElementById('modalPoliciaId').value = policiaId;
            document.getElementById('modalPoliciaNombre').value = policiaNombre;
            document.getElementById('modalLegajoActual').value = legajoActual;
            document.getElementById('modalNuevoLegajo').value = '';
            
            var modal = new bootstrap.Modal(document.getElementById('cambiarLegajoModal'));
            modal.show();
        }
        
        document.getElementById('formCambiarLegajo').addEventListener('submit', function(e) {
            if (!confirm('¿Está seguro de cambiar el legajo de este policía?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>