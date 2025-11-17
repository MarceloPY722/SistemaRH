<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

$mensaje = "";

// Procesar reactivación de policía
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'reactivar') {
    $policia_id = (int)$_POST['policia_id'];
    
    if ($policia_id > 0) {
        $stmt_prev = $conn->prepare("SELECT * FROM policias WHERE id = ?");
        $stmt_prev->execute([$policia_id]);
        $policia_prev = $stmt_prev->fetch(PDO::FETCH_ASSOC);
        $sql = "UPDATE policias SET activo = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([$policia_id])) {
            if (function_exists('auditoriaActualizar')) {
                auditoriaActualizar('policias', $policia_id, $policia_prev ?: null, [
                    'activo' => 1
                ]);
            }
            $mensaje = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Policía reactivado exitosamente</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'><i class='fas fa-exclamacion-triangle'></i> Error al reactivar policía: " . $stmt->errorInfo()[2] . "</div>";
        }
    }
}

// Obtener lista de policías deshabilitados
$policias_deshabilitados = $conn->query("
    SELECT p.*, tg.nombre as grado_nombre, g.nombre as categoria_nombre, e.nombre as especialidad_nombre, lg.nombre as lugar_guardia_nombre, r.nombre as region_nombre
    FROM policias p
    LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
    LEFT JOIN grados g ON tg.grado_id = g.id
    LEFT JOIN especialidades e ON p.especialidad_id = e.id
    LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
    LEFT JOIN regiones r ON p.region_id = r.id
    WHERE p.activo = 0
    ORDER BY p.created_at DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Policías Deshabilitados - Sistema RH</title>
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
            background-color: #6c757d;
            color: white;
            border: none;
        }
        .disabled-row {
            background-color: #f8f9fa;
            opacity: 0.8;
        }
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="page-title">
                            <i class="fas fa-user-slash text-secondary"></i> Policías Deshabilitados
                        </h1>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver a Policías Activos
                        </a>
                    </div>

                    <?php echo $mensaje; ?>

                    <!-- Información y estadísticas -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h5><i class="fas fa-info-circle text-info"></i> Información</h5>
                                            <p class="mb-0">Aquí se muestran todos los policías que han sido deshabilitados del sistema. Puedes reactivarlos cuando sea necesario.</p>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <h3 class="text-secondary"><?php echo $policias_deshabilitados->num_rows; ?></h3>
                                            <small class="text-muted">Policías deshabilitados</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Buscador -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="search-container">
                                <input type="text" class="form-control search-input" id="searchInput" placeholder="Buscar por nombre, CIN, legajo...">
                                <button type="button" class="clear-search" id="clearSearch">
                                    <i class="fas fa-times"></i>
                                </button>
                                <i class="fas fa-search search-icon"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de policías deshabilitados -->
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h5><i class="fas fa-list"></i> Lista de Policías Deshabilitados</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($policias_deshabilitados->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped" id="policiasTable">
                                    <thead>
                                        <tr>
                                            <th>Legajo</th>
                                            <th>CIN</th>
                                            <th>Nombre Completo</th>
                                            <th>Grado</th>
                                            <th>Especialidad</th>
                                            <th>Región</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="policiasTableBody">
                                        <?php while ($policia = $policias_deshabilitados->fetch_assoc()): ?>
                                        <tr class="policia-row disabled-row" data-search="<?php echo strtolower($policia['legajo'] . ' ' . $policia['cin'] . ' ' . $policia['nombre'] . ' ' . $policia['apellido'] . ' ' . $policia['grado_nombre']); ?>">
                                            <td><strong><?php echo htmlspecialchars($policia['legajo']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($policia['cin']); ?></td>
                                            <td><?php echo htmlspecialchars($policia['nombre'] . ' ' . $policia['apellido']); ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo htmlspecialchars($policia['grado_nombre']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo $policia['especialidad_nombre'] ? htmlspecialchars($policia['especialidad_nombre']) : 'Sin especialidad'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning text-dark">
                                                    <?php echo htmlspecialchars($policia['region_nombre']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-times-circle"></i> Deshabilitado
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-success" title="Reactivar Policía" onclick="reactivarPolicia(<?php echo $policia['id']; ?>, '<?php echo htmlspecialchars($policia['nombre'] . ' ' . $policia['apellido']); ?>')">
                                                        <i class="fas fa-user-check"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-info" title="Ver Detalles" onclick="verDetalles(<?php echo $policia['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-user-check fa-5x text-muted mb-3"></i>
                                <h4>No hay policías deshabilitados</h4>
                                <p class="text-muted">Todos los policías están activos en el sistema.</p>
                                <a href="index.php" class="btn btn-primary">
                                    <i class="fas fa-users"></i> Ver Policías Activos
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación para reactivar -->
    <div class="modal fade" id="reactivarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-user-check"></i> Confirmar Reactivación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro de que desea reactivar al policía <strong id="nombrePolicia"></strong>?</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Al reactivar, el policía volverá a aparecer en las listas activas y podrá ser asignado a servicios y guardias.
                    </div>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="reactivar">
                        <input type="hidden" name="policia_id" id="policiaIdReactivar">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-user-check"></i> Reactivar Policía
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de detalles -->
    <div class="modal fade" id="detallesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-user"></i> Detalles del Policía</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detallesContent">
                    <!-- Contenido cargado dinámicamente -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para reactivar policía
        function reactivarPolicia(id, nombre) {
            document.getElementById('nombrePolicia').textContent = nombre;
            document.getElementById('policiaIdReactivar').value = id;
            
            const modal = new bootstrap.Modal(document.getElementById('reactivarModal'));
            modal.show();
        }

        // Función para ver detalles
        function verDetalles(id) {
            // Aquí puedes implementar la carga de detalles vía AJAX
            // Por ahora, mostraremos un mensaje simple
            document.getElementById('detallesContent').innerHTML = `
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                    <p>Cargando detalles del policía...</p>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('detallesModal'));
            modal.show();
            
            // Simular carga de datos
            setTimeout(() => {
                document.getElementById('detallesContent').innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Funcionalidad de detalles en desarrollo.
                        <br>ID del policía: ${id}
                    </div>
                `;
            }, 1000);
        }

        // Funcionalidad de búsqueda
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const clearButton = document.getElementById('clearSearch');
            const tableBody = document.getElementById('policiasTableBody');
            const rows = tableBody ? Array.from(tableBody.querySelectorAll('.policia-row')) : [];

            if (searchInput && rows.length > 0) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase().trim();
                    
                    if (searchTerm.length > 0) {
                        clearButton.style.display = 'block';
                    } else {
                        clearButton.style.display = 'none';
                    }

                    rows.forEach(row => {
                        const searchData = row.getAttribute('data-search');
                        if (searchData && searchData.includes(searchTerm)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });

                clearButton.addEventListener('click', function() {
                    searchInput.value = '';
                    this.style.display = 'none';
                    rows.forEach(row => {
                        row.style.display = '';
                    });
                });
            }
        });
    </script>
</body>
</html>