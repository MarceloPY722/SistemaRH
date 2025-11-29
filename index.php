<?php
require_once 'session_config.php'; // Ngrok-compatible session configuration
require_once 'cnx/db_connect.php';

$error = "";

if ($_POST) {
    $nombre_usuario = trim($_POST['nombre_usuario']);
    $contraseña = trim($_POST['contraseña']);
    
    if (!empty($nombre_usuario) && !empty($contraseña)) {
        $sql = "SELECT id, nombre_usuario, contraseña, nombre_completo, rol, activo FROM usuarios WHERE nombre_usuario = ? AND activo = 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$nombre_usuario]);
        
        if ($stmt->rowCount() == 1) {
            $usuario = $stmt->fetch();
            
            if (password_verify($contraseña, $usuario['contraseña'])) {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['nombre_usuario'] = $usuario['nombre_usuario'];
                $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
                $_SESSION['rol'] = $usuario['rol'];
                if (function_exists('auditoriaLogin')) {
                    auditoriaLogin($usuario['id'], true);
                }
                
                // Redirigir según el rol
                if ($usuario['rol'] == 'ADMIN') {
                    header("Location: admin/index.php");
                } else {
                    header("Location: admin/index.php"); // Por ahora todos van al mismo lugar
                }
                exit();
            } else {
                $error = "Credenciales incorrectas";
                if (function_exists('auditoriaLogin')) {
                    auditoriaLogin($usuario['id'], false);
                }
            }
        } else {
            $error = "Usuario no encontrado";
            if (function_exists('auditoriaLogin')) {
                auditoriaLogin(null, false);
            }
        }
        $stmt = null;
    } else {
        $error = "Por favor complete todos los campos";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema RH - Policía Nacional de Paraguay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
       
            :root {
                --primary-color: #104c75;
                --secondary-color: #0d3d5c;
                --accent-color: #1a5a8a;
                --light-bg: #f8f9fa;
                --text-primary: #2c3e50;
                --text-secondary: #6c757d;
                --success-color: #28a745;
                --danger-color: #dc3545;
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Inter', sans-serif;
                background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .login-container {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                border-radius: 20px;
                box-shadow: 0 25px 50px rgba(16, 76, 117, 0.2);
                width: 100%;
                max-width: 400px;
                padding: 40px 30px;
                border: 1px solid rgba(16, 76, 117, 0.1);
            }
            
            .logo-section {
                text-align: center;
                margin-bottom: 30px;
            }
            
            .logo-icon {
                width: 60px;
                height: 60px;
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                border-radius: 15px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 15px;
                box-shadow: 0 10px 25px rgba(16, 76, 117, 0.3);
            }
            
            .logo-icon i {
                font-size: 24px;
                color: white;
            }
            
            .logo-title {
                font-size: 24px;
                font-weight: 600;
                color: var(--primary-color);
                margin-bottom: 5px;
            }
            
            .logo-subtitle {
                font-size: 14px;
                color: var(--text-secondary);
                font-weight: 400;
            }
            
            .form-group {
                margin-bottom: 20px;
                position: relative;
            }
            
            .form-label {
                display: block;
                font-size: 14px;
                font-weight: 500;
                color: var(--text-primary);
                margin-bottom: 8px;
            }
            
            .form-control {
                width: 100%;
                padding: 12px 16px;
                border: 2px solid #e2e8f0;
                border-radius: 12px;
                font-size: 16px;
                transition: all 0.3s ease;
                background: var(--light-bg);
                color: var(--text-primary);
            }
            
            .form-control:focus {
                outline: none;
                border-color: var(--primary-color);
                background: white;
                box-shadow: 0 0 0 3px rgba(16, 76, 117, 0.1);
            }
            
            .form-control::placeholder {
                color: var(--text-secondary);
            }
            
            .btn-login {
                width: 100%;
                padding: 14px;
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                border: none;
                border-radius: 12px;
                color: white;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                margin-top: 10px;
            }
            
            .btn-login:hover {
                background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
                transform: translateY(-2px);
                box-shadow: 0 15px 35px rgba(16, 76, 117, 0.4);
            }
            
            .btn-login:active {
                transform: translateY(0);
            }
            
            .alert {
                background: #fed7d7;
                color: var(--danger-color);
                padding: 12px 16px;
                border-radius: 10px;
                font-size: 14px;
                margin-bottom: 20px;
                border: 1px solid #feb2b2;
            }
            
            .footer-text {
                text-align: center;
                margin-top: 25px;
                font-size: 12px;
                color: var(--text-secondary);
            }
            
            /* Responsive */
            @media (max-width: 480px) {
                .login-container {
                    padding: 30px 20px;
                    margin: 10px;
                }
                
                .logo-title {
                    font-size: 20px;
                }
                
                .form-control {
                    font-size: 16px;
                }
            }
            
            /* Animaciones */
            .login-container {
                animation: slideUp 0.6s ease-out;
            }
            
            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .form-group {
                animation: fadeIn 0.8s ease-out;
            }
            
            @keyframes fadeIn {
                from {
                    opacity: 0;
                }
                to {
                    opacity: 1;
                }
            }
        
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1 class="logo-title">Sistema RH</h1>
            <p class="logo-subtitle">Policía Nacional de Paraguay</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="nombre_usuario" class="form-label">Usuario</label>
                <input type="text" 
                       class="form-control" 
                       id="nombre_usuario" 
                       name="nombre_usuario" 
                       placeholder="Ingrese su usuario"
                       value="<?php echo isset($_POST['nombre_usuario']) ? htmlspecialchars($_POST['nombre_usuario']) : ''; ?>"
                       required>
            </div>
            
            <div class="form-group">
                <label for="contraseña" class="form-label">Contraseña</label>
                <input type="password" 
                       class="form-control" 
                       id="contraseña" 
                       name="contraseña" 
                       placeholder="Ingrese su contraseña"
                       required>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
            </button>
        </form>
        
        <div class="footer-text">
            Sistema de gestión de recursos humanos
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>