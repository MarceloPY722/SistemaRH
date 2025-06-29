<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

// Obtener grados, especialidades, regiones y lugares de guardia para los formularios
$grados = $conn->query("SELECT * FROM grados ORDER BY nivel_jerarquia ASC");
$especialidades = $conn->query("SELECT * FROM especialidades ORDER BY nombre ASC");
$regiones = $conn->query("SELECT * FROM regiones ORDER BY nombre ASC");
$lugares_guardias = $conn->query("SELECT * FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");

// Procesar formulario de nuevo policía
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'crear') {
    $legajo = (int)trim($_POST['legajo']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $cin = trim($_POST['cin']);
    $genero = $_POST['genero'];
    $grado_id = $_POST['grado_id'];
    $especialidad_id = $_POST['especialidad_id'] ?: null;
    $cargo = trim($_POST['cargo']);
    $comisionamiento = trim($_POST['comisionamiento']);
    $telefono = trim($_POST['telefono']);
    $region_id = $_POST['region_id'];
    $lugar_guardia_id = $_POST['lugar_guardia_id'] ?: null;
    
    $sql = "INSERT INTO policias (legajo, nombre, apellido, cin, genero, grado_id, especialidad_id, cargo, comisionamiento, telefono, region_id, lugar_guardia_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssiisssii", $legajo, $nombre, $apellido, $cin, $genero, $grado_id, $especialidad_id, $cargo, $comisionamiento, $telefono, $region_id, $lugar_guardia_id);
    
    if ($stmt->execute()) {
        $mensaje = "<div class='alert alert-success'>Policía registrado exitosamente</div>";
    } else {
        $mensaje = "<div class='alert alert-danger'>Error al registrar policía: " . $conn->error . "</div>";
    }
}

// Obtener lista de policías

$policias = $conn->query("
    SELECT p.*, g.nombre as grado_nombre, e.nombre as especialidad_nombre, lg.nombre as lugar_guardia_nombre, r.nombre as region_nombre
    FROM policias p
    LEFT JOIN grados g ON p.grado_id = g.id
    LEFT JOIN especialidades e ON p.especialidad_id = e.id
    LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
    LEFT JOIN regiones r ON p.region_id = r.id
    WHERE p.activo = 1
    ORDER BY g.nivel_jerarquia ASC, p.apellido ASC");
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

                    <div class="header-controls">
                        <div class="d-flex gap-2">
                            <a href="agregar.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Registrar Nuevo Policía
                            </a>
                            <a href="deshabilitados.php" class="btn btn-outline-secondary">
                                <i class="fas fa-user-slash"></i> Ver Deshabilitados
                            </a>
                        </div>
                        
                        <div class="d-flex gap-3 align-items-end flex-wrap">
                            <div class="filter-group">
                                <label class="form-label mb-1">Región:</label>
                                <select class="form-select form-select-sm" id="filtroRegion" style="min-width: 150px;">
                                    <option value="">Todas las regiones</option>
                                    <?php 
                                    $regiones->data_seek(0);
                                    while ($region = $regiones->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo htmlspecialchars($region['nombre']); ?>"><?php echo htmlspecialchars($region['nombre']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="form-label mb-1">Grado:</label>
                                <select class="form-select form-select-sm" id="filtroGrado" style="min-width: 150px;">
                                    <option value="">Todos los grados</option>
                                    <?php 
                                    $grados->data_seek(0);
                                    while ($grado = $grados->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo htmlspecialchars($grado['nombre']); ?>"><?php echo htmlspecialchars($grado['nombre']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="form-label mb-1">Buscar:</label>
                                <div class="search-container" style="min-width: 250px;">
                                    <input type="text" 
                                           id="searchInput" 
                                           class="form-control search-input" 
                                           placeholder="Buscar policías..." 
                                           autocomplete="off">
                                    <button type="button" id="clearSearch" class="clear-search">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <i class="fas fa-search search-icon"></i>
                                </div>
                            </div>
                            
                            <div class="filter-group">
                                <label class="form-label mb-1" style="visibility: hidden;">Acción:</label>
                                <button type="button" id="limpiarFiltros" class="btn btn-outline-secondary btn-sm">
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
                            <div class="table-responsive">
                                <table class="table table-striped" id="policiasTable">
                                    <thead>
                                        <tr>
                                            <th>Legajo</th>
                                            <th>CIN</th>
                                            <th>Nombre Completo</th>
                                            <th>Grado</th>
                                            <th>Especialidad</th>
                                            <th>Cargo</th>
                                            <th>Teléfono</th>
                                            <th>Región</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="policiasTableBody">
                                        <?php while ($policia = $policias->fetch_assoc()): ?>
                                        <tr class="policia-row" data-search="<?php echo strtolower($policia['legajo'] . ' ' . $policia['cin'] . ' ' . $policia['nombre'] . ' ' . $policia['apellido'] . ' ' . $policia['grado_nombre'] . ' ' . ($policia['especialidad_nombre'] ?: '') . ' ' . $policia['cargo'] . ' ' . $policia['telefono'] . ' ' . $policia['region_nombre']); ?>">
                                            <td class="searchable"><?php echo $policia['legajo']; ?></td>
                                            <td class="searchable"><?php echo $policia['cin']; ?></td>
                                            <td class="searchable"><?php echo $policia['apellido'] . ', ' . $policia['nombre']; ?></td>
                                            <td class="searchable"><?php echo $policia['grado_nombre']; ?></td>
                                            <td class="searchable"><?php echo $policia['especialidad_nombre'] ?: 'N/A'; ?></td>
                                            <td class="searchable"><?php echo $policia['cargo']; ?></td>
                                            <td class="searchable"><?php echo $policia['telefono']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo (strtoupper($policia['region_nombre']) == 'CENTRAL') ? 'primary' : 'secondary'; ?>">
                                                    <?php echo htmlspecialchars($policia['region_nombre']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="editar.php?id=<?php echo $policia['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="eliminar.php?id=<?php echo $policia['id']; ?>" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-info" title="Ver Detalles" onclick="verDetalles(<?php echo $policia['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
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
                                    $grados->data_seek(0);
                                    while ($grado = $grados->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $grado['id']; ?>"><?php echo $grado['nombre']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Especialidad</label>
                                <select class="form-select" name="especialidad_id">
                                    <option value="">Seleccionar especialidad...</option>
                                    <?php 
                                    $especialidades->data_seek(0);
                                    while ($especialidad = $especialidades->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $especialidad['id']; ?>"><?php echo $especialidad['nombre']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cargo</label>
                                <input type="text" class="form-control" name="cargo">
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
                                    if ($regiones->num_rows > 0) {
                                        $regiones->data_seek(0);
                                        while ($region = $regiones->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $region['id']; ?>"><?php echo htmlspecialchars($region['nombre']); ?></option>
                                    <?php 
                                        endwhile; 
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Lugar de Guardia</label>
                                <select class="form-select" name="lugar_guardia_id">
                                    <option value="">Seleccionar lugar...</option>
                                    <?php 
                                    $lugares_guardias->data_seek(0);
                                    while ($lugar = $lugares_guardias->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $lugar['id']; ?>"><?php echo $lugar['nombre']; ?></option>
                                    <?php endwhile; ?>
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
        // Variables globales
        let allRows = [];
        let filteredRows = [];
        
        // Inicializar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            // Guardar todas las filas originales
            allRows = Array.from(document.querySelectorAll('.policia-row'));
            filteredRows = [...allRows];
            
            // Event listeners para los filtros
            document.getElementById('filtroRegion').addEventListener('change', aplicarFiltros);
            document.getElementById('filtroGrado').addEventListener('change', aplicarFiltros);
            document.getElementById('limpiarFiltros').addEventListener('click', limpiarFiltros);
            
            // Funcionalidad del buscador existente (mejorada)
            const searchInput = document.getElementById('searchInput');
            const clearButton = document.getElementById('clearSearch');
            const searchInfo = document.getElementById('searchInfo');
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                
                if (searchTerm.length > 0) {
                    clearButton.style.display = 'block';
                    buscarEnFilasVisibles(searchTerm);
                } else {
                    clearButton.style.display = 'none';
                    mostrarFilas(filteredRows);
                    searchInfo.textContent = '';
                }
            });
            
            clearButton.addEventListener('click', function() {
                searchInput.value = '';
                this.style.display = 'none';
                mostrarFilas(filteredRows);
                searchInfo.textContent = '';
                searchInput.focus();
            });
        });
        
        // Función para aplicar filtros
        function aplicarFiltros() {
            const regionSeleccionada = document.getElementById('filtroRegion').value;
            const gradoSeleccionado = document.getElementById('filtroGrado').value;
            
            filteredRows = allRows.filter(row => {
                const regionRow = row.querySelector('td:nth-child(8) .badge').textContent.trim();
                const gradoRow = row.querySelector('td:nth-child(4)').textContent.trim();
                
                const cumpleRegion = !regionSeleccionada || regionRow === regionSeleccionada;
                const cumpleGrado = !gradoSeleccionado || gradoRow === gradoSeleccionado;
                
                return cumpleRegion && cumpleGrado;
            });
            
            // Limpiar búsqueda al aplicar filtros
            document.getElementById('searchInput').value = '';
            document.getElementById('clearSearch').style.display = 'none';
            
            mostrarFilas(filteredRows);
            actualizarInfoFiltros();
        }
        
        // Función para buscar en filas visibles
        function buscarEnFilasVisibles(searchTerm) {
            const resultados = filteredRows.filter(row => {
                const searchData = row.getAttribute('data-search');
                return searchData.includes(searchTerm);
            });
            
            mostrarFilas(resultados);
            
            // Actualizar información de búsqueda
            const searchInfo = document.getElementById('searchInfo');
            if (resultados.length === 0) {
                searchInfo.textContent = 'No se encontraron resultados';
            } else {
                searchInfo.textContent = `${resultados.length} resultado(s) encontrado(s)`;
            }
        }
        
        // Función para mostrar filas específicas
        function mostrarFilas(filasAMostrar) {
            const tbody = document.getElementById('policiasTableBody');
            const noResults = document.getElementById('noResults');
            
            // Ocultar todas las filas
            allRows.forEach(row => row.style.display = 'none');
            
            if (filasAMostrar.length === 0) {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
                filasAMostrar.forEach(row => row.style.display = '');
            }
        }
        
        // Función para limpiar filtros
        function limpiarFiltros() {
            document.getElementById('filtroRegion').value = '';
            document.getElementById('filtroGrado').value = '';
            document.getElementById('searchInput').value = '';
            document.getElementById('clearSearch').style.display = 'none';
            
            filteredRows = [...allRows];
            mostrarFilas(filteredRows);
            
            document.getElementById('searchInfo').textContent = '';
            actualizarInfoFiltros();
        }
        
        // Función para actualizar información de filtros
        function actualizarInfoFiltros() {
            const searchInfo = document.getElementById('searchInfo');
            const totalVisible = filteredRows.length;
            const totalGeneral = allRows.length;
            
            if (totalVisible < totalGeneral) {
                searchInfo.textContent = `Mostrando ${totalVisible} de ${totalGeneral} policías`;
            } else {
                searchInfo.textContent = '';
            }
        }
        
        // Función existente para ver detalles
        function verDetalles(id) {
            // Implementar según necesidades
            alert('Ver detalles del policía ID: ' + id);
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>