<?php
$servername = "localhost";
$username = "root"; // Cambiar por tu usuario de base de datos
$password = ""; // Cambiar por tu contraseña de base de datos
$dbname = "sistema_rh_policia"; // Nombre de tu base de datos

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Configurar charset
$conn->set_charset("utf8");
?>