<?php
/**
 * API para obtener sugerencias de autocompletado
 * Para campos de observaciones y comisionamiento
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../../cnx/db_connect.php';

// Verificar que se recibió el parámetro necesario
if (!isset($_GET['tipo']) || !isset($_GET['query'])) {
    echo json_encode(['error' => 'Parámetros requeridos: tipo y query']);
    exit;
}

$tipo = $_GET['tipo'];
$query = trim($_GET['query']);
$sugerencias = [];

// Validar que la consulta tenga al menos 2 caracteres
if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    if ($tipo === 'observaciones') {
        // Sugerencias predefinidas para observaciones
        $observaciones_predefinidas = [
            'VENTANILLA' => 'Personal asignado a ventanilla de atención al público',
            'GRUPO DOMINGO' => 'Personal que solo trabaja los domingos',
            'TELEFONISTA' => 'Personal asignado a central telefónica',
            'DATA CENTER' => 'Personal asignado a centro de datos',
            'DACTILOSCOPIA' => 'Personal especializado en dactiloscopia',
            'SANIDAD' => 'Personal del área de sanidad',
            'CONDUCTOR' => 'Personal autorizado como conductor',
            'JEFE DE CUARTEL' => 'Personal habilitado como jefe de cuartel',
            'OFICIAL DE GUARDIA' => 'Personal habilitado como oficial de guardia',
            'TENIDA' => 'Personal asignado a tenida',
            'COMISIONES ESPECIALES' => 'Personal en comisiones especiales',
            'DISPONIBLE' => 'Personal disponible para cualquier asignación',
            'LICENCIA MÉDICA' => 'Personal con licencia médica temporal',
            'VACACIONES' => 'Personal en período de vacaciones',
            'CURSO DE CAPACITACIÓN' => 'Personal en curso de capacitación',
            'SERVICIO EXTERNO' => 'Personal en servicio externo'
        ];
        
        // Buscar coincidencias en observaciones predefinidas
        foreach ($observaciones_predefinidas as $clave => $descripcion) {
            if (stripos($clave, $query) !== false || stripos($descripcion, $query) !== false) {
                $sugerencias[] = [
                    'valor' => $clave,
                    'descripcion' => $descripcion,
                    'tipo' => 'predefinida'
                ];
            }
        }
        
        // Buscar en observaciones existentes en la base de datos
        $sql = "SELECT DISTINCT observaciones 
                FROM policias 
                WHERE observaciones IS NOT NULL 
                AND observaciones != '' 
                AND observaciones LIKE ? 
                AND activo = 1
                ORDER BY observaciones
                LIMIT 10";
        
        $stmt = $conn->prepare($sql);
        $search_term = '%' . $query . '%';
        $stmt->execute([$search_term]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Evitar duplicados con las predefinidas
            $existe = false;
            foreach ($sugerencias as $sug) {
                if (strtoupper($sug['valor']) === strtoupper($row['observaciones'])) {
                    $existe = true;
                    break;
                }
            }
            
            if (!$existe) {
                $sugerencias[] = [
                    'valor' => $row['observaciones'],
                    'descripcion' => 'Observación existente en el sistema',
                    'tipo' => 'existente'
                ];
            }
        }
        
    } elseif ($tipo === 'comisionamiento') {
        // Sugerencias predefinidas para comisionamiento
        $comisionamientos_predefinidos = [
            'VENTANILLA' => 'Atención al público en ventanilla',
            'SECRETARÍA' => 'Tareas administrativas de secretaría',
            'ARCHIVO' => 'Gestión y mantenimiento de archivos',
            'MESA DE ENTRADAS' => 'Recepción y registro de documentos',
            'INFORMÁTICA' => 'Soporte técnico y sistemas',
            'RECURSOS HUMANOS' => 'Gestión de personal',
            'CONTADURÍA' => 'Gestión contable y financiera',
            'LEGAL' => 'Asesoramiento jurídico',
            'COMUNICACIONES' => 'Manejo de comunicaciones institucionales',
            'LOGÍSTICA' => 'Gestión de recursos y suministros',
            'CAPACITACIÓN' => 'Formación y entrenamiento de personal',
            'PROTOCOLO' => 'Ceremonial y protocolo institucional',
            'PRENSA' => 'Relaciones con medios de comunicación',
            'ESTADÍSTICAS' => 'Análisis y procesamiento de datos',
            'AUDITORÍA' => 'Control y auditoría interna'
        ];
        
        // Buscar coincidencias en comisionamientos predefinidos
        foreach ($comisionamientos_predefinidos as $clave => $descripcion) {
            if (stripos($clave, $query) !== false || stripos($descripcion, $query) !== false) {
                $sugerencias[] = [
                    'valor' => $clave,
                    'descripcion' => $descripcion,
                    'tipo' => 'predefinida'
                ];
            }
        }
        
        // Buscar en comisionamientos existentes en la base de datos
        $sql = "SELECT DISTINCT comisionamiento 
                FROM policias 
                WHERE comisionamiento IS NOT NULL 
                AND comisionamiento != '' 
                AND comisionamiento LIKE ? 
                AND activo = 1
                ORDER BY comisionamiento
                LIMIT 10";
        
        $stmt = $conn->prepare($sql);
        $search_term = '%' . $query . '%';
        $stmt->execute([$search_term]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Evitar duplicados con las predefinidas
            $existe = false;
            foreach ($sugerencias as $sug) {
                if (strtoupper($sug['valor']) === strtoupper($row['comisionamiento'])) {
                    $existe = true;
                    break;
                }
            }
            
            if (!$existe) {
                $sugerencias[] = [
                    'valor' => $row['comisionamiento'],
                    'descripcion' => 'Comisionamiento existente en el sistema',
                    'tipo' => 'existente'
                ];
            }
        }
    } else {
        echo json_encode(['error' => 'Tipo no válido. Use: observaciones o comisionamiento']);
        exit;
    }
    
    // Limitar resultados y ordenar por relevancia
    usort($sugerencias, function($a, $b) use ($query) {
        // Priorizar coincidencias exactas al inicio
        $a_starts = stripos($a['valor'], $query) === 0 ? 1 : 0;
        $b_starts = stripos($b['valor'], $query) === 0 ? 1 : 0;
        
        if ($a_starts !== $b_starts) {
            return $b_starts - $a_starts;
        }
        
        // Luego por tipo (predefinidas primero)
        if ($a['tipo'] !== $b['tipo']) {
            return $a['tipo'] === 'predefinida' ? -1 : 1;
        }
        
        // Finalmente alfabético
        return strcasecmp($a['valor'], $b['valor']);
    });
    
    // Limitar a 8 resultados
    $sugerencias = array_slice($sugerencias, 0, 8);
    
    echo json_encode($sugerencias);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Error interno del servidor']);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>