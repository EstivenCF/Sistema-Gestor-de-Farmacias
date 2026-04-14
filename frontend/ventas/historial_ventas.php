<?php
require_once __DIR__ . '/../../backend/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_sesion'])) {
    header("Location: ../index.php");
    exit();
}

$filtro_fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$filtro_cliente = $_GET['cliente'] ?? '';
$filtro_usuario = $_GET['usuario'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

$clientes = $usuarios = $metodos_pago = [];
try {
    $clientes = $conexion->query("SELECT id_cliente, nombre FROM clientes ORDER BY nombre")->fetchAll();
    $usuarios = $conexion->query("SELECT id_usuario, nombre FROM usuarios WHERE estado = true ORDER BY nombre")->fetchAll();
    $metodos_pago = $conexion->query("SELECT id_metodo, nombre FROM metodos_pago ORDER BY nombre")->fetchAll();
} catch(PDOException $e) {}

$query = "
    SELECT 
        v.id_venta, v.numero_documento, v.fecha,
        v.subtotal, v.itbis_total, v.descuento_total, v.total,
        v.es_credito, v.estado_pago, v.abonos_acumulados,
        v.fecha_vencimiento_pago, v.ncf, v.estado_fiscal,
        v.usa_seguro, v.monto_cubre_seguro, v.monto_paga_paciente,
        COALESCE(c.nombre, 'Consumidor Final') as cliente_nombre,
        u.nombre as usuario_nombre,
        cp.nombre as condicion_pago,
        (SELECT COUNT(*) FROM detalle_venta dv WHERE dv.id_venta = v.id_venta) as total_productos
    FROM ventas v
    LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
    JOIN usuarios u ON v.id_usuario = u.id_usuario
    JOIN condicion_pago cp ON v.id_condicion = cp.id_condicion
    WHERE 1=1
";

$params = [];
if ($filtro_fecha_desde && $filtro_fecha_hasta) {
    $query .= " AND DATE(v.fecha) BETWEEN :fecha_desde AND :fecha_hasta";
    $params[':fecha_desde'] = $filtro_fecha_desde;
    $params[':fecha_hasta'] = $filtro_fecha_hasta;
}
if ($filtro_cliente) {
    $query .= " AND v.id_cliente = :cliente";
    $params[':cliente'] = $filtro_cliente;
}
if ($filtro_usuario) {
    $query .= " AND v.id_usuario = :usuario";
    $params[':usuario'] = $filtro_usuario;
}
if ($filtro_estado) {
    if ($filtro_estado == 'CREDITO_PENDIENTE') $query .= " AND v.es_credito = true AND v.estado_pago = 'PENDIENTE'";
    elseif ($filtro_estado == 'CREDITO_PARCIAL') $query .= " AND v.es_credito = true AND v.estado_pago = 'PARCIAL'";
    elseif ($filtro_estado == 'CREDITO_PAGADO') $query .= " AND v.es_credito = true AND v.estado_pago = 'PAGADO'";
    elseif ($filtro_estado == 'CONTADO') $query .= " AND v.es_credito = false";
    elseif ($filtro_estado == 'ANULADA') $query .= " AND v.estado_fiscal = 'ANULADO'";
}
if ($busqueda) {
    $query .= " AND (v.numero_documento ILIKE :busqueda OR c.nombre ILIKE :busqueda OR v.ncf ILIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}
$query .= " ORDER BY v.fecha DESC LIMIT 500";

$ventas = [];
try {
    $stmt = $conexion->prepare($query);
    $stmt->execute($params);
    $ventas = $stmt->fetchAll();
} catch(PDOException $e) { $ventas = []; }

$total_ventas = count($ventas);
$suma_total = $suma_itbis = $suma_seguro = $suma_descuento = 0;
foreach ($ventas as $v) {
    $suma_total += floatval($v['total']);
    $suma_itbis += floatval($v['itbis_total']);
    $suma_seguro += floatval($v['monto_cubre_seguro'] ?? 0);
    $suma_descuento += floatval($v['descuento_total'] ?? 0);
}
$base_url = '/sistema-gestor-de-farmacias';
?>
<style>
    /* (tus estilos actuales se mantienen igual, solo añado uno nuevo para badge descuento) */
    .badge-descuento {
        background-color: #fd7e14;
        color: white;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    /* ... el resto de tus estilos se conservan ... */
    .modal-backdrop { display: none !important; }
    .modal { background-color: rgba(0, 0, 0, 0.5) !important; z-index: 1050; }
    .modal-dialog-centered { display: flex; align-items: center; min-height: calc(100% - 1rem); }
    .modal.show .modal-dialog { transform: none; margin: 1.75rem auto; }
    
    .filtros-bar {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 25px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    
    .badge-estado {
        padding: 6px 14px;
        border-radius: 30px;
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.3px;
    }
    .badge-contado { background-color: #28a745; color: #fff; }
    .badge-credito-pendiente { background-color: #dc3545; color: #fff; }
    .badge-credito-parcial { background-color: #fd7e14; color: #fff; }
    .badge-credito-pagado { background-color: #17a2b8; color: #fff; }
    .badge-anulado { background-color: #6c757d; color: #fff; }
    .badge-seguro { background-color: #007bff; color: #fff; }
    
    .card-total {
        background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        color: white;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 25px;
        transition: transform 0.2s ease;
    }
    .card-total-seguro {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 25px;
        transition: transform 0.2s ease;
    }
    .card-total-descuento {
        background: linear-gradient(135deg, #fd7e14 0%, #e96a00 100%);
        color: white;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 25px;
        transition: transform 0.2s ease;
    }
    .card-total:hover, .card-total-seguro:hover, .card-total-descuento:hover {
        transform: translateY(-3px);
    }
    .card-total h3, .card-total-seguro h3, .card-total-descuento h3 {
        font-size: 2rem;
        margin: 5px 0 0;
        font-weight: 700;
    }
    .card-total small, .card-total-seguro small, .card-total-descuento small {
        opacity: 0.85;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .detalle-item {
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .detalle-item:last-child {
        border-bottom: none;
    }
    
    .pago-item {
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .pago-item:last-child {
        border-bottom: none;
    }
    
    .form-control, .form-select {
        border: 1.5px solid #e9ecef !important;
        border-radius: 12px;
        padding: 10px 14px;
        transition: all 0.2s ease;
    }
    .form-control:focus, .form-select:focus {
        border-color: #28a745 !important;
        box-shadow: 0 0 0 3px rgba(40,167,69,0.1) !important;
    }
    
    .dashboard-container {
        padding: 20px;
        animation: fadeSlideIn 0.4s ease-out;
    }
    
    @keyframes fadeSlideIn {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .table-ventas {
        border-radius: 16px;
        overflow: hidden;
    }
    .table-ventas thead th {
        background: #f8f9fa;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        font-weight: 700;
        color: #6c757d;
        padding: 15px 12px;
        border-bottom: 2px solid #e9ecef;
    }
    .table-ventas tbody td {
        padding: 14px 12px;
        vertical-align: middle;
        border-bottom: 1px solid #f0f0f0;
    }
    .table-ventas tbody tr:hover {
        background-color: rgba(40,167,69,0.03);
        cursor: pointer;
    }
    
    .btn-quitar-filtros {
        background-color: #f1f3f5;
        color: #495057;
        border: 1.5px solid #e9ecef;
        border-radius: 12px;
        padding: 10px 20px;
        font-weight: 500;
        transition: all 0.2s ease;
    }
    .btn-quitar-filtros:hover {
        background-color: #e9ecef;
        border-color: #dee2e6;
        color: #212529;
        transform: translateY(-1px);
    }
    
    .btn-cancelar {
        background-color: #f1f3f5;
        color: #495057;
        border: 1.5px solid #e9ecef;
        border-radius: 12px;
        padding: 10px 25px;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    .btn-cancelar:hover {
        background-color: #e9ecef;
        border-color: #dee2e6;
        color: #212529;
        transform: translateY(-1px);
    }
    
    .btn-abono {
        background-color: #fd7e14;
        color: white;
        border: none;
        transition: all 0.2s ease;
    }
    .btn-abono:hover {
        background-color: #e96a00;
        transform: scale(1.02);
    }
    
    .btn-accion {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }
    .btn-accion:hover {
        transform: scale(1.08);
    }
    
    .modal-content {
        border-radius: 20px;
        overflow: hidden;
        max-height: 90vh;
        overflow-y: auto;
    }
    .modal-header {
        border-bottom: none;
        padding: 20px 25px;
        position: sticky;
        top: 0;
        background: white;
        z-index: 10;
    }
    .modal-body {
        padding: 20px 25px;
        max-height: 70vh;
        overflow-y: auto;
    }
    .modal-footer {
        border-top: none;
        padding: 15px 25px 25px;
        position: sticky;
        bottom: 0;
        background: white;
        z-index: 10;
    }
    
    .texto-seguro {
        color: #007bff;
        font-weight: 500;
    }
    hr {
        opacity: 0.5;
        margin: 15px 0;
    }
</style>

<div class="dashboard-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1 text-success d-flex align-items-center gap-2">
                <span class="material-symbols-rounded" style="font-size: 32px;">history</span>
                Historial de Ventas
            </h2>
            <p class="text-muted mb-0">Consulte y administre todas las transacciones realizadas</p>
        </div>
        <div>
            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">
                <i class="fas fa-chart-line me-1"></i> <?php echo $total_ventas; ?> ventas registradas
            </span>
        </div>
    </div>

    <div class="row mb-4 g-4">
        <div class="col-md-3"><div class="card-total text-center"><small>TOTAL DE VENTAS</small><h3><?php echo $total_ventas; ?></h3></div></div>
        <div class="col-md-3"><div class="card-total text-center"><small>MONTO TOTAL</small><h3>RD$ <?php echo number_format($suma_total, 2); ?></h3></div></div>
        <div class="col-md-3"><div class="card-total text-center"><small>ITBIS TOTAL</small><h3>RD$ <?php echo number_format($suma_itbis, 2); ?></h3></div></div>
        <div class="col-md-3"><div class="card-total-seguro text-center"><small>DESCUENTO SEGURO</small><h3>RD$ <?php echo number_format($suma_seguro, 2); ?></h3></div></div>
    </div>
    <!-- Nueva tarjeta para descuentos comerciales -->
    <div class="row mb-4">
        <div class="col-md-4 offset-md-4">
            <div class="card-total-descuento text-center">
                <small>DESCUENTOS COMERCIALES</small>
                <h3>RD$ <?php echo number_format($suma_descuento, 2); ?></h3>
            </div>
        </div>
    </div>

    <div class="filtros-bar">
        <div class="row g-3 align-items-end">
            <div class="col-md-2"><label class="form-label fw-semibold small text-muted">DESDE</label><input type="date" class="form-control" id="filtroFechaDesde" value="<?php echo $filtro_fecha_desde; ?>"></div>
            <div class="col-md-2"><label class="form-label fw-semibold small text-muted">HASTA</label><input type="date" class="form-control" id="filtroFechaHasta" value="<?php echo $filtro_fecha_hasta; ?>"></div>
            <div class="col-md-2"><label class="form-label fw-semibold small text-muted">CLIENTE</label><select class="form-select" id="filtroCliente"><option value="">Todos</option><?php foreach ($clientes as $cli): ?><option value="<?php echo $cli['id_cliente']; ?>" <?php echo $filtro_cliente == $cli['id_cliente'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cli['nombre']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label fw-semibold small text-muted">VENDEDOR</label><select class="form-select" id="filtroUsuario"><option value="">Todos</option><?php foreach ($usuarios as $user): ?><option value="<?php echo $user['id_usuario']; ?>" <?php echo $filtro_usuario == $user['id_usuario'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($user['nombre']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label fw-semibold small text-muted">ESTADO</label><select class="form-select" id="filtroEstado"><option value="">Todos</option><option value="CONTADO" <?php echo $filtro_estado == 'CONTADO' ? 'selected' : ''; ?>>Contado</option><option value="CREDITO_PENDIENTE" <?php echo $filtro_estado == 'CREDITO_PENDIENTE' ? 'selected' : ''; ?>>Crédito Pendiente</option><option value="CREDITO_PARCIAL" <?php echo $filtro_estado == 'CREDITO_PARCIAL' ? 'selected' : ''; ?>>Crédito Parcial</option><option value="CREDITO_PAGADO" <?php echo $filtro_estado == 'CREDITO_PAGADO' ? 'selected' : ''; ?>>Crédito Pagado</option><option value="ANULADA" <?php echo $filtro_estado == 'ANULADA' ? 'selected' : ''; ?>>Anulada</option></select></div>
            <div class="col-md-2"><label class="form-label fw-semibold small text-muted">BUSCAR</label><input type="text" class="form-control" id="busquedaInput" placeholder="Documento, cliente..." value="<?php echo htmlspecialchars($busqueda); ?>"></div>
            <div class="col-md-12"><div class="d-flex justify-content-end"><button type="button" class="btn btn-quitar-filtros" onclick="quitarFiltros()"><span class="material-symbols-rounded me-1">filter_list_off</span> Quitar filtros</button></div></div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-ventas mb-0">
                    <thead>
                        <tr>
                            <th>Nº Documento</th><th>Fecha</th><th>Cliente</th><th>Vendedor</th><th>Productos</th>
                            <th>Subtotal</th><th>ITBIS</th><th>Descuento</th><th class="texto-seguro">Seguro</th><th>Total</th>
                            <th>Abonado</th><th>Saldo</th><th>Estado</th><th>NCF</th><th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ventas)): ?><tr><td colspan="15" class="text-center text-muted py-5">No hay ventas registradas con los filtros seleccionados</td></tr>
                        <?php else: foreach ($ventas as $venta): 
                            $saldo_pendiente = floatval($venta['total']) - floatval($venta['abonos_acumulados'] ?? 0);
                            $monto_seguro = floatval($venta['monto_cubre_seguro'] ?? 0);
                            $descuento = floatval($venta['descuento_total'] ?? 0);
                            $usa_seguro = $venta['usa_seguro'] ?? false;
                            if ($venta['estado_fiscal'] == 'ANULADO') { $estado_class = 'badge-anulado'; $estado_texto = 'Anulada'; }
                            elseif (!$venta['es_credito']) { $estado_class = 'badge-contado'; $estado_texto = 'Contado'; }
                            elseif ($saldo_pendiente <= 0) { $estado_class = 'badge-credito-pagado'; $estado_texto = 'Crédito Pagado'; }
                            elseif ($venta['abonos_acumulados'] > 0 && $saldo_pendiente > 0) { $estado_class = 'badge-credito-parcial'; $estado_texto = 'Crédito Parcial'; }
                            else { $estado_class = 'badge-credito-pendiente'; $estado_texto = 'Crédito Pendiente'; }
                        ?>
                            <tr onclick="verDetalleVenta(<?php echo $venta['id_venta']; ?>)" style="cursor: pointer;">
                                <td class="fw-bold"><?php echo htmlspecialchars($venta['numero_documento']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha'])); ?></td>
                                <td><?php echo htmlspecialchars($venta['cliente_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($venta['usuario_nombre']); ?></td>
                                <td><?php echo $venta['total_productos']; ?></td>
                                <td>RD$ <?php echo number_format(floatval($venta['subtotal']), 2); ?></td>
                                <td>RD$ <?php echo number_format(floatval($venta['itbis_total']), 2); ?></td>
                                <td class="text-warning fw-bold"><?php echo $descuento > 0 ? '- RD$ '.number_format($descuento, 2) : '—'; ?></td>
                                <td class="texto-seguro"><?php if ($usa_seguro && $monto_seguro > 0): ?><span class="badge-seguro badge-estado">- RD$ <?php echo number_format($monto_seguro, 2); ?></span><?php else: ?>—<?php endif; ?></td>
                                <td class="fw-bold text-success">RD$ <?php echo number_format(floatval($venta['total']), 2); ?></td>
                                <td class="text-info"><?php if ($venta['es_credito']): ?>RD$ <?php echo number_format(floatval($venta['abonos_acumulados'] ?? 0), 2); ?><?php else: ?>—<?php endif; ?></td>
                                <td class="text-warning"><?php if ($venta['es_credito']): ?>RD$ <?php echo number_format($saldo_pendiente, 2); ?><?php else: ?>—<?php endif; ?></td>
                                <td><span class="badge-estado <?php echo $estado_class; ?>"><?php echo $estado_texto; ?></span></td>
                                <td><?php if ($venta['ncf']): ?><code><?php echo htmlspecialchars($venta['ncf']); ?></code><?php else: ?>—<?php endif; ?></td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center" onclick="event.stopPropagation();">
                                        <button class="btn btn-outline-primary btn-accion" onclick="verDetalleVenta(<?php echo $venta['id_venta']; ?>)" title="Ver detalle"><span class="material-symbols-rounded" style="font-size: 18px;">visibility</span></button>
                                        <button class="btn btn-outline-secondary btn-accion" onclick="imprimirVenta(<?php echo $venta['id_venta']; ?>)" title="Imprimir recibo"><span class="material-symbols-rounded" style="font-size: 18px;">print</span></button>
                                        <?php if ($venta['es_credito'] && $saldo_pendiente > 0 && $venta['estado_fiscal'] != 'ANULADO'): ?>
                                            <button class="btn btn-abono btn-accion" onclick="abrirModalAbono(<?php echo $venta['id_venta']; ?>, '<?php echo htmlspecialchars($venta['numero_documento']); ?>', <?php echo floatval($venta['total']); ?>, <?php echo floatval($venta['abonos_acumulados'] ?? 0); ?>)" title="Registrar abono"><span class="material-symbols-rounded" style="font-size: 18px;">payments</span></button>
                                        <?php endif; ?>
                                        <?php if (!$venta['ncf'] && $venta['estado_fiscal'] != 'ANULADO'): ?>
                                            <button class="btn btn-outline-info btn-accion" onclick="generarFactura(<?php echo $venta['id_venta']; ?>)" title="Generar factura fiscal"><span class="material-symbols-rounded" style="font-size: 18px;">receipt</span></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalle Venta -->
<div class="modal fade" id="modalDetalleVenta" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title d-flex align-items-center gap-2"><span class="material-symbols-rounded">receipt</span> Detalle de Venta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleVentaContenido"><div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando...</p></div></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-secondary" onclick="imprimirVentaDesdeModal()">Imprimir</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Abono -->
<div class="modal fade" id="modalAbono" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title d-flex align-items-center gap-2"><span class="material-symbols-rounded">payments</span> Registrar Abono</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formAbono">
                    <input type="hidden" id="abonoIdVenta">
                    <div class="mb-3"><label class="form-label fw-semibold">Venta</label><input type="text" id="abonoDocumento" class="form-control" readonly></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Total de la Venta</label><input type="text" id="abonoTotal" class="form-control" readonly></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Saldo Pendiente</label><input type="text" id="abonoSaldoPendiente" class="form-control" readonly style="color:#fd7e14; font-weight:bold;"></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Monto a Pagar</label><div class="input-group"><span class="input-group-text bg-success text-white">RD$</span><input type="number" step="0.01" id="abonoMonto" class="form-control" placeholder="0.00"></div></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Método de Pago</label><select id="abonoMetodoPago" class="form-select"><?php foreach ($metodos_pago as $mp): ?><option value="<?php echo $mp['id_metodo']; ?>"><?php echo htmlspecialchars($mp['nombre']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Referencia (Opcional)</label><input type="text" id="abonoReferencia" class="form-control" placeholder="Nº de cheque, transferencia, etc."></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="confirmarAbono()">Registrar Abono</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';
let ventaActualId = null;

function aplicarFiltros() {
    let url = BASE_URL + '/frontend/menuprincipal.php?mod=historial_ventas';
    const fd = document.getElementById('filtroFechaDesde').value;
    const fh = document.getElementById('filtroFechaHasta').value;
    const cli = document.getElementById('filtroCliente').value;
    const usr = document.getElementById('filtroUsuario').value;
    const est = document.getElementById('filtroEstado').value;
    const bus = document.getElementById('busquedaInput').value;
    if (fd) url += `&fecha_desde=${fd}`;
    if (fh) url += `&fecha_hasta=${fh}`;
    if (cli) url += `&cliente=${cli}`;
    if (usr) url += `&usuario=${usr}`;
    if (est) url += `&estado=${est}`;
    if (bus) url += `&busqueda=${encodeURIComponent(bus)}`;
    window.location.href = url;
}
function quitarFiltros() { window.location.href = BASE_URL + '/frontend/menuprincipal.php?mod=historial_ventas'; }

function verDetalleVenta(idVenta) {
    ventaActualId = idVenta;
    const modalBody = document.getElementById('detalleVentaContenido');
    modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando...</p></div>';
    const modalElement = document.getElementById('modalDetalleVenta');
    const modal = new bootstrap.Modal(modalElement, { backdrop: 'static', keyboard: true });
    modal.show();
    setTimeout(() => {
        modalElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const modalDialog = modalElement.querySelector('.modal-dialog');
        if (modalDialog) {
            const windowHeight = window.innerHeight;
            const modalHeight = modalDialog.offsetHeight;
            const top = (windowHeight - modalHeight) / 2;
            if (top > 0) {
                modalDialog.style.marginTop = top + 'px';
                modalDialog.style.marginBottom = 'auto';
            }
        }
    }, 200);
    
    fetch(BASE_URL + `/backend/ventas/get_detalle_venta_completo.php?id_venta=${idVenta}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                let productosHtml = '';
                if (data.productos && data.productos.length) {
                    data.productos.forEach(p => {
                        productosHtml += `<div class="detalle-item d-flex justify-content-between"><div><strong>${escapeHtml(p.producto_nombre)}</strong><br><small>Cant: ${p.cantidad} x RD$ ${parseFloat(p.precio_unitario).toLocaleString()}</small></div><div class="fw-bold text-success">RD$ ${parseFloat(p.subtotal).toLocaleString()}</div></div>`;
                    });
                } else productosHtml = '<div class="text-center text-muted">No hay productos</div>';
                let pagosHtml = '';
                if (data.pagos && data.pagos.length) {
                    pagosHtml = '<div class="mt-3"><strong>📋 HISTORIAL DE PAGOS</strong><hr>';
                    data.pagos.forEach(p => {
                        let estadoClass = p.estado === 'COMPLETADO' ? 'badge-pago-completado' : (p.estado === 'PENDIENTE' ? 'badge-pago-pendiente' : 'badge-pago-rechazado');
                        pagosHtml += `<div class="pago-item d-flex justify-content-between"><div><small>${p.fecha}</small><br><span class="fw-bold">${escapeHtml(p.metodo_nombre)}</span>${p.referencia ? `<br><small>Ref: ${escapeHtml(p.referencia)}</small>` : ''}</div><div class="text-end"><span class="fw-bold text-success">RD$ ${parseFloat(p.monto).toLocaleString()}</span><br><small class="badge ${estadoClass}">${p.estado || 'COMPLETADO'}</small></div></div>`;
                    });
                    pagosHtml += '</div>';
                } else pagosHtml = '<div class="mt-3"><strong>📋 HISTORIAL DE PAGOS</strong><hr><div class="text-center text-muted">No hay pagos registrados</div></div>';
                const saldoPendiente = data.total - (data.abonos_acumulados || 0);
                const montoSeguro = data.monto_cubre_seguro || 0;
                const descuento = data.descuento_total || 0;
                const descuentoNombre = data.descuento_nombre || '';
                const totalOriginal = data.subtotal + data.itbis_total;
                const botonAbono = (data.es_credito && saldoPendiente > 0) ? `<button class="btn btn-abono btn-sm mt-2" onclick="abrirModalAbonoDesdeDetalle(${data.id_venta}, '${escapeHtml(data.numero_documento)}', ${data.total}, ${data.abonos_acumulados || 0})">Registrar Nuevo Abono</button>` : '';
                let estadoPagoText = '', estadoPagoClass = '';
                if (!data.es_credito) { estadoPagoText = 'Contado'; estadoPagoClass = 'bg-success'; }
                else if (saldoPendiente <= 0) { estadoPagoText = 'Pagado'; estadoPagoClass = 'bg-info'; }
                else if (data.abonos_acumulados > 0 && saldoPendiente > 0) { estadoPagoText = 'Parcial'; estadoPagoClass = 'bg-warning'; }
                else { estadoPagoText = 'Pendiente'; estadoPagoClass = 'bg-danger'; }
                const seguroHtml = (data.usa_seguro && montoSeguro > 0) ? `
                    <div class="col-md-6"><div class="bg-light p-3 rounded"><small class="text-muted">💰 DESCUENTO DEL SEGURO</small><strong class="texto-seguro">- RD$ ${montoSeguro.toLocaleString()}</strong></div></div>
                    <div class="col-md-6"><div class="bg-light p-3 rounded"><small class="text-muted">💰 TOTAL ORIGINAL SIN SEGURO</small><strong>RD$ ${totalOriginal.toLocaleString()}</strong></div></div>
                ` : '';
                const descuentoHtml = (descuento > 0) ? `
                    <div class="col-md-6"><div class="bg-light p-3 rounded"><small class="text-muted">🏷️ DESCUENTO COMERCIAL</small><strong class="text-warning">- RD$ ${descuento.toLocaleString()}</strong>${descuentoNombre ? `<br><small>${escapeHtml(descuentoNombre)}</small>` : ''}</div></div>
                ` : '';
                const html = `
                    <div class="row g-3">
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>Nº DOCUMENTO</small><strong class="fs-5">${escapeHtml(data.numero_documento)}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>FECHA</small><strong>${data.fecha}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>CLIENTE</small><strong>${escapeHtml(data.cliente_nombre)}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>VENDEDOR</small><strong>${escapeHtml(data.vendedor_nombre)}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>CONDICIÓN DE PAGO</small><strong>${escapeHtml(data.condicion_pago)}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>ESTADO DE PAGO</small><span class="badge ${estadoPagoClass}">${estadoPagoText}</span>${botonAbono}</div></div>
                        ${descuentoHtml}
                        ${seguroHtml}
                        ${data.ncf ? `<div class="col-md-6"><div class="bg-light p-3 rounded"><small>NCF</small><code>${escapeHtml(data.ncf)}</code></div></div>` : ''}
                        ${data.es_credito ? `<div class="col-md-6"><div class="bg-light p-3 rounded"><small>ABONOS ACUMULADOS</small><strong class="text-info">RD$ ${parseFloat(data.abonos_acumulados || 0).toLocaleString()}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>SALDO PENDIENTE</small><strong class="text-warning">RD$ ${saldoPendiente.toLocaleString()}</strong></div></div>` : ''}
                        <div class="col-12"><div class="bg-light p-3 rounded"><small>PRODUCTOS</small>${productosHtml}</div></div>
                        <div class="col-12">${pagosHtml}</div>
                        <div class="col-12"><div class="bg-success bg-opacity-10 p-3 rounded text-end">
                            <small>Subtotal: RD$ ${parseFloat(data.subtotal).toLocaleString()}</small><br>
                            <small>ITBIS: RD$ ${parseFloat(data.itbis_total).toLocaleString()}</small><br>
                            ${descuento > 0 ? `<small class="text-warning">Descuento: - RD$ ${descuento.toLocaleString()}</small><br>` : ''}
                            ${data.usa_seguro && montoSeguro > 0 ? `<small class="texto-seguro">Seguro Médico: - RD$ ${montoSeguro.toLocaleString()}</small><br>` : ''}
                            <strong class="fs-4 text-success">TOTAL PAGADO: RD$ ${parseFloat(data.total).toLocaleString()}</strong>
                        </div></div>
                    </div>
                `;
                modalBody.innerHTML = html;
            } else modalBody.innerHTML = '<div class="text-center py-5 text-danger">Error al cargar detalles: ' + (data.message || '') + '</div>';
        }).catch(err => { modalBody.innerHTML = '<div class="text-center py-5 text-danger">Error de conexión</div>'; });
}

function abrirModalAbonoDesdeDetalle(idVenta, documento, total, abonosAcumulados) {
    const saldoPendiente = total - abonosAcumulados;
    document.getElementById('abonoIdVenta').value = idVenta;
    document.getElementById('abonoDocumento').value = documento;
    document.getElementById('abonoTotal').value = `RD$ ${parseFloat(total).toLocaleString()}`;
    document.getElementById('abonoSaldoPendiente').value = `RD$ ${saldoPendiente.toLocaleString()}`;
    document.getElementById('abonoMonto').value = '';
    document.getElementById('abonoReferencia').value = '';
    document.getElementById('abonoMonto').max = saldoPendiente;
    const modalDetalle = bootstrap.Modal.getInstance(document.getElementById('modalDetalleVenta'));
    if (modalDetalle) modalDetalle.hide();
    const modalAbonoElement = document.getElementById('modalAbono');
    const modalAbono = new bootstrap.Modal(modalAbonoElement, { backdrop: 'static', keyboard: true });
    modalAbono.show();
    setTimeout(() => { modalAbonoElement.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 200);
}

function abrirModalAbono(idVenta, documento, total, abonosAcumulados) {
    const saldoPendiente = total - abonosAcumulados;
    document.getElementById('abonoIdVenta').value = idVenta;
    document.getElementById('abonoDocumento').value = documento;
    document.getElementById('abonoTotal').value = `RD$ ${parseFloat(total).toLocaleString()}`;
    document.getElementById('abonoSaldoPendiente').value = `RD$ ${saldoPendiente.toLocaleString()}`;
    document.getElementById('abonoMonto').value = '';
    document.getElementById('abonoReferencia').value = '';
    document.getElementById('abonoMonto').max = saldoPendiente;
    const modalAbonoElement = document.getElementById('modalAbono');
    const modalAbono = new bootstrap.Modal(modalAbonoElement, { backdrop: 'static', keyboard: true });
    modalAbono.show();
    setTimeout(() => { modalAbonoElement.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 200);
}

function confirmarAbono() {
    const idVenta = document.getElementById('abonoIdVenta').value;
    const monto = parseFloat(document.getElementById('abonoMonto').value);
    const metodoPago = document.getElementById('abonoMetodoPago').value;
    const referencia = document.getElementById('abonoReferencia').value;
    const maxMonto = parseFloat(document.getElementById('abonoMonto').max);
    if (!monto || monto <= 0) { Swal.fire('Error', 'Ingrese un monto válido', 'error'); return; }
    if (monto > maxMonto) { Swal.fire('Error', `El monto no puede ser mayor al saldo pendiente (RD$ ${maxMonto.toLocaleString()})`, 'error'); return; }
    Swal.fire({
        title: 'Confirmar Abono',
        html: `<p>Monto a registrar: <strong>RD$ ${monto.toLocaleString()}</strong></p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Registrar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            fetch(BASE_URL + '/backend/ventas/registrar_abono.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_venta: idVenta, monto: monto, id_metodo_pago: metodoPago, referencia: referencia })
            }).then(r => r.json()).then(data => {
                Swal.close();
                if (data.success) Swal.fire('Éxito', 'Abono registrado correctamente', 'success').then(() => location.reload());
                else Swal.fire('Error', data.message || 'Error al registrar abono', 'error');
            }).catch(err => { Swal.close(); Swal.fire('Error', 'Error de conexión', 'error'); });
        }
    });
}

function imprimirVenta(idVenta) { window.open(BASE_URL + `/backend/ventas/imprimir_recibo.php?id_venta=${idVenta}`, '_blank'); }
function imprimirVentaDesdeModal() { if (ventaActualId) imprimirVenta(ventaActualId); }
function generarFactura(idVenta) {
    Swal.fire({
        title: 'Generar Factura Fiscal',
        html: `<div class="text-start"><div class="mb-3"><label class="form-label">Tipo de Comprobante</label><select id="tipoComprobante" class="form-select"><option value="FACTURA">Factura Fiscal</option><option value="CREDITO_FISCAL">Crédito Fiscal</option></select></div><div class="mb-3"><label class="form-label">RNC del Cliente</label><input type="text" id="rncCliente" class="form-control" placeholder="Ej: 101234567"></div></div>`,
        showCancelButton: true,
        confirmButtonText: 'Generar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => ({ tipoComprobante: document.getElementById('tipoComprobante').value, rncCliente: document.getElementById('rncCliente').value })
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Generando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            fetch(BASE_URL + '/backend/ventas/generar_factura.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_venta: idVenta, tipo_comprobante: result.value.tipoComprobante, rnc_cliente: result.value.rncCliente })
            }).then(r => r.json()).then(data => {
                Swal.close();
                if (data.success) Swal.fire('Éxito', 'Factura generada correctamente', 'success').then(() => location.reload());
                else Swal.fire('Error', data.message || 'Error al generar factura', 'error');
            });
        }
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        if (m === '"') return '&quot;';
        if (m === "'") return '&#39;';
        return m;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    ['filtroFechaDesde','filtroFechaHasta','filtroCliente','filtroUsuario','filtroEstado','busquedaInput'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', aplicarFiltros);
        if (id === 'busquedaInput') {
            let timeout;
            el.addEventListener('input', () => { clearTimeout(timeout); timeout = setTimeout(aplicarFiltros, 500); });
        }
    });
});
</script>