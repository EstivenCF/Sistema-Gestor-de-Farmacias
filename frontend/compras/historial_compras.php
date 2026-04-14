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
    /* Estilos para el botón de exportar PDF */
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
    .header-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    /* Ocultar elementos no deseados en el PDF */
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
        <div class="col-md-4">
            <div class="card-total text-center">
                <small>TOTAL DE COMPRAS</small>
                <h3><?php echo $total_compras; ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-total text-center">
                <small>MONTO TOTAL</small>
                <h3>RD$ <?php echo number_format($suma_total, 2); ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-total text-center">
                <small>ITBIS TOTAL</small>
                <h3>RD$ <?php echo number_format($suma_itbis, 2); ?></h3>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="hv-filtros-bar">
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">DESDE</label>
                <input type="date" class="form-control" id="filtroFechaDesde" value="<?php echo $filtro_fecha_desde; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">HASTA</label>
                <input type="date" class="form-control" id="filtroFechaHasta" value="<?php echo $filtro_fecha_hasta; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">PROVEEDOR</label>
                <select class="form-select" id="filtroProveedor">
                    <option value="">Todos</option>
                    <?php foreach ($proveedores as $prov): ?>
                        <option value="<?php echo $prov['id_proveedor']; ?>" <?php echo $filtro_proveedor == $prov['id_proveedor'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($prov['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">SUCURSAL</label>
                <select class="form-select" id="filtroSucursal">
                    <option value="">Todas</option>
                    <?php foreach ($sucursales as $suc): ?>
                        <option value="<?php echo $suc['id_sucursal']; ?>" <?php echo $filtro_sucursal == $suc['id_sucursal'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($suc['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">ESTADO</label>
                <select class="form-select" id="filtroEstado">
                    <option value="">Todos</option>
                    <?php foreach ($estados as $est): ?>
                        <option value="<?php echo $est['id_estado']; ?>" <?php echo $filtro_estado == $est['id_estado'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($est['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">BUSCAR</label>
                <input type="text" class="form-control" id="busquedaInput" placeholder="Documento, proveedor..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12 text-end">
                <button type="button" class="btn btn-quitar-filtros" onclick="quitarFiltros()">Quitar filtros</button>
            </div>
        </div>
    </div>

    <!-- Tabla de compras -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaCompras">
                    <thead class="table-light">
                        <tr>
                            <th>Nº Documento</th>
                            <th>Fecha</th>
                            <th>Proveedor</th>
                            <th>Sucursal</th>
                            <th>Usuario</th>
                            <th>Productos</th>
                            <th>Subtotal</th>
                            <th>Descuento</th>
                            <th>ITBIS</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Fecha Esperada</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaComprasBody">
                        <?php if (empty($compras)): ?>
                            <tr>
                                <td colspan="13" class="text-center text-muted py-5">
                                    <i class="fas fa-receipt d-block mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                                    No hay compras registradas con los filtros seleccionados
                                </td>
                            </tr>
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
                                    <button class="btn btn-outline-primary btn-sm" onclick="verDetalleCompra(<?php echo $compra['id_compra']; ?>)">
                                        <span class="material-symbols-rounded">visibility</span>
                                    </button>
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
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';

// Filtros automáticos
document.addEventListener('DOMContentLoaded', function() {
    const filtros = ['filtroFechaDesde', 'filtroFechaHasta', 'filtroProveedor', 'filtroSucursal', 'filtroEstado', 'busquedaInput'];
    filtros.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', aplicarFiltros);
            if (id === 'busquedaInput') {
                let timeout;
                el.addEventListener('input', () => {
                    clearTimeout(timeout);
                    timeout = setTimeout(aplicarFiltros, 500);
                });
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

function quitarFiltros() {
    window.location.href = BASE_URL + '/frontend/menuprincipal.php?mod=historial_compras';
}

function verDetalleCompra(idCompra) {
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
                        productosHtml += `<div class="detalle-item d-flex justify-content-between">
                            <div><strong>${escapeHtml(p.producto_nombre)}</strong><br>
                            <small>Lote: ${escapeHtml(p.numero_lote)} | Vence: ${p.fecha_vencimiento} | Cant: ${p.cantidad}</small></div>
                            <div class="text-end">
                                <div>RD$ ${parseFloat(p.precio_unitario).toLocaleString()} c/u</div>
                                <div class="fw-bold text-success">RD$ ${parseFloat(p.subtotal).toLocaleString()}</div>
                            </div>
                        </div>`;
                    });
                } else productosHtml = '<div class="text-center text-muted">No hay productos</div>';
                const html = `
                    <div class="row g-3">
                        <div class="col-md-4"><div class="bg-light p-3 rounded"><small>Nº DOCUMENTO</small><strong>${escapeHtml(data.numero_documento)}</strong></div></div>
                        <div class="col-md-4"><div class="bg-light p-3 rounded"><small>FECHA</small><strong>${data.fecha}</strong></div></div>
                        <div class="col-md-4"><div class="bg-light p-3 rounded"><small>ESTADO</small><strong>${escapeHtml(data.estado_nombre)}</strong></div></div>
                        <div class="col-md-4"><div class="bg-light p-3 rounded"><small>PROVEEDOR</small><strong>${escapeHtml(data.proveedor_nombre)}</strong></div></div>
                        <div class="col-md-4"><div class="bg-light p-3 rounded"><small>SUCURSAL</small><strong>${escapeHtml(data.sucursal_nombre)}</strong></div></div>
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
            } else {
                modalBody.innerHTML = '<div class="text-center py-5 text-danger">Error al cargar detalles</div>';
            }
        })
        .catch(() => modalBody.innerHTML = '<div class="text-center py-5 text-danger">Error de conexión</div>');
}

// ==================== EXPORTAR A PDF (CLIENT-SIDE) ====================
function exportarPDF() {
    const tabla = document.getElementById('tablaCompras');
    if (!tabla || tabla.rows.length === 0) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    // Obtener los filtros actuales para mostrarlos en el PDF
    const fechaDesde = document.getElementById('filtroFechaDesde').value;
    const fechaHasta = document.getElementById('filtroFechaHasta').value;
    const proveedor = document.getElementById('filtroProveedor').options[document.getElementById('filtroProveedor').selectedIndex]?.text || 'Todos';
    const sucursal = document.getElementById('filtroSucursal').options[document.getElementById('filtroSucursal').selectedIndex]?.text || 'Todas';
    const estado = document.getElementById('filtroEstado').options[document.getElementById('filtroEstado').selectedIndex]?.text || 'Todos';
    const busqueda = document.getElementById('busquedaInput').value || 'Sin búsqueda';
    
    // Construir HTML para el PDF
    let htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Reporte de Compras</title>
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
                .filters {
                    text-align: center;
                    margin-bottom: 20px;
                    font-size: 10px;
                    color: #555;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 15px;
                }
                th, td {
                    border: 1px solid #ddd;
                    padding: 6px;
                    text-align: left;
                }
                th {
                    background-color: #28a745;
                    color: white;
                    font-weight: bold;
                }
                .footer {
                    margin-top: 20px;
                    text-align: right;
                    font-size: 9px;
                    color: #888;
                }
                .total-row {
                    font-weight: bold;
                    background-color: #f9f9f9;
                }
                .text-right {
                    text-align: right;
                }
            </style>
        </head>
        <body>
            <h1>Reporte de Compras</h1>
            <div class="filters">
                Período: ${fechaDesde} - ${fechaHasta}<br>
                Proveedor: ${proveedor} | Sucursal: ${sucursal} | Estado: ${estado} | Búsqueda: ${busqueda}
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Nº Documento</th>
                        <th>Fecha</th>
                        <th>Proveedor</th>
                        <th>Sucursal</th>
                        <th>Usuario</th>
                        <th>Productos</th>
                        <th class="text-right">Subtotal</th>
                        <th class="text-right">Descuento</th>
                        <th class="text-right">ITBIS</th>
                        <th class="text-right">Total</th>
                        <th>Estado</th>
                        <th>Fecha Esperada</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    // Recorrer las filas de la tabla (omitir cabecera)
    const tbody = document.getElementById('tablaComprasBody');
    const rows = tbody.querySelectorAll('tr');
    let totalGeneral = 0;
    
    rows.forEach(row => {
        // Si es la fila de "No hay compras", omitir
        if (row.querySelector('.text-muted')) return;
        
        const cells = row.querySelectorAll('td');
        if (cells.length >= 12) {
            const documento = cells[0]?.innerText || '';
            const fecha = cells[1]?.innerText || '';
            const proveedorNombre = cells[2]?.innerText || '';
            const sucursalNombre = cells[3]?.innerText || '';
            const usuarioNombre = cells[4]?.innerText || '';
            const productos = cells[5]?.innerText || '0';
            const subtotal = cells[6]?.innerText.replace('RD$', '').trim() || '0';
            const descuento = cells[7]?.innerText.replace('RD$', '').trim() || '0';
            const itbis = cells[8]?.innerText.replace('RD$', '').trim() || '0';
            const total = cells[9]?.innerText.replace('RD$', '').trim() || '0';
            const estadoTexto = cells[10]?.innerText || '';
            const fechaEsperada = cells[11]?.innerText || '—';
            
            totalGeneral += parseFloat(total.replace(/\./g, '').replace(',', '.'));
            
            htmlContent += `
                <tr>
                    <td>${escapeHtml(documento)}</td>
                    <td>${escapeHtml(fecha)}</td>
                    <td>${escapeHtml(proveedorNombre)}</td>
                    <td>${escapeHtml(sucursalNombre)}</td>
                    <td>${escapeHtml(usuarioNombre)}</td>
                    <td class="text-right">${escapeHtml(productos)}</td>
                    <td class="text-right">RD$ ${escapeHtml(subtotal)}</td>
                    <td class="text-right">RD$ ${escapeHtml(descuento)}</td>
                    <td class="text-right">RD$ ${escapeHtml(itbis)}</td>
                    <td class="text-right">RD$ ${escapeHtml(total)}</td>
                    <td>${escapeHtml(estadoTexto)}</td>
                    <td>${escapeHtml(fechaEsperada)}</td>
                </tr>
            `;
        }
    });
    
    htmlContent += `
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="9" class="text-right"><strong>Total General:</strong></td>
                        <td class="text-right"><strong>RD$ ${totalGeneral.toFixed(2).replace('.', ',')}</strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
            <div class="footer">
                Reporte generado el ${new Date().toLocaleString()} por ${'<?php echo $_SESSION['usuario'] ?? 'Sistema'; ?>'}
            </div>
        </body>
        </html>
    `;
    
    // Usar html2pdf para exportar
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    
    const opt = {
        margin: [0.5, 0.5, 0.5, 0.5],
        filename: `reporte_compras_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, letterRendering: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    
    html2pdf().set(opt).from(element).save().then(() => {
        document.body.removeChild(element);
        Swal.fire({ icon: 'success', title: 'Exportado', text: 'El reporte se ha generado correctamente', timer: 2000, showConfirmButton: false });
    }).catch(() => {
        document.body.removeChild(element);
        Swal.fire('Error', 'Error al generar el PDF', 'error');
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
</script>