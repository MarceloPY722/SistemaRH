<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';
require_once '../inc/header.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Policías - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #104c75;
            --secondary-color: #0d3d5c;
            --accent-color: #1a5a8a;
            --light-bg: #f8f9fa;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
        }
        
        .main-content {
            padding: 20px;
            background: var(--light-bg);
            min-height: 100vh;
        }
        
        .config-header {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(16, 76, 117, 0.1);
            border: 1px solid rgba(16, 76, 117, 0.1);
        }
        
        .config-header h1 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 1.8rem;
        }
        
        .config-header p {
            color: var(--text-secondary);
            font-size: 1rem;
            margin: 0;
        }
        
        .config-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .config-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 3px 12px rgba(16, 76, 117, 0.1);
            border: 1px solid rgba(16, 76, 117, 0.1);
            position: relative;
            overflow: hidden;
            min-height: 180px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .config-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(16, 76, 117, 0.1), transparent);
            transition: left 0.5s;
        }
        
        .config-card:hover::before {
            left: 100%;
        }
        
        .config-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(16, 76, 117, 0.2);
            border-color: var(--primary-color);
        }
        
        .config-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            color: white;
            transition: all 0.3s ease;
        }
        
        .config-card:hover .config-icon {
            transform: scale(1.1) rotate(5deg);
            background: linear-gradient(135deg, var(--accent-color), var(--primary-color));
        }
        
        .config-title {
            font-size: 1.1em;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .config-description {
            color: var(--text-secondary);
            margin-bottom: 15px;
            line-height: 1.4;
            font-size: 0.85rem;
            flex-grow: 1;
        }
        
        .config-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            font-size: 0.85rem;
        }
        
        .config-btn:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 76, 117, 0.3);
        }
        
        .stats-row {
            margin-top: 15px;
        }
        
        .stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(16, 76, 117, 0.1);
            border: 1px solid rgba(16, 76, 117, 0.1);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(16, 76, 117, 0.15);
        }
        
        .stat-number {
            font-size: 1.8em;
            font-weight: 700;
            color: var(--primary-color);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.85em;
            margin-top: 5px;
            font-weight: 500;
        }
        
        .breadcrumb-container {
            background: #fff;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .config-grid {
                grid-template-columns: 1fr;
            }
            
            .config-card {
                min-height: 160px;
                padding: 20px;
            }
            
            .config-icon {
                width: 45px;
                height: 45px;
                font-size: 1.3em;
            }
        }
        
        @media (max-width: 576px) {
            .stats-row .col-md-3 {
                margin-bottom: 10px;
            }
            
            .stat-card {
                padding: 12px;
            }
            
            .stat-number {
                font-size: 1.5em;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../inc/sidebar.php'; ?>
            
            <!-- Contenido Principal -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Breadcrumb -->
                    <div class="breadcrumb-container">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Configuración de Policías</li>
                            </ol>
                        </nav>
                    </div>
                    
                    <!-- Encabezado -->
                    <div class="config-header">
                        <h1><i class="fas fa-users-cog"></i> Configuración de Policías</h1>
                        <p>Gestiona las configuraciones relacionadas con el personal policial</p>
                    </div>
        
        <!-- Grid de configuraciones -->
        <div class="config-grid">
            <!-- Especialidades -->
            <div class="config-card">
                <div class="config-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <h3 class="config-title">Especialidades</h3>
                <p class="config-description">
                    Gestiona las especialidades policiales disponibles en el sistema. 
                    Agrega, edita o elimina especialidades según las necesidades.
                </p>
                <a href="../policias/especialidad/index.php" class="config-btn">
                    <i class="fas fa-cog"></i> Configurar
                </a>
            </div>
            
            <!-- Grados -->
            <div class="config-card">
                <div class="config-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h3 class="config-title">Grados</h3>
                <p class="config-description">
                    Administra los grados jerárquicos del personal policial. 
                    Define niveles, abreviaturas y estructura organizacional.
                </p>
                <a href="../policias/grado/index.php" class="config-btn">
                    <i class="fas fa-cog"></i> Configurar
                </a>
            </div>
            
            <!-- Lugares de Guardia -->
            <div class="config-card">
                <div class="config-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <h3 class="config-title">Lugares de Guardia</h3>
                <p class="config-description">
                    Configura los lugares donde se realizan las guardias. 
                    Gestiona ubicaciones, capacidades y características especiales.
                </p>
                <a href="../policias/lugar_guardias/index.php" class="config-btn">
                    <i class="fas fa-cog"></i> Configurar
                </a>
            </div>
            
            <!-- Eliminación de Policías -->
            <div class="config-card">
                <div class="config-icon">
                    <i class="fas fa-user-times"></i>
                </div>
                <h3 class="config-title">Eliminar Policías</h3>
                <p class="config-description">
                    Elimina registros de policías del sistema. Maneja automáticamente 
                    las referencias de foreign keys para una eliminación segura.
                </p>
                <a href="eliminar_policias.php" class="config-btn">
                    <i class="fas fa-trash-alt"></i> Gestionar
                </a>
            </div>
            
            <!-- Regiones -->
            <div class="config-card">
                <div class="config-icon">
                    <i class="fas fa-globe-americas"></i>
                </div>
                <h3 class="config-title">Regiones</h3>
                <p class="config-description">
                    Administra las regiones geográficas del sistema. 
                    Define áreas de cobertura y asignación territorial.
                </p>
                <a href="../policias/region/index.php" class="config-btn">
                    <i class="fas fa-cog"></i> Configurar
                </a>
            </div>
            
            <!-- Tipos de Ausencias -->
            <div class="config-card">
                <div class="config-icon">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <h3 class="config-title">Tipos de Ausencias</h3>
                <p class="config-description">
                    Gestiona los tipos de ausencias disponibles en el sistema. 
                    Configura categorías, descripciones y políticas de ausencias.
                </p>
                <a href="../ausencias/tipos_ausencias.php" class="config-btn">
                    <i class="fas fa-cog"></i> Configurar
                </a>
            </div>
            
            <!-- Gestión Junta Médica -->
            <div class="config-card">
                <div class="config-icon">
                    <i class="fas fa-user-md"></i>
                </div>
                <h3 class="config-title">Gestión Junta Médica</h3>
                <p class="config-description">
                    Administra automáticamente los policías con ausencias por Junta Médica. 
                    Los mueve temporalmente a TELEFONISTA y los restaura al finalizar.
                </p>
                <a href="../ausencias/gestion_junta_medica.php" class="config-btn">
                    <i class="fas fa-play"></i> Gestionar
                </a>
            </div>
        </div>
        
        <!-- Estadísticas rápidas -->
        <div class="row stats-row">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <?php
                    $especialidades = $conn->query("SELECT COUNT(*) as total FROM especialidades");
                    $total_especialidades = $especialidades->fetch(PDO::FETCH_ASSOC)['total'];
                    ?>
                    <div class="stat-number"><?php echo $total_especialidades; ?></div>
                    <div class="stat-label">Especialidades</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <?php
                    $grados = $conn->query("SELECT COUNT(*) as total FROM grados");
                    $total_grados = $grados->fetch(PDO::FETCH_ASSOC)['total'];
                    ?>
                    <div class="stat-number"><?php echo $total_grados; ?></div>
                    <div class="stat-label">Grados</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <?php
                    $lugares = $conn->query("SELECT COUNT(*) as total FROM lugares_guardias");
                    $total_lugares = $lugares->fetch(PDO::FETCH_ASSOC)['total'];
                    ?>
                    <div class="stat-number"><?php echo $total_lugares; ?></div>
                    <div class="stat-label">Lugares de Guardia</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <?php
                    $regiones = $conn->query("SELECT COUNT(*) as total FROM regiones");
                    $total_regiones = $regiones->fetch(PDO::FETCH_ASSOC)['total'];
                    ?>
                    <div class="stat-number"><?php echo $total_regiones; ?></div>
                    <div class="stat-label">Regiones</div>
                </div>
            </div>
        </div>
        
        <!-- Segunda fila de estadísticas -->
        <div class="row stats-row">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <?php
                    $tipos_ausencias = $conn->query("SELECT COUNT(*) as total FROM tipos_ausencias");
                    $total_tipos_ausencias = $tipos_ausencias->fetch(PDO::FETCH_ASSOC)['total'];
                    ?>
                    <div class="stat-number"><?php echo $total_tipos_ausencias; ?></div>
                    <div class="stat-label">Tipos de Ausencias</div>
                </div>
            </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animación de entrada para las tarjetas
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.config-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 150);
            });
        });
    </script>
</body>
</html>