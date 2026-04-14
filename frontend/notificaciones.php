<?php
// notificaciones.php - Fragmento para integrarse dentro del menú principal
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../backend/conexion.php';

if (!isset($_SESSION['id_sesion']) || !isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener alertas de inventario
$alertas = [];
try {
    $stmt = $conexion->query("SELECT * FROM generar_alertas_inventario()");
    $alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $alertas = [];
}

// Obtener notificaciones del sistema
$notificaciones_sistema = [];
try {
    $stmt = $conexion->prepare("
        SELECT id_notificacion, titulo, mensaje, tipo, fecha_creacion, leida, fecha_lectura
        FROM notificaciones_sistema
        WHERE id_usuario = :id_usuario
        ORDER BY fecha_creacion DESC
    ");
    $stmt->execute([':id_usuario' => $id_usuario]);
    $notificaciones_sistema = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $notificaciones_sistema = [];
}

$total_no_leidas = 0;
foreach ($notificaciones_sistema as $n) {
    if (!$n['leida']) $total_no_leidas++;
}

function getIconoAlerta($tipo) {
    switch ($tipo) {
        case 'VENCIMIENTO': return 'event_busy';
        case 'STOCK_CRITICO': return 'warning';
        case 'STOCK_AGOTADO': return 'inventory';
        case 'RECALL': return 'skull_crossbones';
        default: return 'notifications';
    }
}

function getColorAlerta($tipo) {
    switch ($tipo) {
        case 'VENCIMIENTO': return 'danger';
        case 'STOCK_CRITICO': return 'warning';
        case 'STOCK_AGOTADO': return 'dark';
        case 'RECALL': return 'danger';
        default: return 'secondary';
    }
}
?>

<!-- CONTENIDO EXCLUSIVO PARA EL ÁREA PRINCIPAL (sin html, head o body) -->
<div class="notifications-wrapper p-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
        <h2 class="h3 mb-0">
            <span class="material-symbols-rounded align-middle">notifications_active</span>
            Notificaciones
            <?php if ($total_no_leidas > 0): ?>
                <span class="badge bg-danger rounded-pill ms-2"><?php echo $total_no_leidas; ?> nuevas</span>
            <?php endif; ?>
        </h2>
        <div class="d-flex gap-2 mt-2 mt-sm-0">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-secondary active filtro-btn" data-filtro="todo">Todas</button>
                <button type="button" class="btn btn-sm btn-outline-secondary filtro-btn" data-filtro="inventario">Inventario</button>
                <button type="button" class="btn btn-sm btn-outline-secondary filtro-btn" data-filtro="sistema">Sistema</button>
            </div>
            <?php if ($total_no_leidas > 0): ?>
                <button class="btn btn-sm btn-outline-primary" id="marcarTodasLeidasBtn">
                    <span class="material-symbols-rounded fs-6">done_all</span> Marcar todas
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sección Inventario -->
    <div class="seccion-notificaciones mb-5" data-tipo="inventario">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="material-symbols-rounded">inventory_2</span>
            <h5 class="fw-semibold m-0">Alertas de Inventario</h5>
            <span class="badge bg-secondary rounded-pill"><?php echo count($alertas); ?></span>
        </div>
        <?php if (empty($alertas)): ?>
            <div class="alert alert-light text-center py-5">
                <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                <p class="mb-0">No hay alertas de inventario activas.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($alertas as $alerta): ?>
                    <div class="col-12">
                        <div class="card shadow-sm border-0 rounded-4 h-100">
                            <div class="card-body d-flex gap-3">
                                <div class="flex-shrink-0">
                                    <div class="rounded-circle bg-<?php echo getColorAlerta($alerta['tipo_alerta']); ?> bg-opacity-10 p-2" style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;">
                                        <span class="material-symbols-rounded text-<?php echo getColorAlerta($alerta['tipo_alerta']); ?>">
                                            <?php echo getIconoAlerta($alerta['tipo_alerta']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start">
                                        <h6 class="mb-1 fw-bold">
                                            <?php if ($alerta['tipo_alerta'] == 'RECALL'): ?>
                                                Alerta Sanitaria: <?php echo htmlspecialchars($alerta['numero_alerta']); ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($alerta['medicamento_nombre']); ?>
                                            <?php endif; ?>
                                        </h6>
                                        <span class="badge bg-<?php echo getColorAlerta($alerta['tipo_alerta']); ?> bg-opacity-25 text-<?php echo getColorAlerta($alerta['tipo_alerta']); ?> px-3 py-1 rounded-pill">
                                            <?php echo str_replace('_', ' ', $alerta['tipo_alerta']); ?>
                                        </span>
                                    </div>
                                    <p class="text-muted small mb-2">
                                        <?php if ($alerta['tipo_alerta'] == 'VENCIMIENTO'): ?>
                                            Lote: <?php echo htmlspecialchars($alerta['numero_lote']); ?> | Vence en <?php echo $alerta['dias_restantes']; ?> días (<?php echo date('d/m/Y', strtotime($alerta['fecha_vencimiento'])); ?>)
                                        <?php elseif ($alerta['tipo_alerta'] == 'STOCK_CRITICO'): ?>
                                            Lote: <?php echo htmlspecialchars($alerta['numero_lote']); ?> | Stock actual: <?php echo $alerta['cantidad']; ?> | Mínimo: <?php echo $alerta['stock_minimo']; ?>
                                        <?php elseif ($alerta['tipo_alerta'] == 'STOCK_AGOTADO'): ?>
                                            Lote: <?php echo htmlspecialchars($alerta['numero_lote']); ?> | Stock AGOTADO
                                        <?php elseif ($alerta['tipo_alerta'] == 'RECALL'): ?>
                                            <?php echo htmlspecialchars($alerta['entidad_emisora']); ?> - Riesgo: <?php echo $alerta['nivel_riesgo']; ?><br>
                                            <?php echo htmlspecialchars(substr($alerta['medicamento_nombre'], 0, 120)); ?>
                                        <?php endif; ?>
                                    </p>
                                    <div>
                                        <a href="menuprincipal.php?mod=<?php echo htmlspecialchars($alerta['url_destino']); ?>" class="btn btn-sm btn-link ps-0 text-decoration-none">
                                            <span class="material-symbols-rounded fs-6">arrow_forward</span> Ver detalles
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sección Sistema -->
    <div class="seccion-notificaciones" data-tipo="sistema">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="material-symbols-rounded">computer</span>
            <h5 class="fw-semibold m-0">Notificaciones del Sistema</h5>
            <span class="badge bg-secondary rounded-pill"><?php echo count($notificaciones_sistema); ?></span>
        </div>
        <?php if (empty($notificaciones_sistema)): ?>
            <div class="alert alert-light text-center py-5">
                <i class="fas fa-bell-slash fa-2x mb-2 text-muted"></i>
                <p class="mb-0">No hay notificaciones del sistema.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($notificaciones_sistema as $notif): ?>
                    <div class="col-12">
                        <div class="card shadow-sm border-0 rounded-4 <?php echo $notif['leida'] ? 'bg-light' : 'border-start border-primary border-3'; ?>">
                            <div class="card-body d-flex gap-3">
                                <div class="flex-shrink-0">
                                    <div class="rounded-circle bg-<?php echo $notif['leida'] ? 'secondary' : 'primary'; ?> bg-opacity-10 p-2" style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;">
                                        <span class="material-symbols-rounded text-<?php echo $notif['leida'] ? 'secondary' : 'primary'; ?>">
                                            <?php echo $notif['tipo'] == 'pago' ? 'payments' : 'info'; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start">
                                        <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($notif['titulo']); ?></h6>
                                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($notif['fecha_creacion'])); ?></small>
                                    </div>
                                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($notif['mensaje'])); ?></p>
                                    <?php if (!$notif['leida']): ?>
                                        <button class="btn btn-sm btn-outline-secondary marcar-leida-sistema" data-id="<?php echo $notif['id_notificacion']; ?>">
                                            <span class="material-symbols-rounded fs-6">check_circle</span> Marcar como leída
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-success bg-opacity-25 text-success">Leída el <?php echo date('d/m/Y H:i', strtotime($notif['fecha_lectura'])); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$notif['leida']): ?>
                                    <button class="btn-marcar-leida-sistema-icon btn btn-link text-secondary" data-id="<?php echo $notif['id_notificacion']; ?>" title="Marcar como leída">
                                        <span class="material-symbols-rounded">done</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Filtrado de secciones
    const filtroBtns = document.querySelectorAll('.filtro-btn');
    const secciones = document.querySelectorAll('.seccion-notificaciones');
    
    filtroBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filtroBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const filtro = this.getAttribute('data-filtro');
            secciones.forEach(sec => {
                if (filtro === 'todo' || sec.getAttribute('data-tipo') === filtro) {
                    sec.style.display = '';
                } else {
                    sec.style.display = 'none';
                }
            });
        });
    });

    // Función para marcar una notificación como leída
    function marcarLeida(idNotif) {
        fetch('../backend/marcar_notificacion_leida.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_notificacion: idNotif })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) location.reload();
            else alert('Error al marcar como leída');
        })
        .catch(err => console.error(err));
    }

    // Botones individuales (texto e icono)
    document.querySelectorAll('.marcar-leida-sistema, .btn-marcar-leida-sistema-icon').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const id = btn.getAttribute('data-id');
            if (id) marcarLeida(id);
        });
    });

    // Marcar todas
    const marcarTodasBtn = document.getElementById('marcarTodasLeidasBtn');
    if (marcarTodasBtn) {
        marcarTodasBtn.addEventListener('click', () => {
            if (confirm('¿Marcar todas las notificaciones del sistema como leídas?')) {
                fetch('../backend/limpiar_notificaciones.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) location.reload();
                    else alert('Error al marcar todas');
                });
            }
        });
    }
</script>