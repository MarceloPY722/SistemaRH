<?php
require_once 'cnx/db_connect.php';

// Iniciar sesión para obtener las correcciones
session_start();

echo "<h2>Corrección Masiva de lugar_guardia_reserva_id</h2>";

if (!isset($_SESSION['correcciones_reserva']) || empty($_SESSION['correcciones_reserva'])) {
    echo "<p style='color: red;'>No hay correcciones pendientes o no se han analizado los datos.</p>";
    echo "<p>Por favor, ejecute primero analizar_oficiales.php</p>";
    exit;
}

$correcciones = $_SESSION['correcciones_reserva'];
echo "<p>Se van a corregir " . count($correcciones) . " oficiales.</p>";

// Ejecutar correcciones directamente
echo "<h3>Ejecutando correcciones...</h3>";

$actualizadas = 0;
$errores = 0;

foreach ($correcciones as $corr) {
    try {
        $sql = "UPDATE policias SET lugar_guardia_reserva_id = :nuevo_id WHERE id = :oficial_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':nuevo_id', $corr['reserva_correcta'], PDO::PARAM_INT);
        $stmt->bindParam(':oficial_id', $corr['id'], PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $actualizadas++;
            echo "<p style='color: green;'>✓ Oficial " . $corr['legajo'] . " - " . $corr['nombre'] . " " . $corr['apellido'] . " actualizado: reserva_id = " . $corr['reserva_correcta'] . "</p>";
        } else {
            $errores++;
            echo "<p style='color: red;'>✗ Error al actualizar oficial " . $corr['legajo'] . "</p>";
        }
    } catch (PDOException $e) {
        $errores++;
        echo "<p style='color: red;'>✗ Error al actualizar oficial " . $corr['legajo'] . ": " . $e->getMessage() . "</p>";
    }
}

echo "<h3>Resultado:</h3>";
echo "<p style='color: green;'>Oficiales actualizados correctamente: " . $actualizadas . "</p>";
echo "<p style='color: red;'>Errores: " . $errores . "</p>";

// Limpiar las correcciones de la sesión
unset($_SESSION['correcciones_reserva']);

echo "<p><a href='analizar_oficiales.php'>Volver al análisis</a></p>";