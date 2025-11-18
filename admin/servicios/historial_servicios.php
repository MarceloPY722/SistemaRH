<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';
require_once '../inc/header.php';

// Procesar búsqueda y filtros
$search_nombre = $_GET['nombre'] ?? '';
$search_fecha = $_GET['fecha'] ?? '';
$search_tipo = $_GET['tipo'] ?? '';

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if (!empty($search_nombre)) {
    $where_conditions[] = "(s.nombre LIKE ? OR s.descripcion LIKE ?)";
    $params[] = "%$search_nombre%";
    $params[] = "%$search_nombre%";
}

if (!empty($search_fecha)) {
    $cols = $conn->query("SHOW COLUMNS FROM servicios")->fetchAll(PDO::FETCH_COLUMN);
    $fecha_campo = in_array('fecha_inicio', $cols) ? 's.fecha_inicio' : (in_array('fecha_servicio', $cols) ? 's.fecha_servicio' : 's.created_at');
    $where_conditions[] = "DATE($fecha_campo) = ?";
    $params[] = $search_fecha;
}

if (!empty($search_tipo)) {
    $where_conditions[] = "s.tipo_servicio_id = ?";
    $params[] = $search_tipo;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Obtener servicios con información relacionada
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
    ORDER BY s.created_at DESC
    LIMIT 100
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$servicios = $stmt->fetchAll();

// Obtener tipos de servicios para filtros
$tipos_servicios = $conn->query("SELECT * FROM tipos_servicios WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Función para obtener personal asignado a un servicio
function getPersonalServicio($conn, $servicio_id) {
    $query = "
        SELECT 
            asig.*,
            p.nombre,
            p.apellido,
            p.legajo,
            g.nombre as grado
        FROM asignaciones_servicios asig
        JOIN policias p ON asig.policia_id = p.id
        LEFT JOIN grados g ON p.grado_id = g.id
        WHERE asig.servicio_id = ?
        ORDER BY p.apellido, p.nombre
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute([$servicio_id]);
    return $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Servicios - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .search-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 20px;
        }
        .service-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 15px;
            transition: transform 0.2s ease;
        }
        .service-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        .service-header {
            background: linear-gradient(135deg, #0d3d5c, #104c75);
            color: white;
            padding: 15px 20px;
            border-radius: 12px 12px 0 0;
        }
        .personal-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 8px;
            border-left: 4px solid #0d3d5c;
        }
        .page-title {
            color: #0d3d5c;
            font-weight: 700;
        }
        .btn-primary {
            background: #0d3d5c;
            border-color: #0d3d5c;
        }
        .btn-primary:hover {
            background: #104c75;
            border-color: #104c75;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0d3d5c;
            box-shadow: 0 0 0 0.2rem rgba(13, 61, 92, 0.25);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../inc/sidebar.php'; ?>
            
            <!-- Contenido Principal -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Header -->
                    <div class="page-header mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1 class="page-title">
                                    <i class="fas fa-history me-3"></i>
                                    Historial de Servicios
                                </h1>
                                <p class="page-subtitle text-muted">Busca y consulta el historial de servicios con su personal asignado</p>
                            </div>
                            <div>
                                <a href="index.php" class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-left me-2"></i>Volver a Servicios
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario de Búsqueda -->
                    <div class="search-card">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="fas fa-search me-1"></i>Nombre o Descripción
                                    </label>
                                    <input type="text" name="nombre" class="form-control" 
                                           value="<?php echo htmlspecialchars($search_nombre); ?>" 
                                           placeholder="Buscar por nombre o descripción...">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">
                                        <i class="fas fa-calendar me-1"></i>Fecha
                                    </label>
                                    <input type="date" name="fecha" class="form-control" 
                                           value="<?php echo htmlspecialchars($search_fecha); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">
                                        <i class="fas fa-tags me-1"></i>Tipo de Servicio
                                    </label>
                                    <select name="tipo" class="form-select">
                                        <option value="">Todos los tipos</option>
                                        <?php foreach ($tipos_servicios as $tipo): ?>
                                            <option value="<?php echo $tipo['id']; ?>" 
                                                    <?php echo $search_tipo == $tipo['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($tipo['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search me-1"></i>Buscar
                                        </button>
                                    </div>
                                </div>
                            </form>
                            <?php if (!empty($search_nombre) || !empty($search_fecha) || !empty($search_tipo)): ?>
                                <div class="mt-3">
                                    <a href="historial_servicios.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-times me-1"></i>Limpiar filtros
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Resultados -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                Resultados (<?php echo count($servicios); ?> servicios)
                            </h5>
                        </div>
                    </div>

                    <?php if (empty($servicios)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No se encontraron servicios con los criterios de búsqueda especificados.
                        </div>
                    <?php else: ?>
                        <?php foreach ($servicios as $servicio): ?>
                            <?php $personal = getPersonalServicio($conn, $servicio['id']); ?>
                            <div class="service-card">
                                <div class="service-header">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h5 class="mb-1">
                                                <i class="fas fa-tasks me-2"></i>
                                                <?php echo htmlspecialchars($servicio['nombre']); ?>
                                            </h5>
                                            <small>
                                                <i class="fas fa-tag me-1"></i>
                                                <?php echo htmlspecialchars($servicio['tipo_servicio']); ?> | 
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php 
                                                $cols = $conn->query("SHOW COLUMNS FROM servicios")->fetchAll(PDO::FETCH_COLUMN);
                                                $fecha_campo = in_array('fecha_inicio', $cols) ? 'fecha_inicio' : (in_array('fecha_servicio', $cols) ? 'fecha_servicio' : 'created_at');
                                                echo date('d/m/Y', strtotime($servicio[$fecha_campo])); 
                                                ?> | 
                                                <i class="fas fa-users me-1"></i>
                                                <?php echo count($personal); ?> asignados
                                            </small>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <span class="badge bg-light text-dark">
                                                <?php echo htmlspecialchars($servicio['estado']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($servicio['descripcion'])): ?>
                                        <p class="text-muted mb-3">
                                            <i class="fas fa-info-circle me-1"></i>
                                            <?php echo htmlspecialchars($servicio['descripcion']); ?>
                                        </p>
                                    <?php endif; ?>

                                    <h6 class="mb-3">
                                        <i class="fas fa-user-friends me-2"></i>
                                        Personal Asignado (<?php echo count($personal); ?>)
                                    </h6>

                                    <?php if (empty($personal)): ?>
                                        <div class="text-muted">
                                            <i class="fas fa-user-slash me-1"></i>
                                            No hay personal asignado a este servicio.
                                        </div>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach ($personal as $persona): ?>
                                                <div class="col-md-6 col-lg-4">
                                                    <div class="personal-item">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($persona['apellido'] . ', ' . $persona['nombre']); ?></strong>
                                                                <br><small class="text-muted">Legajo: <?php echo htmlspecialchars($persona['legajo']); ?></small>
                                                                <?php if (!empty($persona['grado'])): ?>
                                                                    <br><small class="text-muted"><?php echo htmlspecialchars($persona['grado']); ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="text-end">
                                                                <small class="badge bg-secondary">
                                                                    <?php echo htmlspecialchars($persona['puesto']); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                        <?php if (!empty($persona['lugar']) || !empty($persona['observaciones'])): ?>
                                                            <hr class="my-2">
                                                            <?php if (!empty($persona['lugar'])): ?>
                                                                <small class="d-block text-muted">
                                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                                    <?php echo htmlspecialchars($persona['lugar']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                            <?php if (!empty($persona['observaciones'])): ?>
                                                                <small class="d-block text-muted">
                                                                    <i class="fas fa-comment me-1"></i>
                                                                    <?php echo htmlspecialchars($persona['observaciones']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>