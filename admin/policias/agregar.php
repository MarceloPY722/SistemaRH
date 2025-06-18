<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

// Obtener grados, especialidades y lugares de guardia para los selectores del formulario
$grados = $conn->query("SELECT * FROM grados ORDER BY nivel_jerarquia ASC");
$especialidades = $conn->query("SELECT * FROM especialidades ORDER BY nombre ASC");
$lugares_guardias = $conn->query("SELECT * FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");

$mensaje = "";

// Procesar formulario de nuevo policía
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'crear') {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $cin = trim($_POST['cin']);
    $grado_id = $_POST['grado_id'];
    $especialidad_id = !empty($_POST['especialidad_id']) ? $_POST['especialidad_id'] : null;
    $cargo = trim($_POST['cargo']);
    $comisionamiento = trim($_POST['comisionamiento']);
    $telefono = trim($_POST['telefono']);
    $region = $_POST['region'];
    $lugar_guardia_id = !empty($_POST['lugar_guardia_id']) ? $_POST['lugar_guardia_id'] : null;
    $fecha_ingreso = $_POST['fecha_ingreso'];
    $observaciones = trim($_POST['observaciones']);

    $sql = "INSERT INTO policias (nombre, apellido, cin, grado_id, especialidad_id, cargo, comisionamiento, telefono, region, lugar_guardia_id, fecha_ingreso, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    // Ajustar "issssiisssis" según los tipos de datos correctos. `especialidad_id` y `lugar_guardia_id` pueden ser NULL.
    $stmt->bind_param("sssiiisssiss", $nombre, $apellido, $cin, $grado_id, $especialidad_id, $cargo, $comisionamiento, $telefono, $region, $lugar_guardia_id, $fecha_ingreso, $observaciones);

    if ($stmt->execute()) {
        $mensaje = "<div class='alert alert-success'>Policía registrado exitosamente.</div>";
        // Limpiar los campos del POST para evitar re-envío o limpiar el formulario
        $_POST = array(); 
    } else {
        $mensaje = "<div class='alert alert-danger'>Error al registrar policía: " . $conn->error . "</div>";
    }
    $stmt->close();
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
                                    <div class="col-md-4 mb-3">
                                        <label for="nombre" class="form-label required-field">Nombre</label>
                                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="apellido" class="form-label required-field">Apellido</label>
                                        <input type="text" class="form-control" id="apellido" name="apellido" value="<?php echo isset($_POST['apellido']) ? htmlspecialchars($_POST['apellido']) : ''; ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="cin" class="form-label required-field">CIN</label>
                                        <input type="text" class="form-control" id="cin" name="cin" value="<?php echo isset($_POST['cin']) ? htmlspecialchars($_POST['cin']) : ''; ?>" required>
                                    </div>
                                </div>

                                <div class="row">
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
                                        <label for="region" class="form-label required-field">Región</label>
                                        <select class="form-select" id="region" name="region" required>
                                            <option value="CENTRAL" <?php echo (isset($_POST['region']) && $_POST['region'] == 'CENTRAL') ? 'selected' : ''; ?>>Central</option>
                                            <option value="REGIONAL" <?php echo (isset($_POST['region']) && $_POST['region'] == 'REGIONAL') ? 'selected' : ''; ?>>Regional</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
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
                                    <div class="col-md-6 mb-3">
                                        <label for="fecha_ingreso" class="form-label required-field">Fecha de Ingreso</label>
                                        <input type="date" class="form-control" id="fecha_ingreso" name="fecha_ingreso" value="<?php echo isset($_POST['fecha_ingreso']) ? htmlspecialchars($_POST['fecha_ingreso']) : ''; ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="observaciones" class="form-label">Observaciones</label>
                                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3"><?php echo isset($_POST['observaciones']) ? htmlspecialchars($_POST['observaciones']) : ''; ?></textarea>
                                </div>

                                <div class="mt-4">
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
        // Example: Toggle submenu for sidebar (if you add such functionality)
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