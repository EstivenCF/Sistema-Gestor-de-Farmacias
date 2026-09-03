<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header("Location: " . dirname(__DIR__, 2) . "/frontend/index.php");
    exit();
}

date_default_timezone_set('America/Santo_Domingo');

$base_path = dirname(__DIR__, 2);
require_once $base_path . '/backend/queries/index.php';
require_once $base_path . '/backend/inventario/riesgo_vencimiento_lib.php';

$base_url = '/Sistema-Gestor-de-Farmacias';

$id_lote = isset($_GET['id_lote']) ? (int)$_GET['id_lote'] : 0;
$id_sucursal = isset($_GET['id_sucursal']) ? (int)$_GET['id_sucursal'] : 0;
$paginaHistorial = max(1, (int)($_GET['historial_pagina'] ?? 1));
$limiteHistorial = 6;

$umbrales = obtenerUmbralesVencimiento($conexion);
$lote = null;
$error = null;
$diagnostico = [];
$historialAcciones = [];
$totalHistorial = 0;
$idAccionPrevia = null;

if ($id_lote > 0 && $id_sucursal > 0) {
    $lote = evaluarLoteDetalle($conexion, $id_lote, $id_sucursal, $umbrales);
    if (!$lote) {
        $error = 'No se encontró stock de ese lote en esa sucursal.';
    } else {
        $diagnostico = diagnosticarCausaRaiz($conexion, $lote, $id_sucursal, $umbrales);

        // Historial REAL de acciones ya intentadas sobre este lote (no simulado)
        $stmt = $conexion->prepare("
            SELECT id_accion, tipo_accion, estado, fecha_creacion, fecha_ejecucion, observaciones, valor_recuperado_estimado
            FROM accion_recuperacion
            WHERE id_lote = :id_lote
            ORDER BY fecha_creacion ASC
            LIMIT :limite OFFSET :offset
        ");
        $stmt->bindValue(':id_lote', $id_lote, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limiteHistorial, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($paginaHistorial - 1) * $limiteHistorial, PDO::PARAM_INT);
        $stmt->execute();
        $historialAcciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtTotal = $conexion->prepare("SELECT COUNT(*) FROM accion_recuperacion WHERE id_lote = :id_lote");
        $stmtTotal->execute([':id_lote' => $id_lote]);
        $totalHistorial = (int)$stmtTotal->fetchColumn();

        $stmtUltimaAccion = $conexion->prepare("SELECT id_accion FROM accion_recuperacion WHERE id_lote = :id_lote ORDER BY fecha_creacion DESC LIMIT 1");
        $stmtUltimaAccion->execute([':id_lote' => $id_lote]);
        $idAccionPrevia = $stmtUltimaAccion->fetchColumn() ?: null;
    }
} else {
    $error = 'Falta indicar el lote y la sucursal (id_lote / id_sucursal).';
}

$nombre_usuario_sesion = $_SESSION['nombre'] ?? $_SESSION['usuario'];

$etiquetasTipoAccion = [
    'PROMOCION' => 'Promoción', 'REDISTRIBUCION' => 'Redistribución', 'COMBO' => 'Combo/Paquete',
    'DEVOLUCION_PROVEEDOR' => 'Devolución a proveedor', 'DONACION' => 'Donación',
    'PROVISION_PERDIDA' => 'Provisión de pérdida', 'DESTRUCCION' => 'Destrucción',
];
$coloresEstado = ['PENDIENTE' => 'secondary', 'EN_EJECUCION' => 'info', 'COMPLETADA' => 'success', 'CANCELADA' => 'dark', 'SIN_EFECTO' => 'danger'];
?>

<div class="container-fluid">
    <div class="mb-4">
        <nav class="tarea5-breadcrumb" aria-label="breadcrumb">
            <a href="menuprincipal.php?mod=vencimientos" class="tarea5-breadcrumb-home" title="Vencimientos">
                <span class="material-symbols-rounded">home</span>
            </a>
            <?php if (!$error): ?>
                <span class="tarea5-breadcrumb-sep material-symbols-rounded">chevron_right</span>
                <a href="menuprincipal.php?mod=detalle_riesgo_lote&id_lote=<?php echo $id_lote; ?>&id_sucursal=<?php echo $id_sucursal; ?>" class="tarea5-breadcrumb-link">Detalle de lote</a>
            <?php endif; ?>
            <span class="tarea5-breadcrumb-sep material-symbols-rounded">chevron_right</span>
            <span class="tarea5-breadcrumb-actual">Diagnóstico y contingencia</span>
        </nav>
        <h2 class="mb-0 text-danger">
            <span class="material-symbols-rounded align-middle me-2">troubleshoot</span>
            Diagnóstico de causa raíz y plan de contingencia
        </h2>
        <?php if (!$error): ?>
            <p class="text-muted mb-0">
                Lote <code><?php echo htmlspecialchars($lote['numero_lote']); ?></code> ·
                <?php echo htmlspecialchars($lote['medicamento_nombre']); ?> ·
                <?php echo htmlspecialchars($lote['sucursal_nombre']); ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><span class="material-symbols-rounded align-middle me-1">error</span> <?php echo htmlspecialchars($error); ?></div>
        <a href="menuprincipal.php?mod=vencimientos" class="btn btn-outline-secondary">Volver a Vencimientos</a>
    <?php else: ?>

    <!-- KPIs -->
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <small class="text-muted d-block">Valor en riesgo</small>
                    <h4 class="mb-1 text-danger">RD$ <?php echo number_format($lote['valor_en_riesgo'], 2); ?></h4>
                    <span class="badge bg-<?php echo $lote['nivel_riesgo'] === 'CRITICO' ? 'danger' : ($lote['nivel_riesgo'] === 'MODERADO' ? 'warning text-dark' : 'success'); ?>">
                        Riesgo <?php echo $lote['nivel_riesgo']; ?> · <?php echo $lote['dias_restantes']; ?> días
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <small class="text-muted d-block">Acciones previas aplicadas</small>
                    <h4 class="mb-1"><?php echo count($historialAcciones); ?></h4>
                    <small class="text-muted"><?php echo empty($historialAcciones) ? 'Ninguna todavía' : implode(' + ', array_map(fn($a) => $etiquetasTipoAccion[$a['tipo_accion']] ?? $a['tipo_accion'], $historialAcciones)); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <small class="text-muted d-block">Rotación en esta sucursal</small>
                    <h4 class="mb-1"><?php echo $lote['irv_origen'] === null ? '-' : $lote['irv_origen'] . '%'; ?></h4>
                    <span class="badge bg-<?php echo $lote['sin_demanda_en_red'] ? 'danger' : ($lote['sin_datos_en_red'] ? 'secondary' : 'success'); ?>">
                        <?php echo $lote['sin_demanda_en_red'] ? 'Sin demanda en toda la red' : ($lote['sin_datos_en_red'] ? 'Sin datos suficientes' : 'Umbral cumplido en algún punto de la red'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- DIAGNÓSTICO -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <h6 class="mb-1">Diagnóstico automático de causa</h6>
                    <small class="text-muted d-block mb-3">Evaluación basada en rotación, precio y sustitutos disponibles</small>

                    <?php foreach ($diagnostico as $clave => $c): ?>
                        <div class="d-flex justify-content-between align-items-start border rounded-3 p-2 mb-2 <?php echo $c['detectada'] ? 'border-danger-subtle bg-danger bg-opacity-10' : ''; ?>">
                            <div class="pe-2">
                                <div class="fw-semibold small"><?php echo ucwords(str_replace('_', ' ', strtolower($clave))); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($c['detalle']); ?></small>
                            </div>
                            <?php if (!empty($c['no_determinable'])): ?>
                                <span class="badge bg-secondary">No determinable</span>
                            <?php elseif ($c['detectada']): ?>
                                <span class="badge bg-danger">Detectada</span>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border">No aplica</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- HISTORIAL -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <h6 class="mb-1">Historial de acciones sobre este lote</h6>
                    <small class="text-muted d-block mb-3">Evita repetir una estrategia que ya no funcionó</small>

                    <?php if (empty($historialAcciones)): ?>
                        <p class="text-muted small mb-0">Todavía no se ha intentado ninguna acción sobre este lote.</p>
                    <?php else: ?>
                        <?php foreach ($historialAcciones as $a): ?>
                            <div class="border-start border-3 border-<?php echo $coloresEstado[$a['estado']] ?? 'secondary'; ?> ps-3 mb-3">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold small"><?php echo $etiquetasTipoAccion[$a['tipo_accion']] ?? $a['tipo_accion']; ?></span>
                                    <small class="text-muted"><?php echo date('d/m/Y', strtotime($a['fecha_creacion'])); ?></small>
                                </div>
                                <span class="badge bg-<?php echo $coloresEstado[$a['estado']] ?? 'secondary'; ?>"><?php echo $a['estado']; ?></span>
                                <?php if ($a['observaciones']): ?><small class="text-muted d-block mt-1"><?php echo htmlspecialchars($a['observaciones']); ?></small><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php $totalPaginasHistorial = max(1, (int)ceil($totalHistorial / $limiteHistorial)); ?>
                        <?php if ($totalPaginasHistorial > 1): ?>
                            <nav class="mt-3" aria-label="Paginación del historial">
                                <ul class="pagination pagination-sm mb-0 justify-content-center">
                                    <?php for ($pagina = 1; $pagina <= $totalPaginasHistorial; $pagina++): ?>
                                        <li class="page-item <?php echo $pagina === $paginaHistorial ? 'active' : ''; ?>">
                                            <a class="page-link" href="menuprincipal.php?mod=diagnostico_causa_raiz&id_lote=<?php echo $id_lote; ?>&id_sucursal=<?php echo $id_sucursal; ?>&historial_pagina=<?php echo $pagina; ?>"><?php echo $pagina; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- PLAN DE CONTINGENCIA -->
    <form id="formContingencia">
        <div class="card border-0 shadow-sm rounded-4 mt-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h6 class="mb-1">Plan de contingencia final</h6>
                        <small class="text-muted">Última instancia antes de que la farmacia absorba la pérdida total.</small>
                    </div>
                    <span class="badge bg-danger bg-opacity-75">Escalamiento nivel 2</span>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="tile-contingencia" data-tipo="COMBO" onclick="seleccionarTipo('COMBO')">
                            <div class="tile-icon bg-info bg-opacity-10 text-info"><span class="material-symbols-rounded">redeem</span></div>
                            <div class="fw-semibold mb-1">Combo / Paquete</div>
                            <small class="text-muted">Agrupar con un producto de alta rotación para forzar salida conjunta.</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="tile-contingencia" data-tipo="DONACION" onclick="seleccionarTipo('DONACION')">
                            <div class="tile-icon bg-success bg-opacity-10 text-success"><span class="material-symbols-rounded">volunteer_activism</span></div>
                            <div class="fw-semibold mb-1">Donación institucional</div>
                            <small class="text-muted">Entrega a hospital u ONG antes del vencimiento. Beneficio fiscal.</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="tile-contingencia" data-tipo="PROVISION_PERDIDA" onclick="seleccionarTipo('PROVISION_PERDIDA')">
                            <div class="tile-icon bg-warning bg-opacity-10 text-warning"><span class="material-symbols-rounded">request_quote</span></div>
                            <div class="fw-semibold mb-1">Provisión de pérdida</div>
                            <small class="text-muted">Registrar la pérdida esperada si ninguna otra opción aplica.</small>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-3 col-md-6" id="campoEntidadReceptora" style="display:none;">
                        <label class="form-label fw-bold text-secondary small">ENTIDAD RECEPTORA</label>
                        <input type="text" class="form-control" id="entidadReceptora" placeholder="Ej. Hospital Regional, Cruz Roja...">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label fw-bold text-secondary small">RESPONSABLE</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($nombre_usuario_sesion); ?>" readonly>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label fw-bold text-secondary small">FECHA LÍMITE DE EJECUCIÓN</label>
                        <input type="date" class="form-control" id="fechaLimite" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold text-secondary small">JUSTIFICACIÓN (obligatoria en escalamiento nivel 2)</label>
                        <textarea class="form-control" id="observaciones" rows="2" required placeholder="Explique por qué se llegó a esta instancia y por qué se eligió esta acción..."></textarea>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-danger" id="btnGuardar">
                        <span class="material-symbols-rounded align-middle me-1" style="font-size:18px;">save</span>
                        Registrar acción de contingencia
                    </button>
                    <a href="menuprincipal.php?mod=detalle_riesgo_lote&id_lote=<?php echo $id_lote; ?>&id_sucursal=<?php echo $id_sucursal; ?>" class="btn btn-outline-secondary">Volver al detalle del lote</a>
                </div>
            </div>
        </div>
    </form>

    <?php endif; ?>
</div>

<style>
.tile-contingencia { border: 1.5px solid #dee2e6; border-radius: 14px; padding: 16px; cursor: pointer; height: 100%; transition: all .15s ease; }
.tile-contingencia:hover { border-color: #dc3545; }
.tile-contingencia.selected { border-color: #dc3545; background: rgba(220,53,69,0.05); box-shadow: 0 0 0 1px #dc3545; }
.tile-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';
const idLote = <?php echo $id_lote; ?>;
const idSucursal = <?php echo $id_sucursal; ?>;
const idAccionPrevia = <?php echo $idAccionPrevia ? $idAccionPrevia : 'null'; ?>;
<?php if (!$error): ?>
const loteInfo = {
    cantidad: <?php echo $lote['cantidad']; ?>,
    valor_en_riesgo: <?php echo $lote['valor_en_riesgo']; ?>,
    nivel_riesgo: '<?php echo $lote['nivel_riesgo']; ?>',
    irv_origen: <?php echo $lote['irv_origen'] === null ? 'null' : $lote['irv_origen']; ?>
};
const causaDetectada = <?php
    $detectadas = array_keys(array_filter($diagnostico, fn($c) => $c['detectada']));
    echo $detectadas ? "'" . $detectadas[0] . "'" : 'null';
?>;
<?php endif; ?>

let tipoSeleccionado = null;

function seleccionarTipo(tipo) {
    tipoSeleccionado = tipo;
    document.querySelectorAll('.tile-contingencia').forEach(t => t.classList.remove('selected'));
    document.querySelector(`.tile-contingencia[data-tipo="${tipo}"]`).classList.add('selected');
    document.getElementById('campoEntidadReceptora').style.display = (tipo === 'DONACION') ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    const fecha = new Date();
    fecha.setDate(fecha.getDate() + 5);
    document.getElementById('fechaLimite').value = fecha.toISOString().split('T')[0];
});

document.getElementById('formContingencia')?.addEventListener('submit', function(e) {
    e.preventDefault();

    if (!tipoSeleccionado) {
        Swal.fire('Falta seleccionar', 'Elija una de las 3 acciones de contingencia.', 'warning');
        return;
    }
    if (tipoSeleccionado === 'DONACION' && !document.getElementById('entidadReceptora').value.trim()) {
        Swal.fire('Falta información', 'Indique la entidad receptora de la donación.', 'warning');
        return;
    }

    const btn = document.getElementById('btnGuardar');
    btn.disabled = true;

    // COMBO reutiliza el mismo mecanismo de descuento que Promoción, así
    // que se guarda primero y se redirige a Ofertas, igual que en la Pantalla #06.
    const marcarCompletada = (tipoSeleccionado === 'DONACION' || tipoSeleccionado === 'PROVISION_PERDIDA');

    const payload = {
        id_lote: idLote,
        id_sucursal_origen: idSucursal,
        tipo_accion: tipoSeleccionado,
        cantidad_afectada: loteInfo.cantidad,
        valor_en_riesgo: loteInfo.valor_en_riesgo,
        nivel_riesgo_al_generar: loteInfo.nivel_riesgo,
        irv_origen_al_generar: loteInfo.irv_origen,
        causa_raiz: causaDetectada,
        fecha_limite: document.getElementById('fechaLimite').value || null,
        prioridad: 'CRITICA',
        observaciones: document.getElementById('observaciones').value,
        entidad_receptora: tipoSeleccionado === 'DONACION' ? document.getElementById('entidadReceptora').value : null,
        id_accion_previa: idAccionPrevia,
        marcar_completada: marcarCompletada
    };

    fetch(`${BASE_URL}/backend/inventario/gestionar_accion_recuperacion.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            if (!data.success) {
                Swal.fire('Error', data.message, 'error');
                return;
            }

            if (tipoSeleccionado === 'COMBO') {
                window.location.href = `menuprincipal.php?mod=ofertas&id_accion=${data.id_accion}`;
                return;
            }

            Swal.fire({
                icon: 'success',
                title: 'Acción de contingencia registrada',
                html: `Quedó registrada como <strong>${marcarCompletada ? 'COMPLETADA' : 'PENDIENTE'}</strong>. Este es el final del proceso estratégico para este lote.`
            }).then(() => {
                window.location.href = `menuprincipal.php?mod=detalle_riesgo_lote&id_lote=${idLote}&id_sucursal=${idSucursal}`;
            });
        })
        .catch(() => {
            btn.disabled = false;
            Swal.fire('Error', 'Error de conexión con el servidor', 'error');
        });
});
</script>
