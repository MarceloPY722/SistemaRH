<?php
session_start();
require_once '../../../cnx/db_connect.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Verificar permisos (admin o superadmin)
if ($_SESSION['rol'] !== 'ADMIN' && $_SESSION['rol'] !== 'SUPERADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permisos']);
    exit;
}

header('Content-Type: application/json');

try {
    // Obtener el año actual
    $anio_actual = date('Y');
    
    // Buscar el último número de orden para el año actual
    $stmt = $conn->prepare("
        SELECT orden_dia 
        FROM guardias_generadas 
        WHERE orden_dia LIKE ?
        ORDER BY CAST(SUBSTRING_INDEX(orden_dia, '/', 1) AS UNSIGNED) DESC 
        LIMIT 1
    ");
    
    $patron_anio = "%/$anio_actual";
    $stmt->execute([$patron_anio]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $proximo_numero = 1; // Por defecto empezar en 1
    
    if ($resultado && $resultado['orden_dia']) {
        // Extraer el número de la orden (parte antes del /)
        $partes = explode('/', $resultado['orden_dia']);
        if (count($partes) >= 2 && is_numeric($partes[0])) {
            $ultimo_numero = intval($partes[0]);
            $proximo_numero = $ultimo_numero + 1;
        }
    }
    
    // Formatear el próximo número de orden
    $proximo_orden = $proximo_numero . '/' . $anio_actual;
    
    echo json_encode([
        'success' => true,
        'proximo_orden' => $proximo_orden,
        'numero' => $proximo_numero,
        'anio' => $anio_actual
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error del servidor',
        'message' => $e->getMessage()
    ]);
}
?>