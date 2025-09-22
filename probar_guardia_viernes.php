<?php
require_once 'cnx/db_connect.php';
require_once 'admin/guardias/generar_guardia.php';

try {
    echo "<h2>Prueba de Generación de Guardia para Viernes</h2>";
    
    // Fecha de un viernes (debe ser REGIONAL)
    $fecha_prueba = '2025-01-31'; // Viernes
    $orden_dia = 1;
    
    echo "<p><strong>Fecha:</strong> $fecha_prueba (Viernes - debe ser REGIONAL)</p>";
    
    $generador = new GeneradorGuardias($conn);
    
    // Verificar si ya existe una guardia para esta fecha
    if ($generador->fechaTieneGuardia($fecha_prueba)) {
        echo "<p style='color: orange;'>Ya existe una guardia para esta fecha. Eliminándola para hacer la prueba...</p>";
        $stmt_delete = $conn->prepare("DELETE FROM guardias_generadas_detalle WHERE guardia_generada_id IN (SELECT id FROM guardias_generadas WHERE fecha_guardia = ?)");
        $stmt_delete->execute([$fecha_prueba]);
        $stmt_delete = $conn->prepare("DELETE FROM guardias_generadas WHERE fecha_guardia = ?");
        $stmt_delete->execute([$fecha_prueba]);
    }
    
    // Limpiar orden del día si existe
    $stmt_orden = $conn->prepare("DELETE FROM orden_dia WHERE numero_orden = ?");
    $stmt_orden->execute(['31/2025']);
    
    // Limpiar cualquier guardia con el mismo orden del día
    $stmt_guardia_orden = $conn->prepare("DELETE FROM guardias_generadas_detalle WHERE guardia_generada_id IN (SELECT id FROM guardias_generadas WHERE orden_dia = ?)");
    $stmt_guardia_orden->execute(['31/2025']);
    $stmt_guardia_orden = $conn->prepare("DELETE FROM guardias_generadas WHERE orden_dia = ?");
    $stmt_guardia_orden->execute(['31/2025']);

// Generar la guardia
echo "<p>Generando guardia...</p>";
$resultado = $generador->generarGuardia($fecha_prueba, '31/2025');
    
    if($resultado['success']) {
        echo "<p style='color: green;'><strong>✓ Guardia generada exitosamente!</strong></p>";
        echo "<p><strong>Región determinada:</strong> {$resultado['region']}</p>";
        
        // Mostrar los resultados
        $stmt_guardia = $conn->prepare("
            SELECT 
                lg.nombre as lugar_guardia,
                lg.zona,
                CASE 
                    WHEN ggd.policia_id IS NOT NULL THEN CONCAT(p.nombre, ' ', p.apellido)
                    ELSE 'POSICIÓN VACÍA'
                END as policia_asignado,
                g.nombre as grado
            FROM guardias_generadas gg
            LEFT JOIN guardias_generadas_detalle ggd ON gg.id = ggd.guardia_generada_id
            LEFT JOIN lugares_guardias lg ON ggd.lugar_guardia_id = lg.id
            LEFT JOIN policias p ON ggd.policia_id = p.id
            LEFT JOIN grados g ON p.grado_id = g.id
            WHERE gg.fecha_guardia = ?
            ORDER BY lg.nombre
        ");
        
        $stmt_guardia->execute([$fecha_prueba]);
        $guardias = $stmt_guardia->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Resultado de la Guardia:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Lugar de Guardia</th><th>Zona</th><th>Policía Asignado</th><th>Grado</th></tr>";
        
        $posiciones_vacias = 0;
        foreach($guardias as $guardia) {
            $color = ($guardia['policia_asignado'] == 'POSICIÓN VACÍA') ? 'background-color: #ffcccc;' : '';
            if($guardia['policia_asignado'] == 'POSICIÓN VACÍA') $posiciones_vacias++;
            
            echo "<tr style='$color'>";
            echo "<td>{$guardia['lugar_guardia']}</td>";
            echo "<td>{$guardia['zona']}</td>";
            echo "<td>{$guardia['policia_asignado']}</td>";
            echo "<td>{$guardia['grado']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p><strong>Total lugares de guardia:</strong> " . count($guardias) . "</p>";
        echo "<p><strong>Posiciones vacías:</strong> <span style='color: " . ($posiciones_vacias > 0 ? 'red' : 'green') . "'>$posiciones_vacias</span></p>";
        
        if($posiciones_vacias == 0) {
            echo "<p style='color: green; font-weight: bold;'>🎉 ¡Perfecto! No hay posiciones vacías.</p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>⚠️ Aún hay $posiciones_vacias posiciones vacías.</p>";
        }
        
    } else {
        echo "<p style='color: red;'><strong>✗ Error al generar guardia:</strong> {$resultado['error']}</p>";
    }
    
} catch(Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>