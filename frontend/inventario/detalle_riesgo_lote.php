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
require_once $base_path . '/backend/inventario/redistribucion_inteligente_lib.php';

$base_url = '/Sistema-Gestor-de-Farmacias';

$id_lote = isset($_GET['id_lote']) ? (int)$_GET['id_lote'] : 0;
$id_sucursal = isset($_GET['id_sucursal']) ? (int)$_GET['id_sucursal'] : 0;
$paginaAcciones = max(1, (int)($_GET['acciones_pagina'] ?? 1));
$limiteAcciones = 10;

$umbrales = obtenerUmbralesVencimiento($conexion);
$lote = null;
$error = null;
$loteDevuelto = false;

if ($id_lote > 0 && $id_sucursal > 0) {
    $lote = evaluarLoteDetalle($conexion, $id_lote, $id_sucursal, $umbrales);
    if (!$lote) {
        $error = 'No se encontró stock de ese lote en esa sucursal.';
    } else {
        $stmtDevuelto = $conexion->prepare("SELECT EXISTS (
            SELECT 1
            FROM detalle_devolucion dd
            JOIN devoluciones d ON d.id_devolucion = dd.id_devolucion
            JOIN estado_devolucion ed ON ed.id_estado = d.id_estado
            WHERE dd.id_lote = :lote AND ed.nombre = 'COMPLETADA'
        )");
        $stmtDevuelto->execute([':lote' => $id_lote]);
        $loteDevuelto = (bool)$stmtDevuelto->fetchColumn();
    }
}

// NUEVO (mejora final): decisión estratégica única que integra intervalos
// configurables de % de venta + puntuación ponderada de sucursales + tiempo
// restante de vencimiento. Reutiliza el mismo $lote ya calculado arriba.
$recomendacion = null;
if (!$error && !$loteDevuelto) {
    $recomendacion = generarRecomendacionEstrategica($conexion, $lote, $id_sucursal, $umbrales);
}

// NUEVO: acciones ya registradas sobre este lote en esta sucursal.
$accionesLote = [];
$totalAcciones = 0;
if (!$error) {
    $stmt = $conexion->prepare("
        SELECT id_accion, tipo_accion, estado, cantidad_afectada, valor_en_riesgo, valor_recuperado_estimado, fecha_creacion, fecha_ejecucion
        FROM accion_recuperacion
        WHERE id_lote = :lote AND id_sucursal_origen = :suc
        ORDER BY fecha_creacion DESC
        LIMIT :limite OFFSET :offset
    ");
    $stmt->bindValue(':lote', $id_lote, PDO::PARAM_INT);
    $stmt->bindValue(':suc', $id_sucursal, PDO::PARAM_INT);
    $stmt->bindValue(':limite', $limiteAcciones, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($paginaAcciones - 1) * $limiteAcciones, PDO::PARAM_INT);
    $stmt->execute();
    $accionesLote = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtTotal = $conexion->prepare("SELECT COUNT(*) FROM accion_recuperacion WHERE id_lote = :lote AND id_sucursal_origen = :suc");
    $stmtTotal->execute([':lote' => $id_lote, ':suc' => $id_sucursal]);
    $totalAcciones = (int)$stmtTotal->fetchColumn();
} else {
    $error = 'Falta indicar el lote y la sucursal (id_lote / id_sucursal).';
}
?>

<div class="container-fluid">
    <div class="mb-4">
        <nav class="tarea5-breadcrumb" aria-label="breadcrumb">
            <a href="menuprincipal.php?mod=vencimientos" class="tarea5-breadcrumb-home" title="Vencimientos">
                <span class="material-symbols-rounded">home</span>
            </a>
            <span class="tarea5-breadcrumb-sep material-symbols-rounded">chevron_right</span>
            <span class="tarea5-breadcrumb-actual">Detalle de riesgo del lote</span>
        </nav>
        <h2 class="mb-0 text-danger">
            <span class="material-symbols-rounded align-middle me-2">inventory</span>
            Detalle de lote y valor económico en riesgo
        </h2>
        <a href="menuprincipal.php?mod=viajes_redistribucion" class="btn btn-sm btn-outline-info mt-2">
            <span class="material-symbols-rounded align-middle" style="font-size:16px;">local_shipping</span>
            Ver viajes de redistribución activos
        </a>
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
        <?php if ($loteDevuelto): ?>
            <div class="alert alert-secondary">
                <span class="material-symbols-rounded align-middle me-1">assignment_return</span>
                Este lote ya fue devuelto al proveedor. No se pueden registrar nuevas acciones sobre él.
            </div>
        <?php endif; ?>

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

        <!-- NUEVO (mejora final): decisión estratégica única -->
        <?php if ($recomendacion): ?>
        <?php
            $coloresAccion = ['MANTENER' => 'success', 'PROMOCION' => 'primary', 'REDISTRIBUCION' => 'info', 'DEVOLUCION_PROVEEDOR' => 'warning', 'SIN_DATOS_SUFICIENTES' => 'secondary'];
            $colorAccion = $coloresAccion[$recomendacion['accion_recomendada']] ?? 'secondary';
        ?>
        <div class="card border-0 shadow-sm mt-4 border-start border-<?php echo $colorAccion; ?> border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <h6 class="mb-0">
                        <span class="material-symbols-rounded align-middle me-1">insights</span>
                        Recomendación estratégica del sistema
                    </h6>
                    <span class="badge bg-<?php echo $colorAccion; ?> fs-6 px-3 py-2">
                        <?php echo htmlspecialchars($recomendacion['etiqueta']); ?>
                    </span>
                </div>

                <?php if (!empty($recomendacion['motivos'])): ?>
                    <ul class="small text-muted mb-2 ps-3">
                        <?php foreach ($recomendacion['motivos'] as $motivo): ?>
                            <li><?php echo htmlspecialchars($motivo); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if (!empty($recomendacion['advertencias'])): ?>
                    <?php foreach ($recomendacion['advertencias'] as $adv): ?>
                        <div class="alert alert-warning small py-2 mb-2">
                            <span class="material-symbols-rounded align-middle me-1" style="font-size:16px;">warning</span>
                            <?php echo htmlspecialchars($adv); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="d-flex gap-2 mt-3 flex-wrap">
                    <?php if ($recomendacion['accion_recomendada'] === 'REDISTRIBUCION' && $recomendacion['sucursal_recomendada']): ?>
                        <a href="menuprincipal.php?mod=generar_accion_recuperacion&id_lote=<?php echo $id_lote; ?>&id_sucursal=<?php echo $id_sucursal; ?>&accion_recomendada=REDISTRIBUCION" class="btn btn-info text-white">
                            <span class="material-symbols-rounded align-middle me-1" style="font-size:18px;">swap_horiz</span>
                            Transferir a <?php echo htmlspecialchars($recomendacion['sucursal_recomendada']['sucursal_nombre']); ?>
                        </a>
                    <?php elseif (in_array($recomendacion['accion_recomendada'], ['PROMOCION', 'DEVOLUCION_PROVEEDOR'], true)): ?>
                        <a href="menuprincipal.php?mod=generar_accion_recuperacion&id_lote=<?php echo $id_lote; ?>&id_sucursal=<?php echo $id_sucursal; ?>&accion_recomendada=<?php echo $recomendacion['accion_recomendada']; ?>" class="btn btn-<?php echo $colorAccion; ?> text-white">
                            <span class="material-symbols-rounded align-middle me-1" style="font-size:18px;">bolt</span>
                            Generar acción de <?php echo $recomendacion['accion_recomendada'] === 'PROMOCION' ? 'promoción' : 'devolución al proveedor'; ?>
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($recomendacion['ranking_sucursales'])): ?>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#detalleRanking">
                            <span class="material-symbols-rounded align-middle me-1" style="font-size:18px;">table_rows</span>
                            Ver puntuación de todas las sucursales
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (!empty($recomendacion['ranking_sucursales'])): ?>
                <div class="collapse mt-3" id="detalleRanking">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Sucursal</th>
                                    <th class="text-center">Conveniencia</th>
                                    <th>Criterios que más pesaron</th>
                                    <th class="text-center">IRV</th>
                                    <th class="text-center">Inventario</th>
                                    <th class="text-center">Demanda</th>
                                    <th class="text-center">Distancia</th>
                                    <th class="text-center">Flete est.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recomendacion['ranking_sucursales'] as $c): ?>
                                    <?php
                                    $etiquetasCriterio = etiquetasCriterioTransferencia();
                                    $contrib = $c['contribuciones_por_criterio'] ?? [];
                                    arsort($contrib);
                                    $topCriterios = [];
                                    foreach (array_slice($contrib, 0, 2, true) as $claveCriterio => $aporte) {
                                        $pesoC = (float)(($c['pesos_aplicados'][$claveCriterio] ?? 0));
                                        $prefC = ($c['preferencias_aplicadas'][$claveCriterio] ?? 'mayor') === 'menor' ? 'menos' : 'más';
                                        $topCriterios[] = ($etiquetasCriterio[$claveCriterio] ?? $claveCriterio) . " {$pesoC}% ({$prefC})";
                                    }
                                    ?>
                                    <tr class="<?php echo (!$c['viable_economicamente']) ? 'table-light text-muted' : ''; ?>">
                                        <td>
                                            <?php echo htmlspecialchars($c['sucursal_nombre']); ?>
                                            <?php if (!empty($c['perfil_personalizado'])): ?>
                                                <br><small class="text-info">Perfil propio</small>
                                            <?php else: ?>
                                                <br><small class="text-muted">Pesos por defecto</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><strong><?php echo $c['puntuacion']; ?>%</strong></td>
                                        <td class="small"><?php echo htmlspecialchars(implode(' · ', $topCriterios)); ?></td>
                                        <td class="text-center"><?php echo $c['irv'] === null ? '-' : $c['irv'] . '%'; ?></td>
                                        <td class="text-center"><?php echo number_format((float)($c['stock_actual'] ?? 0)); ?> u.</td>
                                        <td class="text-center"><?php echo number_format($c['demanda_historica']); ?> u.</td>
                                        <td class="text-center"><?php echo $c['distancia_km'] === null ? '-' : $c['distancia_km'] . ' km'; ?></td>
                                        <td class="text-center"><?php echo $c['costo_transporte_estimado'] === null ? '-' : 'RD$ ' . number_format($c['costo_transporte_estimado'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- NUEVO: acciones registradas sobre este lote -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <h6 class="mb-3">Acciones registradas sobre este lote</h6>
                <?php if (empty($accionesLote)): ?>
                    <p class="text-muted small mb-0">Todavía no se ha generado ninguna acción de recuperación para este lote.</p>
                <?php else: ?>
                    <?php
                    $etiquetasTipoAccion = ['PROMOCION' => 'Promoción', 'REDISTRIBUCION' => 'Redistribución', 'COMBO' => 'Combo/Paquete', 'DEVOLUCION_PROVEEDOR' => 'Devolución a proveedor', 'DONACION' => 'Donación', 'PROVISION_PERDIDA' => 'Provisión de pérdida', 'DESTRUCCION' => 'Destrucción'];
                    $coloresEstadoAccion = ['PENDIENTE' => 'secondary', 'ESPERANDO_PROVEEDOR' => 'warning', 'EN_EJECUCION' => 'info', 'COMPLETADA' => 'success', 'CANCELADA' => 'dark', 'RECHAZADA_PROVEEDOR' => 'danger', 'SIN_EFECTO' => 'danger'];
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
                                        <?php if ($a['tipo_accion'] === 'DEVOLUCION_PROVEEDOR' && $a['estado'] === 'ESPERANDO_PROVEEDOR'): ?>
                                            <button type="button" class="btn btn-sm btn-primary" onclick="abrirModalDevolucion(<?php echo $a['id_accion']; ?>)">
                                                Registrar respuesta
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php $totalPaginasAcciones = max(1, (int)ceil($totalAcciones / $limiteAcciones)); ?>
                    <?php if ($totalPaginasAcciones > 1): ?>
                        <nav class="mt-3" aria-label="Paginación de acciones">
                            <ul class="pagination pagination-sm mb-0 justify-content-center">
                                <?php for ($pagina = 1; $pagina <= $totalPaginasAcciones; $pagina++): ?>
                                    <li class="page-item <?php echo $pagina === $paginaAcciones ? 'active' : ''; ?>">
                                        <a class="page-link" href="menuprincipal.php?mod=detalle_riesgo_lote&id_lote=<?php echo $id_lote; ?>&id_sucursal=<?php echo $id_sucursal; ?>&acciones_pagina=<?php echo $pagina; ?>"><?php echo $pagina; ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal: registrar la respuesta del proveedor a una solicitud de devolución -->
        <div class="modal fade" id="modalDevolucion" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Registrar respuesta del proveedor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">La solicitud de devolución ya fue enviada al proveedor con el motivo pactado. Indique si la aceptó o la rechazó.</p>
                        <label class="form-label small fw-bold">Decisión del proveedor</label>
                        <select class="form-select mb-3" id="selectDecision">
                            <option value="">Seleccione...</option>
                            <option value="APROBADA">Aprobó la devolución</option>
                            <option value="RECHAZADA">Rechazó la devolución</option>
                        </select>
                        <label class="form-label small fw-bold">Observaciones <span id="lblObsObligatorio" class="text-danger" style="display:none;">(obligatorio si rechaza)</span></label>
                        <textarea class="form-control" id="motivoDevolucion" rows="2" placeholder="Ej. Aceptado según política de devolución por vencimiento. / Rechazado: el lote no cumple la condición pactada."></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="btnConfirmarDevolucion" onclick="confirmarDevolucion()">Guardar respuesta</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <?php if (!$loteDevuelto): ?>
                <a href="menuprincipal.php?mod=generar_accion_recuperacion&id_lote=<?php echo $id_lote; ?>&id_sucursal=<?php echo $id_sucursal; ?>" class="btn btn-primary">
                    <span class="material-symbols-rounded align-middle me-1">bolt</span>
                    Generar acción de recuperación
                </a>
            <?php endif; ?>
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
    document.getElementById('selectDecision').value = '';
    document.getElementById('motivoDevolucion').value = '';
    document.getElementById('lblObsObligatorio').style.display = 'none';

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

document.getElementById('selectDecision')?.addEventListener('change', function() {
    document.getElementById('lblObsObligatorio').style.display = this.value === 'RECHAZADA' ? 'inline' : 'none';
});

function confirmarDevolucion() {
    const decision = document.getElementById('selectDecision').value;
    const observaciones = document.getElementById('motivoDevolucion').value.trim();

    if (!decision) {
        Swal.fire('Falta información', 'Indique si el proveedor aprobó o rechazó la devolución.', 'warning');
        return;
    }
    if (decision === 'RECHAZADA' && observaciones === '') {
        Swal.fire('Falta información', 'Indique el motivo del rechazo.', 'warning');
        return;
    }

    const btn = document.getElementById('btnConfirmarDevolucion');
    btn.disabled = true;

    fetch(`${BASE_URL_DEV}/backend/inventario/registrar_respuesta_devolucion_proveedor.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id_accion: idAccionDevolucionActual,
            decision: decision,
            observaciones: observaciones
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
                Swal.fire(decision === 'APROBADA' ? '¡Devolución aprobada!' : 'Rechazo registrado', data.message, decision === 'APROBADA' ? 'success' : 'info')
                    .then(() => location.reload());
            } else {
                Swal.fire('Error', data && data.message ? data.message : 'Error en el servidor', 'error');
            }
        })
        .catch(err => {
            btn.disabled = false;
            console.error('Error al registrar respuesta de devolución:', err);
            Swal.fire('Error', err.message || 'Error de conexión con el servidor', 'error');
        });
}
</script>
