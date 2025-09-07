<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

// Procesar eliminaciones
$mensaje = '';
$tipo_mensaje = '';

if ($_POST) {
    try {
        $conn->beginTransaction();
        
        if (isset($_POST['eliminar_uno']) && !empty($_POST['policia_id'])) {
            $policia_id = $_POST['policia_id'];
            
            // Eliminar referencias en foreign keys
            $conn->exec("DELETE FROM guardias_generadas_detalle WHERE policia_id = $policia_id");
            $conn->exec("DELETE FROM lista_guardias WHERE policia_id = $policia_id");
            $conn->exec("DELETE FROM ausencias WHERE policia_id = $policia_id");
            $conn->exec("DELETE FROM historial_guardias_policia WHERE policia_id = $policia_id");
            
            // Eliminar el policía
            $stmt = $conn->prepare("DELETE FROM policias WHERE id = ?");
            $stmt->execute([$policia_id]);
            
            $mensaje = "Policía eliminado exitosamente";
            $tipo_mensaje = "success";
            
        } elseif (isset($_POST['eliminar_todos'])) {
            // Eliminar todas las referencias en foreign keys
            $conn->exec("DELETE FROM guardias_generadas_detalle");
            $conn->exec("DELETE FROM lista_guardias");
            $conn->exec("DELETE FROM ausencias");
            $conn->exec("DELETE FROM historial_guardias_policia");
            
            // Eliminar todos los policías
            $conn->exec("DELETE FROM policias");
            
            $mensaje = "Todos los policías han sido eliminados exitosamente";
            $tipo_mensaje = "success";
        }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $mensaje = "Error al eliminar: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// Obtener lista de policías para el buscador
$busqueda = isset($_GET['buscar']) ? $_GET['buscar'] : '';
$where_clause = '';
$params = [];

if (!empty($busqueda)) {
    $where_clause = "WHERE p.nombre LIKE ? OR p.apellido LIKE ? OR p.cin LIKE ? OR g.nombre LIKE ? OR e.nombre LIKE ?";
    $busqueda_param = "%$busqueda%";
    $params = [$busqueda_param, $busqueda_param, $busqueda_param, $busqueda_param, $busqueda_param];
}

$stmt = $conn->prepare("
    SELECT p.*, g.nombre as grado, e.nombre as especialidad, lg.nombre as lugar_guardia
    FROM policias p
    LEFT JOIN grados g ON p.grado_id = g.id
    LEFT JOIN especialidades e ON p.especialidad_id = e.id
    LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
    $where_clause
    ORDER BY p.apellido, p.nombre
");
$stmt->execute($params);
$policias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas
$total_policias = $conn->query("SELECT COUNT(*) FROM policias")->fetchColumn();
$total_referencias = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM guardias_generadas_detalle) +
        (SELECT COUNT(*) FROM lista_guardias) +
        (SELECT COUNT(*) FROM ausencias) +
        (SELECT COUNT(*) FROM historial_guardias_policia) as total
")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Policías - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #dc3545;
            --secondary-color: #c82333;
            --accent-color: #e74c3c;
            --light-bg: #f8f9fa;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin: 20px;
            padding: 30px;
            min-height: calc(100vh - 40px);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .header h1 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .stats-row {
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .search-section {
            background: var(--light-bg);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .danger-zone {
            background: #fff5f5;
            border: 2px solid #fed7d7;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .danger-zone h4 {
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .btn-danger-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-danger-custom:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .table th {
            background: var(--primary-color);
            color: white;
            border: none;
            font-weight: 600;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .btn-sm-danger {
            background: var(--primary-color);
            border: none;
            border-radius: 15px;
            padding: 5px 15px;
            font-size: 0.8em;
        }
        
        .btn-sm-danger:hover {
            background: var(--secondary-color);
        }
        
        .back-btn {
            background: linear-gradient(135deg, var(--text-secondary), #5a6268);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .back-btn:hover {
            background: linear-gradient(135deg, #5a6268, var(--primary-color));
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Botón de regreso -->
        <a href="config_policias.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Volver a Configuración
        </a>
        
        <!-- Encabezado -->
        <div class="header">
            <h1><i class="fas fa-user-times"></i> Eliminar Policías</h1>
            <p class="text-muted">Gestiona la eliminación de registros de policías del sistema</p>
        </div>
        
        <!-- Mensajes -->
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="row stats-row">
            <div class="col-md-6">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_policias; ?></div>
                    <div class="stat-label">Total de Policías</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_referencias; ?></div>
                    <div class="stat-label">Referencias en el Sistema</div>
                </div>
            </div>
        </div>
        
        <!-- Zona de peligro -->
        <div class="danger-zone">
            <h4><i class="fas fa-exclamation-triangle"></i> Zona de Peligro</h4>
            <p class="mb-3">Las siguientes acciones son <strong>irreversibles</strong> y eliminarán automáticamente todas las referencias relacionadas.</p>
            
            <div class="row">
                <div class="col-md-6">
                    <form method="POST" onsubmit="return confirm('¿Estás seguro de que deseas eliminar TODOS los policías? Esta acción no se puede deshacer.')">
                        <button type="submit" name="eliminar_todos" class="btn btn-danger btn-danger-custom w-100">
                            <i class="fas fa-trash-alt"></i> Eliminar Todos los Policías
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Buscador -->
        <div class="search-section">
            <h5><i class="fas fa-search"></i> Buscar Policías</h5>
            <form method="GET" class="row g-3">
                <div class="col-md-10">
                    <input type="text" class="form-control" name="buscar" 
                           placeholder="Buscar por nombre, apellido, cédula, grado o especialidad..." 
                           value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </form>
            <?php if (!empty($busqueda)): ?>
                <div class="mt-2">
                    <a href="eliminar_policias.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times"></i> Limpiar búsqueda
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Lista de policías -->
        <div class="table-container">
            <h5><i class="fas fa-list"></i> Lista de Policías (<?php echo count($policias); ?> encontrados)</h5>
            
            <?php if (empty($policias)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No se encontraron policías</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre Completo</th>
                                <th>Cédula</th>
                                <th>Grado</th>
                                <th>Especialidad</th>
                                <th>Lugar de Guardia</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($policias as $policia): ?>
                                <tr>
                                    <td><?php echo $policia['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($policia['nombre'] . ' ' . $policia['apellido']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($policia['cedula'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($policia['grado'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($policia['especialidad'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($policia['lugar_guardia'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $policia['activo'] ? 'success' : 'danger'; ?>">
                                            <?php echo $policia['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('¿Estás seguro de que deseas eliminar a <?php echo htmlspecialchars($policia['nombre'] . ' ' . $policia['apellido']); ?>? Esta acción eliminará todas sus referencias en el sistema.')">
                                            <input type="hidden" name="policia_id" value="<?php echo $policia['id']; ?>">
                                            <button type="submit" name="eliminar_uno" class="btn btn-danger btn-sm-danger">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .search-section, .danger-zone, .table-container');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>