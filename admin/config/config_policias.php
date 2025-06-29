<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../cnx/db_connect.php';
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
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .config-container {
            padding: 40px 0;
        }
        
        .config-header {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .config-header h1 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .config-header p {
            color: #7f8c8d;
            font-size: 1.1em;
            margin: 0;
        }
        
        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .config-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .config-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s;
        }
        
        .config-card:hover::before {
            left: 100%;
        }
        
        .config-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            border-color: #3498db;
        }
        
        .config-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5em;
            color: white;
            transition: all 0.3s ease;
        }
        
        .config-card:hover .config-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .config-title {
            font-size: 1.4em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .config-description {
            color: #7f8c8d;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .config-btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .config-btn:hover {
            background: linear-gradient(135deg, #2980b9, #1f5f8b);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .back-btn {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .back-btn:hover {
            background: linear-gradient(135deg, #7f8c8d, #6c7b7d);
            color: white;
            transform: translateY(-2px);
        }
        
        .stats-row {
            margin-top: 30px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: 700;
            color: #3498db;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .config-grid {
                grid-template-columns: 1fr;
            }
            
            .config-container {
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container config-container">
        <!-- Botón de regreso -->
        <a href="../index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Volver al Dashboard
        </a>
        
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
        </div>
        
        <!-- Estadísticas rápidas -->
        <div class="row stats-row">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <?php
                    $especialidades = $conn->query("SELECT COUNT(*) as total FROM especialidades");
                    $total_especialidades = $especialidades->fetch_assoc()['total'];
                    ?>
                    <div class="stat-number"><?php echo $total_especialidades; ?></div>
                    <div class="stat-label">Especialidades</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <?php
                    $grados = $conn->query("SELECT COUNT(*) as total FROM grados");
                    $total_grados = $grados->fetch_assoc()['total'];
                    ?>
                    <div class="stat-number"><?php echo $total_grados; ?></div>
                    <div class="stat-label">Grados</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <?php
                    $lugares = $conn->query("SELECT COUNT(*) as total FROM lugares_guardias");
                    $total_lugares = $lugares->fetch_assoc()['total'];
                    ?>
                    <div class="stat-number"><?php echo $total_lugares; ?></div>
                    <div class="stat-label">Lugares de Guardia</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <?php
                    $regiones = $conn->query("SELECT COUNT(*) as total FROM regiones");
                    $total_regiones = $regiones->fetch_assoc()['total'];
                    ?>
                    <div class="stat-number"><?php echo $total_regiones; ?></div>
                    <div class="stat-label">Regiones</div>
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