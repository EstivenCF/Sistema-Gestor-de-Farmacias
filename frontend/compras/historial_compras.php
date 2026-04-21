<?php
require_once __DIR__ . '/../../backend/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_sesion'])) {
    header("Location: ../index.php");
    exit();
}

// Obtener parámetros de filtro
$filtro_fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$filtro_proveedor = $_GET['proveedor'] ?? '';
$filtro_sucursal = $_GET['sucursal'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

// Obtener listas para filtros
$proveedores = [];
$sucursales = [];
$estados = [];

try {
    $proveedores = $conexion->query("SELECT id_proveedor, nombre FROM proveedores ORDER BY nombre")->fetchAll();
    $sucursales = $conexion->query("SELECT id_sucursal, nombre FROM sucursales WHERE estado = true ORDER BY nombre")->fetchAll();
    $estados = $conexion->query("SELECT id_estado, nombre FROM estado_compra ORDER BY id_estado")->fetchAll();
} catch(PDOException $e) {}

// Consulta principal
$query = "
    SELECT 
        c.id_compra,
        c.numero_documento,
        c.fecha,
        c.subtotal,
        c.descuento,
        c.itbis,
        c.total,
        c.observaciones,
        c.fecha_esperada,
        c.id_estado,
        p.nombre as proveedor_nombre,
        s.nombre as sucursal_nombre,
        u.nombre as usuario_nombre,
        ec.nombre as estado_nombre,
        (SELECT COUNT(*) FROM detalle_compra dc WHERE dc.id_compra = c.id_compra) as total_productos
    FROM compras c
    JOIN proveedores p ON c.id_proveedor = p.id_proveedor
    JOIN sucursales s ON c.id_sucursal = s.id_sucursal
    JOIN usuarios u ON c.id_usuario = u.id_usuario
    JOIN estado_compra ec ON c.id_estado = ec.id_estado
    WHERE 1=1
";

$params = [];
if ($filtro_fecha_desde && $filtro_fecha_hasta) {
    $query .= " AND DATE(c.fecha) BETWEEN :fecha_desde AND :fecha_hasta";
    $params[':fecha_desde'] = $filtro_fecha_desde;
    $params[':fecha_hasta'] = $filtro_fecha_hasta;
}
if ($filtro_proveedor) {
    $query .= " AND c.id_proveedor = :proveedor";
    $params[':proveedor'] = $filtro_proveedor;
}
if ($filtro_sucursal) {
    $query .= " AND c.id_sucursal = :sucursal";
    $params[':sucursal'] = $filtro_sucursal;
}
if ($filtro_estado) {
    $query .= " AND c.id_estado = :estado";
    $params[':estado'] = $filtro_estado;
}
if ($busqueda) {
    $query .= " AND (c.numero_documento ILIKE :busqueda OR p.nombre ILIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}
$query .= " ORDER BY c.fecha DESC LIMIT 500";

$compras = [];
try {
    $stmt = $conexion->prepare($query);
    $stmt->execute($params);
    $compras = $stmt->fetchAll();
} catch(PDOException $e) { $compras = []; }

$total_compras = count($compras);
$suma_total = 0;
$suma_itbis = 0;
foreach ($compras as $c) {
    $suma_total += floatval($c['total']);
    $suma_itbis += floatval($c['itbis']);
}

// Obtener lista de sucursales para el modal de recepción (todas activas)
$todas_sucursales = [];
try {
    $todas_sucursales = $conexion->query("SELECT id_sucursal, nombre FROM sucursales WHERE estado = true ORDER BY nombre")->fetchAll();
} catch(PDOException $e) {}

$base_url = '/sistema-gestor-de-farmacias';
?>

<style>
    .hv-filtros-bar {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    .badge-estado {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
    }
    .badge-pendiente { background-color: #ffc107; color: #000; }
    .badge-enviada { background-color: #17a2b8; color: #fff; }
    .badge-parcial { background-color: #fd7e14; color: #fff; }
    .badge-completada { background-color: #28a745; color: #fff; }
    .badge-anulada { background-color: #6c757d; color: #fff; }
    .card-total {
        background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        color: white;
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .card-total h3 {
        font-size: 1.8rem;
        margin: 0;
        font-weight: 700;
    }
    .detalle-item {
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .btn-quitar-filtros {
        background-color: #f1f3f5;
        color: #495057;
        border: 1.5px solid #dee2e6;
        border-radius: 10px;
        padding: 8px 20px;
    }
    .btn-cancelar {
        background-color: #f1f3f5;
        color: #495057;
        border: 1.5px solid #dee2e6;
        border-radius: 10px;
        padding: 10px 25px;
    }
    .dashboard-container {
        padding: 20px;
        animation: fadeSlideIn 0.5s ease-out;
    }
    @keyframes fadeSlideIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .table-hover tbody tr:hover {
        background-color: rgba(40,167,69,0.05);
        cursor: pointer;
    }
    .modal-content {
        border-radius: 20px;
        overflow: hidden;
    }
    .modal-header {
        border-bottom: none;
        padding: 20px 25px;
    }
    .modal-body {
        padding: 20px 25px;
    }
    .modal-footer {
        border-top: none;
        padding: 15px 25px 25px;
    }
    .btn-export-pdf {
        background-color: #dc3545;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 500;
        transition: all 0.2s ease;
        margin-left: 15px;
    }
    .btn-export-pdf:hover {
        background-color: #c82333;
        transform: translateY(-1px);
    }
    .btn-imprimir {
        background-color: #17a2b8;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 500;
        transition: all 0.2s ease;
        margin-left: 10px;
    }
    .btn-imprimir:hover {
        background-color: #138496;
        transform: translateY(-1px);
    }
    .header-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .recepcion-producto {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 15px;
        border-left: 4px solid #28a745;
    }
    .distribucion-row {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 8px;
        flex-wrap: wrap;
    }
    .distribucion-row select {
        width: 150px;
    }
    .distribucion-row input {
        width: 100px;
    }
    .btn-add-sucursal {
        font-size: 0.8rem;
        padding: 4px 8px;
    }
    .badge-pendiente-recibir {
        background-color: #ffc107;
        color: #000;
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 20px;
    }
    .alert-distribucion {
        background-color: #e7f3ff;
        border-left: 4px solid #17a2b8;
        padding: 8px;
        margin: 10px 0;
        font-size: 0.85rem;
    }
    @media print {
        .no-print, .btn-export-pdf, .btn-quitar-filtros, .pagination, .modal, .btn-group {
            display: none !important;
        }
        body {
            padding: 0;
            margin: 0;
        }
        .table {
            font-size: 10pt;
        }
    }
</style>

<div class="dashboard-container">
    <div class="header-actions">
        <div>
            <h2 class="mb-0 text-success">
                <span class="material-symbols-rounded align-middle me-2">receipt_long</span>
                Historial de Compras
            </h2>
            <p class="text-muted mb-0">Consulta y gestiona todas las órdenes de compra</p>
        </div>
        <button type="button" class="btn btn-export-pdf" onclick="exportarPDF()">
            <span class="material-symbols-rounded align-middle me-1">picture_as_pdf</span>
            Exportar a PDF
        </button>
    </div>

    <!-- Tarjetas de totales -->
    <div class="row mb-4">
        <div class="col-md-4"><div class="card-total text-center"><small>TOTAL DE COMPRAS</small><h3><?php echo $total_compras; ?></h3></div></div>
        <div class="col-md-4"><div class="card-total text-center"><small>MONTO TOTAL</small><h3>RD$ <?php echo number_format($suma_total, 2); ?></h3></div></div>
        <div class="col-md-4"><div class="card-total text-center"><small>ITBIS TOTAL</small><h3>RD$ <?php echo number_format($suma_itbis, 2); ?></h3></div></div>
    </div>

    <!-- Filtros -->
    <div class="hv-filtros-bar">
        <div class="row g-3 align-items-end">
            <div class="col-md-2"><label class="form-label fw-bold small text-muted">DESDE</label><input type="date" class="form-control" id="filtroFechaDesde" value="<?php echo $filtro_fecha_desde; ?>"></div>
            <div class="col-md-2"><label class="form-label fw-bold small text-muted">HASTA</label><input type="date" class="form-control" id="filtroFechaHasta" value="<?php echo $filtro_fecha_hasta; ?>"></div>
            <div class="col-md-2"><label class="form-label fw-bold small text-muted">PROVEEDOR</label><select class="form-select" id="filtroProveedor"><option value="">Todos</option><?php foreach ($proveedores as $prov): ?><option value="<?php echo $prov['id_proveedor']; ?>" <?php echo $filtro_proveedor == $prov['id_proveedor'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($prov['nombre']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label fw-bold small text-muted">SUCURSAL</label><select class="form-select" id="filtroSucursal"><option value="">Todas</option><?php foreach ($sucursales as $suc): ?><option value="<?php echo $suc['id_sucursal']; ?>" <?php echo $filtro_sucursal == $suc['id_sucursal'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($suc['nombre']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label fw-bold small text-muted">ESTADO</label><select class="form-select" id="filtroEstado"><option value="">Todos</option><?php foreach ($estados as $est): ?><option value="<?php echo $est['id_estado']; ?>" <?php echo $filtro_estado == $est['id_estado'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($est['nombre']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label fw-bold small text-muted">BUSCAR</label><input type="text" class="form-control" id="busquedaInput" placeholder="Documento, proveedor..." value="<?php echo htmlspecialchars($busqueda); ?>"></div>
        </div>
        <div class="row mt-3"><div class="col-12 text-end"><button type="button" class="btn btn-quitar-filtros" onclick="quitarFiltros()">Quitar filtros</button></div></div>
    </div>

    <!-- Tabla de compras -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaCompras">
                    <thead class="table-light"><tr><th>Nº Documento</th><th>Fecha</th><th>Proveedor</th><th>Sucursal</th><th>Usuario</th><th>Productos</th><th>Subtotal</th><th>Descuento</th><th>ITBIS</th><th>Total</th><th>Estado</th><th>Fecha Esperada</th><th class="text-center">Acciones</th></tr></thead>
                    <tbody id="tablaComprasBody">
                        <?php if (empty($compras)): ?>
                            <tr><td colspan="13" class="text-center text-muted py-5"><i class="fas fa-receipt d-block mb-3" style="font-size: 3rem; opacity: 0.3;"></i>No hay compras registradas con los filtros seleccionados</td></tr>
                        <?php else: foreach ($compras as $compra): 
                            $estado_class = '';
                            switch ($compra['estado_nombre']) {
                                case 'PENDIENTE': $estado_class = 'badge-pendiente'; break;
                                case 'ENVIADA': $estado_class = 'badge-enviada'; break;
                                case 'PARCIAL': $estado_class = 'badge-parcial'; break;
                                case 'COMPLETADA': $estado_class = 'badge-completada'; break;
                                case 'ANULADA': $estado_class = 'badge-anulada'; break;
                                default: $estado_class = 'badge-pendiente';
                            }
                            $fecha_esperada = !empty($compra['fecha_esperada']) ? date('d/m/Y', strtotime($compra['fecha_esperada'])) : '—';
                            $mostrar_recibir = in_array($compra['estado_nombre'], ['PENDIENTE', 'ENVIADA', 'PARCIAL']);
                        ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($compra['numero_documento']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($compra['fecha'])); ?></td>
                                <td><?php echo htmlspecialchars($compra['proveedor_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($compra['sucursal_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($compra['usuario_nombre']); ?></td>
                                <td><?php echo $compra['total_productos']; ?></td>
                                <td>RD$ <?php echo number_format(floatval($compra['subtotal']), 2); ?></td>
                                <td>RD$ <?php echo number_format(floatval($compra['descuento']), 2); ?></td>
                                <td>RD$ <?php echo number_format(floatval($compra['itbis']), 2); ?></td>
                                <td class="fw-bold text-success">RD$ <?php echo number_format(floatval($compra['total']), 2); ?></td>
                                <td><span class="badge-estado <?php echo $estado_class; ?>"><?php echo htmlspecialchars($compra['estado_nombre']); ?></span></td>
                                <td><?php echo $fecha_esperada; ?></td>
                                <td class="text-center">
                                    <button class="btn btn-outline-primary btn-sm me-1" onclick="verDetalleCompra(<?php echo $compra['id_compra']; ?>)"><span class="material-symbols-rounded">visibility</span></button>
                                    <?php if ($mostrar_recibir): ?>
                                        <button class="btn btn-outline-success btn-sm" onclick="abrirRecepcion(<?php echo $compra['id_compra']; ?>)"><span class="material-symbols-rounded">inbox</span> Recibir</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DETALLE DE COMPRA - SIN BACKDROP -->
<div class="modal fade" id="modalDetalleCompra" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Detalle de Compra</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleCompraContenido">
                <div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando...</p></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="imprimirDetalleCompra()">Imprimir</button>
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL RECEPCIÓN DE COMPRA - SIN BACKDROP -->
<div class="modal fade" id="modalRecepcionCompra" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Recepción de Compra</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="recepcionCompraContenido">
                <div class="text-center py-5"><div class="spinner-border text-info"></div><p>Cargando...</p></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="confirmarRecepcion()">Confirmar Recepción</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';
let compraActualId = null;
let sucursalesDisponibles = <?php echo json_encode($todas_sucursales); ?>;
let ultimoDetalleHTML = ''; // Guardar el HTML del detalle para imprimir

// ==================== FILTROS ====================
document.addEventListener('DOMContentLoaded', function() {
    const filtros = ['filtroFechaDesde', 'filtroFechaHasta', 'filtroProveedor', 'filtroSucursal', 'filtroEstado', 'busquedaInput'];
    filtros.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', aplicarFiltros);
            if (id === 'busquedaInput') {
                let timeout;
                el.addEventListener('input', () => { clearTimeout(timeout); timeout = setTimeout(aplicarFiltros, 500); });
            }
        }
    });
});

function aplicarFiltros() {
    let url = BASE_URL + '/frontend/menuprincipal.php?mod=historial_compras';
    const fd = document.getElementById('filtroFechaDesde').value;
    const fh = document.getElementById('filtroFechaHasta').value;
    const prov = document.getElementById('filtroProveedor').value;
    const suc = document.getElementById('filtroSucursal').value;
    const est = document.getElementById('filtroEstado').value;
    const bus = document.getElementById('busquedaInput').value;
    if (fd) url += `&fecha_desde=${fd}`;
    if (fh) url += `&fecha_hasta=${fh}`;
    if (prov) url += `&proveedor=${prov}`;
    if (suc) url += `&sucursal=${suc}`;
    if (est) url += `&estado=${est}`;
    if (bus) url += `&busqueda=${encodeURIComponent(bus)}`;
    window.location.href = url;
}

function quitarFiltros() { window.location.href = BASE_URL + '/frontend/menuprincipal.php?mod=historial_compras'; }

// ==================== DETALLE COMPRA ====================
function verDetalleCompra(idCompra) {
    compraActualId = idCompra;
    const modalBody = document.getElementById('detalleCompraContenido');
    modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando...</p></div>';
    const modal = new bootstrap.Modal(document.getElementById('modalDetalleCompra'), { backdrop: false, keyboard: true });
    modal.show();
    
    fetch(BASE_URL + `/backend/compras/get_detalle_compra.php?id_compra=${idCompra}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                let productosHtml = '';
                if (data.productos && data.productos.length) {
                    data.productos.forEach(p => {
                        let distribucionHtml = '';
                        if (p.distribucion && p.distribucion.length > 0) {
                            distribucionHtml = '<div class="small text-muted mt-1"><strong>Recibido en:</strong><ul class="mb-0 ps-3">';
                            p.distribucion.forEach(d => {
                                distribucionHtml += `<li>${escapeHtml(d.sucursal_nombre)}: ${d.cantidad_recibida_en_sucursal} unidades</li>`;
                            });
                            distribucionHtml += '</ul></div>';
                        } else if (p.recibido > 0) {
                            distribucionHtml = '<div class="small text-muted mt-1"><strong>Recibido en:</strong> Sucursal de compra</div>';
                        } else {
                            distribucionHtml = '<div class="small text-muted mt-1">Pendiente por recibir</div>';
                        }
                        productosHtml += `
                            <div class="detalle-item d-flex justify-content-between">
                                <div>
                                    <strong>${escapeHtml(p.producto_nombre)}</strong><br>
                                    <small>Lote: ${escapeHtml(p.numero_lote || 'Pendiente')} | Vence: ${p.fecha_vencimiento || '—'} | Cant: ${p.cantidad} | Recibido: ${p.recibido}</small>
                                    ${distribucionHtml}
                                </div>
                                <div class="text-end">
                                    <div>RD$ ${parseFloat(p.precio_unitario).toLocaleString()} c/u</div>
                                    <div class="fw-bold text-success">RD$ ${parseFloat(p.cantidad * p.precio_unitario).toLocaleString()}</div>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    productosHtml = '<div class="text-center text-muted">No hay productos</div>';
                }
                
                const html = `
                    <div class="row g-3">
                        <div class="col-md-4"><div class="bg-light p-3 rounded"><small>Nº DOCUMENTO</small><strong>${escapeHtml(data.numero_documento)}</strong></div></div>
                        <div class="col-md-4"><div class="bg-light p-3 rounded"><small>FECHA</small><strong>${data.fecha}</strong></div></div>
                        <div class="col-md-4"><div class="bg-light p-3 rounded"><small>ESTADO</small><strong>${escapeHtml(data.estado_nombre)}</strong></div></div>
                        <div class="col-md-4"><div class="bg-light p-3 rounded"><small>PROVEEDOR</small><strong>${escapeHtml(data.proveedor_nombre)}</strong></div></div>
                        <div class="col-md-4"><div class="bg-light p-3 rounded"><small>SUCURSAL DE COMPRA</small><strong>${escapeHtml(data.sucursal_nombre)}</strong></div></div>
                        <div class="col-md-4"><div class="bg-light p-3 rounded"><small>USUARIO</small><strong>${escapeHtml(data.usuario_nombre)}</strong></div></div>
                        ${data.fecha_esperada ? `<div class="col-md-4"><div class="bg-light p-3 rounded"><small>FECHA ESPERADA</small><strong>${data.fecha_esperada}</strong></div></div>` : ''}
                        ${data.observaciones ? `<div class="col-12"><div class="bg-light p-3 rounded"><small>OBSERVACIONES</small><p>${escapeHtml(data.observaciones)}</p></div></div>` : ''}
                        <div class="col-12"><div class="bg-light p-3 rounded"><small>PRODUCTOS</small>${productosHtml}</div></div>
                        <div class="col-12"><div class="bg-success bg-opacity-10 p-3 rounded text-end">
                            <small>Subtotal: RD$ ${parseFloat(data.subtotal).toLocaleString()}</small><br>
                            <small>Descuento: RD$ ${parseFloat(data.descuento).toLocaleString()}</small><br>
                            <small>ITBIS: RD$ ${parseFloat(data.itbis).toLocaleString()}</small><br>
                            <strong class="fs-4 text-success">TOTAL: RD$ ${parseFloat(data.total).toLocaleString()}</strong>
                        </div></div>
                    </div>
                `;
                modalBody.innerHTML = html;
                ultimoDetalleHTML = html; // Guardar para impresión
            } else {
                modalBody.innerHTML = '<div class="text-center py-5 text-danger">Error al cargar detalles: ' + (data.message || '') + '</div>';
                ultimoDetalleHTML = '';
            }
        })
        .catch(() => {
            modalBody.innerHTML = '<div class="text-center py-5 text-danger">Error de conexión</div>';
            ultimoDetalleHTML = '';
        });
}

function imprimirDetalleCompra() {
    if (!ultimoDetalleHTML || ultimoDetalleHTML === '') {
        Swal.fire('Error', 'No hay información para imprimir', 'error');
        return;
    }
    
    // Crear un documento HTML para el PDF
    const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Detalle de Compra</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                    font-size: 12px;
                }
                h1 {
                    color: #28a745;
                    text-align: center;
                    margin-bottom: 5px;
                }
                .header-info {
                    margin-bottom: 20px;
                    border-bottom: 1px solid #ddd;
                    padding-bottom: 10px;
                }
                .detalle-item {
                    padding: 8px 0;
                    border-bottom: 1px solid #f0f0f0;
                }
                .bg-light {
                    background-color: #f8f9fa;
                    padding: 10px;
                    border-radius: 5px;
                }
                .text-success {
                    color: #28a745;
                }
                .text-end {
                    text-align: right;
                }
                .fw-bold {
                    font-weight: bold;
                }
                .row {
                    display: flex;
                    flex-wrap: wrap;
                    margin: 0 -10px;
                }
                .col-md-4, .col-12 {
                    padding: 0 10px;
                    box-sizing: border-box;
                }
                .col-md-4 {
                    width: 33.33%;
                }
                .col-12 {
                    width: 100%;
                }
                .mt-3 {
                    margin-top: 15px;
                }
                .mb-2 {
                    margin-bottom: 10px;
                }
                .footer {
                    margin-top: 30px;
                    text-align: right;
                    font-size: 10px;
                    color: #888;
                    border-top: 1px solid #ddd;
                    padding-top: 10px;
                }
                ul {
                    margin: 0;
                    padding-left: 20px;
                }
            </style>
        </head>
        <body>
            <h1>Detalle de Compra</h1>
            <div class="header-info">
                ${ultimoDetalleHTML}
            </div>
            <div class="footer">
                Reporte generado el ${new Date().toLocaleString()} por ${'<?php echo $_SESSION['usuario'] ?? 'Sistema'; ?>'}
            </div>
        </body>
        </html>
    `;
    
    const element = document.createElement('div');
    element.innerHTML = printContent;
    document.body.appendChild(element);
    
    const opt = {
        margin: [0.5, 0.5, 0.5, 0.5],
        filename: `detalle_compra_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    
    html2pdf().set(opt).from(element).save().then(() => {
        document.body.removeChild(element);
        Swal.fire({ icon: 'success', title: 'PDF generado', text: 'El detalle se ha exportado correctamente', timer: 2000, showConfirmButton: false });
    }).catch(() => {
        document.body.removeChild(element);
        Swal.fire('Error', 'Error al generar el PDF', 'error');
    });
}

// ==================== RECEPCIÓN DE COMPRA ====================
function abrirRecepcion(idCompra) {
    compraActualId = idCompra;
    const modalBody = document.getElementById('recepcionCompraContenido');
    modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-info"></div><p>Cargando productos pendientes...</p></div>';
    const modal = new bootstrap.Modal(document.getElementById('modalRecepcionCompra'), { backdrop: false, keyboard: true });
    modal.show();
    fetch(BASE_URL + `/backend/compras/get_detalle_compra_pendiente.php?id_compra=${idCompra}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderizarFormularioRecepcion(data);
            } else {
                modalBody.innerHTML = '<div class="alert alert-danger">Error al cargar los productos: ' + (data.message || '') + '</div>';
            }
        })
        .catch(() => modalBody.innerHTML = '<div class="alert alert-danger">Error de conexión</div>');
}

function renderizarFormularioRecepcion(data) {
    const container = document.getElementById('recepcionCompraContenido');
    if (!data.productos || data.productos.length === 0) {
        container.innerHTML = '<div class="alert alert-warning">No hay productos pendientes por recibir en esta compra.</div>';
        return;
    }
    let html = `<div class="alert alert-info"><strong>Compra:</strong> ${escapeHtml(data.numero_documento)}<br><strong>Proveedor:</strong> ${escapeHtml(data.proveedor_nombre)}<br><strong>Fecha:</strong> ${data.fecha}</div><form id="formRecepcion"><input type="hidden" id="recepcion_id_compra" value="${data.id_compra}">`;
    data.productos.forEach(prod => {
        const pendiente = prod.pendiente;
        const disabled = (pendiente <= 0) ? 'disabled' : '';
        html += `
            <div class="recepcion-producto" data-id-detalle="${prod.id_detalle}" data-id-lote="${prod.id_lote || ''}" data-pendiente="${pendiente}">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <strong>${escapeHtml(prod.producto_nombre)}</strong>
                        <span class="badge-pendiente-recibir ms-2">Pendiente: ${pendiente}</span>
                        <div><small>Lote: ${escapeHtml(prod.numero_lote)} | Vence: ${prod.fecha_vencimiento || '—'}</small></div>
                    </div>
                    <div class="text-end">
                        <label class="form-label small">Cantidad a recibir:</label>
                        <input type="number" class="form-control form-control-sm cantidad-recibir" style="width: 100px;" 
                               min="0" max="${pendiente}" step="1" value="0" ${disabled}>
                    </div>
                </div>
                <div class="distribucion-container" style="display: none;">
                    <div class="alert-distribucion">
                        <i class="fas fa-store"></i> <strong>Distribuir a sucursales</strong><br>
                        <small>Asigne la cantidad recibida a una o varias sucursales. La suma debe coincidir con la cantidad a recibir.</small>
                    </div>
                    <div class="distribucion-list"></div>
                    <button type="button" class="btn btn-sm btn-outline-primary btn-add-sucursal mt-1">+ Agregar sucursal</button>
                </div>
            </div>
        `;
    });
    html += `</form>`;
    container.innerHTML = html;
    
    document.querySelectorAll('.recepcion-producto').forEach(productoDiv => {
        const cantidadInput = productoDiv.querySelector('.cantidad-recibir');
        const distribucionContainer = productoDiv.querySelector('.distribucion-container');
        const distribucionList = productoDiv.querySelector('.distribucion-list');
        const btnAdd = productoDiv.querySelector('.btn-add-sucursal');
        
        cantidadInput.addEventListener('change', function() {
            const cantidad = parseInt(this.value) || 0;
            if (cantidad > 0) {
                distribucionContainer.style.display = 'block';
                if (distribucionList.children.length === 0) {
                    agregarFilaDistribucion(productoDiv, cantidad);
                }
                validarDistribucion(productoDiv);
            } else {
                distribucionContainer.style.display = 'none';
                distribucionList.innerHTML = '';
            }
        });
        
        btnAdd.addEventListener('click', () => agregarFilaDistribucion(productoDiv, 0));
    });
}

function agregarFilaDistribucion(productoDiv, cantidadSugerida = 0) {
    const distribucionList = productoDiv.querySelector('.distribucion-list');
    const cantidadRecibir = parseInt(productoDiv.querySelector('.cantidad-recibir').value) || 0;
    let sugerida = (cantidadSugerida > 0 && cantidadSugerida <= cantidadRecibir) ? cantidadSugerida : 0;
    const row = document.createElement('div');
    row.className = 'distribucion-row';
    row.innerHTML = `
        <select class="form-select form-select-sm sucursal-select" style="width: 150px;">
            <option value="">Seleccione sucursal</option>
            ${sucursalesDisponibles.map(s => `<option value="${s.id_sucursal}">${escapeHtml(s.nombre)}</option>`).join('')}
        </select>
        <input type="number" class="form-control form-control-sm cantidad-distribuir" placeholder="Cantidad" min="0" step="1" style="width: 100px;" value="${sugerida}">
        <button type="button" class="btn btn-sm btn-outline-danger eliminar-fila">✖</button>
    `;
    row.querySelector('.eliminar-fila').addEventListener('click', () => { row.remove(); validarDistribucion(productoDiv); });
    row.querySelector('.cantidad-distribuir').addEventListener('input', () => validarDistribucion(productoDiv));
    row.querySelector('.sucursal-select').addEventListener('change', () => validarDistribucion(productoDiv));
    distribucionList.appendChild(row);
    validarDistribucion(productoDiv);
}

function validarDistribucion(productoDiv) {
    const cantidadRecibir = parseInt(productoDiv.querySelector('.cantidad-recibir').value) || 0;
    const distribucionRows = productoDiv.querySelectorAll('.distribucion-row');
    let sumaDistribucion = 0;
    let todasValidas = true;
    const sucursalesUsadas = [];
    
    distribucionRows.forEach(row => {
        const sucursal = row.querySelector('.sucursal-select').value;
        const cantidad = parseInt(row.querySelector('.cantidad-distribuir').value) || 0;
        sumaDistribucion += cantidad;
        
        if (sucursal !== '' && sucursalesUsadas.includes(sucursal)) {
            row.style.border = '1px solid red';
            todasValidas = false;
        } else {
            row.style.border = '';
            if (sucursal !== '') sucursalesUsadas.push(sucursal);
        }
        if (cantidad < 0) {
            row.style.border = '1px solid red';
            todasValidas = false;
        }
    });
    
    const diferencia = cantidadRecibir - sumaDistribucion;
    if (diferencia !== 0 || sumaDistribucion === 0 || !todasValidas) {
        productoDiv.style.borderLeftColor = '#dc3545';
        productoDiv.setAttribute('data-valid', 'false');
        let errorMsg = productoDiv.querySelector('.error-msg');
        if (!errorMsg) {
            errorMsg = document.createElement('div');
            errorMsg.className = 'text-danger small mt-2 error-msg';
            productoDiv.appendChild(errorMsg);
        }
        if (!todasValidas) {
            errorMsg.textContent = 'Error: No se puede asignar la misma sucursal más de una vez.';
        } else if (sumaDistribucion === 0 && cantidadRecibir > 0) {
            errorMsg.textContent = 'Debe distribuir la cantidad recibida en al menos una sucursal.';
        } else {
            errorMsg.textContent = `La suma de distribuciones (${sumaDistribucion}) no coincide con la cantidad a recibir (${cantidadRecibir}). Faltan ${diferencia > 0 ? diferencia : -diferencia} unidades.`;
        }
    } else {
        productoDiv.style.borderLeftColor = '#28a745';
        productoDiv.setAttribute('data-valid', 'true');
        const errorMsg = productoDiv.querySelector('.error-msg');
        if (errorMsg) errorMsg.remove();
    }
}

function confirmarRecepcion() {
    const productos = document.querySelectorAll('.recepcion-producto');
    let valido = true;
    let items = [];

    productos.forEach(prodDiv => {
        const cantidadRecibir = parseInt(prodDiv.querySelector('.cantidad-recibir').value) || 0;
        if (cantidadRecibir === 0) return;

        const idDetalle = prodDiv.dataset.idDetalle;
        const idLoteRaw = prodDiv.dataset.idLote;
        const idLote = (idLoteRaw && idLoteRaw !== "null" && idLoteRaw !== "") ? parseInt(idLoteRaw) : null;
        const distribuciones = [];
        const distribRows = prodDiv.querySelectorAll('.distribucion-row');
        let sumaDist = 0;

        distribRows.forEach(row => {
            const idSucursal = row.querySelector('.sucursal-select').value;
            const cantidad = parseInt(row.querySelector('.cantidad-distribuir').value) || 0;
            if (idSucursal && idSucursal !== "" && cantidad > 0) {
                distribuciones.push({ id_sucursal: parseInt(idSucursal), cantidad: cantidad });
                sumaDist += cantidad;
            }
        });

        let errorMsg = prodDiv.querySelector('.error-msg');
        if (!errorMsg) {
            errorMsg = document.createElement('div');
            errorMsg.className = 'text-danger small mt-2 error-msg';
            prodDiv.appendChild(errorMsg);
        }

        if (distribuciones.length === 0 && cantidadRecibir > 0) {
            valido = false;
            prodDiv.style.borderLeftColor = '#dc3545';
            errorMsg.textContent = 'Debe distribuir la cantidad en al menos una sucursal.';
        } else if (sumaDist !== cantidadRecibir) {
            valido = false;
            prodDiv.style.borderLeftColor = '#dc3545';
            errorMsg.textContent = `La suma de distribuciones (${sumaDist}) no coincide con la cantidad a recibir (${cantidadRecibir}).`;
        } else {
            prodDiv.style.borderLeftColor = '#28a745';
            errorMsg.textContent = '';
            items.push({
                id_detalle_compra: parseInt(idDetalle),
                id_lote: idLote,
                cantidad: cantidadRecibir,
                distribuciones: distribuciones
            });
        }
    });

    if (!valido || items.length === 0) {
        Swal.fire('Error', 'Corrija los errores en los productos antes de confirmar.', 'error');
        return;
    }

    Swal.fire({
        title: 'Confirmar recepción',
        text: '¿Está seguro de registrar la recepción de estos productos?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, recibir',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            fetch(BASE_URL + '/backend/compras/registrar_recepcion_compra.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_compra: compraActualId, items: items })
            })
            .then(r => r.json())
            .then(resp => {
                Swal.close();
                if (resp.success) {
                    Swal.fire('Éxito', 'Recepción registrada correctamente', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', resp.message || 'Error al registrar la recepción', 'error');
                }
            })
            .catch(err => {
                Swal.close();
                Swal.fire('Error', 'Error de conexión', 'error');
            });
        }
    });
}

// ==================== EXPORTAR PDF ====================
function exportarPDF() {
    const tabla = document.getElementById('tablaCompras');
    if (!tabla || tabla.rows.length === 0) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    const fechaDesde = document.getElementById('filtroFechaDesde').value;
    const fechaHasta = document.getElementById('filtroFechaHasta').value;
    const proveedor = document.getElementById('filtroProveedor').options[document.getElementById('filtroProveedor').selectedIndex]?.text || 'Todos';
    const sucursal = document.getElementById('filtroSucursal').options[document.getElementById('filtroSucursal').selectedIndex]?.text || 'Todas';
    const estado = document.getElementById('filtroEstado').options[document.getElementById('filtroEstado').selectedIndex]?.text || 'Todos';
    const busqueda = document.getElementById('busquedaInput').value || 'Sin búsqueda';
    let htmlContent = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Reporte de Compras</title><style>body{font-family:Arial,sans-serif;margin:20px;font-size:12px;}h1{color:#28a745;text-align:center;}.filters{text-align:center;margin-bottom:20px;font-size:10px;color:#555;}table{width:100%;border-collapse:collapse;margin-top:15px;}th,td{border:1px solid #ddd;padding:6px;text-align:left;}th{background-color:#28a745;color:white;}.footer{margin-top:20px;text-align:right;font-size:9px;color:#888;}</style></head><body><h1>Reporte de Compras</h1><div class="filters">Período: ${fechaDesde} - ${fechaHasta}<br>Proveedor: ${proveedor} | Sucursal: ${sucursal} | Estado: ${estado} | Búsqueda: ${busqueda}</div></td><thead><tr><th>Nº Documento</th><th>Fecha</th><th>Proveedor</th><th>Sucursal</th><th>Usuario</th><th>Productos</th><th>Subtotal</th><th>Descuento</th><th>ITBIS</th><th>Total</th><th>Estado</th><th>Fecha Esperada</th></tr></thead><tbody>`;
    const tbody = document.getElementById('tablaComprasBody');
    const rows = tbody.querySelectorAll('tr');
    let totalGeneral = 0;
    rows.forEach(row => {
        if (row.querySelector('.text-muted')) return;
        const cells = row.querySelectorAll('td');
        if (cells.length >= 12) {
            const total = cells[9]?.innerText.replace('RD$', '').trim() || '0';
            totalGeneral += parseFloat(total.replace(/\./g, '').replace(',', '.'));
            htmlContent += `<tr>
                <td>${escapeHtml(cells[0]?.innerText || '')}</td>
                <td>${escapeHtml(cells[1]?.innerText || '')}</td>
                <td>${escapeHtml(cells[2]?.innerText || '')}</td>
                <td>${escapeHtml(cells[3]?.innerText || '')}</td>
                <td>${escapeHtml(cells[4]?.innerText || '')}</td>
                <td class="text-right">${escapeHtml(cells[5]?.innerText || '')}</td>
                <td class="text-right">${escapeHtml(cells[6]?.innerText || '')}</td>
                <td class="text-right">${escapeHtml(cells[7]?.innerText || '')}</td>
                <td class="text-right">${escapeHtml(cells[8]?.innerText || '')}</td>
                <td class="text-right">${escapeHtml(cells[9]?.innerText || '')}</td>
                <td>${escapeHtml(cells[10]?.innerText || '')}</td>
                <td>${escapeHtml(cells[11]?.innerText || '')}</td>
            </tr>`;
        }
    });
    htmlContent += `<tr class="total-row"><td colspan="9" class="text-right"><strong>Total General:</strong></td><td class="text-right"><strong>RD$ ${totalGeneral.toFixed(2)}</strong></td><td colspan="2"></td></tr>`;
    htmlContent += `</tbody><table><div class="footer">Reporte generado el ${new Date().toLocaleString()}</div></body></html>`;
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    const opt = { margin: [0.5, 0.5, 0.5, 0.5], filename: `reporte_compras_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' } };
    html2pdf().set(opt).from(element).save().then(() => { document.body.removeChild(element); Swal.fire({ icon: 'success', title: 'Exportado', text: 'El reporte se ha generado correctamente', timer: 2000, showConfirmButton: false }); }).catch(() => { document.body.removeChild(element); Swal.fire('Error', 'Error al generar el PDF', 'error'); });
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
</script>