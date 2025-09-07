require_once '../../cnx/db_connect.php';

echo "<h2>Verificación de Datos del Sistema</h2>";

// 1. Verificar policías
echo "<h3>1. Policías en el sistema:</h3>";
$stmt = $conn->query("
    SELECT COUNT(*) as total_activos, 
           SUM(CASE WHEN estado = 'DISPONIBLE' THEN 1 ELSE 0 END) as disponibles
    FROM policias 
    WHERE activo = 1
");
$stats = $stmt->fetch();
echo "<p>Total policías activos: {$stats['total_activos']}</p>";
echo "<p>Policías disponibles: {$stats['disponibles']}</p>";

// 2. Verificar lugares de guardia
echo "<h3>2. Lugares de guardia:</h3>";
$stmt = $conn->query("SELECT * FROM lugares_guardias ORDER BY zona, nombre");
$lugares = $stmt->fetchAll();
echo "<p>Total lugares: " . count($lugares) . "</p>";
foreach ($lugares as $lugar) {
    echo "<p>- {$lugar['nombre']} (Zona: {$lugar['zona']})</p>";
}

// 3. Verificar policías por región
echo "<h3>3. Policías por región:</h3>";
$stmt = $conn->query("
    SELECT lg.zona, COUNT(*) as total
    FROM policias p
    LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
    WHERE p.activo = 1 AND p.estado = 'DISPONIBLE'
    GROUP BY lg.zona
");
$por_region = $stmt->fetchAll();
foreach ($por_region as $region) {
    echo "<p>- {$region['zona']}: {$region['total']} policías</p>";
}

// 4. Verificar algunos policías de ejemplo
echo "<h3>4. Ejemplos de policías (primeros 10):</h3>";
$stmt = $conn->query("
    SELECT p.id, p.legajo, p.nombre, p.apellido, p.estado, p.activo,
           lg.nombre as lugar_guardia, lg.zona,
           g.nombre as grado, g.nivel_jerarquia
    FROM policias p
    LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
    LEFT JOIN grados g ON p.grado_id = g.id
    WHERE p.activo = 1
    ORDER BY p.legajo
    LIMIT 10
");
$ejemplos = $stmt->fetchAll();

if (empty($ejemplos)) {
    echo "<div style='color: red; font-weight: bold;'>❌ NO HAY POLICÍAS EN LA BASE DE DATOS</div>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Legajo</th><th>Nombre</th><th>Estado</th><th>Lugar Guardia</th><th>Zona</th><th>Grado</th></tr>";
    foreach ($ejemplos as $policia) {
        $zona = $policia['zona'] ?? 'SIN ZONA';
        $lugar = $policia['lugar_guardia'] ?? 'SIN LUGAR';
        $grado = $policia['grado'] ?? 'SIN GRADO';
        echo "<tr>";
        echo "<td>{$policia['legajo']}</td>";
        echo "<td>{$policia['nombre']} {$policia['apellido']}</td>";
        echo "<td>{$policia['estado']}</td>";
        echo "<td>{$lugar}</td>";
        echo "<td style='font-weight: bold; color: " . ($zona == 'SIN ZONA' ? 'red' : 'green') . ";'>{$zona}</td>";
        echo "<td>{$grado}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 5. Verificar si hay problemas de configuración
echo "<h3>5. Problemas detectados:</h3>";
$problemas = [];

// Policías sin lugar de guardia
$stmt = $conn->query("
    SELECT COUNT(*) as total
    FROM policias p
    WHERE p.activo = 1 AND p.estado = 'DISPONIBLE' AND p.lugar_guardia_id IS NULL
");
$sin_lugar = $stmt->fetch()['total'];
if ($sin_lugar > 0) {
    $problemas[] = "$sin_lugar policías sin lugar de guardia asignado";
}

// Policías sin grado
$stmt = $conn->query("
    SELECT COUNT(*) as total
    FROM policias p
    WHERE p.activo = 1 AND p.estado = 'DISPONIBLE' AND p.grado_id IS NULL
");
$sin_grado = $stmt->fetch()['total'];
if ($sin_grado > 0) {
    $problemas[] = "$sin_grado policías sin grado asignado";
}

// Verificar si hay lugares de guardia para CENTRAL y REGIONAL
$stmt = $conn->query("SELECT DISTINCT zona FROM lugares_guardias");
$zonas_disponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('CENTRAL', $zonas_disponibles)) {
    $problemas[] = "No hay lugares de guardia para la zona CENTRAL";
}
if (!in_array('REGIONAL', $zonas_disponibles)) {
    $problemas[] = "No hay lugares de guardia para la zona REGIONAL";
}

if (empty($problemas)) {
    echo "<div style='color: green; font-weight: bold;'>✅ No se detectaron problemas de configuración</div>";
} else {
    foreach ($problemas as $problema) {
        echo "<div style='color: red; font-weight: bold;'>❌ $problema</div>";
    }
}

echo "<h3>6. Consulta de prueba para obtener policías:</h3>";
$fecha_test = '2025-01-27';
$region_test = 'CENTRAL';

$sql_debug = "
    SELECT 
        p.id,
        p.legajo,
        p.nombre,
        p.apellido,
        p.estado,
        p.activo,
        lg.nombre as lugar_guardia,
        lg.zona as region,
        g.nombre as grado,
        g.nivel_jerarquia
    FROM policias p
    LEFT JOIN grados g ON p.grado_id = g.id
    LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
    WHERE p.activo = 1
    AND p.estado = 'DISPONIBLE'
    AND lg.zona = '$region_test'
    ORDER BY g.nivel_jerarquia ASC, p.legajo ASC
    LIMIT 5
";

echo "<p><strong>SQL:</strong> <code>" . htmlspecialchars($sql_debug) . "</code></p>";

try {
    $stmt = $conn->query($sql_debug);
    $resultados = $stmt->fetchAll();
    
    echo "<p><strong>Resultados encontrados:</strong> " . count($resultados) . "</p>";
    
    if (!empty($resultados)) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Legajo</th><th>Nombre</th><th>Grado</th><th>Jerarquía</th><th>Lugar</th><th>Zona</th></tr>";
        foreach ($resultados as $r) {
            echo "<tr>";
            echo "<td>{$r['id']}</td>";
            echo "<td>{$r['legajo']}</td>";
            echo "<td>{$r['nombre']} {$r['apellido']}</td>";
            echo "<td>{$r['grado']}</td>";
            echo "<td>{$r['nivel_jerarquia']}</td>";
            echo "<td>{$r['lugar_guardia']}</td>";
            echo "<td>{$r['region']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>Error en consulta: " . $e->getMessage() . "</div>";
}
?>