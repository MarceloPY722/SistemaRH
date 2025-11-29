<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

// Manejar búsqueda AJAX de policías
if (isset($_GET['action']) && $_GET['action'] == 'buscar_policia') {
    $termino = $_GET['termino'] ?? '';
    
    if (strlen($termino) >= 2) {
        $sql = "SELECT p.id, p.nombre, p.apellido, p.cin, p.legajo, g.nombre as grado, lg.nombre as lugar_guardia
                FROM policias p
                LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
                LEFT JOIN grados g ON tg.grado_id = g.id
                LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
                WHERE p.activo = 1 
                AND (p.nombre LIKE ? OR p.apellido LIKE ? OR p.cin LIKE ? OR p.legajo LIKE ?)
                ORDER BY p.apellido, p.nombre
                LIMIT 10";
        
        $termino_busqueda = "%$termino%";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$termino_busqueda, $termino_busqueda, $termino_busqueda, $termino_busqueda]);
        
        $policias = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $policias[] = $row;
        }
        
        header('Content-Type: application/json');
        echo json_encode($policias);
        exit();
    }
    
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

// Procesar formulario
if ($_POST) {
    $policia_id = $_POST['policia_id'];
    $tipo_ausencia_id = $_POST['tipo_ausencia_id'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = isset($_POST['fecha_indefinida']) && $_POST['fecha_indefinida'] == '1' ? null : ($_POST['fecha_fin'] ?: null);
    $descripcion = $_POST['descripcion'] ?? '';
    $justificacion = $_POST['justificacion'] ?? '';
    // Variable usuario_id removida - no existe en la tabla ausencias
    
    // Manejar archivo adjunto
    $documento_path = null;
    if (isset($_FILES['documento_adjunto']) && $_FILES['documento_adjunto']['error'] == 0) {
        $upload_dir = '../../uploads/ausencias/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['documento_adjunto']['name'], PATHINFO_EXTENSION);
        $new_filename = 'ausencia_' . $policia_id . '_' . time() . '.' . $file_extension;
        $documento_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['documento_adjunto']['tmp_name'], $documento_path)) {
            $documento_path = 'uploads/ausencias/' . $new_filename;
        } else {
            $documento_path = null;
        }
    }
    
    // Insertar ausencia
    $sql_insert = "INSERT INTO ausencias (policia_id, tipo_ausencia_id, fecha_inicio, fecha_fin, descripcion, justificacion, documento_adjunto, estado, created_at) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, 'APROBADA', NOW())";
    
    $stmt = $conn->prepare($sql_insert);
    
    if ($stmt->execute([$policia_id, $tipo_ausencia_id, $fecha_inicio, $fecha_fin, $descripcion, $justificacion, $documento_path])) {
        $ausencia_id = $conn->lastInsertId();
        if (function_exists('auditoriaCrear')) {
            auditoriaCrear('ausencias', $ausencia_id, [
                'policia_id' => $policia_id,
                'tipo_ausencia_id' => $tipo_ausencia_id,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
                'estado' => 'APROBADA'
            ]);
        }
        
        // Si es Junta Médica, registrar en orden_junta_medica_telefonista
        $sql_tipo = "SELECT nombre FROM tipos_ausencias WHERE id = ?";
        $stmt_tipo = $conn->prepare($sql_tipo);
        $stmt_tipo->execute([$tipo_ausencia_id]);
        $tipo_result = $stmt_tipo->fetch(PDO::FETCH_ASSOC);
        
        if ($tipo_result && $tipo_result['nombre'] == 'Junta Medica') {
            // Obtener datos completos del policía
            $sql_policia = "SELECT lugar_guardia_id, lugar_guardia_reserva_id FROM policias WHERE id = ?";
            $stmt_policia = $conn->prepare($sql_policia);
            $stmt_policia->execute([$policia_id]);
            $policia_data = $stmt_policia->fetch(PDO::FETCH_ASSOC);
            
            // Obtener el siguiente orden de anotación
            $sql_orden = "SELECT COALESCE(MAX(orden_anotacion), 0) + 1 as siguiente_orden FROM orden_junta_medica_telefonista WHERE activo = 1";
            $stmt_orden = $conn->prepare($sql_orden);
            $stmt_orden->execute();
            $siguiente_orden = $stmt_orden->fetch(PDO::FETCH_ASSOC)['siguiente_orden'];
            
            // Insertar en orden_junta_medica_telefonista
            $sql_junta = "INSERT INTO orden_junta_medica_telefonista (policia_id, ausencia_id, lugar_guardia_original_id, orden_anotacion, fecha_anotacion, activo) 
                          VALUES (?, ?, ?, ?, NOW(), 1)";
            $stmt_junta = $conn->prepare($sql_junta);
            $stmt_junta->execute([$policia_id, $ausencia_id, $policia_data['lugar_guardia_id'], $siguiente_orden]);
            
          
            $nuevo_principal = $policia_data['lugar_guardia_reserva_id']; // Su guardia secundaria
            $nuevo_reserva = $policia_data['lugar_guardia_id']; // Su lugar original de guardia             
            $stmt_intercambio = $conn->prepare("UPDATE policias SET lugar_guardia_id = ?, lugar_guardia_reserva_id = ? WHERE id = ?");
            
            if ($stmt_intercambio->execute([$nuevo_principal, $nuevo_reserva, $policia_id])) {
                if (function_exists('auditoriaActualizar')) {
                    $stmt_prev = $conn->prepare("SELECT * FROM policias WHERE id = ?");
                    $stmt_prev->execute([$policia_id]);
                    $policia_prev = $stmt_prev->fetch(PDO::FETCH_ASSOC);
                    auditoriaActualizar('policias', $policia_id, $policia_prev ?: null, [
                        'lugar_guardia_id' => $nuevo_principal,
                        'lugar_guardia_reserva_id' => $nuevo_reserva
                    ]);
                }
                
                $conn->exec("CREATE TABLE IF NOT EXISTS intercambios_guardias (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    policia_id INT NOT NULL,
                    ausencia_id INT NOT NULL,
                    lugar_original_id INT NOT NULL,
                    lugar_intercambio_id INT NOT NULL,
                    fecha_intercambio DATETIME NOT NULL,
                    fecha_restauracion DATETIME NULL,
                    usuario_id INT NOT NULL,
                    activo TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                
                // Registrar el intercambio
                $stmt_log = $conn->prepare("INSERT INTO intercambios_guardias (policia_id, ausencia_id, lugar_original_id, lugar_intercambio_id, fecha_intercambio, usuario_id) VALUES (?, ?, ?, ?, NOW(), ?)");
                $stmt_log->execute([$policia_id, $ausencia_id, $nuevo_reserva, $nuevo_principal, $_SESSION['usuario_id']]);
                if (function_exists('registrarAuditoria')) {
                    registrarAuditoria('Intercambio de guardia por Junta Médica', 'intercambios_guardias', $conn->lastInsertId(), null, [
                        'policia_id' => $policia_id,
                        'ausencia_id' => $ausencia_id,
                        'lugar_original_id' => $nuevo_reserva,
                        'lugar_intercambio_id' => $nuevo_principal
                    ]);
                }
                
                // Asegurar que el policía esté en lista_guardias para su nuevo lugar de guardia
                try {
                    $stmt_disponible = $conn->prepare("CALL MarcarDisponibleEnNuevoLugar(?)");
                    $stmt_disponible->execute([$policia_id]);
                } catch (Exception $e) {
                    // Si el procedimiento no existe, manejar lista_guardias manualmente
                    
                    // Primero, eliminar al policía de su posición actual en lista_guardias
                    $stmt_delete_lista = $conn->prepare("DELETE FROM lista_guardias WHERE policia_id = ?");
                    $stmt_delete_lista->execute([$policia_id]);
                    
                    // Luego, agregarlo a lista_guardias con una nueva posición
                    $stmt_add_lista = $conn->prepare("INSERT INTO lista_guardias (policia_id, posicion) SELECT ?, COALESCE(MAX(posicion), 0) + 1 FROM lista_guardias");
                    $stmt_add_lista->execute([$policia_id]);
                }
            }
        }
        
        if ($tipo_result && $tipo_result['nombre'] == 'Junta Medica') {
            $mensaje = "<div class='alert alert-success'><i class='fas fa-check-circle me-2'></i><strong>Ya se agregó la ausencia permanente por Junta Médica.</strong><br>El lugar de guardia del policía ha sido cambiado automáticamente a su <strong>guardia secundaria</strong>.</div>";
        } else {
            // Para ausencias que no son Junta Médica, cambiar estado a NO DISPONIBLE
            $stmt_prev = $conn->prepare("SELECT * FROM policias WHERE id = ?");
            $stmt_prev->execute([$policia_id]);
            $policia_prev = $stmt_prev->fetch(PDO::FETCH_ASSOC);
            $sql_update_estado = "UPDATE policias SET estado = 'NO DISPONIBLE' WHERE id = ?";
            $stmt_update_estado = $conn->prepare($sql_update_estado);
            $stmt_update_estado->execute([$policia_id]);
            if (function_exists('auditoriaActualizar')) {
                auditoriaActualizar('policias', $policia_id, $policia_prev ?: null, [
                    'estado' => 'NO DISPONIBLE'
                ]);
            }
            
            $mensaje = "<div class='alert alert-success'><i class='fas fa-check-circle me-2'></i>Ausencia registrada exitosamente. El estado del policía ha sido cambiado a <strong>NO DISPONIBLE</strong>.</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle me-2'></i>Error al registrar la ausencia</div>";
    }
}

$sql_tipos = "SELECT * FROM tipos_ausencias ORDER BY nombre";
$stmt_tipos = $conn->prepare($sql_tipos);
$stmt_tipos->execute();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Nueva Ausencia - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary-color: #64748b;
            --success-color: #059669;
            --danger-color: #dc2626;
            --warning-color: #d97706;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --border-radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            min-height: 100vh;
        }

        .main-content {
            padding: 20px;
        }

        .main-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .form-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 30px;
            text-align: center;
        }

        .form-header h1 {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .form-body {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-control, .form-select {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: white;
        }

        .search-container {
            position: relative;
        }

        .search-input {
            padding-right: 45px;
        }

        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary-color);
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .search-result-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            transition: background-color 0.2s;
        }

        .search-result-item:hover {
            background-color: #f8fafc;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .policia-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .policia-name {
            font-weight: 600;
            color: #111827;
        }

        .policia-details {
            font-size: 0.875rem;
            color: var(--secondary-color);
        }

        .policia-badge {
            background: var(--primary-color);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .selected-policia {
            background: #f0f9ff;
            border: 2px solid var(--primary-color);
            border-radius: 8px;
            padding: 16px;
            margin-top: 10px;
            display: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
        }

        .btn-secondary {
            background: var(--secondary-color);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            color: white;
        }

        .file-upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: var(--primary-color);
            background: #f8fafc;
        }

        .file-upload-area.dragover {
            border-color: var(--primary-color);
            background: #eff6ff;
        }

        .required {
            color: var(--danger-color);
        }

        .form-text {
            color: var(--secondary-color);
            font-size: 0.875rem;
            margin-top: 5px;
        }

        .alert {
            border-radius: 8px;
            border: none;
            padding: 16px;
            margin-bottom: 25px;
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 0 15px;
            }
            
            .form-body {
                padding: 25px;
            }
            
            .form-header {
                padding: 25px;
            }
            
            .form-header h1 {
                font-size: 1.5rem;
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
                    <div class="main-container">
                        <div class="form-card">
            <div class="form-header">
                <h1><i class="fas fa-user-clock me-3"></i>Registrar Nueva Ausencia</h1>
                <p>Complete el formulario para registrar una ausencia del personal</p>
            </div>
            
            <div class="form-body">
                <?php if (isset($mensaje)) echo $mensaje; ?>
                
                <form id="formAusencia" method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <!-- Búsqueda de Policía -->
                        <div class="col-12 form-group">
                            <label class="form-label">
                                <i class="fas fa-search"></i>
                                Buscar Policía <span class="required">*</span>
                            </label>
                            <div class="search-container">
                                <input type="text" id="buscar_policia" class="form-control search-input" 
                                       placeholder="Escriba nombre, apellido, CI o legajo..." autocomplete="off">
                                <i class="fas fa-search search-icon"></i>
                                <div class="loading-spinner" id="loading-spinner"></div>
                                <div class="search-results" id="search-results"></div>
                            </div>
                            <input type="hidden" name="policia_id" id="policia_id" required>
                            <div class="selected-policia" id="selected-policia"></div>
                        </div>
                        
                        <!-- Tipo de Ausencia -->
                        <div class="col-md-6 form-group">
                            <label for="tipo_ausencia_id" class="form-label">
                                <i class="fas fa-tags"></i>
                                Tipo de Ausencia <span class="required">*</span>
                            </label>
                            <select name="tipo_ausencia_id" id="tipo_ausencia_id" class="form-select" required>
                                <option value="">Seleccione un tipo...</option>
                                <?php while ($tipo = $stmt_tipos->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $tipo['id']; ?>" 
                                            data-requiere-justificacion="<?php echo $tipo['requiere_justificacion']; ?>">
                                        <?php echo htmlspecialchars($tipo['nombre']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <!-- Fecha de Inicio -->
                        <div class="col-md-6 form-group">
                            <label for="fecha_inicio" class="form-label">
                                <i class="fas fa-calendar-alt"></i>
                                Fecha de Inicio <span class="required">*</span>
                            </label>
                            <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <!-- Fecha de Fin -->
                        <div class="col-md-6 form-group" id="fecha_fin_container">
                            <label for="fecha_fin" class="form-label">
                                <i class="fas fa-calendar-check"></i>
                                Fecha de Fin
                            </label>
                            <input type="date" name="fecha_fin" id="fecha_fin" class="form-control">
                            <div class="form-text">Opcional - Dejar vacío si es indefinida</div>
                        </div>
                        
                        <!-- Fecha Indefinida (solo para Junta Médica) -->
                        <div class="col-md-6 form-group" id="fecha_indefinida_container" style="display: none;">
                            <label class="form-label">
                                <i class="fas fa-infinity"></i>
                                Duración de la Ausencia
                            </label>
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="fecha_indefinida" name="fecha_indefinida" value="1">
                                <label class="form-check-label" for="fecha_indefinida">
                                    <strong>Fecha Indefinida</strong>
                                    <div class="form-text">El policía será asignado a Atención Telefónica Exclusiva permanentemente</div>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Descripción -->
                        <div class="col-md-6 form-group">
                            <label for="descripcion" class="form-label">
                                <i class="fas fa-comment-alt"></i>
                                Descripción
                            </label>
                            <textarea name="descripcion" id="descripcion" class="form-control" rows="3" 
                                      placeholder="Descripción breve de la ausencia..."></textarea>
                        </div>
                        
                        <!-- Justificación (condicional) -->
                        <div class="col-12 form-group justificacion-field" style="display: none;">
                            <label for="justificacion" class="form-label">
                                <i class="fas fa-file-alt"></i>
                                Justificación <span class="required">*</span>
                            </label>
                            <textarea name="justificacion" id="justificacion" class="form-control" rows="4" 
                                      placeholder="Proporcione la justificación detallada para esta ausencia..."></textarea>
                        </div>
                        
                        <!-- Documento Adjunto -->
                        <div class="col-12 form-group">
                            <label class="form-label">
                                <i class="fas fa-paperclip"></i>
                                Documento Adjunto
                            </label>
                            <div class="file-upload-area" onclick="document.getElementById('documento_adjunto').click()">
                                <i class="fas fa-cloud-upload-alt fa-2x text-primary mb-3"></i>
                                <p class="mb-2"><strong>Haga clic para seleccionar un archivo</strong></p>
                                <p class="text-muted mb-0">PDF, Imágenes o Documentos (Máx. 5MB)</p>
                                <input type="file" name="documento_adjunto" id="documento_adjunto" 
                                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="display: none;">
                            </div>
                            <div id="file-selected" class="mt-3" style="display: none;"></div>
                        </div>
                    </div>
                    
                    <!-- Botones -->
                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Volver
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Registrar Ausencia
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let searchTimeout;
        let selectedPoliciaId = null;

        // Búsqueda en tiempo real de policías
        document.getElementById('buscar_policia').addEventListener('input', function() {
            const termino = this.value.trim();
            const resultsContainer = document.getElementById('search-results');
            const loadingSpinner = document.getElementById('loading-spinner');
            
            clearTimeout(searchTimeout);
            
            if (termino.length < 2) {
                resultsContainer.style.display = 'none';
                return;
            }
            
            loadingSpinner.style.display = 'inline-block';
            
            searchTimeout = setTimeout(() => {
                fetch(`?action=buscar_policia&termino=${encodeURIComponent(termino)}`)
                    .then(response => response.json())
                    .then(data => {
                        loadingSpinner.style.display = 'none';
                        mostrarResultados(data);
                    })
                    .catch(error => {
                        loadingSpinner.style.display = 'none';
                        console.error('Error:', error);
                    });
            }, 300);
        });

        function mostrarResultados(policias) {
            const resultsContainer = document.getElementById('search-results');
            
            if (policias.length === 0) {
                resultsContainer.innerHTML = '<div class="search-result-item text-muted">No se encontraron resultados</div>';
                resultsContainer.style.display = 'block';
                return;
            }
            
            let html = '';
            policias.forEach(policia => {
                html += `
                    <div class="search-result-item" onclick="seleccionarPolicia(${policia.id}, '${policia.nombre}', '${policia.apellido}', '${policia.cin}', '${policia.legajo}', '${policia.grado || ''}', '${policia.lugar_guardia || ''}')">
                        <div class="policia-info">
                            <div>
                                <div class="policia-name">${policia.apellido}, ${policia.nombre}</div>
                                <div class="policia-details">CI: ${policia.cin} | Legajo: ${policia.legajo}</div>
                            </div>
                            <div class="text-end">
                                <div class="policia-badge">${policia.grado || 'Sin grado'}</div>
                                <div class="policia-details mt-1">${policia.lugar_guardia || 'Sin asignar'}</div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            resultsContainer.innerHTML = html;
            resultsContainer.style.display = 'block';
        }

        function seleccionarPolicia(id, nombre, apellido, cin, legajo, grado, lugarGuardia) {
            selectedPoliciaId = id;
            document.getElementById('policia_id').value = id;
            document.getElementById('buscar_policia').value = `${apellido}, ${nombre}`;
            document.getElementById('search-results').style.display = 'none';
            
            const selectedContainer = document.getElementById('selected-policia');
            selectedContainer.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="fas fa-user me-2"></i>${apellido}, ${nombre}</h6>
                        <small class="text-muted">CI: ${cin} | Legajo: ${legajo} | ${grado} | ${lugarGuardia}</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="limpiarSeleccion()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            selectedContainer.style.display = 'block';
            selectedContainer.classList.add('fade-in');
        }

        function limpiarSeleccion() {
            selectedPoliciaId = null;
            document.getElementById('policia_id').value = '';
            document.getElementById('buscar_policia').value = '';
            document.getElementById('selected-policia').style.display = 'none';
        }

        // Ocultar resultados al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-container')) {
                document.getElementById('search-results').style.display = 'none';
            }
        });

        // Manejar cambios en tipo de ausencia
        document.getElementById('tipo_ausencia_id').addEventListener('change', function() {
            const requiereJustificacion = this.options[this.selectedIndex].dataset.requiereJustificacion;
            const tipoAusencia = this.options[this.selectedIndex].text;
            
            // Manejar campo de justificación
            const justificacionField = document.querySelector('.justificacion-field');
            const justificacionInput = document.getElementById('justificacion');
            
            if (requiereJustificacion == '1') {
                justificacionField.style.display = 'block';
                justificacionInput.required = true;
            } else {
                justificacionField.style.display = 'none';
                justificacionInput.required = false;
            }
            
            // Manejar campos de fecha según si es Junta Médica
            const fechaFinContainer = document.getElementById('fecha_fin_container');
            const fechaIndefinidaContainer = document.getElementById('fecha_indefinida_container');
            const fechaFinInput = document.getElementById('fecha_fin');
            const fechaIndefinidaInput = document.getElementById('fecha_indefinida');
            
            if (tipoAusencia === 'Junta Medica') {
                fechaFinContainer.style.display = 'none';
                fechaIndefinidaContainer.style.display = 'block';
                fechaFinInput.required = false;
                fechaFinInput.value = '';
                fechaIndefinidaInput.checked = true;
            } else {
                fechaFinContainer.style.display = 'block';
                fechaIndefinidaContainer.style.display = 'none';
                fechaIndefinidaInput.checked = false;
            }
        });

        // Validar fechas
        document.getElementById('fecha_inicio').addEventListener('change', validateDates);
        document.getElementById('fecha_fin').addEventListener('change', validateDates);

        function validateDates() {
            const fechaInicio = new Date(document.getElementById('fecha_inicio').value);
            const fechaFin = new Date(document.getElementById('fecha_fin').value);
            
            if (document.getElementById('fecha_fin').value && fechaFin < fechaInicio) {
                alert('La fecha de fin no puede ser anterior a la fecha de inicio.');
                document.getElementById('fecha_fin').value = '';
            }
        }

        // Manejar archivo adjunto
        document.getElementById('documento_adjunto').addEventListener('change', function() {
            const file = this.files[0];
            const fileSelectedDiv = document.getElementById('file-selected');
            
            if (file) {
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                fileSelectedDiv.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-file me-2"></i>
                        <strong>${file.name}</strong> (${fileSize} MB)
                        <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="clearFile()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                fileSelectedDiv.style.display = 'block';
            } else {
                fileSelectedDiv.style.display = 'none';
            }
        });

        function clearFile() {
            document.getElementById('documento_adjunto').value = '';
            document.getElementById('file-selected').style.display = 'none';
        }

        // Validación del formulario
        document.getElementById('formAusencia').addEventListener('submit', function(e) {
            if (!selectedPoliciaId) {
                e.preventDefault();
                alert('Debe seleccionar un policía.');
                return false;
            }
            
            const tipoAusencia = document.getElementById('tipo_ausencia_id').value;
            if (!tipoAusencia) {
                e.preventDefault();
                alert('Debe seleccionar un tipo de ausencia.');
                return false;
            }
            
            const fechaInicio = document.getElementById('fecha_inicio').value;
            if (!fechaInicio) {
                e.preventDefault();
                alert('Debe especificar la fecha de inicio.');
                return false;
            }
        });

        // Drag and drop para archivos
        const fileUploadArea = document.querySelector('.file-upload-area');
        
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        
        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('documento_adjunto').files = files;
                document.getElementById('documento_adjunto').dispatchEvent(new Event('change'));
            }
        });
    </script>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>