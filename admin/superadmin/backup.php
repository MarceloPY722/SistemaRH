<?php
session_start();

// Verificar autenticación y rol de superadmin
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../cnx/db_connect.php';
require_once '../inc/auditoria_functions.php';

// Verificar rol de superadmin
$stmt = $conn->prepare("SELECT rol FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();

// Obtener configuración de base de datos desde la conexión existente
// Leer el archivo de conexión para obtener las variables
$db_config_content = file_get_contents('../../cnx/db_connect.php');

// Extraer variables usando expresiones regulares
preg_match("/\$servername\s*=\s*['\"](.+?)['\"]/", $db_config_content, $host_match);
preg_match("/\$username\s*=\s*['\"](.+?)['\"]/", $db_config_content, $user_match);
preg_match("/\$password\s*=\s*['\"](.*?)['\"]/", $db_config_content, $pass_match);
preg_match("/\$dbname\s*=\s*['\"](.+?)['\"]/", $db_config_content, $db_match);

$db_host = $host_match[1] ?? 'localhost';
$db_name = $db_match[1] ?? 'sistema_rh_policia';
$db_user = $user_match[1] ?? 'root';
$db_pass = $pass_match[1] ?? '';

if (empty($db_host) || empty($db_user) || empty($db_name)) {
    die("Error: No se pudieron obtener las credenciales de la base de datos");
}

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
        
        // Comando para crear backup (detectar mysqldump automáticamente)
        $mysqldump_paths = [
            'd:\Laragon\bin\mysql\mysql-8.4.0-winx64\bin\mysqldump.exe',
            'd:\Laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe',
            'd:\Laragon\bin\mysql\mysql-5.7.33-winx64\bin\mysqldump.exe',
            'mysqldump' // Fallback al PATH del sistema
        ];
        
        $mysqldump_path = null;
        foreach ($mysqldump_paths as $path) {
            if (file_exists($path) || $path === 'mysqldump') {
                $mysqldump_path = $path;
                break;
            }
        }
        
        if (!$mysqldump_path) {
            throw new Exception('No se encontró mysqldump. Por favor, verifique la instalación de MySQL.');
        }
        
        // Construir comando con contraseña si existe
        $password_param = !empty($db_pass) ? "-p'{$db_pass}'" : '';
        $command = "\"{$mysqldump_path}\" -u {$db_user} {$password_param} {$db_name} > \"{$backup_file}\"";
        
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
        /* Esquema de colores del sistema */
        .system-primary { background: var(--primary-color, #104c75) !important; }
        .system-secondary { background: var(--secondary-color, #0d3d5c) !important; }
        .system-accent { background: linear-gradient(135deg, var(--primary-color, #104c75), var(--secondary-color, #0d3d5c)) !important; }

        .btn-system {
            background: var(--primary-color, #104c75);
            border-color: var(--primary-color, #104c75);
            color: #fff;
        }
        .btn-system:hover { background: var(--secondary-color, #0d3d5c); border-color: var(--secondary-color, #0d3d5c); color: #fff; }
        .btn-outline-system { border-color: var(--primary-color, #104c75); color: var(--primary-color, #104c75); }
        .btn-outline-system:hover { background: var(--primary-color, #104c75); color: #fff; }

        .system-badge { background: var(--primary-color, #104c75); color: #fff; }

        .backup-card {
            border: 2px solid var(--primary-color, #104c75);
            border-radius: 12px;
            box-shadow: var(--shadow, 0 4px 20px rgba(0,0,0,0.08));
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
                    <span class="badge system-badge">Super Admin</span>
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
                            <div class="card-header system-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Crear Nuevo Backup</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Crea una copia de seguridad completa de la base de datos del sistema.</p>
                                
                                <form method="POST">
                                    <div class="d-grid">
                                        <button type="submit" name="crear_backup" class="btn btn-system btn-lg">
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
                            <div class="card-header system-secondary text-white">
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
                                           class="btn btn-sm btn-outline-system" download>
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