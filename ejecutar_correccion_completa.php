<?php
require_once 'cnx/db_connect.php';

echo "<h2>Corrección Masiva de lugar_guardia_reserva_id</h2>";

// Primero obtener los datos de los oficiales que necesitan corrección
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
        lg2.nombre as lugar_guardia_reserva,
        lg2.zona as zona_reserva
    FROM policias p
    LEFT JOIN lugares_guardias lg1 ON p.lugar_guardia_id = lg1.id
    LEFT JOIN lugares_guardias lg2 ON p.lugar_guardia_reserva_id = lg2.id
    WHERE p.activo = 1 AND p.lugar_guardia_reserva_id = 11
    ORDER BY p.lugar_guardia_reserva_id, p.lugar_guardia_id
";

$stmt = $conn->prepare($sql);
$stmt->execute();
$oficiales = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($oficiales) == 0) {
    echo "<p style='color: green;'>No hay oficiales con lugar_guardia_reserva_id = 11 (CONDUCTOR DE GUARDIA)</p>";
    exit;
}

echo "<p>Se van a corregir " . count($oficiales) . " oficiales.</p>";

// Crear array de correcciones
$correcciones = [];
foreach ($oficiales as $oficial) {
    // Determinar el ID correcto según la zona
    $reserva_correcta = ($oficial['zona_actual'] == 'CENTRAL') ? 7 : 8;
    
    $correcciones[] = [
        'id' => $oficial['id'],
        'legajo' => $oficial['legajo'],
        'nombre' => $oficial['nombre'],
        'apellido' => $oficial['apellido'],
        'zona_actual' => $oficial['zona_actual'],
        'reserva_correcta' => $reserva_correcta
    ];
}

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

echo "<p><a href='analizar_oficiales.php'>Verificar resultados</a></p>";