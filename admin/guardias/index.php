<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';
require_once '../../config/config_fecha_sistema.php';
require_once 'generar_pdf.php'; // Incluir el archivo de generación de PDF

// Procesar acciones
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'generar_guardia') {
        $lugar_id = $_POST['lugar_id'];
        
        // Obtener el próximo policía disponible según el día de la semana
        $stmt = $conn->prepare("CALL ObtenerProximoPoliciaDisponible(?, @policia_id, @policia_info)");
        $stmt->bind_param("i", $lugar_id);
        $stmt->execute();
        $stmt->close();
        
        // Obtener los resultados
        $result = $conn->query("SELECT @policia_id as policia_id, @policia_info as policia_info");
        $row = $result->fetch_assoc();
        
        if ($row['policia_id']) {
            $policia_info = json_decode($row['policia_info'], true);
            
            // Registrar la guardia realizada
            $stmt_guardia = $conn->prepare("
                INSERT INTO guardias_realizadas (policia_id, fecha_inicio, fecha_fin, lugar_guardia_id, observaciones) 
                VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR), ?, 'Guardia asignada automáticamente - Sistema FIFO')
            ");
            $stmt_guardia->bind_param("ii", $row['policia_id'], $lugar_id);
            $stmt_guardia->execute();
            
            // Corregir esta línea - cambiar $policia['policia_id'] por $row['policia_id']
            $stmt_rotar = $conn->prepare("CALL RotarGuardiaFIFO(?)");
            $stmt_rotar->bind_param("i", $row['policia_id']); // <- Cambio aquí
            $stmt_rotar->execute();
            
            $dia_semana = date('w'); 
            $region = $policia_info['region'];
            $periodo = ($region == 'CENTRAL') ? '15 días' : '30 días';
            $tipo_dia = ($dia_semana >= 1 && $dia_semana <= 4) ? 'día de semana (Central)' : 'fin de semana (Regional)';
            
            $mensaje = "<div class='alert alert-success'>
                <h5>Guardia Asignada Exitosamente</h5>
                <p><strong>Policía:</strong> {$policia_info['apellido']}, {$policia_info['nombre']}</p>
                <p><strong>Legajo:</strong> {$policia_info['legajo']}</p>
                <p><strong>Grado:</strong> {$policia_info['grado']}</p>
                <p><strong>Región:</strong> {$policia_info['region']}</p>
                <p><strong>Tipo de día:</strong> {$tipo_dia}</p>
                <p><strong>Próxima disponibilidad:</strong> {$periodo}</p>
            </div>";
        } else {
            $dia_semana = date('w');
            $region_requerida = ($dia_semana >= 1 && $dia_semana <= 4) ? 'CENTRAL' : 'REGIONAL';
            $mensaje = "<div class='alert alert-warning'>No hay policías de la región {$region_requerida} disponibles para este lugar de guardia en este momento</div>";
        }
    }
    
    // Nueva acción para reemplazar personal
    if ($action == 'reemplazar_personal') {
        $fecha_guardia = $_POST['fecha_guardia'];
        $lugar_id = $_POST['lugar_id'];
        $nuevo_policia_id = $_POST['nuevo_policia_id'];
        
        // Buscar la guardia existente para esa fecha y lugar
        $stmt_buscar = $conn->prepare("
            SELECT gr.id, gr.policia_id 
            FROM guardias_realizadas gr 
            WHERE DATE(gr.fecha_inicio) = ? AND gr.lugar_guardia_id = ?
        ");
        $stmt_buscar->bind_param("si", $fecha_guardia, $lugar_id);
        $stmt_buscar->execute();
        $guardia_existente = $stmt_buscar->get_result()->fetch_assoc();
        
        if ($guardia_existente) {
            // Liberar al policía anterior (resetear fecha_disponible)
            $stmt_liberar = $conn->prepare("
                UPDATE lista_guardias 
                SET fecha_disponible = NULL 
                WHERE policia_id = ?
            ");
            $stmt_liberar->bind_param("i", $guardia_existente['policia_id']);
            $stmt_liberar->execute();
            
            // Actualizar la guardia con el nuevo policía
            $stmt_actualizar = $conn->prepare("
                UPDATE guardias_realizadas 
                SET policia_id = ?, observaciones = CONCAT(observaciones, ' - Reemplazado el ', NOW()) 
                WHERE id = ?
            ");
            $stmt_actualizar->bind_param("ii", $nuevo_policia_id, $guardia_existente['id']);
            $stmt_actualizar->execute();
            
            // Rotar al nuevo policía al final de la lista
            $stmt_rotar_nuevo = $conn->prepare("CALL RotarGuardiaFIFO(?)");
            $stmt_rotar_nuevo->bind_param("i", $nuevo_policia_id);
            $stmt_rotar_nuevo->execute();
            
            $mensaje = "<div class='alert alert-success'>Personal reemplazado exitosamente. El policía anterior queda disponible para futuras guardias.</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'>No se encontró una guardia para la fecha y lugar especificados.</div>";
        }
    }
    
    // Nueva acción para generar guardia semanal
    if ($action == 'generar_guardia_semanal') {
        $guardias_semanales = [];
        $fecha_inicio = $_POST['fecha_inicio']; // Tomar la fecha del formulario
        $fecha_fin = date('Y-m-d', strtotime($fecha_inicio . ' +6 days')); // 7 días desde la fecha seleccionada
        
        // Obtener todos los lugares de guardia activos
        $lugares = $conn->query("SELECT id, nombre FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");
        
        while ($lugar = $lugares->fetch_assoc()) {
            // Generar guardia para cada día de la semana
            for ($i = 0; $i < 7; $i++) {
                $fecha_guardia = date('Y-m-d', strtotime($fecha_inicio . " +$i days"));
                $dia_semana = date('w', strtotime($fecha_guardia)); // 0=Domingo, 1=Lunes, ..., 6=Sábado
                
                // Determinar región requerida según el día
                if ($dia_semana >= 0 && $dia_semana <= 4) { // Domingo a Jueves
                    $region_requerida = 'Central';
                } else { // Viernes y Sábado
                    $region_requerida = 'Regional';
                }
                
                // Buscar policía disponible de la región correspondiente
                $stmt_policia = $conn->prepare("
                    SELECT 
                        p.id as policia_id,
                        p.legajo,
                        p.nombre,
                        p.apellido,
                        p.cin,
                        p.telefono,
                        g.nombre as grado,
                        r.nombre as region,
                        lg.posicion
                    FROM lista_guardias lg
                    JOIN policias p ON lg.policia_id = p.id
                    JOIN grados g ON p.grado_id = g.id
                    JOIN regiones r ON p.region_id = r.id
                    WHERE p.activo = TRUE 
                    AND p.lugar_guardia_id = ?
                    AND r.nombre = ?
                    AND (lg.fecha_disponible IS NULL OR lg.fecha_disponible <= ?)
                    AND NOT EXISTS (
                        SELECT 1 FROM ausencias a 
                        WHERE a.policia_id = p.id 
                        AND a.estado = 'APROBADA'
                        AND ? BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, ?)
                    )
                    ORDER BY lg.posicion ASC, g.nivel_jerarquia ASC, p.legajo ASC
                    LIMIT 1
                ");
                $stmt_policia->bind_param("issss", $lugar['id'], $region_requerida, $fecha_guardia, $fecha_guardia, $fecha_guardia);
                $stmt_policia->execute();
                $result_policia = $stmt_policia->get_result();
                
                if ($policia = $result_policia->fetch_assoc()) {
                    // Registrar la guardia
                    $stmt_guardia = $conn->prepare("
                        INSERT INTO guardias_realizadas (policia_id, fecha_inicio, fecha_fin, lugar_guardia_id, observaciones) 
                        VALUES (?, ?, ?, ?, 'Guardia semanal generada automáticamente')
                    ");
                    $fecha_inicio_guardia = $fecha_guardia . ' 06:00:00';
                    $fecha_fin_guardia = date('Y-m-d H:i:s', strtotime($fecha_guardia . ' +1 day 06:00:00'));
                    $stmt_guardia->bind_param("issi", $policia['policia_id'], $fecha_inicio_guardia, $fecha_fin_guardia, $lugar['id']);
                    $stmt_guardia->execute();
                    
                    // Rotar al policía (moverlo al final de la lista con restricción de tiempo)
                    $stmt_rotar = $conn->prepare("CALL RotarGuardiaFIFO(?)");
                    $stmt_rotar->bind_param("i", $policia['policia_id']);
                    $stmt_rotar->execute();
                    
                    // Agregar a la lista para el PDF
                    $guardias_semanales[] = [
                        'fecha' => $fecha_guardia,
                        'dia_semana' => $dia_semana,
                        'lugar' => $lugar['nombre'],
                        'policia' => $policia,
                        'region_requerida' => $region_requerida
                    ];
                }
            }
        }
        
        // Registrar la guardia semanal en la tabla
        $stmt_semanal = $conn->prepare("
            INSERT INTO guardias_semanales (fecha_inicio, fecha_fin, usuario_id) 
            VALUES (?, ?, ?)
        ");
        $stmt_semanal->bind_param("ssi", $fecha_inicio, $fecha_fin, $_SESSION['usuario_id']);
        $stmt_semanal->execute();
        
        // Generar PDF
        if (!empty($guardias_semanales)) {
            generarPDFGuardiaSemanal($guardias_semanales);
        } else {
            $mensaje = "<div class='alert alert-warning'>No se pudieron asignar guardias para esta semana. Verifique la disponibilidad del personal.</div>";
        }
    }
}

$lugares_guardias = $conn->query("SELECT * FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");

// Función para obtener policías por lugar de guardia ordenados por FIFO
function obtenerPoliciasPorLugar($conn, $lugar_id, $limite = 7) {
    $stmt = $conn->prepare("
        SELECT 
            p.id,
            p.legajo,
            p.nombre,
            p.apellido,
            p.cin,
            p.telefono,
            g.nombre as grado,
            g.nivel_jerarquia,
            r.nombre as region,
            lg.posicion,
            lg.ultima_guardia_fecha,
            lg.fecha_disponible,
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM ausencias a 
                    WHERE a.policia_id = p.id 
                    AND a.estado = 'APROBADA'
                    AND CURDATE() BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, CURDATE())
                ) THEN 'AUSENTE'
                WHEN lg.fecha_disponible IS NOT NULL AND lg.fecha_disponible > CURDATE() THEN 'NO DISPONIBLE'
                ELSE 'DISPONIBLE'
            END as disponibilidad,
            CASE 
                WHEN lg.fecha_disponible IS NOT NULL AND lg.fecha_disponible > CURDATE() 
                THEN lg.fecha_disponible
                ELSE NULL
            END as proxima_fecha_disponible
        FROM lista_guardias lg
        JOIN policias p ON lg.policia_id = p.id
        JOIN grados g ON p.grado_id = g.id
        JOIN regiones r ON p.region_id = r.id
        WHERE p.activo = TRUE AND p.lugar_guardia_id = ?
        ORDER BY lg.posicion ASC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $lugar_id, $limite);
    $stmt->execute();
    return $stmt->get_result();
}

// Función para contar total de policías por lugar
function contarPoliciasPorLugar($conn, $lugar_id) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM policias p
        WHERE p.activo = TRUE AND p.lugar_guardia_id = ?
    ");
    $stmt->bind_param("i", $lugar_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Guardias - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 20px;
        }
        .page-title {
            color: #2c3e50;
            margin-bottom: 30px;
            font-weight: 600;
            font-size: 1.5rem;
        }
        .search-container {
            background: transparent;
            border: none;
            padding: 0;
            margin: 0;
            display: inline-block;
        }
        .search-input {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 14px;
            width: 200px;
            transition: all 0.2s;
            background: white;
        }
        .search-input:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.1rem rgba(13,110,253,.15);
            outline: none;
        }
        .header-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .search-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .search-input {
            border: 2px solid #e9ecef;
            border-radius: 25px;
            padding: 12px 20px;
            font-size: 16px;
            transition: all 0.3s;
        }
        .search-input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        .lugar-guardia-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            overflow: hidden;
        }
        .lugar-header {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .lugar-title {
            font-size: 1.3em;
            font-weight: 600;
            margin: 0;
        }
        .btn-asignar {
            background: rgba(255,255,255,0.2);
            border: 2px solid white;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-asignar:hover {
            background: white;
            color: #007bff;
            transform: scale(1.05);
        }
        .policias-table {
            width: 100%;
            margin: 0;
        }
        .policias-table th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 12px 8px;
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        }
        .policias-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        .policias-table tr:hover {
            background: #f8f9fa;
        }
        .policias-table tr.primero {
            background: #f8fff9;
            border-left: 4px solid #28a745;
        }
        .policias-table tr.no-disponible {
            background: #fff5f5;
            opacity: 0.7;
        }
        .legajo-badge {
            background: #007bff;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 0.8em;
        }
        .primero .legajo-badge {
            background: #28a745;
        }
        .disponibilidad-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .disponible {
            background: #d4edda;
            color: #155724;
        }
        .no-disponible-badge {
            background: #f8d7da;
            color: #721c24;
        }
        .posicion-numero {
            background: #6c757d;
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.8em;
        }
        .primero .posicion-numero {
            background: #28a745;
        }
        .ver-mas-container {
            text-align: center;
            padding: 20px;
            border-top: 1px solid #e9ecef;
        }
        .btn-ver-mas {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-ver-mas:hover {
            background: #5a6268;
            transform: scale(1.05);
        }
        .no-policias {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .info-panel {
            background: linear-gradient(45deg, #17a2b8, #138496);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .loading {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../inc/sidebar.php'; ?>
            
            <!-- Contenido principal -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="page-title">
                            <i class="fas fa-list-ul"></i> Lista de Guardias
                        </h1>
                        <div>
                            <!-- Botón para generar guardia semanal -->
                            <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#modalGenerarGuardiaSemanal">
                                <i class="fas fa-calendar-week"></i> Generar Guardia Semanal
                            </button>
                            <!-- Solo botón para reemplazar personal -->
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalReemplazarPersonal">
                                <i class="fas fa-user-edit"></i> Reemplazar Personal
                            </button>
                        </div>
                    </div>
                    
                    <?php if (isset($mensaje)) echo $mensaje; ?>
                    
                    <!-- Modal para reemplazar personal -->
                    <div class="modal fade" id="modalReemplazarPersonal" tabindex="-1" aria-labelledby="modalReemplazarPersonalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="modalReemplazarPersonalLabel">
                                        <i class="fas fa-user-edit"></i> Reemplazar Personal de Guardia
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" id="formReemplazarPersonal">
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="reemplazar_personal">
                                        
                                        <div class="mb-3">
                                            <label for="fecha_guardia" class="form-label">Fecha de la Guardia</label>
                                            <input type="date" class="form-control" id="fecha_guardia" name="fecha_guardia" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="lugar_guardia_reemplazo" class="form-label">Lugar de Guardia</label>
                                            <select class="form-control" id="lugar_guardia_reemplazo" name="lugar_id" required>
                                                <option value="">Seleccione un lugar</option>
                                                <?php 
                                                $lugares_reemplazo = $conn->query("SELECT * FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");
                                                while ($lugar = $lugares_reemplazo->fetch_assoc()): ?>
                                                    <option value="<?php echo $lugar['id']; ?>"><?php echo htmlspecialchars($lugar['nombre']); ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        
                                        <div id="guardiaActualInfo" class="alert alert-info" style="display: none;">
                                            <h6>Guardia Actual:</h6>
                                            <div id="guardiaActualTexto"></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="nuevo_policia" class="form-label">Nuevo Policía</label>
                                            <select class="form-control" id="nuevo_policia" name="nuevo_policia_id" required>
                                                <option value="">Seleccione un policía</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-warning">Reemplazar Personal</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
               
                    
                    <!-- Lista de lugares de guardias -->
                    <div id="lugaresContainer">
                        <?php while ($lugar = $lugares_guardias->fetch_assoc()): ?>
                            <div class="lugar-guardia-section" data-lugar-id="<?php echo $lugar['id']; ?>" data-lugar="<?php echo strtolower($lugar['nombre']); ?>">
                                <div class="lugar-header">
                                    <h3 class="lugar-title">
                                        <i class="fas fa-map-marker-alt"></i> 
                                        <?php echo htmlspecialchars($lugar['nombre']); ?>
                                    </h3>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="generar_guardia">
                                        <input type="hidden" name="lugar_id" value="<?php echo $lugar['id']; ?>">
                                        <button type="submit" class="btn btn-asignar">
                                            <i class="fas fa-plus-circle"></i> Asignar Guardia
                                        </button>
                                    </form>
                                </div>
                                
                                <?php 
                                $policias = obtenerPoliciasPorLugar($conn, $lugar['id']);
                                $total_policias = contarPoliciasPorLugar($conn, $lugar['id']);
                                ?>
                                
                                <?php if ($policias->num_rows > 0): ?>
                                    <table class="table policias-table mb-0">
                                        <thead>
                                            <tr>
                                                <th width="8%">Pos.</th>
                                                <th width="12%">Legajo</th>
                                                <th width="25%">Apellido y Nombre</th>
                                                <th width="15%">Grado</th>
                                                <th width="12%">Región</th>
                                                <th width="15%">Disponibilidad</th>
                                                <th width="13%">Próxima Fecha</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $posicion = 1;
                                            while ($policia = $policias->fetch_assoc()): 
                                                $es_primero = ($posicion == 1);
                                                $no_disponible = ($policia['disponibilidad'] != 'DISPONIBLE');
                                                $clase_fila = '';
                                                if ($es_primero && !$no_disponible) {
                                                    $clase_fila = 'primero';
                                                } elseif ($no_disponible) {
                                                    $clase_fila = 'no-disponible';
                                                }
                                            ?>
                                                <tr class="<?php echo $clase_fila; ?>">
                                                    <td>
                                                        <span class="posicion-numero"><?php echo $posicion; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="legajo-badge"><?php echo htmlspecialchars($policia['legajo']); ?></span>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($policia['apellido'] . ', ' . $policia['nombre']); ?></strong>
                                                        <?php if ($policia['cin']): ?>
                                                            <br><small class="text-muted">CIN: <?php echo htmlspecialchars($policia['cin']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($policia['grado']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo ($policia['region'] == 'CENTRAL') ? 'primary' : 'success'; ?>">
                                                            <?php echo htmlspecialchars($policia['region']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $clase_disponibilidad = '';
                                                        switch($policia['disponibilidad']) {
                                                            case 'DISPONIBLE':
                                                                $clase_disponibilidad = 'disponible';
                                                                break;
                                                            case 'NO DISPONIBLE':
                                                            case 'AUSENTE':
                                                                $clase_disponibilidad = 'no-disponible-badge';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="disponibilidad-badge <?php echo $clase_disponibilidad; ?>">
                                                            <?php echo $policia['disponibilidad']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($policia['proxima_fecha_disponible']): ?>
                                                            <small><?php echo date('d/m/Y', strtotime($policia['proxima_fecha_disponible'])); ?></small>
                                                        <?php else: ?>
                                                            <small class="text-muted">-</small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php 
                                            $posicion++;
                                            endwhile; ?>
                                        </tbody>
                                    </table>
                                    
                                    <?php if ($total_policias > 7): ?>
                                        <div class="ver-mas-container">
                                            <button class="btn btn-ver-mas" data-total="<?php echo $total_policias; ?>" data-expanded="false" onclick="verMasPolicias(<?php echo $lugar['id']; ?>)">
                                                <i class="fas fa-eye"></i> Ver todos los policías (<?php echo $total_policias; ?> total)
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                    
                                <?php else: ?>
                                    <div class="no-policias">
                                        <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
                                        <h5>No hay policías asignados</h5>
                                        <p>No se encontraron policías asignados a este lugar de guardia.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función de búsqueda
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const lugares = document.querySelectorAll('.lugar-guardia-section');
            
            lugares.forEach(function(lugar) {
                const nombreLugar = lugar.getAttribute('data-lugar');
                if (nombreLugar.includes(searchTerm)) {
                    lugar.style.display = 'block';
                } else {
                    lugar.style.display = 'none';
                }
            });
        });
        
        // Función para ver más policías
        function verMasPolicias(lugarId) {
            const container = document.querySelector(`[data-lugar-id="${lugarId}"]`);
            const btnVerMas = container.querySelector('.btn-ver-mas');
            const tbody = container.querySelector('.policias-table tbody');
            
            if (btnVerMas.dataset.expanded === 'true') {
                // Contraer - mostrar solo los primeros 7
                const filas = tbody.querySelectorAll('tr');
                filas.forEach((fila, index) => {
                    if (index >= 7) {
                        fila.style.display = 'none';
                    }
                });
                btnVerMas.innerHTML = '<i class="fas fa-eye"></i> Ver todos los policías (' + btnVerMas.dataset.total + ' total)';
                btnVerMas.dataset.expanded = 'false';
            } else {
                // Expandir - cargar y mostrar todos los policías
                fetch('api/obtener_todos_policias.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'lugar_id=' + lugarId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Limpiar tabla actual
                        tbody.innerHTML = '';
                        
                        // Agregar todos los policías
                        data.policias.forEach((policia, index) => {
                            const posicion = index + 1;
                            const esPrimero = posicion === 1;
                            const noDisponible = policia.disponibilidad !== 'DISPONIBLE';
                            let claseFila = '';
                            
                            if (esPrimero && !noDisponible) {
                                claseFila = 'primero';
                            } else if (noDisponible) {
                                claseFila = 'no-disponible';
                            }
                            
                            const regionBadgeClass = policia.region === 'CENTRAL' ? 'primary' : 'success';
                            let disponibilidadClass = '';
                            switch(policia.disponibilidad) {
                                case 'DISPONIBLE':
                                    disponibilidadClass = 'disponible';
                                    break;
                                case 'NO DISPONIBLE':
                                case 'AUSENTE':
                                    disponibilidadClass = 'no-disponible-badge';
                                    break;
                            }
                            
                            const proximaFecha = policia.proxima_fecha_disponible ? 
                                new Date(policia.proxima_fecha_disponible).toLocaleDateString('es-ES') : '-';
                            
                            const fila = `
                                <tr class="${claseFila}">
                                    <td><span class="posicion-numero">${posicion}</span></td>
                                    <td><span class="legajo-badge">${policia.legajo}</span></td>
                                    <td>
                                        <strong>${policia.apellido}, ${policia.nombre}</strong>
                                        ${policia.cin ? `<br><small class="text-muted">CIN: ${policia.cin}</small>` : ''}
                                    </td>
                                    <td>${policia.grado}</td>
                                    <td><span class="badge bg-${regionBadgeClass}">${policia.region}</span></td>
                                    <td><span class="disponibilidad-badge ${disponibilidadClass}">${policia.disponibilidad}</span></td>
                                    <td><small class="${policia.proxima_fecha_disponible ? '' : 'text-muted'}">${proximaFecha}</small></td>
                                </tr>
                            `;
                            tbody.innerHTML += fila;
                        });
                        
                        btnVerMas.innerHTML = '<i class="fas fa-eye-slash"></i> Ver menos';
                        btnVerMas.dataset.expanded = 'true';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar los policías');
                });
            }
        }
        
        // Prevenir envío accidental del formulario
        document.getElementById('searchInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
        
        // Establecer fecha mínima al cargar
        document.addEventListener('DOMContentLoaded', function() {
            const hoy = new Date();
            const fechaMinima = hoy.toISOString().split('T')[0];
            document.getElementById('fecha_guardia').setAttribute('min', fechaMinima);
        });
        
        // Manejo del modal de reemplazo de personal
        document.getElementById('fecha_guardia').addEventListener('change', function() {
            const fecha = this.value;
            const lugarSelect = document.getElementById('lugar_guardia_reemplazo');
            
            if (fecha && lugarSelect.value) {
                buscarGuardiaExistente(fecha, lugarSelect.value);
            }
        });
        
        document.getElementById('lugar_guardia_reemplazo').addEventListener('change', function() {
            const lugar = this.value;
            const fechaInput = document.getElementById('fecha_guardia');
            
            if (fechaInput.value && lugar) {
                buscarGuardiaExistente(fechaInput.value, lugar);
            }
            
            // Cargar policías disponibles para este lugar
            if (lugar) {
                cargarPolicias(lugar);
            }
        });
        
        function buscarGuardiaExistente(fecha, lugarId) {
            fetch('api/buscar_guardia.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `fecha=${fecha}&lugar_id=${lugarId}`
            })
            .then(response => response.json())
            .then(data => {
                const infoDiv = document.getElementById('guardiaActualInfo');
                const textoDiv = document.getElementById('guardiaActualTexto');
                
                if (data.success && data.guardia) {
                    textoDiv.innerHTML = `
                        <strong>Policía actual:</strong> ${data.guardia.apellido}, ${data.guardia.nombre}<br>
                        <strong>Legajo:</strong> ${data.guardia.legajo}<br>
                        <strong>Grado:</strong> ${data.guardia.grado}
                    `;
                    infoDiv.style.display = 'block';
                } else {
                    infoDiv.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('guardiaActualInfo').style.display = 'none';
            });
        }
        
        function cargarPolicias(lugarId) {
            fetch('api/buscar_policias.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `lugar_id=${lugarId}`
            })
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('nuevo_policia');
                select.innerHTML = '<option value="">Seleccione un policía</option>';
                
                if (data.success && data.policias) {
                    data.policias.forEach(policia => {
                        const option = document.createElement('option');
                        option.value = policia.id;
                        option.textContent = `${policia.apellido}, ${policia.nombre} (${policia.legajo}) - ${policia.grado}`;
                        select.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>

                    <!-- Modal para generar guardia semanal -->
                    <div class="modal fade" id="modalGenerarGuardiaSemanal" tabindex="-1" aria-labelledby="modalGenerarGuardiaLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="modalGenerarGuardiaLabel">
                                        <i class="fas fa-calendar-week"></i> Generar Guardia Semanal
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" id="formGenerarGuardiaSemanal">
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="generar_guardia_semanal">
                                        
                                        <div class="mb-3">
                                            <label for="fecha_inicio" class="form-label">Fecha de Inicio (Domingo)</label>
                                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                                            <div class="form-text">Seleccione el domingo de la semana para la cual desea generar las guardias.</div>
                                        </div>
                                        
                                        <div class="alert alert-info">
                                            <h6><i class="fas fa-info-circle"></i> Información:</h6>
                                            <ul class="mb-0">
                                                <li><strong>Domingo a Jueves:</strong> Personal de región Central</li>
                                                <li><strong>Viernes y Sábado:</strong> Personal de región Regional</li>
                                                <li><strong>Central:</strong> Disponible cada 15 días</li>
                                                <li><strong>Regional:</strong> Disponible cada 30 días</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-success">Generar Guardia Semanal</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal para generar guardia semanal -->
                    <div class="modal fade" id="modalGenerarSemanal" tabindex="-1" aria-labelledby="modalGenerarSemanalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="modalGenerarSemanalLabel">
                                        <i class="fas fa-calendar-week"></i> Generar Guardia Semanal
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle"></i> Información:</h6>
                                        <ul class="mb-0">
                                            <li><strong>Domingo a Jueves:</strong> Personal de región Central</li>
                                            <li><strong>Viernes y Sábado:</strong> Personal de región Regional</li>
                                            <li><strong>Rotación:</strong> Cada persona asignada pasa al final de su lista</li>
                                            <li><strong>Restricciones:</strong> Central (15 días), Regional (30 días)</li>
                                        </ul>
                                    </div>
                                    <p>¿Está seguro de generar la guardia semanal? Esto asignará 7 días de guardia para cada lugar y generará un PDF con la programación.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="generar_guardia_semanal">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-file-pdf"></i> Generar y Descargar PDF
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>