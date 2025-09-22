<?php
try {
    $conn = new PDO('mysql:host=localhost;dbname=sistema_rh_policia', 'root', '');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Iniciando corrección de restricciones...\n";
    
    // 1. Eliminar la restricción UNIQUE existente
    echo "Eliminando restricción UNIQUE existente...\n";
    $conn->exec("ALTER TABLE lugares_guardias DROP INDEX nombre");
    echo "✓ Restricción UNIQUE eliminada\n";
    
    // 2. Crear nueva restricción UNIQUE compuesta (nombre + zona)
    echo "Creando nueva restricción UNIQUE compuesta (nombre + zona)...\n";
    $conn->exec("ALTER TABLE lugares_guardias ADD UNIQUE KEY unique_nombre_zona (nombre, zona)");
    echo "✓ Nueva restricción UNIQUE compuesta creada\n";
    
    // 3. Verificar los cambios
    echo "Verificando cambios...\n";
    $stmt = $conn->query('SHOW INDEX FROM lugares_guardias');
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Índices actualizados:\n";
    foreach ($indexes as $index) {
        echo "Key: " . $index['Key_name'] . " | Column: " . $index['Column_name'] . " | Unique: " . ($index['Non_unique'] == 0 ? 'Sí' : 'No') . "\n";
    }
    
    echo "\n✅ ¡Corrección completada exitosamente!\n";
    echo "Ahora puedes tener nombres duplicados en diferentes zonas.\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>