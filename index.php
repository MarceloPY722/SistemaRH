<?php
session_start();
require_once 'cnx/db_connect.php';

$error = "";

if ($_POST) {
    $nombre_usuario = trim($_POST['nombre_usuario']);
    $contraseña = trim($_POST['contraseña']);
    
    if (!empty($nombre_usuario) && !empty($contraseña)) {
        $sql = "SELECT id, nombre_usuario, contraseña, nombre_completo, rol, activo FROM usuarios WHERE nombre_usuario = ? AND activo = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $nombre_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $usuario = $result->fetch_assoc();
            
            if (password_verify($contraseña, $usuario['contraseña'])) {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['nombre_usuario'] = $usuario['nombre_usuario'];
                $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
                $_SESSION['rol'] = $usuario['rol'];
                
                // Redirigir según el rol
                if ($usuario['rol'] == 'ADMIN') {
                    header("Location: admin/index.php");
                } else {
                    header("Location: admin/index.php"); // Por ahora todos van al mismo lugar
                }
                exit();
            } else {
                $error = "Credenciales incorrectas";
            }
        } else {
            $error = "Usuario no encontrado";
        }
        $stmt->close();
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
    <style>
        body {
            background: linear-gradient(135deg, #104c75 0%, #0d3d5c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px 0;
        }
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 25px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            overflow: hidden;
            backdrop-filter: blur(10px);
            margin: 20px 0;
        }
        .login-header {
            background: linear-gradient(45deg, #104c75, #0d3d5c);
            color: white;
            text-align: center;
            padding: 50px 30px;
        }
        .login-header h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .login-header h4 {
            font-size: 1.3rem;
            font-weight: 500;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        .login-header p {
            font-size: 1rem;
            opacity: 0.8;
        }
        .login-body {
            padding: 50px 40px;
        }
        .form-control {
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 18px 25px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }
        .form-control:focus {
            border-color: #104c75;
            box-shadow: 0 0 0 0.2rem rgba(16, 76, 117, 0.25);
            background: white;
        }
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .form-label i {
            margin-right: 10px;
            color: #104c75;
            width: 20px;
        }
        .btn-login {
            background: linear-gradient(45deg, #104c75, #0d3d5c);
            border: none;
            border-radius: 25px;
            padding: 18px 40px;
            font-size: 18px;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(16, 76, 117, 0.4);
            color: white;
            background: linear-gradient(45deg, #0d3d5c, #104c75);
        }
        .police-badge {
            font-size: 5rem;
            color: #f39c12;
            margin-bottom: 25px;
            text-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }
        .alert {
            border-radius: 15px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
            font-weight: 500;
        }
        .footer-info {
            background: rgba(52, 73, 94, 0.1);
            padding: 20px;
            text-align: center;
            border-radius: 10px;
            margin-top: 30px;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 10px 0;
            }
            .container {
                padding: 0 10px;
            }
            .login-header {
                padding: 35px 20px;
            }
            .login-header h2 {
                font-size: 1.6rem;
            }
            .login-header h4 {
                font-size: 1.1rem;
            }
            .login-header p {
                font-size: 0.9rem;
            }
            .login-body {
                padding: 35px 25px;
            }
            .police-badge {
                font-size: 3.5rem;
            }
            .form-control {
                padding: 15px 20px;
                font-size: 15px;
            }
            .btn-login {
                padding: 15px 30px;
                font-size: 16px;
            }
            .footer-info {
                padding: 15px;
                margin-top: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .login-header {
                padding: 25px 15px;
            }
            .login-header h2 {
                font-size: 1.4rem;
            }
            .login-header h4 {
                font-size: 1rem;
            }
            .login-body {
                padding: 25px 20px;
            }
            .police-badge {
                font-size: 3rem;
            }
            .form-control {
                padding: 12px 15px;
                font-size: 14px;
            }
            .btn-login {
                padding: 12px 25px;
                font-size: 15px;
            }
        }
        
        @media (max-width: 360px) {
            .login-header h2 {
                font-size: 1.2rem;
            }
            .police-badge {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
                <div class="login-container">
                    <div class="login-header">
                        <div class="police-badge">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h2>Sistema de Recursos Humanos</h2>
                        <h4>Policía Nacional de Paraguay</h4>
                        <p class="mb-0">Automatización de Guardias y Servicios</p>
                    </div>
                    
                    <div class="login-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-4">
                                <label for="nombre_usuario" class="form-label">
                                    <i class="fas fa-user"></i> Usuario
                                </label>
                                <input type="text" class="form-control" id="nombre_usuario" name="nombre_usuario" 
                                       placeholder="Ingrese su usuario" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="contraseña" class="form-label">
                                    <i class="fas fa-lock"></i> Contraseña
                                </label>
                                <input type="password" class="form-control" id="contraseña" name="contraseña" 
                                       placeholder="Ingrese su contraseña" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-login">
                                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                                </button>
                            </div>
                        </form>
                        
                        <div class="footer-info">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Sistema desarrollado para la gestión de recursos humanos
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>