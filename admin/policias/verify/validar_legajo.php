<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

require_once '../../../cnx/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['legajo'])) {
    $legajo = (int)trim($_POST['legajo']);
    
    if ($legajo <= 0) {
        echo json_encode(['disponible' => false, 'mensaje' => 'El legajo debe ser un número válido']);
        exit();
    }
    
    $check_legajo = $conn->prepare("SELECT id FROM policias WHERE legajo = ? AND activo = 1");
    $check_legajo->execute([$legajo]);
    
    if ($check_legajo->rowCount() > 0) {
        echo json_encode(['disponible' => false, 'mensaje' => 'Este legajo ya está en uso']);
    } else {
        echo json_encode(['disponible' => true, 'mensaje' => 'Legajo disponible']);
    }
} else {
    echo json_encode(['error' => 'Datos inválidos']);
}

$conn->close();
?>