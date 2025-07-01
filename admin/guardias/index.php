<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';
require_once '../../config/config_fecha_sistema.php';
require_once 'generar_pdf.php';

if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'generar_guardia') {
        $lugar_id = $_POST['lugar_id'];
        
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
            
            // Rotar al policía
            $stmt_rotar = $conn->prepare("CALL RotarGuardiaFIFO(?)");
            $stmt_rotar->bind_param("i", $row['policia_id']);
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
        $guardia_id = $_POST['guardia_id'];
        $nuevo_policia_id = $_POST['nuevo_policia_id'];
        
        // Buscar la guardia existente
        $stmt_buscar = $conn->prepare("
            SELECT gr.id, gr.policia_id 
            FROM guardias_realizadas gr 
            WHERE gr.id = ?
        ");
        $stmt_buscar->bind_param("i", $guardia_id);
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
            $mensaje = "<div class='alert alert-danger'>No se encontró la guardia especificada.</div>";
        }
    }
    
    // Nueva acción para generar guardia semanal
    if ($action == 'generar_guardia_semanal') {
        $guardias_semanales = [];
        $fecha_inicio = $_POST['fecha_inicio'];
        $fecha_fin = date('Y-m-d', strtotime($fecha_inicio . ' +6 days'));
        
        // VALIDACIÓN: Verificar si ya existen guardias en este período
        $stmt_validacion = $conn->prepare("
            SELECT COUNT(*) as total FROM guardias_semanales 
            WHERE (fecha_inicio <= ? AND fecha_fin >= ?) 
            OR (fecha_inicio <= ? AND fecha_fin >= ?)
            OR (fecha_inicio >= ? AND fecha_fin <= ?)
        ");
        $stmt_validacion->bind_param("ssssss", 
            $fecha_fin, $fecha_inicio,    // Caso 1: período existente contiene inicio
            $fecha_inicio, $fecha_fin,    // Caso 2: período existente contiene fin  
            $fecha_inicio, $fecha_fin     // Caso 3: nuevo período contiene existente
        );
        $stmt_validacion->execute();
        $result_validacion = $stmt_validacion->get_result();
        $existe = $result_validacion->fetch_assoc()['total'];
        
        if ($existe > 0) {
            $mensaje = "<div class='alert alert-danger'>Ya existen guardias programadas que se superponen con el período seleccionado ($fecha_inicio al $fecha_fin). No se pueden generar guardias duplicadas.</div>";
        } else {
            // NUEVA VALIDACIÓN: Verificar disponibilidad de personal
            $lugares = $conn->query("SELECT id, nombre FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");
            $personal_disponible = false;
            
            // Verificar si hay al menos un policía disponible en algún lugar
            while ($lugar = $lugares->fetch_assoc()) {
                $stmt_disponibilidad = $conn->prepare("
                    SELECT COUNT(*) as disponibles
                    FROM lista_guardias lg
                    JOIN policias p ON lg.policia_id = p.id
                    JOIN regiones r ON p.region_id = r.id
                    WHERE p.activo = TRUE 
                    AND p.lugar_guardia_id = ?
                    AND (lg.fecha_disponible IS NULL OR lg.fecha_disponible <= ?)
                    AND NOT EXISTS (
                        SELECT 1 FROM ausencias a 
                        WHERE a.policia_id = p.id 
                        AND a.estado = 'APROBADA'
                        AND ? BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, ?)
                    )
                ");
                $stmt_disponibilidad->bind_param("isss", $lugar['id'], $fecha_inicio, $fecha_inicio, $fecha_inicio);
                $stmt_disponibilidad->execute();
                $result_disponibilidad = $stmt_disponibilidad->get_result();
                $disponibles = $result_disponibilidad->fetch_assoc()['disponibles'];
                
                if ($disponibles > 0) {
                    $personal_disponible = true;
                    break;
                }
            }
            
            if (!$personal_disponible) {
                $mensaje = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> <strong>No hay personal disponible para guardias actualmente.</strong><br>Todo el personal se encuentra ausente o no disponible para el período seleccionado ($fecha_inicio al $fecha_fin).</div>";
            } else {
                // Reiniciar el cursor para el bucle principal
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
                                lg.policia_id,
                                p.nombre,
                                p.apellido,
                                p.legajo,
                                p.cin,
                                p.telefono,
                                g.nombre as grado,
                                g.abreviatura as grado_abreviatura,
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
                    // Guardar datos en sesión para mostrar mensaje de éxito
                    $_SESSION['guardias_generadas'] = count($guardias_semanales);
                    $_SESSION['fecha_guardias'] = $fecha_inicio . ' al ' . $fecha_fin;
                    
                    generarPDFGuardiaSemanal($guardias_semanales);
                    
                    // Después del PDF, redirigir con mensaje de éxito
                    header('Location: index.php?success=guardias_generadas');
                    exit();
                } else {
                    $mensaje = "<div class='alert alert-warning'>No se pudieron asignar guardias para esta semana. Verifique la disponibilidad del personal.</div>";
                }
            }
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
                WHEN lg.fecha_disponible IS NOT NULL AND lg.fecha_disponible > CURDATE() THEN 'NO DISPONIBLE'
                ELSE 'DISPONIBLE'
            END as disponibilidad,
            CASE 
                WHEN lg.fecha_disponible IS NOT NULL AND lg.fecha_disponible > CURDATE() 
                THEN lg.fecha_disponible
                ELSE NULL
            END as proxima_fecha_disponible,
            (SELECT MAX(gr.fecha_inicio)
                FROM guardias_realizadas gr 
                WHERE gr.policia_id = p.id
            ) as ultima_guardia_realizada
        FROM lista_guardias lg
        JOIN policias p ON lg.policia_id = p.id
        JOIN grados g ON p.grado_id = g.id
        JOIN regiones r ON p.region_id = r.id
        WHERE p.activo = TRUE 
        AND p.lugar_guardia_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM ausencias a 
            WHERE a.policia_id = p.id 
            AND a.estado = 'APROBADA'
            AND CURDATE() BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, CURDATE())
        )
        ORDER BY lg.posicion ASC
        LIMIT ?
    ");
    
    if ($stmt === false) {
        die('Error en la consulta SQL: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $lugar_id, $limite);
    $stmt->execute();
    return $stmt->get_result();
}

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Guardias</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 20px;
        }

        .search-container {
            position: relative;
            margin-bottom: 25px;
            max-width: 400px;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 2px solid #f1f3f4;
            border-radius: 25px;
            font-size: 15px;
            background: #ffffff;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            outline: none;
        }
        
        .search-input:focus {
            border-color: #4285f4;
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.15);
            transform: translateY(-1px);
        }
        
        .search-input::placeholder {
            color: #9aa0a6;
            font-weight: 400;
        }
        
        .search-container::before {
            content: '\f002';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9aa0a6;
            font-size: 14px;
            z-index: 1;
            transition: color 0.3s ease;
        }
        
        .search-container:focus-within::before {
            color: #4285f4;
        }
        
        .title-section {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .action-buttons .btn {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .action-buttons .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .page-title {
            color: #202124;
            font-weight: 500;
            margin-bottom: 0;
        }
        
        @media (max-width: 768px) {
            .d-flex.justify-content-between {
                flex-direction: column;
                align-items: stretch !important;
                gap: 20px;
            }
            
            .search-container {
                max-width: 100%;
            }
            
            .action-buttons {
                justify-content: stretch;
            }
            
            .action-buttons .btn {
                flex: 1;
                min-width: 0;
            }
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
            color: #333; 
        }
        
        .policias-table td:nth-child(3),
        .policias-table td:nth-child(4) { 
            color: #333 !important;
            font-weight: normal;
        }
        
        .policias-table td:nth-child(3) {
            font-weight: bold !important;
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
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .disponible {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .no-disponible {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .ausente {
            background: #dc3545 !important;
            color: white !important;
            font-weight: bold;
            border: 1px solid #c82333;
            animation: pulse-red 2s infinite;
        }
        
        .policias-table tr.disponible {
            background: #f8fff9;
            border-left: 3px solid #28a745;
        }
        
        .policias-table tr.no-disponible {
            background: #fffbf0;
            border-left: 3px solid #fd7e14;
            opacity: 0.9;
        }
        
        .policias-table tr.ausente {
            background: #f8d7da !important;
            border-left: 4px solid #dc3545 !important;
            opacity: 0.8;
        }
        
        .policias-table tr.ausente:hover {
            background: #f5c6cb !important;
        }
        
        .no-disponible-badge {
            background: #fd7e14;
            color: white;
            border: 1px solid #e55a00;
        }
        
        .ausente-info {
            background: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.75em;
            font-weight: bold;
            margin-left: 5px;
        }
        
        @keyframes pulse-red {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
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
        .region-badge {
            background: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
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
        
        .cursor-pointer {
            cursor: pointer;
        }
        
        .guardia-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: all 0.2s;
        }
        
        .table-responsive {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }
        
        #tbody-policias tr:hover:not(.table-secondary) {
            background-color: #f8f9fa;
        }
        
        #tbody-policias tr.table-primary {
            background-color: #cfe2ff !important;
        }
        
        .selected-row {
            background-color: #e3f2fd !important;
        }
        
        .fecha-guardia {
            font-weight: 600;
            color: #495057;
        }
        
        .text-muted {
            color: #6c757d !important;
            font-size: 0.85em;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../inc/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-start pt-3 pb-2 mb-3">
                    <div class="title-section">
                        <h1 class="page-title">
                            <i class="fas fa-shield-alt"></i> Lista de Guardias
                        </h1>
                        <div class="search-container">
                            <input type="text" id="searchInput" class="search-input" placeholder="Buscar policía, legajo o ubicación...">
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalGenerarGuardiaSemanal">
                            <i class="fas fa-calendar-week"></i> Generar Guardia Semanal
                        </button>
                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalReemplazarPersonal">
                            <i class="fas fa-user-edit"></i> Reemplazar Personal
                        </button>
                    </div>
                </div>
                
                <?php if (isset($mensaje)) echo $mensaje; ?>
                
                <div class="modal fade" id="modalReemplazarPersonal" tabindex="-1" aria-labelledby="modalReemplazarLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalReemplazarLabel">
                                    <i class="fas fa-user-edit"></i> Reemplazar Personal de Guardia
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            
                            <div id="paso1-seleccionar-guardia">
                                <div class="modal-body">
                                    <h6 class="mb-3"><i class="fas fa-search"></i> Paso 1: Seleccionar Guardia a Reemplazar</h6>
                                    
                                    <div class="mb-3">
                                        <label for="fecha_busqueda" class="form-label">Fecha de Guardia</label>
                                        <input type="date" class="form-control" id="fecha_busqueda" required>
                                        <div class="form-text">Seleccione la fecha para ver las guardias asignadas</div>
                                    </div>
                                    
                                    <div id="guardias-existentes" class="mt-3" style="display: none;">
                                        <h6>Guardias Asignadas:</h6>
                                        <div id="lista-guardias-existentes"></div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                </div>
                            </div>
                            
                            <div id="paso2-seleccionar-policia" style="display: none;">
                                <form method="POST" id="formReemplazarPersonal">
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="reemplazar_personal">
                                        <input type="hidden" id="guardia_id_reemplazo" name="guardia_id">
                                        <input type="hidden" id="fecha_guardia_reemplazo" name="fecha_guardia">
                                        <input type="hidden" id="lugar_id_reemplazo" name="lugar_id">
                                        
                                        <h6 class="mb-3"><i class="fas fa-user-plus"></i> Paso 2: Seleccionar Nuevo Policía</h6>
                                        
                                        <div id="info-guardia-actual" class="alert alert-info mb-3">
                                            <h6>Guardia a Reemplazar:</h6>
                                            <div id="datos-guardia-actual"></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="buscar_policia" class="form-label">Buscar Policía</label>
                                            <input type="text" class="form-control" id="buscar_policia" placeholder="Buscar por nombre, apellido, legajo o CIN...">
                                            <div class="form-text">Escriba para buscar en tiempo real</div>
                                        </div>
                                        
                                        <div id="resultados-busqueda" class="mb-3">
                                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                                <table class="table table-hover" id="tabla-policias">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th width="5%"></th>
                                                            <th width="15%">Legajo</th>
                                                            <th width="35%">Apellido y Nombre</th>
                                                            <th width="20%">Grado</th>
                                                            <th width="15%">Región</th>
                                                            <th width="10%">Estado</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="tbody-policias">
                                                        <tr>
                                                            <td colspan="6" class="text-center text-muted">Cargando policías disponibles...</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <input type="hidden" id="nuevo_policia_id" name="nuevo_policia_id" required>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" onclick="volverPaso1()">Volver</button>
                                        <button type="submit" class="btn btn-warning" id="btn-confirmar-reemplazo" disabled>
                                            <i class="fas fa-exchange-alt"></i> Confirmar Reemplazo
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="lugaresContainer">
                    <?php while ($lugar = $lugares_guardias->fetch_assoc()): ?>
                        <div class="lugar-guardia-section" data-lugar="<?php echo strtolower($lugar['nombre']); ?>">
                            <div class="lugar-header">
                                <h3 class="lugar-title">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($lugar['nombre']); ?>
                                </h3>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="generar_guardia">
                                    <input type="hidden" name="lugar_id" value="<?php echo $lugar['id']; ?>">
                                    
                                </form>
                            </div>
                            
                            <?php 
                            $policias = obtenerPoliciasPorLugar($conn, $lugar['id'], 7);
                            $total_policias = contarPoliciasPorLugar($conn, $lugar['id']);
                            ?>
                            
                            <?php if ($policias->num_rows > 0): ?>
                                <table class="policias-table">
                                    <thead>
                                        <tr>
                                            <th width="6%">Pos.</th>
                                            <th width="10%">Legajo</th>
                                            <th width="25%">Apellido y Nombre</th>
                                            <th width="12%">Grado</th>
                                            <th width="10%">Región</th>
                                            <th width="12%">Estado</th>
                                            <th width="12%">Última Guardia</th>
                                            <th width="13%">Próx. Disp.</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-policias-disponibles-<?php echo $lugar['id']; ?>">
                                        <?php while ($policia = $policias->fetch_assoc()): ?>
                                            <?php 
                                            $clase_fila = '';
                                            if ($policia['posicion'] == 1) {
                                                $clase_fila .= 'primero ';
                                            }
                                            
                                            switch($policia['disponibilidad']) {
                                                case 'DISPONIBLE':
                                                    $clase_fila .= 'disponible';
                                                    break;
                                                case 'NO DISPONIBLE':
                                                    $clase_fila .= 'no-disponible';
                                                    break;
                                                case 'AUSENTE':
                                                    $clase_fila .= 'ausente';
                                                    break;
                                                default:
                                                    $clase_fila .= 'disponible';
                                            }
                                            ?>
                                            <tr class="<?php echo trim($clase_fila); ?>">
                                                <td>
                                                    <span class="posicion-numero"><?php echo $policia['posicion']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="legajo-badge"><?php echo htmlspecialchars($policia['legajo']); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($policia['apellido'] . ', ' . $policia['nombre']); ?></td>
                                                <td><?php echo htmlspecialchars($policia['grado']); ?></td>
                                                <td><span class="region-badge"><?php echo htmlspecialchars($policia['region']); ?></span></td>
                                                <td>
                                                    <?php 
                                                    $clase_badge = 'disponibilidad-badge ';
                                                    switch($policia['disponibilidad']) {
                                                        case 'DISPONIBLE':
                                                            $clase_badge .= 'disponible';
                                                            break;
                                                        case 'NO DISPONIBLE':
                                                            $clase_badge .= 'no-disponible';
                                                            break;
                                                        case 'AUSENTE':
                                                            $clase_badge .= 'ausente';
                                                            break;
                                                        default:
                                                            $clase_badge .= 'disponible';
                                                    }
                                                    ?>
                                                    <span class="<?php echo $clase_badge; ?>">
                                                        <?php 
                                                        echo $policia['disponibilidad'];
                                                        ?>
                                                    </span>
                                                    
                                                </td>
                                                <td>
                                                    <?php 
                                                    if ($policia['ultima_guardia_realizada']) {
                                                        $fecha_guardia = date('d/m/Y', strtotime($policia['ultima_guardia_realizada']));
                                                        echo '<span class="fecha-guardia">' . $fecha_guardia . '</span>';
                                                    } else {
                                                        echo '<span class="text-muted">Sin guardias</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if ($policia['disponibilidad'] == 'AUSENTE' && $policia['fecha_fin_ausencia']) {
                                                        echo 'Hasta: ' . date('d/m/Y', strtotime($policia['fecha_fin_ausencia']));
                                                    } elseif ($policia['proxima_fecha_disponible']) {
                                                        echo date('d/m/Y', strtotime($policia['proxima_fecha_disponible']));
                                                    } else {
                                                        echo 'Disponible';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                                
                                <?php if ($total_policias > 7): ?>
                                    <div class="ver-mas-container">
                                        <button class="btn-ver-mas" onclick="toggleVerMas(this)" data-lugar-id="<?php echo $lugar['id']; ?>" data-expanded="false">
                                            <i class="fas fa-eye"></i> Ver más (<?php echo $total_policias - 7; ?> restantes)
                                        </button>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="no-policias">
                                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                                    <p>No hay policías asignados a este lugar de guardia</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
                
              
            </main>
        </div>
    </div>
    
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let guardiaSeleccionada = null;
        let policiaSeleccionado = null;
        let timeoutBusqueda = null;
        
        function toggleVerMas(btn) {
            const tbody = document.getElementById('tbody-policias-disponibles-' + btn.dataset.lugarId);
            const isExpanded = btn.dataset.expanded === 'true';
            
            if (isExpanded) {
                const filas = tbody.querySelectorAll('tr');
                filas.forEach((fila, index) => {
                    if (index >= 7) {
                        fila.style.display = 'none';
                    }
                });
                btn.innerHTML = '<i class="fas fa-eye"></i> Ver más';
                btn.dataset.expanded = 'false';
            } else {
                const lugarId = btn.dataset.lugarId;
                const query = document.getElementById('searchInput').value;
                
                fetch(`api/buscar_policias.php?lugar_id=${lugarId}&q=${encodeURIComponent(query)}&limite=50`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.policias) {
                        tbody.innerHTML = '';
                        
                        data.policias.forEach((policia, index) => {
                            const posicion = index + 1;
                            let claseFila = '';
                            if (posicion === 1) {
                                claseFila += 'primero ';
                            }
           
                            switch(policia.disponibilidad) {
                                case 'DISPONIBLE':
                                    claseFila += 'disponible';
                                    break;
                                case 'NO DISPONIBLE':
                                    claseFila += 'no-disponible';
                                    break;
                                case 'AUSENTE':
                                    claseFila += 'ausente';
                                    break;
                                default:
                                    claseFila += 'disponible';
                            }
                            
                            const disponibilidadClass = policia.disponibilidad === 'DISPONIBLE' ? 'disponible' : (policia.disponibilidad === 'AUSENTE' ? 'ausente' : 'no-disponible');
                            const proximaFecha = policia.proxima_fecha_disponible ? 
                                new Date(policia.proxima_fecha_disponible).toLocaleDateString('es-ES') : 
                                'No especificada';
                            
                            const fila = `
                                <tr class="${claseFila}">
                                    <td><span class="posicion-numero">${posicion}</span></td>
                                    <td><span class="legajo-badge">${policia.legajo}</span></td>
                                    <td>
                                        <strong style="color: #333;">${policia.apellido}, ${policia.nombre}</strong>
                                        ${policia.cin ? `<br><small class="text-muted">CIN: ${policia.cin}</small>` : ''}
                                    </td>
                                    <td style="color: #333;">${policia.grado}</td>
                                    <td><span class="region-badge">${policia.region}</span></td>
                                    <td>
                                        <span class="disponibilidad-badge ${disponibilidadClass}">
                                            ${policia.disponibilidad}
                                        </span>
                                       
                                    </td>
                                    <td>
                                        ${policia.ultima_guardia_realizada ? 
                                            `<span class="fecha-guardia">${new Date(policia.ultima_guardia_realizada).toLocaleDateString('es-ES')}</span>` : 
                                            '<span class="text-muted">Sin guardias</span>'}
                                    </td>
                                    <td>
                                        ${policia.disponibilidad === 'AUSENTE' && policia.fecha_fin_ausencia ? 
                                            `Hasta: ${new Date(policia.fecha_fin_ausencia).toLocaleDateString('es-ES')}` : 
                                            (policia.proxima_fecha_disponible ? 
                                                new Date(policia.proxima_fecha_disponible).toLocaleDateString('es-ES') : 
                                                'Disponible')}
                                    </td>
                                </tr>
                            `;
                            tbody.innerHTML += fila;
                        });
                        
                        btn.innerHTML = '<i class="fas fa-eye-slash"></i> Ver menos';
                        btn.dataset.expanded = 'true';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar los policías');
                });
            }
        }
        
        document.getElementById('searchInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            const hoy = new Date();
            const fechaMinima = hoy.toISOString().split('T')[0];
            const fechaBusqueda = document.getElementById('fecha_busqueda');
            
            if (fechaBusqueda) {
                fechaBusqueda.setAttribute('min', fechaMinima);
               
                fechaBusqueda.addEventListener('change', function() {
                    buscarGuardiasPorFecha(this.value);
                });
            }
            
            const buscarPolicia = document.getElementById('buscar_policia');
            if (buscarPolicia) {
                buscarPolicia.addEventListener('input', function() {
                    clearTimeout(timeoutBusqueda);
                    timeoutBusqueda = setTimeout(() => {
                        buscarPolicias(this.value);
                    }, 300);
                });
            }
        });
        
        function buscarGuardiasPorFecha(fecha) {
            if (!fecha) return;
            
            console.log('Buscando guardias para fecha:', fecha); 
            
            fetch(`api/buscar_guardias_fecha.php?fecha=${fecha}`)
            .then(response => {
                console.log('Response status:', response.status); 
                return response.json();
            })
            .then(data => {
                console.log('Data received:', data);
                
                const container = document.getElementById('lista-guardias-existentes');
                const guardiaDiv = document.getElementById('guardias-existentes');
                
                if (data.success && data.guardias && data.guardias.length > 0) {
                    let html = '<div class="row">';
                    
                    data.guardias.forEach(guardia => {
                        html += `
                            <div class="col-md-6 mb-2">
                                <div class="card border-primary cursor-pointer guardia-card" 
                                     onclick="seleccionarGuardia(${guardia.id}, '${guardia.lugar_nombre}', '${guardia.apellido}, ${guardia.nombre}', '${guardia.legajo}', '${guardia.grado}', ${guardia.lugar_guardia_id}, '${fecha}')">
                                    <div class="card-body p-3">
                                        <h6 class="card-title mb-1">
                                            <i class="fas fa-map-marker-alt text-primary"></i> ${guardia.lugar_nombre}
                                        </h6>
                                        <p class="card-text mb-1">
                                            <strong>${guardia.apellido}, ${guardia.nombre}</strong><br>
                                            <small class="text-muted">Legajo: ${guardia.legajo} | ${guardia.grado}</small>
                                        </p>
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i> ${new Date(guardia.fecha_inicio).toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'})}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                    container.innerHTML = html;
                    guardiaDiv.style.display = 'block';
                } else {
                    container.innerHTML = '<div class="alert alert-warning">No se encontraron guardias asignadas para esta fecha.</div>';
                    guardiaDiv.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const container = document.getElementById('lista-guardias-existentes');
                const guardiaDiv = document.getElementById('guardias-existentes');
                container.innerHTML = '<div class="alert alert-danger">Error al buscar guardias. Verifique la conexión.</div>';
                guardiaDiv.style.display = 'block';
            });
        }
        
        function seleccionarGuardia(guardiaId, lugar, policiaNombre, legajo, grado, lugarId, fecha) {
            guardiaSeleccionada = {
                id: guardiaId,
                lugar: lugar,
                policia: policiaNombre,
                legajo: legajo,
                grado: grado,
                lugar_id: lugarId,
                fecha: fecha
            };
            
            document.getElementById('guardia_id_reemplazo').value = guardiaId;
            document.getElementById('fecha_guardia_reemplazo').value = fecha;
            document.getElementById('lugar_id_reemplazo').value = lugarId;
            
            document.getElementById('datos-guardia-actual').innerHTML = `
                <strong>Lugar:</strong> ${lugar}<br>
                <strong>Policía actual:</strong> ${policiaNombre}<br>
                <strong>Legajo:</strong> ${legajo}<br>
                <strong>Grado:</strong> ${grado}
            `;
            
            document.getElementById('paso1-seleccionar-guardia').style.display = 'none';
            document.getElementById('paso2-seleccionar-policia').style.display = 'block';
            
            buscarPolicias('');
        }
        
        function buscarPolicias(query = '') {
            if (!guardiaSeleccionada) return;
            
            const tbody = document.getElementById('tbody-policias');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">Buscando...</td></tr>';
            
            fetch(`api/buscar_policias.php?lugar_id=${guardiaSeleccionada.lugar_id}&q=${encodeURIComponent(query)}&limite=20`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.policias && data.policias.length > 0) {
                    let html = '';
                    
                    data.policias.forEach(policia => {
                        const disponible = policia.disponibilidad === 'DISPONIBLE';
                        const claseDisponibilidad = disponible ? 'success' : (policia.disponibilidad === 'AUSENTE' ? 'danger' : 'warning');
                        const disabled = !disponible ? 'disabled' : '';
                        
                        html += `
                            <tr class="${!disponible ? 'table-secondary' : 'cursor-pointer'}" 
                                ${disponible ? `onclick="seleccionarPolicia(${policia.id}, '${policia.apellido}, ${policia.nombre}', '${policia.legajo}', '${policia.grado}')"` : ''}>
                                <td>
                                    <input type="radio" name="policia_radio" value="${policia.id}" 
                                           ${disabled} class="form-check-input" 
                                           ${disponible ? `onchange="seleccionarPolicia(${policia.id}, '${policia.apellido}, ${policia.nombre}', '${policia.legajo}', '${policia.grado}')"` : ''}>
                                </td>
                                <td><span class="badge bg-primary">${policia.legajo}</span></td>
                                <td>
                                    <strong style="color: #333;">${policia.apellido}, ${policia.nombre}</strong>
                                    ${policia.cin ? `<br><small class="text-muted">CIN: ${policia.cin}</small>` : ''}
                                </td>
                                <td style="color: #333;">${policia.grado}</td>
                                <td>
                                                    <span class="region-badge">
                                                        ${policia.region}
                                                    </span>
                                                </td>
                                <td>
                                    <span class="badge bg-${claseDisponibilidad}">
                                        ${policia.disponibilidad}
                                    </span>
                                </td>
                            </tr>
                        `;
                    });
                    
                    tbody.innerHTML = html;
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No se encontraron policías</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al cargar policías</td></tr>';
            });
        }
        function seleccionarPolicia(id, nombre, legajo, grado) {
            policiaSeleccionado = { id, nombre, legajo, grado };
            document.getElementById('nuevo_policia_id').value = id;
            document.getElementById('btn-confirmar-reemplazo').disabled = false;           
            document.querySelectorAll('#tbody-policias tr').forEach(tr => {
                tr.classList.remove('table-primary');
            });
            event.target.closest('tr').classList.add('table-primary');
        }
        
        function volverPaso1() {
            document.getElementById('paso2-seleccionar-policia').style.display = 'none';
            document.getElementById('paso1-seleccionar-guardia').style.display = 'block';
            guardiaSeleccionada = null;
            policiaSeleccionado = null;
            document.getElementById('nuevo_policia_id').value = '';
            document.getElementById('btn-confirmar-reemplazo').disabled = true;
        }
        
        document.getElementById('modalReemplazarPersonal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('paso2-seleccionar-policia').style.display = 'none';
            document.getElementById('paso1-seleccionar-guardia').style.display = 'block';
            document.getElementById('guardias-existentes').style.display = 'none';
            document.getElementById('fecha_busqueda').value = '';
            document.getElementById('buscar_policia').value = '';
            guardiaSeleccionada = null;
            policiaSeleccionado = null;
        });
        
        document.getElementById('formGenerarGuardiaSemanal').addEventListener('submit', function(e) {
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';
            submitButton.disabled = true;    
            setTimeout(function() {
                window.location.reload();
            }, 3000);
        });
        
        document.getElementById('searchInput').addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const secciones = document.querySelectorAll('.lugar-guardia-section');
            
            secciones.forEach(seccion => {
                const filas = seccion.querySelectorAll('tbody tr');
                let hayCoincidencias = false;
                
                filas.forEach(fila => {
                    const texto = fila.textContent.toLowerCase();
                    if (texto.includes(query)) {
                        fila.style.display = '';
                        hayCoincidencias = true;
                    } else {
                        fila.style.display = 'none';
                    }
                });
                if (query === '' || hayCoincidencias) {
                    seccion.style.display = 'block';
                } else {
                    seccion.style.display = 'none';
                }
            });
        });
    </script>
