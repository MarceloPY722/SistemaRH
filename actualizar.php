<?php
/**
 * Script para actualizar la base de datos agregando la columna 'estado' a la tabla 'policias'
 * Este script debe ejecutarse una sola vez para aplicar los cambios necesarios
 */

require_once 'cnx/db_connect.php';

try {
    echo "<h2>Actualizando Base de Datos - Agregando columna 'estado' a tabla 'policias'</h2>";
    echo "<hr>";
    
    // Verificar si la columna 'estado' ya existe
    $check_column = "SHOW COLUMNS FROM policias LIKE 'estado'";
    $stmt_check = $conn->prepare($check_column);
    $stmt_check->execute();
    $column_exists = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if ($column_exists) {
        echo "<p style='color: orange;'>⚠️ La columna 'estado' ya existe en la tabla 'policias'.</p>";
    } else {
        // Agregar la columna 'estado' a la tabla 'policias'
        $sql_add_column = "ALTER TABLE policias ADD COLUMN estado ENUM('DISPONIBLE', 'NO DISPONIBLE') DEFAULT 'DISPONIBLE' AFTER activo";
        $conn->exec($sql_add_column);
        echo "<p style='color: green;'>✅ Columna 'estado' agregada exitosamente a la tabla 'policias'.</p>";
    }
    
    // Actualizar estados basados en la columna 'activo' existente
    echo "<p>🔄 Actualizando estados basados en la columna 'activo'...</p>";
    
    $sql_update_estados = "
        UPDATE policias 
        SET estado = CASE 
            WHEN activo = 1 THEN 'DISPONIBLE'
            WHEN activo = 0 THEN 'NO DISPONIBLE'
            ELSE 'DISPONIBLE'
        END
    ";
    
    $stmt_update = $conn->prepare($sql_update_estados);
    $stmt_update->execute();
    $affected_rows = $stmt_update->rowCount();
    
    echo "<p style='color: green;'>✅ Estados actualizados para {$affected_rows} policías.</p>";
    
    // Verificar policías con ausencias activas y marcarlos como NO DISPONIBLE
    echo "<p>🔄 Verificando policías con ausencias activas...</p>";
    
    $sql_ausencias_activas = "
        UPDATE policias p
        INNER JOIN ausencias a ON p.id = a.policia_id
        SET p.estado = 'NO DISPONIBLE'
        WHERE a.estado = 'APROBADA' 
        AND (
            (a.fecha_fin IS NULL) OR 
            (a.fecha_fin >= CURDATE())
        )
        AND a.fecha_inicio <= CURDATE()
    ";
    
    $stmt_ausencias = $conn->prepare($sql_ausencias_activas);
    $stmt_ausencias->execute();
    $ausencias_affected = $stmt_ausencias->rowCount();
    
    echo "<p style='color: green;'>✅ {$ausencias_affected} policías con ausencias activas marcados como NO DISPONIBLE.</p>";
    
    // Mostrar resumen de estados
    echo "<h3>📊 Resumen de Estados:</h3>";
    
    $sql_resumen = "
        SELECT 
            estado,
            COUNT(*) as cantidad
        FROM policias 
        WHERE activo = 1
        GROUP BY estado
    ";
    
    $stmt_resumen = $conn->prepare($sql_resumen);
    $stmt_resumen->execute();
    $resumen = $stmt_resumen->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th style='padding: 8px; background-color: #f0f0f0;'>Estado</th><th style='padding: 8px; background-color: #f0f0f0;'>Cantidad</th></tr>";
    
    foreach ($resumen as $row) {
        $color = $row['estado'] == 'DISPONIBLE' ? 'green' : 'red';
        echo "<tr>";
        echo "<td style='padding: 8px; color: {$color}; font-weight: bold;'>{$row['estado']}</td>";
        echo "<td style='padding: 8px; text-align: center;'>{$row['cantidad']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<p style='color: green; font-weight: bold;'>🎉 ¡Actualización completada exitosamente!</p>";
    echo "<p><strong>Cambios realizados:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Columna 'estado' agregada a la tabla 'policias'</li>";
    echo "<li>✅ Estados inicializados basados en la columna 'activo'</li>";
    echo "<li>✅ Policías con ausencias activas marcados como NO DISPONIBLE</li>";
    echo "<li>✅ Sistema listo para manejar estados de disponibilidad</li>";
    echo "</ul>";
    
    echo "<p style='margin-top: 20px;'><a href='admin/index.php' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🏠 Volver al Panel de Administración</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error al actualizar la base de datos: " . $e->getMessage() . "</p>";
    echo "<p>Por favor, verifica la conexión a la base de datos y vuelve a intentar.</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error general: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualización de Base de Datos - Sistema RH</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h2, h3 {
            color: #333;
        }
        p {
            line-height: 1.6;
        }
        ul {
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        table {
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- El contenido PHP se muestra arriba -->
</body>
</html>