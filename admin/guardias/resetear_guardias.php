<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../cnx/db_connect.php';

// Verificar si el usuario es superadmin
$es_superadmin = false;
try {
    $stmt = $conn->prepare("SELECT rol FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario && $usuario['rol'] === 'SUPERADMIN') {
        $es_superadmin = true;
    }
} catch (Exception $e) {
    // En caso de error, redirigir con mensaje
    $_SESSION['mensaje'] = "Error al verificar permisos: " . $e->getMessage();
    $_SESSION['tipo_mensaje'] = "danger";
    header("Location: ../index.php");
    exit();
}

// Si no es superadmin, redirigir
if (!$es_superadmin) {
    $_SESSION['mensaje'] = "Acceso denegado. Solo usuarios SUPERADMIN pueden realizar esta acción.";
    $_SESSION['tipo_mensaje'] = "danger";
    header("Location: ../index.php");
    exit();
}

// Procesar reset de órdenes de guardia
try {
    // Comenzar transacción
    $conn->beginTransaction();
    if (function_exists('auditoriaReset')) {
        auditoriaReset('Resetear guardias');
    }
    
    // Limpiar lista actual
    $conn->exec("DELETE FROM lista_guardias");
    if (function_exists('auditoriaEliminar')) {
        auditoriaEliminar('lista_guardias', null, [ 'accion' => 'Eliminar todos los registros' ]);
    }
    
    // Obtener policías ordenados por jerarquía y legajo (como proxy de antigüedad)
    $policias_sql = "SELECT p.id
                    FROM policias p
                    JOIN grados g ON p.grado_id = g.id
                    WHERE p.activo = TRUE
                    ORDER BY g.nivel_jerarquia ASC, p.legajo ASC, p.id ASC";
    
    // Nota: Se usa legajo como proxy de antigüedad (legajo menor = más antiguo)
    
    $result = $conn->query($policias_sql);
    $posicion = 1;
    
    // Insertar policías en nueva lista ordenada
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $stmt = $conn->prepare("INSERT INTO lista_guardias (policia_id, posicion) VALUES (?, ?)");
        $stmt->execute([$row['id'], $posicion]);
        if (function_exists('auditoriaCrear')) {
            $registro_id = $conn->lastInsertId();
            auditoriaCrear('lista_guardias', $registro_id, [
                'policia_id' => $row['id'],
                'posicion' => $posicion
            ]);
        }
        $posicion++;
    }
    
    // Confirmar transacción
    $conn->commit();
    
    // Redirigir con mensaje de éxito
    $_SESSION['mensaje'] = "Lista de guardias reorganizada exitosamente. Las posiciones han sido restablecidas.";
    $_SESSION['tipo_mensaje'] = "success";
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollBack();
    
    // Redirigir con mensaje de error
    $_SESSION['mensaje'] = "Error al reorganizar la lista de guardias: " . $e->getMessage();
    $_SESSION['tipo_mensaje'] = "danger";
}

// Redirigir de vuelta a la interfaz de generación
header("Location: generar_guardia_interface.php");
exit();
?>