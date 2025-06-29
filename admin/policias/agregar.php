<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

function obtenerSiguienteLegajo($conn) {
    $query = "SELECT MAX(legajo) as max_legajo FROM policias"; // Removido WHERE activo = 1
    $result = $conn->query($query);
    
    if ($result && $row = $result->fetch_assoc()) {
        $max_legajo = $row['max_legajo'];
        if ($max_legajo === null) {
            return 1;
        }
        return $max_legajo + 1;
    }
    
    return 1;
}

$siguiente_legajo = obtenerSiguienteLegajo($conn);
$grados = $conn->query("SELECT * FROM grados ORDER BY nivel_jerarquia ASC");
$especialidades = $conn->query("SELECT * FROM especialidades ORDER BY nombre ASC");
$regiones = $conn->query("SELECT * FROM regiones ORDER BY nombre ASC");
$lugares_guardias = $conn->query("SELECT * FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'crear') {
    $legajo = obtenerSiguienteLegajo($conn);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $cin = trim($_POST['cin']);
    $genero = !empty($_POST['genero']) ? $_POST['genero'] : null;
    $grado_id = $_POST['grado_id'];
    $especialidad_id = !empty($_POST['especialidad_id']) ? $_POST['especialidad_id'] : null;
    $cargo = trim($_POST['cargo']);
    $comisionamiento = trim($_POST['comisionamiento']);
    $telefono = trim($_POST['telefono']);
    $region_id = !empty($_POST['region_id']) ? $_POST['region_id'] : 1;
    $lugar_guardia_id = !empty($_POST['lugar_guardia_id']) ? $_POST['lugar_guardia_id'] : null;
    $observaciones = trim($_POST['observaciones']);

    // Validar CIN único
    $check_cin = $conn->prepare("SELECT id FROM policias WHERE cin = ? AND activo = 1");
    $check_cin->bind_param("s", $cin);
    $check_cin->execute();
    $result_cin = $check_cin->get_result();

    // Validar campos obligatorios
    if (empty($genero)) {
        $mensaje = "<div class='alert alert-danger'>El campo género es obligatorio</div>";
    } elseif ($result_cin->num_rows > 0) {
        $mensaje = "<div class='alert alert-danger'>El CIN ya está registrado por otro policía</div>";
    } else {
        $sql = "INSERT INTO policias (legajo, nombre, apellido, cin, genero, grado_id, especialidad_id, cargo, comisionamiento, telefono, region_id, lugar_guardia_id, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssiisssiss", $legajo, $nombre, $apellido, $cin, $genero, $grado_id, $especialidad_id, $cargo, $comisionamiento, $telefono, $region_id, $lugar_guardia_id, $observaciones);

        if ($stmt->execute()) {
            $mensaje = "<div class='alert alert-success'>Policía registrado exitosamente con legajo: $legajo</div>";
            $_POST = array(); 
            // Actualizar el siguiente legajo para el próximo registro
            $siguiente_legajo = obtenerSiguienteLegajo($conn);
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al registrar policía: " . $conn->error . "</div>";
        }
        $stmt->close();
    }
    
    $check_cin->close();
}
// $conn->close(); // <-- REMOVE THIS LINE
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Nuevo Policía - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            font-weight: 500;
        }
        .required-field::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php 
            // Establecer la página activa para el sidebar
            $_GET['page'] = 'policias_agregar'; // Puedes usar esto para resaltar el item del menú
            include '../inc/sidebar.php'; 
            ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <h1 class="page-title">
                        <i class="fas fa-user-plus"></i> Agregar Nuevo Policía
                    </h1>

                    <?php if (!empty($mensaje)) echo $mensaje; ?>

                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5><i class="fas fa-id-card"></i> Datos del Policía</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                <input type="hidden" name="action" value="crear">
                                
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label for="legajo" class="form-label">Legajo</label>
                                        <input type="number" class="form-control" id="legajo" name="legajo" value="<?php echo $siguiente_legajo; ?>" readonly>
                                        <small class="text-muted">Se asigna automáticamente</small>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="nombre" class="form-label required-field">Nombre</label>
                                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>" required>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="apellido" class="form-label required-field">Apellido</label>
                                        <input type="text" class="form-control" id="apellido" name="apellido" value="<?php echo isset($_POST['apellido']) ? htmlspecialchars($_POST['apellido']) : ''; ?>" required>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="cin" class="form-label required-field">CIN</label>
                                        <input type="text" class="form-control" id="cin" name="cin" value="<?php echo isset($_POST['cin']) ? htmlspecialchars($_POST['cin']) : ''; ?>" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label for="genero" class="form-label required-field">Género</label>
                                        <select class="form-select" id="genero" name="genero" required>
                                            <option value="MASCULINO" <?php echo (!isset($_POST['genero']) || $_POST['genero'] == 'MASCULINO') ? 'selected' : ''; ?>>Masculino</option>
                                            <option value="FEMENINO" <?php echo (isset($_POST['genero']) && $_POST['genero'] == 'FEMENINO') ? 'selected' : ''; ?>>Femenino</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="grado_id" class="form-label required-field">Grado</label>
                                        <select class="form-select" id="grado_id" name="grado_id" required>
                                            <option value="">Seleccionar grado...</option>
                                            <?php 
                                            if ($grados->num_rows > 0) {
                                                while ($grado = $grados->fetch_assoc()): 
                                            ?>
                                            <option value="<?php echo $grado['id']; ?>" <?php echo (isset($_POST['grado_id']) && $_POST['grado_id'] == $grado['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($grado['nombre']); ?></option>
                                            <?php 
                                                endwhile; 
                                                $grados->data_seek(0); // Reset pointer para posible uso futuro
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="especialidad_id" class="form-label">Especialidad</label>
                                        <select class="form-select" id="especialidad_id" name="especialidad_id">
                                            <option value="">Seleccionar especialidad...</option>
                                            <?php 
                                            if ($especialidades->num_rows > 0) {
                                                while ($especialidad = $especialidades->fetch_assoc()): 
                                            ?>
                                            <option value="<?php echo $especialidad['id']; ?>" <?php echo (isset($_POST['especialidad_id']) && $_POST['especialidad_id'] == $especialidad['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($especialidad['nombre']); ?></option>
                                            <?php 
                                                endwhile; 
                                                $especialidades->data_seek(0);
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="cargo" class="form-label">Cargo</label>
                                        <input type="text" class="form-control" id="cargo" name="cargo" value="<?php echo isset($_POST['cargo']) ? htmlspecialchars($_POST['cargo']) : ''; ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="comisionamiento" class="form-label">Comisionamiento</label>
                                        <input type="text" class="form-control" id="comisionamiento" name="comisionamiento" value="<?php echo isset($_POST['comisionamiento']) ? htmlspecialchars($_POST['comisionamiento']) : ''; ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="telefono" class="form-label">Teléfono</label>
                                        <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="region_id" class="form-label required-field">Región</label>
                                        <select class="form-select" id="region_id" name="region_id" required>
                                            <option value="">Seleccionar región...</option>
                                            <?php 
                                            if ($regiones->num_rows > 0) {
                                                while ($region = $regiones->fetch_assoc()): 
                                            ?>
                                            <option value="<?php echo $region['id']; ?>" <?php echo (isset($_POST['region_id']) && $_POST['region_id'] == $region['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($region['nombre']); ?></option>
                                            <?php 
                                                endwhile; 
                                                $regiones->data_seek(0);
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="lugar_guardia_id" class="form-label">Lugar de Guardia Asignado</label>
                                        <select class="form-select" id="lugar_guardia_id" name="lugar_guardia_id">
                                            <option value="">Seleccionar lugar...</option>
                                            <?php 
                                            if ($lugares_guardias->num_rows > 0) {
                                                while ($lugar = $lugares_guardias->fetch_assoc()): 
                                            ?>
                                            <option value="<?php echo $lugar['id']; ?>" <?php echo (isset($_POST['lugar_guardia_id']) && $_POST['lugar_guardia_id'] == $lugar['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($lugar['nombre']); ?></option>
                                            <?php 
                                                endwhile; 
                                                $lugares_guardias->data_seek(0);
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="zona_guardia" class="form-label">Zona de Guardia</label>
                                        <input type="text" class="form-control" id="zona_guardia" name="zona_guardia" value="<?php echo isset($_POST['zona_guardia']) ? htmlspecialchars($_POST['zona_guardia']) : ''; ?>" placeholder="Especificar zona...">
                                    </div>
                                  
                                </div>

                                <div class="mb-3">
                                    <label for="observaciones" class="form-label">Observaciones</label>
                                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3"><?php echo isset($_POST['observaciones']) ? htmlspecialchars($_POST['observaciones']) : ''; ?></textarea>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Registrar Policía</button>
                                    <a href="index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
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
        // Toggle submenu for sidebar
        document.querySelectorAll('.sidebar .has-submenu > a').forEach(item => {
            item.addEventListener('click', event => {
                event.preventDefault();
                let submenu = item.nextElementSibling;
                if (submenu) {
                    submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
                    item.querySelector('.submenu-arrow').classList.toggle('fa-chevron-down');
                }
            });
        });
    </script>
</body>
</html>
<?php
$conn->close(); // <-- ADD THIS LINE HERE, AT THE VERY END
?>