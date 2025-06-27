<?php
// Script de prueba para verificar el funcionamiento de la cola FIFO
require_once 'config/database.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Estado de la Cola FIFO de Guardias</h2>";
    
    // Verificar si existe el procedimiento InicializarColaFIFO
    $stmt = $conn->prepare("SHOW PROCEDURE STATUS WHERE Name = 'InicializarColaFIFO'");
    $stmt->execute();
    $procedure_exists = $stmt->rowCount() > 0;
    
    if (!$procedure_exists) {
        echo "<p style='color: red;'>⚠️ El procedimiento InicializarColaFIFO no existe. Ejecute el script DatabaseNew.sql primero.</p>";
    } else {
        echo "<p style='color: green;'>✅ Procedimiento InicializarColaFIFO encontrado.</p>";
        
        // Inicializar la cola FIFO
        try {
            $stmt = $conn->prepare("CALL InicializarColaFIFO()");
            $stmt->execute();
            echo "<p style='color: green;'>✅ Cola FIFO inicializada correctamente.</p>";
        } catch (Exception $e) {
            echo "<p style='color: orange;'>⚠️ Error al inicializar cola FIFO: " . $e->getMessage() . "</p>";
        }
    }
    
    // Mostrar los primeros 10 en la cola
    echo "<h3>Primeros 10 Policías en la Cola</h3>";
    $stmt = $conn->prepare("
        SELECT 
            lg.posicion,
            p.legajo,
            CONCAT(p.apellido, ', ', p.nombre) as nombre_completo,
            g.nombre as grado,
            r.nombre as region,
            CASE 
                WHEN lg.posicion <= 7 THEN 'COLA ACTIVA'
                ELSE 'EN ESPERA'
            END as estado,
            lg.fecha_disponible
        FROM lista_guardias lg
        JOIN policias p ON lg.policia_id = p.id
        JOIN grados g ON p.grado_id = g.id
        JOIN regiones r ON p.region_id = r.id
        WHERE p.activo = TRUE
        ORDER BY lg.posicion ASC
        LIMIT 10
    ");
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($resultados) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Posición</th><th>Legajo</th><th>Nombre</th><th>Grado</th><th>Región</th><th>Estado</th><th>Disponible</th>";
        echo "</tr>";
        
        foreach ($resultados as $row) {
            $color = $row['posicion'] <= 7 ? '#e8f5e8' : '#fff8e1';
            echo "<tr style='background-color: $color;'>";
            echo "<td>" . $row['posicion'] . "</td>";
            echo "<td>" . $row['legajo'] . "</td>";
            echo "<td>" . $row['nombre_completo'] . "</td>";
            echo "<td>" . $row['grado'] . "</td>";
            echo "<td>" . $row['region'] . "</td>";
            echo "<td><strong>" . $row['estado'] . "</strong></td>";
            echo "<td>" . ($row['fecha_disponible'] ?? 'Inmediato') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Mostrar estadísticas
        $stmt_stats = $conn->prepare("SELECT COUNT(*) as total FROM lista_guardias");
        $stmt_stats->execute();
        $total = $stmt_stats->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt_activos = $conn->prepare("SELECT COUNT(*) as activos FROM lista_guardias WHERE posicion <= 7");
        $stmt_activos->execute();
        $activos = $stmt_activos->fetch(PDO::FETCH_ASSOC)['activos'];
        
        echo "<h3>Estadísticas</h3>";
        echo "<p><strong>Total de policías en cola:</strong> $total</p>";
        echo "<p><strong>Policías en cola activa (1-7):</strong> $activos</p>";
        echo "<p><strong>Policías en espera (8+):</strong> " . ($total - $activos) . "</p>";
        
    } else {
        echo "<p style='color: red;'>No se encontraron policías en la cola.</p>";
    }
    
    // Probar rotación FIFO con el primer policía
    if (count($resultados) > 0) {
        echo "<h3>Prueba de Rotación FIFO</h3>";
        $primer_policia_id = null;
        
        // Obtener el ID del primer policía
        $stmt_primer = $conn->prepare("
            SELECT p.id, p.legajo, CONCAT(p.apellido, ', ', p.nombre) as nombre
            FROM lista_guardias lg
            JOIN policias p ON lg.policia_id = p.id
            WHERE lg.posicion = 1
        ");
        $stmt_primer->execute();
        $primer_policia = $stmt_primer->fetch(PDO::FETCH_ASSOC);
        
        if ($primer_policia) {
            echo "<p><strong>Primer policía en cola:</strong> " . $primer_policia['nombre'] . " (Legajo: " . $primer_policia['legajo'] . ")</p>";
            echo "<p><em>Para probar la rotación, asigne una guardia a este policía desde la interfaz principal.</em></p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error de conexión: " . $e->getMessage() . "</p>";
}
?>

<style>
table { font-family: Arial, sans-serif; }
th, td { padding: 8px; text-align: left; }
th { font-weight: bold; }
</style>