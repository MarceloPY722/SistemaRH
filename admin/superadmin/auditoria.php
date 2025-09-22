<?php
session_start();

// Verificar autenticación y rol de superadmin
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../cnx/db_connect.php';

// Verificar rol de superadmin
$stmt = $conn->prepare("SELECT rol FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();

if ($usuario['rol'] !== 'SUPERADMIN') {
    header('Location: ../../index.php');
    exit();
}

// Crear tabla de auditoría si no existe
$conn->exec("CREATE TABLE IF NOT EXISTS auditoria_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    accion VARCHAR(255) NOT NULL,
    tabla_afectada VARCHAR(100),
    registro_id INT,
    datos_anteriores TEXT,
    datos_nuevos TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
)");

// Función para registrar acciones de auditoría
function registrarAuditoria($accion, $tabla_afectada = null, $registro_id = null, $datos_anteriores = null, $datos_nuevos = null) {
    global $conn;
    
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $stmt = $conn->prepare("INSERT INTO auditoria_sistema (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, user_agent) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['usuario_id'],
        $accion,
        $tabla_afectada,
        $registro_id,
        $datos_anteriores,
        $datos_nuevos,
        $ip_address,
        $user_agent
    ]);
}

// Obtener registros de auditoría con filtros
$filtro_usuario = $_GET['usuario'] ?? '';
$filtro_accion = $_GET['accion'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';

$query = "SELECT a.*, u.nombre_usuario, u.nombre_completo 
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
        .audit-card {
            border: 2px solid #20c997;
            border-radius: 15px;
        }
        .audit-row {
            border-left: 4px solid #20c997;
            transition: all 0.3s;
        }
        .audit-row:hover {
            background-color: #f8f9fa;
            border-left-color: #198754;
        }
        .table-responsive {
            max-height: 600px;
        }
        .badge-action {
            font-size: 0.75em;
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
                    <span class="badge bg-success"><?php echo $total_registros; ?> registros</span>
                </div>

                <!-- Filtros -->
                <div class="card audit-card mb-4">
                    <div class="card-header bg-success text-white">
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
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Filtrar
                                    </button>
                                    <a href="?" class="btn btn-outline-secondary">
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
                        <div class="card text-white bg-info mb-3">
                            <div class="card-body text-center">
                                <h5><i class="fas fa-database me-2"></i>Total Registros</h5>
                                <h3><?php echo $total_registros; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-warning mb-3">
                            <div class="card-body text-center">
                                <h5><i class="fas fa-cogs me-2"></i>Acciones Únicas</h5>
                                <h3><?php echo count($acciones_unicas); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success mb-3">
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
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Registros de Auditoría</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Fecha/Hora</th>
                                        <th>Usuario</th>
                                        <th>Acción</th>
                                        <th>Tabla</th>
                                        <th>Registro</th>
                                        <th>IP</th>
                                        <th>Detalles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registros_auditoria as $registro): ?>
                                    <tr class="audit-row">
                                        <td>
                                            <small><?php echo $registro['creado_en']; ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($registro['nombre_usuario'] ?? 'N/A'); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($registro['nombre_completo'] ?? ''); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary badge-action">
                                                <?php echo htmlspecialchars($registro['accion']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($registro['tabla_afectada'] ?? 'N/A'); ?></td>
                                        <td><?php echo $registro['registro_id'] ?? 'N/A'; ?></td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($registro['ip_address']); ?></small>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-info" 
                                                    data-bs-toggle="popover" 
                                                    title="Detalles Completos"
                                                    data-bs-content="
                                                        <strong>User Agent:</strong> <?php echo htmlspecialchars($registro['user_agent']); ?><br>
                                                        <?php if ($registro['datos_anteriores']): ?>
                                                        <strong>Datos Anteriores:</strong> <?php echo htmlspecialchars($registro['datos_anteriores']); ?><br>
                                                        <?php endif; ?>
                                                        <?php if ($registro['datos_nuevos']): ?>
                                                        <strong>Datos Nuevos:</strong> <?php echo htmlspecialchars($registro['datos_nuevos']); ?>
                                                        <?php endif; ?>
                                                    ">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
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