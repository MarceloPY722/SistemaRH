<?php
require_once '../cnx/db_connect.php';

$mensaje = "";

if ($_POST) {
    $nombre_usuario = trim($_POST['nombre_usuario']);
    $contraseña = trim($_POST['contraseña']);
    $nombre_completo = trim($_POST['nombre_completo']);
    $email = trim($_POST['email']);
    $rol = $_POST['rol'];
    
    if (!empty($nombre_usuario) && !empty($contraseña) && !empty($nombre_completo)) {
        // Encriptar contraseña
        $contraseña_hash = password_hash($contraseña, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO usuarios (nombre_usuario, contraseña, nombre_completo, email, rol) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $nombre_usuario, $contraseña_hash, $nombre_completo, $email, $rol);
        
        if ($stmt->execute()) {
            $mensaje = "<div class='alert alert-success'>Usuario creado exitosamente</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al crear usuario: " . $conn->error . "</div>";
        }
        $stmt->close();
    } else {
        $mensaje = "<div class='alert alert-warning'>Por favor complete todos los campos obligatorios</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Usuario - Sistema RH Policía Nacional</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #104c75 0%, #0d3d5c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-header {
            background: linear-gradient(45deg, #104c75, #0d3d5c);
            color: white;
            text-align: center;
            padding: 40px 30px;
        }
        .btn-primary {
            background: linear-gradient(45deg, #104c75, #0d3d5c);
            border: none;
            border-radius: 25px;
        }
        .btn-primary:hover {
            background: linear-gradient(45deg, #0d3d5c, #104c75);
        }
        .form-control:focus {
            border-color: #104c75;
            box-shadow: 0 0 0 0.2rem rgba(16, 76, 117, 0.25);
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 10px 15px;
            font-size: 15px;
            transition: all 0.3s ease;
            height: auto;
        }
        .form-control:focus, .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .form-label i {
            margin-right: 6px;
            color: #3498db;
            width: 16px;
        }
        .alert {
            border-radius: 10px;
            border: none;
            padding: 10px 15px;
            margin-bottom: 15px;
            font-weight: 500;
            font-size: 14px;
        }
        .police-badge {
            font-size: 2.5rem;
            color: #f39c12;
            margin-bottom: 10px;
        }
        .mb-3 {
            margin-bottom: 15px !important;
        }
        .mb-4 {
            margin-bottom: 20px !important;
        }
        
        /* Layout en dos columnas para pantallas grandes */
        @media (min-width: 768px) {
            .form-row {
                display: flex;
                gap: 20px;
            }
            .form-col {
                flex: 1;
            }
            .card {
                max-width: 800px;
            }
        }
        
        /* Responsive Design */
        @media (max-width: 767px) {
            body {
                padding: 5px 0;
            }
            .container {
                padding: 0 10px;
            }
            .card-header {
                padding: 15px 10px;
            }
            .card-header h3 {
                font-size: 1.4rem;
            }
            .card-header p {
                font-size: 0.9rem;
            }
            .card-body {
                padding: 20px 15px;
            }
            .police-badge {
                font-size: 2rem;
            }
            .form-control, .form-select {
                padding: 8px 12px;
                font-size: 14px;
            }
            .btn-primary {
                padding: 10px 25px;
                font-size: 15px;
            }
            .mb-3 {
                margin-bottom: 12px !important;
            }
        }
        
        @media (max-width: 480px) {
            .card-header {
                padding: 12px 8px;
            }
            .card-header h3 {
                font-size: 1.2rem;
            }
            .card-body {
                padding: 15px 12px;
            }
            .police-badge {
                font-size: 1.8rem;
            }
        }
        
        /* Optimización para altura de pantalla */
        @media (max-height: 700px) {
            .card-header {
                padding: 15px 10px;
            }
            .card-body {
                padding: 20px 25px;
            }
            .police-badge {
                font-size: 2rem;
                margin-bottom: 5px;
            }
            .mb-3 {
                margin-bottom: 10px !important;
            }
            .form-control, .form-select {
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card mx-auto">
                    <div class="card-header">
                        <div class="police-badge">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3>Sistema RH - Policía Nacional</h3>
                        <p>Crear Usuario Administrador</p>
                    </div>
                    <div class="card-body">
                        <?php echo $mensaje; ?>
                        
                        <form method="POST">
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="mb-3">
                                        <label for="nombre_usuario" class="form-label"><i class="fas fa-user"></i> Nombre de Usuario *</label>
                                        <input type="text" class="form-control" id="nombre_usuario" name="nombre_usuario" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="contraseña" class="form-label"><i class="fas fa-lock"></i> Contraseña *</label>
                                        <input type="password" class="form-control" id="contraseña" name="contraseña" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="rol" class="form-label"><i class="fas fa-user-tag"></i> Rol</label>
                                        <select class="form-select" id="rol" name="rol">
                                            <option value="ADMIN">Administrador</option>
                                            <option value="SUPERVISOR">Supervisor</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-col">
                                    <div class="mb-3">
                                        <label for="nombre_completo" class="form-label"><i class="fas fa-id-card"></i> Nombre Completo *</label>
                                        <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label"><i class="fas fa-envelope"></i> Email</label>
                                        <input type="email" class="form-control" id="email" name="email">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="d-grid" style="margin-top: 32px;">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-user-plus"></i> Crear Usuario
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>