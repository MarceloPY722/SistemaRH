<?php
session_start();
require_once '../../cnx/db_connect.php';
require_once 'generar_guardia.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit();
}

$generador = new GeneradorGuardias($conn);
$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_servicio = $_POST['fecha_servicio'] ?? '';
    $orden_dia = $_POST['orden_dia'] ?? '';
    
    if (empty($fecha_servicio) || empty($orden_dia)) {
        $mensaje = 'Por favor complete todos los campos requeridos.';
        $tipo_mensaje = 'danger';
    } else {
        // Verificar si la fecha ya tiene guardia
        if ($generador->fechaTieneGuardia($fecha_servicio)) {
            $mensaje = 'Ya existe una guardia generada para la fecha seleccionada.';
            $tipo_mensaje = 'warning';
        } else {
            // Verificar si el número de orden ya existe
            if ($generador->ordenDiaExiste($orden_dia)) {
                $mensaje = 'El número de orden del día ya existe. Por favor seleccione otro.';
                $tipo_mensaje = 'warning';
            } else {
                try {
                    // Generar la guardia
                    $resultado = $generador->generarGuardia($fecha_servicio, $orden_dia);
                    
                    if (!$resultado['success']) {
                        throw new Exception($resultado['error']);
                    }
                    
                    $guardia_id = $resultado['guardia_id'];
                    
                    // Redirigir a ver_guardias.php después de generar exitosamente
                    // Agregar parámetro redirect_to_index para que después de descargar PDF redirija a index.php
                    header("Location: ver_guardias.php?fecha=" . urlencode($fecha_servicio) . "&redirect_to_index=1");
                    exit();
                    
                } catch (Exception $e) {
                    $mensaje = 'Error al generar la guardia: ' . $e->getMessage();
                    $tipo_mensaje = 'danger';
                }
            }
        }
    }
}

// Obtener sugerencias de orden del día
$sugerencias_data = $generador->obtenerSugerenciasOrdenDia();
$sugerencias = $sugerencias_data['sugerencias'];
$historial = $sugerencias_data['historial'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Nueva Guardia - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #104c75;
            --primary-dark: #0d3d5c;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-bg: #f8f9fa;
            --white: #ffffff;
            --border-color: #dee2e6;
            --text-muted: #6c757d;
            --shadow: 0 2px 10px rgba(0,0,0,0.1);
            --shadow-lg: 0 5px 15px rgba(0,0,0,0.08);
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
        }

        .main-content {
            padding: 30px;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            margin-top: 8px;
            margin-bottom: 0;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: var(--shadow-lg);
            margin-bottom: 25px;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 20px 25px;
            border: none;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 25px;
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(16, 76, 117, 0.25);
        }

        .btn {
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 100%);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #5a6268 100%);
            border: none;
            color: white;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268 0%, var(--secondary-color) 100%);
            color: white;
        }

        .info-card {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-left: 4px solid var(--info-color);
        }

        .info-card .card-header {
            background: linear-gradient(135deg, var(--info-color) 0%, #138496 100%);
        }

        .suggestion-badge {
            background: linear-gradient(135deg, var(--success-color) 0%, #20c997 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            margin: 3px;
        }

        .suggestion-badge:hover {
            background: linear-gradient(135deg, #20c997 0%, var(--success-color) 100%);
            transform: translateY(-1px);
            color: white;
        }

        .region-info {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }

        .region-badge {
            background: linear-gradient(135deg, var(--warning-color) 0%, #e0a800 100%);
            color: #212529;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .alert {
            border: none;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 25px;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 4px solid var(--danger-color);
        }

        .alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border-left: 4px solid var(--warning-color);
        }

        .required {
            color: var(--danger-color);
        }

        .historial-item {
            background: var(--white);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .historial-numero {
            font-weight: 600;
            color: var(--primary-color);
        }

        .historial-fecha {
            color: var(--text-muted);
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }
            
            .page-header {
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .card-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php 
            $_GET['page'] = 'guardias';
            include '../inc/sidebar.php'; 
            ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Page Header -->
                    <div class="page-header">
                        <h1 class="page-title">
                            <i class="fas fa-calendar-plus"></i>
                            Generar Nueva Guardia
                        </h1>
                        <p class="page-subtitle">
                            Genere una nueva guardia especificando la fecha de servicio y el número de orden del día
                        </p>
                    </div>

                    <!-- Mensajes de alerta -->
                    <?php if (!empty($mensaje)): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php echo htmlspecialchars($mensaje); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Formulario Principal -->
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        <i class="fas fa-edit"></i>
                                        Datos de la Guardia
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" id="formGenerarGuardia">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="fecha_servicio" class="form-label">
                                                        <i class="fas fa-calendar"></i>
                                                        Fecha de Servicio <span class="required">*</span>
                                                    </label>
                                                    <input type="date" 
                                                           class="form-control" 
                                                           id="fecha_servicio" 
                                                           name="fecha_servicio" 
                                                           required
                                                           min="<?php echo date('Y-m-d'); ?>"
                                                           value="<?php echo $_POST['fecha_servicio'] ?? ''; ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="orden_dia" class="form-label">
                                                        <i class="fas fa-list-ol"></i>
                                                        Orden del Día <span class="required">*</span>
                                                    </label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="orden_dia" 
                                                           name="orden_dia" 
                                                           placeholder="Ej: 28/2025" 
                                                           required
                                                           pattern="[0-9]+/[0-9]{4}"
                                                           title="Formato: número/año (ej: 28/2025)"
                                                           value="<?php echo $_POST['orden_dia'] ?? ''; ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Información de región -->
                                        <div class="region-info" id="regionInfo" style="display: none;">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-map-marker-alt me-2"></i>
                                                <strong>Región asignada:</strong>
                                                <span class="region-badge ms-2" id="regionBadge"></span>
                                            </div>
                                            <small class="text-muted mt-2 d-block">
                                                <i class="fas fa-info-circle me-1"></i>
                                                La región se determina automáticamente según el día de la semana seleccionado.
                                            </small>
                                        </div>

                                        <div class="d-flex gap-3 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-2"></i>
                                Generar Guardia
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>
                                Volver
                            </a>
                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Panel de Información -->
                        <div class="col-lg-4">
                            <!-- Sugerencias de Orden del Día -->
                            <div class="card info-card">
                                <div class="card-header">
                                    <h6 class="card-title">
                                        <i class="fas fa-lightbulb"></i>
                                        Sugerencias de Orden del Día
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-3">
                                        <small>Próximos números sugeridos:</small>
                                    </p>
                                    <div class="mb-3">
                                        <?php foreach ($sugerencias as $sugerencia): ?>
                                        <button type="button" 
                                                class="suggestion-badge" 
                                                onclick="seleccionarOrden('<?php echo $sugerencia; ?>')">
                                            <?php echo $sugerencia; ?>
                                        </button>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <?php if (!empty($historial)): ?>
                                    <hr>
                                    <p class="text-muted mb-2">
                                        <small><i class="fas fa-history"></i> Últimas órdenes:</small>
                                    </p>
                                    <?php foreach (array_slice($historial, 0, 3) as $orden): ?>
                                    <div class="historial-item">
                                        <div class="historial-numero"><?php echo $orden['numero_orden']; ?></div>
                                        <div class="historial-fecha">
                                            Año <?php echo $orden['año']; ?> - Número <?php echo $orden['numero']; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Información Importante -->
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title">
                                        <i class="fas fa-info-circle"></i>
                                        Información Importante
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            <small>La guardia se genera automáticamente según la región</small>
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            <small>Se asignan puestos según disponibilidad y jerarquía</small>
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            <small>Los números de orden deben ser únicos</small>
                                        </li>
                                        <li class="mb-0">
                                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                            <small>No se puede generar guardia para fechas pasadas</small>
                                        </li>
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
        // Función para seleccionar orden sugerida
        function seleccionarOrden(orden) {
            document.getElementById('orden_dia').value = orden;
            // Trigger change event para validaciones
            document.getElementById('orden_dia').dispatchEvent(new Event('change'));
        }

        // Función para actualizar región según fecha
        function actualizarRegion() {
            const fechaInput = document.getElementById('fecha_servicio');
            const regionInfo = document.getElementById('regionInfo');
            const regionBadge = document.getElementById('regionBadge');
            
            if (fechaInput.value) {
                const fecha = new Date(fechaInput.value + 'T00:00:00');
                const diaSemana = fecha.getDay(); // 0=domingo, 1=lunes, ..., 6=sábado
                
                let region, regionClass;
                if (diaSemana === 5 || diaSemana === 6) { // Viernes o Sábado
                    region = 'REGIONAL';
                    regionClass = 'bg-info';
                } else { // Domingo a Jueves
                    region = 'CENTRAL';
                    regionClass = 'bg-primary';
                }
                
                regionBadge.textContent = region;
                regionBadge.className = `region-badge ${regionClass}`;
                regionInfo.style.display = 'block';
            } else {
                regionInfo.style.display = 'none';
            }
        }

        // Event listeners
        document.getElementById('fecha_servicio').addEventListener('change', actualizarRegion);
        
        // Validación del formulario
        document.getElementById('formGenerarGuardia').addEventListener('submit', function(e) {
            const fecha = document.getElementById('fecha_servicio').value;
            const orden = document.getElementById('orden_dia').value;
            
            if (!fecha || !orden) {
                e.preventDefault();
                alert('Por favor complete todos los campos requeridos.');
                return false;
            }
            
            // Validar formato de orden
            const formatoOrden = /^[0-9]+\/[0-9]{4}$/;
            if (!formatoOrden.test(orden)) {
                e.preventDefault();
                alert('El formato del orden del día debe ser: número/año (ej: 28/2025)');
                return false;
            }
            
            // Validar que la fecha no sea pasada
            const fechaSeleccionada = new Date(fecha);
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            
            if (fechaSeleccionada < hoy) {
                e.preventDefault();
                alert('No se puede generar una guardia para una fecha pasada.');
                return false;
            }
        });
        
        // Inicializar región si hay fecha preseleccionada
        document.addEventListener('DOMContentLoaded', function() {
            actualizarRegion();
        });
    </script>
</body>
</html>