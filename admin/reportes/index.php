<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

// Obtener lugares de guardia para el filtro
$lugares_guardias = $conn->query("SELECT id, nombre FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");

// Generar reportes según el tipo seleccionado
$reporte_tipo = $_GET['tipo'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$fecha_especifica = $_GET['fecha_especifica'] ?? '';
$dia_semana = $_GET['dia_semana'] ?? '';

$reportes_data = [];

if ($reporte_tipo) {
    switch ($reporte_tipo) {
        case 'policias_deshabilitados':
            $reportes_data = $conn->query("
                SELECT p.legajo, p.nombre, p.apellido, p.cin, 
                       g.nombre as grado, p.comisionamiento, 
                       r.nombre as region, lg.nombre as lugar_guardia, 
                       p.telefono, p.updated_at as fecha_deshabilitacion
                FROM policias p
                LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
                LEFT JOIN grados g ON tg.grado_id = g.id
                LEFT JOIN regiones r ON p.region_id = r.id
                LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
                WHERE p.activo = 0
                ORDER BY p.updated_at DESC
            ");
            break;
            
        case 'servicios_periodo':
            $stmt = $conn->prepare("
                SELECT s.nombre, s.fecha_servicio, s.descripcion,
                       CONCAT(p.nombre, ' ', p.apellido) as jefe_servicio,
                       g.nombre as grado_jefe
                FROM servicios s
                LEFT JOIN policias p ON s.jefe_servicio_id = p.id
                LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
                LEFT JOIN grados g ON tg.grado_id = g.id
                WHERE s.fecha_servicio BETWEEN ? AND ?
                ORDER BY s.fecha_servicio DESC
            ");
            $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
            $stmt->execute();
            $reportes_data = $stmt->get_result();
            break;
            
        case 'ausencias_periodo':
            $stmt = $conn->prepare("
                SELECT CONCAT(p.nombre, ' ', p.apellido) as policia,
                       p.cin, g.nombre as grado, ta.nombre as tipo_ausencia,
                       a.fecha_inicio, a.fecha_fin, a.estado, a.descripcion
                FROM ausencias a
                JOIN policias p ON a.policia_id = p.id
                LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
                LEFT JOIN grados g ON tg.grado_id = g.id
                JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id
                WHERE a.fecha_inicio BETWEEN ? AND ?
                ORDER BY a.fecha_inicio DESC
            ");
            $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
            $stmt->execute();
            $reportes_data = $stmt->get_result();
            break;
            
        case 'guardias_rotacion':
            $reportes_data = $conn->query("
                SELECT lg.posicion, CONCAT(p.nombre, ' ', p.apellido) as policia,
                       p.cin, g.nombre as grado, lg.ultima_guardia_fecha,
                       lguar.nombre as lugar_guardia
                FROM lista_guardias lg
                JOIN policias p ON lg.policia_id = p.id
                LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
                LEFT JOIN grados g ON tg.grado_id = g.id
                LEFT JOIN lugares_guardias lguar ON p.lugar_guardia_id = lguar.id
                WHERE p.activo = 1
                ORDER BY lg.posicion ASC
            ");
            break;
            
        case 'guardias_realizadas':
            // Determinar si usar fecha específica o rango
            if ($fecha_especifica) {
                $stmt = $conn->prepare("
                    SELECT gr.fecha_inicio as fecha_guardia, 
                           CONCAT(p.nombre, ' ', p.apellido) as policia,
                           p.cin, g.nombre as grado,
                           lg.nombre as lugar_guardia,
                           lg.id as lugar_guardia_id,
                           gr.created_at as fecha_registro
                    FROM guardias_realizadas gr
                    JOIN policias p ON gr.policia_id = p.id
                    LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
                    LEFT JOIN grados g ON tg.grado_id = g.id
                    JOIN lugares_guardias lg ON gr.lugar_guardia_id = lg.id
                    WHERE DATE(gr.fecha_inicio) = ?
                    ORDER BY lg.nombre ASC, gr.fecha_inicio DESC
                ");
                $stmt->bind_param("s", $fecha_especifica);
            } else {
                $stmt = $conn->prepare("
                    SELECT gr.fecha_inicio as fecha_guardia, 
                           CONCAT(p.nombre, ' ', p.apellido) as policia,
                           p.cin, g.nombre as grado,
                           lg.nombre as lugar_guardia,
                           lg.id as lugar_guardia_id,
                           gr.created_at as fecha_registro
                    FROM guardias_realizadas gr
                    JOIN policias p ON gr.policia_id = p.id
                    LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
                    LEFT JOIN grados g ON tg.grado_id = g.id
                    JOIN lugares_guardias lg ON gr.lugar_guardia_id = lg.id
                    WHERE gr.fecha_inicio BETWEEN ? AND ?
                    ORDER BY lg.nombre ASC, gr.fecha_inicio DESC
                ");
                $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
            }
            $stmt->execute();
            $reportes_data = $stmt->get_result();
            break;
            
        case 'guardias_por_dia':
            if ($dia_semana) {
                $stmt = $conn->prepare("
                    SELECT 
                        gr.fecha_inicio,
                        gr.fecha_fin,
                        p.nombre,
                        p.apellido,
                        p.telefono,
                        g.abreviatura as grado_abreviatura,
                        g.nombre as grado,
                        r.nombre as region,
                        lg.nombre as lugar_guardia,
                        DAYOFWEEK(gr.fecha_inicio) as dia_semana_num
                    FROM guardias_realizadas gr
                    JOIN policias p ON gr.policia_id = p.id
                    LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
                    LEFT JOIN grados g ON tg.grado_id = g.id
                    LEFT JOIN regiones r ON p.region_id = r.id
                    LEFT JOIN lugares_guardias lg ON gr.lugar_guardia_id = lg.id
                    WHERE DAYOFWEEK(gr.fecha_inicio) = ?
                    ORDER BY gr.fecha_inicio DESC, lg.nombre, p.apellido, p.nombre
                ");
                $stmt->bind_param('i', $dia_semana);
                $stmt->execute();
                $reportes_data = $stmt->get_result();
            }
            break;
            
        case 'ausentes_activos':
            $reportes_data = $conn->query("
                SELECT CONCAT(p.nombre, ' ', p.apellido) as policia,
                       p.cin, g.nombre as grado, ta.nombre as tipo_ausencia,
                       a.fecha_inicio, a.fecha_fin, a.descripcion,
                       DATEDIFF(COALESCE(a.fecha_fin, CURDATE()), a.fecha_inicio) + 1 as dias_ausencia
                FROM ausencias a
                JOIN policias p ON a.policia_id = p.id
                LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
                LEFT JOIN grados g ON tg.grado_id = g.id
                JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id
                WHERE a.estado = 'APROBADA' 
                AND (a.fecha_fin IS NULL OR a.fecha_fin >= CURDATE())
                AND a.fecha_inicio <= CURDATE()
                ORDER BY a.fecha_inicio DESC
            ");
            break;
    }
}

// Verificar si hay guardias realizadas para el reporte por día
$tiene_guardias = false;
if ($reporte_tipo == 'guardias_por_dia') {
    $sql_verificar = "SELECT COUNT(*) as total FROM guardias_realizadas";
    $result_verificar = $conn->query($sql_verificar);
    $row = $result_verificar->fetch_assoc();
    $tiene_guardias = $row ? $row['total'] > 0 : false;
}

$dias_semana = [
    1 => 'Domingo',
    2 => 'Lunes', 
    3 => 'Martes',
    4 => 'Miércoles',
    5 => 'Jueves',
    6 => 'Viernes',
    7 => 'Sábado'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema RH</title>
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
        .report-card {
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .report-card:hover {
            transform: translateY(-5px);
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .navbar, .sidebar {
                display: none !important;
            }
            .main-content {
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>
   
    <div class="container-fluid">
        <div class="row">
            <div class="no-print">
                <?php include '../inc/sidebar.php'; ?>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <h1 class="page-title">
                        <i class="fas fa-chart-bar"></i> Reportes del Sistema
                    </h1>

                    <?php if (!$reporte_tipo): ?>
                    <!-- Selección de Reportes -->
                    <div class="row">
                          <div class="col-md-4 mb-4">
                            <div class="card report-card" onclick="location.href='orden_dia.php'">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-word fa-3x text-primary mb-3"></i>
                                    <h5>Orden del Día</h5>
                                    <p class="text-muted">Generar orden del día con personal de guardia</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card report-card" onclick="location.href='?tipo=policias_deshabilitados'">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                                    <h5>Policías Deshabilitados</h5>
                                    <p class="text-muted">Personal que ha sido deshabilitado del sistema</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card report-card" onclick="location.href='?tipo=ausentes_activos'">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-times fa-3x text-danger mb-3"></i>
                                    <h5>Ausentes Actuales</h5>
                                    <p class="text-muted">Personal actualmente ausente del servicio</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card report-card" onclick="location.href='?tipo=guardias_rotacion'">
                                <div class="card-body text-center">
                                    <i class="fas fa-list-ol fa-3x text-info mb-3"></i>
                                    <h5>Lista de Guardias</h5>
                                    <p class="text-muted">Orden actual de rotación de guardias</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card report-card" onclick="location.href='guardias_realizadas.php'">
                                <div class="card-body text-center">
                                    <i class="fas fa-shield-alt fa-3x text-success mb-3"></i>
                                    <h5>Guardias Realizadas</h5>
                                    <p class="text-muted">Historial detallado de guardias por fecha y lugar</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card report-card" onclick="showDayModal('guardias_por_dia')">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-day fa-3x text-purple mb-3"></i>
                                    <h5>Guardias por Día</h5>
                                    <p class="text-muted">Consultar guardias por día de la semana</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card report-card" onclick="showDateModal('servicios_periodo')">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-alt fa-3x text-warning mb-3"></i>
                                    <h5>Servicios por Período</h5>
                                    <p class="text-muted">Servicios programados en un rango de fechas</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card report-card" onclick="showDateModal('ausencias_periodo')">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-times fa-3x text-secondary mb-3"></i>
                                    <h5>Ausencias por Período</h5>
                                    <p class="text-muted">Ausencias registradas en un rango de fechas</p>
                                </div>
                            </div>
                        </div>
                      
                    </div>
                    <?php else: ?>
                    <!-- Mostrar Reporte -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <?php 
                                $titulos = [
                                    'policias_deshabilitados' => 'Reporte de Policías Deshabilitados',
                                    'servicios_periodo' => 'Reporte de Servicios por Período',
                                    'ausencias_periodo' => 'Reporte de Ausencias por Período',
                                    'guardias_rotacion' => 'Reporte de Lista de Guardias',
                                    'guardias_realizadas' => 'Reporte de Guardias Realizadas',
                                    'guardias_por_dia' => 'Reporte de Guardias por Día de la Semana',
                                    'ausentes_activos' => 'Reporte de Ausentes Actuales'
                                ];
                                echo $titulos[$reporte_tipo] ?? 'Reporte';
                                ?>
                            </h5>
                            <div class="no-print">
                                <button onclick="window.print()" class="btn btn-primary me-2">
                                    <i class="fas fa-print"></i> Imprimir
                                </button>
                                <a href="?" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Volver
                                </a>
                            </div>
                        </div>
                        
                        <!-- Buscador y Filtros -->
                        <div class="card-body border-bottom no-print">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        <input type="text" class="form-control" id="searchInput" placeholder="Buscar personal por nombre, apellido, CIN, grado...">
                                        <button class="btn btn-outline-secondary" type="button" id="clearSearch" style="display: none;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted" id="searchInfo"></small>
                                </div>
                                <?php if (in_array($reporte_tipo, ['guardias_rotacion', 'guardias_realizadas', 'policias_deshabilitados'])): ?>
                                <div class="col-md-4">
                                    <select class="form-select" id="filtroLugarGuardia">
                                        <option value="">Todos los lugares de guardia</option>
                                        <?php 
                                        if ($lugares_guardias && $lugares_guardias->num_rows > 0) {
                                            $lugares_guardias->data_seek(0);
                                            while ($lugar = $lugares_guardias->fetch_assoc()): 
                                        ?>
                                        <option value="<?php echo htmlspecialchars($lugar['nombre']); ?>"><?php echo htmlspecialchars($lugar['nombre']); ?></option>
                                        <?php 
                                            endwhile;
                                        }
                                        ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <div class="col-md-2">
                                    <button class="btn btn-outline-secondary w-100" id="limpiarFiltros">
                                        <i class="fas fa-eraser"></i> Limpiar
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($reporte_tipo == 'guardias_por_dia'): ?>
                                <?php if (!$tiene_guardias): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        No hay guardias generadas en el sistema. Primero debes generar guardias semanales.
                                    </div>
                                <?php elseif (!$dia_semana): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        Selecciona un día de la semana para consultar las guardias.
                                    </div>
                                <?php elseif ($reportes_data && $reportes_data->num_rows > 0): ?>
                                    <div class="alert alert-info">
                                        <strong>Mostrando guardias para: <?= $dias_semana[$dia_semana] ?></strong>
                                        <br>Total de guardias encontradas: <?= $reportes_data->num_rows ?>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Personal</th>
                                                    <th>Teléfono</th>
                                                    <th>Región</th>
                                                    <th>Lugar de Guardia</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($row = $reportes_data->fetch_assoc()): ?>
                                                <?php 
                                                $nombre_completo = '';
                                                if (!empty($row['grado_abreviatura'])) {
                                                    $nombre_completo = $row['grado_abreviatura'] . ' ';
                                                }
                                                $nombre_completo .= $row['apellido'] . ', ' . $row['nombre'];
                                                $data_search = strtolower($nombre_completo . ' ' . $row['telefono'] . ' ' . $row['region'] . ' ' . $row['lugar_guardia']);
                                                ?>
                                                <tr class="reporte-row" data-search="<?= htmlspecialchars($data_search) ?>">
                                                    <td><?= date('d/m/Y', strtotime($row['fecha_inicio'])) ?></td>
                                                    <td><?= htmlspecialchars($nombre_completo) ?></td>
                                                    <td><?= $row['telefono'] ?: 'No registrado' ?></td>
                                                    <td><?= htmlspecialchars($row['region']) ?></td>
                                                    <td><?= htmlspecialchars($row['lugar_guardia']) ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        No se encontraron guardias para el día <?= $dias_semana[$dia_semana] ?>.
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($reportes_data && $reportes_data->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <?php 
                                            // Generar encabezados según el tipo de reporte
                                            switch ($reporte_tipo) {
                                                case 'policias_deshabilitados':
                                                    echo '<th>Legajo</th>';
                                                    echo '<th>Nombre</th>';
                                                    echo '<th>Apellido</th>';
                                                    echo '<th>CIN</th>';
                                                    echo '<th>Grado</th>';
                                                    echo '<th>Comisionamiento</th>';
                                                    echo '<th>Región</th>';
                                                    echo '<th>Lugar Guardia</th>';
                                                    echo '<th>Teléfono</th>';
                                                    echo '<th>Fecha Deshabilitación</th>';
                                                    break;
                                                case 'servicios_periodo':
                                                    echo '<th>Servicio</th><th>Fecha</th><th>Descripción</th><th>Jefe de Servicio</th><th>Grado</th>';
                                                    break;
                                                case 'ausencias_periodo':
                                                    echo '<th>Policía</th><th>CIN</th><th>Grado</th><th>Tipo</th><th>Fecha Inicio</th><th>Fecha Fin</th><th>Estado</th><th>Descripción</th>';
                                                    break;
                                                case 'guardias_rotacion':
                                                    echo '<th>Posición</th><th>Policía</th><th>CIN</th><th>Grado</th><th>Última Guardia</th><th>Lugar</th>';
                                                    break;
                                                case 'guardias_realizadas':
                                                    echo '<th>Fecha Guardia</th><th>Policía</th><th>CIN</th><th>Grado</th><th>Lugar</th><th>Fecha Registro</th>';
                                                    break;
                                                case 'ausentes_activos':
                                                    echo '<th>Policía</th><th>CIN</th><th>Grado</th><th>Tipo Ausencia</th><th>Fecha Inicio</th><th>Fecha Fin</th><th>Días</th><th>Descripción</th>';
                                                    break;
                                            }
                                            ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $reportes_data->fetch_assoc()): ?>
                                        <?php 
                                        // Generar data-search según el tipo de reporte
                                        $data_search = '';
                                        switch ($reporte_tipo) {
                                            case 'policias_deshabilitados':
                                                $data_search = strtolower($row['legajo'] . ' ' . $row['nombre'] . ' ' . $row['apellido'] . ' ' . $row['cin'] . ' ' . $row['grado'] . ' ' . $row['comisionamiento'] . ' ' . $row['region'] . ' ' . ($row['lugar_guardia'] ?? 'no asignado') . ' ' . $row['telefono']);
                                                break;
                                            case 'servicios_periodo':
                                                $data_search = strtolower($row['nombre'] . ' ' . ($row['jefe_servicio'] ?? '') . ' ' . ($row['grado_jefe'] ?? '') . ' ' . $row['descripcion']);
                                                break;
                                            case 'ausencias_periodo':
                                                $data_search = strtolower($row['policia'] . ' ' . $row['cin'] . ' ' . $row['grado'] . ' ' . $row['tipo_ausencia'] . ' ' . $row['estado'] . ' ' . $row['descripcion']);
                                                break;
                                            case 'guardias_rotacion':
                                                $data_search = strtolower($row['policia'] . ' ' . $row['cin'] . ' ' . $row['grado'] . ' ' . ($row['lugar_guardia'] ?? 'no asignado'));
                                                break;
                                            case 'guardias_realizadas':
                                                $data_search = strtolower($row['policia'] . ' ' . $row['cin'] . ' ' . $row['grado'] . ' ' . $row['lugar_guardia']);
                                                break;
                                            case 'ausentes_activos':
                                                $data_search = strtolower($row['policia'] . ' ' . $row['cin'] . ' ' . $row['grado'] . ' ' . $row['tipo_ausencia'] . ' ' . $row['descripcion']);
                                                break;
                                        }
                                        ?>
                                        <tr class="reporte-row" data-search="<?= htmlspecialchars($data_search) ?>">
                                            <?php 
                                            // Mostrar datos según el tipo de reporte
                                            switch ($reporte_tipo) {
                                                case 'policias_deshabilitados':
                                                    echo '<td>' . htmlspecialchars($row['legajo']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['nombre']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['apellido']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['cin']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['grado']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['comisionamiento']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['region']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['lugar_guardia'] ?? 'No asignado') . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['telefono']) . '</td>';
                                                    echo '<td>' . date('d/m/Y', strtotime($row['fecha_deshabilitacion'])) . '</td>';
                                                    break;
                                                case 'servicios_periodo':
                                                    echo '<td>' . htmlspecialchars($row['nombre']) . '</td>';
                                                    echo '<td>' . date('d/m/Y', strtotime($row['fecha_servicio'])) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['descripcion']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['jefe_servicio'] ?? 'No asignado') . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['grado_jefe'] ?? '-') . '</td>';
                                                    break;
                                                case 'ausencias_periodo':
                                                    echo '<td>' . htmlspecialchars($row['policia']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['cin']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['grado']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['tipo_ausencia']) . '</td>';
                                                    echo '<td>' . date('d/m/Y', strtotime($row['fecha_inicio'])) . '</td>';
                                                    echo '<td>' . ($row['fecha_fin'] ? date('d/m/Y', strtotime($row['fecha_fin'])) : 'Indefinida') . '</td>';
                                                    echo '<td><span class="badge bg-' . ($row['estado'] == 'APROBADA' ? 'success' : ($row['estado'] == 'RECHAZADA' ? 'danger' : 'warning')) . '">' . $row['estado'] . '</span></td>';
                                                    echo '<td>' . htmlspecialchars($row['descripcion']) . '</td>';
                                                    break;
                                                case 'guardias_rotacion':
                                                    echo '<td><span class="badge bg-primary">' . $row['posicion'] . '</span></td>';
                                                    echo '<td>' . htmlspecialchars($row['policia']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['cin']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['grado']) . '</td>';
                                                    echo '<td>' . ($row['ultima_guardia_fecha'] ? date('d/m/Y', strtotime($row['ultima_guardia_fecha'])) : 'Nunca') . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['lugar_guardia'] ?? 'No asignado') . '</td>';
                                                    break;
                                                case 'guardias_realizadas':
                                                    echo '<td>' . date('d/m/Y', strtotime($row['fecha_guardia'])) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['policia']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['cin']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['grado']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['lugar_guardia']) . '</td>';
                                                    echo '<td>' . date('d/m/Y H:i', strtotime($row['fecha_registro'])) . '</td>';
                                                    break;
                                                case 'ausentes_activos':
                                                    echo '<td>' . htmlspecialchars($row['policia']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['cin']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['grado']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['tipo_ausencia']) . '</td>';
                                                    echo '<td>' . date('d/m/Y', strtotime($row['fecha_inicio'])) . '</td>';
                                                    echo '<td>' . ($row['fecha_fin'] ? date('d/m/Y', strtotime($row['fecha_fin'])) : 'Indefinida') . '</td>';
                                                    echo '<td><span class="badge bg-info">' . $row['dias_ausencia'] . '</span></td>';
                                                    echo '<td>' . htmlspecialchars($row['descripcion']) . '</td>';
                                                    break;
                                            }
                                            ?>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No se encontraron datos para este reporte.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para selección de fechas -->
    <div class="modal fade" id="dateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Seleccionar Período</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="dateForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Fecha Inicio</label>
                                <input type="date" class="form-control" name="fecha_inicio" value="<?php echo date('Y-m-01'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha Fin</label>
                                <input type="date" class="form-control" name="fecha_fin" value="<?php echo date('Y-m-t'); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Generar Reporte</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para selección de día de la semana -->
    <div class="modal fade" id="dayModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Seleccionar Día de la Semana</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="dayForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12">
                                <label class="form-label">Día de la Semana</label>
                                <select name="dia_semana" class="form-select" required>
                                    <option value="">-- Seleccionar día --</option>
                                    <?php foreach ($dias_semana as $num => $nombre): ?>
                                        <option value="<?= $num ?>"><?= $nombre ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Consultar Guardias</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentReportType = '';
        
        function showDateModal(tipo) {
            currentReportType = tipo;
            new bootstrap.Modal(document.getElementById('dateModal')).show();
        }
        
        function showDayModal(tipo) {
            currentReportType = tipo;
            new bootstrap.Modal(document.getElementById('dayModal')).show();
        }
        
        document.getElementById('dateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const fechaInicio = formData.get('fecha_inicio');
            const fechaFin = formData.get('fecha_fin');
            
            window.location.href = `?tipo=${currentReportType}&fecha_inicio=${fechaInicio}&fecha_fin=${fechaFin}`;
        });
        
        document.getElementById('dayForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const diaSemana = formData.get('dia_semana');
            
            window.location.href = `?tipo=${currentReportType}&dia_semana=${diaSemana}`;
        });
        
        // Funcionalidad del buscador en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const clearButton = document.getElementById('clearSearch');
            const searchInfo = document.getElementById('searchInfo');
            const filtroLugarGuardia = document.getElementById('filtroLugarGuardia');
            const limpiarFiltros = document.getElementById('limpiarFiltros');
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase().trim();
                    
                    if (searchTerm.length > 0) {
                        clearButton.style.display = 'block';
                    } else {
                        clearButton.style.display = 'none';
                    }
                    
                    aplicarFiltros();
                });
                
                clearButton.addEventListener('click', function() {
                    searchInput.value = '';
                    this.style.display = 'none';
                    aplicarFiltros();
                    searchInput.focus();
                });
            }
            
            if (filtroLugarGuardia) {
                filtroLugarGuardia.addEventListener('change', aplicarFiltros);
            }
            
            if (limpiarFiltros) {
                limpiarFiltros.addEventListener('click', function() {
                    if (searchInput) {
                        searchInput.value = '';
                        clearButton.style.display = 'none';
                    }
                    if (filtroLugarGuardia) {
                        filtroLugarGuardia.value = '';
                    }
                    aplicarFiltros();
                });
            }
        });
        
        function aplicarFiltros() {
            const searchInput = document.getElementById('searchInput');
            const filtroLugarGuardia = document.getElementById('filtroLugarGuardia');
            const searchInfo = document.getElementById('searchInfo');
            
            const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const lugarSeleccionado = filtroLugarGuardia ? filtroLugarGuardia.value.toLowerCase() : '';
            
            const filas = document.querySelectorAll('.reporte-row');
            let filasVisibles = 0;
            
            filas.forEach(function(fila) {
                const datosCompletos = fila.getAttribute('data-search');
                let mostrar = true;
                
                // Filtro por búsqueda
                if (searchTerm && !datosCompletos.includes(searchTerm)) {
                    mostrar = false;
                }
                
                // Filtro por lugar de guardia
                if (lugarSeleccionado && !datosCompletos.includes(lugarSeleccionado)) {
                    mostrar = false;
                }
                
                if (mostrar) {
                    fila.style.display = '';
                    filasVisibles++;
                } else {
                    fila.style.display = 'none';
                }
            });
            
            // Actualizar información de búsqueda
            if (searchInfo) {
                if (searchTerm || lugarSeleccionado) {
                    if (filasVisibles === 0) {
                        searchInfo.textContent = 'No se encontraron resultados';
                        searchInfo.className = 'text-warning';
                    } else {
                        searchInfo.textContent = `${filasVisibles} resultado(s) encontrado(s)`;
                        searchInfo.className = 'text-success';
                    }
                } else {
                    searchInfo.textContent = '';
                    searchInfo.className = 'text-muted';
                }
            }
        }
    </script>
</body>
</html>