<?php
try {
    $conn = new PDO('mysql:host=localhost;dbname=sistema_rh_policia', 'root', '');
    echo "Conexión exitosa\n";
    
    // Verificar índices de la tabla
    $stmt = $conn->query('SHOW INDEX FROM lugares_guardias');
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Índices de la tabla lugares_guardias:\n";
    foreach ($indexes as $index) {
        echo "Key: " . $index['Key_name'] . " | Column: " . $index['Column_name'] . " | Unique: " . ($index['Non_unique'] == 0 ? 'Sí' : 'No') . "\n";
    }
    
    // Verificar estructura completa
    $stmt = $conn->query('SHOW CREATE TABLE lugares_guardias');
    $table = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\nEstructura completa:\n" . $table['Create Table'] . "\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>