<?php
// sesiones.php - Módulo de sesiones activas
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../backend/conexion.php';

if (!isset($_SESSION['id_sesion']) || !isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

// Solo administradores (ya validado por el menú, pero reforzamos)
$id_usuario_actual = $_SESSION['id_usuario'];

// Obtener filtros
$filtro_usuario = $_GET['usuario'] ?? '';
$filtro_ip = $_GET['ip'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Construir consulta: sesiones activas (o que no tengan fecha_cierre)
$sql = "SELECT s.*, u.nombre as usuario_nombre, u.usuario as usuario_login
        FROM sesiones s
        LEFT JOIN usuarios u ON s.id_usuario = u.id_usuario
        WHERE s.activa = TRUE AND s.fecha_cierre IS NULL
        AND (s.fecha_expiracion > NOW() OR s.fecha_expiracion IS NULL)"; // Solo no expiradas
$params = [];

if (!empty($filtro_usuario)) {
    $sql .= " AND (u.nombre ILIKE :usuario OR u.usuario ILIKE :usuario)";
    $params[':usuario'] = "%$filtro_usuario%";
}
if (!empty($filtro_ip)) {
    $sql .= " AND s.ip ILIKE :ip";
    $params[':ip'] = "%$filtro_ip%";
}
if (!empty($fecha_desde)) {
    $sql .= " AND s.fecha_inicio >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde . ' 00:00:00';
}
if (!empty($fecha_hasta)) {
    $sql .= " AND s.fecha_inicio <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta . ' 23:59:59';
}

$sql .= " ORDER BY s.fecha_inicio DESC";
$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$sesiones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar total de sesiones activas
$total_activas = count($sesiones);

// Obtener lista de usuarios para filtro rápido (solo con sesiones activas)
$stmt_usu = $conexion->query("
    SELECT DISTINCT u.id_usuario, u.nombre, u.usuario
    FROM sesiones s
    JOIN usuarios u ON s.id_usuario = u.id_usuario
    WHERE s.activa = TRUE AND s.fecha_cierre IS NULL
    ORDER BY u.nombre
");
$usuarios_filtro = $stmt_usu->fetchAll(PDO::FETCH_ASSOC);

// Función para determinar si una sesión está expirada (por fecha)
function sesionExpirada($fecha_expiracion) {
    if (!$fecha_expiracion) return false;
    return strtotime($fecha_expiracion) < time();
}
?>

<div class="sesiones-container">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
        <h2 class="h3">
            <span class="material-symbols-rounded align-middle">vpn_key</span>
            Sesiones Activas
            <span class="badge bg-primary rounded-pill ms-2"><?php echo $total_activas; ?> activas</span>
        </h2>
        <div>
            <button class="btn btn-outline-danger btn-sm" id="limpiarExpiradasBtn" title="Cerrar sesiones expiradas">
                <span class="material-symbols-rounded fs-6">delete_sweep</span> Limpiar expiradas
            </button>
            <button class="btn btn-outline-secondary btn-sm ms-2" onclick="location.reload()">
                <span class="material-symbols-rounded fs-6">refresh</span> Refrescar
            </button>
        </div>
    </div>

    <!-- Tarjeta de resumen -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="material-symbols-rounded text-primary">devices</span>
                            <h5 class="mt-2 mb-0"><?php echo $total_activas; ?></h5>
                            <small class="text-muted">Sesiones activas</small>
                        </div>
                        <div>
                            <span class="material-symbols-rounded text-success">check_circle</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="material-symbols-rounded text-warning">schedule</span>
                            <h5 class="mt-2 mb-0"><?php echo count(array_filter($sesiones, function($s) { return sesionExpirada($s['fecha_expiracion']); })); ?></h5>
                            <small class="text-muted">Próximas a expirar</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="material-symbols-rounded text-info">groups</span>
                            <h5 class="mt-2 mb-0"><?php echo count(array_unique(array_column($sesiones, 'id_usuario'))); ?></h5>
                            <small class="text-muted">Usuarios conectados</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3" id="formFiltros">
                <input type="hidden" name="mod" value="sesiones">
                <div class="col-md-3">
                    <label class="form-label small">Usuario</label>
                    <select name="usuario" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($usuarios_filtro as $u): ?>
                            <option value="<?php echo htmlspecialchars($u['usuario']); ?>" <?php echo ($filtro_usuario == $u['usuario']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['nombre'] . ' (' . $u['usuario'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Dirección IP</label>
                    <input type="text" name="ip" class="form-control" placeholder="Ej: 192.168.1." value="<?php echo htmlspecialchars($filtro_ip); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    <a href="?mod=sesiones" class="btn btn-link ms-2">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de sesiones -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Usuario</th>
                            <th>IP</th>
                            <th>User Agent</th>
                            <th>Inicio de sesión</th>
                            <th>Expira</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sesiones)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-door-open fa-2x mb-2 d-block"></i>
                                    No hay sesiones activas en este momento.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sesiones as $ses): 
                                $expirada = sesionExpirada($ses['fecha_expiracion']);
                                $es_sesion_actual = ($ses['id_sesion'] == $_SESSION['id_sesion']);
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($ses['usuario_nombre']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($ses['usuario_login']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($ses['ip'] ?? '-'); ?></td>
                                    <td class="text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($ses['user_agent']); ?>">
                                        <?php echo htmlspecialchars(substr($ses['user_agent'] ?? '', 0, 80)); ?>
                                    </td>
                                    <td nowrap><?php echo date('d/m/Y H:i:s', strtotime($ses['fecha_inicio'])); ?></td>
                                    <td nowrap>
                                        <?php echo $ses['fecha_expiracion'] ? date('d/m/Y H:i:s', strtotime($ses['fecha_expiracion'])) : 'Nunca'; ?>
                                        <?php if ($expirada): ?>
                                            <span class="badge bg-danger ms-1">Expirada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($expirada): ?>
                                            <span class="badge bg-warning">Caducada</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Activa</span>
                                        <?php endif; ?>
                                        <?php if ($es_sesion_actual): ?>
                                            <span class="badge bg-info">Sesión actual</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$es_sesion_actual && !$expirada): ?>
                                            <button class="btn btn-sm btn-outline-danger cerrar-sesion" data-id="<?php echo $ses['id_sesion']; ?>" data-usuario="<?php echo htmlspecialchars($ses['usuario_nombre']); ?>">
                                                <span class="material-symbols-rounded fs-6">logout</span> Cerrar
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled title="No se puede cerrar la sesión actual o ya expiró">
                                                <span class="material-symbols-rounded fs-6">block</span>
                                            </button>
                                        <?php endif; ?>
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

<!-- Modal de confirmación para cerrar sesión -->
<div class="modal fade" id="modalConfirmarCierre" tabindex="-1" data-bs-backdrop="false" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar cierre de sesión</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                ¿Está seguro de que desea cerrar la sesión de <strong id="nombreUsuarioCerrar"></strong>?
                <br><small>El usuario será desconectado inmediatamente.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmarCierreBtn">Cerrar sesión</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Eliminar backdrop del modal */
    .modal-backdrop {
        display: none !important;
    }
    body.modal-open {
        overflow: auto !important;
        padding-right: 0 !important;
    }
</style>

<script>
    // Variables para el cierre de sesión
    let sesionIdACerrar = null;

    // Al hacer clic en "Cerrar" de una fila
    document.querySelectorAll('.cerrar-sesion').forEach(btn => {
        btn.addEventListener('click', function() {
            sesionIdACerrar = this.getAttribute('data-id');
            const nombre = this.getAttribute('data-usuario');
            document.getElementById('nombreUsuarioCerrar').innerText = nombre;
            const modal = new bootstrap.Modal(document.getElementById('modalConfirmarCierre'));
            modal.show();
        });
    });

    // Confirmar cierre
    document.getElementById('confirmarCierreBtn').addEventListener('click', function() {
        if (!sesionIdACerrar) return;
        fetch('../backend/cerrar_sesion_remota.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_sesion: sesionIdACerrar })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Recargar la página para actualizar la tabla
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'No se pudo cerrar la sesión'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error de conexión');
        });
    });

    // Limpiar sesiones expiradas (cerrar todas las que ya pasaron su fecha)
    document.getElementById('limpiarExpiradasBtn').addEventListener('click', function() {
        if (confirm('¿Desea cerrar todas las sesiones expiradas? Esto no afectará las sesiones activas vigentes.')) {
            fetch('../backend/limpiar_sesiones_expiradas.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error al limpiar sesiones expiradas');
                }
            });
        }
    });
</script>