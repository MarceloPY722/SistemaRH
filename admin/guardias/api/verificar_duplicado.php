<?php
session_start();
require_once '../../../cnx/db_connect.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

// Verificar permisos de administrador
if ($_SESSION['rol'] !== 'ADMIN' && $_SESSION['rol'] !== 'SUPERADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permisos']);
    exit();
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Obtener datos del POST
$fecha_guardia = $_POST['fecha_guardia'] ?? '';
$orden_dia = $_POST['orden_dia'] ?? '';

// Validar campos requeridos
if (empty($fecha_guardia) && empty($orden_dia)) {
    echo json_encode(['error' => 'Campos requeridos faltantes']);
    exit();
}

try {
    $duplicados = [];
    $mensajes = [];
    
    // Verificar duplicado por fecha si se proporciona
    if (!empty($fecha_guardia)) {
        $query_fecha = "SELECT id, orden_dia FROM guardias_generadas WHERE fecha_guardia = ?";
        $stmt_fecha = $conn->prepare($query_fecha);
        $stmt_fecha->execute([$fecha_guardia]);
        $duplicado_fecha = $stmt_fecha->fetch(PDO::FETCH_ASSOC);
        
        if ($duplicado_fecha) {
            $duplicados['fecha'] = true;
            $mensajes[] = 'La fecha ' . date('d/m/Y', strtotime($fecha_guardia)) . ' ya tiene una guardia asignada (Orden: ' . htmlspecialchars($duplicado_fecha['orden_dia']) . ')';
        } else {
            $duplicados['fecha'] = false;
        }
    }
    
    // Verificar duplicado por orden del día si se proporciona
    if (!empty($orden_dia)) {
        $query_orden = "SELECT id, fecha_guardia FROM guardias_generadas WHERE orden_dia = ?";
        $stmt_orden = $conn->prepare($query_orden);
        $stmt_orden->execute([$orden_dia]);
        $duplicado_orden = $stmt_orden->fetch(PDO::FETCH_ASSOC);
        
        if ($duplicado_orden) {
            $duplicados['orden'] = true;
            $mensajes[] = 'El orden del día "' . htmlspecialchars($orden_dia) . '" ya está asignado a la fecha ' . date('d/m/Y', strtotime($duplicado_orden['fecha_guardia']));
        } else {
            $duplicados['orden'] = false;
        }
    }
    
    // Verificar duplicado exacto (ambos campos) si se proporcionan ambos
    $duplicado_exacto = false;
    if (!empty($fecha_guardia) && !empty($orden_dia)) {
        $query_exacto = "SELECT id FROM guardias_generadas WHERE fecha_guardia = ? AND orden_dia = ?";
        $stmt_exacto = $conn->prepare($query_exacto);
        $stmt_exacto->execute([$fecha_guardia, $orden_dia]);
        $duplicado_exacto = $stmt_exacto->fetch() ? true : false;
    }
    
    // Determinar si hay algún duplicado
    $hay_duplicado = ($duplicados['fecha'] ?? false) || ($duplicados['orden'] ?? false) || $duplicado_exacto;
    
    echo json_encode([
        'duplicado' => $hay_duplicado,
        'duplicado_fecha' => $duplicados['fecha'] ?? false,
        'duplicado_orden' => $duplicados['orden'] ?? false,
        'duplicado_exacto' => $duplicado_exacto,
        'mensajes' => $mensajes,
        'mensaje' => $hay_duplicado ? implode(' | ', $mensajes) : 'Disponible para generar'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>