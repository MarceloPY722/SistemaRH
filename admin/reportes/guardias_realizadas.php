<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

// Parámetros de filtrado
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$fecha_especifica = $_GET['fecha_especifica'] ?? '';
$lugar_guardia_id = $_GET['lugar_guardia_id'] ?? '';

$guardias_data = [];
$lugares_guardias = [];

// Obtener lugares de guardias para el filtro
$lugares_result = $conn->query("SELECT id, nombre FROM lugares_guardias ORDER BY nombre");
while ($lugar = $lugares_result->fetch_assoc()) {
    $lugares_guardias[] = $lugar;
}

// Construir consulta según filtros
if (!empty($_GET['fecha_inicio']) || !empty($_GET['fecha_especifica'])) {
    $sql = "
        SELECT gr.fecha_inicio as fecha_guardia, 
               CONCAT(p.nombre, ' ', p.apellido) as policia,
               p.cin, g.nombre as grado,
               lg.nombre as lugar_guardia,
               lg.id as lugar_guardia_id,
               gr.puesto,
               gr.observaciones,
               gr.created_at as fecha_registro
        FROM guardias_realizadas gr
        JOIN policias p ON gr.policia_id = p.id
        JOIN grados g ON p.grado_id = g.id
        JOIN lugares_guardias lg ON gr.lugar_guardia_id = lg.id
        WHERE 1=1
    ";
    
    $params = [];
    $types = "";
    
    // Filtro por fecha
    if ($fecha_especifica) {
        $sql .= " AND DATE(gr.fecha_inicio) = ?";
        $params[] = $fecha_especifica;
        $types .= "s";
    } else {
        $sql .= " AND gr.fecha_inicio BETWEEN ? AND ?";
        $params[] = $fecha_inicio;
        $params[] = $fecha_fin;
        $types .= "ss";
    }
    
    // Filtro por lugar de guardia
    if ($lugar_guardia_id) {
        $sql .= " AND lg.id = ?";
        $params[] = $lugar_guardia_id;
        $types .= "i";
    }
    
    $sql .= " ORDER BY lg.nombre ASC, gr.fecha_inicio DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $guardias_result = $stmt->get_result();
    
    // Agrupar por lugar de guardia
    while ($row = $guardias_result->fetch_assoc()) {
        $lugar = $row['lugar_guardia'];
        if (!isset($guardias_data[$lugar])) {
            $guardias_data[$lugar] = [];
        }
        $guardias_data[$lugar][] = $row;
    }
}

// Calcular estadísticas
$total_guardias = array_sum(array_map('count', $guardias_data));
$total_lugares = count($guardias_data);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guardias Realizadas - Sistema RH</title>
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
        .lugar-header {
            background: linear-gradient(45deg, #104c75, #0d3d5c);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .stats-card {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
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
                        <i class="fas fa-shield-alt"></i> Guardias Realizadas
                    </h1>

                    <!-- Filtros -->
                    <div class="card mb-4 no-print">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-filter"></i> Filtros de Búsqueda</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label">Tipo de Filtro:</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="filtro_tipo" id="filtro_periodo" value="periodo" <?= empty($fecha_especifica) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="filtro_periodo">
                                                Período
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="filtro_tipo" id="filtro_especifica" value="especifica" <?= !empty($fecha_especifica) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="filtro_especifica">
                                                Fecha específica
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div id="periodo_fields" <?= !empty($fecha_especifica) ? 'style="display:none"' : '' ?>>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label class="form-label">Fecha Inicio:</label>
                                                    <input type="date" class="form-control" name="fecha_inicio" value="<?= $fecha_inicio ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Fecha Fin:</label>
                                                    <input type="date" class="form-control" name="fecha_fin" value="<?= $fecha_fin ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div id="especifica_fields" <?= empty($fecha_especifica) ? 'style="display:none"' : '' ?>>
                                            <label class="form-label">Fecha Específica:</label>
                                            <input type="date" class="form-control" name="fecha_especifica" value="<?= $fecha_especifica ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">Lugar de Guardia:</label>
                                        <select name="lugar_guardia_id" class="form-select">
                                            <option value="">Todos los lugares</option>
                                            <?php foreach ($lugares_guardias as $lugar): ?>
                                                <option value="<?= $lugar['id'] ?>" <?= $lugar_guardia_id == $lugar['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($lugar['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary me-2">
                                            <i class="fas fa-search"></i> Buscar
                                        </button>
                                        <a href="?" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Limpiar
                                        </a>
                                        <button type="button" onclick="window.print()" class="btn btn-success ms-2">
                                            <i class="fas fa-print"></i> Imprimir
                                        </button>
                                        <a href="index.php" class="btn btn-outline-secondary ms-2">
                                            <i class="fas fa-arrow-left"></i> Volver a Reportes
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Resultados -->
                    <?php if (!empty($guardias_data)): ?>
                        <!-- Estadísticas -->
                        <div class="stats-card">
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <h3><i class="fas fa-shield-alt"></i> <?= $total_guardias ?></h3>
                                    <p class="mb-0">Total de Guardias</p>
                                </div>
                                <div class="col-md-4">
                                    <h3><i class="fas fa-map-marker-alt"></i> <?= $total_lugares ?></h3>
                                    <p class="mb-0">Lugares de Guardia</p>
                                </div>
                                <div class="col-md-4">
                                    <h3><i class="fas fa-calendar"></i> 
                                        <?php 
                                        if ($fecha_especifica) {
                                            echo date('d/m/Y', strtotime($fecha_especifica));
                                        } else {
                                            echo date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin));
                                        }
                                        ?>
                                    </h3>
                                    <p class="mb-0">Período Consultado</p>
                                </div>
                            </div>
                        </div>

                        <!-- Guardias por Lugar -->
                        <?php foreach ($guardias_data as $lugar => $guardias): ?>
                            <div class="card mb-4">
                                <div class="lugar-header">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <h5 class="mb-0">
                                                <i class="fas fa-map-marker-alt"></i> 
                                                <?= htmlspecialchars($lugar) ?>
                                            </h5>
                                        </div>
                                        <div class="col-auto">
                                            <span class="badge bg-light text-dark fs-6">
                                                <?= count($guardias) ?> guardias
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th><i class="fas fa-calendar"></i> Fecha</th>
                                                    <th><i class="fas fa-user"></i> Policía</th>
                                                    <th><i class="fas fa-id-card"></i> CIN</th>
                                                    <th><i class="fas fa-star"></i> Grado</th>
                                                    <th><i class="fas fa-briefcase"></i> Puesto</th>
                                                    <th><i class="fas fa-sticky-note"></i> Observaciones</th>
                                                    <th><i class="fas fa-clock"></i> Registrado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($guardias as $guardia): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= date('d/m/Y', strtotime($guardia['fecha_guardia'])) ?></strong>
                                                        <br><small class="text-muted"><?= date('l', strtotime($guardia['fecha_guardia'])) ?></small>
                                                    </td>
                                                    <td><?= htmlspecialchars($guardia['policia']) ?></td>
                                                    <td><code><?= htmlspecialchars($guardia['cin']) ?></code></td>
                                                    <td>
                                                        <span class="badge bg-primary">
                                                            <?= htmlspecialchars($guardia['grado']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($guardia['puesto'] ?? 'No especificado') ?></td>
                                                    <td>
                                                        <?php if ($guardia['observaciones']): ?>
                                                            <small><?= htmlspecialchars($guardia['observaciones']) ?></small>
                                                        <?php else: ?>
                                                            <span class="text-muted">Sin observaciones</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small><?= date('d/m/Y H:i', strtotime($guardia['fecha_registro'])) ?></small>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                    <?php elseif (!empty($_GET['fecha_inicio']) || !empty($_GET['fecha_especifica'])): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            No se encontraron guardias realizadas con los filtros seleccionados.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Utiliza los filtros de búsqueda para consultar las guardias realizadas.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Manejar cambio de tipo de filtro
        document.querySelectorAll('input[name="filtro_tipo"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const periodoFields = document.getElementById('periodo_fields');
                const especificaFields = document.getElementById('especifica_fields');
                
                if (this.value === 'periodo') {
                    periodoFields.style.display = 'block';
                    especificaFields.style.display = 'none';
                    // Limpiar campo de fecha específica
                    document.querySelector('input[name="fecha_especifica"]').value = '';
                } else {
                    periodoFields.style.display = 'none';
                    especificaFields.style.display = 'block';
                    // Limpiar campos de período
                    document.querySelector('input[name="fecha_inicio"]').value = '';
                    document.querySelector('input[name="fecha_fin"]').value = '';
                }
            });
        });
    </script>
</body>
</html>