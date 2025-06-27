<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

$mensaje = "";
$policia_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($policia_id <= 0) {
    header("Location: index.php");
    exit();
}

// Obtener datos del policía
$stmt = $conn->prepare("
    SELECT p.*, g.nombre as grado_nombre, e.nombre as especialidad_nombre, lg.nombre as lugar_guardia_nombre, r.nombre as region_nombre
    FROM policias p
    LEFT JOIN grados g ON p.grado_id = g.id
    LEFT JOIN especialidades e ON p.especialidad_id = e.id
    LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
    LEFT JOIN regiones r ON p.region_id = r.id
    WHERE p.id = ? AND p.activo = 1
");
$stmt->bind_param("i", $policia_id);
$stmt->execute();
$policia = $stmt->get_result()->fetch_assoc();

if (!$policia) {
    header("Location: index.php");
    exit();
}

// Verificar dependencias antes de eliminar
$dependencias = [];

// Verificar ausencias
$ausencias = $conn->prepare("SELECT COUNT(*) as total FROM ausencias WHERE policia_id = ?");
$ausencias->bind_param("i", $policia_id);
$ausencias->execute();
$total_ausencias = $ausencias->get_result()->fetch_assoc()['total'];
if ($total_ausencias > 0) {
    $dependencias[] = "$total_ausencias ausencia(s) registrada(s)";
}

// Verificar asignaciones de servicios
$servicios = $conn->prepare("SELECT COUNT(*) as total FROM asignaciones_servicios WHERE policia_id = ?");
$servicios->bind_param("i", $policia_id);
$servicios->execute();
$total_servicios = $servicios->get_result()->fetch_assoc()['total'];
if ($total_servicios > 0) {
    $dependencias[] = "$total_servicios asignación(es) de servicio";
}

// Verificar guardias realizadas
$guardias = $conn->prepare("SELECT COUNT(*) as total FROM guardias_realizadas WHERE policia_id = ?");
$guardias->bind_param("i", $policia_id);
$guardias->execute();
$total_guardias = $guardias->get_result()->fetch_assoc()['total'];
if ($total_guardias > 0) {
    $dependencias[] = "$total_guardias guardia(s) realizada(s)";
}

// Verificar lista de guardias
$lista_guardias = $conn->prepare("SELECT COUNT(*) as total FROM lista_guardias WHERE policia_id = ?");
$lista_guardias->bind_param("i", $policia_id);
$lista_guardias->execute();
$total_lista = $lista_guardias->get_result()->fetch_assoc()['total'];
if ($total_lista > 0) {
    $dependencias[] = "Está en la lista de guardias";
}

// Procesar eliminación
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'eliminar') {
    $tipo_eliminacion = $_POST['tipo_eliminacion'];
    
    if ($tipo_eliminacion == 'logica') {
        // Eliminación lógica (marcar como inactivo)
        $sql = "UPDATE policias SET activo = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $policia_id);
        
        if ($stmt->execute()) {
            // También remover de lista de guardias
            $conn->prepare("DELETE FROM lista_guardias WHERE policia_id = ?")->execute([$policia_id]);
            
            $mensaje = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Policía desactivado exitosamente. Los registros históricos se mantienen.</div>";
            $policia['activo'] = 0;
        } else {
            $mensaje = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error al desactivar policía: " . $conn->error . "</div>";
        }
    } elseif ($tipo_eliminacion == 'fisica' && empty($dependencias)) {
        // Eliminación física (solo si no hay dependencias)
        $sql = "DELETE FROM policias WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $policia_id);
        
        if ($stmt->execute()) {
            header("Location: index.php?mensaje=eliminado");
            exit();
        } else {
            $mensaje = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error al eliminar policía: " . $conn->error . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Policía - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(45deg, #104c75, #0d3d5c) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .main-content {
            padding: 30px;
        }
        .page-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 30px;
        }
        .btn-danger {
            background: linear-gradient(45deg, #dc3545, #c82333);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
        .btn-warning {
            background: linear-gradient(45deg, #ffc107, #e0a800);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
        .btn-secondary {
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
        .info-badge {
            background: linear-gradient(45deg, #17a2b8, #138496);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        .danger-zone {
            border: 2px solid #dc3545;
            border-radius: 15px;
            background: rgba(220, 53, 69, 0.05);
        }
        .warning-zone {
            border: 2px solid #ffc107;
            border-radius: 15px;
            background: rgba(255, 193, 7, 0.05);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php 
            $_GET['page'] = 'policias';
            include '../inc/sidebar.php'; 
            ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="page-title"><i class="fas fa-user-times text-danger"></i> Eliminar Policía</h1>
                        <div class="d-flex gap-2">
                            <span class="info-badge">ID: <?php echo $policia['id']; ?></span>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Volver a la Lista
                            </a>
                        </div>
                    </div>

                    <?php echo $mensaje; ?>

                    <?php if ($policia['activo'] == 1): ?>
                    <!-- Información del Policía -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-user"></i> Información del Policía a Eliminar</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Nombre Completo:</strong></td>
                                            <td><?php echo htmlspecialchars($policia['nombre'] . ' ' . $policia['apellido']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>CIN:</strong></td>
                                            <td><?php echo htmlspecialchars($policia['cin']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Grado:</strong></td>
                                            <td><?php echo htmlspecialchars($policia['grado_nombre']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Especialidad:</strong></td>
                                            <td><?php echo $policia['especialidad_nombre'] ? htmlspecialchars($policia['especialidad_nombre']) : 'Sin especialidad'; ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Cargo:</strong></td>
                                            <td><?php echo $policia['cargo'] ? htmlspecialchars($policia['cargo']) : 'Sin cargo'; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Región:</strong></td>
                                            <td><?php echo htmlspecialchars($policia['region_nombre']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Lugar de Guardia:</strong></td>
                                            <td><?php echo $policia['lugar_guardia_nombre'] ? htmlspecialchars($policia['lugar_guardia_nombre']) : 'Sin asignar'; ?></td>
                                        </tr>
                                      
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Verificación de Dependencias -->
                    <?php if (!empty($dependencias)): ?>
                    <div class="card mb-4 warning-zone">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Registros Relacionados Encontrados</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-3">Este policía tiene los siguientes registros relacionados:</p>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($dependencias as $dependencia): ?>
                                    <li class="list-group-item"><i class="fas fa-link text-warning"></i> <?php echo $dependencia; ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="alert alert-warning mt-3">
                                <strong>Recomendación:</strong> Debido a estos registros relacionados, se recomienda realizar una <strong>eliminación lógica</strong> (desactivar) en lugar de eliminar físicamente el registro.
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Opciones de Eliminación -->
                    <div class="row">
                        <!-- Eliminación Lógica -->
                        <div class="col-md-6">
                            <div class="card warning-zone">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="mb-0"><i class="fas fa-eye-slash"></i> Eliminación Lógica (Recomendado)</h5>
                                </div>
                                <div class="card-body">
                                    <p>Desactiva el policía del sistema manteniendo todos los registros históricos.</p>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success"></i> Mantiene historial completo</li>
                                        <li><i class="fas fa-check text-success"></i> Preserva integridad de datos</li>
                                        <li><i class="fas fa-check text-success"></i> Permite reactivación futura</li>
                                        <li><i class="fas fa-check text-success"></i> Cumple con auditorías</li>
                                    </ul>
                                    
                                    <form method="POST" action="" class="mt-3" onsubmit="return confirmarEliminacionLogica()">
                                        <input type="hidden" name="action" value="eliminar">
                                        <input type="hidden" name="tipo_eliminacion" value="logica">
                                        <button type="submit" class="btn btn-warning w-100">
                                            <i class="fas fa-eye-slash"></i> Desactivar Policía
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Eliminación Física -->
                        <div class="col-md-6">
                            <div class="card danger-zone">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0"><i class="fas fa-trash"></i> Eliminación Física (Permanente)</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($dependencias)): ?>
                                        <div class="alert alert-danger">
                                            <i class="fas fa-ban"></i> <strong>No disponible</strong><br>
                                            No se puede eliminar físicamente debido a registros relacionados.
                                        </div>
                                    <?php else: ?>
                                        <p>Elimina permanentemente el registro del policía y todos sus datos.</p>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-times text-danger"></i> Eliminación irreversible</li>
                                            <li><i class="fas fa-times text-danger"></i> Pérdida total de datos</li>
                                            <li><i class="fas fa-times text-danger"></i> No recomendado para auditorías</li>
                                        </ul>
                                        
                                        <form method="POST" action="" class="mt-3" onsubmit="return confirmarEliminacionFisica()">
                                            <input type="hidden" name="action" value="eliminar">
                                            <input type="hidden" name="tipo_eliminacion" value="fisica">
                                            <button type="submit" class="btn btn-danger w-100">
                                                <i class="fas fa-trash"></i> Eliminar Permanentemente
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php else: ?>
                    <!-- Policía ya desactivado -->
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Policía Desactivado</h5>
                        </div>
                        <div class="card-body text-center">
                            <i class="fas fa-user-slash fa-5x text-muted mb-3"></i>
                            <h4>Este policía ya ha sido desactivado</h4>
                            <p class="text-muted">El registro se encuentra inactivo en el sistema.</p>
                            <a href="index.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i> Volver a la Lista
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmarEliminacionLogica() {
            return confirm('¿Está seguro de que desea DESACTIVAR este policía?\n\nEsta acción:\n- Ocultará el policía de las listas activas\n- Mantendrá todos los registros históricos\n- Permitirá reactivación futura');
        }
        
        function confirmarEliminacionFisica() {
            const confirmacion1 = confirm('⚠️ ADVERTENCIA: ELIMINACIÓN PERMANENTE ⚠️\n\n¿Está ABSOLUTAMENTE SEGURO de que desea eliminar permanentemente este policía?\n\nEsta acción es IRREVERSIBLE.');
            
            if (confirmacion1) {
                const confirmacion2 = confirm('ÚLTIMA CONFIRMACIÓN:\n\nEscribir "ELIMINAR" en el siguiente prompt para confirmar la eliminación permanente.');
                
                if (confirmacion2) {
                    const texto = prompt('Escriba "ELIMINAR" para confirmar:');
                    return texto === 'ELIMINAR';
                }
            }
            
            return false;
        }
    </script>
</body>
</html>