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
    
    if ($result && $row = $result->fetch()) {
        $max_legajo = $row['max_legajo'];
        if ($max_legajo === null) {
            return 1;
        }
        return $max_legajo + 1;
    }
    
    return 1;
}

$siguiente_legajo = obtenerSiguienteLegajo($conn);
$grados = $conn->query("SELECT tg.*, g.nombre as categoria_nombre FROM tipo_grados tg JOIN grados g ON tg.grado_id = g.id ORDER BY g.nivel_jerarquia ASC, tg.nivel_jerarquia ASC");
$especialidades = $conn->query("SELECT * FROM especialidades ORDER BY nombre ASC");
$regiones = $conn->query("SELECT * FROM regiones ORDER BY nombre ASC");
$lugares_guardias = $conn->query("SELECT * FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");

$mensaje = "";

// Función para generar usuarios aleatorios
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'crear_random') {
    // Verificar si se usa el modo simple o avanzado
    $modo_simple = isset($_POST['modo_simple']) && $_POST['modo_simple'] == '1';
    
    if ($modo_simple) {
        // Modo simple: cantidad total con lugares aleatorios
        $cantidad = intval($_POST['cantidad']);
        if ($cantidad > 0 && $cantidad <= 100) {
            $nombres = ['Juan', 'María', 'Pedro', 'Ana', 'Carlos', 'Laura', 'Roberto', 'Carmen', 'Fernando', 'Silvia', 'Diego', 'Patricia', 'Andrés', 'Mónica', 'Javier', 'Claudia', 'Ricardo', 'Adriana', 'Mauricio', 'Esperanza'];
            $apellidos = ['García', 'Rodríguez', 'Martínez', 'Fernández', 'López', 'González', 'Sánchez', 'Díaz', 'Herrera', 'Jiménez', 'Ramírez', 'Vargas', 'Castro', 'Restrepo', 'Moreno', 'Ospina', 'Peña', 'Gutiérrez', 'Cardona', 'Álvarez'];
            $comisionamientos = [null, 'VENTANILLA', 'TELEFONISTA'];
            
            $creados = 0;
            $errores = 0;
            
            for ($i = 0; $i < $cantidad; $i++) {
                $legajo = obtenerSiguienteLegajo($conn);
                $nombre = $nombres[array_rand($nombres)];
                $apellido = $apellidos[array_rand($apellidos)] . ' ' . $apellidos[array_rand($apellidos)];
                $cin = str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
                $genero = rand(0, 1) ? 'MASCULINO' : 'FEMENINO';
                $grado_id = rand(1, 5);
                $especialidad_id = rand(0, 1) ? rand(1, 2) : null;
                $cargo = 'Policía';
                $comisionamiento = $comisionamientos[array_rand($comisionamientos)];
                $telefono = '099' . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
                $region_id = rand(1, 2);
                $lugar_guardia_id = rand(1, 10);
                $observaciones = 'Usuario generado automáticamente';
                
                // Verificar que el CIN no exista
                $check_cin = $conn->prepare("SELECT id FROM policias WHERE cin = ? AND activo = 1");
                $check_cin->execute([$cin]);
                
                if ($check_cin->rowCount() == 0) {
                     $sql = "INSERT INTO policias (legajo, nombre, apellido, cin, genero, grado_id, especialidad_id, cargo, comisionamiento, telefono, region_id, lugar_guardia_id, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                     $stmt = $conn->prepare($sql);
                     
                     if ($stmt->execute([$legajo, $nombre, $apellido, $cin, $genero, $grado_id, $especialidad_id, $cargo, $comisionamiento, $telefono, $region_id, $lugar_guardia_id, $observaciones])) {
                         $policia_id = $conn->lastInsertId();
                         
                         // Insertar en lista_guardias
                         $sql_lista = "INSERT INTO lista_guardias (policia_id, posicion) SELECT ?, COALESCE(MAX(posicion), 0) + 1 FROM lista_guardias WHERE policia_id IN (SELECT id FROM policias WHERE lugar_guardia_id = ?)";
                         $stmt_lista = $conn->prepare($sql_lista);
                         $stmt_lista->execute([$policia_id, $lugar_guardia_id]);
                         
                         $creados++;
                     } else {
                         $errores++;
                     }
                 } else {
                     $errores++;
                 }
            }
            
            $mensaje = "<div class='alert alert-success'>Se crearon $creados usuarios aleatorios exitosamente.";
            if ($errores > 0) {
                $mensaje .= " $errores usuarios no pudieron ser creados.";
            }
            $mensaje .= "</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'>La cantidad debe ser entre 1 y 100</div>";
        }
    } else {
        // Modo avanzado: lugares específicos con cantidades
        $lugares_cantidades = [];
        $total_usuarios = 0;
        
        // Procesar los lugares y cantidades seleccionados
        if (isset($_POST['lugares_guardias']) && isset($_POST['cantidades'])) {
            foreach ($_POST['lugares_guardias'] as $index => $lugar_id) {
                $cantidad = intval($_POST['cantidades'][$index]);
                if ($cantidad > 0) {
                    $lugares_cantidades[$lugar_id] = $cantidad;
                    $total_usuarios += $cantidad;
                }
            }
        }
        
        if ($total_usuarios > 100) {
            $mensaje = "<div class='alert alert-danger'>El total de usuarios no puede exceder 100. Total solicitado: $total_usuarios</div>";
        } elseif (empty($lugares_cantidades)) {
            $mensaje = "<div class='alert alert-danger'>Debe seleccionar al menos un lugar de guardia con cantidad mayor a 0</div>";
        } else {
            $nombres = ['Juan', 'María', 'Pedro', 'Ana', 'Carlos', 'Laura', 'Roberto', 'Carmen', 'Fernando', 'Silvia', 'Diego', 'Patricia', 'Andrés', 'Mónica', 'Javier', 'Claudia', 'Ricardo', 'Adriana', 'Mauricio', 'Esperanza'];
            $apellidos = ['García', 'Rodríguez', 'Martínez', 'Fernández', 'López', 'González', 'Sánchez', 'Díaz', 'Herrera', 'Jiménez', 'Ramírez', 'Vargas', 'Castro', 'Restrepo', 'Moreno', 'Ospina', 'Peña', 'Gutiérrez', 'Cardona', 'Álvarez'];
            $comisionamientos = [null, 'VENTANILLA', 'TELEFONISTA'];
            
            $creados = 0;
            $errores = 0;
            $detalle_creacion = [];
            
            // Crear usuarios para cada lugar de guardia
            foreach ($lugares_cantidades as $lugar_guardia_id => $cantidad) {
                $creados_lugar = 0;
                
                for ($i = 0; $i < $cantidad; $i++) {
                    $legajo = obtenerSiguienteLegajo($conn);
                    $nombre = $nombres[array_rand($nombres)];
                    $apellido = $apellidos[array_rand($apellidos)] . ' ' . $apellidos[array_rand($apellidos)];
                    $cin = str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
                    $genero = rand(0, 1) ? 'MASCULINO' : 'FEMENINO';
                    $grado_id = rand(1, 5);
                    $especialidad_id = rand(0, 1) ? rand(1, 2) : null;
                    $cargo = 'Policía';
                    $comisionamiento = $comisionamientos[array_rand($comisionamientos)];
                    $telefono = '099' . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
                    $region_id = rand(1, 2);
                    $observaciones = 'Usuario generado automáticamente para lugar específico';
                    
                    // Verificar que el CIN no exista
                    $check_cin = $conn->prepare("SELECT id FROM policias WHERE cin = ? AND activo = 1");
                    $check_cin->execute([$cin]);
                    
                    if ($check_cin->rowCount() == 0) {
                         $sql = "INSERT INTO policias (legajo, nombre, apellido, cin, genero, grado_id, especialidad_id, cargo, comisionamiento, telefono, region_id, lugar_guardia_id, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                         $stmt = $conn->prepare($sql);
                         
                         if ($stmt->execute([$legajo, $nombre, $apellido, $cin, $genero, $grado_id, $especialidad_id, $cargo, $comisionamiento, $telefono, $region_id, $lugar_guardia_id, $observaciones])) {
                             $policia_id = $conn->lastInsertId();
                             
                             // Insertar en lista_guardias
                             $sql_lista = "INSERT INTO lista_guardias (policia_id, posicion) SELECT ?, COALESCE(MAX(posicion), 0) + 1 FROM lista_guardias WHERE policia_id IN (SELECT id FROM policias WHERE lugar_guardia_id = ?)";
                             $stmt_lista = $conn->prepare($sql_lista);
                             $stmt_lista->execute([$policia_id, $lugar_guardia_id]);
                             
                             $creados++;
                             $creados_lugar++;
                         } else {
                             $errores++;
                         }
                     } else {
                         $errores++;
                     }
                }
                
                // Obtener nombre del lugar para el resumen
                $stmt_lugar = $conn->prepare("SELECT nombre FROM lugares_guardias WHERE id = ?");
                $stmt_lugar->execute([$lugar_guardia_id]);
                $lugar_nombre = $stmt_lugar->fetchColumn();
                $detalle_creacion[] = "$creados_lugar usuarios en $lugar_nombre";
            }
            
            $mensaje = "<div class='alert alert-success'>Se crearon $creados usuarios aleatorios exitosamente:<br>";
            $mensaje .= "• " . implode("<br>• ", $detalle_creacion);
            if ($errores > 0) {
                $mensaje .= "<br><strong>$errores usuarios no pudieron ser creados.</strong>";
            }
            $mensaje .= "</div>";
        }
    }
    
    // Actualizar el siguiente legajo
    $siguiente_legajo = obtenerSiguienteLegajo($conn);
}

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
    $check_cin->execute([$cin]);

    // Validar campos obligatorios
    if (empty($genero)) {
        $mensaje = "<div class='alert alert-danger'>El campo género es obligatorio</div>";
    } elseif ($check_cin->rowCount() > 0) {
        $mensaje = "<div class='alert alert-danger'>El CIN ya está registrado por otro policía</div>";
    } else {
        $sql = "INSERT INTO policias (legajo, nombre, apellido, cin, genero, grado_id, especialidad_id, cargo, comisionamiento, telefono, region_id, lugar_guardia_id, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([$legajo, $nombre, $apellido, $cin, $genero, $grado_id, $especialidad_id, $cargo, $comisionamiento, $telefono, $region_id, $lugar_guardia_id, $observaciones])) {
            $mensaje = "<div class='alert alert-success'>Policía registrado exitosamente con legajo: $legajo</div>";
            $_POST = array(); 
            // Actualizar el siguiente legajo para el próximo registro
            $siguiente_legajo = obtenerSiguienteLegajo($conn);
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al registrar policía: " . $conn->error . "</div>";
        }
    }
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
    <link rel="stylesheet" href="css/autocomplete.css">
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="page-title mb-0">
                            <i class="fas fa-user-plus"></i> Agregar Nuevo Policía
                        </h1>
                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#randomUserModal">
                            <i class="fas fa-random"></i> Crear Usuarios Aleatorios
                        </button>
                    </div>

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
                                            if ($grados->rowCount() > 0) {
                                                while ($grado = $grados->fetch()): 
                                            ?>
                                            <option value="<?php echo $grado['id']; ?>" <?php echo (isset($_POST['grado_id']) && $_POST['grado_id'] == $grado['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($grado['categoria_nombre'] . ' - ' . $grado['nombre']); ?>
                                            </option>
                                            <?php 
                                                endwhile;
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="especialidad_id" class="form-label">Especialidad</label>
                                        <select class="form-select" id="especialidad_id" name="especialidad_id">
                                            <option value="">Seleccionar especialidad...</option>
                                            <?php 
                                            if ($especialidades->rowCount() > 0) {
                                                while ($especialidad = $especialidades->fetch()): 
                                            ?>
                                            <option value="<?php echo $especialidad['id']; ?>" <?php echo (isset($_POST['especialidad_id']) && $_POST['especialidad_id'] == $especialidad['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($especialidad['nombre']); ?></option>
                                            <?php 
                                                endwhile;
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
                                        <input type="text" class="form-control" id="comisionamiento" name="comisionamiento" value="<?php echo isset($_POST['comisionamiento']) ? htmlspecialchars($_POST['comisionamiento']) : ''; ?>" placeholder="Ej: VENTANILLA, SECRETARÍA">
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
                                            if ($regiones->rowCount() > 0) {
                                                while ($region = $regiones->fetch()): 
                                            ?>
                                            <option value="<?php echo $region['id']; ?>" <?php echo (isset($_POST['region_id']) && $_POST['region_id'] == $region['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($region['nombre']); ?></option>
                                            <?php 
                                                endwhile;
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="lugar_guardia_id" class="form-label">Lugar de Guardia Principal</label>
                                        <select class="form-select" id="lugar_guardia_id" name="lugar_guardia_id">
                                            <option value="">Seleccionar lugar principal...</option>
                                            <?php 
                                            if ($lugares_guardias->rowCount() > 0) {
                                                while ($lugar = $lugares_guardias->fetch()): 
                                            ?>
                                            <option value="<?php echo $lugar['id']; ?>" <?php echo (isset($_POST['lugar_guardia_id']) && $_POST['lugar_guardia_id'] == $lugar['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($lugar['nombre']); ?></option>
                                            <?php 
                                                endwhile;
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
                                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3" placeholder="Observaciones adicionales sobre el policía"><?php echo isset($_POST['observaciones']) ? htmlspecialchars($_POST['observaciones']) : ''; ?></textarea>
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

    <!-- Modal para crear usuarios aleatorios -->
    <div class="modal fade" id="randomUserModal" tabindex="-1" aria-labelledby="randomUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="randomUserModalLabel">
                        <i class="fas fa-random"></i> Crear Usuarios Aleatorios
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="randomUserForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="crear_random">
                        
                        <!-- Selector de modo -->
                        <div class="mb-4">
                            <label class="form-label"><strong>Modo de creación:</strong></label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="modo_creacion" id="modo_simple" value="simple" checked>
                                <label class="form-check-label" for="modo_simple">
                                    <strong>Modo Simple:</strong> Cantidad total con lugares aleatorios
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="modo_creacion" id="modo_avanzado" value="avanzado">
                                <label class="form-check-label" for="modo_avanzado">
                                    <strong>Modo Avanzado:</strong> Seleccionar lugares específicos y cantidades
                                </label>
                            </div>
                        </div>
                        
                        <!-- Modo Simple -->
                        <div id="modo_simple_content">
                            <input type="hidden" name="modo_simple" value="1">
                            <div class="mb-3">
                                <label for="cantidad" class="form-label">¿Cuántos usuarios deseas crear?</label>
                                <input type="number" class="form-control" id="cantidad" name="cantidad" min="1" max="100" value="10" required>
                                <div class="form-text">Máximo 100 usuarios por vez</div>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Información:</strong> Se crearán usuarios con datos aleatorios incluyendo nombres, apellidos, CIN, género, grado, región y lugar de guardia asignados aleatoriamente.
                            </div>
                        </div>
                        
                        <!-- Modo Avanzado -->
                        <div id="modo_avanzado_content" style="display: none;">
                            <input type="hidden" name="modo_simple" value="0">
                            <div class="mb-3">
                                <label class="form-label"><strong>Seleccionar lugares de guardia y cantidades:</strong></label>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Importante:</strong> El total de usuarios no puede exceder 100.
                                </div>
                                
                                <div id="lugares_container">
                                    <div class="row mb-2 lugar-row">
                                        <div class="col-md-7">
                                            <select class="form-select" name="lugares_guardias[]" required>
                                                <option value="">Seleccionar lugar de guardia...</option>
                                                <?php 
                                                // Reiniciar el cursor de lugares_guardias
                                                $lugares_guardias = $conn->query("SELECT * FROM lugares_guardias WHERE activo = 1 ORDER BY nombre ASC");
                                                if ($lugares_guardias->rowCount() > 0) {
                                                    while ($lugar = $lugares_guardias->fetch()): 
                                                ?>
                                                <option value="<?php echo $lugar['id']; ?>"><?php echo htmlspecialchars($lugar['nombre']); ?></option>
                                                <?php 
                                                    endwhile;
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="number" class="form-control cantidad-input" name="cantidades[]" min="1" max="100" placeholder="Cantidad" required>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-danger btn-sm remove-lugar" style="display: none;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="button" class="btn btn-success btn-sm" id="add_lugar">
                                    <i class="fas fa-plus"></i> Agregar otro lugar
                                </button>
                                
                                <div class="mt-3">
                                    <strong>Total de usuarios: <span id="total_usuarios">0</span></strong>
                                    <div class="progress mt-2">
                                        <div class="progress-bar" role="progressbar" style="width: 0%" id="progress_bar"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Información:</strong> Se crearán usuarios con datos aleatorios para los lugares de guardia específicos seleccionados.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning" id="submit_btn">
                            <i class="fas fa-plus"></i> Crear Usuarios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/autocomplete.js"></script>
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
<script>
// JavaScript para el modal de usuarios aleatorios
document.addEventListener('DOMContentLoaded', function() {
    const modoSimple = document.getElementById('modo_simple');
    const modoAvanzado = document.getElementById('modo_avanzado');
    const modoSimpleContent = document.getElementById('modo_simple_content');
    const modoAvanzadoContent = document.getElementById('modo_avanzado_content');
    const addLugarBtn = document.getElementById('add_lugar');
    const lugaresContainer = document.getElementById('lugares_container');
    const totalUsuarios = document.getElementById('total_usuarios');
    const progressBar = document.getElementById('progress_bar');
    const submitBtn = document.getElementById('submit_btn');
    
    // Cambio entre modos
    modoSimple.addEventListener('change', function() {
        if (this.checked) {
            modoSimpleContent.style.display = 'block';
            modoAvanzadoContent.style.display = 'none';
            // Limpiar validaciones del modo avanzado
            const selectsAvanzado = modoAvanzadoContent.querySelectorAll('select, input');
            selectsAvanzado.forEach(input => {
                input.removeAttribute('required');
            });
            // Activar validaciones del modo simple
            document.getElementById('cantidad').setAttribute('required', 'required');
        }
    });
    
    modoAvanzado.addEventListener('change', function() {
        if (this.checked) {
            modoSimpleContent.style.display = 'none';
            modoAvanzadoContent.style.display = 'block';
            // Limpiar validaciones del modo simple
            document.getElementById('cantidad').removeAttribute('required');
            // Activar validaciones del modo avanzado
            const selectsAvanzado = modoAvanzadoContent.querySelectorAll('select[name="lugares_guardias[]"], input[name="cantidades[]"]');
            selectsAvanzado.forEach(input => {
                input.setAttribute('required', 'required');
            });
            calcularTotal();
        }
    });
    
    // Agregar nuevo lugar
    addLugarBtn.addEventListener('click', function() {
        const lugarRows = lugaresContainer.querySelectorAll('.lugar-row');
        if (lugarRows.length >= 10) {
            alert('Máximo 10 lugares de guardia permitidos');
            return;
        }
        
        const newRow = lugarRows[0].cloneNode(true);
        // Limpiar valores
        newRow.querySelector('select').value = '';
        newRow.querySelector('input').value = '';
        // Mostrar botón de eliminar
        newRow.querySelector('.remove-lugar').style.display = 'block';
        
        lugaresContainer.appendChild(newRow);
        
        // Agregar event listeners al nuevo row
        addRowEventListeners(newRow);
        
        calcularTotal();
    });
    
    // Función para agregar event listeners a una fila
    function addRowEventListeners(row) {
        const removeBtn = row.querySelector('.remove-lugar');
        const cantidadInput = row.querySelector('.cantidad-input');
        const selectLugar = row.querySelector('select');
        
        removeBtn.addEventListener('click', function() {
            row.remove();
            calcularTotal();
        });
        
        cantidadInput.addEventListener('input', calcularTotal);
        selectLugar.addEventListener('change', validarLugaresUnicos);
    }
    
    // Agregar event listeners a la primera fila
    const firstRow = lugaresContainer.querySelector('.lugar-row');
    addRowEventListeners(firstRow);
    
    // Calcular total de usuarios
    function calcularTotal() {
        const cantidadInputs = lugaresContainer.querySelectorAll('.cantidad-input');
        let total = 0;
        
        cantidadInputs.forEach(input => {
            const valor = parseInt(input.value) || 0;
            total += valor;
        });
        
        totalUsuarios.textContent = total;
        
        // Actualizar barra de progreso
        const porcentaje = Math.min((total / 100) * 100, 100);
        progressBar.style.width = porcentaje + '%';
        
        // Cambiar color de la barra según el total
        progressBar.className = 'progress-bar';
        if (total > 100) {
            progressBar.classList.add('bg-danger');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Excede el límite (100)';
        } else if (total === 0) {
            progressBar.classList.add('bg-secondary');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-plus"></i> Crear Usuarios';
        } else {
            progressBar.classList.add('bg-success');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-plus"></i> Crear ' + total + ' Usuarios';
        }
    }
    
    // Validar que no se repitan lugares
    function validarLugaresUnicos() {
        const selects = lugaresContainer.querySelectorAll('select[name="lugares_guardias[]"]');
        const valores = [];
        let hayDuplicados = false;
        
        selects.forEach(select => {
            if (select.value && valores.includes(select.value)) {
                hayDuplicados = true;
                select.classList.add('is-invalid');
            } else {
                select.classList.remove('is-invalid');
                if (select.value) {
                    valores.push(select.value);
                }
            }
        });
        
        if (hayDuplicados) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Lugares duplicados';
        } else {
            calcularTotal();
        }
    }
    
    // Validación del formulario
    document.getElementById('randomUserForm').addEventListener('submit', function(e) {
        if (modoAvanzado.checked) {
            const total = parseInt(totalUsuarios.textContent);
            if (total === 0) {
                e.preventDefault();
                alert('Debe especificar al menos un usuario para crear.');
                return;
            }
            if (total > 100) {
                e.preventDefault();
                alert('El total de usuarios no puede exceder 100.');
                return;
            }
            
            // Validar lugares únicos
            const selects = lugaresContainer.querySelectorAll('select[name="lugares_guardias[]"]');
            const valores = [];
            let hayDuplicados = false;
            
            selects.forEach(select => {
                if (select.value) {
                    if (valores.includes(select.value)) {
                        hayDuplicados = true;
                    } else {
                        valores.push(select.value);
                    }
                }
            });
            
            if (hayDuplicados) {
                e.preventDefault();
                alert('No puede seleccionar el mismo lugar de guardia múltiples veces.');
                return;
            }
        }
    });
    
    // Inicializar cálculo
    calcularTotal();
});
</script>

</body>
</html>
<?php
// PDO connections close automatically when script ends
?>