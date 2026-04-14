<?php
// auditoria.php - Módulo de auditoría del sistema (sin backdrop)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../backend/conexion.php';

if (!isset($_SESSION['id_sesion']) || !isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener filtros
$modulo = $_GET['modulo'] ?? '';
$usuario = $_GET['usuario'] ?? '';
$accion = $_GET['accion'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Construir consulta base
$sql = "SELECT a.*, u.nombre as usuario_nombre, u.usuario as usuario_login
        FROM auditoria_cambios a
        LEFT JOIN usuarios u ON a.id_usuario = u.id_usuario
        WHERE 1=1";
$params = [];

if (!empty($modulo)) {
    $sql .= " AND a.tabla_afectada = :modulo";
    $params[':modulo'] = $modulo;
}
if (!empty($usuario)) {
    $sql .= " AND a.id_usuario = :usuario";
    $params[':usuario'] = $usuario;
}
if (!empty($accion)) {
    $sql .= " AND a.accion = :accion";
    $params[':accion'] = $accion;
}
if (!empty($fecha_desde)) {
    $sql .= " AND a.fecha >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde . ' 00:00:00';
}
if (!empty($fecha_hasta)) {
    $sql .= " AND a.fecha <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta . ' 23:59:59';
}

$sql .= " ORDER BY a.fecha DESC LIMIT 500";

$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de módulos únicos para el filtro
$stmtMod = $conexion->query("SELECT DISTINCT tabla_afectada FROM auditoria_cambios ORDER BY tabla_afectada");
$modulos = $stmtMod->fetchAll(PDO::FETCH_COLUMN);

// Obtener lista de usuarios para filtro
$stmtUsu = $conexion->query("SELECT DISTINCT u.id_usuario, u.nombre FROM auditoria_cambios a JOIN usuarios u ON a.id_usuario = u.id_usuario ORDER BY u.nombre");
$usuariosFiltro = $stmtUsu->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas por módulo
$stats = [];
$stmtStats = $conexion->query("
    SELECT tabla_afectada, COUNT(*) as total, 
           SUM(CASE WHEN accion = 'INSERT' THEN 1 ELSE 0 END) as inserts,
           SUM(CASE WHEN accion = 'UPDATE' THEN 1 ELSE 0 END) as updates,
           SUM(CASE WHEN accion = 'DELETE' THEN 1 ELSE 0 END) as deletes
    FROM auditoria_cambios
    GROUP BY tabla_afectada
    ORDER BY total DESC
");
$stats = $stmtStats->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="auditoria-container">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
        <h2 class="h3">
            <span class="material-symbols-rounded align-middle">policy</span>
            Auditoría de Cambios
        </h2>
        <button class="btn btn-outline-secondary btn-sm" onclick="window.location.reload()">
            <span class="material-symbols-rounded fs-6">refresh</span> Refrescar
        </button>
    </div>

    <!-- Tarjetas de resumen por módulo -->
    <div class="row g-3 mb-4">
        <?php foreach ($stats as $stat): ?>
            <div class="col-md-3 col-sm-6">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge bg-primary mb-2"><?php echo strtoupper($stat['tabla_afectada']); ?></span>
                                <h5 class="card-title mb-0"><?php echo $stat['total']; ?> cambios</h5>
                            </div>
                            <span class="material-symbols-rounded text-muted">database</span>
                        </div>
                        <div class="mt-2 small text-muted">
                            <span class="text-success">+<?php echo $stat['inserts']; ?> ins</span> |
                            <span class="text-warning">~<?php echo $stat['updates']; ?> upd</span> |
                            <span class="text-danger">-<?php echo $stat['deletes']; ?> del</span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($stats)): ?>
            <div class="col-12">
                <div class="alert alert-info">No hay registros de auditoría aún. Los triggers se activarán con cambios en los datos.</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="mod" value="auditoria">
                <div class="col-md-3">
                    <label class="form-label small">Módulo (Tabla)</label>
                    <select name="modulo" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($modulos as $m): ?>
                            <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $modulo == $m ? 'selected' : ''; ?>><?php echo htmlspecialchars($m); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Usuario</label>
                    <select name="usuario" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($usuariosFiltro as $u): ?>
                            <option value="<?php echo $u['id_usuario']; ?>" <?php echo $usuario == $u['id_usuario'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Acción</label>
                    <select name="accion" class="form-select">
                        <option value="">Todas</option>
                        <option value="INSERT" <?php echo $accion == 'INSERT' ? 'selected' : ''; ?>>INSERT</option>
                        <option value="UPDATE" <?php echo $accion == 'UPDATE' ? 'selected' : ''; ?>>UPDATE</option>
                        <option value="DELETE" <?php echo $accion == 'DELETE' ? 'selected' : ''; ?>>DELETE</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                </div>
                <div class="col-md-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                    <a href="?mod=auditoria" class="btn btn-link">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de registros -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>Módulo</th>
                            <th>ID Registro</th>
                            <th>Acción</th>
                            <th>Usuario</th>
                            <th>IP</th>
                            <th>Detalles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($registros) === 0): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-search fa-2x mb-2 d-block"></i>
                                    No se encontraron registros de auditoría con los filtros actuales.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($registros as $reg): ?>
                                <tr>
                                    <td nowrap><?php echo date('d/m/Y H:i:s', strtotime($reg['fecha'])); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($reg['tabla_afectada']); ?></span></td>
                                    <td><?php echo $reg['id_registro']; ?></td>
                                    <td>
                                        <?php
                                        $badgeClass = '';
                                        if ($reg['accion'] == 'INSERT') $badgeClass = 'success';
                                        elseif ($reg['accion'] == 'UPDATE') $badgeClass = 'warning';
                                        else $badgeClass = 'danger';
                                        ?>
                                        <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo $reg['accion']; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($reg['usuario_nombre'] ?? 'Sistema'); ?></td>
                                    <td><?php echo htmlspecialchars($reg['ip'] ?? '-'); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info ver-detalle" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalDetalle"
                                                data-anteriores='<?php echo htmlspecialchars(json_encode($reg['datos_anteriores'])); ?>'
                                                data-nuevos='<?php echo htmlspecialchars(json_encode($reg['datos_nuevos'])); ?>'
                                                data-accion="<?php echo $reg['accion']; ?>"
                                                data-tabla="<?php echo $reg['tabla_afectada']; ?>"
                                                data-id="<?php echo $reg['id_registro']; ?>">
                                            <span class="material-symbols-rounded fs-6">visibility</span> Ver
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal sin backdrop (data-bs-backdrop="false" y sin clase modal-backdrop) -->
<div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true" data-bs-backdrop="false" data-bs-keyboard="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle del cambio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>Tabla:</strong> <span id="detalleTabla"></span> | <strong>ID Registro:</strong> <span id="detalleId"></span> | <strong>Acción:</strong> <span id="detalleAccion"></span></p>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Datos Anteriores</h6>
                        <pre id="detalleAnteriores" class="bg-light p-2 rounded" style="max-height: 300px; overflow: auto;"></pre>
                    </div>
                    <div class="col-md-6">
                        <h6>Datos Nuevos</h6>
                        <pre id="detalleNuevos" class="bg-light p-2 rounded" style="max-height: 300px; overflow: auto;"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Llenar modal con datos JSON
    document.querySelectorAll('.ver-detalle').forEach(btn => {
        btn.addEventListener('click', function() {
            const anteriores = this.dataset.anteriores;
            const nuevos = this.dataset.nuevos;
            const accion = this.dataset.accion;
            const tabla = this.dataset.tabla;
            const id = this.dataset.id;
            
            document.getElementById('detalleTabla').innerText = tabla;
            document.getElementById('detalleId').innerText = id;
            document.getElementById('detalleAccion').innerText = accion;
            
            let ant = anteriores && anteriores !== 'null' ? JSON.parse(anteriores) : null;
            let nue = nuevos && nuevos !== 'null' ? JSON.parse(nuevos) : null;
            
            document.getElementById('detalleAnteriores').innerText = ant ? JSON.stringify(ant, null, 2) : 'No hay datos anteriores';
            document.getElementById('detalleNuevos').innerText = nue ? JSON.stringify(nue, null, 2) : 'No hay datos nuevos';
        });
    });
</script>

<!-- Estilo adicional para eliminar cualquier backdrop residual -->
<style>
    .modal-backdrop {
        display: none !important;
    }
    body.modal-open {
        overflow: auto !important;
        padding-right: 0 !important;
    }
</style>