<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

// Obtener grados y especialidades para los formularios
$grados = $conn->query("SELECT * FROM grados ORDER BY nivel_jerarquia ASC");
$especialidades = $conn->query("SELECT * FROM especialidades ORDER BY nombre ASC");
$lugares_guardias = $conn->query("SELECT * FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");

// Procesar formulario de nuevo policía
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'crear') {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $cin = trim($_POST['cin']);
    $grado_id = $_POST['grado_id'];
    $especialidad_id = $_POST['especialidad_id'] ?: null;
    $cargo = trim($_POST['cargo']);
    $comisionamiento = trim($_POST['comisionamiento']);
    $telefono = trim($_POST['telefono']);
    $region = $_POST['region'];
    $lugar_guardia_id = $_POST['lugar_guardia_id'] ?: null;
    $fecha_ingreso = $_POST['fecha_ingreso'];
    
    $sql = "INSERT INTO policias (nombre, apellido, cin, grado_id, especialidad_id, cargo, comisionamiento, telefono, region, lugar_guardia_id, fecha_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssiiisssii", $nombre, $apellido, $cin, $grado_id, $especialidad_id, $cargo, $comisionamiento, $telefono, $region, $lugar_guardia_id, $fecha_ingreso);
    
    if ($stmt->execute()) {
        $mensaje = "<div class='alert alert-success'>Policía registrado exitosamente</div>";
    } else {
        $mensaje = "<div class='alert alert-danger'>Error al registrar policía: " . $conn->error . "</div>";
    }
}

// Obtener lista de policías
$policias = $conn->query("
    SELECT p.*, g.nombre as grado_nombre, e.nombre as especialidad_nombre, lg.nombre as lugar_guardia_nombre
    FROM policias p
    LEFT JOIN grados g ON p.grado_id = g.id
    LEFT JOIN especialidades e ON p.especialidad_id = e.id
    LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
    WHERE p.activo = 1
    ORDER BY g.nivel_jerarquia ASC, p.apellido ASC
");
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
            max-width: 400px;
        }
        .search-input {
            border-radius: 25px;
            border: 2px solid #e9ecef;
            padding: 12px 45px 12px 20px;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .search-input:focus {
            border-color: #104c75;
            box-shadow: 0 0 0 0.2rem rgba(16, 76, 117, 0.25);
            outline: none;
        }
        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 18px;
        }
        .clear-search {
            position: absolute;
            right: 40px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            font-size: 16px;
            cursor: pointer;
            display: none;
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
        .header-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
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

                    <!-- Controles del header -->
                    <div class="header-controls">
                        <!-- Botón para agregar nuevo policía -->
                        <div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoPolicia">
                                <i class="fas fa-user-plus"></i> Registrar Nuevo Policía
                            </button>
                        </div>
                        
                        <!-- Buscador en tiempo real -->
                        <div class="search-container">
                            <input type="text" 
                                   id="searchInput" 
                                   class="form-control search-input" 
                                   placeholder="Buscar policías..." 
                                   autocomplete="off">
                            <button type="button" id="clearSearch" class="clear-search">
                                <i class="fas fa-times"></i>
                            </button>
                            <i class="fas fa-search search-icon"></i>
                            <div id="searchInfo" class="search-results-info"></div>
                        </div>
                    </div>

                    <!-- Tabla de policías -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5><i class="fas fa-list"></i> Lista de Policías Activos</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped" id="policiasTable">
                                    <thead>
                                        <tr>
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
                                        <tr class="policia-row" data-search="<?php echo strtolower($policia['cin'] . ' ' . $policia['nombre'] . ' ' . $policia['apellido'] . ' ' . $policia['grado_nombre'] . ' ' . ($policia['especialidad_nombre'] ?: '') . ' ' . $policia['cargo'] . ' ' . $policia['telefono'] . ' ' . $policia['region']); ?>">
                                            <td class="searchable"><?php echo $policia['cin']; ?></td>
                                            <td class="searchable"><?php echo $policia['apellido'] . ', ' . $policia['nombre']; ?></td>
                                            <td class="searchable"><?php echo $policia['grado_nombre']; ?></td>
                                            <td class="searchable"><?php echo $policia['especialidad_nombre'] ?: 'N/A'; ?></td>
                                            <td class="searchable"><?php echo $policia['cargo']; ?></td>
                                            <td class="searchable"><?php echo $policia['telefono']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $policia['region'] == 'CENTRAL' ? 'primary' : 'secondary'; ?>">
                                                    <?php echo $policia['region']; ?>
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
                                <select class="form-select" name="region" required>
                                    <option value="CENTRAL">Central</option>
                                    <option value="REGIONAL">Regional</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
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
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha de Ingreso *</label>
                                <input type="date" class="form-control" name="fecha_ingreso" required>
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
        // Funcionalidad del buscador en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const clearButton = document.getElementById('clearSearch');
            const searchInfo = document.getElementById('searchInfo');
            const tableBody = document.getElementById('policiasTableBody');
            const noResults = document.getElementById('noResults');
            const table = document.getElementById('policiasTable');
            const allRows = document.querySelectorAll('.policia-row');
            
            let searchTimeout;
            
            // Función para resaltar texto
            function highlightText(text, searchTerm) {
                if (!searchTerm) return text;
                const regex = new RegExp(`(${searchTerm})`, 'gi');
                return text.replace(regex, '<span class="highlight">$1</span>');
            }
            
            // Función para limpiar resaltados
            function clearHighlights() {
                document.querySelectorAll('.searchable').forEach(cell => {
                    const text = cell.textContent;
                    cell.innerHTML = text;
                });
            }
            
            // Función principal de búsqueda
            function performSearch() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                let visibleCount = 0;
                
                // Limpiar resaltados previos
                clearHighlights();
                
                allRows.forEach(row => {
                    const searchData = row.getAttribute('data-search');
                    const isVisible = searchData.includes(searchTerm);
                    
                    row.style.display = isVisible ? '' : 'none';
                    
                    if (isVisible) {
                        visibleCount++;
                        
                        // Resaltar términos de búsqueda si hay texto
                        if (searchTerm) {
                            row.querySelectorAll('.searchable').forEach(cell => {
                                const originalText = cell.textContent;
                                cell.innerHTML = highlightText(originalText, searchTerm);
                            });
                        }
                    }
                });
                
                // Mostrar/ocultar mensaje de "no resultados"
                if (visibleCount === 0 && searchTerm) {
                    table.style.display = 'none';
                    noResults.style.display = 'block';
                } else {
                    table.style.display = 'table';
                    noResults.style.display = 'none';
                }
                
                // Actualizar información de resultados
                if (searchTerm) {
                    searchInfo.textContent = `${visibleCount} resultado(s) encontrado(s)`;
                    clearButton.style.display = 'block';
                } else {
                    searchInfo.textContent = '';
                    clearButton.style.display = 'none';
                }
            }
            
            // Event listeners
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(performSearch, 150); // Debounce de 150ms
            });
            
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    clearSearch();
                }
            });
            
            clearButton.addEventListener('click', clearSearch);
            
            function clearSearch() {
                searchInput.value = '';
                performSearch();
                searchInput.focus();
            }
            
            // Enfocar el buscador con Ctrl+F
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'f') {
                    e.preventDefault();
                    searchInput.focus();
                }
            });
        });
        
        function verDetalles(policiaId) {
            // Aquí puedes implementar un modal o redireccionar a una página de detalles
            // Por ejemplo, abrir un modal con información detallada
            alert('Funcionalidad de ver detalles para policía ID: ' + policiaId);
            // O redireccionar: window.location.href = 'detalle.php?id=' + policiaId;
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>