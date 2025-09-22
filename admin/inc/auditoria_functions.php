<?php
// Funciones de auditoría del sistema

/**
 * Registrar acción en el sistema de auditoría
 * @param string $accion Descripción de la acción realizada
 * @param string|null $tabla_afectada Tabla afectada por la acción
 * @param int|null $registro_id ID del registro afectado
 * @param mixed $datos_anteriores Datos antes de la modificación
 * @param mixed $datos_nuevos Datos después de la modificación
 * @return bool True si se registró correctamente
 */
function registrarAuditoria($accion, $tabla_afectada = null, $registro_id = null, $datos_anteriores = null, $datos_nuevos = null) {
    global $conn;
    
    // Convertir arrays/objetos a JSON para almacenamiento
    if (is_array($datos_anteriores) || is_object($datos_anteriores)) {
        $datos_anteriores = json_encode($datos_anteriores, JSON_UNESCAPED_UNICODE);
    }
    
    if (is_array($datos_nuevos) || is_object($datos_nuevos)) {
        $datos_nuevos = json_encode($datos_nuevos, JSON_UNESCAPED_UNICODE);
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $usuario_id = $_SESSION['usuario_id'] ?? null;
    
    try {
        // Crear tabla de auditoría si no existe
        $conn->exec("CREATE TABLE IF NOT EXISTS auditoria_sistema (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT,
            accion VARCHAR(255) NOT NULL,
            tabla_afectada VARCHAR(100),
            registro_id INT,
            datos_anteriores TEXT,
            datos_nuevos TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
        )");
        
        $stmt = $conn->prepare("INSERT INTO auditoria_sistema (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, user_agent) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        return $stmt->execute([
            $usuario_id,
            $accion,
            $tabla_afectada,
            $registro_id,
            $datos_anteriores,
            $datos_nuevos,
            $ip_address,
            $user_agent
        ]);
        
    } catch (Exception $e) {
        // Log error pero no interrumpir el flujo principal
        error_log("Error en auditoría: " . $e->getMessage());
        return false;
    }
}

/**
 * Registrar login de usuario
 */
function auditoriaLogin($usuario_id, $exitoso = true) {
    $accion = $exitoso ? "Inicio de sesión exitoso" : "Intento de inicio de sesión fallido";
    return registrarAuditoria($accion, 'usuarios', $usuario_id);
}

/**
 * Registrar logout de usuario
 */
function auditoriaLogout($usuario_id) {
    return registrarAuditoria("Cierre de sesión", 'usuarios', $usuario_id);
}

/**
 * Registrar creación de registro
 */
function auditoriaCrear($tabla, $registro_id, $datos_nuevos) {
    return registrarAuditoria("Creación de registro", $tabla, $registro_id, null, $datos_nuevos);
}

/**
 * Registrar actualización de registro
 */
function auditoriaActualizar($tabla, $registro_id, $datos_anteriores, $datos_nuevos) {
    return registrarAuditoria("Actualización de registro", $tabla, $registro_id, $datos_anteriores, $datos_nuevos);
}

/**
 * Registrar eliminación de registro
 */
function auditoriaEliminar($tabla, $registro_id, $datos_anteriores) {
    return registrarAuditoria("Eliminación de registro", $tabla, $registro_id, $datos_anteriores, null);
}

/**
 * Registrar acceso a módulo
 */
function auditoriaAccesoModulo($modulo) {
    return registrarAuditoria("Acceso a módulo: " . $modulo);
}

/**
 * Registrar descarga de archivo
 */
function auditoriaDescarga($archivo) {
    return registrarAuditoria("Descarga de archivo: " . $archivo);
}

/**
 * Registrar backup de base de datos
 */
function auditoriaBackup($archivo_backup) {
    return registrarAuditoria("Backup de base de datos", null, null, null, $archivo_backup);
}

/**
 * Registrar reset del sistema
 */
function auditoriaReset($tipo_reset) {
    return registrarAuditoria("Reset del sistema: " . $tipo_reset);
}

/**
 * Obtener estadísticas de auditoría
 */
function obtenerEstadisticasAuditoria($filtros = []) {
    global $conn;
    
    $query = "SELECT 
                COUNT(*) as total_registros,
                COUNT(DISTINCT usuario_id) as usuarios_unicos,
                COUNT(DISTINCT accion) as acciones_unicas,
                MIN(creado_en) as fecha_minima,
                MAX(creado_en) as fecha_maxima
              FROM auditoria_sistema";
    
    return $conn->query($query)->fetch();
}

/**
 * Obtener acciones más frecuentes
 */
function obtenerAccionesFrecuentes($limite = 10) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT accion, COUNT(*) as cantidad 
                           FROM auditoria_sistema 
                           GROUP BY accion 
                           ORDER BY cantidad DESC 
                           LIMIT ?");
    $stmt->execute([$limite]);
    
    return $stmt->fetchAll();
}

/**
 * Obtener actividad por usuario
 */
function obtenerActividadPorUsuario($limite = 10) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT u.nombre_usuario, u.nombre_completo, COUNT(a.id) as total_acciones
                           FROM auditoria_sistema a
                           JOIN usuarios u ON a.usuario_id = u.id
                           GROUP BY a.usuario_id
                           ORDER BY total_acciones DESC
                           LIMIT ?");
    $stmt->execute([$limite]);
    
    return $stmt->fetchAll();
}
?>