<?php
session_start();
require_once '../cnx/db_connect.php';
if (isset($_SESSION['usuario_id']) && function_exists('auditoriaLogout')) {
    auditoriaLogout($_SESSION['usuario_id']);
}
session_destroy();
header("Location: ../index.php");
exit();
?>