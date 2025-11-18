<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit();
}
require_once '../../cnx/db_connect.php';
require_once '../inc/header.php';
$_GET['page'] = 'reportes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes del Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .page-title { font-weight: 700; color: #0d3d5c; }
        .report-card { border: none; border-radius: 14px; box-shadow: 0 8px 24px rgba(13,61,92,0.08); cursor: pointer; transition: transform .15s ease, box-shadow .15s ease; }
        .report-card:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(13,61,92,0.12); }
        .text-purple { color: #6f42c1; }
        .card-header.bg-gradient { background: linear-gradient(45deg, #104c75, #0d3d5c); color: #fff; }
    </style>
    <script>
        function loadHtml(endpoint, params) {
            const url = new URL(endpoint, window.location.href);
            if (params) Object.keys(params).forEach(k => url.searchParams.append(k, params[k]));
            fetch(url.toString()).then(r => r.json()).then(d => {
                const c = document.getElementById('report-content');
                c.innerHTML = d.html || '<div class="alert alert-danger">Error al cargar datos</div>';
            }).catch(() => {
                document.getElementById('report-content').innerHTML = '<div class="alert alert-danger">No se pudo obtener el reporte</div>';
            });
        }
        function showDateModal(tipo) {
            const m = new bootstrap.Modal(document.getElementById('dateModal'));
            document.getElementById('dateModalApply').onclick = function() {
                const f = document.getElementById('fechaInput').value;
                if (!f) return;
                if (tipo === 'guardias_por_fecha') loadHtml('ajax_guardias.php', { fecha: f });
                document.getElementById('current-report-title').textContent = 'Guardias del ' + f.split('-').reverse().join('/');
                m.hide();
                window.scrollTo({ top: document.getElementById('report-anchor').offsetTop - 60, behavior: 'smooth' });
            };
            m.show();
        }
        function showRangeModal(tipo) {
            const m = new bootstrap.Modal(document.getElementById('rangeModal'));
            document.getElementById('rangeModalApply').onclick = function() {
                const fi = document.getElementById('fechaInicio').value;
                const ff = document.getElementById('fechaFin').value;
                if (!fi || !ff) return;
                if (tipo === 'servicios_periodo') {
                    loadHtml('ajax_servicios.php', { fecha_inicio: fi, fecha_fin: ff });
                    document.getElementById('current-report-title').textContent = 'Servicios del ' + fi.split('-').reverse().join('/') + ' al ' + ff.split('-').reverse().join('/');
                }
                m.hide();
                window.scrollTo({ top: document.getElementById('report-anchor').offsetTop - 60, behavior: 'smooth' });
            };
            m.show();
        }
    </script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../inc/sidebar.php'; ?>
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="page-title mb-0"><i class="fas fa-chart-bar"></i> Reportes del Sistema</h1>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="card report-card" onclick="location.href='../guardias/generar_guardia_interface.php'">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-word fa-3x text-primary mb-3"></i>
                                    <h5>Orden del Día</h5>
                                    <p class="text-muted">Generar orden del día con personal de guardia</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card report-card" onclick="loadHtml('ajax_deshabilitados.php')">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                                    <h5>Policías Deshabilitados</h5>
                                    <p class="text-muted">Personal que ha sido deshabilitado del sistema</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card report-card" onclick="loadHtml('ajax_ausentes.php')">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-times fa-3x text-danger mb-3"></i>
                                    <h5>Ausentes Actuales</h5>
                                    <p class="text-muted">Personal actualmente ausente del servicio</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card report-card" onclick="location.href='../guardias/index.php'">
                                <div class="card-body text-center">
                                    <i class="fas fa-list-ol fa-3x text-info mb-3"></i>
                                    <h5>Lista de Guardias</h5>
                                    <p class="text-muted">Orden actual de rotación de guardias</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card report-card" onclick="showDateModal('guardias_por_fecha')">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-day fa-3x text-purple mb-3"></i>
                                    <h5>Guardias por Día</h5>
                                    <p class="text-muted">Consultar guardias por fecha</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card report-card" onclick="showRangeModal('servicios_periodo')">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-alt fa-3x text-warning mb-3"></i>
                                    <h5>Servicios por Período</h5>
                                    <p class="text-muted">Servicios programados en un rango de fechas</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card report-card" onclick="loadHtml('ajax_junta_medica.php')">
                                <div class="card-body text-center">
                                    <i class="fas fa-notes-medical fa-3x text-secondary mb-3"></i>
                                    <h5>Junta Médica</h5>
                                    <p class="text-muted">Ausencias por junta médica activas</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="report-anchor"></div>
                    <div class="card mt-2">
                        <div class="card-header bg-gradient d-flex justify-content-between align-items-center">
                            <span id="current-report-title">Selecciona un reporte</span>
                            <div>
                                <button class="btn btn-sm btn-light" onclick="document.getElementById('report-content').innerHTML=''">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body" id="report-content"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="dateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Seleccionar fecha</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="date" class="form-control" id="fechaInput">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="dateModalApply">Aplicar</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="rangeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rango de fechas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Inicio</label>
                            <input type="date" class="form-control" id="fechaInicio">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fin</label>
                            <input type="date" class="form-control" id="fechaFin">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="rangeModalApply">Aplicar</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>