<?php
// Configuración para sistema de guardias

class ConfigGuardias {
    // Días de descanso después de una guardia
    const DIAS_DESCANSO_POST_GUARDIA = 7;
    
    // Días de descanso para guardias especiales
    const DIAS_DESCANSO_JEFE_SERVICIO = 10;
    const DIAS_DESCANSO_OFICIAL_GUARDIA = 5;
    const DIAS_DESCANSO_JEFE_CUARTEL = 8;
    
    // Obtener días de descanso según el tipo de guardia
    public static function obtenerDiasDescanso($lugar_guardia) {
        switch(strtoupper($lugar_guardia)) {
            case 'JEFE DE SERVICIO':
                return self::DIAS_DESCANSO_JEFE_SERVICIO;
            case 'OFICIAL DE GUARDIA':
                return self::DIAS_DESCANSO_OFICIAL_GUARDIA;
            case 'JEFE DE CUARTEL':
                return self::DIAS_DESCANSO_JEFE_CUARTEL;
            default:
                return self::DIAS_DESCANSO_POST_GUARDIA;
        }
    }
    
    // Función para crear ausencia automática
    public static function crearAusenciaPostGuardia($conn, $policia_id, $lugar_guardia, $fecha_guardia, $usuario_id) {
        try {
            // Obtener días de descanso según el lugar
            $dias_descanso = self::obtenerDiasDescanso($lugar_guardia);
            
            // Calcular fechas
            $fecha_inicio = date('Y-m-d', strtotime($fecha_guardia . ' +1 day')); // Inicia al día siguiente
            $fecha_fin = date('Y-m-d', strtotime($fecha_inicio . " +$dias_descanso days"));
            
            // Obtener el ID del tipo de ausencia
            $tipo_query = $conn->query("SELECT id FROM tipos_ausencias WHERE nombre = 'Descanso Post-Guardia'");
            if ($tipo_query && $tipo_query->num_rows > 0) {
                $tipo_ausencia_id = $tipo_query->fetch_assoc()['id'];
                
                // Verificar si ya existe una ausencia para este período
                $verificar = $conn->prepare("
                    SELECT COUNT(*) as count FROM ausencias 
                    WHERE policia_id = ? 
                    AND tipo_ausencia_id = ? 
                    AND fecha_inicio = ?
                ");
                $verificar->bind_param("iis", $policia_id, $tipo_ausencia_id, $fecha_inicio);
                $verificar->execute();
                $existe = $verificar->get_result()->fetch_assoc()['count'];
                
                if ($existe == 0) {
                    // Insertar ausencia automática
                    $stmt_ausencia = $conn->prepare("
                        INSERT INTO ausencias (policia_id, tipo_ausencia_id, fecha_inicio, fecha_fin, descripcion, estado, aprobado_por) 
                        VALUES (?, ?, ?, ?, ?, 'APROBADA', ?)
                    ");
                    $descripcion = "Descanso automático post-guardia del $fecha_guardia en $lugar_guardia";
                    $stmt_ausencia->bind_param("iisssi", 
                        $policia_id, 
                        $tipo_ausencia_id, 
                        $fecha_inicio, 
                        $fecha_fin, 
                        $descripcion,
                        $usuario_id
                    );
                    
                    if ($stmt_ausencia->execute()) {
                        return true;
                    }
                }
            }
            return false;
        } catch (Exception $e) {
            error_log("Error creando ausencia post-guardia: " . $e->getMessage());
            return false;
        }
    }
    
    // Función para limpiar ausencias vencidas (opcional)
    public static function limpiarAusenciasVencidas($conn) {
        $sql = "UPDATE ausencias SET estado = 'COMPLETADA' 
                WHERE tipo_ausencia_id = (SELECT id FROM tipos_ausencias WHERE nombre = 'Descanso Post-Guardia') 
                AND estado = 'APROBADA' 
                AND fecha_fin < CURDATE()";
        return $conn->query($sql);
    }
    
    // Función para obtener estadísticas de ausencias
    public static function obtenerEstadisticasDescanso($conn, $fecha_inicio = null, $fecha_fin = null) {
        $where_fecha = "";
        if ($fecha_inicio && $fecha_fin) {
            $where_fecha = "AND a.fecha_inicio BETWEEN '$fecha_inicio' AND '$fecha_fin'";
        }
        
        $sql = "SELECT 
                    COUNT(*) as total_ausencias,
                    AVG(DATEDIFF(a.fecha_fin, a.fecha_inicio)) as promedio_dias,
                    lg.nombre as lugar_guardia
                FROM ausencias a
                JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id
                JOIN policias p ON a.policia_id = p.id
                JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
                WHERE ta.nombre = 'Descanso Post-Guardia'
                AND a.estado = 'APROBADA'
                $where_fecha
                GROUP BY lg.id, lg.nombre
                ORDER BY total_ausencias DESC";
        
        return $conn->query($sql);
    }
}
?>