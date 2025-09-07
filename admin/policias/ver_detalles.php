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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Policía - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-container {
            padding: 20px 0;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        .info-row {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #495057;
        }
        .info-value {
            color: #6c757d;
        }
        .status-badge {
            font-size: 0.9em;
            padding: 8px 15px;
        }
        .btn-back {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container main-container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header text-center">
                        <h3><i class="fas fa-user-circle me-2"></i>Detalles del Policía</h3>
                    </div>
                    <div class="card-body p-4">
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
                    
                    <div class="card-footer text-center bg-light">
                        <a href="index.php" class="btn btn-back">
                            <i class="fas fa-arrow-left me-2"></i>Volver al Listado
                        </a>
                        <a href="editar.php?id=<?php echo $policia['id']; ?>" class="btn btn-warning ms-2">
                            <i class="fas fa-edit me-2"></i>Editar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>