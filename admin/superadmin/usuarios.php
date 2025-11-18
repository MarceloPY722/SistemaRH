<?php
session_start();

// Verificar autenticación y rol de superadmin
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../cnx/db_connect.php';

// Verificar rol de superadmin
$stmt = $conn->prepare("SELECT rol FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();

if ($usuario['rol'] !== 'SUPERADMIN') {
    header('Location: ../../index.php');
    exit();
}

$mensaje = '';
$tipo_mensaje = '';

// Obtener lista de usuarios
$usuarios = [];
$stmt = $conn->query("SELECT id, nombre_usuario, nombre_completo, email, rol, activo, creado_en FROM usuarios ORDER BY creado_en DESC");
$usuarios = $stmt->fetchAll();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['crear_usuario'])) {
            $username = trim($_POST['nombre_usuario']);
            $password = $_POST['contraseña'];
            $nombre_completo = trim($_POST['nombre_completo']);
            $email = trim($_POST['email']);
            $rol = $_POST['rol'];
            
            // Validaciones
            if (empty($username) || empty($password)) {
                throw new Exception('Usuario y contraseña son obligatorios');
            }
            
            // Verificar si el usuario ya existe
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE nombre_usuario = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                throw new Exception('El nombre de usuario ya existe');
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre_usuario, contraseña, nombre_completo, email, rol, activo) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$username, $hashed_password, $nombre_completo, $email, $rol]);
            
            $usuario_id = $conn->lastInsertId();
            
            // Registrar en auditoría
            require_once '../inc/auditoria_functions.php';
            auditoriaCrear('usuarios', $usuario_id, [
                'nombre_usuario' => $username,
                'nombre_completo' => $nombre_completo,
                'email' => $email,
                'rol' => $rol
            ]);
            
            $mensaje = '✅ Usuario creado exitosamente';
            $tipo_mensaje = 'success';
            
        } elseif (isset($_POST['eliminar_usuario'])) {
            $usuario_id = $_POST['usuario_id'];
            
            // No permitir eliminarse a sí mismo
            if ($usuario_id == $_SESSION['usuario_id']) {
                throw new Exception('No puedes eliminarte a ti mismo');
            }
            
            // Obtener datos del usuario antes de eliminar para auditoría
            $stmt_select = $conn->prepare("SELECT nombre_usuario, nombre_completo, email, rol FROM usuarios WHERE id = ?");
            $stmt_select->execute([$usuario_id]);
            $usuario_data = $stmt_select->fetch();
            
            $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            
            // Registrar en auditoría
            require_once '../inc/auditoria_functions.php';
            auditoriaEliminar('usuarios', $usuario_id, $usuario_data);
            
            $mensaje = '✅ Usuario eliminado exitosamente';
            $tipo_mensaje = 'success';
            
        } elseif (isset($_POST['cambiar_estado'])) {
            $usuario_id = $_POST['usuario_id'];
            $nuevo_estado = $_POST['nuevo_estado'];
            
            // No permitir desactivarse a sí mismo
            if ($usuario_id == $_SESSION['usuario_id'] && $nuevo_estado == 0) {
                throw new Exception('No puedes desactivarte a ti mismo');
            }
            
            // Obtener datos actuales del usuario para auditoría
            $stmt_select = $conn->prepare("SELECT nombre_usuario, nombre_completo, email, rol, activo FROM usuarios WHERE id = ?");
            $stmt_select->execute([$usuario_id]);
            $datos_anteriores = $stmt_select->fetch();
            
            $stmt = $conn->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
            $stmt->execute([$nuevo_estado, $usuario_id]);
            
            // Registrar en auditoría
            require_once '../inc/auditoria_functions.php';
            auditoriaActualizar('usuarios', $usuario_id, $datos_anteriores, [
                'activo' => $nuevo_estado
            ]);
            
            $mensaje = '✅ Estado del usuario actualizado';
            $tipo_mensaje = 'success';
        }
        
        // Recargar lista de usuarios
        $stmt = $conn->query("SELECT id, nombre_usuario, nombre_completo, email, rol, activo, creado_en FROM usuarios ORDER BY creado_en DESC");
        $usuarios = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $mensaje = '❌ Error: ' . $e->getMessage();
        $tipo_mensaje = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .user-card { border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.08); }
        .user-badge { font-size: 0.75em; }
        .table-responsive { max-height: 500px; }
        .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
        .page-header h2 { margin:0; }
        .card-header.bg-primary { background: linear-gradient(135deg, #104c75 0%, #0d3d5c 100%) !important; }
        .card-header.bg-info { background: linear-gradient(135deg, #17a2b8 0%, #0d3d5c 100%) !important; }
        
        /* Estilos para tabla compacta */
        .table-compact {
            font-size: 0.85rem;
        }
        .table-compact th,
        .table-compact td {
            padding: 0.5rem 0.75rem;
            vertical-align: middle;
        }
        .table-compact .btn-group .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        .table-compact .btn-group {
            gap: 2px;
        }
        .table-compact .text-muted {
            font-size: 0.75rem;
        }
        .table-compact .badge {
            font-size: 0.7rem;
        }
        .compact-card .card-body {
            padding: 1rem;
        }
        .compact-form .form-control,
        .compact-form .form-select {
            font-size: 0.85rem;
            padding: 0.5rem;
        }
        .compact-form .btn {
            font-size: 0.85rem;
            padding: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include '../inc/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../inc/sidebar.php'; ?>
            
            <main class="col-md-9 col-lg-10 px-4 py-4">
                <div class="page-header">
                    <h2><i class="fas fa-user-cog me-2"></i>Gestión de Usuarios</h2>
                    <span class="badge bg-warning text-dark">Super Admin</span>
                </div>

                <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card user-card compact-card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Crear Nuevo Usuario</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="compact-form">
                                    <div class="mb-3">
                                        <label class="form-label">Nombre de Usuario *</label>
                                        <input type="text" name="nombre_usuario" class="form-control" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Contraseña *</label>
                                        <input type="password" name="contraseña" class="form-control" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Nombre Completo</label>
                                        <input type="text" name="nombre_completo" class="form-control">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Rol</label>
                                        <select name="rol" class="form-select">
                                            <option value="ADMIN">Administrador</option>
                                            <option value="SUPERADMIN">Super Administrador</option>
                                        </select>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" name="crear_usuario" class="btn btn-success">
                                            <i class="fas fa-save me-2"></i>Crear Usuario
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="card user-card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Usuarios del Sistema</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-compact">
                                        <thead>
                                            <tr>
                                                <th>Usuario</th>
                                                <th>Nombre</th>
                                                <th>Email</th>
                                                <th>Rol</th>
                                                <th>Estado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($usuarios as $usuario): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($usuario['nombre_usuario']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo $usuario['creado_en']; ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($usuario['nombre_completo'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($usuario['email'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $usuario['rol'] === 'SUPERADMIN' ? 'warning' : 'primary'; ?> user-badge">
                                                        <?php echo $usuario['rol']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $usuario['activo'] ? 'success' : 'danger'; ?> user-badge">
                                                        <?php echo $usuario['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                                            <input type="hidden" name="nuevo_estado" value="<?php echo $usuario['activo'] ? 0 : 1; ?>">
                                                            <button type="submit" name="cambiar_estado" class="btn btn-sm btn-<?php echo $usuario['activo'] ? 'warning' : 'success'; ?>"
                                                                    <?php echo $usuario['id'] == $_SESSION['usuario_id'] ? 'disabled' : ''; ?>>
                                                                <i class="fas fa-<?php echo $usuario['activo'] ? 'times' : 'check'; ?>"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                                            <button type="submit" name="eliminar_usuario" class="btn btn-sm btn-danger"
                                                                    <?php echo $usuario['id'] == $_SESSION['usuario_id'] ? 'disabled' : ''; ?>
                                                                    onclick="return confirm('¿Estás seguro de eliminar este usuario?')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>