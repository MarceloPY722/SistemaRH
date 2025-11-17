<?php
session_start();

// Verificar autenticación y rol de superadmin
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../cnx/db_connect.php';
require_once '../inc/auditoria_functions.php';

// Verificar rol de superadmin
$stmt = $conn->prepare("SELECT rol FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();

if ($usuario['rol'] !== 'SUPERADMIN') {
    header('Location: ../../index.php');
    exit();
}

$filtro_usuario = $_GET['usuario'] ?? '';
$filtro_accion = $_GET['accion'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';

$query = "SELECT a.*, u.nombre_usuario, u.nombre_completo, u.rol 
          FROM auditoria_sistema a 
          LEFT JOIN usuarios u ON a.usuario_id = u.id 
          WHERE 1=1";
$params = [];

if ($filtro_usuario) {
    $query .= " AND (u.nombre_usuario LIKE ? OR u.nombre_completo LIKE ?)";
    $params[] = "%$filtro_usuario%";
    $params[] = "%$filtro_usuario%";
}

if ($filtro_accion) {
    $query .= " AND a.accion LIKE ?";
    $params[] = "%$filtro_accion%";
}

if ($filtro_fecha_desde) {
    $query .= " AND a.creado_en >= ?";
    $params[] = $filtro_fecha_desde;
}

if ($filtro_fecha_hasta) {
    $query .= " AND a.creado_en <= ?";
    $params[] = $filtro_fecha_hasta . ' 23:59:59';
}

$query .= " ORDER BY a.creado_en DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$registros_auditoria = $stmt->fetchAll();

// Obtener estadísticas
$total_registros = count($registros_auditoria);
$acciones_unicas = $conn->query("SELECT DISTINCT accion FROM auditoria_sistema ORDER BY accion")->fetchAll();

// Exportar a CSV
if (isset($_GET['exportar']) && $_GET['exportar'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=auditoria_sistema_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    // Forzar a Excel a interpretar como UTF-8 (evita "AcciÃ³n")
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    fputcsv($output, ['Fecha', 'Usuario', 'Acción', 'Tabla', 'Registro ID', 'IP', 'User Agent']);
    
    foreach ($registros_auditoria as $registro) {
        fputcsv($output, [
            $registro['creado_en'],
            $registro['nombre_usuario'] . ' (' . $registro['nombre_completo'] . ')',
            $registro['accion'],
            $registro['tabla_afectada'],
            $registro['registro_id'],
            $registro['ip_address'],
            $registro['user_agent']
        ]);
    }
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría del Sistema - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Esquema de colores del sistema */
        .system-primary { background: var(--primary-color, #104c75) !important; }
        .system-secondary { background: var(--secondary-color, #0d3d5c) !important; }
        .system-accent { background: linear-gradient(135deg, var(--primary-color, #104c75), var(--secondary-color, #0d3d5c)) !important; }

        .btn-system {
            background: var(--primary-color, #104c75);
            border-color: var(--primary-color, #104c75);
            color: #fff;
        }
        .btn-system:hover { background: var(--secondary-color, #0d3d5c); border-color: var(--secondary-color, #0d3d5c); color: #fff; }
        .btn-outline-system { border-color: var(--primary-color, #104c75); color: var(--primary-color, #104c75); }
        .btn-outline-system:hover { background: var(--primary-color, #104c75); color: #fff; }

        .system-badge { background: var(--primary-color, #104c75); }

        .audit-card {
            border: 2px solid var(--primary-color, #104c75);
            border-radius: 12px;
            box-shadow: var(--shadow, 0 4px 20px rgba(0,0,0,0.08));
        }
        .audit-row {
            border-left: 4px solid var(--primary-color, #104c75);
            transition: all 0.3s;
        }
        .audit-row:hover {
            background-color: rgba(16, 76, 117, 0.06);
            border-left-color: var(--secondary-color, #0d3d5c);
        }
        .table-responsive { max-height: 600px; }
        .badge-action { font-size: 0.75em; }

        .table-header-system th {
            background: var(--secondary-color, #0d3d5c);
            color: #fff;
            border-color: var(--secondary-color, #0d3d5c);
        }
    </style>
</head>
<body>
    <?php include '../inc/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../inc/sidebar.php'; ?>
            
            <main class="col-md-9 col-lg-10 px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-clipboard-list me-2"></i>Auditoría del Sistema</h2>
                    <span class="badge system-badge"><?php echo $total_registros; ?> registros</span>
                </div>

                <!-- Filtros -->
                <div class="card audit-card mb-4">
                    <div class="card-header system-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Usuario</label>
                                <input type="text" name="usuario" class="form-control" value="<?php echo htmlspecialchars($filtro_usuario); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Acción</label>
                                <select name="accion" class="form-select">
                                    <option value="">Todas las acciones</option>
                                    <?php foreach ($acciones_unicas as $accion): ?>
                                    <option value="<?php echo htmlspecialchars($accion['accion']); ?>" <?php echo $filtro_accion === $accion['accion'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($accion['accion']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Desde</label>
                                <input type="date" name="fecha_desde" class="form-control" value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Hasta</label>
                                <input type="date" name="fecha_hasta" class="form-control" value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-system">
                                        <i class="fas fa-search me-2"></i>Filtrar
                                    </button>
                                    <a href="?" class="btn btn-outline-system">
                                        <i class="fas fa-times me-2"></i>Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white system-secondary mb-3">
                            <div class="card-body text-center">
                                <h5><i class="fas fa-database me-2"></i>Total Registros</h5>
                                <h3><?php echo $total_registros; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white system-accent mb-3">
                            <div class="card-body text-center">
                                <h5><i class="fas fa-cogs me-2"></i>Acciones Únicas</h5>
                                <h3><?php echo count($acciones_unicas); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white system-primary mb-3">
                            <div class="card-body text-center">
                                <h5><i class="fas fa-download me-2"></i>Exportar</h5>
                                <a href="?exportar=csv<?php echo $_SERVER['QUERY_STRING'] ? '&' . $_SERVER['QUERY_STRING'] : ''; ?>" class="btn btn-light">
                                    <i class="fas fa-file-csv me-2"></i>Descargar CSV
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Auditoría -->
                <div class="card audit-card">
                    <div class="card-header system-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Registros de Auditoría</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-header-system">
                                    <tr>
                                        <th>Rol</th>
                                        <th>Nombre</th>
                                        <th>Descripción</th>
                                        <th>Fecha y Hora</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registros_auditoria as $registro): ?>
                                    <tr class="audit-row">
                                        <td><strong><?php echo htmlspecialchars($registro['rol'] ?? 'N/A'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($registro['nombre_completo'] ?? ($registro['nombre_usuario'] ?? 'N/A')); ?></td>
                                        <td><?php echo htmlspecialchars($registro['accion']); ?></td>
                                        <td>
                                            <?php 
                                                $fecha = $registro['creado_en'];
                                                echo date('d-m-Y H:i', strtotime($fecha));
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Inicializar popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl, {
                html: true,
                trigger: 'click'
            });
        });
    </script>
</body>
</html>