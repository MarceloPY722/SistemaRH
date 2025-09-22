<?php
// Desactivar la visualización de errores para evitar HTML en respuesta JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Establecer header JSON desde el inicio
header('Content-Type: application/json');

session_start();
require_once '../../cnx/db_connect.php';
require_once 'generar_guardia.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener datos del POST
$fecha_servicio = $_POST['fecha_servicio'] ?? '';
$orden_dia = $_POST['orden_dia'] ?? '';

if (empty($fecha_servicio) || empty($orden_dia)) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan datos requeridos']);
    exit;
}

try {
    $generador = new GeneradorGuardias($conn);
    
    // Obtener vista previa de la asignación
    $preview = $generador->obtenerVistaPrevia($fecha_servicio, $orden_dia);
    
    echo json_encode([
        'success' => true,
        'data' => $preview
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error al obtener vista previa: ' . $e->getMessage()
    ]);
}
?>