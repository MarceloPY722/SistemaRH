<?php
require_once 'cnx/db_connect.php';

try {
    
    echo "<h2>Reasignación de Policías Regionales</h2>";
    
    // Obtener lugares de guardia regionales
    $stmt_lugares = $conn->query("
        SELECT id, nombre, zona 
        FROM lugares_guardias 
        WHERE zona = 'REGIONAL' 
        ORDER BY id
    ");
    $lugares_regionales = $stmt_lugares->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Lugares de Guardia Regionales Disponibles:</h3>";
    foreach($lugares_regionales as $lugar) {
        echo "ID: {$lugar['id']} - {$lugar['nombre']}<br>";
    }
    
    // Obtener policías regionales que están asignados a lugares centrales
    $stmt_policias = $conn->query("
        SELECT 
            p.id,
            p.nombre,
            p.apellido,
            p.lugar_guardia_id,
            lg.nombre as lugar_actual,
            lg.zona as zona_actual,
            r.nombre as region
        FROM policias p
        LEFT JOIN regiones r ON p.region_id = r.id
        LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
        WHERE r.nombre = 'REGIONAL'
        AND (lg.zona = 'CENTRAL' OR lg.zona IS NULL)
        AND p.activo = 1
        ORDER BY p.nombre
    ");
    
    $policias_mal_asignados = $stmt_policias->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Policías Regionales Mal Asignados: " . count($policias_mal_asignados) . "</h3>";
    
    if(count($policias_mal_asignados) > 0 && count($lugares_regionales) > 0) {
        echo "<p>Procediendo a reasignar...</p>";
        
        $contador = 0;
        $lugar_index = 0;
        
        foreach($policias_mal_asignados as $policia) {
            // Distribuir equitativamente entre los lugares regionales
            $nuevo_lugar = $lugares_regionales[$lugar_index];
            
            $stmt_update = $conn->prepare("
                UPDATE policias 
                SET lugar_guardia_id = ? 
                WHERE id = ?
            ");
            
            $stmt_update->execute([$nuevo_lugar['id'], $policia['id']]);
            
            echo "✓ {$policia['nombre']} {$policia['apellido']} reasignado de '{$policia['lugar_actual']}' a '{$nuevo_lugar['nombre']}'<br>";
            
            $contador++;
            $lugar_index = ($lugar_index + 1) % count($lugares_regionales);
        }
        
        echo "<p><strong>Total reasignados: $contador policías</strong></p>";
        
        // Verificar resultado
        $stmt_verificar = $conn->query("
            SELECT COUNT(*) as total
            FROM policias p
            LEFT JOIN regiones r ON p.region_id = r.id
            LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
            WHERE r.nombre = 'REGIONAL'
            AND lg.zona = 'REGIONAL'
            AND p.activo = 1
        ");
        
        $resultado = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
        echo "<p><strong>Policías regionales ahora en lugares regionales: {$resultado['total']}</strong></p>";
        
    } else {
        echo "<p>No hay policías para reasignar o no hay lugares regionales disponibles.</p>";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>