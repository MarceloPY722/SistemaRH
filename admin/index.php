<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../cnx/db_connect.php';

// Obtener estadísticas
$total_policias = $conn->query("SELECT COUNT(*) as total FROM policias WHERE activo = 1")->fetch_assoc()['total'];
$total_servicios = $conn->query("SELECT COUNT(*) as total FROM servicios WHERE estado = 'PROGRAMADO'")->fetch_assoc()['total'];
$total_ausencias = $conn->query("SELECT COUNT(*) as total FROM ausencias WHERE estado = 'PENDIENTE'")->fetch_assoc()['total'];
$total_guardias = $conn->query("SELECT COUNT(*) as total FROM lista_guardias")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrativo - Sistema RH Policía Nacional</title>
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
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .stat-card {
            background: linear-gradient(135deg, #104c75 0%, #0d3d5c 100%);
            color: white;
        }
        .stat-card.green {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .stat-card.orange {
            background: linear-gradient(135deg, #fd7e14 0%, #e83e8c 100%);
        }
        .stat-card.red {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        .main-content {
            padding: 30px;
        }
        .page-title {
            color: #104c75;
            font-weight: 600;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
  
    <div class="container-fluid">
        <div class="row">
            <?php include 'inc/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <h1 class="page-title">
                        <i class="fas fa-tachometer-alt"></i> Panel de Control
                    </h1>

                    <!-- Estadísticas -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-2x mb-3"></i>
                                    <h3><?php echo $total_policias; ?></h3>
                                    <p class="mb-0">Policías Activos</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card green">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-check fa-2x mb-3"></i>
                                    <h3><?php echo $total_servicios; ?></h3>
                                    <p class="mb-0">Servicios Programados</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card orange">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-clock fa-2x mb-3"></i>
                                    <h3><?php echo $total_ausencias; ?></h3>
                                    <p class="mb-0">Ausencias Pendientes</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card red">
                                <div class="card-body text-center">
                                    <i class="fas fa-list-ol fa-2x mb-3"></i>
                                    <h3><?php echo $total_guardias; ?></h3>
                                    <p class="mb-0">En Lista de Guardias</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones Rápidas -->
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5><i class="fas fa-plus-circle"></i> Acciones Rápidas</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="policias/index.php" class="btn btn-outline-primary">
                                            <i class="fas fa-user-plus"></i> Registrar Nuevo Policía
                                        </a>
                                        <a href="servicios/index.php" class="btn btn-outline-success">
                                            <i class="fas fa-calendar-plus"></i> Programar Servicio
                                        </a>
                                        <a href="ausencias/index.php" class="btn btn-outline-warning">
                                            <i class="fas fa-user-times"></i> Registrar Ausencia
                                        </a>
                                        <a href="guardias/index.php" class="btn btn-outline-info">
                                            <i class="fas fa-sync-alt"></i> Reorganizar Lista de Guardias
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h5><i class="fas fa-bell"></i> Notificaciones Recientes</h5>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Hay <?php echo $total_ausencias; ?> ausencias pendientes de aprobación
                                    </div>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        Sistema funcionando correctamente
                                    </div>
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