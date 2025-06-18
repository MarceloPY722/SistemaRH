<?php
session_start();

// Verificar si el usuario está logueado y tiene permisos (puedes ajustar esto según tu sistema de roles)
if (!isset($_SESSION['usuario_id'])) {
    // Redirigir a la página de login si no está logueado
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php'; // Ajusta la ruta si es necesario

$mensaje = "";
$policia_id_a_eliminar = null;

// --- INICIO: Lógica para obtener el ID del policía a eliminar ---
// Opción 1: Obtener el ID desde un parámetro GET (ej: eliminar_policia_script.php?id=1)
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $policia_id_a_eliminar = (int)$_GET['id'];
} 
// Opción 2: Si quieres que el script siempre elimine el ID 1 (como en tu solicitud original)
// Descomenta la siguiente línea y comenta o elimina la sección de $_GET['id'] de arriba.
// $policia_id_a_eliminar = 1;

// Opción 3: Obtener el ID desde un formulario POST
// if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['policia_id']) && filter_var($_POST['policia_id'], FILTER_VALIDATE_INT)) {
//    $policia_id_a_eliminar = (int)$_POST['policia_id'];
// }
// --- FIN: Lógica para obtener el ID del policía a eliminar ---

if ($policia_id_a_eliminar !== null) {
    // Verificar si el policía existe antes de intentar eliminarlo (opcional pero recomendado)
    $stmt_check = $conn->prepare("SELECT id FROM policias WHERE id = ?");
    $stmt_check->bind_param("i", $policia_id_a_eliminar);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        // --- Lógica de eliminación (Soft Delete) ---
        // Cambia activo a 0 (FALSE) en lugar de DELETE FROM
        $sql = "UPDATE policias SET activo = 0 WHERE id = ?";
        // Para eliminación permanente (Hard Delete), usarías:
        // $sql = "DELETE FROM policias WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $policia_id_a_eliminar);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $mensaje = "<div class='alert alert-success'>Policía con ID " . htmlspecialchars($policia_id_a_eliminar) . " marcado como inactivo exitosamente.</div>";
                    // Para Hard Delete: $mensaje = "<div class='alert alert-success'>Policía con ID " . htmlspecialchars($policia_id_a_eliminar) . " eliminado exitosamente.</div>";
                } else {
                    $mensaje = "<div class='alert alert-warning'>No se encontró un policía activo con el ID " . htmlspecialchars($policia_id_a_eliminar) . " o ya estaba inactivo.</div>";
                    // Para Hard Delete: $mensaje = "<div class='alert alert-warning'>No se encontró un policía con el ID " . htmlspecialchars($policia_id_a_eliminar) . ".</div>";
                }
            } else {
                $mensaje = "<div class='alert alert-danger'>Error al ejecutar la eliminación: " . htmlspecialchars($stmt->error) . "</div>";
            }
            $stmt->close();
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al preparar la consulta de eliminación: " . htmlspecialchars($conn->error) . "</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-danger'>Error: No se encontró ningún policía con el ID " . htmlspecialchars($policia_id_a_eliminar) . ".</div>";
    }
    $stmt_check->close();
} else {
    if ($_SERVER["REQUEST_METHOD"] != "POST") { // Evitar mensaje si es un POST sin ID (podría ser un formulario)
       $mensaje = "<div class='alert alert-warning'>No se proporcionó un ID de policía válido para eliminar.</div>";
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Policía</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding-top: 20px; }
        .container { max-width: 800px; }
        .card { border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h4><i class="fas fa-trash-alt"></i> Confirmar Eliminación de Policía</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($mensaje)) echo $mensaje; ?>

                <?php if ($policia_id_a_eliminar === null && $_SERVER["REQUEST_METHOD"] != "POST"): ?>
                    <p>Para eliminar un policía, especifique el ID en la URL, por ejemplo: 
                        <code><a href="?id=1">eliminar_policia_script.php?id=1</a></code>
                    </p>
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="mt-3">
                        <div class="mb-3">
                            <label for="policia_id_input" class="form-label">ID del Policía a Eliminar:</label>
                            <input type="number" class="form-control" id="policia_id_input" name="policia_id" required>
                        </div>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Eliminar Policía</button>
                    </form>
                <?php elseif ($policia_id_a_eliminar !== null && strpos($mensaje, 'alert-success') === false && strpos($mensaje, 'Error') === false): ?>
                    <!-- Mostrar confirmación si el ID está presente y no hay error/éxito aún -->
                    <p>¿Está seguro de que desea marcar como inactivo al policía con ID <strong><?php echo htmlspecialchars($policia_id_a_eliminar); ?></strong>?</p>
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?id=<?php echo htmlspecialchars($policia_id_a_eliminar); ?>"> 
                        <!-- Puedes pasar el ID por POST también si prefieres -->
                        <input type="hidden" name="confirm_delete" value="yes">
                        <input type="hidden" name="policia_id" value="<?php echo htmlspecialchars($policia_id_a_eliminar); ?>">
                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Sí, marcar como inactivo</button>
                        <a href="index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
                    </form>
                <?php endif; ?>

                <hr>
                <a href="index.php" class="btn btn-primary"><i class="fas fa-list"></i> Volver a la Lista de Policías</a>
                <a href="agregar.php" class="btn btn-success"><i class="fas fa-user-plus"></i> Agregar Nuevo Policía</a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>