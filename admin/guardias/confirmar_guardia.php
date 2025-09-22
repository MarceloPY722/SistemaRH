<?php
session_start();
require_once '../../cnx/db_connect.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Verificar permisos de administrador
if ($_SESSION['rol'] !== 'ADMIN' && $_SESSION['rol'] !== 'SUPERADMIN') {
    header('Location: ../../index.php');
    exit();
}

// Verificar si hay asignaciones generadas
if (!isset($_SESSION['asignaciones_generadas'])) {
    header('Location: generar_guardia_interface.php');
    exit();
}

$asignaciones_data = $_SESSION['asignaciones_generadas'];
$fecha_guardia = $asignaciones_data['fecha_guardia'];
$orden_dia = $asignaciones_data['orden_dia'];
$zona = $asignaciones_data['zona'];
$asignaciones = $asignaciones_data['asignaciones'];

$mensaje = '';
$error = '';

// Procesar confirmación y guardar en base de datos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        // Guardar orden del día si no existe
        $query_orden = "INSERT IGNORE INTO orden_dia (numero_orden, año, numero, fecha_creacion, activo) VALUES (?, ?, ?, NOW(), 1)";
        $stmt_orden = $conn->prepare($query_orden);
        
        // Extraer año y número del orden del día (formato esperado: "AÑO-NÚMERO")
        $partes_orden = explode('-', $orden_dia);
        $año_orden = $partes_orden[0] ?? date('Y');
        $numero_orden = $partes_orden[1] ?? 1;
        
        $stmt_orden->execute([$orden_dia, $año_orden, $numero_orden]);
        
        // Obtener ID del orden del día
        $orden_dia_id = $conn->lastInsertId();
        if ($orden_dia_id == 0) {
            $query_get_orden = "SELECT id FROM orden_dia WHERE numero_orden = ?";
            $stmt_get_orden = $conn->prepare($query_get_orden);
            $stmt_get_orden->execute([$orden_dia]);
            $orden_dia_row = $stmt_get_orden->fetch(PDO::FETCH_ASSOC);
            $orden_dia_id = $orden_dia_row['id'];
        }
        
        // Determinar la región según el día de la semana
        $dia_semana = date('w', strtotime($fecha_guardia)); // 0=domingo, 1=lunes, etc.
        $region = ($dia_semana == 0) ? 'REGIONAL' : 'CENTRAL'; // Domingo = REGIONAL, otros días = CENTRAL
        
        // 1. Crear registro en guardias_generadas
        $query_guardia = "INSERT INTO guardias_generadas (fecha_guardia, orden_dia, region, estado) VALUES (?, ?, ?, 'PROGRAMADA')";
        $stmt_guardia = $conn->prepare($query_guardia);
        $stmt_guardia->execute([$fecha_guardia, $orden_dia, $region]);
        $guardia_generada_id = $conn->lastInsertId();
        
        // 2. Guardar asignaciones en guardias_generadas_detalle
        $posicion_asignacion = 1;
        foreach ($asignaciones as $asignacion) {
            // Obtener la posición original del policía en lista_guardias
            $query_pos_original = "SELECT posicion FROM lista_guardias WHERE policia_id = ?";
            $stmt_pos_original = $conn->prepare($query_pos_original);
            $stmt_pos_original->execute([$asignacion['policia_id']]);
            $posicion_original = $stmt_pos_original->fetch(PDO::FETCH_ASSOC)['posicion'];
            
            // Determinar las observaciones según el lugar de guardia
            $observaciones = '';
            switch($asignacion['lugar_guardia_id']) {
                case 1: $observaciones = 'JEFE_SERVICIO'; break;
                case 2: $observaciones = 'JEFE_CUARTEL'; break;
                case 3: $observaciones = 'OFICIAL_GUARDIA'; break;
                case 4: $observaciones = 'ATENCION_TELEFONICA_EXCLUSIVA'; break;
                case 5: $observaciones = 'NUMERO_GUARDIA_' . $posicion_asignacion; break;
                case 6: $observaciones = 'CONDUCTOR_GUARDIA'; break;
                case 7: $observaciones = 'GUARDIA_06_30_22_00'; break;
                case 8: $observaciones = 'TENIDA_REGLAMENTO'; break;
                case 9: $observaciones = 'SANIDAD_GUARDIA_' . ($posicion_asignacion - 8); break;
                default: $observaciones = 'GUARDIA_GENERAL'; break;
            }
            
            $query_detalle = "INSERT INTO guardias_generadas_detalle (guardia_generada_id, policia_id, lugar_guardia_id, posicion_asignacion, posicion_lista_original, es_retorno_ausencia, observaciones_asignacion) VALUES (?, ?, ?, ?, ?, 0, ?)";
            $stmt_detalle = $conn->prepare($query_detalle);
            $stmt_detalle->execute([
                $guardia_generada_id,
                $asignacion['policia_id'],
                $asignacion['lugar_guardia_id'],
                $posicion_asignacion,
                $posicion_original,
                $observaciones
            ]);
            
            $posicion_asignacion++;
        }
        
        // 3. Actualizar posiciones FIFO en lista_guardias
        foreach ($asignaciones as $asignacion) {
            // Obtener la máxima posición actual
            $query_max_pos = "SELECT MAX(posicion) as max_pos FROM lista_guardias";
            $stmt_max_pos = $conn->prepare($query_max_pos);
            $stmt_max_pos->execute();
            $max_pos = $stmt_max_pos->fetch(PDO::FETCH_ASSOC)['max_pos'];
            
            // Mover el policía al final de la lista y actualizar fecha de última guardia
            $query_update_pos = "UPDATE lista_guardias SET posicion = ?, ultima_guardia_fecha = ? WHERE policia_id = ?";
            $stmt_update_pos = $conn->prepare($query_update_pos);
            $stmt_update_pos->execute([$max_pos + 1, $fecha_guardia, $asignacion['policia_id']]);
        }
        
        // 4. Reordenar las posiciones para que sean secuenciales
        $query_reorder = "SET @pos := 0;";
        $conn->exec($query_reorder);
        
        $query_reorder_main = "UPDATE lista_guardias SET posicion = (@pos := @pos + 1) ORDER BY posicion ASC";
        $conn->exec($query_reorder_main);
        
        $conn->commit();
        
        // 5. Limpiar sesión y redirigir al PDF
        unset($_SESSION['asignaciones_generadas']);
        $_SESSION['mensaje_exito'] = 'Guardias generadas exitosamente';
        
        // Redirigir directamente al PDF
        header('Location: ver_guardias.php?fecha=' . $fecha_guardia . '&pdf=1');
        exit();
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $error = 'Error al guardar las asignaciones: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Guardias - Sistema RH Policía</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/styles.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../inc/sidebar.php'; ?>
            
            <main class="col-md-9 col-lg-10 px-4 py-4">
        <div class="row">
            <div class="col-md-10 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Confirmar Generación de Guardias</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info mb-4">
                            <h5>Resumen de la Generación</h5>
                            <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($fecha_guardia)); ?></p>
                            <p><strong>Orden del Día:</strong> <?php echo htmlspecialchars($orden_dia); ?></p>
                            <p><strong>Zona:</strong> <?php echo htmlspecialchars($zona); ?></p>
                            <p><strong>Total de Asignaciones:</strong> <?php echo count($asignaciones); ?> policías</p>
                        </div>
                        
                        <h5>Detalle de Asignaciones</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Legajo</th>
                                        <th>Nombre</th>
                                        <th>Apellido</th>
                                        <th>Lugar de Guardia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($asignaciones as $asignacion): ?>
                                         <tr>
                                             <td><?php echo htmlspecialchars($asignacion['legajo']); ?></td>
                                             <td><?php echo htmlspecialchars($asignacion['nombre']); ?></td>
                                             <td><?php echo htmlspecialchars($asignacion['apellido']); ?></td>
                                             <td><?php echo htmlspecialchars($asignacion['nombre_lugar'] ?? 'Lugar ID ' . $asignacion['lugar_guardia_id']); ?></td>
                                         </tr>
                                     <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <form method="POST">
                            <div class="alert alert-warning">
                                <strong>⚠️ Advertencia:</strong> Al confirmar, los policías asignados serán movidos al final de sus respectivas listas FIFO y las asignaciones quedarán guardadas en el sistema.
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success">Confirmar y Guardar</button>
                                <a href="generar_guardia_interface.php" class="btn btn-secondary">Cancelar y Volver</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>