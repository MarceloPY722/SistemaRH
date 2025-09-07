<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

// Procesar intercambios manuales
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] == 'intercambiar') {
        $policia_id = $_POST['policia_id'];
        $ausencia_id = $_POST['ausencia_id'];
        
        // Obtener datos del policía
        $stmt = $conn->prepare("SELECT lugar_guardia_id, lugar_guardia_reserva_id FROM policias WHERE id = ?");
        $stmt->bind_param("i", $policia_id);
        $stmt->execute();
        $policia = $stmt->get_result()->fetch_assoc();
        
        // Intercambiar lugares de guardia
        $nuevo_principal = $policia['lugar_guardia_reserva_id'];
        $nuevo_reserva = $policia['lugar_guardia_id'];
        
        $stmt_update = $conn->prepare("UPDATE policias SET lugar_guardia_id = ?, lugar_guardia_reserva_id = ? WHERE id = ?");
        $stmt_update->bind_param("iii", $nuevo_principal, $nuevo_reserva, $policia_id);
        
        if ($stmt_update->execute()) {
            // Registrar el intercambio
            $stmt_log = $conn->prepare("INSERT INTO intercambios_guardias (policia_id, ausencia_id, lugar_original_id, lugar_intercambio_id, fecha_intercambio, usuario_id) VALUES (?, ?, ?, ?, NOW(), ?)");
            $stmt_log->bind_param("iiiii", $policia_id, $ausencia_id, $nuevo_reserva, $nuevo_principal, $_SESSION['usuario_id']);
            $stmt_log->execute();
            
            // Marcar al policía como disponible en su nuevo lugar de guardia
            try {
                $stmt_disponible = $conn->prepare("CALL MarcarDisponibleEnNuevoLugar(?)");
                $stmt_disponible->bind_param("i", $policia_id);
                $stmt_disponible->execute();
                $stmt_disponible->close();
            } catch (Exception $e) {
                // Si el procedimiento no existe, asegurar que esté en lista_guardias
                $stmt_check_lista = $conn->prepare("SELECT COUNT(*) as count FROM lista_guardias WHERE policia_id = ?");
                $stmt_check_lista->bind_param("i", $policia_id);
                $stmt_check_lista->execute();
                $result = $stmt_check_lista->get_result()->fetch_assoc();
                
                if ($result['count'] == 0) {
                    // Agregar a lista_guardias si no está
                    $stmt_add_lista = $conn->prepare("INSERT INTO lista_guardias (policia_id, posicion) SELECT ?, COALESCE(MAX(posicion), 0) + 1 FROM lista_guardias");
                    $stmt_add_lista->bind_param("i", $policia_id);
                    $stmt_add_lista->execute();
                }
            }
            
            $mensaje = "<div class='alert alert-success'>Intercambio realizado exitosamente. El policía está ahora disponible en su nuevo lugar de guardia.</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al realizar el intercambio</div>";
        }
    }
    
    if ($_POST['action'] == 'completar') {
        $policia_id = $_POST['policia_id'];
        $ausencia_id = $_POST['ausencia_id'];
        
        // Marcar ausencia como completada
        $stmt = $conn->prepare("UPDATE ausencias SET estado = 'COMPLETADA' WHERE id = ?");
        $stmt->bind_param("i", $ausencia_id);
        
        if ($stmt->execute()) {
            // Restaurar lugar de guardia original si hay intercambio activo
            $stmt_check = $conn->prepare("SELECT lugar_original_id, lugar_intercambio_id FROM intercambios_guardias WHERE policia_id = ? AND ausencia_id = ? AND activo = 1");
            $stmt_check->bind_param("ii", $policia_id, $ausencia_id);
            $stmt_check->execute();
            $intercambio = $stmt_check->get_result()->fetch_assoc();
            
            if ($intercambio) {
                // Restaurar lugar original
                $stmt_restore = $conn->prepare("UPDATE policias SET lugar_guardia_id = ?, lugar_guardia_reserva_id = ? WHERE id = ?");
                $stmt_restore->bind_param("iii", $intercambio['lugar_original_id'], $intercambio['lugar_intercambio_id'], $policia_id);
                $stmt_restore->execute();
                
                // Marcar intercambio como inactivo
                $stmt_deactivate = $conn->prepare("UPDATE intercambios_guardias SET activo = 0, fecha_restauracion = NOW() WHERE policia_id = ? AND ausencia_id = ?");
                $stmt_deactivate->bind_param("ii", $policia_id, $ausencia_id);
                $stmt_deactivate->execute();
            }
            
            $mensaje = "<div class='alert alert-success'>Ausencia completada y lugar de guardia restaurado</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al completar ausencia</div>";
        }
    }
}

// Crear tabla de intercambios si no existe
$conn->query("CREATE TABLE IF NOT EXISTS intercambios_guardias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    policia_id INT NOT NULL,
    ausencia_id INT NOT NULL,
    lugar_original_id INT NOT NULL,
    lugar_intercambio_id INT NOT NULL,
    fecha_intercambio DATETIME NOT NULL,
    fecha_restauracion DATETIME NULL,
    usuario_id INT NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Obtener personal con ausencias por Junta Médica para intercambio
$query_ausentes = "
    SELECT 
        a.id as ausencia_id,
        a.fecha_inicio,
        a.fecha_fin,
        a.estado,
        a.descripcion,
        p.id as policia_id,
        p.nombre,
        p.apellido,
        p.cin,
        p.legajo,
        tg.nombre as grado_nombre,
        tg.abreviatura as grado_abreviatura,
        lg_principal.nombre as lugar_principal,
        lg_reserva.nombre as lugar_reserva,
        ta.nombre as tipo_ausencia,
        CASE 
            WHEN ig.id IS NOT NULL AND ig.activo = 1 THEN 'INTERCAMBIADO'
            ELSE 'NORMAL'
        END as estado_intercambio,
        DATEDIFF(COALESCE(a.fecha_fin, CURDATE()), a.fecha_inicio) + 1 as dias_ausencia,
        CASE 
            WHEN a.fecha_fin IS NULL THEN 'Indefinida'
            WHEN a.fecha_fin < CURDATE() THEN 'Vencida'
            WHEN a.fecha_fin = CURDATE() THEN 'Termina hoy'
            ELSE CONCAT(DATEDIFF(a.fecha_fin, CURDATE()), ' días restantes')
        END as tiempo_restante
    FROM ausencias a
    JOIN policias p ON a.policia_id = p.id
    LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
    LEFT JOIN lugares_guardias lg_principal ON p.lugar_guardia_id = lg_principal.id
    LEFT JOIN lugares_guardias lg_reserva ON p.lugar_guardia_reserva_id = lg_reserva.id
    JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id
    LEFT JOIN intercambios_guardias ig ON p.id = ig.policia_id AND a.id = ig.ausencia_id AND ig.activo = 1
    WHERE a.estado = 'APROBADA' 
    AND ta.nombre = 'Junta Medica'
    AND CURDATE() BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, CURDATE())
    ORDER BY a.fecha_inicio ASC
";

$ausentes_result = $conn->query($query_ausentes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intercambio de Lugares de Guardia (Junta Médica) - Sistema RH</title>
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
            margin-bottom: 20px;
        }
        .main-content {
            padding: 30px;
        }
        .page-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 30px;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-normal {
            background: #e3f2fd;
            color: #1976d2;
        }
        .status-intercambiado {
            background: #fff3e0;
            color: #f57c00;
        }
        .tiempo-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .tiempo-normal {
            background: #e8f5e8;
            color: #2e7d32;
        }
        .tiempo-urgente {
            background: #ffebee;
            color: #c62828;
        }
        .btn-intercambio {
            background: linear-gradient(45deg, #ff9800, #f57c00);
            border: none;
            color: white;
        }
        .btn-intercambio:hover {
            background: linear-gradient(45deg, #f57c00, #ff9800);
            color: white;
        }
        .btn-completar {
            background: linear-gradient(45deg, #4caf50, #388e3c);
            border: none;
            color: white;
        }
        .btn-completar:hover {
            background: linear-gradient(45deg, #388e3c, #4caf50);
            color: white;
        }
        .alert-tiempo {
            background: linear-gradient(45deg, #ff5722, #d84315);
            color: white;
            border: none;
            border-radius: 10px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php 
            $_GET['page'] = 'intercambio';
            include '../inc/sidebar.php'; 
            ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="page-title mb-0">
                            <i class="fas fa-exchange-alt"></i> Intercambio de Lugares de Guardia
                        </h1>
                        <a href="../index.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Volver al Dashboard
                        </a>
                    </div>

                    <?php if (isset($mensaje)) echo $mensaje; ?>

                    <!-- Alertas de tiempo -->
                    <div id="alertas-tiempo"></div>

                    <!-- Personal Ausente -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5><i class="fas fa-user-times"></i> Personal Ausente - Intercambios Disponibles</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($ausentes_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Personal</th>
                                            <th>Ausencia</th>
                                            <th>Lugar Principal</th>
                                            <th>Lugar Reserva</th>
                                            <th>Estado</th>
                                            <th>Tiempo</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($ausente = $ausentes_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $ausente['grado_abreviatura'] . ' ' . $ausente['apellido'] . ', ' . $ausente['nombre']; ?></strong><br>
                                                <small class="text-muted">Legajo: <?php echo $ausente['legajo']; ?> | CIN: <?php echo $ausente['cin']; ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo $ausente['tipo_ausencia']; ?></strong><br>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y', strtotime($ausente['fecha_inicio'])); ?>
                                                    <?php if ($ausente['fecha_fin']): ?>
                                                        - <?php echo date('d/m/Y', strtotime($ausente['fecha_fin'])); ?>
                                                    <?php else: ?>
                                                        - Indefinida
                                                    <?php endif; ?>
                                                    <br>(<?php echo $ausente['dias_ausencia']; ?> días)
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $ausente['lugar_principal']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $ausente['lugar_reserva']; ?></span>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $ausente['estado_intercambio'] == 'INTERCAMBIADO' ? 'status-intercambiado' : 'status-normal'; ?>">
                                                    <?php echo $ausente['estado_intercambio']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="tiempo-badge <?php echo (strpos($ausente['tiempo_restante'], 'Termina hoy') !== false || strpos($ausente['tiempo_restante'], 'Vencida') !== false) ? 'tiempo-urgente' : 'tiempo-normal'; ?>">
                                                    <?php echo $ausente['tiempo_restante']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <?php if ($ausente['estado_intercambio'] == 'NORMAL'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="intercambiar">
                                                        <input type="hidden" name="policia_id" value="<?php echo $ausente['policia_id']; ?>">
                                                        <input type="hidden" name="ausencia_id" value="<?php echo $ausente['ausencia_id']; ?>">
                                                        <button type="submit" class="btn btn-intercambio btn-sm" 
                                                                onclick="return confirm('¿Intercambiar lugares de guardia?')" 
                                                                title="Intercambiar a lugar de reserva">
                                                            <i class="fas fa-exchange-alt"></i>
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                    
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="completar">
                                                        <input type="hidden" name="policia_id" value="<?php echo $ausente['policia_id']; ?>">
                                                        <input type="hidden" name="ausencia_id" value="<?php echo $ausente['ausencia_id']; ?>">
                                                        <button type="submit" class="btn btn-completar btn-sm" 
                                                                onclick="return confirm('¿Marcar ausencia como completada?')" 
                                                                title="Completar ausencia">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h5 class="text-muted">No hay personal ausente que requiera intercambio</h5>
                                <p class="text-muted">Todo el personal está disponible en sus lugares de guardia asignados.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para verificar alertas de tiempo (GMT-3)
        function verificarAlertas() {
            const ahora = new Date();
            // Ajustar a GMT-3 (Paraguay)
            ahora.setHours(ahora.getHours() - 3);
            
            const filas = document.querySelectorAll('tbody tr');
            const alertasContainer = document.getElementById('alertas-tiempo');
            let alertas = [];
            
            filas.forEach(fila => {
                const tiempoBadge = fila.querySelector('.tiempo-badge');
                const nombrePersonal = fila.querySelector('strong').textContent;
                
                if (tiempoBadge && tiempoBadge.textContent.includes('Termina hoy')) {
                    alertas.push(`⚠️ ${nombrePersonal} - Su ausencia termina hoy`);
                }
                
                if (tiempoBadge && tiempoBadge.textContent.includes('Vencida')) {
                    alertas.push(`🚨 ${nombrePersonal} - Su ausencia ya venció, debe ser completada`);
                }
            });
            
            if (alertas.length > 0) {
                alertasContainer.innerHTML = `
                    <div class="alert alert-tiempo">
                        <h6><i class="fas fa-exclamation-triangle"></i> Alertas de Tiempo</h6>
                        ${alertas.map(alerta => `<div>${alerta}</div>`).join('')}
                    </div>
                `;
            }
        }
        
        // Verificar alertas al cargar la página
        document.addEventListener('DOMContentLoaded', verificarAlertas);
        
        // Verificar alertas cada minuto
        setInterval(verificarAlertas, 60000);
    </script>
</body>
</html>