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
// Opcional: si se llega desde una acción de recuperación ya creada en la Pantalla #04
$id_accion = isset($_GET['id_accion']) ? (int)$_GET['id_accion'] : null;

$umbrales = obtenerUmbralesVencimiento($conexion);
$lote = null;
$error = null;

if ($id_lote > 0 && $id_sucursal > 0) {
    $lote = evaluarLoteDetalle($conexion, $id_lote, $id_sucursal, $umbrales);
    if (!$lote) {
        $error = 'No se encontró stock de ese lote en esa sucursal.';
    }
} else {
    $error = 'Falta indicar el lote y la sucursal de origen (id_lote / id_sucursal).';
}
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
            <span class="tarea5-breadcrumb-actual">Redistribución de stock</span>
        </nav>
        <h2 class="mb-0">
            <span class="material-symbols-rounded align-middle me-2 text-info">swap_horiz</span>
            Redistribución de stock entre sucursales
        </h2>
        <?php if (!$error): ?>
            <p class="text-muted mb-0">
                Lote <code><?php echo htmlspecialchars($lote['numero_lote']); ?></code> ·
                <?php echo htmlspecialchars($lote['medicamento_nombre']); ?> ·
                Existencia disponible en origen: <strong><?php echo $lote['cantidad']; ?> u.</strong>
            </p>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><span class="material-symbols-rounded align-middle me-1">error</span> <?php echo htmlspecialchars($error); ?></div>
        <a href="menuprincipal.php?mod=vencimientos" class="btn btn-outline-secondary">Volver a Vencimientos</a>
    <?php elseif ($lote['sin_demanda_en_red']): ?>
        <div class="alert alert-warning">
            <span class="material-symbols-rounded align-middle me-1">warning</span>
            <strong>Ninguna sucursal cumple el umbral mínimo de rotación (<?php echo $umbrales['venc_irv_umbral_minimo']; ?>%)</strong>, con datos de venta reales, ni siquiera la de origen.
            Redistribuir no resolvería el problema de fondo — daría igual transferirlo o no, porque tampoco se vendería en destino.
        </div>
        <a href="menuprincipal.php?mod=diagnostico_causa_raiz&id_lote=<?php echo $id_lote; ?>&id_sucursal=<?php echo $id_sucursal; ?>" class="btn btn-warning">
            <span class="material-symbols-rounded align-middle me-1">troubleshoot</span>
            Ir al diagnóstico de causa raíz
        </a>
        <a href="menuprincipal.php?mod=generar_accion_recuperacion&id_lote=<?php echo $id_lote; ?>&id_sucursal=<?php echo $id_sucursal; ?>" class="btn btn-outline-secondary">Volver</a>
    <?php else: ?>

    <?php if ($lote['sin_datos_en_red']): ?>
        <div class="alert alert-secondary">
            <span class="material-symbols-rounded align-middle me-1">info</span>
            Ninguna sucursal tiene historial de ventas suficiente para este medicamento todavía, así que no se puede recomendar un destino con datos.
            Puede continuar y elegir la sucursal destino manualmente.
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <label class="form-label fw-bold text-secondary small">SUCURSAL DE ORIGEN</label>
                    <input type="text" class="form-control mb-3" value="<?php echo htmlspecialchars($lote['sucursal_nombre']); ?>" disabled>

                    <label class="form-label fw-bold text-secondary small">SUCURSAL DE DESTINO</label>
                    <select class="form-select mb-3" id="sucursalDestino" onchange="actualizarAdvertenciaDestino()">
                        <option value="">Seleccione...</option>
                        <?php foreach ($lote['rotacion_por_sucursal'] as $suc): ?>
                            <?php if ($suc['id_sucursal'] == $id_sucursal) continue; // no listarse a sí misma como destino ?>
                            <option value="<?php echo $suc['id_sucursal']; ?>" data-irv="<?php echo $suc['irv'] === null ? '' : $suc['irv']; ?>" data-cumple="<?php echo $suc['cumple_umbral'] ? '1' : '0'; ?>">
                                <?php echo htmlspecialchars($suc['sucursal_nombre']); ?>
                                <?php echo $suc['irv'] === null ? ' (sin datos)' : ' — IRV ' . $suc['irv'] . '%'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="advertenciaDestino"></div>

                    <label class="form-label fw-bold text-secondary small">CANTIDAD A TRANSFERIR</label>
                    <div class="input-group mb-1">
                        <input type="number" class="form-control" id="cantidadTransferir" min="1" max="<?php echo $lote['cantidad']; ?>" value="<?php echo $lote['cantidad']; ?>">
                        <span class="input-group-text">de <?php echo $lote['cantidad']; ?> u.</span>
                    </div>
                    <small class="text-muted d-block mb-3">Puede ser una transferencia parcial, no es obligatorio mover el lote completo.</small>

                    <div class="d-flex gap-2 mt-3">
                        <button type="button" class="btn btn-info text-white" id="btnConfirmar" onclick="confirmarTransferencia()">
                            <span class="material-symbols-rounded align-middle me-1" style="font-size:18px;">swap_horiz</span>
                            Confirmar transferencia
                        </button>
                        <a href="menuprincipal.php?mod=detalle_riesgo_lote&id_lote=<?php echo $id_lote; ?>&id_sucursal=<?php echo $id_sucursal; ?>" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <h6 class="mb-1">Rotación por sucursal (últimos <?php echo $umbrales['venc_irv_periodo_dias']; ?> días)</h6>
                    <small class="text-muted d-block mb-3">Umbral mínimo aceptable: <?php echo $umbrales['venc_irv_umbral_minimo']; ?>%</small>
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Sucursal</th><th class="text-center">IRV</th><th class="text-center">Demanda</th></tr></thead>
                        <tbody>
                            <?php foreach ($lote['rotacion_por_sucursal'] as $suc): ?>
                                <tr class="<?php echo $suc['id_sucursal'] === $lote['id_sucursal'] ? 'table-light fw-bold' : ''; ?>">
                                    <td><?php echo htmlspecialchars($suc['sucursal_nombre']); ?><?php echo $suc['id_sucursal'] === $lote['id_sucursal'] ? ' <small class="text-muted">(origen)</small>' : ''; ?></td>
                                    <td class="text-center"><?php echo $suc['irv'] === null ? '-' : $suc['irv'] . '%'; ?></td>
                                    <td class="text-center">
                                        <?php if ($suc['irv'] === null): ?>
                                            <span class="badge bg-secondary">Sin datos</span>
                                        <?php elseif ($suc['cumple_umbral']): ?>
                                            <span class="badge bg-success">Alta</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Baja</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';
const idLote = <?php echo $id_lote; ?>;
const idSucursalOrigen = <?php echo $id_sucursal; ?>;
const idAccion = <?php echo $id_accion ? $id_accion : 'null'; ?>;

function actualizarAdvertenciaDestino() {
    const select = document.getElementById('sucursalDestino');
    const opcion = select.options[select.selectedIndex];
    const contenedor = document.getElementById('advertenciaDestino');

    if (!opcion || !opcion.value) { contenedor.innerHTML = ''; return; }

    const cumple = opcion.dataset.cumple === '1';
    if (!cumple) {
        contenedor.innerHTML = `<div class="alert alert-warning py-2 small mb-3">
            <span class="material-symbols-rounded align-middle me-1" style="font-size:16px;">warning</span>
            Esta sucursal tampoco cumple el umbral mínimo de rotación. El sistema pedirá confirmación extra antes de transferir.
        </div>`;
    } else {
        contenedor.innerHTML = `<div class="alert alert-success py-2 small mb-3">
            <span class="material-symbols-rounded align-middle me-1" style="font-size:16px;">check_circle</span>
            Esta sucursal registra buena rotación para este medicamento.
        </div>`;
    }
}

function confirmarTransferencia(forzar = false) {
    const idSucursalDestino = document.getElementById('sucursalDestino').value;
    const cantidad = parseInt(document.getElementById('cantidadTransferir').value) || 0;

    if (!idSucursalDestino) {
        Swal.fire('Falta información', 'Seleccione la sucursal de destino.', 'warning');
        return;
    }
    if (cantidad < 1) {
        Swal.fire('Cantidad inválida', 'Debe transferir al menos 1 unidad.', 'warning');
        return;
    }

    const btn = document.getElementById('btnConfirmar');
    btn.disabled = true;

    fetch(`${BASE_URL}/backend/inventario/transferir_stock.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id_lote: idLote,
            id_sucursal_origen: idSucursalOrigen,
            id_sucursal_destino: parseInt(idSucursalDestino),
            cantidad: cantidad,
            motivo: 'Redistribución - proceso estratégico de vencimientos',
            id_accion: idAccion,
            validar_rotacion: true,
            forzar: forzar
        })
    })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;

            if (data.success) {
                Swal.fire('¡Transferencia realizada!', data.message, 'success')
                    .then(() => {
                        window.location.href = `menuprincipal.php?mod=detalle_riesgo_lote&id_lote=${idLote}&id_sucursal=${idSucursalDestino}`;
                    });
                return;
            }

            // NUEVO (Tarea 5): si el backend pide confirmación porque el
            // destino tampoco vende bien, se le pregunta al usuario si
            // quiere forzar de todas formas (queda registrado que fue una
            // decisión consciente, no automática).
            if (data.requiere_confirmacion) {
                Swal.fire({
                    icon: 'warning',
                    title: '¿Transferir de todas formas?',
                    text: data.message,
                    showCancelButton: true,
                    confirmButtonText: 'Sí, transferir igual',
                    cancelButtonText: 'Cancelar'
                }).then(result => {
                    if (result.isConfirmed) {
                        confirmarTransferencia(true);
                    }
                });
                return;
            }

            Swal.fire('Error', data.message, 'error');
        })
        .catch(() => {
            btn.disabled = false;
            Swal.fire('Error', 'Error de conexión con el servidor', 'error');
        });
}
</script>
