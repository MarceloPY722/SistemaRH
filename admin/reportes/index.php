<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

// Generar reportes según el tipo seleccionado
$reporte_tipo = $_GET['tipo'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');

$reportes_data = [];

if ($reporte_tipo) {
    switch ($reporte_tipo) {
        case 'policias_activos':
            $reportes_data = $conn->query("
                SELECT p.nombre, p.apellido, p.cin, g.nombre as grado, 
                       p.comisionamiento, p.region, lg.nombre as lugar_guardia
                FROM policias p
                JOIN grados g ON p.grado_id = g.id
                LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
                WHERE p.activo = 1
                ORDER BY g.nivel_jerarquia ASC, p.apellido ASC
            ");
            break;
            
        case 'servicios_periodo':
            $stmt = $conn->prepare("
                SELECT s.nombre, s.fecha_servicio, s.descripcion,
                       CONCAT(p.nombre, ' ', p.apellido) as jefe_servicio,
                       g.nombre as grado_jefe
                FROM servicios s
                LEFT JOIN policias p ON s.jefe_servicio_id = p.id
                LEFT JOIN grados g ON p.grado_id = g.id
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
                JOIN grados g ON p.grado_id = g.id
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
                JOIN grados g ON p.grado_id = g.id
                LEFT JOIN lugares_guardias lguar ON p.lugar_guardia_id = lguar.id
                WHERE p.activo = 1
                ORDER BY lg.posicion ASC
            ");
            break;
    }
}
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
                        <div class="col-md-6 mb-4">
                            <div class="card report-card" onclick="location.href='?tipo=policias_activos'">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                    <h5>Policías Activos</h5>
                                    <p class="text-muted">Lista completa de policías en servicio activo</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card report-card" onclick="showDateModal('servicios_periodo')">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-alt fa-3x text-success mb-3"></i>
                                    <h5>Servicios por Período</h5>
                                    <p class="text-muted">Servicios programados en un rango de fechas</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card report-card" onclick="showDateModal('ausencias_periodo')">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-times fa-3x text-warning mb-3"></i>
                                    <h5>Ausencias por Período</h5>
                                    <p class="text-muted">Ausencias registradas en un rango de fechas</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card report-card" onclick="location.href='?tipo=guardias_rotacion'">
                                <div class="card-body text-center">
                                    <i class="fas fa-list-ol fa-3x text-info mb-3"></i>
                                    <h5>Lista de Guardias</h5>
                                    <p class="text-muted">Orden actual de rotación de guardias</p>
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
                                    'policias_activos' => 'Reporte de Policías Activos',
                                    'servicios_periodo' => 'Reporte de Servicios por Período',
                                    'ausencias_periodo' => 'Reporte de Ausencias por Período',
                                    'guardias_rotacion' => 'Reporte de Lista de Guardias'
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
                        <div class="card-body">
                            <?php if ($reportes_data && $reportes_data->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <?php 
                                            // Generar encabezados según el tipo de reporte
                                            switch ($reporte_tipo) {
                                                case 'policias_activos':
                                                    echo '<th>Nombre</th><th>Apellido</th><th>CIN</th><th>Grado</th><th>Comisionamiento</th><th>Región</th><th>Lugar de Guardia</th>';
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
                                            }
                                            ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $reportes_data->fetch_assoc()): ?>
                                        <tr>
                                            <?php 
                                            // Mostrar datos según el tipo de reporte
                                            switch ($reporte_tipo) {
                                                case 'policias_activos':
                                                    echo '<td>' . htmlspecialchars($row['nombre']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['apellido']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['cin']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['grado']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['comisionamiento']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['region']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['lugar_guardia'] ?? 'No asignado') . '</td>';
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentReportType = '';
        
        function showDateModal(tipo) {
            currentReportType = tipo;
            new bootstrap.Modal(document.getElementById('dateModal')).show();
        }
        
        document.getElementById('dateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const fechaInicio = formData.get('fecha_inicio');
            const fechaFin = formData.get('fecha_fin');
            
            window.location.href = `?tipo=${currentReportType}&fecha_inicio=${fechaInicio}&fecha_fin=${fechaFin}`;
        });
    </script>
</body>
</html>