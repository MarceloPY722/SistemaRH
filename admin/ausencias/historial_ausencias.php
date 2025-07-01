<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../cnx/db_connect.php';

// Búsqueda AJAX
if (isset($_GET['buscar'])) {
    $termino = $_GET['buscar'];
    
    $sql = "SELECT a.*, p.nombre, p.apellido, p.cin, g.nombre as grado, ta.nombre as tipo_ausencia,
                   u.nombre_completo as aprobado_por_nombre
            FROM ausencias a
            JOIN policias p ON a.policia_id = p.id
            JOIN grados g ON p.grado_id = g.id
            JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id
            LEFT JOIN usuarios u ON a.aprobado_por = u.id
            WHERE (p.nombre LIKE ? OR p.apellido LIKE ? OR p.cin LIKE ?)
            ORDER BY a.created_at DESC
            LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    $busqueda = "%$termino%";
    $stmt->bind_param('sss', $busqueda, $busqueda, $busqueda);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ausencias = [];
    while ($row = $result->fetch_assoc()) {
        $ausencias[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($ausencias);
    exit();
}

// Obtener todas las ausencias por defecto
$sql_ausencias = "SELECT a.*, p.nombre, p.apellido, p.cin, g.nombre as grado, ta.nombre as tipo_ausencia,
                         u.nombre_completo as aprobado_por_nombre
                  FROM ausencias a
                  JOIN policias p ON a.policia_id = p.id
                  JOIN grados g ON p.grado_id = g.id
                  JOIN tipos_ausencias ta ON a.tipo_ausencia_id = ta.id
                  LEFT JOIN usuarios u ON a.aprobado_por = u.id
                  ORDER BY a.created_at DESC
                  LIMIT 100";
$result_ausencias = $conn->query($sql_ausencias);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Ausencias - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-bg: #f8f9fa;
            --border-color: #dee2e6;
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .search-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
        }

        .results-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
        }

        .search-input {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 12px 15px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .table-container {
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead {
            background: linear-gradient(135deg, var(--primary-color), #34495e);
            color: white;
        }

        .table thead th {
            border: none;
            font-weight: 500;
            padding: 15px 10px;
        }

        .table tbody tr {
            transition: background-color 0.2s;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .badge {
            font-size: 0.75em;
            padding: 5px 8px;
            border-radius: 6px;
        }

        .badge-warning {
            background-color: var(--warning-color);
        }

        .badge-success {
            background-color: var(--success-color);
        }

        .badge-danger {
            background-color: var(--danger-color);
        }

        .badge-secondary {
            background-color: #6c757d;
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .btn-outline-primary {
            border-color: var(--secondary-color);
            color: var(--secondary-color);
            border-radius: 8px;
        }

        .btn-outline-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
    </style>
</head>
<body>
    <?php include '../inc/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1"><i class="fas fa-history me-2"></i>Historial de Ausencias</h2>
                    <p class="mb-0 opacity-75">Consulta el historial completo de ausencias del personal</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i>Volver a Gestión
                    </a>
                </div>
            </div>
        </div>

        <!-- Búsqueda -->
        <div class="search-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" id="buscarInput" class="form-control search-input" 
                               placeholder="Buscar por nombre, apellido o CI del policía..." autocomplete="off">
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <button type="button" class="btn btn-outline-primary" onclick="limpiarBusqueda()">
                        <i class="fas fa-times me-2"></i>Limpiar
                    </button>
                </div>
            </div>
        </div>

        <!-- Resultados -->
        <div class="results-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">
                    <i class="fas fa-list me-2"></i>Resultados
                </h4>
                <span id="contador" class="badge bg-info">Cargando...</span>
            </div>

            <div id="loading" class="loading" style="display: none;">
                <i class="fas fa-spinner fa-spin me-2"></i>Buscando...
            </div>

            <div class="table-container">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Policía</th>
                            <th>Grado</th>
                            <th>Tipo</th>
                            <th>Fechas</th>
                            <th>Estado</th>
                            <th>Descripción</th>
                            <th>Fecha Registro</th>
                        </tr>
                    </thead>
                    <tbody id="tablaResultados">
                        <!-- Los resultados se cargarán aquí -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let timeoutId;
        let ausenciasIniciales = [];

        // Cargar datos iniciales
        document.addEventListener('DOMContentLoaded', function() {
            cargarAusenciasIniciales();
        });

        // Búsqueda en tiempo real
        document.getElementById('buscarInput').addEventListener('input', function() {
            const termino = this.value.trim();
            
            clearTimeout(timeoutId);
            
            if (termino.length === 0) {
                mostrarAusencias(ausenciasIniciales);
                return;
            }
            
            if (termino.length < 2) {
                return;
            }
            
            document.getElementById('loading').style.display = 'block';
            
            timeoutId = setTimeout(() => {
                buscarAusencias(termino);
            }, 300);
        });

        function cargarAusenciasIniciales() {
            <?php 
            $ausencias_json = [];
            $result_ausencias->data_seek(0);
            while ($row = $result_ausencias->fetch_assoc()) {
                $ausencias_json[] = $row;
            }
            echo 'ausenciasIniciales = ' . json_encode($ausencias_json) . ';';
            ?>
            mostrarAusencias(ausenciasIniciales);
        }

        function buscarAusencias(termino) {
            fetch(`?buscar=${encodeURIComponent(termino)}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loading').style.display = 'none';
                    mostrarAusencias(data);
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('loading').style.display = 'none';
                });
        }

        function mostrarAusencias(ausencias) {
            const tbody = document.getElementById('tablaResultados');
            const contador = document.getElementById('contador');
            
            contador.textContent = `${ausencias.length} registros`;
            
            if (ausencias.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="no-results">
                            <i class="fas fa-search me-2"></i>No se encontraron ausencias
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = ausencias.map(ausencia => {
                const fechaInicio = new Date(ausencia.fecha_inicio).toLocaleDateString('es-ES');
                const fechaFin = ausencia.fecha_fin ? new Date(ausencia.fecha_fin).toLocaleDateString('es-ES') : 'Sin fecha fin';
                const fechaRegistro = new Date(ausencia.created_at).toLocaleDateString('es-ES');
                
                let badgeClass = '';
                switch(ausencia.estado) {
                    case 'PENDIENTE': badgeClass = 'badge-warning'; break;
                    case 'APROBADA': badgeClass = 'badge-success'; break;
                    case 'RECHAZADA': badgeClass = 'badge-danger'; break;
                    case 'COMPLETADA': badgeClass = 'badge-secondary'; break;
                    default: badgeClass = 'bg-secondary';
                }
                
                return `
                    <tr>
                        <td>
                            <div>
                                <strong>${ausencia.apellido}, ${ausencia.nombre}</strong>
                                <br><small class="text-muted">CI: ${ausencia.cin}</small>
                            </div>
                        </td>
                        <td>${ausencia.grado}</td>
                        <td>
                            <span class="badge bg-secondary">${ausencia.tipo_ausencia}</span>
                        </td>
                        <td>
                            <small>
                                <strong>Inicio:</strong> ${fechaInicio}<br>
                                <strong>Fin:</strong> ${fechaFin}
                            </small>
                        </td>
                        <td>
                            <span class="badge ${badgeClass}">${ausencia.estado}</span>
                        </td>
                        <td>
                            <small>${ausencia.descripcion ? ausencia.descripcion.substring(0, 40) + '...' : 'Sin descripción'}</small>
                        </td>
                        <td>
                            <small>${fechaRegistro}</small>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function limpiarBusqueda() {
            document.getElementById('buscarInput').value = '';
            mostrarAusencias(ausenciasIniciales);
        }
    </script>
</body>
</html>