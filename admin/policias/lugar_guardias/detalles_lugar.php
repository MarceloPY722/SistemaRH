<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

require_once '../../../cnx/db_connect.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<p class="text-danger">ID de lugar inválido.</p>';
    exit;
}

$id = $_GET['id'];

try {
    // Obtener información del lugar
    $stmt = $pdo->prepare("SELECT * FROM lugares_guardias WHERE id = ?");
    $stmt->execute([$id]);
    $lugar = $stmt->fetch();
    
    if (!$lugar) {
        echo '<p class="text-danger">Lugar de guardia no encontrado.</p>';
        exit;
    }
    
    // Obtener policías asignados
    $stmt = $pdo->prepare("
        SELECT p.id, p.nombre, p.apellido, p.cin, tg.nombre as grado, e.nombre as especialidad
        FROM policias p
        LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
        LEFT JOIN grados g ON tg.grado_id = g.id
        LEFT JOIN especialidades e ON p.especialidad_id = e.id
        WHERE p.lugar_guardia_id = ? AND p.activo = 1
        ORDER BY g.nivel_jerarquia, tg.nivel_jerarquia, p.nombre
    ");
    $stmt->execute([$id]);
    $policias = $stmt->fetchAll();
    
    // Obtener últimas guardias realizadas
    $stmt = $pdo->prepare("
        SELECT gr.fecha_inicio, gr.fecha_fin, gr.puesto, p.nombre, p.apellido, tg.nombre as grado
        FROM guardias_realizadas gr
        JOIN policias p ON gr.policia_id = p.id
        LEFT JOIN tipo_grados tg ON p.grado_id = tg.id
        LEFT JOIN grados g ON tg.grado_id = g.id
        WHERE gr.lugar_guardia_id = ?
        ORDER BY gr.fecha_inicio DESC
        LIMIT 10
    ");
    $stmt->execute([$id]);
    $guardias = $stmt->fetchAll();
    
} catch (PDOException $e) {
    echo '<p class="text-danger">Error al cargar los detalles: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}
?>

<div class="row">
    <div class="col-md-6">
        <h6><i class="fas fa-info-circle me-2"></i>Información General</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>ID:</strong></td>
                <td><?php echo $lugar['id']; ?></td>
            </tr>
            <tr>
                <td><strong>Nombre:</strong></td>
                <td><?php echo htmlspecialchars($lugar['nombre']); ?></td>
            </tr>
            <tr>
                <td><strong>Zona:</strong></td>
                <td>
                    <span class="badge <?php echo $lugar['zona'] === 'Central' ? 'bg-primary' : 'bg-secondary'; ?>">
                        <?php echo htmlspecialchars($lugar['zona']); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td><strong>Dirección:</strong></td>
                <td><?php echo htmlspecialchars($lugar['direccion'] ?: 'Sin dirección'); ?></td>
            </tr>
            <tr>
                <td><strong>Estado:</strong></td>
                <td>
                    <span class="badge <?php echo $lugar['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                        <?php echo $lugar['activo'] ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td><strong>Creado:</strong></td>
                <td><?php echo date('d/m/Y H:i', strtotime($lugar['created_at'])); ?></td>
            </tr>
            <tr>
                <td><strong>Actualizado:</strong></td>
                <td><?php echo date('d/m/Y H:i', strtotime($lugar['updated_at'])); ?></td>
            </tr>
        </table>
        
        <?php if ($lugar['descripcion']): ?>
        <h6><i class="fas fa-file-text me-2"></i>Descripción</h6>
        <p class="text-muted"><?php echo nl2br(htmlspecialchars($lugar['descripcion'])); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="col-md-6">
        <h6><i class="fas fa-users me-2"></i>Policías Asignados (<?php echo count($policias); ?>)</h6>
        <?php if (count($policias) > 0): ?>
            <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>CIN</th>
                            <th>Nombre</th>
                            <th>Grado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($policias as $policia): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($policia['cin']); ?></td>
                                <td><?php echo htmlspecialchars($policia['nombre'] . ' ' . $policia['apellido']); ?></td>
                                <td><?php echo htmlspecialchars($policia['grado']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">No hay policías asignados a este lugar.</p>
        <?php endif; ?>
        
        <h6><i class="fas fa-shield-alt me-2"></i>Últimas Guardias (<?php echo count($guardias); ?>)</h6>
        <?php if (count($guardias) > 0): ?>
            <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Policía</th>
                            <th>Puesto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($guardias as $guardia): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($guardia['fecha_inicio'])); ?></td>
                                <td>
                                    <small>
                                        <?php echo htmlspecialchars($guardia['grado'] . ' ' . $guardia['nombre'] . ' ' . $guardia['apellido']); ?>
                                    </small>
                                </td>
                                <td><small><?php echo htmlspecialchars($guardia['puesto'] ?: 'Sin especificar'); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">No hay guardias registradas en este lugar.</p>
        <?php endif; ?>
    </div>
</div>