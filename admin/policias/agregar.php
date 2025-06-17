<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

// Obtener datos para los formularios
$grados = $conn->query("SELECT * FROM grados ORDER BY nivel_jerarquia ASC");
$especialidades = $conn->query("SELECT * FROM especialidades ORDER BY nombre ASC");
$lugares_guardias = $conn->query("SELECT * FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");

$mensaje = "";

// Procesar formulario
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
    $observaciones = trim($_POST['observaciones']);
    
    // Validar CIN único
    $check_cin = $conn->prepare("SELECT id FROM policias WHERE cin = ? AND activo = 1");
    $check_cin->bind_param("s", $cin);
    $check_cin->execute();
    $result = $check_cin->get_result();
    
    if ($result->num_rows > 0) {
        $mensaje = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> El CIN ya está registrado en el sistema</div>";
    } else {
        $sql = "INSERT INTO policias (nombre, apellido, cin, grado_id, especialidad_id, cargo, comisionamiento, telefono, region, lugar_guardia_id, fecha_ingreso, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiisisssis", $nombre, $apellido, $cin, $grado_id, $especialidad_id, $cargo, $comisionamiento, $telefono, $region, $lugar_guardia_id, $fecha_ingreso, $observaciones);
        
        if ($stmt->execute()) {
            $mensaje = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Policía registrado exitosamente</div>";
            // Limpiar formulario
            $_POST = array();
        } else {
            $mensaje = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error al registrar policía: " . $conn->error . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Policía - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(45deg, #2c3e50, #34495e) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
        .btn-secondary {
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .required {
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
                        <h1 class="page-title"><i class="fas fa-user-plus"></i> Agregar Nuevo Policía</h1>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver a la Lista
                        </a>
                    </div>

                    <?php echo $mensaje; ?>

                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-user-plus"></i> Información del Policía</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="formAgregar">
                                <input type="hidden" name="action" value="crear">
                                
                                <div class="row">
                                    <!-- Información Personal -->
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3"><i class="fas fa-user"></i> Datos Personales</h6>
                                        
                                        <div class="mb-3">
                                            <label for="nombre" class="form-label">Nombre <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="nombre" name="nombre" 
                                                   value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="apellido" class="form-label">Apellido <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="apellido" name="apellido" 
                                                   value="<?php echo isset($_POST['apellido']) ? htmlspecialchars($_POST['apellido']) : ''; ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="cin" class="form-label">CIN (Cédula) <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="cin" name="cin" 
                                                   value="<?php echo isset($_POST['cin']) ? htmlspecialchars($_POST['cin']) : ''; ?>" 
                                                   pattern="[0-9]{1,8}" title="Solo números, máximo 8 dígitos" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="telefono" class="form-label">Teléfono</label>
                                            <input type="tel" class="form-control" id="telefono" name="telefono" 
                                                   value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <!-- Información Profesional -->
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3"><i class="fas fa-badge"></i> Información Profesional</h6>
                                        
                                        <div class="mb-3">
                                            <label for="grado_id" class="form-label">Grado <span class="required">*</span></label>
                                            <select class="form-select" id="grado_id" name="grado_id" required>
                                                <option value="">Seleccionar grado...</option>
                                                <?php while ($grado = $grados->fetch_assoc()): ?>
                                                    <option value="<?php echo $grado['id']; ?>" 
                                                            <?php echo (isset($_POST['grado_id']) && $_POST['grado_id'] == $grado['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($grado['nombre']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="especialidad_id" class="form-label">Especialidad</label>
                                            <select class="form-select" id="especialidad_id" name="especialidad_id">
                                                <option value="">Sin especialidad...</option>
                                                <?php while ($especialidad = $especialidades->fetch_assoc()): ?>
                                                    <option value="<?php echo $especialidad['id']; ?>" 
                                                            <?php echo (isset($_POST['especialidad_id']) && $_POST['especialidad_id'] == $especialidad['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($especialidad['nombre']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="cargo" class="form-label">Cargo</label>
                                            <input type="text" class="form-control" id="cargo" name="cargo" 
                                                   value="<?php echo isset($_POST['cargo']) ? htmlspecialchars($_POST['cargo']) : ''; ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="fecha_ingreso" class="form-label">Fecha de Ingreso <span class="required">*</span></label>
                                            <input type="date" class="form-control" id="fecha_ingreso" name="fecha_ingreso" 
                                                   value="<?php echo isset($_POST['fecha_ingreso']) ? $_POST['fecha_ingreso'] : ''; ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <!-- Asignación y Ubicación -->
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3"><i class="fas fa-map-marker-alt"></i> Asignación</h6>
                                        
                                        <div class="mb-3">
                                            <label for="region" class="form-label">Región <span class="required">*</span></label>
                                            <select class="form-select" id="region" name="region" required>
                                                <option value="">Seleccionar región...</option>
                                                <option value="CENTRAL" <?php echo (isset($_POST['region']) && $_POST['region'] == 'CENTRAL') ? 'selected' : ''; ?>>Central</option>
                                                <option value="REGIONAL" <?php echo (isset($_POST['region']) && $_POST['region'] == 'REGIONAL') ? 'selected' : ''; ?>>Regional</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="lugar_guardia_id" class="form-label">Lugar de Guardia</label>
                                            <select class="form-select" id="lugar_guardia_id" name="lugar_guardia_id">
                                                <option value="">Sin asignar...</option>
                                                <?php while ($lugar = $lugares_guardias->fetch_assoc()): ?>
                                                    <option value="<?php echo $lugar['id']; ?>" 
                                                            <?php echo (isset($_POST['lugar_guardia_id']) && $_POST['lugar_guardia_id'] == $lugar['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($lugar['nombre']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="comisionamiento" class="form-label">Comisionamiento</label>
                                            <input type="text" class="form-control" id="comisionamiento" name="comisionamiento" 
                                                   value="<?php echo isset($_POST['comisionamiento']) ? htmlspecialchars($_POST['comisionamiento']) : ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <!-- Observaciones -->
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3"><i class="fas fa-sticky-note"></i> Observaciones</h6>
                                        
                                        <div class="mb-3">
                                            <label for="observaciones" class="form-label">Observaciones</label>
                                            <textarea class="form-control" id="observaciones" name="observaciones" rows="6" 
                                                      placeholder="Información adicional sobre el policía..."><?php echo isset($_POST['observaciones']) ? htmlspecialchars($_POST['observaciones']) : ''; ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="d-flex gap-3">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Guardar Policía
                                            </button>
                                            <button type="reset" class="btn btn-outline-secondary">
                                                <i class="fas fa-undo"></i> Limpiar Formulario
                                            </button>
                                            <a href="index.php" class="btn btn-outline-danger">
                                                <i class="fas fa-times"></i> Cancelar
                                            </a>
                                        </div>
                                    </div>
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
        // Validación del formulario
        document.getElementById('formAgregar').addEventListener('submit', function(e) {
            const cin = document.getElementById('cin').value;
            const nombre = document.getElementById('nombre').value;
            const apellido = document.getElementById('apellido').value;
            
            if (cin.length < 6 || cin.length > 8) {
                e.preventDefault();
                alert('El CIN debe tener entre 6 y 8 dígitos');
                return false;
            }
            
            if (nombre.length < 2 || apellido.length < 2) {
                e.preventDefault();
                alert('El nombre y apellido deben tener al menos 2 caracteres');
                return false;
            }
        });
        
        // Formatear CIN solo números
        document.getElementById('cin').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>