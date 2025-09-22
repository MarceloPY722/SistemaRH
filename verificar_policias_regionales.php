<?php
require_once 'cnx/db_connect.php';

echo "<h2>Verificación de Policías Regionales</h2>";

// Verificar policías regionales y sus lugares de guardia
$stmt = $conn->query("
    SELECT 
        p.nombre, 
        p.apellido, 
        r.nombre as region, 
        lg.nombre as lugar_guardia, 
        lg.zona, 
        p.estado, 
        p.activo,
        p.lugar_guardia_id
    FROM policias p 
    LEFT JOIN regiones r ON p.region_id = r.id
    LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id 
    WHERE r.nombre = 'REGIONAL' 
    ORDER BY p.nombre
");

echo "<table border='1'>";
echo "<tr><th>Nombre</th><th>Región</th><th>Lugar Guardia</th><th>Zona Lugar</th><th>Estado</th><th>Activo</th><th>Lugar ID</th></tr>";

$count = 0;
while($row = $stmt->fetch()) {
    $count++;
    echo "<tr>";
    echo "<td>{$row['nombre']} {$row['apellido']}</td>";
    echo "<td>{$row['region']}</td>";
    echo "<td>" . ($row['lugar_guardia'] ?? 'SIN ASIGNAR') . "</td>";
    echo "<td>" . ($row['zona'] ?? 'N/A') . "</td>";
    echo "<td>{$row['estado']}</td>";
    echo "<td>{$row['activo']}</td>";
    echo "<td>" . ($row['lugar_guardia_id'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><strong>Total policías regionales: $count</strong></p>";

// Verificar lugares de guardia regionales
echo "<h3>Lugares de Guardia Regionales</h3>";
$stmt2 = $conn->query("SELECT id, nombre, zona FROM lugares_guardias WHERE zona = 'REGIONAL' ORDER BY nombre");
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Nombre</th><th>Zona</th></tr>";
while($row = $stmt2->fetch()) {
    echo "<tr><td>{$row['id']}</td><td>{$row['nombre']}</td><td>{$row['zona']}</td></tr>";
}
echo "</table>";

// Verificar policías regionales disponibles para una fecha de viernes
echo "<h3>Simulación: Policías Regionales Disponibles para Viernes</h3>";
$fecha_viernes = '2025-01-10'; // Un viernes
$stmt3 = $conn->prepare("
    SELECT 
        p.id,
        p.nombre,
        p.apellido,
        p.cin,
        g.nombre as grado,
        lg.nombre as lugar_guardia,
        lg.zona as region,
        p.comisionamiento,
        p.estado,
        p.activo,
        CASE 
            WHEN a.id IS NOT NULL AND a.fecha_fin >= ? THEN 'CON_AUSENCIA'
            ELSE 'DISPONIBLE'
        END as disponibilidad
    FROM policias p
    LEFT JOIN grados g ON p.grado_id = g.id
    LEFT JOIN regiones r ON p.region_id = r.id
    LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
    LEFT JOIN ausencias a ON p.id = a.policia_id 
        AND a.fecha_inicio <= ? 
        AND a.fecha_fin >= ?
    WHERE p.activo = 1
    AND p.estado = 'DISPONIBLE'
    AND r.nombre = 'REGIONAL'
    
    ORDER BY p.nombre
");
$stmt3->execute([$fecha_viernes, $fecha_viernes, $fecha_viernes]);

echo "<table border='1'>";
echo "<tr><th>Nombre</th><th>Grado</th><th>Lugar Guardia</th><th>Comisionamiento</th><th>Disponibilidad</th></tr>";
$disponibles = 0;
while($row = $stmt3->fetch()) {
    echo "<tr>";
    echo "<td>{$row['nombre']} {$row['apellido']}</td>";
    echo "<td>{$row['grado']}</td>";
    echo "<td>{$row['lugar_guardia']}</td>";
    echo "<td>{$row['comisionamiento']}</td>";
    echo "<td>{$row['disponibilidad']}</td>";
    echo "</tr>";
    if($row['disponibilidad'] == 'DISPONIBLE') $disponibles++;
}
echo "</table>";
echo "<p><strong>Policías regionales disponibles para viernes: $disponibles</strong></p>";
?>