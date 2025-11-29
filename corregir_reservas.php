<?php
require_once 'cnx/db_connect.php';

// Iniciar sesión para obtener las correcciones
session_start();

echo "<h2>Corrección Masiva de lugar_guardia_reserva_id</h2>";

if (!isset($_SESSION['correcciones_reserva']) || empty($_SESSION['correcciones_reserva'])) {
    echo "<p style='color: red;'>No hay correcciones pendientes o no se han analizado los datos.</p>";
    echo "<p>Por favor, ejecute primero <a href='analizar_oficiales.php'>analizar_oficiales.php</a></p>";
    exit;
}

$correcciones = $_SESSION['correcciones_reserva'];
echo "<p>Se van a corregir " . count($correcciones) . " oficiales.</p>";

// Mostrar resumen de correcciones
echo "<h3>Resumen de correcciones:</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f2f2f2;'>";
echo "<th>Legajo</th><th>Nombre</th><th>Apellido</th><th>Zona</th><th>Reserva Actual</th><th>Reserva Correcta</th>";
echo "</tr>";

foreach ($correcciones as $corr) {
    echo "<tr>";
    echo "<td>" . $corr['legajo'] . "</td>";
    echo "<td>" . $corr['nombre'] . "</td>";
    echo "<td>" . $corr['apellido'] . "</td>";
    echo "<td>" . $corr['zona_actual'] . "</td>";
    echo "<td style='color: red;'>11</td>";
    echo "<td style='color: green; font-weight: bold;'>" . $corr['reserva_correcta'] . "</td>";
    echo "</tr>";
}

echo "</table>";

// Procesar la corrección si se ha enviado el formulario
if (isset($_POST['confirmar_correccion'])) {
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
                echo "<p style='color: green;'>✓ Oficial " . $corr['legajo'] . " - " . $corr['nombre'] . " " . $corr['apellido'] . ": lugar_guardia_reserva_id actualizado a " . $corr['reserva_correcta'] . "</p>";
            } else {
                $errores++;
                echo "<p style='color: red;'>✗ Error al actualizar oficial " . $corr['legajo'] . "</p>";
            }
        } catch (PDOException $e) {
            $errores++;
            echo "<p style='color: red;'>✗ Error al actualizar oficial " . $corr['legajo'] . ": " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h3>Resultado de la corrección:</h3>";
    echo "<p style='color: green;'>✓ Oficiales actualizados correctamente: $actualizadas</p>";
    if ($errores > 0) {
        echo "<p style='color: red;'>✗ Errores: $errores</p>";
    }
    
    // Limpiar las correcciones de la sesión
    unset($_SESSION['correcciones_reserva']);
    
    echo "<br><a href='analizar_oficiales.php' style='background-color: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Verificar cambios</a>";
    
} else {
    // Mostrar formulario de confirmación
    echo "<form method='POST' action=''>";
    echo "<input type='hidden' name='confirmar_correccion' value='1'>";
    echo "<br><button type='submit' style='background-color: #FF9800; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;'>";
    echo "⚠️ CONFIRMAR CORRECCIÓN MASIVA";
    echo "</button>";
    echo "</form>";
    
    echo "<p style='color: #FF5722; font-weight: bold;'>⚠️ ADVERTENCIA: Esta acción actualizará los lugar_guardia_reserva_id de todos los oficiales listados arriba.</p>";
}