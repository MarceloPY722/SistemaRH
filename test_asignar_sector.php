<?php
require_once 'cnx/db_connect.php';

// Simular la función seleccionarPoliciaFIFO corregida
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
    
    if ($dia_semana == 0) { // Domingo
        // Prioridad: GRUPO DOMINGO, luego cualquier policía activo
        $condicion_comisionamiento = "AND (p.comisionamiento = 'GRUPO DOMINGO' OR p.comisionamiento IS NOT NULL OR p.comisionamiento = '')";
        $orden_prioridad = "CASE WHEN p.comisionamiento = 'GRUPO DOMINGO' THEN 1 ELSE 2 END,";
    } elseif ($dia_semana >= 1 && $dia_semana <= 4) { // Lunes a Jueves
        // Cualquier policía activo (sin restricción de comisionamiento)
        $condicion_comisionamiento = "";
        $orden_prioridad = "";
    } elseif ($dia_semana == 5 || $dia_semana == 6) { // Viernes y Sábado
        // Cualquier policía activo (sin restricción de comisionamiento)
        $condicion_comisionamiento = "";
        $orden_prioridad = "";
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
    
    echo "=== Test de función seleccionarPoliciaFIFO ===\n";
    echo "Fecha: $fecha_guardia (día de semana: $dia_semana)\n";
    echo "Posición FIFO calculada: $posicion_fifo\n";
    echo "Condición comisionamiento: " . ($condicion_comisionamiento ?: "Sin restricción") . "\n";
    
    // Contar placeholders
    $placeholder_count = substr_count($sql, '?');
    echo "Placeholders en SQL: $placeholder_count\n";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$posicion_fifo, $fecha_guardia, $fecha_guardia]);
    $policia = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Policía encontrado en posición $posicion_fifo: " . ($policia ? "SÍ" : "NO") . "\n";
    
    // Si no hay policía en esa posición con el comisionamiento requerido, buscar en las siguientes posiciones
    if (!$policia) {
        echo "\nBuscando en otras posiciones...\n";
        for ($i = 1; $i <= 4; $i++) {
            if ($i == $posicion_fifo) continue; // Ya lo intentamos
            
            echo "Probando posición $i...\n";
            // Preparar un nuevo statement para evitar problemas de reutilización
            $stmt_loop = $conn->prepare($sql);
            $stmt_loop->execute([$i, $fecha_guardia, $fecha_guardia]);
            $policia = $stmt_loop->fetch(PDO::FETCH_ASSOC);
            
            if ($policia) {
                echo "Policía encontrado en posición $i: SÍ\n";
                break;
            } else {
                echo "Policía encontrado en posición $i: NO\n";
            }
        }
    }
    
    if ($policia) {
        echo "\nPolicía seleccionado:\n";
        echo "ID: " . $policia['id'] . "\n";
        echo "Legajo: " . $policia['legajo'] . "\n";
        echo "Nombre: " . $policia['nombre_completo'] . "\n";
        echo "Posición: " . $policia['posicion'] . "\n";
        echo "Comisionamiento: " . ($policia['comisionamiento'] ?: 'Sin comisionamiento') . "\n";
    } else {
        echo "\nNo se encontró ningún policía disponible.\n";
    }
    
    return $policia;
}

try {
    echo "=== Test de corrección de error SQL ===\n";
    
    // Parámetros de prueba
    $sector_id = 1;
    $fecha_guardia = '2025-09-01'; // Lunes
    
    // Verificar que existe el sector
    $stmt = $conn->prepare("SELECT id, nombre FROM lugares_guardias WHERE id = ? LIMIT 1");
    $stmt->execute([$sector_id]);
    $sector = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sector) {
        echo "Sector ID $sector_id no encontrado. Buscando primer sector disponible...\n";
        $stmt = $conn->prepare("SELECT id, nombre FROM lugares_guardias WHERE activo = 1 LIMIT 1");
        $stmt->execute();
        $sector = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sector) {
            $sector_id = $sector['id'];
            echo "Usando sector: " . $sector['nombre'] . " (ID: $sector_id)\n";
        } else {
            echo "No hay sectores disponibles para la prueba.\n";
            exit;
        }
    } else {
        echo "Sector encontrado: " . $sector['nombre'] . " (ID: $sector_id)\n";
    }
    
    // Ejecutar la función corregida
    $policia_seleccionado = seleccionarPoliciaFIFO($conn, $sector_id, $fecha_guardia);
    
    if ($policia_seleccionado) {
        echo "\n*** ÉXITO: La función funciona correctamente sin errores SQL ***\n";
    } else {
        echo "\n*** ADVERTENCIA: No se encontraron policías disponibles, pero no hubo errores SQL ***\n";
    }
    
} catch (PDOException $e) {
    echo "\n*** ERROR SQL: " . $e->getMessage() . " ***\n";
    echo "Código de error: " . $e->getCode() . "\n";
    if (strpos($e->getMessage(), 'HY093') !== false) {
        echo "*** CONFIRMADO: Aún existe el error de parámetros SQL ***\n";
    }
} catch (Exception $e) {
    echo "\n*** ERROR GENERAL: " . $e->getMessage() . " ***\n";
}
?>