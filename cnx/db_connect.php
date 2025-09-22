<?php
$servername = "localhost";
$username = "root"; // Cambiar por tu usuario de base de datos
$password = ""; // Cambiar por tu contraseña de base de datos
$dbname = "sistema_rh_policia"; // Nombre de tu base de datos

try {
    // Crear conexión PDO
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Incluir funciones de auditoría automáticamente
if (file_exists(__DIR__ . '/../admin/inc/auditoria_functions.php')) {
    require_once __DIR__ . '/../admin/inc/auditoria_functions.php';
}
?>