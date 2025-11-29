<?php
require_once '../../session_config.php'; // Ngrok-compatible session configuration

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

// Obtener información del usuario actual
$stmt = $conn->prepare("SELECT nombre_completo, rol FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario_actual = $stmt->fetch();

// Obtener estadísticas generales
try {
    // 1. Estadísticas de Guardias por Lugar
    $sql_guardias = "
        SELECT 
            p.lugar_guardia_id,
            lg.nombre as lugar_guardia,
            COUNT(*) as total_policias,
            COUNT(CASE WHEN p.estado = 'DISPONIBLE' THEN 1 END) as disponibles,
            COUNT(CASE WHEN p.estado = 'NO DISPONIBLE' THEN 1 END) as no_disponibles
        FROM policias p
        LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
        WHERE p.activo = 1
        GROUP BY p.lugar_guardia_id, lg.nombre
    ";
    $stmt_guardias = $conn->prepare($sql_guardias);
    $stmt_guardias->execute();
    $estadisticas_guardias = $stmt_guardias->fetchAll();

    // 2. Estadísticas de Ausencias
    $sql_ausencias = "
        SELECT 
            MONTH(fecha_inicio) as mes,
            COUNT(*) as total_ausencias,
            COUNT(CASE WHEN estado = 'APROBADA' THEN 1 END) as aprobadas,
            COUNT(CASE WHEN estado = 'PENDIENTE' THEN 1 END) as pendientes,
            COUNT(CASE WHEN estado = 'RECHAZADA' THEN 1 END) as rechazadas
        FROM ausencias 
        WHERE YEAR(fecha_inicio) = YEAR(CURDATE())
        GROUP BY MONTH(fecha_inicio)
        ORDER BY mes
    ";
    $stmt_ausencias = $conn->prepare($sql_ausencias);
    $stmt_ausencias->execute();
    $estadisticas_ausencias = $stmt_ausencias->fetchAll();

    // 3. Estadísticas de Servicios
    $sql_servicios = "
        SELECT 
            ts.nombre as tipo_servicio,
            COUNT(*) as total_servicios,
            COUNT(CASE WHEN s.fecha_inicio >= CURDATE() THEN 1 END) as futuros,
            COUNT(CASE WHEN s.fecha_inicio < CURDATE() THEN 1 END) as pasados
        FROM servicios s
        INNER JOIN tipos_servicios ts ON s.tipo_servicio_id = ts.id
        WHERE YEAR(s.fecha_inicio) = YEAR(CURDATE())
        GROUP BY ts.nombre
    ";
    $stmt_servicios = $conn->prepare($sql_servicios);
    $stmt_servicios->execute();
    $estadisticas_servicios = $stmt_servicios->fetchAll();


    $sql_deshabilitados = "
        SELECT 
            p.id,
            p.legajo,
            p.nombre,
            p.apellido,
            p.updated_at AS fecha_deshabilitado,
            lg.nombre as lugar_guardia
        FROM policias p
        LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
        WHERE p.activo = 0
        ORDER BY p.updated_at DESC
    ";
    $stmt_deshabilitados = $conn->prepare($sql_deshabilitados);
    $stmt_deshabilitados->execute();
    $policias_deshabilitados = $stmt_deshabilitados->fetchAll();

    $sql_resumen = "
        SELECT 
            (SELECT COUNT(*) FROM policias WHERE activo = 1) as total_activos,
            (SELECT COUNT(*) FROM policias WHERE activo = 1 AND estado = 'DISPONIBLE') as disponibles,
            (SELECT COUNT(*) FROM policias WHERE activo = 1 AND estado = 'NO DISPONIBLE') as no_disponibles,
            (SELECT COUNT(*) FROM ausencias WHERE estado = 'APROBADA' AND fecha_fin >= CURDATE()) as ausencias_activas,
            (SELECT COUNT(*) FROM servicios WHERE fecha_inicio >= CURDATE()) as servicios_futuros
    ";
    $stmt_resumen = $conn->prepare($sql_resumen);
    $stmt_resumen->execute();
    $resumen_general = $stmt_resumen->fetch();

} catch (PDOException $e) {
    die("Error al obtener estadísticas: " . $e->getMessage());
}

// Configurar la página
$titulo = "Reportes del Sistema";
require_once '../inc/header.php';
?>

<style>
:root {
    --primary-color: #104c75;
    --secondary-color: #f8f9fa;
    --success-color: #28a745;
    --warning-color: #ffc107;
    --danger-color: #dc3545;
    --info-color: #17a2b8;
    --sidebar-bg: linear-gradient(135deg, #104c75 0%, #0d3d5f 100%);
}

.reports-container {
    margin-left: 250px;
    padding: 20px;
    transition: margin-left 0.3s ease;
}

.reports-header {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-card .icon {
    font-size: 2.5em;
    margin-bottom: 10px;
}

.stat-card .number {
    font-size: 1.6em;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 5px;
}

.stat-card .label {
    color: #666;
    font-size: 0.9em;
}

.chart-container {
    background: white;
    padding: 16px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 16px;
    height: 280px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.chart-title {
    text-align: center;
    margin: 0;
    color: var(--primary-color);
    font-weight: 600;
    font-size: 1rem;
}

.chart-canvas {
    width: 100% !important;
    height: 220px !important;
}

.table-container {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.table th {
    background-color: var(--primary-color);
    color: white;
    border: none;
}

.btn-primary {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-primary:hover {
    background-color: #0d3d5f;
    border-color: #0d3d5f;
}

.section-title {
    color: var(--primary-color);
    font-weight: bold;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .reports-container {
        margin-left: 0;
        padding: 10px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once '../inc/sidebar.php'; ?>
<div class="reports-container">
    <div class="reports-header">
        <h1><i class="fas fa-chart-bar me-2"></i>Reportes del Sistema</h1>
        <p class="text-muted">Panel de estadísticas y análisis del Sistema de Recursos Humanos</p>
    </div>

    <!-- Resumen General -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon text-primary"><i class="fas fa-users"></i></div>
            <div class="number"><?php echo $resumen_general['total_activos']; ?></div>
            <div class="label">Total de Policías Activos</div>
        </div>
        <div class="stat-card">
            <div class="icon text-success"><i class="fas fa-user-check"></i></div>
            <div class="number"><?php echo $resumen_general['disponibles']; ?></div>
            <div class="label">Policías Disponibles</div>
        </div>
        <div class="stat-card">
            <div class="icon text-warning"><i class="fas fa-user-times"></i></div>
            <div class="number"><?php echo $resumen_general['no_disponibles']; ?></div>
            <div class="label">Policías No Disponibles</div>
        </div>
        <div class="stat-card">
            <div class="icon text-info"><i class="fas fa-calendar-alt"></i></div>
            <div class="number"><?php echo $resumen_general['ausencias_activas']; ?></div>
            <div class="label">Ausencias Activas</div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="row">
        <div class="col-md-6">
            <div class="chart-container">
                <h5 class="chart-title">Distribución de Guardias por Lugar</h5>
                <canvas id="guardiasZonaChart" class="chart-canvas"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-container">
                <h5 class="chart-title">Estado de Disponibilidad</h5>
                <canvas id="disponibilidadChart" class="chart-canvas"></canvas>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6">
            <div class="chart-container">
                <h5 class="chart-title">Tendencia de Ausencias por Mes</h5>
                <canvas id="ausenciasMesChart" class="chart-canvas"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-container">
                <h5 class="chart-title">Tipos de Servicios</h5>
                <canvas id="serviciosChart" class="chart-canvas"></canvas>
            </div>
        </div>
    </div>

    <!-- Policías Deshabilitados -->
    <div class="table-container mt-4">
        <h5 class="section-title">
            <i class="fas fa-user-slash me-2"></i>
            Policías Deshabilitados
        </h5>
        <?php if (count($policias_deshabilitados) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Legajo</th>
                            <th>Nombre</th>
                            <th>Apellido</th>
                            <th>Lugar de Guardia</th>
                            <th>Fecha de Deshabilitación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($policias_deshabilitados as $policia): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($policia['legajo']); ?></td>
                            <td><?php echo htmlspecialchars($policia['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($policia['apellido']); ?></td>
                            <td><?php echo htmlspecialchars($policia['lugar_guardia'] ?? 'No asignado'); ?></td>
                            <td><?php echo $policia['fecha_deshabilitado'] ? date('d/m/Y', strtotime($policia['fecha_deshabilitado'])) : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No hay policías deshabilitados actualmente.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Datos para los gráficos
const guardiasData = {
    labels: [<?php foreach ($estadisticas_guardias as $g) echo "'" . htmlspecialchars($g['lugar_guardia']) . "',"; ?>],
    datasets: [{
        data: [<?php foreach ($estadisticas_guardias as $g) echo $g['total_policias'].","; ?>],
        backgroundColor: [
            '#104c75',
            '#0d3d5f',
            '#1a5a8a',
            '#2677a5',
            '#3294c0',
            '#3eb1db',
            '#4acef6',
            '#56ebff'
        ]
    }]
};

const disponibilidadData = {
    labels: ['Disponibles', 'No Disponibles'],
    datasets: [{
        data: [<?php echo $resumen_general['disponibles'] . ',' . $resumen_general['no_disponibles']; ?>],
        backgroundColor: ['#28a745', '#dc3545']
    }]
};

const ausenciasLabels = [<?php 
$meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
foreach ($estadisticas_ausencias as $a) echo "'" . $meses[$a['mes']-1] . "',"; 
?>];

const ausenciasData = {
    labels: ausenciasLabels,
    datasets: [{
        label: 'Total Ausencias',
        data: [<?php foreach ($estadisticas_ausencias as $a) echo $a['total_ausencias'].","; ?>],
        borderColor: '#104c75',
        backgroundColor: 'rgba(16, 76, 117, 0.1)',
        tension: 0.4
    }, {
        label: 'Aprobadas',
        data: [<?php foreach ($estadisticas_ausencias as $a) echo $a['aprobadas'].","; ?>],
        borderColor: '#28a745',
        backgroundColor: 'rgba(40, 167, 69, 0.1)',
        tension: 0.4
    }]
};

const serviciosLabels = [<?php foreach ($estadisticas_servicios as $s) echo "'" . htmlspecialchars($s['tipo_servicio']) . "',"; ?>];
const serviciosData = {
    labels: serviciosLabels,
    datasets: [{
        data: [<?php foreach ($estadisticas_servicios as $s) echo $s['total_servicios'].","; ?>],
        backgroundColor: [
            '#104c75',
            '#0d3d5f',
            '#1a5a8a',
            '#2677a5',
            '#3294c0'
        ]
    }]
};

// Crear gráficos
window.addEventListener('DOMContentLoaded', function() {
    // Gráfico de Guardias por Zona
    const guardiasZonaCtx = document.getElementById('guardiasZonaChart').getContext('2d');
    new Chart(guardiasZonaCtx, {
        type: 'doughnut',
        data: guardiasData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 10, font: { size: 12 } }
                }
            }
        }
    });

    // Gráfico de Disponibilidad
    const disponibilidadCtx = document.getElementById('disponibilidadChart').getContext('2d');
    new Chart(disponibilidadCtx, {
        type: 'pie',
        data: disponibilidadData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 10, font: { size: 12 } }
                }
            }
        }
    });

    // Gráfico de Ausencias por Mes
    const ausenciasMesCtx = document.getElementById('ausenciasMesChart').getContext('2d');
    new Chart(ausenciasMesCtx, {
        type: 'line',
        data: ausenciasData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 10, font: { size: 12 } }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { font: { size: 11 } }
                },
                x: {
                    ticks: { font: { size: 11 } }
                }
            }
        }
    });

    // Gráfico de Servicios
    const serviciosCtx = document.getElementById('serviciosChart').getContext('2d');
    new Chart(serviciosCtx, {
        type: 'bar',
        data: serviciosData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { font: { size: 11 } }
                },
                x: {
                    ticks: { font: { size: 11 } }
                }
            }
        }
    });
});
</script>
