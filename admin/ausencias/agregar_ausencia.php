<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../cnx/db_connect.php';

// Búsqueda AJAX de policías
if (isset($_GET['buscar_policia'])) {
    $termino = $_GET['buscar_policia'];
    
    $sql = "SELECT p.id, p.nombre, p.apellido, p.cin, g.nombre as grado 
            FROM policias p 
            JOIN grados g ON p.grado_id = g.id 
            WHERE p.activo = 1 
            AND (p.nombre LIKE ? OR p.apellido LIKE ? OR p.cin LIKE ?)
            ORDER BY p.apellido, p.nombre
            LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    $busqueda = "%$termino%";
    $stmt->bind_param('sss', $busqueda, $busqueda, $busqueda);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $policias = [];
    while ($row = $result->fetch_assoc()) {
        $policias[] = [
            'id' => $row['id'],
            'texto' => $row['apellido'] . ', ' . $row['nombre'] . ' - ' . $row['grado'] . ' (CI: ' . $row['cin'] . ')'
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($policias);
    exit();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $policia_id = $_POST['policia_id'];
    $tipo_ausencia_id = $_POST['tipo_ausencia_id'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'] ?: null;
    $descripcion = $_POST['descripcion'];
    $justificacion = $_POST['justificacion'];
    
    // Aprobación automática para administradores
    $estado = 'APROBADA';
    $aprobado_por = $_SESSION['usuario_id'];
    
    $sql = "INSERT INTO ausencias (policia_id, tipo_ausencia_id, fecha_inicio, fecha_fin, descripcion, justificacion, estado, aprobado_por) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iisssssi', $policia_id, $tipo_ausencia_id, $fecha_inicio, $fecha_fin, $descripcion, $justificacion, $estado, $aprobado_por);
    
    if ($stmt->execute()) {
        $_SESSION['mensaje'] = 'Ausencia registrada y aprobada automáticamente';
        $_SESSION['tipo_mensaje'] = 'success';
        header('Location: index.php');
        exit();
    } else {
        $_SESSION['mensaje'] = 'Error al registrar la ausencia';
        $_SESSION['tipo_mensaje'] = 'danger';
    }
}

// Obtener tipos de ausencias
$sql_tipos = "SELECT * FROM tipos_ausencias ORDER BY nombre";
$result_tipos = $conn->query($sql_tipos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Ausencia - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #1e3d72;
            --light-blue: #e8f2ff;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-bg: #f8f9fa;
            --white: #ffffff;
            --border-color: #dee2e6;
            --text-dark: #343a40;
            --text-muted: #6c757d;
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-dark);
            font-size: 14px;
        }

        .main-content {
            padding: 15px;
            min-height: 100vh;
        }

        .page-header {
            background: var(--primary-color);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(44, 90, 160, 0.2);
        }

        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .page-header p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }

        .form-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .form-card {
            background: var(--white);
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            border: 1px solid var(--border-color);
        }

        .form-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 1.25rem;
        }

        .form-section {
            background: var(--light-blue);
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 3px solid var(--primary-color);
        }

        .section-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }

        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }

        .required-field::after {
            content: ' *';
            color: var(--danger-color);
            font-weight: bold;
        }

        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid var(--border-color);
            padding: 0.5rem 0.75rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            height: auto;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.15rem rgba(44, 90, 160, 0.25);
        }

        .search-container {
            position: relative;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--white);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            z-index: 1000;
            max-height: 180px;
            overflow-y: auto;
        }

        .search-item {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            border-bottom: 1px solid #f1f1f1;
            transition: background-color 0.2s ease;
            font-size: 0.85rem;
        }

        .search-item:hover {
            background-color: var(--light-blue);
        }

        .search-item:last-child {
            border-bottom: none;
        }

        .policia-selected {
            background: #d4edda;
            border: 1px solid var(--success-color);
            border-radius: 6px;
            padding: 0.75rem;
            margin-top: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-1px);
        }

        .btn-outline-secondary {
            border: 1px solid var(--text-muted);
            color: var(--text-muted);
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-outline-secondary:hover {
            background-color: var(--text-muted);
            color: white;
        }

        .btn-outline-light {
            border: 1px solid rgba(255,255,255,0.5);
            color: white;
            transition: all 0.3s ease;
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
        }

        .btn-outline-light:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }

        .btn-outline-danger {
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
            border-radius: 4px;
            padding: 0.2rem 0.4rem;
            font-size: 0.75rem;
        }

        .btn-outline-danger:hover {
            background-color: var(--danger-color);
            color: white;
        }

        .alert {
            border-radius: 6px;
            margin-bottom: 1rem;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 0.75rem 1rem;
        }

        .input-group-text {
            background-color: var(--primary-color);
            color: white;
            border: 1px solid var(--primary-color);
            border-radius: 6px 0 0 6px;
            padding: 0.5rem 0.75rem;
        }

        .form-text {
            color: var(--text-muted);
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        .mb-3 {
            margin-bottom: 0.75rem !important;
        }

        .mb-2 {
            margin-bottom: 0.5rem !important;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 60px;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }
            
            .form-card {
                padding: 1rem;
            }
            
            .page-header {
                padding: 1rem;
                text-align: center;
            }

            .page-header .d-flex {
                flex-direction: column;
                gap: 0.5rem;
            }

            .col-md-6, .col-md-4 {
                margin-bottom: 0.5rem;
            }
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
                    <!-- Mensajes de alerta -->
                    <?php if (isset($_SESSION['mensaje'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['tipo_mensaje']; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle me-2"></i><?php echo $_SESSION['mensaje']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php 
                    unset($_SESSION['mensaje']);
                    unset($_SESSION['tipo_mensaje']);
                    endif; ?>

                    <!-- Header -->
                    <div class="page-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1><i class="fas fa-plus-circle me-2"></i>Nueva Ausencia</h1>
                                <p>Registra una nueva ausencia del personal</p>
                            </div>
                            <div>
                                <a href="index.php" class="btn btn-outline-light">
                                    <i class="fas fa-arrow-left me-1"></i>Volver
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario -->
                    <div class="form-container">
                        <div class="form-card">
                            <h3 class="form-title">
                                <i class="fas fa-user-plus me-2"></i>Datos de la Ausencia
                            </h3>
                            
                            <form method="POST" id="formAusencia">
                                <!-- Sección: Selección de Personal -->
                                <div class="form-section">
                                    <h5 class="section-title">
                                        <i class="fas fa-user me-2"></i>Personal
                                    </h5>
                                    
                                    <input type="hidden" name="policia_id" id="policia_id_hidden">
                                    
                                    <div class="mb-2">
                                        <label class="form-label required-field">
                                            Buscar Policía
                                        </label>
                                        <div class="search-container">
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-search"></i>
                                                </span>
                                                <input type="text" class="form-control" id="buscar_policia" 
                                                       placeholder="Nombre, apellido o CI..." autocomplete="off">
                                            </div>
                                            <div id="resultados_busqueda" class="search-results" style="display: none;"></div>
                                        </div>
                                        <div id="policia_seleccionado" class="policia-selected" style="display: none;">
                                            <span id="policia_texto"></span>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="limpiarSeleccion()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sección: Tipo y Fechas -->
                                <div class="form-section">
                                    <h5 class="section-title">
                                        <i class="fas fa-calendar me-2"></i>Tipo y Fechas
                                    </h5>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label required-field">
                                                Tipo de Ausencia
                                            </label>
                                            <select name="tipo_ausencia_id" class="form-select" required>
                                                <option value="">Seleccionar...</option>
                                                <?php while ($tipo = $result_tipos->fetch_assoc()): ?>
                                                <option value="<?php echo $tipo['id']; ?>"><?php echo $tipo['nombre']; ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label required-field">
                                                Fecha Inicio
                                            </label>
                                            <input type="date" name="fecha_inicio" class="form-control" required>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label">
                                                Fecha Fin (Opcional)
                                            </label>
                                            <input type="date" name="fecha_fin" class="form-control">
                                        </div>
                                    </div>
                                </div>

                                <!-- Sección: Detalles -->
                                <div class="form-section">
                                    <h5 class="section-title">
                                        <i class="fas fa-file-text me-2"></i>Detalles
                                    </h5>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <label class="form-label">
                                                Descripción
                                            </label>
                                            <textarea name="descripcion" class="form-control" rows="2" 
                                                      placeholder="Motivo de la ausencia..."></textarea>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label class="form-label">
                                                Justificación (Opcional)
                                            </label>
                                            <textarea name="justificacion" class="form-control" rows="2" 
                                                      placeholder="Justificación adicional..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Botones -->
                                <div class="d-flex justify-content-between mt-3">
                                                
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Registrar Ausencia
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let timeoutId;
        let policiaSeleccionado = null;

        // Búsqueda de policías con debounce
        document.getElementById('buscar_policia').addEventListener('input', function() {
            clearTimeout(timeoutId);
            const query = this.value.trim();
            
            if (query.length < 2) {
                document.getElementById('resultados_busqueda').style.display = 'none';
                return;
            }
            
            timeoutId = setTimeout(() => {
                buscarPolicias(query);
            }, 300);
        });

        function buscarPolicias(query) {
            fetch(`agregar_ausencia.php?buscar_policia=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    mostrarResultados(data);
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        function mostrarResultados(policias) {
            const container = document.getElementById('resultados_busqueda');
            
            if (policias.length === 0) {
                container.innerHTML = '<div class="search-item"><i class="fas fa-exclamation-circle me-2"></i>No se encontraron resultados</div>';
            } else {
                container.innerHTML = policias.map(policia => 
                    `<div class="search-item" onclick="seleccionarPolicia(${policia.id}, '${policia.texto.replace(/'/g, "\\'")}')">                        <i class="fas fa-user me-2"></i>${policia.texto}
                    </div>`
                ).join('');
            }
            
            container.style.display = 'block';
        }

        function seleccionarPolicia(id, texto) {
            policiaSeleccionado = { id, texto };
            document.getElementById('policia_id_hidden').value = id;
            document.getElementById('policia_texto').textContent = texto;
            document.getElementById('policia_seleccionado').style.display = 'flex';
            document.getElementById('buscar_policia').value = '';
            document.getElementById('resultados_busqueda').style.display = 'none';
        }

        function limpiarSeleccion() {
            policiaSeleccionado = null;
            document.getElementById('policia_id_hidden').value = '';
            document.getElementById('policia_seleccionado').style.display = 'none';
            document.getElementById('buscar_policia').value = '';
        }

        // Ocultar resultados al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-container')) {
                document.getElementById('resultados_busqueda').style.display = 'none';
            }
        });

        // Validación del formulario
        document.getElementById('formAusencia').addEventListener('submit', function(e) {
            if (!policiaSeleccionado) {
                e.preventDefault();
                alert('Por favor, selecciona un policía.');
                return;
            }

            const fechaInicio = document.querySelector('input[name="fecha_inicio"]').value;
            const fechaFin = document.querySelector('input[name="fecha_fin"]').value;

            if (fechaFin && fechaFin < fechaInicio) {
                e.preventDefault();
                alert('La fecha de fin no puede ser anterior a la fecha de inicio.');
                return;
            }
        });
    </script>
</body>
</html>