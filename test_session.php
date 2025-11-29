<?php
session_start();
echo "Session Test:\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Name: " . session_name() . "\n";
echo "Cookie Domain: " . ini_get('session.cookie_domain') . "\n";
echo "Cookie Path: " . ini_get('session.cookie_path') . "\n";
echo "Cookie Secure: " . ini_get('session.cookie_secure') . "\n";
echo "Cookie HttpOnly: " . ini_get('session.cookie_httponly') . "\n";
echo "Server Name: " . $_SERVER['SERVER_NAME'] . "\n";
echo "HTTP Host: " . $_SERVER['HTTP_HOST'] . "\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";

if (isset($_SESSION['usuario_id'])) {
    echo "User ID: " . $_SESSION['usuario_id'] . "\n";
    echo "User Name: " . $_SESSION['nombre_usuario'] . "\n";
} else {
    echo "No session data found\n";
}
?>