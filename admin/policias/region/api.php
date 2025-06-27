<?php
require_once '../../../cnx/db_connect.php';

header('Content-Type: application/json');

// Verificar método de solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener datos JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

$action = $input['action'];
$response = ['success' => false];

try {
    switch ($action) {
        case 'get_all':
            $query = "SELECT r.*, 
                      (SELECT COUNT(*) FROM policias p WHERE p.region_id = r.id) as policias_count 
                      FROM regiones r ORDER BY r.nombre";
            $result = $conn->query($query);
            
            $regiones = [];
            while ($row = $result->fetch_assoc()) {
                $regiones[] = $row;
            }
            
            $response['success'] = true;
            $response['data'] = $regiones;
            break;
            
        case 'get_by_id':
            if (!isset($input['id']) || !is_numeric($input['id'])) {
                throw new Exception('ID inválido');
            }
            
            $stmt = $conn->prepare("SELECT * FROM regiones WHERE id = ?");
            $stmt->bind_param("i", $input['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $response['success'] = true;
                $response['data'] = $row;
            } else {
                throw new Exception('Región no encontrada');
            }
            $stmt->close();
            break;
            
        case 'create':
            if (!isset($input['nombre']) || empty(trim($input['nombre']))) {
                throw new Exception('El nombre es obligatorio');
            }
            
            $nombre = trim($input['nombre']);
            $descripcion = isset($input['descripcion']) ? trim($input['descripcion']) : '';
            $activo = isset($input['activo']) ? (bool)$input['activo'] : true;
            
            $stmt = $conn->prepare("INSERT INTO regiones (nombre, descripcion, activo) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $nombre, $descripcion, $activo);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Región creada exitosamente';
                $response['id'] = $conn->insert_id;
            } else {
                throw new Exception('Error al crear la región');
            }
            $stmt->close();
            break;
            
        case 'update':
            if (!isset($input['id']) || !is_numeric($input['id'])) {
                throw new Exception('ID inválido');
            }
            if (!isset($input['nombre']) || empty(trim($input['nombre']))) {
                throw new Exception('El nombre es obligatorio');
            }
            
            $id = intval($input['id']);
            $nombre = trim($input['nombre']);
            $descripcion = isset($input['descripcion']) ? trim($input['descripcion']) : '';
            $activo = isset($input['activo']) ? (bool)$input['activo'] : true;
            
            $stmt = $conn->prepare("UPDATE regiones SET nombre = ?, descripcion = ?, activo = ? WHERE id = ?");
            $stmt->bind_param("ssii", $nombre, $descripcion, $activo, $id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Región actualizada exitosamente';
            } else {
                throw new Exception('Error al actualizar la región');
            }
            $stmt->close();
            break;
            
        case 'delete':
            if (!isset($input['id']) || !is_numeric($input['id'])) {
                throw new Exception('ID inválido');
            }
            
            $id = intval($input['id']);
            
            // Verificar si la región está siendo usada
            $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM policias WHERE region_id = ?");
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                throw new Exception('No se puede eliminar la región porque está siendo utilizada por ' . $row['count'] . ' policía(s)');
            }
            
            $stmt = $conn->prepare("DELETE FROM regiones WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Región eliminada exitosamente';
            } else {
                throw new Exception('Error al eliminar la región');
            }
            
            $check_stmt->close();
            $stmt->close();
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>