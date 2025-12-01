<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

// Verificar que se proporcione un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$policia_id = (int)$_GET['id'];

// Obtener datos del policía
$sql = "
    SELECT p.*, tg.nombre as grado_nombre, tg.abreviatura as grado_abreviatura, 
           g.nombre as categoria_nombre, e.nombre as especialidad_nombre, 
           lg.nombre as lugar_guardia_nombre, r.nombre as region_nombre
    FROM policias p
    LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
    LEFT JOIN grados g ON tg.grado_id = g.id
    LEFT JOIN especialidades e ON p.especialidad_id = e.id
    LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
    LEFT JOIN regiones r ON p.region_id = r.id
    WHERE p.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->execute([$policia_id]);
$policia = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$policia) {
    header("Location: index.php");
    exit();
}

// Obtener última guardia
$sql_ultima_guardia = "
    SELECT gg.fecha_guardia, lg.nombre as lugar_guardia
    FROM guardias_generadas_detalle ggd
    INNER JOIN guardias_generadas gg ON ggd.guardia_generada_id = gg.id
    LEFT JOIN lugares_guardias lg ON ggd.lugar_guardia_id = lg.id
    WHERE ggd.policia_id = ?
    ORDER BY gg.fecha_guardia DESC
    LIMIT 1
";
$stmt_guardia = $conn->prepare($sql_ultima_guardia);
$stmt_guardia->execute([$policia_id]);
$ultima_guardia = $stmt_guardia->fetch(PDO::FETCH_ASSOC);

// Obtener último servicio
$sql_ultimo_servicio = "
    SELECT s.fecha_inicio, s.fecha_fin, ts.nombre as tipo_servicio, s.descripcion
    FROM asignaciones_servicios as_serv
    INNER JOIN servicios s ON as_serv.servicio_id = s.id
    LEFT JOIN tipos_servicios ts ON s.tipo_servicio_id = ts.id
    WHERE as_serv.policia_id = ?
    ORDER BY s.fecha_inicio DESC
    LIMIT 1
";
$stmt_servicio = $conn->prepare($sql_ultimo_servicio);
$stmt_servicio->execute([$policia_id]);
$ultimo_servicio = $stmt_servicio->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Policía - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/sidebar.css">
    <style>
        :root {
            --primary-color: #104c75;
            --secondary-color: #1e7e34;
            --accent-color: #ffc107;
            --light-bg: #f8f9fa;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --border-color: rgba(16, 76, 117, 0.1);
            --shadow-color: rgba(16, 76, 117, 0.1);
        }
        
        .main-content {
            padding: 20px;
            background: var(--light-bg);
            min-height: 100vh;
        }
        
        .details-header {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px var(--shadow-color);
            border: 1px solid var(--border-color);
        }
        
        .details-header h1 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 1.8rem;
        }
        
        .details-header p {
            color: var(--text-secondary);
            font-size: 1rem;
            margin: 0;
        }
        
        .details-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 3px 12px var(--shadow-color);
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .details-card:hover {
            box-shadow: 0 8px 25px rgba(16, 76, 117, 0.15);
            transform: translateY(-2px);
        }
        
        .details-card-header {
            background: linear-gradient(135deg, var(--primary-color), #1565c0);
            color: white;
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .details-card-header h3 {
            margin: 0;
            font-weight: 600;
            font-size: 1.3rem;
        }
        
        .details-card-body {
            padding: 25px;
        }
        
        .info-row {
            border-bottom: 1px solid var(--border-color);
            padding: 15px 0;
            display: flex;
            align-items: center;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--text-primary);
            min-width: 150px;
            margin-right: 15px;
        }
        
        .info-value {
            color: var(--text-secondary);
            flex: 1;
        }
        
        .status-badge {
            font-size: 0.85em;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .btn-action {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-color), #1565c0);
            color: white;
        }
        
        .btn-primary-custom:hover {
            background: linear-gradient(135deg, #0d3a5c, #104c75);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 76, 117, 0.3);
        }
        
        .btn-warning-custom {
            background: linear-gradient(135deg, var(--accent-color), #ffb300);
            color: #212529;
        }
        
        .btn-warning-custom:hover {
            background: linear-gradient(135deg, #e0a800, #ffc107);
            color: #212529;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
        }
        
        .breadcrumb-container {
            background: #fff;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px var(--shadow-color);
            border: 1px solid var(--border-color);
        }
        
        .policia-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), #1565c0);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin-right: 20px;
            box-shadow: 0 4px 12px rgba(16, 76, 117, 0.3);
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
                    <!-- Breadcrumb -->
                    <div class="breadcrumb-container">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Policías</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Detalles del Policía</li>
                            </ol>
                        </nav>
                    </div>
                    
                    <!-- Encabezado -->
                    <div class="details-header">
                        <div class="d-flex align-items-center">
                            <div class="policia-avatar">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div>
                                <h1><i class="fas fa-user-circle me-2"></i>Detalles del Policía</h1>
                                <p>Información completa del personal policial</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tarjeta de Detalles -->
                    <div class="details-card">
                        <div class="details-card-header">
                            <h3><i class="fas fa-id-card me-2"></i>Información Personal</h3>
                        </div>
                        <div class="details-card-body">
                        <div class="row info-row">
                            <div class="col-md-3 info-label">Legajo:</div>
                            <div class="col-md-9 info-value"><?php echo htmlspecialchars($policia['legajo']); ?></div>
                        </div>
                        
                        <div class="row info-row">
                            <div class="col-md-3 info-label">Nombre Completo:</div>
                            <div class="col-md-9 info-value"><?php echo htmlspecialchars($policia['nombre'] . ' ' . $policia['apellido']); ?></div>
                        </div>
                        
                        <div class="row info-row">
                            <div class="col-md-3 info-label">CIN:</div>
                            <div class="col-md-9 info-value"><?php echo htmlspecialchars($policia['cin']); ?></div>
                        </div>
                        
                        <div class="row info-row">
                            <div class="col-md-3 info-label">Género:</div>
                            <div class="col-md-9 info-value"><?php echo $policia['genero'] == 'M' ? 'Masculino' : 'Femenino'; ?></div>
                        </div>
                        
                        <div class="row info-row">
                            <div class="col-md-3 info-label">Grado:</div>
                            <div class="col-md-9 info-value">
                                <?php if ($policia['grado_abreviatura']): ?>
                                    <?php echo htmlspecialchars($policia['grado_abreviatura'] . ' - ' . $policia['grado_nombre']); ?>
                                    <small class="text-muted">(<?php echo htmlspecialchars($policia['categoria_nombre']); ?>)</small>
                                <?php else: ?>
                                    <span class="text-muted">No asignado</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row info-row">
                            <div class="col-md-3 info-label">Especialidad:</div>
                            <div class="col-md-9 info-value">
                                <?php echo $policia['especialidad_nombre'] ? htmlspecialchars($policia['especialidad_nombre']) : '<span class="text-muted">No asignada</span>'; ?>
                            </div>
                        </div>
                        
                        <div class="row info-row">
                            <div class="col-md-3 info-label">Cargo:</div>
                            <div class="col-md-9 info-value"><?php echo $policia['cargo'] ? htmlspecialchars($policia['cargo']) : '<span class="text-muted">No especificado</span>'; ?></div>
                        </div>
                        
                        <div class="row info-row">
                            <div class="col-md-3 info-label">Comisionamiento:</div>
                            <div class="col-md-9 info-value"><?php echo $policia['comisionamiento'] ? htmlspecialchars($policia['comisionamiento']) : '<span class="text-muted">No especificado</span>'; ?></div>
                        </div>
                        
                        <div class="row info-row">
                            <div class="col-md-3 info-label">Teléfono:</div>
                            <div class="col-md-9 info-value"><?php echo $policia['telefono'] ? htmlspecialchars($policia['telefono']) : '<span class="text-muted">No especificado</span>'; ?></div>
                        </div>
                        
                        <div class="row info-row">
                            <div class="col-md-3 info-label">Región:</div>
                            <div class="col-md-9 info-value">
                                <?php echo $policia['region_nombre'] ? htmlspecialchars($policia['region_nombre']) : '<span class="text-muted">No asignada</span>'; ?>
                            </div>
                        </div>
                        
                        <div class="row info-row">
                            <div class="col-md-3 info-label">Lugar de Guardia:</div>
                            <div class="col-md-9 info-value">
                                <?php echo $policia['lugar_guardia_nombre'] ? htmlspecialchars($policia['lugar_guardia_nombre']) : '<span class="text-muted">No asignado</span>'; ?>
                            </div>
                        </div>
                        
                        <div class="row info-row">
                            <div class="col-md-3 info-label">Observaciones:</div>
                            <div class="col-md-9 info-value">
                                <?php echo $policia['observaciones'] ? htmlspecialchars($policia['observaciones']) : '<span class="text-muted">Sin observaciones</span>'; ?>
                            </div>
                        </div>
                        
                        <div class="row info-row">
                            <div class="col-md-3 info-label">Última Guardia:</div>
                            <div class="col-md-9 info-value">
                                <?php if ($ultima_guardia): ?>
                                    <strong><?php echo date('d/m/Y', strtotime($ultima_guardia['fecha_guardia'])); ?></strong>
                                    <?php if ($ultima_guardia['lugar_guardia']): ?>
                                        - <?php echo htmlspecialchars($ultima_guardia['lugar_guardia']); ?>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <span class="text-muted">Sin guardias registradas</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row info-row">
                            <div class="col-md-3 info-label">Último Servicio:</div>
                            <div class="col-md-9 info-value">
                                <?php if ($ultimo_servicio): ?>
                                    <strong><?php echo date('d/m/Y', strtotime($ultimo_servicio['fecha_inicio'])); ?></strong>
                                    <?php if ($ultimo_servicio['tipo_servicio']): ?>
                                        - <?php echo htmlspecialchars($ultimo_servicio['tipo_servicio']); ?>
                                    <?php endif; ?>
                                    <?php if ($ultimo_servicio['descripcion']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($ultimo_servicio['descripcion']); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Sin servicios registrados</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row info-row">
                            <div class="col-md-3 info-label">Estado:</div>
                            <div class="col-md-9 info-value">
                                <?php if ($policia['activo']): ?>
                                    <span class="badge bg-success status-badge"><i class="fas fa-check-circle me-1"></i>Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger status-badge"><i class="fas fa-times-circle me-1"></i>Inactivo</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row info-row">
                            <div class="col-md-3 info-label">Fecha de Registro:</div>
                            <div class="col-md-9 info-value">
                                <?php echo date('d/m/Y H:i', strtotime($policia['created_at'])); ?>
                            </div>
                        </div>
                        
                        <?php if ($policia['updated_at']): ?>
                        <div class="row info-row">
                            <div class="col-md-3 info-label">Última Actualización:</div>
                            <div class="col-md-9 info-value">
                                <?php echo date('d/m/Y H:i', strtotime($policia['updated_at'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-footer text-center bg-light py-3">
                        <a href="index.php" class="btn btn-action btn-primary-custom me-2">
                            <i class="fas fa-arrow-left"></i>Volver al Listado
                        </a>
                        <a href="editar.php?id=<?php echo $policia['id']; ?>" class="btn btn-action btn-warning-custom">
                            <i class="fas fa-edit"></i>Editar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>