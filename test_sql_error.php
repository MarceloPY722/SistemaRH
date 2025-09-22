<?php
require_once 'cnx/db_connect.php';

// Simular una llamada a la función que puede estar causando el error
try {
    // Test 1: Verificar conexión a la base de datos
    echo "=== Test 1: Conexión a la base de datos ===\n";
    $test_query = $conn->prepare("SELECT COUNT(*) as total FROM policias WHERE activo = 1");
    $test_query->execute();
    $result = $test_query->fetch(PDO::FETCH_ASSOC);
    echo "Policías activos: " . $result['total'] . "\n\n";

    // Test 2: Simular la consulta problemática de asignar_sector.php
    echo "=== Test 2: Consulta de asignación de sector ===\n";
    
    $posicion_fifo = 1;
    $fecha_guardia = '2025-09-01';
    $dia_semana = date('w', strtotime($fecha_guardia));
    
    echo "Día de la semana: $dia_semana\n";
    
    // Construir condiciones según el día de la semana (copiado de asignar_sector.php)
    $condicion_comisionamiento = "";
    $orden_prioridad = "";
    
    if ($dia_semana == 0) { // Domingo
        $condicion_comisionamiento = "AND (p.comisionamiento = 'GRUPO DOMINGO' OR p.comisionamiento IS NOT NULL OR p.comisionamiento = '')";
        $orden_prioridad = "CASE WHEN p.comisionamiento = 'GRUPO DOMINGO' THEN 1 ELSE 2 END,";
    } elseif ($dia_semana >= 1 && $dia_semana <= 4) { // Lunes a Jueves
        $condicion_comisionamiento = "";
        $orden_prioridad = "";
    } elseif ($dia_semana == 5 || $dia_semana == 6) { // Viernes y Sábado
        $condicion_comisionamiento = "";
        $orden_prioridad = "";
    }
    
    echo "Condición comisionamiento: '$condicion_comisionamiento'\n";
    echo "Orden prioridad: '$orden_prioridad'\n\n";
    
    // Construir la consulta SQL
    $sql = "
        SELECT 
            p.id,
            p.legajo,
            CONCAT(p.nombre, ' ', p.apellido) AS nombre_completo,
            lg.posicion,
            p.comisionamiento
        FROM lista_guardias lg
        INNER JOIN policias p ON lg.policia_id = p.id
        WHERE p.activo = 1 
        AND lg.posicion = ?
        " . $condicion_comisionamiento . "
        AND p.id NOT IN (
            SELECT policia_id 
            FROM asignaciones_temporales_guardia 
            WHERE fecha_guardia = ?
        )
        AND p.id NOT IN (
            SELECT policia_id 
            FROM ausencias 
            WHERE ? BETWEEN fecha_inicio AND fecha_fin
        )
        ORDER BY " . $orden_prioridad . " lg.posicion, p.legajo
        LIMIT 1
    ";
    
    echo "=== SQL Query ===\n";
    echo $sql . "\n\n";
    
    echo "=== Parámetros ===\n";
    $parametros = [$posicion_fifo, $fecha_guardia, $fecha_guardia];
    print_r($parametros);
    echo "\n";
    
    // Contar placeholders en la consulta
    $placeholder_count = substr_count($sql, '?');
    echo "Número de placeholders (?): $placeholder_count\n";
    echo "Número de parámetros: " . count($parametros) . "\n\n";
    
    // Intentar ejecutar la consulta
    echo "=== Ejecutando consulta ===\n";
    $stmt = $conn->prepare($sql);
    $stmt->execute($parametros);
    $policia = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($policia) {
        echo "Policía encontrado: " . $policia['nombre_completo'] . "\n";
    } else {
        echo "No se encontró policía disponible\n";
    }
    
} catch (PDOException $e) {
    echo "ERROR SQL: " . $e->getMessage() . "\n";
    echo "Código de error: " . $e->getCode() . "\n";
    
    // Información adicional para debugging
    if (isset($sql)) {
        echo "\nSQL problemático:\n" . $sql . "\n";
    }
    if (isset($parametros)) {
        echo "\nParámetros:\n";
        print_r($parametros);
    }
} catch (Exception $e) {
    echo "ERROR GENERAL: " . $e->getMessage() . "\n";
}
?>