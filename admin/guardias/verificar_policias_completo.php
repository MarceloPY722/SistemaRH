<?php
require_once '../../cnx/db_connect.php';

echo "<h2>Verificación Completa de Policías</h2>";

try {
    // 1. Total de policías en la base de datos
    $stmt = $conn->query("SELECT COUNT(*) as total FROM policias");
    $total_policias = $stmt->fetch()['total'];
    echo "<p><strong>1. Total de policías en BD:</strong> $total_policias</p>";
    
    // 2. Policías activos
    $stmt = $conn->query("SELECT COUNT(*) as total FROM policias WHERE activo = 1");
    $activos = $stmt->fetch()['total'];
    echo "<p><strong>2. Policías activos:</strong> $activos</p>";
    
    // 3. Policías disponibles
    $stmt = $conn->query("SELECT COUNT(*) as total FROM policias WHERE activo = 1 AND estado = 'DISPONIBLE'");
    $disponibles = $stmt->fetch()['total'];
    echo "<p><strong>3. Policías disponibles:</strong> $disponibles</p>";
    
    // 4. Policías con lugar_guardia_id asignado
    $stmt = $conn->query("SELECT COUNT(*) as total FROM policias WHERE activo = 1 AND lugar_guardia_id IS NOT NULL");
    $con_lugar = $stmt->fetch()['total'];
    echo "<p><strong>4. Policías con lugar de guardia asignado:</strong> $con_lugar</p>";
    
    // 5. Policías disponibles con lugar de guardia
    $stmt = $conn->query("SELECT COUNT(*) as total FROM policias WHERE activo = 1 AND estado = 'DISPONIBLE' AND lugar_guardia_id IS NOT NULL");
    $disponibles_con_lugar = $stmt->fetch()['total'];
    echo "<p><strong>5. Policías disponibles con lugar de guardia:</strong> $disponibles_con_lugar</p>";
    
    // 6. Verificar lugares de guardia
    echo "<h3>6. Lugares de Guardia:</h3>";
    $stmt = $conn->query("SELECT id, nombre, zona FROM lugares_guardias ORDER BY id");
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Zona</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr><td>{$row['id']}</td><td>{$row['nombre']}</td><td>{$row['zona']}</td></tr>";
    }
    echo "</table>";
    
    // 7. Policías por lugar de guardia
    echo "<h3>7. Policías por Lugar de Guardia:</h3>";
    $stmt = $conn->query("
        SELECT 
            lg.nombre as lugar,
            lg.zona,
            COUNT(p.id) as total_policias,
            SUM(CASE WHEN p.estado = 'DISPONIBLE' THEN 1 ELSE 0 END) as disponibles
        FROM lugares_guardias lg
        LEFT JOIN policias p ON lg.id = p.lugar_guardia_id AND p.activo = 1
        GROUP BY lg.id, lg.nombre, lg.zona
        ORDER BY lg.id
    ");
    echo "<table border='1'>";
    echo "<tr><th>Lugar</th><th>Zona</th><th>Total</th><th>Disponibles</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>{$row['lugar']}</td>";
        echo "<td>{$row['zona']}</td>";
        echo "<td>{$row['total_policias']}</td>";
        echo "<td>{$row['disponibles']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 8. Mostrar algunos policías de ejemplo
    echo "<h3>8. Primeros 10 Policías Activos:</h3>";
    $stmt = $conn->query("
        SELECT 
            p.id,
            p.legajo,
            p.nombre,
            p.apellido,
            p.estado,
            p.lugar_guardia_id,
            lg.nombre as lugar_guardia,
            lg.zona
        FROM policias p
        LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
        WHERE p.activo = 1
        ORDER BY p.id
        LIMIT 10
    ");
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Legajo</th><th>Nombre</th><th>Estado</th><th>Lugar ID</th><th>Lugar</th><th>Zona</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['legajo']}</td>";
        echo "<td>{$row['nombre']} {$row['apellido']}</td>";
        echo "<td style='color: " . ($row['estado'] == 'DISPONIBLE' ? 'green' : 'red') . "'>{$row['estado']}</td>";
        echo "<td>{$row['lugar_guardia_id']}</td>";
        echo "<td>{$row['lugar_guardia']}</td>";
        echo "<td>{$row['zona']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 9. Verificar si hay policías sin lugar de guardia asignado
    echo "<h3>9. Policías sin Lugar de Guardia:</h3>";
    $stmt = $conn->query("
        SELECT COUNT(*) as total 
        FROM policias 
        WHERE activo = 1 AND lugar_guardia_id IS NULL
    ");
    $sin_lugar = $stmt->fetch()['total'];
    echo "<p><strong>Policías activos sin lugar de guardia:</strong> $sin_lugar</p>";
    
    if ($sin_lugar > 0) {
        $stmt = $conn->query("
            SELECT id, legajo, nombre, apellido, estado
            FROM policias 
            WHERE activo = 1 AND lugar_guardia_id IS NULL
            LIMIT 5
        ");
        echo "<p>Primeros 5 policías sin lugar de guardia:</p>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Legajo</th><th>Nombre</th><th>Estado</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['legajo']}</td>";
            echo "<td>{$row['nombre']} {$row['apellido']}</td>";
            echo "<td>{$row['estado']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<br><br><a href='generar_guardia_interface.php'>Volver a Generar Guardia</a>";
?>