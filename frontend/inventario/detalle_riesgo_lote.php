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

$umbrales = obtenerUmbralesVencimiento($conexion);
$lote = null;
$error = null;

if ($id_lote > 0 && $id_sucursal > 0) {
    $lote = evaluarLoteDetalle($conexion, $id_lote, $id_sucursal, $umbrales);
    if (!$lote) {
        $error = 'No se encontró stock de ese lote en esa sucursal.';
    }
}

// NUEVO: acciones ya registradas sobre este lote en esta sucursal, y
// proveedores disponibles para poder completar una Devolución pendiente.
$accionesLote = [];
$proveedores = [];
if (!$error) {
    $stmt = $conexion->prepare("
        SELECT id_accion, tipo_accion, estado, cantidad_afectada, valor_en_riesgo, valor_recuperado_estimado, fecha_creacion, fecha_ejecucion
        FROM accion_recuperacion
        WHERE id_lote = :lote AND id_sucursal_origen = :suc
        ORDER BY fecha_creacion DESC
    ");
    $stmt->execute([':lote' => $id_lote, ':suc' => $id_sucursal]);
    $accionesLote = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conexion->query("SELECT id_proveedor, nombre FROM proveedores ORDER BY nombre");
    $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $error = 'Falta indicar el lote y la sucursal (id_lote / id_sucursal).';
}
?>

<div class="container-fluid">
    <div class="mb-4">
        <div class="d-flex align-items-center gap-2 text-muted small mb-1">
            <a href="menuprincipal.php?mod=vencimientos" class="text-decoration-none text-muted">Vencimientos</a>
            <span>&rsaquo;</span>
            <span class="fw-semibold text-dark">Detalle de riesgo del lote</span>
        </div>
        <h2 class="mb-0 text-danger">
            <span class="material-symbols-rounded align-middle me-2">inventory</span>
            Detalle de lote y valor económico en riesgo
        </h2>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span class="material-symbols-rounded align-middle me-1">error</span>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <a href="menuprincipal.php?mod=vencimientos" class="btn btn-outline-secondary">
            <span class="material-symbols-rounded align-middle me-1">arrow_back</span> Volver a Vencimientos
        </a>
    <?php else: ?>

        <?php
        $coloresRiesgo = ['CRITICO' => 'danger', 'MODERADO' => 'warning', 'BAJO' => 'success'];
        $colorRiesgo = $coloresRiesgo[$lote['nivel_riesgo']] ?? 'secondary';
        ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1"><?php echo htmlspecialchars($lote['medicamento_nombre']); ?> <small class="text-muted"><?php echo htmlspecialchars($lote['concentracion'] ?? ''); ?></small></h4>
                        <span class="text-muted">Lote <code><?php echo htmlspecialchars($lote['numero_lote']); ?></code> · Sucursal <?php echo htmlspecialchars($lote['sucursal_nombre']); ?></span>
                    </div>
                    <span class="badge bg-<?php echo $colorRiesgo; ?> fs-6 px-3 py-2">Riesgo <?php echo $lote['nivel_riesgo']; ?></span>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted d-block">Existencia disponible</small>
                        <h4 class="mb-0"><?php echo number_format($lote['cantidad']); ?> u.</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted d-block">Costo unitario</small>
                        <h4 class="mb-0">RD$ <?php echo number_format($lote['costo_unitario'], 2); ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #dc3545;">
                    <div class="card-body">
                        <small class="text-muted d-block">Valor total en riesgo</small>
                        <h4 class="mb-0 text-danger">RD$ <?php echo number_format($lote['valor_en_riesgo'], 2); ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted d-block">Días restantes</small>
                        <h4 class="mb-0 <?php echo $lote['dias_restantes'] < 0 ? 'text-danger' : ''; ?>">
                            <?php echo $lote['dias_restantes'] < 0 ? 'VENCIDO' : $lote['dias_restantes'] . ' días'; ?>
                        </h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="mb-1">Rotación (IRV) en esta sucursal</h6>
                        <small class="text-muted d-block mb-3">Umbral mínimo aceptable: <?php echo $umbrales['venc_irv_umbral_minimo']; ?>% en <?php echo $umbrales['venc_irv_periodo_dias']; ?> días</small>
                        <?php if ($lote['irv_origen'] === null): ?>
                            <div class="alert alert-secondary mb-0">Sin datos suficientes para calcular el IRV en esta sucursal.</div>
                        <?php else: ?>
                            <?php $bajaRotacion = $lote['irv_origen'] < (float)$umbrales['venc_irv_umbral_minimo']; ?>
                            <h2 class="<?php echo $bajaRotacion ? 'text-danger' : 'text-success'; ?>"><?php echo $lote['irv_origen']; ?>%</h2>
                            <span class="badge bg-<?php echo $bajaRotacion ? 'danger' : 'success'; ?>">
                                <?php echo $bajaRotacion ? 'Baja rotación' : 'Rotación normal'; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="mb-1">Rotación por sucursal</h6>
                        <small class="text-muted d-block mb-3">Para decidir si conviene redistribuir a otra sucursal</small>
                        <?php if (empty($lote['rotacion_por_sucursal'])): ?>
                            <p class="text-muted mb-0">Este medicamento no tiene stock en ninguna sucursal.</p>
                        <?php else: ?>
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr><th>Sucursal</th><th class="text-center">IRV</th><th class="text-center">Estado</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lote['rotacion_por_sucursal'] as $suc): ?>
                                        <tr class="<?php echo $suc['id_sucursal'] === $lote['id_sucursal'] ? 'table-light fw-bold' : ''; ?>">
                                            <td><?php echo htmlspecialchars($suc['sucursal_nombre']); ?><?php echo $suc['id_sucursal'] === $lote['id_sucursal'] ? ' <small class="text-muted">(actual)</small>' : ''; ?></td>
                                            <td class="text-center"><?php echo $suc['irv'] === null ? '-' : $suc['irv'] . '%'; ?></td>
                                            <td class="text-center">
                                                <?php if ($suc['irv'] === null): ?>
                                                    <span class="badge bg-secondary">Sin datos</span>
                                                <?php elseif ($suc['cumple_umbral']): ?>
                                                    <span class="badge bg-success">Cumple umbral</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">No cumple</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if ($lote['sin_demanda_en_red']): ?>
                                <div class="alert alert-warning mt-3 mb-0 small">
                                    <span class="material-symbols-rounded align-middle me-1" style="font-size:18px;">warning</span>
                                    Ninguna sucursal cumple el umbral mínimo de rotación (con datos de venta reales). Transferir no resolvería el problema de fondo.
                                </div>
                            <?php elseif ($lote['sin_datos_en_red']): ?>
                                <div class="alert alert-secondary mt-3 mb-0 small">
                                    <span class="material-symbols-rounded align-middle me-1" style="font-size:18px;">info</span>
                                    Ninguna sucursal tiene historial de ventas suficiente para este medicamento todavía. No se puede concluir si conviene o no redistribuir — evalúe con criterio antes de decidir.
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- NUEVO: acciones registradas sobre este lote -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <h6 class="mb-3">Acciones registradas sobre este lote</h6>
                <?php if (empty($accionesLote)): ?>
                    <p class="text-muted small mb-0">Todavía no se ha generado ninguna acción de recuperación para este lote.</p>
                <?php else: ?>
                    <?php
                    $etiquetasTipoAccion = ['PROMOCION' => 'Promoción', 'REDISTRIBUCION' => 'Redistribución', 'COMBO' => 'Combo/Paquete', 'DEVOLUCION_PROVEEDOR' => 'Devolución a proveedor', 'DONACION' => 'Donación', 'PROVISION_PERDIDA' => 'Provisión de pérdida', 'DESTRUCCION' => 'Destrucción'];
                    $coloresEstadoAccion = ['PENDIENTE' => 'secondary', 'EN_EJECUCION' => 'info', 'COMPLETADA' => 'success', 'CANCELADA' => 'dark', 'SIN_EFECTO' => 'danger'];
                    ?>
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Tipo</th><th>Estado</th><th class="text-end">Cantidad</th><th class="text-end">Valor</th><th>Fecha</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($accionesLote as $a): ?>
                                <tr>
                                    <td><?php echo $etiquetasTipoAccion[$a['tipo_accion']] ?? $a['tipo_accion']; ?></td>
                                    <td><span class="badge bg-<?php echo $coloresEstadoAccion[$a['estado']] ?? 'secondary'; ?>"><?php echo $a['estado']; ?></span></td>
                                    <td class="text-end"><?php echo $a['cantidad_afectada']; ?> u.</td>
                                    <td class="text-end">RD$ <?php echo number_format($a['valor_en_riesgo'], 2); ?></td>
                                    <td><small class="text-muted"><?php echo date('d/m/Y', strtotime($a['fecha_creacion'])); ?></small></td>
                                    <td class="text-end">
                                        <?php if ($a['tipo_accion'] === 'DEVOLUCION_PROVEEDOR' && $a['estado'] === 'PENDIENTE'): ?>
                                            <button type="button" class="btn btn-sm btn-primary" onclick="abrirModalDevolucion(<?php echo $a['id_accion']; ?>)">
                                                Completar
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal: completar devolución a proveedor -->
        <div class="modal fade" id="modalDevolucion" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Completar devolución a proveedor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Esto crea el registro real en el módulo de Devoluciones, descuenta el inventario de esta sucursal y cierra la acción de recuperación.</p>
                        <label class="form-label small fw-bold">Proveedor</label>
                        <select class="form-select mb-3" id="selectProveedor">
                            <option value="">Seleccione...</option>
                            <?php foreach ($proveedores as $p): ?>
                                <option value="<?php echo $p['id_proveedor']; ?>"><?php echo htmlspecialchars($p['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="form-label small fw-bold">Motivo</label>
                        <textarea class="form-control" id="motivoDevolucion" rows="2" placeholder="Ej. Producto próximo a vencer, devuelto según política del proveedor."></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="btnConfirmarDevolucion" onclick="confirmarDevolucion()">Confirmar devolución</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <a href="menuprincipal.php?mod=generar_accion_recuperacion&id_lote=<?php echo $id_lote; ?>&id_sucursal=<?php echo $id_sucursal; ?>" class="btn btn-primary">
                <span class="material-symbols-rounded align-middle me-1">bolt</span>
                Generar acción de recuperación
            </a>
            <a href="menuprincipal.php?mod=vencimientos" class="btn btn-outline-secondary">
                <span class="material-symbols-rounded align-middle me-1">arrow_back</span>
                Volver al monitoreo
            </a>
        </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const BASE_URL_DEV = '/Sistema-Gestor-de-Farmacias';
let idAccionDevolucionActual = null;
let modalDevolucionInstance = null;

function abrirModalDevolucion(idAccion) {
    idAccionDevolucionActual = idAccion;
    document.getElementById('selectProveedor').value = '';
    document.getElementById('motivoDevolucion').value = '';

    const modalEl = document.getElementById('modalDevolucion');
    // Mover el modal al body para evitar problemas de backdrop/z-index
    if (modalEl.parentNode !== document.body) {
        document.body.appendChild(modalEl);
    }

    // Dispose de la instancia previa si existe
    if (modalDevolucionInstance) {
        try { modalDevolucionInstance.dispose(); } catch (e) { /* ignore */ }
        modalDevolucionInstance = null;
    }

    modalDevolucionInstance = new bootstrap.Modal(modalEl, { backdrop: true, focus: true });
    modalDevolucionInstance.show();
}

function confirmarDevolucion() {
    const idProveedor = document.getElementById('selectProveedor').value;
    if (!idProveedor) {
        Swal.fire('Falta información', 'Seleccione el proveedor.', 'warning');
        return;
    }

    const btn = document.getElementById('btnConfirmarDevolucion');
    btn.disabled = true;

    fetch(`${BASE_URL_DEV}/backend/inventario/completar_devolucion_proveedor.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id_accion: idAccionDevolucionActual,
            id_proveedor: parseInt(idProveedor),
            motivo: document.getElementById('motivoDevolucion').value
        })
    })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => { throw new Error('HTTP ' + response.status + ' - ' + text); });
            }
            return response.text().then(text => {
                try { return JSON.parse(text); } catch (e) { throw new Error('Respuesta inválida del servidor'); }
            });
        })
        .then(data => {
            btn.disabled = false;
            if (modalDevolucionInstance) {
                modalDevolucionInstance.hide();
            } else {
                const inst = bootstrap.Modal.getInstance(document.getElementById('modalDevolucion'));
                if (inst) inst.hide();
            }
            if (data && data.success) {
                Swal.fire('¡Devolución completada!', 'Se creó el registro #' + data.id_devolucion + ' y se descontó el inventario.', 'success')
                    .then(() => location.reload());
            } else {
                Swal.fire('Error', data && data.message ? data.message : 'Error en el servidor', 'error');
            }
        })
        .catch(err => {
            btn.disabled = false;
            console.error('Error completar devolución:', err);
            Swal.fire('Error', err.message || 'Error de conexión con el servidor', 'error');
        });
}
</script>
