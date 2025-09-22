<?php
require_once 'cnx/db_connect.php';

echo "=== Test de corrección de parámetros SQL ===\n\n";

// Simular diferentes días de la semana para probar la función
$fechas_prueba = [
    '2025-09-21', // Domingo (debería usar condición especial)
    '2025-09-22', // Lunes (sin condición especial)
    '2025-09-27'  // Sábado (sin condición especial)
];

$sector_id = 1; // Sector de prueba

foreach ($fechas_prueba as $fecha) {
    echo "Probando fecha: $fecha\n";
    echo "Día de la semana: " . date('l', strtotime($fecha)) . "\n";
    
    try {
        $resultado = seleccionarPoliciaFIFO($conn, $sector_id, $fecha);
        
        if ($resultado) {
            echo "✓ Policía seleccionado: " . $resultado['nombre_completo'] . " (Legajo: " . $resultado['legajo'] . ")\n";
        } else {
            echo "✓ No hay policías disponibles (comportamiento esperado)\n";
        }
        echo "✓ Sin errores de parámetros SQL\n\n";
        
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'HY093') !== false || strpos($e->getMessage(), 'Invalid parameter number') !== false) {
            echo "✗ ERROR: Persiste el problema de parámetros SQL: " . $e->getMessage() . "\n\n";
        } else {
            echo "✗ ERROR de base de datos: " . $e->getMessage() . "\n\n";
        }
    } catch (Exception $e) {
        echo "✗ ERROR general: " . $e->getMessage() . "\n\n";
    }
}

// Función copiada exactamente del archivo corregido
function seleccionarPoliciaFIFO($conn, $sector_id, $fecha_guardia) {
    // Obtener el día de la semana (0=domingo, 1=lunes, ..., 6=sábado)
    $dia_semana = date('w', strtotime($fecha_guardia));
    
    // Primero, verificar cuántos policías ya están asignados para esta fecha
    $stmt = $conn->prepare("
        SELECT COUNT(*) as asignados 
        FROM asignaciones_temporales_guardia 
        WHERE fecha_guardia = ?
    ");
    $stmt->execute([$fecha_guardia]);
    $asignados = $stmt->fetch(PDO::FETCH_ASSOC)['asignados'];
    
    // Determinar qué posición FIFO usar (1, 2, 3, o 4)
    $posicion_fifo = ($asignados % 4) + 1;
    
    // Construir condiciones según el día de la semana
    $condicion_comisionamiento = "";
    $parametros_extra = [];
    $orden_prioridad = "";
    
    if ($dia_semana == 0) { // Domingo
        // Prioridad: GRUPO DOMINGO, luego cualquier policía activo
        $condicion_comisionamiento = "AND (p.comisionamiento = 'GRUPO DOMINGO' OR p.comisionamiento IS NOT NULL OR p.comisionamiento = '')";
        $orden_prioridad = "CASE WHEN p.comisionamiento = 'GRUPO DOMINGO' THEN 1 ELSE 2 END,";
    }
    
    // Buscar policías disponibles ordenados por posición FIFO y reglas de comisionamiento
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
    
    // Preparar y ejecutar la consulta con los parámetros correctos
    $stmt = $conn->prepare($sql);
    $params = [$posicion_fifo, $fecha_guardia, $fecha_guardia];
    $stmt->execute($params);
    $policia = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si no hay policía en esa posición con el comisionamiento requerido, buscar en las siguientes posiciones
    if (!$policia) {
        for ($i = 1; $i <= 4; $i++) {
            if ($i == $posicion_fifo) continue; // Ya lo intentamos
            
            // Preparar un nuevo statement para evitar problemas de reutilización
            $stmt_loop = $conn->prepare($sql);
            $params_loop = [$i, $fecha_guardia, $fecha_guardia];
            $stmt_loop->execute($params_loop);
            $policia = $stmt_loop->fetch(PDO::FETCH_ASSOC);
            
            if ($policia) break;
        }
    }
    
    return $policia;
}

echo "=== Test completado ===\n";
?>