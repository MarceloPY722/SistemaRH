<?php
require_once 'cnx/db_connect.php';

// Script de prueba para verificar la corrección en unos pocos oficiales

echo "<h2>Prueba de Corrección - 3 Oficiales</h2>";

// Seleccionar solo 3 oficiales para probar
$sql = "
    SELECT 
        p.id,
        p.nombre,
        p.apellido,
        p.legajo,
        p.lugar_guardia_id,
        lg1.nombre as lugar_guardia_actual,
        lg1.zona as zona_actual,
        p.lugar_guardia_reserva_id,
        lg2.nombre as lugar_guardia_reserva
    FROM policias p
    LEFT JOIN lugares_guardias lg1 ON p.lugar_guardia_id = lg1.id
    LEFT JOIN lugares_guardias lg2 ON p.lugar_guardia_reserva_id = lg2.id
    WHERE p.activo = 1 AND p.lugar_guardia_reserva_id = 11
    LIMIT 3
";

try {
    $stmt = $conn->query($sql);
    $oficiales_prueba = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($oficiales_prueba) == 0) {
        echo "<p style='color: green;'>No hay oficiales con lugar_guardia_reserva_id = 11 para probar.</p>";
        exit;
    }
    
    echo "<p>Probando con " . count($oficiales_prueba) . " oficiales.</p>";
    
    // Mostrar los oficiales seleccionados
    echo "<h3>Oficiales seleccionados para prueba:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>ID</th><th>Legajo</th><th>Nombre</th><th>Apellido</th><th>Zona Actual</th><th>Reserva Actual</th><th>Reserva Correcta</th>";
    echo "</tr>";
    
    $correcciones_prueba = [];
    foreach ($oficiales_prueba as $oficial) {
        $reserva_correcta = ($oficial['zona_actual'] == 'CENTRAL') ? 7 : 8;
        echo "<tr>";
        echo "<td>" . $oficial['id'] . "</td>";
        echo "<td>" . $oficial['legajo'] . "</td>";
        echo "<td>" . $oficial['nombre'] . "</td>";
        echo "<td>" . $oficial['apellido'] . "</td>";
        echo "<td>" . $oficial['zona_actual'] . "</td>";
        echo "<td style='color: red;'>" . $oficial['lugar_guardia_reserva_id'] . " (" . $oficial['lugar_guardia_reserva'] . ")</td>";
        echo "<td style='color: green; font-weight: bold;'>$reserva_correcta</td>";
        echo "</tr>";
        
        $correcciones_prueba[] = [
            'id' => $oficial['id'],
            'legajo' => $oficial['legajo'],
            'nombre' => $oficial['nombre'],
            'apellido' => $oficial['apellido'],
            'zona_actual' => $oficial['zona_actual'],
            'reserva_correcta' => $reserva_correcta
        ];
    }
    echo "</table>";
    
    // Procesar la corrección si se ha enviado el formulario
    if (isset($_POST['confirmar_prueba'])) {
        echo "<h3>Ejecutando correcciones de prueba...</h3>";
        
        $actualizadas = 0;
        $errores = 0;
        
        foreach ($correcciones_prueba as $corr) {
            try {
                $sql_update = "UPDATE policias SET lugar_guardia_reserva_id = :nuevo_id WHERE id = :oficial_id";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bindParam(':nuevo_id', $corr['reserva_correcta'], PDO::PARAM_INT);
                $stmt_update->bindParam(':oficial_id', $corr['id'], PDO::PARAM_INT);
                
                if ($stmt_update->execute()) {
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
        
        echo "<h3>Resultado de la prueba:</h3>";
        echo "<p style='color: green;'>✓ Oficiales actualizados correctamente: $actualizadas</p>";
        if ($errores > 0) {
            echo "<p style='color: red;'>✗ Errores: $errores</p>";
        }
        
        echo "<br><a href='analizar_oficiales.php' style='background-color: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Verificar cambios</a>";
        echo " <a href='corregir_reservas.php' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Corregir todos</a>";
        
    } else {
        // Mostrar formulario de confirmación
        echo "<form method='POST' action=''>";
        echo "<input type='hidden' name='confirmar_prueba' value='1'>";
        echo "<br><button type='submit' style='background-color: #FF9800; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;'>";
        echo "⚠️ CONFIRMAR PRUEBA (3 oficiales)";
        echo "</button>";
        echo "</form>";
        
        echo "<p style='color: #FF5722; font-weight: bold;'>⚠️ ADVERTENCIA: Esta acción actualizará los lugar_guardia_reserva_id de los 3 oficiales listados arriba como prueba.</p>";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { margin: 20px 0; }
th, td { padding: 8px; text-align: left; }
</style>