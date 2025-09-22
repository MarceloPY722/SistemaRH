<?php
// Obtener información del usuario actual
$stmt = $conn->prepare("SELECT nombre_completo, rol FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario_actual = $stmt->fetch();
?>