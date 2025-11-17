<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

$grados = $conn->query("SELECT tg.*, g.nombre as categoria_nombre FROM tipo_grados tg JOIN grados g ON tg.grado_id = g.id ORDER BY g.nivel_jerarquia ASC, tg.nivel_jerarquia ASC");

$categorias_grados = $conn->query("SELECT DISTINCT g.nombre as categoria_nombre, g.nivel_jerarquia FROM grados g ORDER BY g.nivel_jerarquia ASC");
// Obtener tipos específicos de grados para el segundo filtro
$tipos_grados = $conn->query("SELECT tg.*, g.nombre as categoria_nombre FROM tipo_grados tg JOIN grados g ON tg.grado_id = g.id ORDER BY g.nivel_jerarquia ASC, tg.nivel_jerarquia ASC");
$especialidades = $conn->query("SELECT * FROM especialidades ORDER BY nombre ASC");
$regiones = $conn->query("SELECT * FROM regiones ORDER BY nombre ASC");
$lugares_guardias = $conn->query("SELECT * FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");
// Lista deduplicada solo para filtro de Guardia
$lugares_guardias_filtro = $conn->query("SELECT TRIM(nombre) AS nombre FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");
// Construir lista única normalizando espacios para evitar clones
$guardias_nombres = [];
$guardias_set = [];
foreach ($lugares_guardias_filtro->fetchAll(PDO::FETCH_COLUMN, 0) as $nombre) {
    $normalized = strtolower(preg_replace('/\s+/', ' ', trim($nombre)));
    if (!isset($guardias_set[$normalized])) {
        $guardias_set[$normalized] = true;
        $guardias_nombres[] = trim($nombre);
    }
}

// Procesar formulario de nuevo policía
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'crear') {
    $legajo = (int)trim($_POST['legajo']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $cin = trim($_POST['cin']);
    $genero = $_POST['genero'];
    $grado_id = $_POST['grado_id'];
    $especialidad_id = $_POST['especialidad_id'] ?: null;
    $comisionamiento = trim($_POST['comisionamiento']);
    $telefono = trim($_POST['telefono']);
    $region_id = $_POST['region_id'];
    $lugar_guardia_id = $_POST['lugar_guardia_id'] ?: null;
    
    $sql = "INSERT INTO policias (legajo, nombre, apellido, cin, genero, grado_id, especialidad_id, comisionamiento, telefono, region_id, lugar_guardia_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([$legajo, $nombre, $apellido, $cin, $genero, $grado_id, $especialidad_id, $comisionamiento, $telefono, $region_id, $lugar_guardia_id])) {
        $mensaje = "<div class='alert alert-success'>Policía registrado exitosamente</div>";
    } else {
        $mensaje = "<div class='alert alert-danger'>Error al registrar policía</div>";
    }
}

$policias = $conn->query("
    SELECT p.*, tg.nombre as grado_nombre, tg.abreviatura as grado_abreviatura, g.nombre as categoria_nombre, 
           e.nombre as especialidad_nombre, lg.nombre as lugar_guardia_nombre, r.nombre as region_nombre
    FROM policias p
    LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
    LEFT JOIN grados g ON tg.grado_id = g.id
    LEFT JOIN especialidades e ON p.especialidad_id = e.id
    LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
    LEFT JOIN regiones r ON p.region_id = r.id
    WHERE p.activo = 1
    ORDER BY g.nivel_jerarquia ASC, tg.nivel_jerarquia ASC, p.legajo ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Policías - Sistema RH</title>
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
        .btn-primary:hover {
            background: linear-gradient(45deg, #0d3d5c, #104c75);
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        .card-body {
            padding: 10px;
        }
        .card-header {
            padding: 8px 15px;
        }
        .main-content {
            padding: 15px;
        }
        .page-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }
        .table {
            margin-bottom: 0;
        }
        .table th {
            background-color: #34495e;
            color: white;
            border: none;
            padding: 8px 6px;
            white-space: nowrap;
        }
        .table td {
            padding: 6px 6px;
            vertical-align: middle;
            border-bottom: 1px solid #dee2e6;
        }
        .table-responsive {
            font-size: 12px;
        }
        
        /* Estilos para el buscador */
        .search-container {
            position: relative;
            width: 100%;
        }
        .search-input {
            border-radius: 6px;
            border: 1px solid #ced4da;
            padding: 6px 45px 6px 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            height: 31px;
        }
        .search-input:focus {
            border-color: #104c75;
            box-shadow: 0 0 0 0.2rem rgba(16, 76, 117, 0.25);
            outline: none;
        }
        .search-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 14px;
            pointer-events: none;
        }
        .clear-search {
            position: absolute;
            right: 35px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            font-size: 12px;
            cursor: pointer;
            display: none;
            padding: 2px;
        }
        .clear-search:hover {
            color: #dc3545;
        }
        .search-results-info {
            font-size: 14px;
            color: #6c757d;
            margin-top: 10px;
        }
        .highlight {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
        }
        .no-results {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #495057;
        }
        .header-controls {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        @media (max-width: 768px) {
            .header-controls {
                flex-direction: column;
                align-items: stretch;
            }
            .search-container {
                max-width: 100%;
            }
            .d-flex.gap-3 {
                flex-direction: column;
                gap: 10px !important;
            }
        }
    </style>
</head>
<body>
  

    <div class="container-fluid">
        <div class="row">
            <?php 
            $_GET['page'] = 'policias';
            include '../inc/sidebar.php'; 
            ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <h1 class="page-title">
                        <i class="fas fa-users"></i> Gestión de Policías
                    </h1>

                    <?php if (isset($mensaje)) echo $mensaje; ?>

                    <div class="header-controls" style="margin-bottom: 10px;">
                        <div class="d-flex gap-2 mb-2">
                            <a href="agregar.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Nuevo
                            </a>
                            <a href="deshabilitados.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-user-slash"></i> Deshabilitados
                            </a>
                        </div>
                        
                        <!-- Buscador en tiempo real -->
                        <div class="d-flex gap-2 align-items-end flex-wrap mb-2">
                            <div class="filter-group">
                                <label class="form-label mb-1" style="font-size: 12px;">Buscar:</label>
                                <div class="search-container" style="min-width: 200px;">
                                    <input type="text" class="form-control search-input" id="buscarPersonal" placeholder="Nombre, legajo, CIN..." style="height: 30px; font-size: 12px;">
                                    <button type="button" class="clear-search" id="limpiarBusqueda">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <i class="fas fa-search search-icon"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2 align-items-end flex-wrap">
                            <div class="filter-group">
                                <label class="form-label mb-1" style="font-size: 11px;">Guardia:</label>
                                <select class="form-select form-select-sm" id="filtroLugarGuardia" style="min-width: 120px; height: 30px; font-size: 11px;">
                                    <option value="">Todos</option>
                                    <option value="sin_asignar">Sin asignar</option>
                                    <?php foreach ($guardias_nombres as $nombre_guardia): ?>
                                        <option value="<?php echo htmlspecialchars($nombre_guardia); ?>"><?php echo htmlspecialchars($nombre_guardia); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="form-label mb-1" style="font-size: 11px;">Región:</label>
                                <select class="form-select form-select-sm" id="filtroRegion" style="min-width: 100px; height: 30px; font-size: 11px;">
                                    <option value="">Todas</option>
                                    <?php 
                                    foreach ($regiones as $region): 
                                    ?>
                                    <option value="<?php echo htmlspecialchars($region['nombre']); ?>"><?php echo htmlspecialchars($region['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="form-label mb-1" style="visibility: hidden; font-size: 11px;">Acción:</label>
                                <button type="button" id="limpiarFiltros" class="btn btn-outline-secondary btn-sm" style="height: 30px; font-size: 11px; padding: 4px 8px;">
                                    <i class="fas fa-times"></i> Limpiar
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5><i class="fas fa-list"></i> Lista de Policías Activos</h5>
                        </div>
                        <div class="card-body">
                            <!-- Información de resultados de búsqueda -->
                            <div id="searchInfo" class="search-results-info" style="display: none;"></div>
                            
                            <div class="table-responsive">
                                <table class="table table-striped" id="policiasTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 8%;">Legajo</th>
                            <th style="width: 10%;">CIN</th>
                            <th style="width: 20%;">Nombre</th>
                            <th style="width: 27%;">Grado</th>
                            <th style="width: 10%;">Teléfono</th>
                            <th style="width: 12%;">Guardia</th>
                            <th style="width: 8%;">Región</th>
                            <th style="width: 5%;">Acc.</th>
                                        </tr>
                                    </thead>
                                    <tbody id="policiasTableBody">
                                        <?php while ($policia = $policias->fetch()): ?>
                                        <tr class="policia-row"
                                             data-search="<?php echo strtolower($policia['legajo'] . ' ' . $policia['cin'] . ' ' . $policia['nombre'] . ' ' . $policia['apellido'] . ' ' . $policia['grado_nombre'] . ' ' . $policia['categoria_nombre'] . ' ' . $policia['telefono'] . ' ' . ($policia['lugar_guardia_nombre'] ?: '') . ' ' . $policia['region_nombre']); ?>"
                                             data-region="<?php echo htmlspecialchars(trim($policia['region_nombre'] ?? '')); ?>"
                                             data-categoria="<?php echo htmlspecialchars(trim($policia['categoria_nombre'] ?? '')); ?>"
                                             data-tipo="<?php echo htmlspecialchars(trim($policia['grado_nombre'] ?? '')); ?>"
                                             data-guardia="<?php echo $policia['lugar_guardia_nombre'] ? htmlspecialchars(trim($policia['lugar_guardia_nombre'])) : 'sin_asignar'; ?>">
                                            <td class="searchable" style="font-size: 11px;"><?php echo $policia['legajo']; ?></td>
                                            <td class="searchable" style="font-size: 11px;"><?php echo $policia['cin']; ?></td>
                                            <td class="searchable" style="font-size: 11px;" title="<?php echo $policia['apellido'] . ', ' . $policia['nombre']; ?>">
                                                <?php 
                                                $nombre_completo = $policia['apellido'] . ', ' . $policia['nombre'];
                                                echo strlen($nombre_completo) > 25 ? substr($nombre_completo, 0, 22) . '...' : $nombre_completo;
                                                ?>
                                            </td>
                                            <td class="searchable" style="font-size: 10px;" title="<?php echo $policia['grado_nombre']; ?>">
                                                <?php echo $policia['grado_nombre']; ?>
                                            </td>
                                            <td class="searchable" style="font-size: 11px;"><?php echo $policia['telefono']; ?></td>
                                            <td class="searchable" style="font-size: 10px;">
                                                <?php if ($policia['lugar_guardia_nombre']): ?>
                                                    <span class="badge bg-info" style="font-size: 9px; padding: 2px 4px;" title="<?php echo htmlspecialchars($policia['lugar_guardia_nombre']); ?>">
                                                        <?php echo strlen($policia['lugar_guardia_nombre']) > 12 ? substr($policia['lugar_guardia_nombre'], 0, 9) . '...' : htmlspecialchars($policia['lugar_guardia_nombre']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-size: 9px;">N/A</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <span class="badge bg-<?php echo (strtoupper($policia['region_nombre']) == 'CENTRAL') ? 'primary' : 'secondary'; ?>" style="font-size: 9px; padding: 2px 4px;">
                                                    <?php echo strtoupper($policia['region_nombre']) == 'CENTRAL' ? 'CENT' : 'REG'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="editar.php?id=<?php echo $policia['id']; ?>" class="btn btn-xs btn-outline-primary" style="padding: 2px 4px; font-size: 10px;" title="Editar">
                                                        <i class="fas fa-edit" style="font-size: 10px;"></i>
                                                    </a>
                                                    <a href="eliminar.php?id=<?php echo $policia['id']; ?>" class="btn btn-xs btn-outline-danger" style="padding: 2px 4px; font-size: 10px;" title="Eliminar">
                                                        <i class="fas fa-trash" style="font-size: 10px;"></i>
                                                    </a>
                                                    <button class="btn btn-xs btn-outline-info" style="padding: 2px 4px; font-size: 10px;" title="Ver Detalles" onclick="verDetalles(<?php echo $policia['id']; ?>)">
                                                        <i class="fas fa-eye" style="font-size: 10px;"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                                
                                <!-- Mensaje cuando no hay resultados -->
                                <div id="noResults" class="no-results" style="display: none;">
                                    <i class="fas fa-search fa-3x mb-3"></i>
                                    <h5>No se encontraron resultados</h5>
                                    <p>Intenta con otros términos de búsqueda</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo Policía -->
    <div class="modal fade" id="modalNuevoPolicia" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Registrar Nuevo Policía</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="crear">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre *</label>
                                <input type="text" class="form-control" name="nombre" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Apellido *</label>
                                <input type="text" class="form-control" name="apellido" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Legajo *</label>
                                <input type="number" class="form-control" name="legajo" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">CIN *</label>
                                <input type="text" class="form-control" name="cin" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Grado *</label>
                                <select class="form-select" name="grado_id" required>
                                    <option value="">Seleccionar grado...</option>
                                    <?php 
                                    foreach ($grados as $grado): 
                                    ?>
                                    <option value="<?php echo $grado['id']; ?>"><?php echo $grado['nombre']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Especialidad</label>
                                <select class="form-select" name="especialidad_id">
                                    <option value="">Seleccionar especialidad...</option>
                                    <?php 
                                    foreach ($especialidades as $especialidad): 
                                    ?>
                                    <option value="<?php echo $especialidad['id']; ?>"><?php echo $especialidad['nombre']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Comisionamiento</label>
                                <input type="text" class="form-control" name="comisionamiento">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" class="form-control" name="telefono">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Región *</label>
                                <select class="form-select" name="region_id" required>
                                    <option value="">Seleccionar región...</option>
                                    <?php 
                                    if ($regiones->rowCount() > 0) {
                                        foreach ($regiones as $region): 
                                    ?>
                                    <option value="<?php echo $region['id']; ?>"><?php echo htmlspecialchars($region['nombre']); ?></option>
                                    <?php 
                                        endforeach; 
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Lugar de Guardia</label>
                                <select class="form-select" name="lugar_guardia_id">
                                    <option value="">Seleccionar lugar...</option>
                                    <?php 
                                    foreach ($lugares_guardias as $lugar): 
                                    ?>
                                    <option value="<?php echo $lugar['id']; ?>"><?php echo $lugar['nombre']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Zona de Guardia</label>
                                <input type="text" class="form-select" name="zona_guardia" placeholder="Especificar zona...">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Fecha de Ingreso *</label>
                                // Eliminar el campo del modal (línea 366)
                                // <input type="date" class="form-control" name="fecha_ingreso" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="observaciones" class="form-label">Observaciones</label>
                                <textarea class="form-control" id="observaciones" name="observaciones" rows="2"></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Registrar Policía</button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Registrar Policía</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Inicializar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            // Event listeners para los filtros
            document.getElementById('filtroRegion').addEventListener('change', aplicarFiltros);
            document.getElementById('filtroLugarGuardia').addEventListener('change', aplicarFiltros);
            document.getElementById('limpiarFiltros').addEventListener('click', limpiarFiltros);
            
            // Event listeners para el buscador
            const buscarInput = document.getElementById('buscarPersonal');
            const limpiarBusqueda = document.getElementById('limpiarBusqueda');
            
            buscarInput.addEventListener('input', function() {
                aplicarFiltros();
                // Mostrar/ocultar botón de limpiar búsqueda
                if (this.value.length > 0) {
                    limpiarBusqueda.style.display = 'block';
                } else {
                    limpiarBusqueda.style.display = 'none';
                }
            });
            
            limpiarBusqueda.addEventListener('click', function() {
                buscarInput.value = '';
                this.style.display = 'none';
                aplicarFiltros();
            });
        });
        
        // Función para aplicar filtros
        function aplicarFiltros() {
            const normalizar = (str) => (str || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/\s+/g, ' ')
                .trim()
                .toLowerCase();
            const textoBusqueda = normalizar(document.getElementById('buscarPersonal').value);
            const regionSeleccionada = normalizar(document.getElementById('filtroRegion').value);
            const lugarGuardiaSeleccionado = normalizar(document.getElementById('filtroLugarGuardia').value);
            
            const filas = document.querySelectorAll('.policia-row');
            let filasVisibles = 0;
            
            filas.forEach(function(fila) {
                const datosCompletos = normalizar(fila.getAttribute('data-search'));
                const rowRegion = normalizar(fila.dataset.region || '');
                const rowGuardia = normalizar(fila.dataset.guardia || '');
                let mostrar = true;
                
                // Filtro por texto de búsqueda
                if (textoBusqueda && !datosCompletos.includes(textoBusqueda)) {
                    mostrar = false;
                }
                
                // Filtro por región (coincidencia exacta)
                if (regionSeleccionada && rowRegion !== regionSeleccionada) {
                    mostrar = false;
                }
                
                // Filtros de Categoría y Tipo eliminados
                
                // Filtro por lugar de guardia (coincidencia exacta y caso sin asignar)
                if (lugarGuardiaSeleccionado) {
                    if (lugarGuardiaSeleccionado === 'sin_asignar') {
                        if (rowGuardia !== 'sin_asignar') {
                            mostrar = false;
                        }
                    } else if (rowGuardia !== lugarGuardiaSeleccionado) {
                        mostrar = false;
                    }
                }
                
                if (mostrar) {
                    fila.style.display = '';
                    filasVisibles++;
                } else {
                    fila.style.display = 'none';
                }
            });
            
            // Actualizar información de búsqueda
            actualizarInfoBusqueda(filasVisibles, textoBusqueda);
            
            // Mostrar/ocultar mensaje de "no hay resultados"
            const noResults = document.getElementById('noResults');
            if (filasVisibles === 0) {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
            }
        }
        
        // Función para actualizar información de búsqueda
        function actualizarInfoBusqueda(filasVisibles, textoBusqueda) {
            const searchInfo = document.getElementById('searchInfo');
            const totalFilas = document.querySelectorAll('.policia-row').length;
            
            if (textoBusqueda || 
                document.getElementById('filtroRegion').value ||
                document.getElementById('filtroLugarGuardia').value) {
                
                searchInfo.style.display = 'block';
                if (textoBusqueda) {
                    searchInfo.innerHTML = `Mostrando ${filasVisibles} de ${totalFilas} policías que coinciden con "${textoBusqueda}"`;
                } else {
                    searchInfo.innerHTML = `Mostrando ${filasVisibles} de ${totalFilas} policías filtrados`;
                }
            } else {
                searchInfo.style.display = 'none';
            }
        }
        
        // Función para limpiar filtros
        function limpiarFiltros() {
            document.getElementById('buscarPersonal').value = '';
            document.getElementById('filtroRegion').value = '';
            document.getElementById('filtroLugarGuardia').value = '';
            document.getElementById('limpiarBusqueda').style.display = 'none';
            
            // Mostrar todas las filas
            const filas = document.querySelectorAll('.policia-row');
            filas.forEach(function(fila) {
                fila.style.display = '';
            });
            
            // Ocultar mensaje de "no hay resultados" e información de búsqueda
            document.getElementById('noResults').style.display = 'none';
            document.getElementById('searchInfo').style.display = 'none';
        }
        
        // Función para ver detalles
        function verDetalles(id) {
            window.location.href = 'ver_detalles.php?id=' + id;
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

   