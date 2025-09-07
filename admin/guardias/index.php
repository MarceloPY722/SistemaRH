<?php
session_start();
require_once '../../cnx/db_connect.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /SistemaRH/index.php');
    exit();
}

// Obtener lugares de guardia activos
$lugares_query = "SELECT id, nombre FROM lugares_guardias WHERE activo = 1 ORDER BY nombre";
$lugares_result = $conn->prepare($lugares_query);
$lugares_result->execute();

// Función para obtener policías por lugar de guardia con ordenamiento FIFO
function obtenerPoliciasPorLugar($conn, $lugar_id, $limite = 5, $incluir_no_disponibles = false) {
    $condicion_estado = $incluir_no_disponibles ? "" : "AND p.estado = 'DISPONIBLE'";
    
    $query = "
        SELECT 
            vdp.id,
            p.legajo,
            vdp.nombre,
            vdp.apellido,
            vdp.cin,
            vdp.grado,
            lg.posicion,
            lg.ultima_guardia_fecha,
            lg.fecha_disponible,
            p.comisionamiento,
            p.estado,
            vdp.zona,
            CASE 
                WHEN p.estado = 'NO DISPONIBLE' THEN 'NO DISPONIBLE (AUSENCIA)'
                WHEN lg.fecha_disponible IS NOT NULL AND lg.fecha_disponible > CURDATE() THEN 'NO DISPONIBLE (15 días)'
                ELSE vdp.disponibilidad
            END as disponibilidad
        FROM lista_guardias lg
        INNER JOIN vista_disponibilidad_policias vdp ON lg.policia_id = vdp.id
        INNER JOIN policias p ON lg.policia_id = p.id
        WHERE p.lugar_guardia_id = ? 
            AND p.activo = 1
            $condicion_estado
        ORDER BY 
            p.estado DESC,
            lg.posicion ASC,
            p.legajo ASC
        LIMIT " . (int)$limite . "
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$lugar_id]);
    return $stmt;
}

// Función para buscar policías
function buscarPolicias($conn, $termino) {
    $termino = "%" . $termino . "%";
    $query = "
        SELECT 
            vdp.id,
            p.legajo,
            vdp.nombre,
            vdp.apellido,
            vdp.cin,
            vdp.grado,
            vdp.lugar_guardia,
            lg.posicion,
            p.comisionamiento,
            vdp.zona,
            vdp.disponibilidad
        FROM vista_disponibilidad_policias vdp
        INNER JOIN policias p ON vdp.id = p.id
        LEFT JOIN lista_guardias lg ON p.id = lg.policia_id
        WHERE p.activo = 1
            AND p.estado = 'DISPONIBLE'
            AND (vdp.nombre LIKE ? OR vdp.apellido LIKE ? OR p.legajo LIKE ? OR vdp.cin LIKE ?)
        ORDER BY vdp.apellido, vdp.nombre
        LIMIT 20
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$termino, $termino, $termino, $termino]);
    return $stmt;
}

// Manejar búsqueda AJAX
if (isset($_GET['buscar']) && isset($_GET['termino'])) {
    header('Content-Type: application/json');
    $termino = $_GET['termino'];
    $resultado = buscarPolicias($conn, $termino);
    
    $policias = [];
    while ($row = $resultado->fetch(PDO::FETCH_ASSOC)) {
        $policias[] = $row;
    }
    
    echo json_encode($policias);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Guardias - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #104c75;
            --secondary-color: #0d3d5c;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-bg: #f8f9fa;
            --border-color: #dee2e6;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            background-color: var(--light-bg);
            max-width: calc(100vw - 270px);
            overflow-x: hidden;
        }
        
        .guardia-card {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 15px;
            box-shadow: 0 4px 12px rgba(16, 76, 117, 0.1);
            background: white;
            transition: all 0.3s ease;
            width: 100%;
            max-width: 100%;
        }
        
        .guardia-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 76, 117, 0.15);
        }
        
        .guardia-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 15px 20px;
            border-radius: 12px 12px 0 0;
            font-weight: 600;
            font-size: 1.1em;
        }
        
        .policia-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.2s ease;
            width: 100%;
            box-sizing: border-box;
        }
        
        .policia-item:hover {
            background-color: rgba(16, 76, 117, 0.05);
            transform: translateX(5px);
        }
        
        .policia-item:last-child {
            border-bottom: none;
        }
        
        .posicion-badge {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(16, 76, 117, 0.3);
        }
        
        .disponibilidad-badge {
            font-size: 11px;
            padding: 4px 8px;
        }
        
        .disponible { background-color: var(--success-color); }
        .no-disponible { background-color: var(--danger-color); }
        
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-top: none;
            border-radius: 0 0 12px 12px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 15px rgba(16, 76, 117, 0.15);
        }
        
        .search-item {
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
        }
        
        .search-item:hover {
            background-color: #f8f9fa;
        }
        
        .search-item:last-child {
            border-bottom: none;
        }
        
        .generar-btn {
            background: linear-gradient(135deg, var(--success-color), #20c997);
            border: none;
            padding: 15px 30px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            transition: all 0.3s ease;
        }
        
        .generar-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
        }
        
        .info-text {
            color: #6c757d;
            font-size: 14px;
            font-style: italic;
        }
        
        .empty-slot {
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-style: italic;
        }
        
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(16, 76, 117, 0.1);
            border-left: 5px solid var(--primary-color);
        }
        
        .search-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(16, 76, 117, 0.1);
            width: 100%;
            max-width: 100%;
            position: relative;
        }
        
        .search-container input {
            width: 100%;
            padding: 12px 20px;
            border: 2px solid var(--border-color);
            border-radius: 25px;
            font-size: 16px;
            outline: none;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .guardias-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            width: 100%;
            max-width: 100%;
        }
        
        .policia-list {
            max-height: 400px;
            overflow-y: auto;
            width: 100%;
        }
        
        .ver-mas-container {
            border-top: 1px solid #f0f0f0;
            background-color: #f8f9fa;
            padding: 10px;
            text-align: center;
        }
        
        .ver-mas-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 20px;
            font-size: 12px;
            padding: 8px 16px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .ver-mas-btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(16, 76, 117, 0.2);
        }
        
        .toggle-icon {
            transition: transform 0.3s ease;
            color: var(--primary-color);
        }
        
        .toggle-icon.rotated {
            transform: rotate(180deg);
        }
        
        .guardia-body.collapsed {
            display: none;
        }
        
        .guardia-header {
            cursor: pointer;
            user-select: none;
        }
        
        .guardia-header:hover {
            background-color: #f8f9fa;
        }
        
        .hidden-policia {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        .no-disponible-item {
            opacity: 0.7;
            background-color: #f8f9fa;
            border-left: 4px solid #dc3545;
        }
        
        .no-disponible-item .badge {
            background-color: #dc3545 !important;
        }
        
        .ver-no-disponibles-btn {
            border-color: #6c757d;
            color: #6c757d;
        }
        
        .ver-no-disponibles-btn:hover {
            background-color: #6c757d;
            color: white;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .empty-slot {
            opacity: 0.6;
            font-style: italic;
            color: #6c757d;
        }
        
        .guardia-row {
            width: 100%;
        }
        
        @media (max-width: 1200px) {
            .main-content {
                margin-left: 260px;
                max-width: calc(100vw - 280px);
            }
        }
        
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                max-width: 100vw;
                padding: 15px;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .generar-btn {
                width: 100%;
                margin-bottom: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 10px;
            }
            
            .page-header {
                text-align: center;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
            
            .guardia-card {
                margin-bottom: 15px;
            }
            
            .policia-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .policia-info {
                width: 100%;
            }
            
            .status-badges {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <?php include '../inc/sidebar.php'; ?>
            
            <!-- Contenido Principal -->
            <div class="main-content">
                <!-- Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-2" style="color: var(--primary-color);"><i class="fas fa-shield-alt me-2"></i>Gestión de Guardias</h1>
                            <p class="text-muted mb-0">Sistema FIFO - Los primeros en la lista son los próximos en guardia</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-success generar-btn" onclick="generarGuardia()">
                                <i class="fas fa-plus-circle me-2"></i>Generar Guardia
                            </button>
                            <a href="../config/config_guard.php" class="btn btn-warning resetear-btn" style="background: linear-gradient(135deg, #ffc107, #ff9800); border: none; padding: 15px 20px; border-radius: 10px; box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3); transition: all 0.3s ease;">
                                <i class="fas fa-sync-alt me-2"></i>Resetear Guardias
                            </a>
                        </div>
                    </div>
                </div>

               

                <!-- Lista de Lugares de Guardia -->
                <div class="guardias-container">
                    <?php if ($lugares_result->rowCount() > 0): ?>
                        <?php while ($lugar = $lugares_result->fetch(PDO::FETCH_ASSOC)): ?>
                            <div class="guardia-row">
                                <div class="guardia-card">
                                    <div class="guardia-header" style="cursor: pointer;" onclick="toggleGuardia(<?php echo $lugar['id']; ?>)">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($lugar['nombre'] ?? ''); ?></span>
                            <i class="fas fa-chevron-down toggle-icon" id="toggle-icon-<?php echo $lugar['id']; ?>"></i>
                        </div>
                    </div>
                                    <div class="guardia-body" id="guardia-body-<?php echo $lugar['id']; ?>">
                                <?php 
                                $policias_disponibles = obtenerPoliciasPorLugar($conn, $lugar['id'], 20, false); // Solo disponibles
                                $policias_todos = obtenerPoliciasPorLugar($conn, $lugar['id'], 50, true); // Todos incluidos no disponibles
                                $count = 0;
                                $totalDisponibles = $policias_disponibles->rowCount();
                                $totalTodos = $policias_todos->rowCount();
                                $totalNoDisponibles = $totalTodos - $totalDisponibles;
                                ?>
                                
                                <!-- Policías Disponibles -->
                                <?php if ($policias_disponibles->rowCount() > 0): ?>
                                    <?php while ($policia = $policias_disponibles->fetch(PDO::FETCH_ASSOC)): ?>
                                        <?php 
                                        $count++; 
                                        $isHidden = $count > 4;
                                        ?>
                                        <div class="policia-item disponible-item <?php echo $isHidden ? 'hidden-policia' : ''; ?>" <?php echo $isHidden ? 'style="display: none;"' : ''; ?>>
                                            <div class="d-flex align-items-center">
                                                <span class="posicion-badge me-3"><?php echo $policia['posicion']; ?></span>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold">
                                                        <?php echo htmlspecialchars(($policia['apellido'] ?? '') . ', ' . ($policia['nombre'] ?? '')); ?>
                                                    </div>
                                                    <div class="small text-muted">
                                                        <span class="me-3"><i class="fas fa-id-badge me-1"></i>Legajo: <?php echo $policia['legajo']; ?></span>
                                                        <span class="me-3"><i class="fas fa-star me-1"></i><?php echo htmlspecialchars($policia['grado'] ?? ''); ?></span>
                                                        <span class="me-3"><i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($policia['cin'] ?? ''); ?></span>
                                                        <span><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($policia['zona'] ?? 'N/A'); ?></span>
                                                    </div>
                                                    <?php if (!empty($policia['ultima_guardia_fecha'])): ?>
                                                        <div class="small text-info mt-1">
                                                            <i class="fas fa-clock me-1"></i>Última guardia: <?php echo date('d/m/Y', strtotime($policia['ultima_guardia_fecha'])); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="small text-muted mt-1">
                                                            <i class="fas fa-clock me-1"></i>Sin guardias previas
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($policia['comisionamiento'])): ?>
                                                        <div class="small text-warning mt-1">
                                                            <i class="fas fa-briefcase me-1"></i><?php echo htmlspecialchars($policia['comisionamiento'] ?? ''); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-end">
                                                    <?php 
                                                    $disponibilidad_class = 'disponible';
                                                    if (strpos($policia['disponibilidad'], 'NO DISPONIBLE') !== false) {
                                                        $disponibilidad_class = 'no-disponible';
                                                    }
                                                    ?>
                                                    <span class="badge disponibilidad-badge <?php echo $disponibilidad_class; ?>">
                                                        <?php echo $policia['disponibilidad']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                    
                                    <!-- Policías No Disponibles (ocultos por defecto) -->
                                    <?php 
                                    $policias_todos->execute([$lugar['id']]); // Re-ejecutar consulta
                                    $count_no_disponibles = 0;
                                    ?>
                                    <?php while ($policia = $policias_todos->fetch(PDO::FETCH_ASSOC)): ?>
                                        <?php if ($policia['estado'] == 'NO DISPONIBLE'): ?>
                                            <?php $count_no_disponibles++; ?>
                                            <div class="policia-item no-disponible-item" style="display: none; opacity: 0.7;">
                                                <div class="d-flex align-items-center">
                                                    <span class="posicion-badge me-3 bg-secondary"><?php echo $policia['posicion']; ?></span>
                                                    <div class="flex-grow-1">
                                                        <div class="fw-bold text-muted">
                                                            <?php echo htmlspecialchars(($policia['apellido'] ?? '') . ', ' . ($policia['nombre'] ?? '')); ?>
                                                        </div>
                                                        <div class="small text-muted">
                                                            <span class="me-3"><i class="fas fa-id-badge me-1"></i>Legajo: <?php echo $policia['legajo']; ?></span>
                                                            <span class="me-3"><i class="fas fa-star me-1"></i><?php echo htmlspecialchars($policia['grado'] ?? ''); ?></span>
                                                            <span class="me-3"><i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($policia['cin'] ?? ''); ?></span>
                                                            <span><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($policia['zona'] ?? 'N/A'); ?></span>
                                                        </div>
                                                        <?php if (!empty($policia['comisionamiento'])): ?>
                                                            <div class="small text-warning mt-1">
                                                                <i class="fas fa-briefcase me-1"></i><?php echo htmlspecialchars($policia['comisionamiento'] ?? ''); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-end">
                                                        <?php 
                                                        $disponibilidad_class = 'no-disponible';
                                                        if ($policia['disponibilidad'] == 'NO DISPONIBLE (15 días)') {
                                                            $disponibilidad_class = 'no-disponible';
                                                        } elseif ($policia['disponibilidad'] == 'NO DISPONIBLE (AUSENCIA)') {
                                                            $disponibilidad_class = 'no-disponible';
                                                        }
                                                        ?>
                                                        <span class="badge disponibilidad-badge <?php echo $disponibilidad_class; ?>">
                                                            <?php echo $policia['disponibilidad']; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endwhile; ?>
                                    
                                    <?php if ($totalDisponibles > 4): ?>
                                        <div class="ver-mas-container text-center py-2">
                                            <button class="btn btn-outline-primary btn-sm ver-mas-btn" onclick="toggleVerMas(<?php echo $lugar['id']; ?>, <?php echo $totalDisponibles; ?>)">
                                                <i class="fas fa-chevron-down"></i> Ver más disponibles (<?php echo $totalDisponibles - 4; ?> restantes)
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($totalNoDisponibles > 0): ?>
                                        <div class="ver-no-disponibles-container text-center py-2">
                                            <button class="btn btn-outline-secondary btn-sm ver-no-disponibles-btn" onclick="toggleVerNoDisponibles(<?php echo $lugar['id']; ?>, <?php echo $totalNoDisponibles; ?>)">
                                                <i class="fas fa-eye-slash"></i> Ver no disponibles (<?php echo $totalNoDisponibles; ?>)
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    // Llenar espacios vacíos hasta 4 solo si hay menos de 4 policías disponibles
                                    if ($totalDisponibles < 4):
                                        for ($i = $totalDisponibles; $i < 4; $i++): 
                                    ?>
                                        <div class="policia-item empty-slot">
                                            <i class="fas fa-user-slash me-2"></i>Posición vacía
                                        </div>
                                    <?php 
                                        endfor;
                                    endif;
                                    ?>
                                <?php else: ?>
                                    <?php for ($i = 0; $i < 4; $i++): ?>
                                        <div class="policia-item empty-slot">
                                            <i class="fas fa-user-slash me-2"></i>Posición vacía
                                        </div>
                                    <?php endfor; ?>
                                <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="guardia-row">
                            <div class="alert alert-warning text-center">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No hay lugares de guardia configurados.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Guardias Generadas Recientes -->
                <div class="mt-4">
                    <div class="card" style="border-radius: 12px; box-shadow: 0 2px 10px rgba(16, 76, 117, 0.1);">
                        <div class="card-body">
                            <h5 class="card-title" style="color: var(--primary-color);"><i class="fas fa-calendar-check me-2"></i>Guardias Generadas Recientes</h5>
                            <?php
                            // Obtener las últimas 5 guardias generadas
                            $guardias_query = "
                                SELECT 
                                    gg.id,
                                    gg.fecha_guardia,
                                    gg.orden_dia,
                                    gg.region,
                                    gg.created_at,
                                    COUNT(ggd.id) as total_asignados
                                FROM guardias_generadas gg
                                LEFT JOIN guardias_generadas_detalle ggd ON gg.id = ggd.guardia_generada_id
                                GROUP BY gg.id, gg.fecha_guardia, gg.orden_dia, gg.region, gg.created_at
                                ORDER BY gg.fecha_guardia DESC, gg.created_at DESC
                                LIMIT 5
                            ";
                            $guardias_result = $conn->prepare($guardias_query);
                            $guardias_result->execute();
                            ?>
                            
                            <?php if ($guardias_result->rowCount() > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th><i class="fas fa-calendar me-1"></i>Fecha</th>
                                                <th><i class="fas fa-file-alt me-1"></i>Orden del Día</th>
                                                <th><i class="fas fa-map-marker-alt me-1"></i>Región</th>
                                                <th><i class="fas fa-users me-1"></i>Efectivos</th>
                                                <th><i class="fas fa-clock me-1"></i>Generada</th>
                                                <th><i class="fas fa-eye me-1"></i>Personal Asignado</th>
                                                <th><i class="fas fa-cogs me-1"></i>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($guardia = $guardias_result->fetch(PDO::FETCH_ASSOC)): ?>
                                                <?php
                                                // Obtener el personal asignado para esta guardia
                                                $personal_query = "
                                                    SELECT 
                                                        p.nombre,
                                                        p.apellido,
                                                        p.legajo,
                                                        g.nombre as grado,
                                                        ggd.posicion_asignacion
                                                    FROM guardias_generadas_detalle ggd
                                                    JOIN policias p ON ggd.policia_id = p.id
                                                    LEFT JOIN grados g ON p.grado_id = g.id
                                                    WHERE ggd.guardia_generada_id = ?
                                                    ORDER BY ggd.posicion_asignacion
                                                ";
                                                $personal_stmt = $conn->prepare($personal_query);
                                                $personal_stmt->execute([$guardia['id']]);
                                                $personal_asignado = $personal_stmt->fetchAll();
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo date('d/m/Y', strtotime($guardia['fecha_guardia'])); ?></strong>
                                                        <?php if (date('w', strtotime($guardia['fecha_guardia'])) == 0): ?>
                                                            <span class="badge bg-warning text-dark ms-1">DOM</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($guardia['orden_dia'] ?? 'N/A'); ?></td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($guardia['region']); ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success"><?php echo $guardia['total_asignados']; ?></span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted"><?php echo date('d/m H:i', strtotime($guardia['created_at'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php if (count($personal_asignado) > 0): ?>
                                                            <div class="personal-asignado" style="max-height: 100px; overflow-y: auto;">
                                                                <?php foreach (array_slice($personal_asignado, 0, 3) as $persona): ?>
                                                                    <div class="small mb-1">
                                                                        <strong><?php echo htmlspecialchars($persona['apellido'] . ', ' . $persona['nombre']); ?></strong>
                                                                        <span class="text-muted">(<?php echo htmlspecialchars($persona['grado'] ?? 'N/A'); ?>)</span>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                                <?php if (count($personal_asignado) > 3): ?>
                                                                    <small class="text-muted">... y <?php echo count($personal_asignado) - 3; ?> más</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">Sin personal</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="ver_guardias.php?fecha=<?php echo $guardia['fecha_guardia']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" 
                                                           title="Ver detalles">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="ver_guardias.php?fecha=<?php echo $guardia['fecha_guardia']; ?>&pdf=1" 
                                                           class="btn btn-sm btn-outline-info ms-1" 
                                                           title="Descargar PDF">
                                                            <i class="fas fa-file-pdf"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-calendar-times fa-3x mb-3"></i>
                                    <p class="mb-0">No hay guardias generadas aún</p>
                                    <small>Las guardias generadas aparecerán aquí</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Información adicional -->
                <div class="mt-4">
                    <div class="card" style="border-radius: 12px; box-shadow: 0 2px 10px rgba(16, 76, 117, 0.1);">
                        <div class="card-body">
                            <h5 class="card-title" style="color: var(--primary-color);"><i class="fas fa-info-circle me-2"></i>Información del Sistema FIFO</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 style="color: var(--primary-color);">Orden de Prioridad:</h6>
                                    <ul class="list-unstyled info-text">
                                        <li><i class="fas fa-arrow-right me-2" style="color: var(--primary-color);"></i>1. Posición en lista (FIFO)</li>
                                        <li><i class="fas fa-arrow-right me-2" style="color: var(--primary-color);"></i>2. Nivel jerárquico del grado</li>
                                        <li><i class="fas fa-arrow-right me-2" style="color: var(--primary-color);"></i>3. Número de legajo (menor primero)</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6 style="color: var(--primary-color);">Estados de Disponibilidad:</h6>
                                    <ul class="list-unstyled info-text">
                                        <li><span class="badge disponible me-2">DISPONIBLE</span>Listo para guardia</li>
                                        <li><span class="badge ausente me-2">AUSENTE</span>Con ausencia activa</li>
                                        <li><span class="badge comisionado me-2">COMISIONADO</span>En comisión especial</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Búsqueda en tiempo real
        let searchTimeout;
        const searchInput = document.getElementById('searchInput');
        const searchResults = document.getElementById('searchResults');

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const termino = this.value.trim();
            
            if (termino.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch(`?buscar=1&termino=${encodeURIComponent(termino)}`)
                    .then(response => response.json())
                    .then(data => {
                        mostrarResultadosBusqueda(data);
                    })
                    .catch(error => {
                        console.error('Error en búsqueda:', error);
                    });
            }, 300);
        });

        function mostrarResultadosBusqueda(policias) {
            if (policias.length === 0) {
                searchResults.innerHTML = '<div class="search-item text-muted">No se encontraron resultados</div>';
            } else {
                let html = '';
                policias.forEach(policia => {
                    const disponibilidadClass = policia.disponibilidad.toLowerCase().replace(' ', '-');
                    html += `
                        <div class="search-item">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="fw-bold">${policia.apellido}, ${policia.nombre}</div>
                                    <div class="small text-muted">
                                        <span class="me-3">Legajo: ${policia.legajo}</span>
                                        <span class="me-3">${policia.grado}</span>
                                        <span class="me-3">Pos: ${policia.posicion || 'N/A'}</span>
                                        <span class="me-3">${policia.lugar_guardia || 'Sin asignar'}</span>
                                        <span>Zona: ${policia.zona || 'N/A'}</span>
                                    </div>
                                    ${policia.comisionamiento ? `<div class="small text-warning">Comisión: ${policia.comisionamiento}</div>` : ''}
                                </div>
                                <span class="badge disponibilidad-badge ${disponibilidadClass}">
                                    ${policia.disponibilidad}
                                </span>
                            </div>
                        </div>
                    `;
                });
                searchResults.innerHTML = html;
            }
            searchResults.style.display = 'block';
        }

        // Ocultar resultados al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });

        // Función para generar guardia
        function generarGuardia() {
            window.location.href = 'generar_guardia_interface.php';
        }
        
        // Función para mostrar/ocultar más policías disponibles
        function toggleVerMas(lugarId, totalPolicias) {
            const guardiaBody = document.getElementById(`guardia-body-${lugarId}`);
            const hiddenPolicias = guardiaBody.querySelectorAll('.disponible-item.hidden-policia');
            const verMasBtn = guardiaBody.querySelector('.ver-mas-btn');
            const icon = verMasBtn.querySelector('i');
            
            const isExpanded = hiddenPolicias[0] && hiddenPolicias[0].style.display !== 'none';
            
            hiddenPolicias.forEach(policia => {
                policia.style.display = isExpanded ? 'none' : 'block';
            });
            
            if (isExpanded) {
                icon.className = 'fas fa-chevron-down';
                verMasBtn.innerHTML = `<i class="fas fa-chevron-down"></i> Ver más disponibles (${totalPolicias - 4} restantes)`;
            } else {
                icon.className = 'fas fa-chevron-up';
                verMasBtn.innerHTML = `<i class="fas fa-chevron-up"></i> Ver menos disponibles`;
            }
        }
        
        // Función para mostrar/ocultar policías no disponibles
        function toggleVerNoDisponibles(lugarId, totalNoDisponibles) {
            const guardiaBody = document.getElementById(`guardia-body-${lugarId}`);
            const noDisponiblesPolicias = guardiaBody.querySelectorAll('.no-disponible-item');
            const verNoDisponiblesBtn = guardiaBody.querySelector('.ver-no-disponibles-btn');
            const icon = verNoDisponiblesBtn.querySelector('i');
            
            const isVisible = noDisponiblesPolicias[0] && noDisponiblesPolicias[0].style.display !== 'none';
            
            noDisponiblesPolicias.forEach(policia => {
                policia.style.display = isVisible ? 'none' : 'block';
            });
            
            if (isVisible) {
                icon.className = 'fas fa-eye-slash';
                verNoDisponiblesBtn.innerHTML = `<i class="fas fa-eye-slash"></i> Ver no disponibles (${totalNoDisponibles})`;
            } else {
                icon.className = 'fas fa-eye';
                verNoDisponiblesBtn.innerHTML = `<i class="fas fa-eye"></i> Ocultar no disponibles`;
            }
        }
        
        // Función para colapsar/expandir lugar de guardia
        function toggleGuardia(lugarId) {
            const guardiaBody = document.getElementById(`guardia-body-${lugarId}`);
            const toggleIcon = document.getElementById(`toggle-icon-${lugarId}`);
            
            guardiaBody.classList.toggle('collapsed');
            toggleIcon.classList.toggle('rotated');
        }

        // Auto-refresh cada 30 segundos para mantener datos actualizados
        setInterval(function() {
            if (!searchInput.value.trim()) {
                location.reload();
            }
        }, 30000);
        
        // Inicializar todos los lugares de guardia como expandidos
        document.addEventListener('DOMContentLoaded', function() {
            // Todos los lugares inician expandidos por defecto
        });
        

    </script>
</body>
</html>