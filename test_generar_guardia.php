<?php
require_once 'cnx/db_connect.php';

try {
    echo "=== Test de consulta generar_guardia.php ===\n";
    
    $fecha = '2025-09-01';
    $region = 'CENTRAL';
    
    $sql = "
        SELECT 
            p.id,
            p.legajo,
            p.nombre,
            p.apellido,
            p.cin,
            g.nombre as grado,
            g.nivel_jerarquia,
            e.nombre as especialidad,
            p.cargo,
            p.telefono,
            p.lugar_guardia_id,
            lg.nombre as lugar_guardia,
            lg.zona as region,
            p.comisionamiento,
            p.created_at as fecha_ingreso,
            DATEDIFF(CURDATE(), p.created_at) as antiguedad_dias,
            CASE 
                WHEN a.id IS NOT NULL AND a.fecha_fin >= ? THEN 'CON_AUSENCIA'
                ELSE 'DISPONIBLE'
            END as disponibilidad,
            a.fecha_fin as fecha_fin_ausencia,
            hgp.fecha_guardia as ultima_guardia,
            COALESCE(DATEDIFF(?, hgp.fecha_guardia), 999) as dias_desde_ultima_guardia,
            COALESCE(lg_lista.posicion, 999) as posicion_fifo
        FROM policias p
        LEFT JOIN grados g ON p.grado_id = g.id
        LEFT JOIN especialidades e ON p.especialidad_id = e.id
        LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
        LEFT JOIN lista_guardias lg_lista ON p.id = lg_lista.policia_id
        LEFT JOIN ausencias a ON p.id = a.policia_id 
            AND a.fecha_inicio <= ? 
            AND a.fecha_fin >= ?
        LEFT JOIN (
            SELECT ggd.policia_id, MAX(gg.fecha_guardia) as fecha_guardia
            FROM guardias_generadas_detalle ggd
            JOIN guardias_generadas gg ON ggd.guardia_generada_id = gg.id
            GROUP BY ggd.policia_id
        ) hgp ON p.id = hgp.policia_id
        WHERE p.activo = 1
        AND p.estado = 'DISPONIBLE'
        AND lg.zona = ?
        ORDER BY 
            CASE WHEN a.id IS NOT NULL THEN 2
                 ELSE 1 END,
            COALESCE(lg_lista.posicion, 999) ASC,
            COALESCE(DATEDIFF(?, hgp.fecha_guardia), 999) DESC,
            g.nivel_jerarquia ASC,
            p.legajo ASC
    ";
    
    // Contar placeholders
    $placeholder_count = substr_count($sql, '?');
    echo "Número de placeholders (?): $placeholder_count\n";
    
    // Parámetros que se están pasando
    $parametros = [$fecha, $fecha, $fecha, $fecha, $region, $fecha];
    echo "Número de parámetros: " . count($parametros) . "\n";
    echo "Parámetros: ";
    print_r($parametros);
    
    if ($placeholder_count != count($parametros)) {
        echo "\n*** ERROR: Número de placeholders no coincide con número de parámetros ***\n";
        echo "Placeholders: $placeholder_count\n";
        echo "Parámetros: " . count($parametros) . "\n";
        
        // Mostrar cada placeholder encontrado
        echo "\nPlaceholders encontrados:\n";
        $lines = explode("\n", $sql);
        foreach ($lines as $line_num => $line) {
            if (strpos($line, '?') !== false) {
                echo "Línea " . ($line_num + 1) . ": " . trim($line) . "\n";
            }
        }
    } else {
        echo "\n*** OK: Número de placeholders coincide con parámetros ***\n";
        
        // Intentar ejecutar la consulta
        echo "\n=== Ejecutando consulta ===\n";
        $stmt = $conn->prepare($sql);
        $stmt->execute($parametros);
        $result = $stmt->fetchAll();
        echo "Resultados obtenidos: " . count($result) . " registros\n";
    }
    
} catch (PDOException $e) {
    echo "\nERROR SQL: " . $e->getMessage() . "\n";
    echo "Código de error: " . $e->getCode() . "\n";
} catch (Exception $e) {
    echo "\nERROR GENERAL: " . $e->getMessage() . "\n";
}
?>