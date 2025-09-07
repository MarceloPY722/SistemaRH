<?php
require_once '../../cnx/db_connect.php';

echo "<h2>Lugares de Guardia:</h2>";
$stmt = $conn->prepare('SELECT id, nombre, zona FROM lugares_guardias ORDER BY zona, nombre');
$stmt->execute();
$lugares = $stmt->fetchAll();

echo "<table border='1'>";
echo "<tr><th>ID</th><th>Nombre</th><th>Zona</th></tr>";
foreach($lugares as $lugar) {
    $zona = $lugar['zona'] ? $lugar['zona'] : '[VACÍO]';
    echo "<tr>";
    echo "<td>{$lugar['id']}</td>";
    echo "<td>{$lugar['nombre']}</td>";
    echo "<td style='color: " . ($lugar['zona'] ? 'black' : 'red') . "'>{$zona}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Resumen:</h3>";
$stmt = $conn->prepare('SELECT zona, COUNT(*) as total FROM lugares_guardias GROUP BY zona');
$stmt->execute();
$resumen = $stmt->fetchAll();

echo "<ul>";
foreach($resumen as $item) {
    $zona = $item['zona'] ? $item['zona'] : '[SIN ZONA]';
    echo "<li>{$zona}: {$item['total']} lugares</li>";
}
echo "</ul>";
?>