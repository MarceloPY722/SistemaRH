<?php
session_start();
require_once '../../cnx/db_connect.php';

header('Content-Type: application/json');

// Log para debugging
error_log("=== Inicio obtener_personal_recomendado ===");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    error_log("Input recibido: " . print_r($input, true));
    
    if (!isset($input['fecha_servicio']) || !isset($input['requisitos'])) {
        throw new Exception('Faltan parámetros requeridos');
    }
    
    $fecha_servicio = $input['fecha_servicio'];
    $requisitos = $input['requisitos'];
    
    if (empty($requisitos)) {
        echo json_encode([]);
        exit;
    }
    
    $personal_recomendado = [];
    
    foreach ($requisitos as $requisito) {
        $cantidad = (int)($requisito['cantidad'] ?? 0);
        $genero = $requisito['genero'] ?? '';
        $grado_id = (int)($requisito['grado'] ?? 0);
        $region_id = (int)($requisito['region'] ?? 0);
        
        error_log("Procesando requisito: cantidad=$cantidad, genero=$genero, grado=$grado_id, region=$region_id");
        
        if ($cantidad <= 0) continue;
        
        // Validar que se hayan especificado los requisitos mínimos
        if (empty($genero) || $grado_id <= 0 || $region_id <= 0) {
            error_log("Requisito inválido, saltando...");
            continue;
        }
        
        // Consulta corregida usando las columnas reales de la tabla policias
        $sql = "
            SELECT 
                p.id,
                p.nombre,
                p.apellido,
                CONCAT(p.nombre, ' ', p.apellido) as nombre_completo,
                p.cin,
                p.genero,
                g.nombre as grado,
                g.id as grado_id,
                r.nombre as region,
                r.id as region_id
            FROM policias p
            INNER JOIN grados g ON p.grado_id = g.id
            INNER JOIN regiones r ON p.region_id = r.id
            WHERE p.activo = 1
            AND p.genero = :genero
            AND p.grado_id = :grado_id
            AND p.region_id = :region_id
            AND p.grado_id NOT IN (
                SELECT tg.id FROM tipo_grados tg
                WHERE UPPER(tg.nombre) LIKE '%FUNCIONARIO%'
                   OR UPPER(tg.abreviatura) = 'FUNC.'
            )
            ORDER BY 
                p.apellido,
                p.nombre
            LIMIT :cantidad
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':genero', $genero, PDO::PARAM_STR);
        $stmt->bindValue(':grado_id', $grado_id, PDO::PARAM_INT);
        $stmt->bindValue(':region_id', $region_id, PDO::PARAM_INT);
        $stmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
        
        $stmt->execute();
        $resultados = $stmt->fetchAll();
        
        error_log("Encontrados: " . count($resultados) . " personas");
        
        $personal_para_requisito = [];
        foreach ($resultados as $row) {
            $personal_para_requisito[] = [
                'id' => $row['id'],
                'nombre_completo' => $row['nombre_completo'],
                'cin' => $row['cin'],
                'genero' => $row['genero'],
                'grado' => $row['grado'],
                'grado_id' => $row['grado_id'],
                'region' => $row['region'],
                'region_id' => $row['region_id'],
                'disponible' => true,
                'requisito_id' => $requisito['id']
            ];
        }
        
        $personal_recomendado[] = [
            'requisito' => $requisito,
            'personal' => $personal_para_requisito,
            'encontrados' => count($personal_para_requisito),
            'requeridos' => $cantidad
        ];
    }
    
    error_log("Total grupos: " . count($personal_recomendado));
    echo json_encode($personal_recomendado);
    
} catch (PDOException $e) {
    error_log("Error PDO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error de base de datos: ' . $e->getMessage(),
        'type' => 'database'
    ]);
} catch (Exception $e) {
    error_log("Error general: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'type' => 'general'
    ]);
}
?>
