<?php
session_start();
require_once '../../../cnx/db_connect.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

// Verificar permisos
if ($_SESSION['rol'] !== 'ADMIN' && $_SESSION['rol'] !== 'SUPERADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permisos']);
    exit();
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Obtener datos JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit();
}

$detalle_id = $input['detalle_id'] ?? null;
$asistio = isset($input['asistio']) ? (int)$input['asistio'] : 1;
$hora_llegada = $input['hora_llegada'] ?? null;
$hora_salida = $input['hora_salida'] ?? null;
$observaciones = $input['observaciones'] ?? null;

if (!$detalle_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de detalle requerido']);
    exit();
}

try {
    // Verificar si ya existe un registro de asistencia
    $stmt_check = $conn->prepare("SELECT id FROM guardias_asistencia WHERE guardia_generada_detalle_id = ?");
    $stmt_check->execute([$detalle_id]);
    $existe = $stmt_check->fetch();

    if ($existe) {
        // Actualizar registro existente
        $stmt = $conn->prepare("
            UPDATE guardias_asistencia 
            SET asistio = ?, hora_llegada = ?, hora_salida = ?, observaciones = ?, registrado_por = ?
            WHERE guardia_generada_detalle_id = ?
        ");
        $stmt->execute([$asistio, $hora_llegada, $hora_salida, $observaciones, $_SESSION['usuario_id'], $detalle_id]);
    } else {
        // Crear nuevo registro
        $stmt = $conn->prepare("
            INSERT INTO guardias_asistencia (guardia_generada_detalle_id, asistio, hora_llegada, hora_salida, observaciones, registrado_por)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$detalle_id, $asistio, $hora_llegada, $hora_salida, $observaciones, $_SESSION['usuario_id']]);
    }

    echo json_encode(['success' => true, 'message' => 'Asistencia actualizada correctamente']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>