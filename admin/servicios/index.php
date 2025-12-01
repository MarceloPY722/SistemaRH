<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';
require_once '../inc/header.php';

// Utilidad: columnas existentes en la tabla servicios, para adaptar updates al esquema activo
function getServiciosColumns($conn) {
    try {
        $cols = $conn->query("SHOW COLUMNS FROM servicios")->fetchAll(PDO::FETCH_COLUMN);
        return $cols ?: [];
    } catch (Exception $e) {
        return [];
    }
}

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'cambiar_estado':
                $servicio_id = $_POST['servicio_id'];
                $nuevo_estado = $_POST['nuevo_estado'];
                
                $stmt = $conn->prepare("UPDATE servicios SET estado = ? WHERE id = ?");
                try {
                    $stmt->execute([$nuevo_estado, $servicio_id]);
                } catch (PDOException $e) {
                    // Si el esquema usa 'ACTIVO' en vez de 'EN_CURSO' (o viceversa), intentamos el valor alterno
                    $msg = $e->getMessage();
                    if (stripos($msg, 'Data truncated') !== false && stripos($msg, "estado") !== false) {
                        $alterno = ($nuevo_estado === 'EN_CURSO') ? 'ACTIVO' : (($nuevo_estado === 'ACTIVO') ? 'EN_CURSO' : $nuevo_estado);
                        $stmt->execute([$alterno, $servicio_id]);
                    } else {
                        throw $e;
                    }
                }
                
                header("Location: index.php?success=estado_actualizado");
                exit();
                break;
                
            case 'editar':
                try {
                    $servicio_id = $_POST['servicio_id'];
                    $nombre = $_POST['nombre'];
                    $tipo_servicio_id = $_POST['tipo_servicio_id'];
                    $fecha_servicio = $_POST['fecha_servicio'];
                    $orden_del_dia = $_POST['orden_del_dia'] ?? '';
                    $descripcion = $_POST['descripcion'] ?? '';

                    // Mapeo de fecha a inicio/fin del día (si existen esas columnas)
                    $fecha_inicio = $fecha_servicio . ' 00:00:00';
                    $fecha_fin = $fecha_servicio . ' 23:59:59';

                    // Construimos dinámicamente el UPDATE según columnas existentes
                    $cols = getServiciosColumns($conn);
                    $set = [];
                    $params = [];

                    // nombre siempre
                    $set[] = 'nombre = ?';
                    $params[] = $nombre;

                    // tipo_servicio_id si existe
                    if (in_array('tipo_servicio_id', $cols)) {
                        $set[] = 'tipo_servicio_id = ?';
                        $params[] = $tipo_servicio_id;
                    }

                    // descripcion
                    $set[] = 'descripcion = ?';
                    $params[] = $descripcion;

                    // fechas según esquema
                    if (in_array('fecha_inicio', $cols) && in_array('fecha_fin', $cols)) {
                        $set[] = 'fecha_inicio = ?';
                        $params[] = $fecha_inicio;
                        $set[] = 'fecha_fin = ?';
                        $params[] = $fecha_fin;
                    } elseif (in_array('fecha_servicio', $cols)) {
                        $set[] = 'fecha_servicio = ?';
                        $params[] = $fecha_servicio;
                    }

                    // orden_del_dia si existe
                    if (in_array('orden_del_dia', $cols)) {
                        $set[] = 'orden_del_dia = ?';
                        $params[] = $orden_del_dia;
                    }

                    // updated_at
                    $set[] = 'updated_at = NOW()';

                    $sql = 'UPDATE servicios SET ' . implode(', ', $set) . ' WHERE id = ?';
                    $params[] = $servicio_id;

                    $stmt = $conn->prepare($sql);
                    $stmt->execute($params);

                    header("Location: index.php?success=servicio_actualizado");
                    exit();
                } catch (Exception $e) {
                    header("Location: index.php?error=" . urlencode('No se pudo actualizar el servicio: ' . $e->getMessage()));
                    exit();
                }
                break;

            case 'eliminar':
                $servicio_id = $_POST['servicio_id'];
                
                // Eliminar asignaciones primero
                $stmt = $conn->prepare("DELETE FROM asignaciones_servicios WHERE servicio_id = ?");
                $stmt->execute([$servicio_id]);
                
                // Eliminar servicio
                $stmt = $conn->prepare("DELETE FROM servicios WHERE id = ?");
                $stmt->execute([$servicio_id]);
                
                header("Location: index.php?success=servicio_eliminado");
                exit();
                break;
        }
    }
}

// Obtener servicios con información relacionada
// Cargar servicios con orden adaptable según columnas disponibles
$cols = getServiciosColumns($conn);
$orderCampo = in_array('fecha_inicio', $cols) ? 's.fecha_inicio' : (in_array('fecha_servicio', $cols) ? 's.fecha_servicio' : 's.created_at');

// Filtrar por mes actual si no se especifica otro filtro
$mes_actual = date('Y-m');
$where_conditions = [];
$params = [];

// Si no hay filtros específicos, mostrar solo servicios del mes actual
if (!isset($_GET['mostrar_todos'])) {
    $fecha_campo = in_array('fecha_inicio', $cols) ? 's.fecha_inicio' : (in_array('fecha_servicio', $cols) ? 's.fecha_servicio' : 's.created_at');
    $where_conditions[] = "DATE_FORMAT($fecha_campo, '%Y-%m') = ?";
    $params[] = $mes_actual;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "
    SELECT 
        s.*,
        ts.nombre as tipo_servicio,
        COUNT(asig.policia_id) as personal_asignado
    FROM servicios s
    LEFT JOIN tipos_servicios ts ON s.tipo_servicio_id = ts.id
    LEFT JOIN asignaciones_servicios asig ON s.id = asig.servicio_id
    $where_clause
    GROUP BY s.id
    ORDER BY $orderCampo DESC, s.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$servicios = $stmt->fetchAll();

// Obtener tipos de servicios para filtros
$tipos_servicios = $conn->query("SELECT * FROM tipos_servicios WHERE activo = 1 ORDER BY nombre")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Servicios - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../inc/sidebar.php'; ?>
            
            <!-- Contenido Principal -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Header de la página -->
                    <div class="page-header mb-4">
                        <div>
                            <h1 class="page-title">
                                <i class="fas fa-calendar-alt me-3"></i>
                                Gestión de Servicios
                            </h1>
                            <p class="page-subtitle text-muted">Administra y asigna servicios de personal</p>
                        </div>
                        <a href="crear_servicio.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Nuevo Servicio
                        </a>
                    </div>

                    <!-- Alertas -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <?php if (!isset($_GET['mostrar_todos'])): ?>
                                        <span class="badge bg-info">
                                            <i class="fas fa-filter me-1"></i>
                                            Mostrando servicios de <?php echo date('F Y'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-list me-1"></i>
                                            Mostrando todos los servicios
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if (!isset($_GET['mostrar_todos'])): ?>
                                        <a href="?mostrar_todos=1" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>Ver Todos
                                        </a>
                                    <?php else: ?>
                                        <a href="index.php" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-calendar me-1"></i>Ver Mes Actual
                                        </a>
                                    <?php endif; ?>
                                    <a href="historial_servicios.php" class="btn btn-outline-secondary btn-sm ms-2">
                                        <i class="fas fa-history me-1"></i>Historial
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Alertas -->
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php
                            switch ($_GET['success']) {
                                case 'estado_actualizado':
                                    echo '<i class="fas fa-check-circle me-2"></i>Estado del servicio actualizado correctamente.';
                                    break;
                                case 'servicio_actualizado':
                                    echo '<i class="fas fa-save me-2"></i>Servicio actualizado correctamente.';
                                    break;
                                case 'servicio_eliminado':
                                    echo '<i class="fas fa-trash me-2"></i>Servicio eliminado correctamente.';
                                    break;
                                default:
                                    echo '<i class="fas fa-check-circle me-2"></i>Operación realizada correctamente.';
                            }
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Estadísticas -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-primary">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="stats-content">
                                    <h3><?php echo count(array_filter($servicios, fn($s) => $s['estado'] == 'PROGRAMADO')); ?></h3>
                                    <p>Programados</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stats-content">
                                    <h3><?php echo count(array_filter($servicios, fn($s) => in_array($s['estado'], ['EN_CURSO','ACTIVO']))); ?></h3>
                                    <p>En Curso</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-success">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="stats-content">
                                    <h3><?php echo count(array_filter($servicios, fn($s) => $s['estado'] == 'COMPLETADO')); ?></h3>
                                    <p>Completados</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-danger">
                                    <i class="fas fa-times"></i>
                                </div>
                                <div class="stats-content">
                                    <h3><?php echo count(array_filter($servicios, fn($s) => $s['estado'] == 'CANCELADO')); ?></h3>
                                    <p>Cancelados</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros -->
                    <div class="filter-card mb-4">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Filtrar por tipo:</label>
                                    <select class="form-select" id="filtroTipo">
                                        <option value="">Todos los tipos</option>
                                        <?php foreach ($tipos_servicios as $tipo): ?>
                                            <option value="<?php echo $tipo['id']; ?>"><?php echo htmlspecialchars($tipo['nombre'] ?? ''); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Filtrar por estado:</label>
                                    <select class="form-select" id="filtroEstado">
                                        <option value="">Todos los estados</option>
                                        <option value="PROGRAMADO">Programado</option>
                                        <option value="EN_CURSO">En Curso</option>
                                        <option value="COMPLETADO">Completado</option>
                                        <option value="CANCELADO">Cancelado</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Buscar:</label>
                                    <input type="text" class="form-control" id="buscarServicio" placeholder="Buscar por nombre o descripción...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de Servicios -->
                    <div class="services-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>Lista de Servicios
                                <span class="badge bg-secondary ms-2"><?php echo count($servicios); ?> servicios</span>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($servicios)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-calendar-plus"></i>
                                    <h4>No hay servicios registrados</h4>
                                    <p>Comienza creando tu primer servicio</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover services-table">
                                        <thead>
                                            <tr>
                                                <th>Servicio</th>
                                                <th>Fecha & Estado</th>
                                                <th>Personal</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($servicios as $servicio): ?>
                                                <tr data-tipo="<?php echo $servicio['tipo_servicio_id']; ?>" data-estado="<?php echo $servicio['estado']; ?>">
                                                    <td>
                                                        <div class="service-info">
                                                            <h6 class="service-name"><?php echo htmlspecialchars($servicio['nombre'] ?? ''); ?></h6>
                                            <?php if ($servicio['tipo_servicio']): ?>
                                                <span class="service-type"><?php echo htmlspecialchars($servicio['tipo_servicio'] ?? ''); ?></span>
                                            <?php endif; ?>
                                            <?php if ($servicio['descripcion']): ?>
                                                <p class="service-desc"><?php echo htmlspecialchars(substr($servicio['descripcion'] ?? '', 0, 80)); ?>...</p>
                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="date-status">
                                                            <div class="service-date">
                                                                <i class="fas fa-calendar me-1"></i>
                                                                <?php echo date('d/m/Y', strtotime($servicio['fecha_inicio'])); ?>
                                                            </div>
                                                            <?php
                                                            $estado_class = [
                                                                'PROGRAMADO' => 'status-programado',
                                                                'EN_CURSO' => 'status-en-curso',
                                                                'COMPLETADO' => 'status-completado',
                                                                'CANCELADO' => 'status-cancelado'
                                                            ];
                                                            ?>
                                                            <span class="service-status <?php echo $estado_class[$servicio['estado']] ?? 'status-default'; ?>">
                                                                <?php echo $servicio['estado']; ?>
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="personnel-count">
                                                            <i class="fas fa-users me-1"></i>
                                                            <span><?php echo $servicio['personal_asignado']; ?></span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <button class="btn btn-sm btn-outline-primary" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#modalVer<?php echo $servicio['id']; ?>" 
                                                                    title="Ver detalles">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <a class="btn btn-sm btn-outline-secondary" 
                                                               href="imprimir_servicio.php?id=<?php echo (int)$servicio['id']; ?>" 
                                                               target="_blank" 
                                                               title="Imprimir">
                                                                <i class="fas fa-print"></i>
                                                            </a>
                                                            <button class="btn btn-sm btn-outline-info" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#modalEstado<?php echo $servicio['id']; ?>" 
                                                                    title="Cambiar estado">
                                                                <i class="fas fa-exchange-alt"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-warning" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#modalEditar<?php echo $servicio['id']; ?>" 
                                                                    title="Editar">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#modalEliminar<?php echo $servicio['id']; ?>" 
                                                                    title="Eliminar">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>

                                                <!-- Modal Ver Detalles -->
                                                <div class="modal fade" id="modalVer<?php echo $servicio['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-lg">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Detalles del Servicio</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="row g-3">
                                                                    <div class="col-md-6">
                                                                        <div class="mb-2"><strong>Nombre:</strong> <?php echo htmlspecialchars($servicio['nombre'] ?? ''); ?></div>
                                                                        <div class="mb-2"><strong>Tipo:</strong> <?php echo htmlspecialchars($servicio['tipo_servicio'] ?? ''); ?></div>
                                                                        <div class="mb-2"><strong>Estado:</strong> <?php echo htmlspecialchars($servicio['estado'] ?? ''); ?></div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <?php
                                                                        $fechaMostrar = $servicio['fecha_servicio'] ?? (isset($servicio['fecha_inicio']) ? substr($servicio['fecha_inicio'], 0, 10) : '');
                                                                        ?>
                                                                        <div class="mb-2"><strong>Fecha:</strong> <?php echo htmlspecialchars($fechaMostrar); ?></div>
                                                                        <div class="mb-2"><strong>Personal asignado:</strong> <?php echo (int)($servicio['personal_asignado'] ?? 0); ?></div>
                                                                    </div>
                                                                </div>
                                                                <hr/>
                                                                <h6 class="mb-2">Personal Asignado</h6>
                                                                <?php
                                                                    try {
                                                                        $asig = $conn->prepare("
                                                                            SELECT 
                                                                                p.nombre, 
                                                                                p.apellido, 
                                                                                g.abreviatura AS grado_abrev,
                                                                                g.nombre AS grado_nombre,
                                                                                a.puesto
                                                                             FROM asignaciones_servicios a 
                                                                             JOIN policias p ON p.id = a.policia_id 
                                                                             LEFT JOIN grados g ON g.id = p.grado_id 
                                                                             WHERE a.servicio_id = ? 
                                                                             ORDER BY g.nivel_jerarquia ASC, p.apellido ASC"
                                                                        );
                                                                        $asig->execute([$servicio['id']]);
                                                                        $asignados = $asig->fetchAll();
                                                                    } catch (Exception $e) {
                                                                        $asignados = [];
                                                                        // Uncomment for debugging: echo "Error: " . $e->getMessage();
                                                                    }
                                                                ?>
                                                                <?php if (!empty($asignados)): ?>
                                                                    <ul class="list-group">
                                                                        <?php foreach ($asignados as $p): ?>
                                                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                                <span>
                                                                                    <?php 
                                                                                        $grado = $p['grado_abrev'] ?? ($p['grado_nombre'] ?? '');
                                                                                        echo htmlspecialchars(trim($grado . ' ' . ($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? '')));
                                                                                    ?>
                                                                                </span>
                                                                                <span class="badge bg-secondary">
                                                                                    <?php echo htmlspecialchars($p['puesto'] ?? ''); ?>
                                                                                </span>
                                                                            </li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                <?php else: ?>
                                                                    <p class="text-muted">No hay personal asignado.</p>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Modal Cambiar Estado -->
                                                <div class="modal fade" id="modalEstado<?php echo $servicio['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Cambiar Estado del Servicio</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <p>Cambiar estado del servicio: <strong><?php echo htmlspecialchars($servicio['nombre'] ?? ''); ?></strong></p>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Nuevo Estado:</label>
                                                                        <select name="nuevo_estado" class="form-select" required>
                                                                            <option value="PROGRAMADO" <?php echo $servicio['estado'] == 'PROGRAMADO' ? 'selected' : ''; ?>>Programado</option>
                                                                            <option value="EN_CURSO" <?php echo $servicio['estado'] == 'EN_CURSO' ? 'selected' : ''; ?>>En Curso</option>
                                                                            <option value="COMPLETADO" <?php echo $servicio['estado'] == 'COMPLETADO' ? 'selected' : ''; ?>>Completado</option>
                                                                            <option value="CANCELADO" <?php echo $servicio['estado'] == 'CANCELADO' ? 'selected' : ''; ?>>Cancelado</option>
                                                                        </select>
                                                                    </div>
                                                                    <input type="hidden" name="action" value="cambiar_estado">
                                                                    <input type="hidden" name="servicio_id" value="<?php echo $servicio['id']; ?>">
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                    <button type="submit" class="btn btn-primary">Cambiar Estado</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Modal Editar Servicio -->
                                                <div class="modal fade" id="modalEditar<?php echo $servicio['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Editar Servicio</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <?php
                                                                        $fecha_form = $servicio['fecha_servicio'] ?? (isset($servicio['fecha_inicio']) ? substr($servicio['fecha_inicio'], 0, 10) : '');
                                                                    ?>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Nombre</label>
                                                                        <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($servicio['nombre'] ?? ''); ?>" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Tipo de Servicio</label>
                                                                        <select name="tipo_servicio_id" class="form-select" required>
                                                                            <?php foreach ($tipos_servicios as $tipo): ?>
                                                                                <option value="<?php echo $tipo['id']; ?>" <?php echo ($servicio['tipo_servicio_id'] ?? null) == $tipo['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($tipo['nombre'] ?? ''); ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Fecha del Servicio</label>
                                                                        <input type="date" name="fecha_servicio" class="form-control" value="<?php echo htmlspecialchars($fecha_form); ?>" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Orden del Día</label>
                                                                        <input type="text" name="orden_del_dia" class="form-control" value="<?php echo htmlspecialchars($servicio['orden_del_dia'] ?? ''); ?>" placeholder="Ej.: 322/2025">
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Descripción</label>
                                                                        <textarea name="descripcion" class="form-control" rows="3"><?php echo htmlspecialchars($servicio['descripcion'] ?? ''); ?></textarea>
                                                                    </div>
                                                                    <input type="hidden" name="action" value="editar">
                                                                    <input type="hidden" name="servicio_id" value="<?php echo $servicio['id']; ?>">
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                    <button type="submit" class="btn btn-warning">Guardar Cambios</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Modal Eliminar -->
                                                <div class="modal fade" id="modalEliminar<?php echo $servicio['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header bg-danger text-white">
                                                                <h5 class="modal-title">Confirmar Eliminación</h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <div class="text-center">
                                                                        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                                                                        <p>¿Está seguro que desea eliminar el servicio?</p>
                                                                        <p><strong><?php echo htmlspecialchars($servicio['nombre'] ?? ''); ?></strong></p>
                                                                        <p class="text-muted">Esta acción no se puede deshacer y eliminará todas las asignaciones relacionadas.</p>
                                                                    </div>
                                                                    <input type="hidden" name="action" value="eliminar">
                                                                    <input type="hidden" name="servicio_id" value="<?php echo $servicio['id']; ?>">
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                    <button type="submit" class="btn btn-danger">Eliminar Servicio</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filtros en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            const filtroTipo = document.getElementById('filtroTipo');
            const filtroEstado = document.getElementById('filtroEstado');
            const buscarServicio = document.getElementById('buscarServicio');
            const filas = document.querySelectorAll('tbody tr[data-tipo]');

            function aplicarFiltros() {
                const tipoSeleccionado = filtroTipo.value;
                const estadoSeleccionado = filtroEstado.value;
                const textoBusqueda = buscarServicio.value.toLowerCase();

                filas.forEach(fila => {
                    const tipo = fila.getAttribute('data-tipo');
                    const estado = fila.getAttribute('data-estado');
                    const texto = fila.textContent.toLowerCase();

                    const cumpleTipo = !tipoSeleccionado || tipo === tipoSeleccionado;
                    // Tratar 'EN_CURSO' y 'ACTIVO' como equivalentes para filtrado
                    const cumpleEstado = !estadoSeleccionado 
                        || estado === estadoSeleccionado 
                        || (estadoSeleccionado === 'EN_CURSO' && estado === 'ACTIVO')
                        || (estadoSeleccionado === 'ACTIVO' && estado === 'EN_CURSO');
                    const cumpleTexto = !textoBusqueda || texto.includes(textoBusqueda);

                    if (cumpleTipo && cumpleEstado && cumpleTexto) {
                        fila.style.display = '';
                    } else {
                        fila.style.display = 'none';
                    }
                });
            }

            filtroTipo.addEventListener('change', aplicarFiltros);
            filtroEstado.addEventListener('change', aplicarFiltros);
            buscarServicio.addEventListener('input', aplicarFiltros);
        });
    </script>

    <style>
        :root {
            --primary-color: #104c75;
            --primary-dark: #0d3d5c;
            --secondary-color: #6c757d;
            --bg-light: #f4f6f9;
            --card-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .main-content {
            padding: 2rem;
        }

        /* Header Moderno */
        .page-header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            border: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: nowrap;
        }

        .page-header > div:first-child {
            flex: 0 1 auto;
        }

        .page-header > a,
        .page-header > div:last-child {
            flex: 0 0 auto;
            margin-left: auto;
        }

        .page-title {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.75rem;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .page-title i {
            background: rgba(16, 76, 117, 0.1);
            padding: 0.5rem;
            border-radius: 8px;
            margin-right: 1rem;
            font-size: 1.2rem;
        }

        .page-subtitle {
            color: var(--secondary-color);
            margin-top: 0.25rem;
            font-size: 0.95rem;
            margin-left: 3.5rem; /* Alineado con el texto del título */
        }

        /* Botones Refinados */
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.5rem 1.2rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(16, 76, 117, 0.2);
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 6px;
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }

        /* Stats Cards Minimalistas */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            border: none;
            height: 100%;
            transition: transform 0.2s;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stats-card:hover {
            transform: translateY(-3px);
        }

        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
        }

        .stats-content h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            color: #2c3e50;
            line-height: 1.2;
        }

        .stats-content p {
            margin: 0;
            color: #8898aa;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filtros y Tablas */
        .filter-card, .services-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            border: none;
            margin-bottom: 2rem;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1.25rem 1.5rem;
            border-radius: 12px 12px 0 0 !important;
        }

        .card-header h5 {
            color: var(--primary-color);
            font-weight: 600;
        }

        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            color: #8898aa;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 1rem;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }

        .service-name {
            font-weight: 600;
            color: #32325d;
            margin-bottom: 0.25rem;
        }

        .service-type {
            font-size: 0.8rem;
            color: var(--primary-color);
            background: rgba(16, 76, 117, 0.08);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-weight: 600;
        }

        .service-desc {
            font-size: 0.85rem;
            color: #8898aa;
            margin-top: 0.25rem;
            margin-bottom: 0;
        }

        .service-date {
            font-weight: 500;
            color: #525f7f;
        }

        /* Estados */
        .service-status {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.25rem;
        }

        .status-programado { background-color: #e3f2fd; color: #1976d2; }
        .status-en-curso { background-color: #fff3e0; color: #f57c00; }
        .status-completado { background-color: #e8f5e9; color: #388e3c; }
        .status-cancelado { background-color: #ffebee; color: #d32f2f; }
        .status-default { background-color: #f8f9fa; color: #6c757d; }

        /* Acciones */
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            margin: 0 0.1rem;
            border-radius: 6px;
        }

        .services-card .card-header {
            background: linear-gradient(45deg, #104c75, #0d3d5c);
            color: white;
            padding: 20px 30px;
            border: none;
            border-radius: 15px 15px 0 0;
        }

        .services-card .card-header h5 {
            color: white !important;
        }

        /* Tabla */
        .services-table {
            margin: 0;
        }

        .services-table th {
            background: #f8f9fa;
            border: none;
            padding: 15px 20px;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.5px;
        }

        .services-table td {
            padding: 20px;
            border: none;
            border-bottom: 1px solid #f1f1f1;
            vertical-align: middle;
        }

        .services-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .services-table tbody tr:last-child td {
            border-bottom: none;
        }

        .service-info .service-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 1.1em;
        }

        .service-type {
            font-size: 0.85em;
            color: #104c75;
            background: rgba(16, 76, 117, 0.1);
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .service-desc {
            font-size: 0.9em;
            color: #6c757d;
            margin: 5px 0 0 0;
        }

        .date-status {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .service-date {
            font-weight: 500;
            color: #495057;
        }

        .service-status {
            font-size: 0.85em;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
            text-align: center;
            font-weight: 600;
            width: fit-content;
        }

        .status-programado { background: #e3f2fd; color: #0d47a1; }
        .status-en-curso { background: #fff3e0; color: #e65100; }
        .status-completado { background: #e8f5e9; color: #1b5e20; }
        .status-cancelado { background: #ffebee; color: #b71c1c; }
        .status-default { background: #f5f5f5; color: #616161; }

        .personnel-count {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #495057;
            font-weight: 500;
        }

        .order-info {
            font-size: 0.9em;
            color: #6c757d;
        }

        .action-buttons .btn {
            padding: 5px 10px;
            border-radius: 6px;
            margin-right: 3px;
        }

        /* Empty State Mejorado */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: linear-gradient(to bottom, #ffffff, #f8f9fa);
        }

        .empty-state i {
            font-size: 4em;
            color: #cbd5e0;
            margin-bottom: 20px;
        }

        .empty-state h4 {
            color: #2d3748;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .empty-state p {
            color: #718096;
            margin-bottom: 25px;
        }
    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

