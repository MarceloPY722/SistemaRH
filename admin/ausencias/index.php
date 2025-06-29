<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

// Obtener información del usuario logueado para verificar si es administrador
$stmt_usuario = $conn->prepare("SELECT nombre_usuario, rol FROM usuarios WHERE id = ?");
$stmt_usuario->bind_param("i", $_SESSION['usuario_id']);
$stmt_usuario->execute();
$usuario_actual = $stmt_usuario->get_result()->fetch_assoc();
$stmt_usuario->close();

// AJAX para búsqueda de policías
if (isset($_GET['buscar_policia'])) {
    $termino = '%' . $_GET['buscar_policia'] . '%';
    $stmt = $conn->prepare("
        SELECT p.id, p.nombre, p.apellido, p.cin, g.nombre as grado
        FROM policias p
        JOIN grados g ON p.grado_id = g.id
        WHERE p.activo = 1 
        AND (p.nombre LIKE ? OR p.apellido LIKE ? OR p.cin LIKE ?)
        ORDER BY p.apellido ASC
        LIMIT 10
    ");
    $stmt->bind_param("sss", $termino, $termino, $termino);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $policias = [];
    while ($policia = $resultado->fetch_assoc()) {
        $policias[] = [
            'id' => $policia['id'],
            'texto' => $policia['grado'] . ' ' . $policia['apellido'] . ', ' . $policia['nombre'] . ' (CIN: ' . $policia['cin'] . ')'
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($policias);
    exit();
}

// Obtener tipos de ausencias
$tipos_ausencias = $conn->query("SELECT * FROM tipos_ausencias ORDER BY nombre ASC");

// Procesar formulario de nueva ausencia
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'crear') {
    $policia_id = $_POST['policia_id'];
    $tipo_ausencia_id = $_POST['tipo_ausencia_id'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'] ?: null;
    $descripcion = trim($_POST['descripcion']);
    $justificacion = trim($_POST['justificacion']);
    
    // Verificar si el usuario actual es administrador
    $es_administrador = (strtolower($usuario_actual['rol']) === 'administrador' || strtolower($usuario_actual['rol']) === 'admin');
    
    if ($es_administrador) {
        // Si es administrador, crear la ausencia ya aprobada
        $sql = "INSERT INTO ausencias (policia_id, tipo_ausencia_id, fecha_inicio, fecha_fin, descripcion, justificacion, estado, aprobado_por) VALUES (?, ?, ?, ?, ?, ?, 'APROBADA', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissssi", $policia_id, $tipo_ausencia_id, $fecha_inicio, $fecha_fin, $descripcion, $justificacion, $_SESSION['usuario_id']);
        
        if ($stmt->execute()) {
            $mensaje = "<div class='alert alert-success'>Ausencia registrada y aprobada automáticamente por el administrador</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al registrar ausencia: " . $conn->error . "</div>";
        }
    } else {
        // Si no es administrador, crear la ausencia pendiente de aprobación
        $sql = "INSERT INTO ausencias (policia_id, tipo_ausencia_id, fecha_inicio, fecha_fin, descripcion, justificacion) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissss", $policia_id, $tipo_ausencia_id, $fecha_inicio, $fecha_fin, $descripcion, $justificacion);
        
        if ($stmt->execute()) {
            $mensaje = "<div class='alert alert-success'>Ausencia registrada exitosamente. Pendiente de aprobación.</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al registrar ausencia: " . $conn->error . "</div>";
        }
    }
}

// Procesar aprobación/rechazo de ausencias
if ($_POST && isset($_POST['action']) && in_array($_POST['action'], ['aprobar', 'rechazar'])) {
    $ausencia_id = $_POST['ausencia_id'];
    $estado = $_POST['action'] == 'aprobar' ? 'APROBADA' : 'RECHAZADA';
    $aprobado_por = $_SESSION['usuario_id'];
    
    $sql = "UPDATE ausencias SET estado = ?, aprobado_por = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $estado, $aprobado_por, $ausencia_id);
    
    if ($stmt->execute()) {
        $mensaje = "<div class='alert alert-success'>Ausencia " . strtolower($estado) . " exitosamente</div>";
    } else {
        $mensaje = "<div class='alert alert-danger'>Error al procesar ausencia: " . $conn->error . "</div>";
    }
}

// Procesar edición de ausencias
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'editar') {
    $ausencia_id = $_POST['ausencia_id'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'] ?: null;
    
    $sql = "UPDATE ausencias SET fecha_inicio = ?, fecha_fin = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $fecha_inicio, $fecha_fin, $ausencia_id);
    
    if ($stmt->execute()) {
        $mensaje = "<div class='alert alert-success'>Fechas de ausencia actualizadas exitosamente</div>";
    } else {
        $mensaje = "<div class='alert alert-danger'>Error al actualizar ausencia: " . $conn->error . "</div>";
    }
}

// Procesar eliminación de ausencias
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'eliminar') {
    $ausencia_id = $_POST['ausencia_id'];
    
    $sql = "DELETE FROM ausencias WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ausencia_id);
    
    if ($stmt->execute()) {
        $mensaje = "<div class='alert alert-success'>Ausencia eliminada exitosamente</div>";
    } else {
        $mensaje = "<div class='alert alert-danger'>Error al eliminar ausencia: " . $conn->error . "</div>";
    }
}

// Obtener ausencias
$ausencias = $conn->query("
    SELECT a.*, p.nombre, p.apellido, p.cin, g.nombre as grado, ta.nombre as tipo_ausencia,
           ap.nombre as aprobado_nombre, ap.apellido as aprobado_apellido
    FROM ausencias a
    JOIN policias p ON a.policia_id = p.id
    JOIN grados g ON p.grado_id = g.id
    JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id
    LEFT JOIN policias ap ON a.aprobado_por = ap.id
    ORDER BY a.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ausencias - Sistema RH</title>
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
        
        /* Estilos para el buscador */
        .search-container {
            position: relative;
        }
        
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .search-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .search-item:hover {
            background-color: #f8f9fa;
        }
        
        .search-item:last-child {
            border-bottom: none;
        }
        
        .no-results {
            padding: 10px 15px;
            color: #6c757d;
            font-style: italic;
        }
        
        .selected-policia {
            background-color: #e3f2fd;
            border: 2px solid #2196f3;
            border-radius: 8px;
            padding: 10px;
            margin-top: 5px;
            display: none;
        }
        
        .clear-selection {
            color: #dc3545;
            cursor: pointer;
            float: right;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../inc/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <h1 class="page-title">
                        <i class="fas fa-user-times"></i> Gestión de Ausencias
                    </h1>

                    <?php if (isset($mensaje)) echo $mensaje; ?>

                    <!-- Formulario para nueva ausencia -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5><i class="fas fa-plus-circle"></i> Registrar Nueva Ausencia</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="formAusencia">
                                <input type="hidden" name="action" value="crear">
                                <input type="hidden" name="policia_id" id="policia_id_hidden">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Policía *</label>
                                        <div class="search-container">
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="buscar_policia" 
                                                   placeholder="Buscar por nombre, apellido o CIN..."
                                                   autocomplete="off">
                                            <div class="search-results" id="resultados_busqueda"></div>
                                        </div>
                                        <div class="selected-policia" id="policia_seleccionado">
                                            <span id="policia_texto"></span>
                                            <span class="clear-selection" onclick="limpiarSeleccion()">
                                                <i class="fas fa-times"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tipo de Ausencia *</label>
                                        <select class="form-select" name="tipo_ausencia_id" required>
                                            <option value="">Seleccionar tipo...</option>
                                            <?php while ($tipo = $tipos_ausencias->fetch_assoc()): ?>
                                            <option value="<?php echo $tipo['id']; ?>">
                                                <?php echo $tipo['nombre']; ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Fecha de Inicio *</label>
                                        <input type="date" class="form-control" name="fecha_inicio" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Fecha de Fin</label>
                                        <input type="date" class="form-control" name="fecha_fin">
                                        <div class="form-text">Dejar vacío para ausencias indefinidas</div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Descripción *</label>
                                        <textarea class="form-control" name="descripcion" rows="3" required></textarea>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Justificación</label>
                                        <textarea class="form-control" name="justificacion" rows="3"></textarea>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Registrar Ausencia
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Lista de ausencias -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5><i class="fas fa-list"></i> Lista de Ausencias</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Policía</th>
                                            <th>CIN</th>
                                            <th>Grado</th>
                                            <th>Tipo</th>
                                            <th>Fecha Inicio</th>
                                            <th>Fecha Fin</th>
                                            <th>Estado</th>
                                            <th>Descripción</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($ausencias->num_rows > 0): ?>
                                        <?php while ($ausencia = $ausencias->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $ausencia['apellido'] . ', ' . $ausencia['nombre']; ?></td>
                                            <td><?php echo $ausencia['cin']; ?></td>
                                            <td><?php echo $ausencia['grado']; ?></td>
                                            <td><?php echo $ausencia['tipo_ausencia']; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($ausencia['fecha_inicio'])); ?></td>
                                            <td>
                                                <?php echo $ausencia['fecha_fin'] ? date('d/m/Y', strtotime($ausencia['fecha_fin'])) : 'Indefinida'; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $ausencia['estado'] == 'APROBADA' ? 'success' : 
                                                        ($ausencia['estado'] == 'RECHAZADA' ? 'danger' : 'warning'); 
                                                ?>">
                                                    <?php echo $ausencia['estado']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo substr($ausencia['descripcion'], 0, 50) . '...'; ?></td>
                                            <td class="text-center">
                                                <?php if ($ausencia['estado'] == 'PENDIENTE'): ?>
                                                <div class="btn-group" role="group">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="aprobar">
                                                        <input type="hidden" name="ausencia_id" value="<?php echo $ausencia['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-success" 
                                                                onclick="return confirm('¿Aprobar esta ausencia?')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="rechazar">
                                                        <input type="hidden" name="ausencia_id" value="<?php echo $ausencia['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" 
                                                                onclick="return confirm('¿Rechazar esta ausencia?')">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            onclick="editarAusencia(<?php echo $ausencia['id']; ?>, '<?php echo $ausencia['fecha_inicio']; ?>', '<?php echo $ausencia['fecha_fin']; ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
                                                <?php else: ?>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            onclick="editarAusencia(<?php echo $ausencia['id']; ?>, '<?php echo $ausencia['fecha_inicio']; ?>', '<?php echo $ausencia['fecha_fin']; ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="eliminar">
                                                        <input type="hidden" name="ausencia_id" value="<?php echo $ausencia['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                                onclick="return confirm('¿Está seguro de eliminar esta ausencia? Esta acción no se puede deshacer.')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                        <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
                                                <i class="fas fa-info-circle"></i> No hay ausencias registradas
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para editar fechas de ausencia -->
    <div class="modal fade" id="editarAusenciaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Fechas de Ausencia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="editar">
                        <input type="hidden" name="ausencia_id" id="edit_ausencia_id">
                        
                        <div class="mb-3">
                            <label for="edit_fecha_inicio" class="form-label">Fecha de Inicio</label>
                            <input type="date" class="form-control" name="fecha_inicio" id="edit_fecha_inicio" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_fecha_fin" class="form-label">Fecha de Fin (opcional)</label>
                            <input type="date" class="form-control" name="fecha_fin" id="edit_fecha_fin">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function editarAusencia(id, fechaInicio, fechaFin) {
        document.getElementById('edit_ausencia_id').value = id;
        document.getElementById('edit_fecha_inicio').value = fechaInicio;
        document.getElementById('edit_fecha_fin').value = fechaFin || '';
        
        var modal = new bootstrap.Modal(document.getElementById('editarAusenciaModal'));
        modal.show();
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let timeoutId;
        let policiaSeleccionado = null;
        
        document.getElementById('buscar_policia').addEventListener('input', function() {
            const termino = this.value.trim();
            
            clearTimeout(timeoutId);
            
            if (termino.length < 2) {
                ocultarResultados();
                return;
            }
            
            timeoutId = setTimeout(() => {
                buscarPolicias(termino);
            }, 300);
        });
        
        function buscarPolicias(termino) {
            fetch(`?buscar_policia=${encodeURIComponent(termino)}`)
                .then(response => response.json())
                .then(data => {
                    mostrarResultados(data);
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }
        
        function mostrarResultados(policias) {
            const contenedor = document.getElementById('resultados_busqueda');
            
            if (policias.length === 0) {
                contenedor.innerHTML = '<div class="no-results">No se encontraron policías</div>';
            } else {
                contenedor.innerHTML = policias.map(policia => 
                    `<div class="search-item" onclick="seleccionarPolicia(${policia.id}, '${policia.texto.replace(/'/g, "\\'")}')">                        ${policia.texto}
                    </div>`
                ).join('');
            }
            
            contenedor.style.display = 'block';
        }
        
        function seleccionarPolicia(id, texto) {
            policiaSeleccionado = { id, texto };
            
            document.getElementById('policia_id_hidden').value = id;
            document.getElementById('policia_texto').textContent = texto;
            document.getElementById('policia_seleccionado').style.display = 'block';
            document.getElementById('buscar_policia').value = '';
            
            ocultarResultados();
        }
        
        function limpiarSeleccion() {
            policiaSeleccionado = null;
            document.getElementById('policia_id_hidden').value = '';
            document.getElementById('policia_seleccionado').style.display = 'none';
            document.getElementById('buscar_policia').value = '';
            document.getElementById('buscar_policia').focus();
        }
        
        function ocultarResultados() {
            document.getElementById('resultados_busqueda').style.display = 'none';
        }
        
        // Ocultar resultados al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-container')) {
                ocultarResultados();
            }
        });
        
        // Validación del formulario
        document.getElementById('formAusencia').addEventListener('submit', function(e) {
            if (!policiaSeleccionado) {
                e.preventDefault();
                alert('Por favor selecciona un policía');
                document.getElementById('buscar_policia').focus();
            }
        });
    </script>
</body>
</html>