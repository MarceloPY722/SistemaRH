<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

$mensaje = "";
$policia_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($policia_id <= 0) {
    header("Location: index.php");
    exit();
}

// Obtener datos del policía con información de región
$stmt = $conn->prepare("SELECT p.*, r.nombre as region_nombre FROM policias p LEFT JOIN regiones r ON p.region_id = r.id WHERE p.id = ? AND p.activo = 1");
$stmt->bind_param("i", $policia_id);
$stmt->execute();
$policia = $stmt->get_result()->fetch_assoc();

if (!$policia) {
    header("Location: index.php");
    exit();
}

// Obtener grados, especialidades, regiones y lugares de guardia para los selectores del formulario
$grados = $conn->query("SELECT * FROM grados ORDER BY nivel_jerarquia ASC");
$especialidades = $conn->query("SELECT * FROM especialidades ORDER BY nombre ASC");
$regiones = $conn->query("SELECT * FROM regiones ORDER BY nombre ASC");
$lugares_guardias = $conn->query("SELECT * FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");

// Procesar formulario de actualización
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'actualizar') {
    $legajo = (int)trim($_POST['legajo']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $cin = trim($_POST['cin']);
    $genero = trim($_POST['genero']);
    $grado_id = $_POST['grado_id'];
    $especialidad_id = $_POST['especialidad_id'] ?: null;
    $cargo = trim($_POST['cargo']);
    $comisionamiento = trim($_POST['comisionamiento']);
    $telefono = trim($_POST['telefono']);
    $region_id = !empty($_POST['region_id']) ? $_POST['region_id'] : null;
    $lugar_guardia_id = $_POST['lugar_guardia_id'] ?: null;
    $observaciones = trim($_POST['observaciones']);



    // Validar campos requeridos
    if (empty($genero)) {
        $mensaje = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> El género es requerido</div>";
    } elseif ($legajo <= 0) {
        $mensaje = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> El legajo debe ser un número válido</div>";
    } else {
        // Validar legajo único (excluyendo el registro actual)
        $check_legajo = $conn->prepare("SELECT id FROM policias WHERE legajo = ? AND id != ? AND activo = 1");
        $check_legajo->bind_param("ii", $legajo, $policia_id);
        $check_legajo->execute();
        $result_legajo = $check_legajo->get_result();
        
        // Validar CIN único (excluyendo el registro actual)
        $check_cin = $conn->prepare("SELECT id FROM policias WHERE cin = ? AND id != ? AND activo = 1");
        $check_cin->bind_param("si", $cin, $policia_id);
        $check_cin->execute();
        $result_cin = $check_cin->get_result();
        
        if ($result_legajo->num_rows > 0) {
            $mensaje = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> El legajo ya está registrado por otro policía</div>";
        } elseif ($result_cin->num_rows > 0) {
            $mensaje = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> El CIN ya está registrado por otro policía</div>";
        } else {
            $sql = "UPDATE policias SET legajo = ?, nombre = ?, apellido = ?, cin = ?, genero = ?, grado_id = ?, especialidad_id = ?, cargo = ?, comisionamiento = ?, telefono = ?, region_id = ?, lugar_guardia_id = ?, observaciones = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssiisssisii", $legajo, $nombre, $apellido, $cin, $genero, $grado_id, $especialidad_id, $cargo, $comisionamiento, $telefono, $region_id, $lugar_guardia_id, $observaciones, $policia_id);
            
            if ($stmt->execute()) {
                $mensaje = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Policía actualizado exitosamente</div>";
                // Recargar datos del policía
                $stmt = $conn->prepare("SELECT * FROM policias WHERE id = ?");
                $stmt->bind_param("i", $policia_id);
                $stmt->execute();
                $policia = $stmt->get_result()->fetch_assoc();
            } else {
                $mensaje = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error al actualizar policía: " . $conn->error . "</div>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Policía - Sistema RH</title>
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
        .form-control:focus {
            border-color: #104c75;
            box-shadow: 0 0 0 0.2rem rgba(16, 76, 117, 0.25);
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
        .info-badge {
            background: linear-gradient(45deg, #17a2b8, #138496);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
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
                        <h1 class="page-title"><i class="fas fa-user-edit"></i> Editar Policía</h1>
                        <div class="d-flex gap-2">
                            <span class="info-badge">ID: <?php echo $policia['id']; ?></span>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Volver a la Lista
                            </a>
                        </div>
                    </div>

                    <?php echo $mensaje; ?>

                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-user-edit"></i> Modificar Información de <?php echo htmlspecialchars($policia['nombre'] . ' ' . $policia['apellido']); ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="formEditar">
                                <input type="hidden" name="action" value="actualizar">
                                
                                <div class="row">
                                    <!-- Información Personal -->
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3"><i class="fas fa-user"></i> Información Personal</h6>
                                        
                                        <div class="mb-3">
                                            <label for="legajo" class="form-label">Legajo <span class="required">*</span></label>
                                            <input type="number" class="form-control" id="legajo" name="legajo" 
                                                   value="<?php echo htmlspecialchars($policia['legajo']); ?>" required min="1">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="nombre" class="form-label">Nombre <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="nombre" name="nombre" 
                                                   value="<?php echo htmlspecialchars($policia['nombre']); ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="apellido" class="form-label">Apellido <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="apellido" name="apellido" 
                                                   value="<?php echo htmlspecialchars($policia['apellido']); ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="cin" class="form-label">CIN (Cédula) <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="cin" name="cin" 
                                                   value="<?php echo htmlspecialchars($policia['cin']); ?>" 
                                                   pattern="[0-9]{1,8}" title="Solo números, máximo 8 dígitos" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="genero" class="form-label">Género <span class="required">*</span></label>
                                            <select class="form-select" id="genero" name="genero" required>
                                                <option value="">Seleccionar género...</option>
                                                <option value="MASCULINO" <?php echo ($policia['genero'] == 'MASCULINO') ? 'selected' : ''; ?>>Masculino</option>
                                                <option value="FEMENINO" <?php echo ($policia['genero'] == 'FEMENINO') ? 'selected' : ''; ?>>Femenino</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="telefono" class="form-label">Teléfono</label>
                                            <input type="tel" class="form-control" id="telefono" name="telefono" 
                                                   value="<?php echo htmlspecialchars($policia['telefono']); ?>">
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
                                                            <?php echo ($policia['grado_id'] == $grado['id']) ? 'selected' : ''; ?>>
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
                                                            <?php echo ($policia['especialidad_id'] == $especialidad['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($especialidad['nombre']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="cargo" class="form-label">Cargo</label>
                                            <input type="text" class="form-control" id="cargo" name="cargo" 
                                                   value="<?php echo htmlspecialchars($policia['cargo']); ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="comisionamiento" class="form-label">Comisionamiento</label>
                                            <input type="text" class="form-control" id="comisionamiento" name="comisionamiento" 
                                                   value="<?php echo htmlspecialchars($policia['comisionamiento']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <!-- Asignación y Ubicación -->
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3"><i class="fas fa-map-marker-alt"></i> Asignación</h6>
                                        
                                        <div class="mb-3">
                                            <label for="region_id" class="form-label">Región</label>
                                            <select class="form-select" id="region_id" name="region_id">
                                                <option value="">Seleccionar región...</option>
                                                <?php 
                                                if ($regiones->num_rows > 0) {
                                                    while ($region = $regiones->fetch_assoc()): 
                                                ?>
                                                <option value="<?php echo $region['id']; ?>" <?php echo ($policia['region_id'] == $region['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($region['nombre']); ?></option>
                                                <?php 
                                                    endwhile; 
                                                    $regiones->data_seek(0);
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="lugar_guardia_id" class="form-label">Lugar de Guardia</label>
                                            <select class="form-select" id="lugar_guardia_id" name="lugar_guardia_id">
                                                <option value="">Sin asignar...</option>
                                                <?php while ($lugar = $lugares_guardias->fetch_assoc()): ?>
                                                    <option value="<?php echo $lugar['id']; ?>" 
                                                            <?php echo ($policia['lugar_guardia_id'] == $lugar['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($lugar['nombre']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <!-- Observaciones -->
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3"><i class="fas fa-sticky-note"></i> Observaciones</h6>
                                        
                                        <div class="mb-3">
                                            <label for="observaciones" class="form-label">Observaciones</label>
                                            <textarea class="form-control" id="observaciones" name="observaciones" rows="5"><?php echo htmlspecialchars($policia['observaciones']); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="d-flex gap-3">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Actualizar Policía
                                            </button>
                                            <button type="reset" class="btn btn-outline-secondary">
                                                <i class="fas fa-undo"></i> Restaurar Valores
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
        document.getElementById('formEditar').addEventListener('submit', function(e) {
            const cin = document.getElementById('cin').value;
            const nombre = document.getElementById('nombre').value;
            const apellido = document.getElementById('apellido').value;
            const legajo = document.getElementById('legajo').value;
            const genero = document.getElementById('genero').value;
            
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
            
            if (!legajo || legajo <= 0) {
                e.preventDefault();
                alert('El legajo es requerido y debe ser un número válido');
                return false;
            }
            
            if (!genero) {
                e.preventDefault();
                alert('El género es requerido');
                return false;
            }
        });
        
        // Formatear CIN solo números
        document.getElementById('cin').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // Formatear legajo solo números
        document.getElementById('legajo').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // Confirmación antes de restaurar valores
        document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
            if (!confirm('¿Está seguro de que desea restaurar todos los valores originales?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>