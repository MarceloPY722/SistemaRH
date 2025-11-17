<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

// Procesar formulario de creación de servicio
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombre = $_POST['nombre'];
        $tipo_servicio_id = $_POST['tipo_servicio_id'];
        $fecha_servicio = $_POST['fecha_servicio'];
        $descripcion = $_POST['descripcion'] ?? '';
        // Mapear la fecha del formulario a inicio/fin del día según esquema
        $fecha_inicio = $fecha_servicio . ' 00:00:00';
        $fecha_fin = $fecha_servicio . ' 23:59:59';
        $personal_seleccionado = $_POST['personal_seleccionado'] ?? [];
        
        // Insertar el servicio (ajustado al esquema actual con fecha_inicio/fecha_fin)
        $stmt = $conn->prepare("
            INSERT INTO servicios (tipo_servicio_id, nombre, descripcion, fecha_inicio, fecha_fin, estado) 
            VALUES (?, ?, ?, ?, ?, 'PROGRAMADO')
        ");
        
        $stmt->execute([
            $tipo_servicio_id, 
            $nombre, 
            $descripcion, 
            $fecha_inicio,
            $fecha_fin
        ]);
        
        $servicio_id = $conn->lastInsertId();
        
        // Insertar asignaciones de personal
        if (!empty($personal_seleccionado)) {
            $stmt_asignacion = $conn->prepare("
                INSERT INTO asignaciones_servicios (servicio_id, policia_id, puesto, observaciones, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $puestoPorDefecto = 'OFICIAL DE SERVICIO';
            $observaciones = '';
            foreach ($personal_seleccionado as $policia_id) {
                $stmt_asignacion->execute([$servicio_id, $policia_id, $puestoPorDefecto, $observaciones]);
            }
        }
        
        header("Location: index.php?success=servicio_creado");
        exit();
        
    } catch (Exception $e) {
        $error_message = "Error al crear el servicio: " . $e->getMessage();
    }
}

// Cargar tipos de servicios desde la base de datos
try {
    $tipos_servicios = $conn->query("SELECT * FROM tipos_servicios WHERE activo = 1 ORDER BY nombre")->fetchAll();
} catch (Exception $e) {
    $tipos_servicios = [];
    $error_message = "Error al cargar tipos de servicios: " . $e->getMessage();
}

// Cargar grados desde la base de datos
try {
    $grados = $conn->query("SELECT * FROM grados ORDER BY nivel_jerarquia")->fetchAll();
} catch (Exception $e) {
    $grados = [];
    if (!isset($error_message)) {
        $error_message = "Error al cargar grados: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Servicio - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../inc/sidebar.php'; ?>
            
            <!-- Contenido Principal -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Header de la página -->
                    <div class="page-header mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1 class="page-title">
                                    <i class="fas fa-plus-circle me-3"></i>
                                    Crear Nuevo Servicio
                                </h1>
                                <p class="page-subtitle text-muted">Complete la información del servicio y seleccione el personal</p>
                            </div>
                            <div>
                                <a href="index.php" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-arrow-left me-2"></i>Volver
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Alertas -->
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Indicador de pasos -->
                    <div class="steps-container mb-4">
                        <div class="step-indicator">
                            <div class="step active" id="step1">
                                <div class="step-icon">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <div class="step-content">
                                    <h6>Información Básica</h6>
                                    <p>Datos generales del servicio</p>
                                </div>
                            </div>
                            <div class="step-line"></div>
                            <div class="step" id="step2">
                                <div class="step-icon">
                                    <i class="fas fa-cogs"></i>
                                </div>
                                <div class="step-content">
                                    <h6>Tipo y Requisitos</h6>
                                    <p>Configuración específica</p>
                                </div>
                            </div>
                            <div class="step-line"></div>
                            <div class="step" id="step3">
                                <div class="step-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="step-content">
                                    <h6>Selección de Personal</h6>
                                    <p>Asignar efectivos</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" id="formCrearServicio">
                        <!-- Paso 1: Información Básica -->
                        <div class="form-card" id="paso1">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Información Básica del Servicio
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Nombre del Servicio *</label>
                                            <input type="text" name="nombre" class="form-control" required 
                                                   placeholder="Ej: Manifestación Asunción 2024">
                                            <div class="form-text">Ingrese un nombre descriptivo para el servicio</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Fecha del Servicio *</label>
                                            <input type="date" name="fecha_servicio" class="form-control" required 
                                                   min="<?php echo date('Y-m-d'); ?>">
                                            <div class="form-text">Seleccione la fecha cuando se realizará el servicio</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label class="form-label">Descripción</label>
                                            <textarea name="descripcion" class="form-control" rows="4" 
                                                      placeholder="Describa los detalles del servicio..."></textarea>
                                            <div class="form-text">Proporcione información adicional sobre el servicio</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="button" class="btn btn-primary btn-lg" onclick="siguientePaso(2)">
                                        Siguiente <i class="fas fa-arrow-right ms-2"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Paso 2: Tipo y Requisitos -->
                        <div class="form-card" id="paso2" style="display: none;">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-cogs me-2"></i>
                                    Tipo de Servicio y Requisitos
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Tipo de Servicio *</label>
                                            <select name="tipo_servicio_id" class="form-select" required id="tipoServicio">
                                                <option value="">Seleccione un tipo de servicio</option>
                                                <?php foreach ($tipos_servicios as $tipo): ?>
                                                    <option value="<?php echo $tipo['id']; ?>" 
                                                            data-descripcion="<?php echo htmlspecialchars($tipo['descripcion'] ?? ''); ?>"
                                                            data-requisitos="<?php echo htmlspecialchars($tipo['requisitos'] ?? ''); ?>">
                                                        <?php echo htmlspecialchars($tipo['nombre'] ?? ''); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Orden del Día</label>
                                            <textarea name="orden_del_dia" class="form-control" rows="3" 
                                                      placeholder="Detalle la orden del día para este servicio..."></textarea>
                                        </div>
                                    </div>
                                    
                                    <!-- Sección de Requisitos de Personal -->
                                    <div class="col-12">
                                        <div class="requisitos-section">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-users me-2"></i>
                                                    Requisitos de Personal
                                                </h6>
                                                <button type="button" class="btn btn-primary" onclick="abrirModalRequisitos()" title="Definir requisitos específicos">
                                                    <i class="fas fa-plus me-2"></i>Definir Requisitos
                                                </button>
                                            </div>
                                            <div id="requisitosPersonal" class="requisitos-display">
                                                <div class="alert alert-info mb-0">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    No se han definido requisitos específicos. Haga clic en "Definir Requisitos" para comenzar.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <div id="tipoInfo" class="tipo-info-card" style="display: none;">
                                            <div class="info-header">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong>Información del Tipo de Servicio</strong>
                                            </div>
                                            <div class="info-content">
                                                <div class="info-section">
                                                    <h6><i class="fas fa-file-alt me-2"></i>Descripción:</h6>
                                                    <p id="tipoDescripcion"></p>
                                                </div>
                                                <div class="info-section">
                                                    <h6><i class="fas fa-users me-2"></i>Personal Requerido:</h6>
                                                    <p id="tipoRequisitos"></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="button" class="btn btn-outline-secondary btn-lg" onclick="anteriorPaso(1)">
                                        <i class="fas fa-arrow-left me-2"></i> Anterior
                                    </button>
                                    <button type="button" class="btn btn-primary btn-lg" onclick="siguientePaso(3)">
                                        Siguiente <i class="fas fa-arrow-right ms-2"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Paso 3: Selección de Personal -->
                        <div class="form-card" id="paso3" style="display: none;">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-users me-2"></i>
                                    Selección de Personal
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Filtros de personal -->
                                <div class="personnel-filters mb-4">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Filtrar por grado:</label>
                                            <select class="form-select" id="filtroGrado">
                                                <option value="">Todos los grados</option>
                                                <?php foreach ($grados as $grado): ?>
                                                    <option value="<?php echo $grado['id']; ?>"><?php echo htmlspecialchars($grado['nombre'] ?? ''); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Buscar personal:</label>
                                            <input type="text" class="form-control" id="buscarPersonal" 
                                                   placeholder="Buscar por nombre o CI...">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Mostrar:</label>
                                            <select class="form-select" id="filtroDisponibilidad">
                                                <option value="todos">Todo el personal</option>
                                                <option value="disponible">Solo disponibles</option>
                                                <option value="no_disponible">Solo no disponibles</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Contador de seleccionados y requeridos -->
                                <div class="selection-counter mb-3">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <div class="counter-card">
                                                <i class="fas fa-users me-2"></i>
                                                <span>Personal seleccionado: </span>
                                                <strong id="contadorSeleccionados">0</strong>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="counter-card counter-required">
                                                <i class="fas fa-calculator me-2"></i>
                                                <span>Personal requerido: </span>
                                                <strong id="contadorRequerido">0</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="progress-indicator mt-2">
                                        <div class="progress">
                                            <div class="progress-bar" id="progresoSeleccion" role="progressbar" 
                                                 style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                            </div>
                                        </div>
                                        <small class="text-muted mt-1 d-block" id="estadoSeleccion">
                                            Seleccione el personal necesario para el servicio
                                        </small>
                                    </div>
                                </div>

                                <!-- Lista de personal -->
                                <div id="listaPersonal" class="personnel-list">
                                    <div class="loading-state">
                                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                                        <p>Cargando personal disponible...</p>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="button" class="btn btn-outline-secondary btn-lg" onclick="anteriorPaso(2)">
                                        <i class="fas fa-arrow-left me-2"></i> Anterior
                                    </button>
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-save me-2"></i> Crear Servicio
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let pasoActual = 1;
        let personalSeleccionado = [];

        // Navegación entre pasos
        function siguientePaso(paso) {
            if (validarPaso(pasoActual)) {
                document.getElementById('paso' + pasoActual).style.display = 'none';
                document.getElementById('step' + pasoActual).classList.remove('active');
                document.getElementById('step' + pasoActual).classList.add('completed');
                
                pasoActual = paso;
                document.getElementById('paso' + paso).style.display = 'block';
                document.getElementById('step' + paso).classList.add('active');
                
                if (paso === 3) {
                    cargarPersonal();
                    calcularPersonalRequerido();
                }
            }
        }

        function anteriorPaso(paso) {
            document.getElementById('paso' + pasoActual).style.display = 'none';
            document.getElementById('step' + pasoActual).classList.remove('active');
            
            pasoActual = paso;
            document.getElementById('paso' + paso).style.display = 'block';
            document.getElementById('step' + paso).classList.add('active');
            document.getElementById('step' + paso).classList.remove('completed');
        }

        function validarPaso(paso) {
            if (paso === 1) {
                const nombre = document.querySelector('input[name="nombre"]').value;
                const fecha = document.querySelector('input[name="fecha_servicio"]').value;
                
                if (!nombre || !fecha) {
                    alert('Por favor complete todos los campos obligatorios');
                    return false;
                }
            } else if (paso === 2) {
                const tipo = document.querySelector('select[name="tipo_servicio_id"]').value;
                
                if (!tipo) {
                    alert('Por favor seleccione un tipo de servicio');
                    return false;
                }
            }
            return true;
        }

        // Mostrar información del tipo de servicio y cargar requisitos desde la base de datos
        document.getElementById('tipoServicio').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const tipoInfo = document.getElementById('tipoInfo');
            const tipoServicioId = selectedOption.value;
            
            if (tipoServicioId) {
                // Mostrar información básica
                document.getElementById('tipoDescripcion').textContent = selectedOption.dataset.descripcion || 'No disponible';
                
                // Cargar requisitos desde la base de datos
                fetch('obtener_tipos_servicios.php?id=' + tipoServicioId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            throw new Error(data.error);
                        }
                        
                        const requisitosElement = document.getElementById('tipoRequisitos');
                        const requisitosPersonalDiv = document.getElementById('requisitosPersonal');
                        
                        if (data.requisitos && data.requisitos.length > 0) {
                            // Mostrar resumen de requisitos
                            const totalPersonal = data.requisitos.reduce((sum, req) => sum + parseInt(req.cantidad_requerida), 0);
                            requisitosElement.innerHTML = `<span class="badge bg-primary fs-6">${totalPersonal} ${totalPersonal === 1 ? 'persona' : 'personas'}</span>`;
                            
                            // Mostrar requisitos detallados
                            let requisitosHtml = '<div class="requisitos-detalle">';
                            requisitosHtml += '<h6><i class="fas fa-list me-2"></i>Requisitos Específicos:</h6>';
                            requisitosHtml += '<div class="row g-2">';
                            
                            data.requisitos.forEach(req => {
                                const generoTexto = req.genero === 'MASCULINO' ? 'Masculino' : req.genero === 'FEMENINO' ? 'Femenino' : 'Ambos';
                                requisitosHtml += `
                                    <div class="col-md-6">
                                        <div class="requisito-card">
                                            <div class="requisito-info">
                                                <span class="cantidad">${req.cantidad_requerida}</span>
                                                <span class="grado">${req.grado_nombre}</span>
                                                <span class="genero">${generoTexto}</span>
                                                ${req.region_nombre ? `<span class="region">${req.region_nombre}</span>` : ''}
                                            </div>
                                            ${req.descripcion_puesto ? `<small class="descripcion">${req.descripcion_puesto}</small>` : ''}
                                        </div>
                                    </div>
                                `;
                            });
                            
                            requisitosHtml += '</div></div>';
                            requisitosPersonalDiv.innerHTML = requisitosHtml;
                            
                            // Guardar requisitos para uso posterior
                            localStorage.setItem('requisitos_servicio_' + tipoServicioId, JSON.stringify(data.requisitos));
                        } else {
                            requisitosElement.innerHTML = '<span class="text-muted">No especificado</span>';
                            requisitosPersonalDiv.innerHTML = `
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Este tipo de servicio no tiene requisitos específicos definidos.
                                </div>
                            `;
                        }
                        
                        tipoInfo.style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Error al cargar requisitos:', error);
                        document.getElementById('tipoRequisitos').innerHTML = '<span class="text-danger">Error al cargar</span>';
                        document.getElementById('requisitosPersonal').innerHTML = `
                            <div class="alert alert-danger mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Error al cargar los requisitos: ${error.message}
                            </div>
                        `;
                        tipoInfo.style.display = 'block';
                    });
            } else {
                tipoInfo.style.display = 'none';
                document.getElementById('requisitosPersonal').innerHTML = `
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Seleccione un tipo de servicio para ver los requisitos.
                    </div>
                `;
            }
        });

        // Cargar personal disponible basado en tipo de servicio y fecha
        function cargarPersonal() {
            const fechaServicio = document.querySelector('input[name="fecha_servicio"]').value;
            const tipoServicioId = document.querySelector('select[name="tipo_servicio_id"]').value;
            const listaPersonal = document.getElementById('listaPersonal');
            
            if (!fechaServicio) {
                listaPersonal.innerHTML = '<div class="alert alert-warning"><i class="fas fa-calendar-times me-2"></i>Por favor seleccione una fecha de servicio primero</div>';
                return;
            }
            
            if (!tipoServicioId) {
                listaPersonal.innerHTML = '<div class="alert alert-warning"><i class="fas fa-clipboard-list me-2"></i>Por favor seleccione un tipo de servicio primero</div>';
                return;
            }
            
            // Mostrar estado de carga
            listaPersonal.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Cargando personal disponible...</p></div>';
            
            fetch('obtener_personal_disponible.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    tipo_servicio_id: tipoServicioId,
                    fecha_servicio: fechaServicio
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Personal disponible:', data);
                
                if (!data.success) {
                    throw new Error(data.message || 'Error al obtener personal disponible');
                }
                
                mostrarPersonalDisponible(data);
            })
            .catch(error => {
                console.error('Error completo:', error);
                listaPersonal.innerHTML = `
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Error al cargar el personal</h5>
                        <p class="mb-0">${error.message}</p>
                        <small class="d-block mt-2">Revise la consola del navegador (F12) para más detalles</small>
                    </div>
                `;
            });
        }

        function mostrarPersonalDisponible(data) {
            const listaPersonal = document.getElementById('listaPersonal');
            const personal = data.personal || [];
            const requisitosBackend = data.requisitos || {};
            const requisitosCustom = (function() {
                try {
                    const tipoServicioId = document.querySelector('select[name="tipo_servicio_id"]').value;
                    const raw = localStorage.getItem('requisitos_servicio_' + tipoServicioId);
                    return raw ? JSON.parse(raw) : [];
                } catch (e) { return []; }
            })();
            
            // Elegir fuente de requisitos: prioridad a los definidos por el usuario
            let requisitosFuente = [];
            if (Array.isArray(requisitosCustom) && requisitosCustom.length > 0) {
                requisitosFuente = requisitosCustom.map((req, index) => ({
                    id: index,
                    grado_nombre: obtenerNombreGrado(req.grado),
                    genero: req.genero || 'AMBOS',
                    region_id: req.region || null,
                    cantidad: parseInt(req.cantidad || 1, 10)
                }));
            } else if (Array.isArray(requisitosBackend.grados)) {
                requisitosFuente = requisitosBackend.grados.map((req, index) => ({
                    id: index,
                    grado_nombre: req.grado_nombre,
                    genero: req.genero || 'AMBOS',
                    region_id: req.region_id || null,
                    cantidad: parseInt(req.cantidad || 1, 10)
                }));
            }
            
            if (personal.length === 0) {
                listaPersonal.innerHTML = `
                    <div class="empty-personnel">
                        <i class="fas fa-user-slash fa-3x"></i>
                        <h5>No hay personal disponible</h5>
                        <p>No se encontró personal disponible para este tipo de servicio en la fecha seleccionada</p>
                    </div>
                `;
                return;
            }
            
            // Agrupar personal por requisitos
            const gruposRequisitos = {};
            let totalRequerido = 0;
            
            requisitosFuente.forEach((req) => {
                const key = `${req.grado_nombre}_${req.genero || 'AMBOS'}_${req.region_id ?? 'ANY'}`;
                gruposRequisitos[key] = {
                    id: req.id,
                    grado: req.grado_nombre,
                    genero: req.genero || 'AMBOS',
                    region_id: req.region_id ?? null,
                    cantidad: req.cantidad || 1,
                    personal: []
                };
                totalRequerido += req.cantidad || 1;
            });
            
            // Clasificar personal en grupos (grado + género + región)
            personal.forEach(persona => {
                Object.keys(gruposRequisitos).forEach(k => {
                    const grupo = gruposRequisitos[k];
                    const matchGrado = persona.grado_nombre === grupo.grado;
                    const matchGenero = grupo.genero === 'AMBOS' || persona.genero === grupo.genero;
                    const matchRegion = !grupo.region_id || (String(persona.region_id) === String(grupo.region_id));
                    if (matchGrado && matchGenero && matchRegion) {
                        grupo.personal.push(persona);
                    }
                });
            });
            
            let html = '<div class="personal-disponible-container">';
            
            // Mostrar estadísticas generales
            html += `
                <div class="stats-container mb-4">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <i class="fas fa-users text-primary"></i>
                                <div class="stat-info">
                                    <h6>Total Personal</h6>
                                    <span class="stat-number">${data.total || 0}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <i class="fas fa-check-circle text-success"></i>
                                <div class="stat-info">
                                    <h6>Disponibles</h6>
                                    <span class="stat-number">${data.disponibles || 0}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <i class="fas fa-clipboard-list text-info"></i>
                                <div class="stat-info">
                                    <h6>Requeridos</h6>
                                    <span class="stat-number" id="contadorRequeridoStats">${totalRequerido}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <i class="fas fa-user-check text-warning"></i>
                                <div class="stat-info">
                                    <h6>Seleccionados</h6>
                                    <span class="stat-number" id="contadorSeleccionados">0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            html += `
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-list-check me-2"></i>Resumen de selección</span>
                        <button class="btn btn-sm btn-outline-warning" type="button" onclick="deseleccionarTodos()">Limpiar selección</button>
                    </div>
                    <div class="card-body" id="resumenSeleccion">
                        <small class="text-muted">No hay personal seleccionado aún.</small>
                    </div>
                </div>
            `;
            
            // Mostrar grupos de requisitos
            Object.values(gruposRequisitos).forEach(grupo => {
                const disponiblesGrupo = grupo.personal.filter(p => p.disponible).length;
                const cumpleRequisito = disponiblesGrupo >= grupo.cantidad;
                const porcentaje = grupo.cantidad > 0 ? Math.round((disponiblesGrupo / grupo.cantidad) * 100) : 0;
                
                html += `
                    <div class="requisito-grupo mb-4 collapsed" id="grupo_${grupo.id}" data-requisito-id="${grupo.id}">
                        <div class="requisito-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="fas fa-users me-2"></i>
                                    ${grupo.cantidad} ${grupo.grado} 
                                    ${grupo.genero === 'M' ? 'Masculino' : grupo.genero === 'F' ? 'Femenino' : 'Ambos'}
                                    ${grupo.region_id ? `<span class="badge bg-light text-dark ms-2">Región ${grupo.region_id}</span>` : ''}
                                </h6>
                                <div class="req-actions d-flex align-items-center">
                                    <div class="requisito-status" id="estado_grupo_${grupo.id}">
                                        <span class="badge ${cumpleRequisito ? 'bg-success' : 'bg-warning'}">
                                            ${disponiblesGrupo}/${grupo.cantidad} disponibles (${porcentaje}%)
                                        </span>
                                        <span class="badge bg-info ms-1" id="seleccionados_grupo_${grupo.id}">0/${grupo.cantidad} seleccionados</span>
                                    </div>
                                    <button class="btn btn-sm btn-outline-secondary btn-req btn-req-toggle" onclick="toggleGrupo(${grupo.id})" type="button">
                                        <i class="fas fa-chevron-down me-1"></i><span>Desplegar</span>
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary btn-req btn-req-select" onclick="seleccionarTodoGrupo(${grupo.id})" type="button">
                                        <i class="fas fa-check-square me-1"></i><span>Seleccionar todo</span>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger btn-req btn-req-clear" onclick="deseleccionarTodoGrupo(${grupo.id})" type="button">
                                        <i class="fas fa-times me-1"></i><span>Quitar selección</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="personal-grupo mt-3">
                `;
                
                if (grupo.personal.length === 0) {
                    html += `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No se encontró personal que cumpla estos requisitos
                        </div>
                    `;
                } else {
                    grupo.personal.forEach(persona => {
                        const disponible = persona.disponible;
                        const generoTexto = persona.genero === 'M' ? 'Masculino' : persona.genero === 'F' ? 'Femenino' : (persona.genero || '');
                        const generoIcon = persona.genero === 'M' ? 'mars' : 'venus';
                        const ultimaGuardia = persona.ultima_guardia ? persona.ultima_guardia : '—';
                        const ultimaServicio = persona.ultima_servicio ? persona.ultima_servicio : '—';
                        html += `
                            <div class="personnel-card ${disponible ? 'available' : 'unavailable'}" 
                                 data-requisito="${grupo.id}" 
                                 data-grado="${persona.grado_nombre}"
                                 data-grado-id="${persona.grado_id}"
                                 data-nombre="${persona.nombre} ${persona.apellido}"
                                 data-ci="${persona.cin || ''}"
                                 data-genero="${persona.genero}"
                                 data-cupo="${grupo.cantidad}">
                                <div class="personnel-info">
                                    <div class="personnel-header">
                                        <h6 class="personnel-name">${persona.nombre} ${persona.apellido}</h6>
                                        <span class="personnel-rank">${persona.grado_nombre}</span>
                                        <span class="availability-badge ${disponible ? 'available' : 'unavailable'}">
                                            <i class="fas fa-${disponible ? 'check-circle' : 'times-circle'} me-1"></i>
                                            ${disponible ? 'Disponible' : 'No disponible'}
                                        </span>
                                    </div>
                                    <div class="personnel-details">
                                        <span class="personnel-legajo"><i class="fas fa-id-card me-1"></i>Legajo: ${persona.legajo}</span>
                                        <span class="personnel-gender"><i class="fas fa-${generoIcon} me-1"></i>${generoTexto}</span>
                                        <span class="personnel-ci"><i class="fas fa-id-badge me-1"></i>CI: ${persona.cin || '—'}</span>
                                    </div>
                                    <div class="personnel-last">
                                        <small class="text-muted"><i class="fas fa-shield-alt me-1"></i>Ult. Guardia: ${ultimaGuardia}</small>
                                        <small class="text-muted ms-3"><i class="fas fa-briefcase me-1"></i>Ult. Servicio: ${ultimaServicio}</small>
                                    </div>
                                </div>
                                <div class="personnel-actions">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="personal_seleccionado[]" 
                                               value="${persona.id}" 
                                               id="personal_${persona.id}" 
                                               data-requisito="${grupo.id}"
                                               ${!disponible ? 'disabled' : ''}
                                               onchange="validarSeleccionRequisito(this)">
                                        <label class="form-check-label" for="personal_${persona.id}">
                                            ${disponible ? 'Seleccionar' : 'No disponible'}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
                
                html += `
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            
            listaPersonal.innerHTML = html;
            actualizarContador();
            renderizarResumenSeleccion();
            document.querySelectorAll('.requisito-grupo').forEach(el => {
                const reqId = el.dataset.requisitoId;
                if (reqId) actualizarEstadoGrupo(reqId);
            });
        }

        function validarSeleccionRequisito(checkbox) {
            const requisitoId = checkbox.getAttribute('data-requisito');
            const seleccionados = document.querySelectorAll(`input[data-requisito="${requisitoId}"]:checked`);
            // Obtener cupo directamente del contenedor del card
            const card = checkbox.closest('.personnel-card');
            const cupo = card ? parseInt(card.dataset.cupo || '0') : 0;
            if (cupo > 0 && seleccionados.length > cupo) {
                checkbox.checked = false;
                alert(`Solo puede seleccionar ${cupo} personas para este requisito.`);
                return;
            }
            
            actualizarContador();
            actualizarEstadoGrupo(requisitoId);
            renderizarResumenSeleccion();
        }
        
        function actualizarContador() {
            const seleccionados = document.querySelectorAll('input[name="personal_seleccionado[]"]:checked');
            const cantidadSeleccionada = seleccionados.length;
            const cantidadRequerida = parseInt(document.getElementById('contadorRequerido').textContent) || 0;
            
            // Actualizar contador de seleccionados
            document.getElementById('contadorSeleccionados').textContent = cantidadSeleccionada;
            
            // Actualizar barra de progreso
            const progreso = cantidadRequerida > 0 ? (cantidadSeleccionada / cantidadRequerida) * 100 : 0;
            const barraProgreso = document.getElementById('progresoSeleccion');
            const estadoSeleccion = document.getElementById('estadoSeleccion');
            
            barraProgreso.style.width = Math.min(progreso, 100) + '%';
            barraProgreso.setAttribute('aria-valuenow', Math.min(progreso, 100));
            
            // Actualizar estado y color de la barra
            if (cantidadSeleccionada === 0) {
                barraProgreso.className = 'progress-bar';
                estadoSeleccion.textContent = 'Seleccione el personal necesario para el servicio';
                estadoSeleccion.className = 'text-muted mt-1 d-block';
            } else if (cantidadSeleccionada < cantidadRequerida) {
                barraProgreso.className = 'progress-bar bg-warning';
                estadoSeleccion.textContent = `Faltan ${cantidadRequerida - cantidadSeleccionada} personas por seleccionar`;
                estadoSeleccion.className = 'text-warning mt-1 d-block';
            } else if (cantidadSeleccionada === cantidadRequerida) {
                barraProgreso.className = 'progress-bar bg-success';
                estadoSeleccion.textContent = '¡Personal completo! Cantidad exacta seleccionada';
                estadoSeleccion.className = 'text-success mt-1 d-block';
            } else {
                barraProgreso.className = 'progress-bar bg-danger';
                estadoSeleccion.textContent = `Exceso de ${cantidadSeleccionada - cantidadRequerida} personas seleccionadas`;
                estadoSeleccion.className = 'text-danger mt-1 d-block';
            }
        }

        // Filtros de personal
        document.getElementById('filtroGrado').addEventListener('change', aplicarFiltros);
        document.getElementById('buscarPersonal').addEventListener('input', aplicarFiltros);
        document.getElementById('filtroDisponibilidad').addEventListener('change', aplicarFiltros);

        // Validación al enviar el formulario: debe cumplirse la cantidad requerida total
        const formCrearServicio = document.getElementById('formCrearServicio');
        if (formCrearServicio) {
            formCrearServicio.addEventListener('submit', function(e) {
                const seleccionados = document.querySelectorAll('input[name="personal_seleccionado[]"]:checked');
                const cantidadSeleccionada = seleccionados.length;
                const cantidadRequerida = parseInt(document.getElementById('contadorRequerido').textContent) || 0;
                if (cantidadRequerida > 0 && cantidadSeleccionada !== cantidadRequerida) {
                    e.preventDefault();
                    mostrarNotificacionTemporal(`Debe seleccionar exactamente ${cantidadRequerida} personas (actualmente: ${cantidadSeleccionada}).`, 'danger');
                }
            });
        }

        function aplicarFiltros() {
            const filtroGrado = document.getElementById('filtroGrado').value;
            const buscarTexto = document.getElementById('buscarPersonal').value.toLowerCase();
            const filtroDisponibilidad = document.getElementById('filtroDisponibilidad').value;
            
            const cards = document.querySelectorAll('.personnel-card');
            
            cards.forEach(card => {
                const gradoId = card.dataset.gradoId || '';
                const nombre = (card.dataset.nombre || '').toLowerCase();
                const ci = (card.dataset.ci || '').toLowerCase();
                const disponible = card.classList.contains('available');
                
                let mostrar = true;
                
                if (filtroGrado && gradoId !== filtroGrado) mostrar = false;
                if (buscarTexto && !nombre.includes(buscarTexto) && !ci.includes(buscarTexto)) mostrar = false;
                if (filtroDisponibilidad === 'disponible' && !disponible) mostrar = false;
                if (filtroDisponibilidad === 'no_disponible' && disponible) mostrar = false;
                
                card.style.display = mostrar ? 'flex' : 'none';
            });
        }

        // === Acciones de requisito y resumen ===
        function toggleGrupo(requisitoId) {
            const contenedor = document.getElementById(`grupo_${requisitoId}`);
            if (!contenedor) return;
            contenedor.classList.toggle('collapsed');
            const btn = contenedor.querySelector('.btn-req-toggle');
            if (btn) {
                btn.innerHTML = contenedor.classList.contains('collapsed')
                    ? '<i class="fas fa-chevron-down me-1"></i><span>Desplegar</span>'
                    : '<i class="fas fa-chevron-up me-1"></i><span>Ocultar</span>';
            }
        }

        function seleccionarTodoGrupo(requisitoId) {
            const grupo = document.getElementById(`grupo_${requisitoId}`);
            if (!grupo) return;
            const checks = grupo.querySelectorAll(`input[data-requisito="${requisitoId}"]`);
            const card = grupo.querySelector('.personnel-card');
            const cupo = card ? parseInt(card.dataset.cupo || '0') : 0;
            const yaSeleccionados = grupo.querySelectorAll(`input[data-requisito="${requisitoId}"]:checked`).length;
            const disponibles = Array.from(checks).filter(c => !c.disabled && !c.checked);
            const porSeleccionar = cupo > 0 ? Math.max(0, cupo - yaSeleccionados) : disponibles.length;
            disponibles.slice(0, porSeleccionar).forEach(c => c.checked = true);
            actualizarContador();
            actualizarEstadoGrupo(requisitoId);
            renderizarResumenSeleccion();
        }

        function deseleccionarTodoGrupo(requisitoId) {
            const grupo = document.getElementById(`grupo_${requisitoId}`);
            if (!grupo) return;
            const checks = grupo.querySelectorAll(`input[data-requisito="${requisitoId}"]`);
            checks.forEach(c => c.checked = false);
            actualizarContador();
            actualizarEstadoGrupo(requisitoId);
            renderizarResumenSeleccion();
        }

        function deseleccionarTodos() {
            document.querySelectorAll('input[name="personal_seleccionado[]"]:checked').forEach(c => c.checked = false);
            actualizarContador();
            document.querySelectorAll('.requisito-grupo').forEach(el => {
                const reqId = el.dataset.requisitoId;
                if (reqId) actualizarEstadoGrupo(reqId);
            });
            renderizarResumenSeleccion();
        }

        function actualizarEstadoGrupo(requisitoId) {
            const grupo = document.getElementById(`grupo_${requisitoId}`);
            if (!grupo) return;
            const card = grupo.querySelector('.personnel-card');
            const cupo = card ? parseInt(card.dataset.cupo || '0') : 0;
            const seleccionados = grupo.querySelectorAll(`input[data-requisito="${requisitoId}"]:checked`).length;
            const badgeSel = document.getElementById(`seleccionados_grupo_${requisitoId}`);
            if (badgeSel) {
                badgeSel.textContent = `${seleccionados}/${cupo} seleccionados`;
                badgeSel.className = `badge ${seleccionados === cupo && cupo > 0 ? 'bg-success' : 'bg-info'} ms-1`;
            }
            const estado = document.getElementById(`estado_grupo_${requisitoId}`);
            if (estado) {
                const existenteCumplido = estado.querySelector('.cumplido-text');
                if (existenteCumplido) existenteCumplido.remove();
                const span = document.createElement('span');
                span.className = `cumplido-text badge ${seleccionados === cupo && cupo > 0 ? 'bg-success' : 'bg-warning'} ms-1`;
                span.textContent = seleccionados === cupo && cupo > 0 ? 'Requisito cumplido' : `Faltan ${Math.max(0, cupo - seleccionados)}`;
                estado.appendChild(span);
            }
        }

        function renderizarResumenSeleccion() {
            const cont = document.getElementById('resumenSeleccion');
            if (!cont) return;
            const seleccionados = Array.from(document.querySelectorAll('input[name="personal_seleccionado[]"]:checked'));
            if (seleccionados.length === 0) {
                cont.innerHTML = '<small class="text-muted">No hay personal seleccionado aún.</small>';
                return;
            }
            const grupos = {};
            seleccionados.forEach(chk => {
                const reqId = chk.getAttribute('data-requisito');
                const card = chk.closest('.personnel-card');
                const nombre = card ? (card.dataset.nombre || '') : '';
                const grupoEl = document.getElementById(`grupo_${reqId}`);
                const titulo = grupoEl ? (grupoEl.querySelector('.requisito-header h6')?.textContent || `Requisito ${reqId}`) : `Requisito ${reqId}`;
                if (!grupos[reqId]) grupos[reqId] = { titulo, personas: [] };
                grupos[reqId].personas.push(nombre);
            });
            let html = '';
            Object.values(grupos).forEach(g => {
                html += `<div class="mb-2"><div class="fw-semibold">${g.titulo}</div>`;
                html += '<div class="mt-1">';
                g.personas.forEach(n => {
                    html += `<span class=\"badge rounded-pill bg-secondary me-1\">${n}</span>`;
                });
                html += '</div></div>';
            });
            cont.innerHTML = html;
        }

        // Variables globales para el modal de requisitos
        let tipoServicioActual = null;
        let requisitosPersonal = [];

        // Función para abrir el modal de definición de requisitos
        function abrirModalRequisitos() {
            const tipoSelect = document.getElementById('tipoServicio');
            const selectedOption = tipoSelect.options[tipoSelect.selectedIndex];
            
            if (!selectedOption.value) {
                alert('Por favor seleccione un tipo de servicio primero');
                return;
            }

            tipoServicioActual = {
                id: selectedOption.value,
                nombre: selectedOption.textContent
            };

            // Llenar el modal con los datos actuales
            document.getElementById('tipoServicioNombre').value = tipoServicioActual.nombre;
            document.getElementById('tipoServicioId').value = tipoServicioActual.id;

            // Cargar requisitos existentes desde localStorage
            cargarRequisitosDesdeStorage();

            // Mostrar el modal
            document.getElementById('editRequisitosModal').style.display = 'flex';
        }

        // Función para cerrar el modal
        function cerrarModalRequisitos() {
            document.getElementById('editRequisitosModal').style.display = 'none';
            tipoServicioActual = null;
        }

        // Función para cargar requisitos desde localStorage
        function cargarRequisitosDesdeStorage() {
            // Si no hay tipo de servicio actual, intentar cargar desde el último guardado
            if (!tipoServicioActual) {
                const tipoSelect = document.getElementById('tipoServicio');
                if (tipoSelect && tipoSelect.value) {
                    tipoServicioActual = {
                        id: tipoSelect.value,
                        nombre: tipoSelect.options[tipoSelect.selectedIndex].textContent
                    };
                }
            }
            
            if (tipoServicioActual) {
                const storageKey = `requisitos_servicio_${tipoServicioActual.id}`;
                const requisitosGuardados = localStorage.getItem(storageKey);
                
                if (requisitosGuardados) {
                    try {
                        const requisitos = JSON.parse(requisitosGuardados);
                        if (Array.isArray(requisitos) && requisitos.length > 0) {
                            requisitosPersonal = requisitos;
                            if (typeof renderizarRequisitos === 'function') {
                                renderizarRequisitos();
                            }
                            return requisitos;
                        }
                    } catch (e) {
                        console.error('Error al parsear requisitos:', e);
                    }
                }
            }
            
            requisitosPersonal = [];
            if (typeof renderizarRequisitos === 'function') {
                renderizarRequisitos();
            }
            return [];
        }

        // Función para agregar un nuevo requisito
        function agregarRequisito() {
            const nuevoRequisito = {
                id: Date.now(),
                cantidad: 1,
                genero: '',
                grado: '',
                region: ''
            };
            
            requisitosPersonal.push(nuevoRequisito);
            renderizarRequisitos();
        }

        // Función para eliminar un requisito
        function eliminarRequisito(id) {
            requisitosPersonal = requisitosPersonal.filter(req => req.id !== id);
            renderizarRequisitos();
        }

        // Datos de grados desde PHP
        const gradosData = <?php echo json_encode(array_map(function($g) { 
            return ['id' => $g['id'], 'nombre' => $g['nombre']]; 
        }, $grados)); ?>;
        
        // Función para renderizar la lista de requisitos
        function renderizarRequisitos() {
            const container = document.getElementById('requisitosLista');
            
            if (requisitosPersonal.length === 0) {
                container.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <p>No hay requisitos definidos. Haga clic en "Agregar Requisito" para comenzar.</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = requisitosPersonal.map(req => {
                // Generar opciones de grados
                const gradosOptions = gradosData.map(grado => 
                    `<option value="${grado.id}" ${req.grado == grado.id ? 'selected' : ''}>${grado.nombre}</option>`
                ).join('');
                
                return `
                    <div class="requisito-item card mb-3" data-id="${req.id}">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label">Cantidad *</label>
                                    <input type="number" class="form-control" min="1" max="100" value="${req.cantidad}" 
                                           onchange="actualizarRequisito(${req.id}, 'cantidad', this.value)"
                                           placeholder="Ej: 10">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Género *</label>
                                    <select class="form-select" onchange="actualizarRequisito(${req.id}, 'genero', this.value)" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="M" ${req.genero === 'M' ? 'selected' : ''}>Masculino</option>
                                        <option value="F" ${req.genero === 'F' ? 'selected' : ''}>Femenino</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Grado *</label>
                                    <select class="form-select" onchange="actualizarRequisito(${req.id}, 'grado', this.value)" required>
                                        <option value="">Seleccionar...</option>
                                        ${gradosOptions}
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Región *</label>
                                    <select class="form-select" onchange="actualizarRequisito(${req.id}, 'region', this.value)" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="1" ${req.region == '1' ? 'selected' : ''}>Central</option>
                                        <option value="2" ${req.region == '2' ? 'selected' : ''}>Regional</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-danger w-100" 
                                            onclick="eliminarRequisito(${req.id})" title="Eliminar requisito">
                                        <i class="fas fa-trash me-1"></i>Eliminar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Función para actualizar un requisito específico
        function actualizarRequisito(id, campo, valor) {
            const requisito = requisitosPersonal.find(req => req.id === id);
            if (requisito) {
                requisito[campo] = valor;
            }
        }

        // Función para guardar los requisitos
        function guardarRequisitos() {
            if (requisitosPersonal.length === 0) {
                alert('Debe agregar al menos un requisito de personal');
                return;
            }

            // Validar que todos los requisitos tengan cantidad válida
            const requisitosInvalidos = requisitosPersonal.filter(req => !req.cantidad || req.cantidad < 1);
            if (requisitosInvalidos.length > 0) {
                alert('Todos los requisitos deben tener una cantidad válida (mayor a 0)');
                return;
            }

            // Guardar en localStorage
            const storageKey = `requisitos_servicio_${tipoServicioActual.id}`;
            localStorage.setItem(storageKey, JSON.stringify(requisitosPersonal));

            // Actualizar la visualización en el formulario
            actualizarVisualizacionRequisitos();

            // Cerrar el modal
            cerrarModalRequisitos();

            // Mostrar mensaje de éxito
            mostrarNotificacion('Requisitos guardados correctamente', 'success');
        }

        // Función para actualizar la visualización de requisitos en el formulario
        function actualizarVisualizacionRequisitos() {
            const container = document.getElementById('requisitosPersonal');
            
            if (requisitosPersonal.length === 0) {
                container.innerHTML = `
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        No se han definido requisitos específicos. Haga clic en "Definir Requisitos" para comenzar.
                    </div>
                `;
                return;
            }
            
            const totalPersonal = requisitosPersonal.reduce((total, req) => total + parseInt(req.cantidad), 0);
            
            const html = `
                <div class="requisitos-resumen">
                    <div class="alert alert-success mb-2">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Total de personal requerido: ${totalPersonal}</strong>
                    </div>
                    <div class="requisitos-detalle">
                        ${requisitosPersonal.map(req => {
                            const generoTexto = req.genero ? (req.genero === 'M' ? 'Hombres' : req.genero === 'F' ? 'Mujeres' : 'Cualquier género') : 'Cualquier género';
                            const gradoTexto = obtenerNombreGrado(req.grado);
                            const regionTexto = obtenerNombreRegion(req.region);
                            
                            return `
                                <div class="requisito-resumen">
                                    <span class="badge bg-primary me-2">${req.cantidad}</span>
                                    ${generoTexto} - ${gradoTexto} - ${regionTexto}
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
        }

        // Función auxiliar para obtener nombre del grado
        function obtenerNombreGrado(gradoId) {
            if (!gradoId) return 'Cualquier grado';
            const grado = gradosData.find(g => g.id == gradoId);
            return grado ? grado.nombre : 'Cualquier grado';
        }

        // Función auxiliar para obtener nombre de la región
        function obtenerNombreRegion(regionId) {
            const regiones = {
                '1': 'Central',
                '2': 'Regional',
                '3': 'Grupo Domingo'
            };
            return regiones[regionId] || 'Cualquier región';
        }

        // Cerrar modal al hacer clic fuera de él
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('editRequisitosModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        cerrarModalRequisitos();
                    }
                });
            }
        });

        // Función para validar la selección de personal
        function validarSeleccion(checkbox) {
            const cantidadRequerida = parseInt(document.getElementById('contadorRequerido').textContent) || 0;
            const seleccionados = document.querySelectorAll('input[name="personal_seleccionado[]"]:checked');
            
            // Si se está intentando seleccionar y ya se alcanzó el límite
            if (checkbox.checked && seleccionados.length > cantidadRequerida && cantidadRequerida > 0) {
                checkbox.checked = false;
                
                // Mostrar mensaje de advertencia
                const mensaje = `No se puede seleccionar más personal. Límite máximo: ${cantidadRequerida} ${cantidadRequerida === 1 ? 'persona' : 'personas'}.`;
                
                // Crear y mostrar notificación temporal
                mostrarNotificacion(mensaje, 'warning');
                
                return false;
            }
            
            // Actualizar contador normalmente
            actualizarContador();
            return true;
        }

        // Función para mostrar notificaciones temporales
        function mostrarNotificacion(mensaje, tipo = 'info') {
            // Remover notificación existente si la hay
            const notificacionExistente = document.querySelector('.notification-toast');
            if (notificacionExistente) {
                notificacionExistente.remove();
            }
            
            // Crear nueva notificación
            const notificacion = document.createElement('div');
            notificacion.className = `notification-toast alert alert-${tipo === 'warning' ? 'warning' : 'info'} alert-dismissible`;
            notificacion.innerHTML = `
                <i class="fas fa-${tipo === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                ${mensaje}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            
            // Agregar al DOM
            document.body.appendChild(notificacion);
            
            // Auto-remover después de 4 segundos
            setTimeout(() => {
                if (notificacion.parentElement) {
                    notificacion.remove();
                }
            }, 4000);
        }

        // Función para calcular el personal requerido total basado en requisitos
        function calcularPersonalRequerido() {
            const requisitos = cargarRequisitosDesdeStorage();
            let totalRequerido = 0;
            
            if (requisitos && requisitos.length > 0) {
                totalRequerido = requisitos.reduce((total, req) => total + parseInt(req.cantidad || 0), 0);
            }
            
            // Actualizar el contador de personal requerido
            document.getElementById('contadorRequerido').textContent = totalRequerido;
            
            // Actualizar el progreso si ya hay personal seleccionado
            actualizarContador();
        }

        // Cerrar modal con la tecla Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('editRequisitosModal').style.display === 'flex') {
                cerrarModalRequisitos();
            }
        });
    </script>

    <style>
        /* Estilos específicos para crear servicio */
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 30px;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 5px solid #104c75;
        }

        .page-title {
            color: #104c75;
            font-weight: 700;
            margin: 0;
        }

        .page-subtitle {
            margin: 5px 0 0 0;
            font-size: 1.1em;
        }

        .steps-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            border-radius: 12px;
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            transition: all 0.3s ease;
            flex: 1;
            max-width: 300px;
        }

        .step.active {
            background: #104c75;
            color: white;
            border-color: #104c75;
        }

        .step.completed {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }

        .step-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
        }

        .step.active .step-icon,
        .step.completed .step-icon {
            background: rgba(255,255,255,0.2);
        }

        .step-content h6 {
            margin: 0 0 5px 0;
            font-weight: 600;
        }

        .step-content p {
            margin: 0;
            font-size: 0.9em;
            opacity: 0.8;
        }

        .step-line {
            height: 2px;
            background: #dee2e6;
            flex: 1;
            margin: 0 20px;
        }

        .form-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .form-card .card-header {
            background: #104c75;
            color: white;
            padding: 20px 30px;
            border: none;
        }

        .form-card .card-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #104c75;
            box-shadow: 0 0 0 0.2rem rgba(16, 76, 117, 0.25);
        }

        .form-text {
            color: #6c757d;
            font-size: 0.9em;
            margin-top: 5px;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .tipo-info-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 20px;
            margin-top: 15px;
        }
        
        /* Estilos para la sección de requisitos */
        .requisitos-section {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 12px;
            padding: 20px;
            margin-top: 15px;
        }
        
        .requisitos-display {
            margin-top: 15px;
        }

        .info-header {
            color: #104c75;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .info-section {
            margin-bottom: 15px;
        }

        .info-section h6 {
            color: #495057;
            margin-bottom: 8px;
        }

        .info-section p {
            margin: 0;
            color: #6c757d;
            line-height: 1.6;
            white-space: pre-line;
        }

        .personnel-filters {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #dee2e6;
        }

        .selection-counter {
            text-align: center;
        }

        .counter-card {
            background: #e3f2fd;
            color: #1976d2;
            padding: 15px 25px;
            border-radius: 25px;
            display: inline-flex;
            align-items: center;
            font-size: 1.1em;
        }

        .counter-required {
            background: #fff3e0;
            color: #f57c00;
        }

        .progress-indicator {
            margin-top: 15px;
        }

        .progress {
            height: 8px;
            border-radius: 10px;
            background-color: #e9ecef;
        }

        .progress-bar {
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        /* Estilos para notificaciones */
        .notification-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 500px;
            animation: slideInRight 0.3s ease-out;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .personnel-list {
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 15px;
        }

        .personnel-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 6px;
            transition: all 0.3s ease;
        }

        .personnel-card.available {
            border-left: 4px solid #28a745;
        }

        .personnel-card.unavailable {
            border-left: 4px solid #dc3545;
            opacity: 0.7;
            background-color: #f8f9fa;
        }

        .personnel-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .availability-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .availability-badge.available {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .availability-badge.unavailable {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .stat-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
        }

        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .stat-card i {
            font-size: 2em;
            margin-bottom: 10px;
        }

        .stat-info h6 {
            margin: 0 0 5px 0;
            color: #6c757d;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            font-size: 1.8em;
            font-weight: 700;
            color: #333;
        }

        .stats-container {
            margin-bottom: 16px;
        }

        .personal-disponible-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
        }

        .personnel-name {
            margin: 0 0 5px 0;
            font-weight: 600;
            color: #333;
        }

        .personnel-rank {
            background: #e9ecef;
            color: #495057;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .personnel-details {
            display: flex;
            gap: 15px;
            margin-top: 5px;
        }

        .personnel-ci {
            color: #6c757d;
            font-size: 0.9em;
        }

        .unavailable-reason {
            color: #dc3545;
            font-size: 0.9em;
            font-weight: 500;
        }

        .loading-state, .empty-personnel {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .loading-state i, .empty-personnel i {
            margin-bottom: 15px;
            color: #dee2e6;
        }

        .empty-personnel h5 {
            color: #495057;
            margin-bottom: 10px;
        }

        /* Estilos para el modal de edición de requisitos */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1050;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            width: 96%;
            max-width: 1200px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .modal-content.modal-lg {
            max-width: 1200px;
        }

        .requisitos-lista {
            max-height: 65vh;
            overflow-y: auto;
        }

        .requisito-item {
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }

        .requisito-item:hover {
            border-color: #007bff;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.1);
        }

        .requisitos-resumen {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
        }

        .requisito-resumen {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            padding: 8px;
            background: white;
            border-radius: 4px;
            border-left: 4px solid #007bff;
        }

        .requisito-resumen:last-child {
            margin-bottom: 0;
        }

        .requisito-grupo {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 12px;
            background: #f8f9fa;
        }

        .requisito-header h6 {
            color: #495057;
            font-weight: 600;
        }

        .requisito-status .badge {
            font-size: 0.875rem;
        }

        .personal-grupo {
            background: white;
            border-radius: 6px;
            padding: 6px;
        }

        /* Plegado de grupos de requisitos */
        .requisito-grupo.collapsed .personal-grupo {
            display: none;
        }

        .personnel-gender, .personnel-region {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .modal-header {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 12px;
        }

        .modal-footer {
            padding: 10px 12px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6c757d;
        }

        .close-modal:hover {
            color: #495057;
        }

        .edit-requisitos-btn {
            background: none;
            border: none;
            color: #007bff;
            cursor: pointer;
            font-size: 14px;
            text-decoration: underline;
            padding: 0;
            margin-left: 10px;
        }

        .edit-requisitos-btn:hover {
            color: #0056b3;
        }

        /* Acciones de requisito: botones simétricos y estéticos */
        .req-actions {
            gap: 8px;
        }

        .btn-req {
            min-width: 150px;
            padding: 6px 14px;
            border-radius: 10px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            column-gap: 8px;
        }

        .btn-req i { font-size: 0.95em; }

        .btn-req:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
    </style>

    <!-- Modal para definir requisitos de personal -->
    <div id="editRequisitosModal" class="modal-overlay">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Definir Requisitos de Personal
                </h5>
                <button type="button" class="close-modal" onclick="cerrarModalRequisitos()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-3">
                    <label class="form-label">Tipo de Servicio:</label>
                    <input type="text" id="tipoServicioNombre" class="form-control" readonly>
                    <input type="hidden" id="tipoServicioId">
                </div>
                
                <div class="requisitos-container">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Requisitos de Personal</h6>
                        <button type="button" class="btn btn-sm btn-primary" onclick="agregarRequisito()">
                            <i class="fas fa-plus me-1"></i>Agregar Requisito
                        </button>
                    </div>
                    
                    <div id="requisitosLista" class="requisitos-lista">
                        <!-- Los requisitos se agregarán dinámicamente aquí -->
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Información:</strong> Los requisitos se guardarán temporalmente y se utilizarán para recomendar personal disponible en el siguiente paso.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="cerrarModalRequisitos()">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="guardarRequisitos()">
                    <i class="fas fa-save me-2"></i>Guardar Requisitos
                </button>
            </div>
        </div>
    </div>
</body>
</html>