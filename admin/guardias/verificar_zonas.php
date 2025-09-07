<?php
require_once '../../cnx/db_connect.php';

echo "<h2>Verificación de Zonas y Policías</h2>";

try {
    // Verificar zonas en lugares_guardias
    echo "<h3>Zonas en lugares_guardias:</h3>";
    $stmt = $conn->query("SELECT zona, COUNT(*) as total FROM lugares_guardias GROUP BY zona");
    while ($row = $stmt->fetch()) {
        echo "<p>Zona: {$row['zona']} - {$row['total']} lugares</p>";
    }
    
    // Verificar policías por zona
    echo "<h3>Policías por zona:</h3>";
    $stmt = $conn->query("
        SELECT lg.zona, COUNT(p.id) as total_policias
        FROM lugares_guardias lg 
        LEFT JOIN policias p ON lg.id = p.lugar_guardia_id AND p.activo = 1
        GROUP BY lg.zona
    ");
    while ($row = $stmt->fetch()) {
        echo "<p>Zona: {$row['zona']} - {$row['total_policias']} policías</p>";
    }
    
    // Verificar policías disponibles por zona
    echo "<h3>Policías DISPONIBLES por zona:</h3>";
    $stmt = $conn->query("
        SELECT lg.zona, COUNT(p.id) as disponibles
        FROM lugares_guardias lg 
        LEFT JOIN policias p ON lg.id = p.lugar_guardia_id AND p.activo = 1 AND p.estado = 'DISPONIBLE'
        GROUP BY lg.zona
    ");
    while ($row = $stmt->fetch()) {
        echo "<p>Zona: {$row['zona']} - {$row['disponibles']} policías disponibles</p>";
    }
    
    // Mostrar algunos policías de ejemplo
    echo "<h3>Primeros 5 policías activos:</h3>";
    $stmt = $conn->query("
        SELECT p.id, p.nombre, p.apellido, p.estado, lg.nombre as lugar, lg.zona
        FROM policias p
        LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
        WHERE p.activo = 1
        LIMIT 5
    ");
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Estado</th><th>Lugar</th><th>Zona</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['nombre']} {$row['apellido']}</td>";
        echo "<td>{$row['estado']}</td>";
        echo "<td>{$row['lugar']}</td>";
        echo "<td>{$row['zona']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>