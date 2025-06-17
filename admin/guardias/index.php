<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';

// Procesar reorganización de lista
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'reorganizar') {
    $conn->query("CALL ReorganizarListaGuardias()");
    $mensaje = "<div class='alert alert-success'>Lista de guardias reorganizada exitosamente</div>";
}

// Procesar rotación de guardia
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'rotar') {
    $policia_id = $_POST['policia_id'];
    $stmt = $conn->prepare("CALL RotarGuardia(?)");
    $stmt->bind_param("i", $policia_id);
    $stmt->execute();
    $mensaje = "<div class='alert alert-success'>Guardia rotada exitosamente</div>";
}

// Obtener lista de guardias ordenada
$lista_guardias = $conn->query("
    SELECT 
        lg.posicion,
        p.id as policia_id,
        p.nombre,
        p.apellido,
        p.cin,
        g.nombre as grado,
        g.nivel_jerarquia,
        p.antiguedad_dias,
        lguar.nombre as lugar_guardia,
        lg.ultima_guardia_fecha,
        CASE 
            WHEN EXISTS (
                SELECT 1 FROM ausencias a 
                WHERE a.policia_id = p.id 
                AND a.estado = 'APROBADA'
                AND CURDATE() BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, CURDATE())
            ) THEN 'NO DISPONIBLE'
            ELSE 'DISPONIBLE'
        END as disponibilidad
    FROM lista_guardias lg
    JOIN policias p ON lg.policia_id = p.id
    JOIN grados g ON p.grado_id = g.id
    LEFT JOIN lugares_guardias lguar ON p.lugar_guardia_id = lguar.id
    WHERE p.activo = TRUE
    ORDER BY lg.posicion
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Guardias - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(45deg, #2c3e50, #34495e) !important;
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
        .table th {
            background-color: #34495e;
            color: white;
            border: none;
        }
        .posicion-badge {
            font-size: 1.2rem;
            padding: 8px 12px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-shield-alt"></i> Sistema RH - Policía Nacional
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?php echo $_SESSION['nombre_completo']; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user-cog"></i> Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <?php 
            $_GET['page'] = 'guardias';
            include '../inc/sidebar.php'; 
            ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <h1 class="page-title">
                        <i class="fas fa-list-ul"></i> Lista de Guardias
                    </h1>

                    <?php if (isset($mensaje)) echo $mensaje; ?>

                    <!-- Botones de acción -->
                    <div class="mb-3">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="reorganizar">
                            <button type="submit" class="btn btn-info" onclick="return confirm('¿Está seguro de reorganizar la lista de guardias?')">
                                <i class="fas fa-sync-alt"></i> Reorganizar Lista por Jerarquía y Antigüedad
                            </button>
                        </form>
                    </div>

                    <!-- Tabla de lista de guardias -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5><i class="fas fa-list-ol"></i> Lista de Guardias FIFO (Primero en Entrar, Primero en Salir)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Posición</th>
                                            <th>CIN</th>
                                            <th>Nombre Completo</th>
                                            <th>Grado</th>
                                            <th>Antigüedad (días)</th>
                                            <th>Lugar de Guardia</th>
                                            <th>Última Guardia</th>
                                            <th>Disponibilidad</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($guardia = $lista_guardias->fetch_assoc()): ?>
                                        <tr class="<?php echo $guardia['disponibilidad'] == 'NO DISPONIBLE' ? 'table-warning' : ''; ?>">
                                            <td>
                                                <span class="badge bg-primary posicion-badge">
                                                    #<?php echo $guardia['posicion']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $guardia['cin']; ?></td>
                                            <td><?php echo $guardia['apellido'] . ', ' . $guardia['nombre']; ?></td>
                                            <td><?php echo $guardia['grado']; ?></td>
                                            <td><?php echo number_format($guardia['antiguedad_dias']); ?> días</td>
                                            <td><?php echo $guardia['lugar_guardia'] ?: 'Sin asignar'; ?></td>
                                            <td>
                                                <?php if ($guardia['ultima_guardia_fecha']): ?>
                                                    <?php echo date('d/m/Y', strtotime($guardia['ultima_guardia_fecha'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Nunca</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $guardia['disponibilidad'] == 'DISPONIBLE' ? 'success' : 'danger'; ?>">
                                                    <?php echo $guardia['disponibilidad']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($guardia['disponibilidad'] == 'DISPONIBLE'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="rotar">
                                                    <input type="hidden" name="policia_id" value="<?php echo $guardia['policia_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning" 
                                                            onclick="return confirm('¿Confirma que este policía realizó la guardia?')"
                                                            title="Marcar como guardia realizada">
                                                        <i class="fas fa-check"></i> Guardia Realizada
                                                    </button>
                                                </form>
                                                <?php else: ?>
                                                <span class="text-muted">No disponible</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Información adicional -->
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-secondary text-white">
                                    <h6><i class="fas fa-info-circle"></i> Información del Sistema FIFO</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="mb-0">
                                        <li><strong>Orden por Jerarquía:</strong> Los policías de mayor jerarquía tienen prioridad en la lista.</li>
                                        <li><strong>Orden por Antigüedad:</strong> En caso de igual jerarquía, se ordena por antigüedad (más antiguo primero).</li>
                                        <li><strong>Rotación Automática:</strong> Al marcar una guardia como realizada, el policía se mueve al final de la lista.</li>
                                        <li><strong>Disponibilidad:</strong> Solo los policías disponibles (sin ausencias aprobadas) pueden realizar guardias.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>