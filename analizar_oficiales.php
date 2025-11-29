<?php
require_once 'cnx/db_connect.php';

// Analizar el estado actual de los oficiales y sus lugares de guardia
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
    WHERE p.activo = 1
    ORDER BY p.lugar_guardia_reserva_id, p.lugar_guardia_id
";

try {
    $stmt = $conn->query($sql);
    $oficiales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Análisis de Oficiales y sus Lugares de Guardia</h2>";
    echo "<p>Total de oficiales activos: " . count($oficiales) . "</p>";
    
    // Contar por lugar de guardia reserva
    $reserva_counts = [];
    $zona_counts = [];
    
    foreach ($oficiales as $oficial) {
        $reserva_id = $oficial['lugar_guardia_reserva_id'];
        $zona_actual = $oficial['zona_actual'];
        
        if (!isset($reserva_counts[$reserva_id])) {
            $reserva_counts[$reserva_id] = 0;
        }
        $reserva_counts[$reserva_id]++;
        
        if (!isset($zona_counts[$zona_actual])) {
            $zona_counts[$zona_actual] = 0;
        }
        $zona_counts[$zona_actual]++;
    }
    
    echo "<h3>Distribución por Lugar de Guardia Reserva:</h3>";
    foreach ($reserva_counts as $reserva_id => $count) {
        $nombre_reserva = "ID $reserva_id";
        if ($reserva_id == 7) $nombre_reserva = "ATENCIÓN TELEFÓNICA EXCLUSIVA (CENTRAL)";
        if ($reserva_id == 8) $nombre_reserva = "ATENCIÓN TELEFÓNICA EXCLUSIVA (REGIONAL)";
        if ($reserva_id == 11) $nombre_reserva = "CONDUCTOR DE GUARDIA";
        echo "<p><strong>$nombre_reserva:</strong> $count oficiales</p>";
    }
    
    echo "<h3>Distribución por Zona Actual:</h3>";
    foreach ($zona_counts as $zona => $count) {
        echo "<p><strong>$zona:</strong> $count oficiales</p>";
    }
    
    // Mostrar detalles de oficiales con ID 11 (incorrecto)
    echo "<h3>Oficiales con lugar_guardia_reserva_id = 11 (INCORRECTO):</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>Legajo</th><th>Nombre</th><th>Apellido</th><th>Lugar Guardia Actual</th><th>Zona Actual</th><th>Lugar Guardia Reserva</th>";
    echo "</tr>";
    
    $oficiales_incorrectos = 0;
    foreach ($oficiales as $oficial) {
        if ($oficial['lugar_guardia_reserva_id'] == 11) {
            echo "<tr>";
            echo "<td>" . $oficial['legajo'] . "</td>";
            echo "<td>" . $oficial['nombre'] . "</td>";
            echo "<td>" . $oficial['apellido'] . "</td>";
            echo "<td>" . $oficial['lugar_guardia_actual'] . "</td>";
            echo "<td>" . $oficial['zona_actual'] . "</td>";
            echo "<td style='color: red; font-weight: bold;'>" . $oficial['lugar_guardia_reserva'] . " (ID: " . $oficial['lugar_guardia_reserva_id'] . ")</td>";
            echo "</tr>";
            $oficiales_incorrectos++;
        }
    }
    
    echo "</table>";
    echo "<p style='color: red; font-weight: bold;'>Total de oficiales con lugar_guardia_reserva_id incorrecto (11): $oficiales_incorrectos</p>";
    
    // Identificar cuáles deberían tener ID 7 o 8
    echo "<h3>Oficiales que necesitan corrección:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>Legajo</th><th>Nombre</th><th>Apellido</th><th>Zona Actual</th><th>Reserva Actual (ID)</th><th>Reserva Correcta</th>";
    echo "</tr>";
    
    $correcciones = [];
    foreach ($oficiales as $oficial) {
        if ($oficial['lugar_guardia_reserva_id'] == 11) {
            $reserva_correcta = ($oficial['zona_actual'] == 'CENTRAL') ? 7 : 8;
            echo "<tr>";
            echo "<td>" . $oficial['legajo'] . "</td>";
            echo "<td>" . $oficial['nombre'] . "</td>";
            echo "<td>" . $oficial['apellido'] . "</td>";
            echo "<td>" . $oficial['zona_actual'] . "</td>";
            echo "<td style='color: red;'>" . $oficial['lugar_guardia_reserva_id'] . "</td>";
            echo "<td style='color: green; font-weight: bold;'>$reserva_correcta</td>";
            echo "</tr>";
            
            $correcciones[] = [
                'id' => $oficial['id'],
                'legajo' => $oficial['legajo'],
                'nombre' => $oficial['nombre'],
                'apellido' => $oficial['apellido'],
                'zona_actual' => $oficial['zona_actual'],
                'reserva_correcta' => $reserva_correcta
            ];
        }
    }
    
    echo "</table>";
    
    // Guardar correcciones en sesión para el script de actualización
    session_start();
    $_SESSION['correcciones_reserva'] = $correcciones;
    
    echo "<h3>Resumen de correcciones necesarias:</h3>";
    echo "<p>Se necesitan corregir " . count($correcciones) . " oficiales.</p>";
    
    $central_count = 0;
    $regional_count = 0;
    foreach ($correcciones as $corr) {
        if ($corr['zona_actual'] == 'CENTRAL') $central_count++;
        if ($corr['zona_actual'] == 'REGIONAL') $regional_count++;
    }
    
    echo "<p>- Oficiales de CENTRAL que necesitan ID 7: $central_count</p>";
    echo "<p>- Oficiales de REGIONAL que necesitan ID 8: $regional_count</p>";
    
    echo "<br><a href='corregir_reservas.php' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ejecutar Corrección Masiva</a>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>