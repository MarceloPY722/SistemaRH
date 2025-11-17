<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: ../../index.php'); exit(); }
require_once '../../cnx/db_connect.php';

$stmt = $conn->prepare('SELECT rol FROM usuarios WHERE id = ?');
$stmt->execute([$_SESSION['usuario_id']]);
$usr = $stmt->fetch();
if (!$usr || $usr['rol'] !== 'SUPERADMIN') { header('Location: ../../index.php'); exit(); }

$action = isset($_GET['action']) ? $_GET['action'] : '';

function output_csv_headers($filename) {
    if (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo "\xEF\xBB\xBF";
}

if ($action === 'sample') {
    output_csv_headers('ejemplo_policias.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Legajo','CIN','Nombre','Apellido','Genero','Grado','Especialidad','Cargo','Comisionamiento','Telefono','Region','LugarGuardia','Observaciones']);
    fputcsv($out, ['', '12345678', 'Juan', 'Pérez', 'MASCULINO', 'OFICIAL', 'Investigaciones', 'Policía', 'VENTANILLA', '099123456', 'Asunción', 'Comisaría 1ª', 'Sin observaciones']);
    fputcsv($out, ['', '87654321', 'Ana', 'Gómez', 'FEMENINO', 'SUBOFICIAL', '', 'Policía', '', '098765432', 'Central', 'Comisaría 2ª', '']);
    fclose($out);
    exit;
}

if ($action === 'export') {
    output_csv_headers('policias_export.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Legajo','CIN','Nombre','Apellido','Genero','Grado','Especialidad','Cargo','Comisionamiento','Telefono','Region','LugarGuardia','Observaciones']);
    $sql = "SELECT p.legajo, p.cin, p.nombre, p.apellido, p.genero, tg.nombre AS grado, e.nombre AS especialidad, p.cargo, p.comisionamiento, p.telefono, r.nombre AS region, lg.nombre AS lugar_guardia, p.observaciones
            FROM policias p
            LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
            LEFT JOIN especialidades e ON p.especialidad_id = e.id
            LEFT JOIN regiones r ON p.region_id = r.id
            LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
            ORDER BY p.legajo ASC";
    foreach ($conn->query($sql) as $row) {
        fputcsv($out, [
            $row['legajo'], $row['cin'], $row['nombre'], $row['apellido'], $row['genero'],
            $row['grado'], $row['especialidad'], $row['cargo'], $row['comisionamiento'], $row['telefono'],
            $row['region'], $row['lugar_guardia'], $row['observaciones']
        ]);
    }
    fclose($out);
    exit;
}

$resultado_importacion = [];
$errores_importacion = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['importar']) && isset($_FILES['archivo_csv'])) {
    $file = $_FILES['archivo_csv'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($file['tmp_name'], 'r');
        $rowIndex = 0;
        while (($data = fgetcsv($handle, 10000, ',')) !== false) {
            $rowIndex++;
            if ($rowIndex === 1) { continue; }
            $legajo = trim($data[0] ?? '');
            $cin = trim($data[1] ?? '');
            $nombre = trim($data[2] ?? '');
            $apellido = trim($data[3] ?? '');
            $genero = trim($data[4] ?? '');
            $grado_txt = trim($data[5] ?? '');
            $especialidad_txt = trim($data[6] ?? '');
            $cargo = trim($data[7] ?? '');
            $comisionamiento = trim($data[8] ?? '');
            $telefono = trim($data[9] ?? '');
            $region_txt = trim($data[10] ?? '');
            $lugar_txt = trim($data[11] ?? '');
            $observaciones = trim($data[12] ?? '');

            if ($cin === '' || $nombre === '' || $apellido === '' || $genero === '') {
                $errores_importacion[] = 'Fila '.$rowIndex.': faltan campos obligatorios';
                continue;
            }

            $checkCin = $conn->prepare('SELECT id FROM policias WHERE cin = ? AND activo = 1');
            $checkCin->execute([$cin]);
            if ($checkCin->rowCount() > 0) {
                $errores_importacion[] = 'Fila '.$rowIndex.': CIN duplicado';
                continue;
            }

            $grado_id = null;
            if ($grado_txt !== '') {
                $s = $conn->prepare('SELECT id FROM tipo_grados WHERE UPPER(nombre) = UPPER(?) OR UPPER(abreviatura) = UPPER(?)');
                $s->execute([$grado_txt, $grado_txt]);
                $grado_id = $s->fetchColumn();
            }
            $especialidad_id = null;
            if ($especialidad_txt !== '') {
                $s = $conn->prepare('SELECT id FROM especialidades WHERE UPPER(nombre) = UPPER(?)');
                $s->execute([$especialidad_txt]);
                $especialidad_id = $s->fetchColumn();
            }
            $region_id = null;
            if ($region_txt !== '') {
                $s = $conn->prepare('SELECT id FROM regiones WHERE UPPER(nombre) = UPPER(?)');
                $s->execute([$region_txt]);
                $region_id = $s->fetchColumn();
            }
            $lugar_guardia_id = null;
            if ($lugar_txt !== '') {
                $s = $conn->prepare('SELECT id FROM lugares_guardias WHERE UPPER(nombre) = UPPER(?)');
                $s->execute([$lugar_txt]);
                $lugar_guardia_id = $s->fetchColumn();
            }

            if ($legajo === '') {
                $stmtMax = $conn->query('SELECT MAX(legajo) AS max_legajo FROM policias');
                $maxLegajo = $stmtMax->fetchColumn();
                $legajo = $maxLegajo ? ($maxLegajo + 1) : 1;
            }

            $sql = 'INSERT INTO policias (legajo, nombre, apellido, cin, genero, grado_id, especialidad_id, cargo, comisionamiento, telefono, region_id, lugar_guardia_id, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $stmtIns = $conn->prepare($sql);
            $ok = $stmtIns->execute([$legajo, $nombre, $apellido, $cin, $genero, $grado_id, $especialidad_id, $cargo, $comisionamiento, $telefono, $region_id, $lugar_guardia_id, $observaciones]);
            if ($ok) {
                $resultado_importacion[] = 'Fila '.$rowIndex.': importado';
            } else {
                $errores_importacion[] = 'Fila '.$rowIndex.': error al insertar';
            }
        }
        fclose($handle);
    } else {
        $errores_importacion[] = 'Error al subir archivo';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar/Exportar Policías</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../inc/sidebar.php'; ?>
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="page-title mb-0"><i class="fas fa-file-csv"></i> Importar/Exportar Policías</h1>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="card">
                                <div class="card-header bg-primary text-white"><i class="fas fa-download"></i> Descargar Ejemplo</div>
                                <div class="card-body">
                                    <p>Descargar archivo CSV de ejemplo con las columnas esperadas.</p>
                                    <a href="policias_csv.php?action=sample" class="btn btn-primary"><i class="fas fa-file-download"></i> Descargar ejemplo</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card">
                                <div class="card-header bg-info text-white"><i class="fas fa-table"></i> Exportar Policías</div>
                                <div class="card-body">
                                    <p>Descargar todos los policías registrados en formato CSV.</p>
                                    <a href="policias_csv.php?action=export" class="btn btn-info text-white"><i class="fas fa-download"></i> Descargar datos</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card">
                                <div class="card-header bg-warning"><i class="fas fa-upload"></i> Importar Policías</div>
                                <div class="card-body">
                                    <form method="POST" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label class="form-label">Seleccionar archivo CSV</label>
                                            <input type="file" class="form-control" name="archivo_csv" accept=".csv" required>
                                        </div>
                                        <button type="submit" name="importar" class="btn btn-warning"><i class="fas fa-file-import"></i> Importar</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($resultado_importacion) || !empty($errores_importacion)): ?>
                    <div class="card">
                        <div class="card-header bg-secondary text-white">Resultado de importación</div>
                        <div class="card-body">
                            <?php if (!empty($resultado_importacion)): ?>
                                <div class="alert alert-success">Se importaron <?php echo count($resultado_importacion); ?> filas correctamente.</div>
                            <?php endif; ?>
                            <?php if (!empty($errores_importacion)): ?>
                                <div class="alert alert-danger">Errores: <?php echo count($errores_importacion); ?></div>
                                <ul>
                                    <?php foreach ($errores_importacion as $e): ?>
                                        <li><?php echo htmlspecialchars($e); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>