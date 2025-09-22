<?php
session_start();
require_once '../../cnx/db_connect.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Verificar permisos de administrador
if ($_SESSION['rol'] !== 'ADMIN' && $_SESSION['rol'] !== 'SUPERADMIN') {
    header('Location: ../../index.php');
    exit();
}

$mensaje = '';
$error = '';

// Definir lugares de guardia por zona
$lugares_central = [1, 3, 5, 7, 9, 11, 13, 15, 17];
$lugares_regional = [2, 4, 6, 8, 10, 12, 14, 16, 18];

// Definir cantidades de personal por lugar
$cantidades_personal = [
    1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1, 7 => 1, 8 => 1,
    9 => 4, 10 => 4, 11 => 1, 12 => 1, 13 => 1, 14 => 1, 15 => 1, 16 => 1,
    17 => 3, 18 => 3
];

// Procesar formulario de generación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_guardia = $_POST['fecha_guardia'] ?? '';
    $orden_dia = $_POST['orden_dia'] ?? '';
    
    if (empty($fecha_guardia) || empty($orden_dia)) {
        $error = 'Debe completar todos los campos obligatorios';
    } else {
        // Verificar si ya existe una guardia con la misma fecha y orden del día
        try {
            $query_verificar = "SELECT id FROM guardias_generadas WHERE fecha_guardia = ? AND orden_dia = ?";
            $stmt_verificar = $conn->prepare($query_verificar);
            $stmt_verificar->execute([$fecha_guardia, $orden_dia]);
            
            if ($stmt_verificar->fetch()) {
                $error = 'Ya existe una guardia generada para la fecha ' . date('d/m/Y', strtotime($fecha_guardia)) . ' con el orden del día "' . htmlspecialchars($orden_dia) . '". No se pueden generar guardias duplicadas.';
            }
        } catch (PDOException $e) {
            $error = 'Error al verificar guardias existentes: ' . $e->getMessage();
        }
    }
    
    // Solo proceder si no hay errores de validación
    if (empty($error)) {
        try {
            // Determinar zona según día de la semana
            $dia_semana = date('N', strtotime($fecha_guardia));
            $es_central = in_array($dia_semana, [7, 1, 2, 3, 4]); // Domingo(7) a Jueves(4)
            $lugares_activos = $es_central ? $lugares_central : $lugares_regional;
            
            // Deshabilitar ID 7 y 8 los domingos
            if ($dia_semana == 7) { // Domingo
                $lugares_activos = array_diff($lugares_activos, [7, 8]);
            }
            
            // Obtener policías disponibles agrupados por lugar y ordenados por posición FIFO dentro de cada lugar
            $policias_por_lugar = [];
            
            // Para cada lugar activo, obtener los policías ordenados por posición FIFO
            foreach ($lugares_activos as $lugar_id) {
                $query = "SELECT p.id, p.legajo, p.nombre, p.apellido, p.lugar_guardia_id, lg.posicion, 
                         lguar.nombre as nombre_lugar
                         FROM policias p 
                         INNER JOIN lista_guardias lg ON p.id = lg.policia_id 
                         LEFT JOIN lugares_guardias lguar ON p.lugar_guardia_id = lguar.id
                         WHERE p.activo = 1 AND p.estado = 'DISPONIBLE' 
                         AND p.lugar_guardia_id = :lugar_id
                         ORDER BY lg.posicion ASC";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':lugar_id', $lugar_id, PDO::PARAM_INT);
                $stmt->execute();
                $policias_por_lugar[$lugar_id] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Generar asignaciones
            $asignaciones = [];
            foreach ($lugares_activos as $lugar_id) {
                $cantidad_necesaria = $cantidades_personal[$lugar_id];
                $policias_lugar = $policias_por_lugar[$lugar_id] ?? [];
                
                // Tomar los primeros N policías según cantidad necesaria
                $asignados = array_slice($policias_lugar, 0, $cantidad_necesaria);
                
                foreach ($asignados as $policia) {
                    $asignaciones[] = [
                        'policia_id' => $policia['id'],
                        'lugar_guardia_id' => $lugar_id,
                        'legajo' => $policia['legajo'],
                        'nombre' => $policia['nombre'],
                        'apellido' => $policia['apellido'],
                        'posicion_original' => $policia['posicion'],
                        'nombre_lugar' => $policia['nombre_lugar']
                    ];
                }
            }
            
            // Almacenar asignaciones en sesión para confirmación
            $_SESSION['asignaciones_generadas'] = [
                'fecha_guardia' => $fecha_guardia,
                'orden_dia' => $orden_dia,
                'asignaciones' => $asignaciones,
                'zona' => $es_central ? 'CENTRAL' : 'REGIONAL'
            ];
            
            header('Location: confirmar_guardia.php');
            exit();
            
        } catch (PDOException $e) {
            $error = 'Error al generar las guardias: ' . $e->getMessage();
        }
    } // Cierre del if (empty($error))
}

// Obtener último número de orden para sugerencia
$ultimo_orden = '';
try {
    $query = "SELECT numero_orden FROM orden_dia ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $ultimo_orden = $result['numero_orden'];
    }
} catch (PDOException $e) {
    // Silenciar error si la tabla no existe
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Guardias - Sistema RH Policía</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/styles.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            font-size: 0.9rem; 
            overflow-x: hidden;
        }
        
        .modern-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            background: white;
            transition: all 0.3s ease;
        }
        
        .modern-card:hover {
            box-shadow: 0 6px 25px rgba(0,0,0,0.12);
        }
        
        .card-header-modern {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 1rem 1.5rem;
            border: none;
        }
        
        .card-title-modern {
            font-weight: 600;
            margin: 0;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-control-modern {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 8px 12px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .form-control-modern:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.15rem rgba(52, 152, 219, 0.2);
        }
        
        .btn-modern-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-modern-primary:hover {
            background: linear-gradient(135deg, #2980b9 0%, #2471a3 100%);
        }
        
        .btn-modern-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-modern-secondary:hover {
            background: linear-gradient(135deg, #7f8c8d 0%, #6c7b7d 100%);
        }
        
        .info-panel {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            border-left: 3px solid #3498db;
            padding: 1rem;
            font-size: 0.85rem;
        }
        
        .form-label-modern {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 1rem 0;
        }
        
        .stats-grid {
            display: flex;
            gap: 0.8rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            flex: 1;
            min-width: 120px;
        }
        
        .stat-number {
            font-size: 1.4rem;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 0.2rem;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.75rem;
        }
        
        .compact-header {
            margin-bottom: 1rem;
        }
        
        .compact-header h1 {
            font-size: 1.5rem;
            margin-bottom: 0.2rem;
        }
        
        .compact-header p {
            font-size: 0.85rem;
            margin-bottom: 0;
        }
        
        .form-text {
            font-size: 0.75rem;
        }
        
        .info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
        }
        
        .info-item:last-child {
            margin-bottom: 0;
        }
        
        .info-item i {
            margin-right: 0.5rem;
            margin-top: 0.1rem;
            width: 12px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../inc/sidebar.php'; ?>
            
            <main class="col-md-9 col-lg-10 px-4 py-2 main-content">
                <!-- Header Section -->
                <div class="row mb-2">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center compact-header">
                            <div>
                                <h1 class="h4 mb-0 text-dark">
                                    <i class="fas fa-shield-alt text-primary me-2"></i>
                                    Generar Guardias
                                </h1>
                                <p class="text-muted mb-0">Sistema de asignación automática de personal</p>
                            </div>
                            <span class="badge bg-primary">
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo date('d/m/Y'); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Stats Overview -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-number">18</div>
                                <div class="stat-label">Lugares</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number">2</div>
                                <div class="stat-label">Zonas</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number">7</div>
                                <div class="stat-label">Días</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number">FIFO</div>
                                <div class="stat-label">Prioridad</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Form -->
                <div class="row">
                    <div class="col-xl-10 col-lg-12 mx-auto">
                        <div class="modern-card">
                            <div class="card-header card-header-modern">
                                <h3 class="card-title-modern">
                                    <i class="fas fa-cogs"></i>
                                    Configuración de Guardias
                                </h3>
                            </div>
                            <div class="card-body p-3">
                                <?php if ($error): ?>
                                    <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        <?php echo htmlspecialchars($error); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="row">
                                    <!-- Formulario -->
                                    <div class="col-md-6">
                                        <form method="POST" id="formGenerarGuardia" class="needs-validation" novalidate>
                                            <!-- Fecha de Guardia -->
                                            <div class="mb-3">
                                                <label for="fecha_guardia" class="form-label-modern">
                                                    <i class="fas fa-calendar-day text-primary me-1"></i>
                                                    Fecha de Guardia *
                                                </label>
                                                <input type="date" class="form-control form-control-modern" 
                                                       id="fecha_guardia" name="fecha_guardia" 
                                                       value="<?php echo date('Y-m-d'); ?>" required>
                                                <div class="form-text text-muted">Seleccione la fecha para la asignación</div>
                                            </div>
                                            
                                            <!-- Número de Orden del Día -->
                                            <div class="mb-3">
                                                <label for="orden_dia" class="form-label-modern">
                                                    <i class="fas fa-file-alt text-primary me-1"></i>
                                                    Número de Orden del Día *
                                                </label>
                                                <input type="text" class="form-control form-control-modern" 
                                                       id="orden_dia" name="orden_dia" 
                                                       placeholder="Ej: 27/2025" 
                                                       value="<?php echo htmlspecialchars($ultimo_orden); ?>" 
                                                       required>
                                                <div class="form-text text-muted">Formato: número/año (ej: 27/2025)</div>
                                            </div>
                                            
                                            <!-- Action Buttons -->
                                            <div class="d-flex gap-2 pt-2">
                                                <button type="submit" class="btn btn-modern-primary flex-fill">
                                                    <i class="fas fa-play-circle me-1"></i>
                                                    Generar Guardias
                                                </button>
                                                <a href="index.php" class="btn btn-modern-secondary flex-fill">
                                                    <i class="fas fa-times-circle me-1"></i>
                                                    Cancelar
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <!-- Información del Sistema -->
                                    <div class="col-md-6">
                                        <h6 class="form-label-modern mb-2">
                                            <i class="fas fa-info-circle text-primary me-1"></i>
                                            Información del Sistema
                                        </h6>
                                        <div class="alert info-panel">
                                            <div class="info-item">
                                                <i class="fas fa-building text-primary"></i>
                                                <div>
                                                    <strong>CENTRALES:</strong> Dom-Jue (1,3,5,7,9,11,13,15,17)
                                                </div>
                                            </div>
                                            <div class="info-item">
                                                <i class="fas fa-map-marker-alt text-success"></i>
                                                <div>
                                                    <strong>REGIONALES:</strong> Vie-Sáb (2,4,6,8,10,12,14,16,18)
                                                </div>
                                            </div>
                                            <div class="info-item">
                                                <i class="fas fa-phone-slash text-warning"></i>
                                                <div>
                                                    <strong>Domingos:</strong> IDs 7-8 deshabilitados
                                                </div>
                                            </div>
                                            <div class="info-item">
                                                <i class="fas fa-users text-info"></i>
                                                <div>
                                                    <strong>Personal:</strong> 9-10: 4 pers. | 17-18: 3 pers.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let duplicadoDetectado = false;
        let duplicadoFecha = false;
        let duplicadoOrden = false;
        
        // Función para verificar duplicados (fecha y orden por separado)
        async function verificarDuplicado() {
            const fechaGuardia = document.getElementById('fecha_guardia').value;
            const ordenDia = document.getElementById('orden_dia').value;
            const submitBtn = document.querySelector('button[type="submit"]');
            const alertContainer = document.querySelector('.card-body');
            
            // Remover alertas previas de duplicado
            const alertaPrevia = document.querySelector('.alert-warning-duplicado');
            if (alertaPrevia) {
                alertaPrevia.remove();
            }
            
            // Si no hay ningún campo, resetear estado
            if (!fechaGuardia && !ordenDia) {
                duplicadoDetectado = false;
                duplicadoFecha = false;
                duplicadoOrden = false;
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-play-circle me-1"></i> Generar Guardias';
                return;
            }
            
            try {
                const formData = new FormData();
                if (fechaGuardia) formData.append('fecha_guardia', fechaGuardia);
                if (ordenDia) formData.append('orden_dia', ordenDia);
                
                const response = await fetch('api/verificar_duplicado.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.error) {
                    console.error('Error del servidor:', data.error);
                    return;
                }
                
                // Actualizar estados de duplicado
                duplicadoFecha = data.duplicado_fecha || false;
                duplicadoOrden = data.duplicado_orden || false;
                duplicadoDetectado = data.duplicado || false;
                
                // Actualizar botón según el estado
                if (duplicadoDetectado) {
                    submitBtn.disabled = true;
                    if (duplicadoFecha && duplicadoOrden) {
                        submitBtn.innerHTML = '<i class="fas fa-ban me-1"></i> 🚫 Fecha y Orden Duplicados';
                    } else if (duplicadoFecha) {
                        submitBtn.innerHTML = '<i class="fas fa-ban me-1"></i> 🚫 Fecha Duplicada';
                    } else if (duplicadoOrden) {
                        submitBtn.innerHTML = '<i class="fas fa-ban me-1"></i> 🚫 Orden Duplicado';
                    } else {
                        submitBtn.innerHTML = '<i class="fas fa-ban me-1"></i> 🚫 Duplicado Detectado';
                    }
                    
                    // Mostrar alerta de duplicado con mensajes específicos
                    const alertaDuplicado = document.createElement('div');
                    alertaDuplicado.className = 'alert alert-warning alert-dismissible fade show py-2 alert-warning-duplicado';
                    
                    let iconoAlerta = '<i class="fas fa-exclamation-triangle me-2"></i>';
                    let mensajeCompleto = data.mensaje || 'Se detectaron duplicados';
                    
                    alertaDuplicado.innerHTML = `
                        ${iconoAlerta}
                        <strong>¡Atención!</strong> ${mensajeCompleto}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    
                    // Insertar la alerta
                    const existingAlert = document.querySelector('.alert-danger');
                    if (existingAlert) {
                        existingAlert.parentNode.insertBefore(alertaDuplicado, existingAlert.nextSibling);
                    } else {
                        alertContainer.insertBefore(alertaDuplicado, alertContainer.firstChild);
                    }
                } else {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-play-circle me-1"></i> Generar Guardias';
                }
            } catch (error) {
                console.error('Error al verificar duplicado:', error);
                duplicadoDetectado = false;
                duplicadoFecha = false;
                duplicadoOrden = false;
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-play-circle me-1"></i> Generar Guardias';
            }
        }
        
        // Función para verificar solo fecha
        async function verificarFecha() {
            const fechaGuardia = document.getElementById('fecha_guardia').value;
            if (!fechaGuardia) return;
            
            try {
                const formData = new FormData();
                formData.append('fecha_guardia', fechaGuardia);
                
                const response = await fetch('api/verificar_duplicado.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                duplicadoFecha = data.duplicado_fecha || false;
                
                // Actualizar estado general
                duplicadoDetectado = duplicadoFecha || duplicadoOrden;
                actualizarBotonSubmit();
                
            } catch (error) {
                console.error('Error al verificar fecha:', error);
            }
        }
        
        // Función para verificar solo orden
        async function verificarOrden() {
            const ordenDia = document.getElementById('orden_dia').value;
            if (!ordenDia) return;
            
            try {
                const formData = new FormData();
                formData.append('orden_dia', ordenDia);
                
                const response = await fetch('api/verificar_duplicado.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                duplicadoOrden = data.duplicado_orden || false;
                
                // Actualizar estado general
                duplicadoDetectado = duplicadoFecha || duplicadoOrden;
                actualizarBotonSubmit();
                
            } catch (error) {
                console.error('Error al verificar orden:', error);
            }
        }
        
        // Función para actualizar el botón de submit
        function actualizarBotonSubmit() {
            const submitBtn = document.querySelector('button[type="submit"]');
            
            if (duplicadoDetectado) {
                submitBtn.disabled = true;
                if (duplicadoFecha && duplicadoOrden) {
                    submitBtn.innerHTML = '<i class="fas fa-ban me-1"></i> 🚫 Fecha y Orden Duplicados';
                } else if (duplicadoFecha) {
                    submitBtn.innerHTML = '<i class="fas fa-ban me-1"></i> 🚫 Fecha Duplicada';
                } else if (duplicadoOrden) {
                    submitBtn.innerHTML = '<i class="fas fa-ban me-1"></i> 🚫 Orden Duplicado';
                }
            } else {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-play-circle me-1"></i> Generar Guardias';
            }
        }
        
        // Validación del formato del número de orden y prevención de duplicados
        document.getElementById('formGenerarGuardia').addEventListener('submit', function(e) {
            const ordenInput = document.getElementById('orden_dia');
            const ordenValue = ordenInput.value.trim();
            
            // Prevenir envío si hay duplicado detectado
            if (duplicadoDetectado) {
                e.preventDefault();
                let mensaje = 'No se puede generar la guardia porque ';
                if (duplicadoFecha && duplicadoOrden) {
                    mensaje += 'tanto la fecha como el orden del día ya están en uso.';
                } else if (duplicadoFecha) {
                    mensaje += 'la fecha seleccionada ya tiene una guardia asignada.';
                } else if (duplicadoOrden) {
                    mensaje += 'el orden del día ya está asignado a otra fecha.';
                } else {
                    mensaje += 'se detectaron duplicados.';
                }
                alert(mensaje);
                return;
            }
            
            // Validar formato número/año
            const regex = /^\d+\/\d{4}$/;
            if (!regex.test(ordenValue)) {
                e.preventDefault();
                alert('El número de orden debe tener el formato: número/año (ej: 27/2025)');
                ordenInput.focus();
            }
        });
        
        // Verificar duplicados cuando cambie la fecha
        document.getElementById('fecha_guardia').addEventListener('change', function() {
            const fecha = new Date(this.value);
            const diaSemana = fecha.getDay(); // 0=Domingo, 1=Lunes, ..., 6=Sábado
            
            let zona = '';
            if (diaSemana === 0 || diaSemana >= 1 && diaSemana <= 4) { // Domingo a Jueves
                zona = 'CENTRAL';
            } else { // Viernes y Sábado
                zona = 'REGIONAL';
            }
            
            // Mostrar alerta informativa
            const infoDiv = document.querySelector('.alert-info');
            if (infoDiv) {
                infoDiv.innerHTML = `<strong>Día seleccionado:</strong> ${fecha.toLocaleDateString('es-ES', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}<br>
                                   <strong>Zona:</strong> ${zona}<br>
                                   <strong>Lugares activos:</strong> ${zona === 'CENTRAL' ? '1,3,5,7,9,11,13,15,17' : '2,4,6,8,10,12,14,16,18'}${diaSemana === 0 ? ' (IDs 7-8 deshabilitados)' : ''}`;
            }
            
            // Verificar duplicados completos (ambos campos si están disponibles)
            verificarDuplicado();
        });
        
        // Verificar duplicados cuando cambie el orden del día
        document.getElementById('orden_dia').addEventListener('input', function() {
            // Debounce para evitar muchas peticiones
            clearTimeout(this.timeoutId);
            this.timeoutId = setTimeout(() => {
                // Verificar duplicados completos (ambos campos si están disponibles)
                verificarDuplicado();
            }, 500);
        });
        
        // Verificar duplicados al cargar la página si hay valores
        document.addEventListener('DOMContentLoaded', function() {
            verificarDuplicado();
        });
    </script>
</body>
</html>