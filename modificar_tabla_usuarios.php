<?php
require_once 'cnx/db_connect.php';

try {
    // Modificar la tabla usuarios para agregar el rol SUPERADMIN
    $sql = "ALTER TABLE usuarios MODIFY COLUMN rol ENUM('ADMIN','SUPERADMIN') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT 'ADMIN';";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    echo "✅ Tabla usuarios modificada exitosamente. Rol SUPERADMIN agregado.\n";
    
    // Crear usuario superadmin
    $username = 'superadmin';
    $password = 'Niki722!';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $nombre_completo = 'Super Administrador';
    $email = 'superadmin@sistemarh.com';
    
    $sql_insert = "INSERT INTO usuarios (nombre_usuario, contraseña, nombre_completo, email, rol, activo) 
                   VALUES (:username, :password, :nombre_completo, :email, 'SUPERADMIN', 1)";
    
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bindParam(':username', $username);
    $stmt_insert->bindParam(':password', $hashed_password);
    $stmt_insert->bindParam(':nombre_completo', $nombre_completo);
    $stmt_insert->bindParam(':email', $email);
    
    if ($stmt_insert->execute()) {
        echo "✅ Usuario superadmin creado exitosamente.\n";
        echo "Usuario: superadmin\n";
        echo "Contraseña: Niki722!\n";
    } else {
        echo "❌ Error al crear usuario superadmin.\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>