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

// Procesar backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_backup'])) {
    try {
        $backup_dir = '../../backup/databases/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }
        
        $fecha = date('Y-m-d_His');
        $backup_file = $backup_dir . 'backup_sistema_rh_' . $fecha . '.sql';
        
        // Comando para crear backup (usando mysqldump de Laragon)
        $command = 'd:\Laragon\bin\mysql\mysql-8.4.0-winx64\bin\mysqldump.exe -u root sistema_rh_policia > ' . $backup_file;
        
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            $mensaje = '✅ Backup creado exitosamente: ' . basename($backup_file);
            $tipo_mensaje = 'success';
        } else {
            $mensaje = '❌ Error al crear backup. Verifique la configuración de MySQL.';
            $tipo_mensaje = 'danger';
        }
        
    } catch (Exception $e) {
        $mensaje = '❌ Error: ' . $e->getMessage();
        $tipo_mensaje = 'danger';
    }
}

// Obtener lista de backups existentes
$backups = [];
$backup_dir = '../../backup/databases/';
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $file_path = $backup_dir . $file;
            $backups[] = [
                'nombre' => $file,
                'tamaño' => filesize($file_path),
                'fecha' => date('Y-m-d H:i:s', filemtime($file_path))
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Base de Datos - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .backup-card {
            border: 2px solid #0d6efd;
            border-radius: 15px;
        }
        .backup-list {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <?php include '../inc/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../inc/sidebar.php'; ?>
            
            <main class="col-md-9 col-lg-10 px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-database me-2"></i>Backup Base de Datos</h2>
                    <span class="badge bg-warning text-dark">Super Admin</span>
                </div>

                <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card backup-card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Crear Nuevo Backup</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Crea una copia de seguridad completa de la base de datos del sistema.</p>
                                
                                <form method="POST">
                                    <div class="d-grid">
                                        <button type="submit" name="crear_backup" class="btn btn-success btn-lg">
                                            <i class="fas fa-database me-2"></i>Crear Backup Ahora
                                        </button>
                                    </div>
                                </form>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        El backup incluye todas las tablas y datos del sistema.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card backup-card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Backups Existentes</h5>
                            </div>
                            <div class="card-body backup-list">
                                <?php if (empty($backups)): ?>
                                    <p class="text-muted text-center">No hay backups disponibles.</p>
                                <?php else: ?>
                                    <?php foreach ($backups as $backup): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                                        <div>
                                            <i class="fas fa-file-archive text-primary me-2"></i>
                                            <span class="fw-bold"><?php echo $backup['nombre']; ?></span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo round($backup['tamaño'] / 1024, 2); ?> KB | 
                                                <?php echo $backup['fecha']; ?>
                                            </small>
                                        </div>
                                        <a href="<?php echo $backup_dir . $backup['nombre']; ?>" 
                                           class="btn btn-sm btn-outline-primary" download>
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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