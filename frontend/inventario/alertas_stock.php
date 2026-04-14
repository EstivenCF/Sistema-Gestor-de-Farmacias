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

$base_url = '/Sistema-Gestor-de-Farmacias';

// Obtener sucursales para los filtros
$sucursales = [];
try {
    $stmt = $conexion->query("SELECT id_sucursal, nombre FROM sucursales WHERE estado = true ORDER BY nombre");
    $sucursales = $stmt->fetchAll();
} catch(PDOException $e) {}

// Obtener medicamentos para el filtro
$medicamentos_filtro = [];
try {
    $stmt = $conexion->query("SELECT id_medicamento, nombre_completo, nombre FROM medicamentos ORDER BY nombre");
    $medicamentos_filtro = $stmt->fetchAll();
} catch(PDOException $e) {}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 text-warning">
                <span class="material-symbols-rounded align-middle me-2">notifications_active</span>
                Alertas de Stock
            </h2>
            <p class="text-muted mb-0">Monitoree productos con stock crítico, bajo y agotado</p>
        </div>
        <div>
            <button type="button" class="btn btn-outline-warning shadow-sm" onclick="exportarPDF()">
                <span class="material-symbols-rounded align-middle me-1">picture_as_pdf</span>
                Exportar PDF
            </button>
        </div>
    </div>

    <!-- ESTADÍSTICAS RÁPIDAS -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-danger bg-opacity-10 border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Stock Crítico</h6>
                            <h3 class="mb-0 text-danger" id="statCritico">0</h3>
                            <small class="text-muted">≤ 5 unidades</small>
                        </div>
                        <span class="material-symbols-rounded text-danger" style="font-size:40px;">warning</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning bg-opacity-10 border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Stock Bajo</h6>
                            <h3 class="mb-0 text-warning" id="statBajo">0</h3>
                            <small class="text-muted">6-10 unidades</small>
                        </div>
                        <span class="material-symbols-rounded text-warning" style="font-size:40px;">priority_high</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary bg-opacity-10 border-secondary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Stock Normal</h6>
                            <h3 class="mb-0 text-secondary" id="statNormal">0</h3>
                            <small class="text-muted">> 10 unidades</small>
                        </div>
                        <span class="material-symbols-rounded text-secondary" style="font-size:40px;">check_circle</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-dark bg-opacity-10 border-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Agotados</h6>
                            <h3 class="mb-0 text-dark" id="statAgotado">0</h3>
                            <small class="text-muted">Sin stock</small>
                        </div>
                        <span class="material-symbols-rounded text-dark" style="font-size:40px;">inventory</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTROS (automáticos) -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold text-secondary small">TIPO ALERTA</label>
                    <select class="form-select" id="filtroAlerta">
                        <option value="critico">Crítico (≤5)</option>
                        <option value="bajo">Bajo (6-10)</option>
                        <option value="normal">Normal (>10)</option>
                        <option value="agotado">Agotado (0)</option>
                        <option value="todos" selected>Todas las alertas</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold text-secondary small">MEDICAMENTO</label>
                    <select class="form-select" id="filtroMedicamento">
                        <option value="">Todos los medicamentos</option>
                        <?php foreach ($medicamentos_filtro as $med): ?>
                            <option value="<?php echo $med['id_medicamento']; ?>"><?php echo htmlspecialchars($med['nombre_completo'] ?? $med['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold text-secondary small">SUCURSAL</label>
                    <select class="form-select" id="filtroSucursal">
                        <option value="">Todas las sucursales</option>
                        <?php foreach ($sucursales as $suc): ?>
                            <option value="<?php echo $suc['id_sucursal']; ?>"><?php echo htmlspecialchars($suc['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold text-secondary small">BUSCAR</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <span class="material-symbols-rounded text-muted">search</span>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-0" id="buscarAlerta" placeholder="Lote, medicamento...">
                    </div>
                </div>
                <div class="col-md-12">
                    <button class="btn btn-outline-secondary w-100 fw-bold" onclick="limpiarFiltros()">
                        <span class="material-symbols-rounded align-middle me-1">filter_list_off</span>
                        Quitar filtros
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLA DE ALERTAS DE STOCK -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaAlertas">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Lote</th>
                            <th>Medicamento</th>
                            <th>Concentración</th>
                            <th>Sucursal</th>
                            <th class="text-center">Stock Actual</th>
                            <th>Stock Mínimo</th>
                            <th>Alerta</th>
                            <th>Estado Lote</th>
                            <th>Vencimiento</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaAlertasBody">
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <div class="spinner-border text-warning" role="status"></div>
                                <p class="mt-2">Cargando alertas...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PAGINACIÓN -->
    <div class="d-flex justify-content-center mt-4">
        <nav>
            <ul class="pagination pagination-custom" id="paginacion"></ul>
        </nav>
    </div>
</div>

<!-- MODAL PARA VER DETALLES DEL PRODUCTO -->
<div class="modal fade" id="modalDetalleProducto" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-warning text-white p-4">
                <h5 class="modal-title d-flex align-items-center">
                    <span class="material-symbols-rounded me-2">info</span>
                    Detalles del Producto
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detalleProductoContenido">
                <div class="text-center py-4">
                    <div class="spinner-border text-warning"></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';
const RUTAS_API = {
    listarAlertas: BASE_URL + '/backend/inventario/listar_alertas_stock.php',
    detalleProducto: BASE_URL + '/backend/inventario/detalle_medicamento_alerta.php',
    estadisticas: BASE_URL + '/backend/inventario/estadisticas_alertas.php'
};

// Variables globales
let alertasData = [];
let paginaActual = 1;
let filasPorPagina = 10;
let timeoutBusqueda;

let modalDetalle;

// Función para hacer scroll al modal
function scrollAlModal() {
    setTimeout(function() {
        const modalAbierto = document.querySelector('.modal.show');
        if (modalAbierto) {
            modalAbierto.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center', 
                inline: 'center' 
            });
        }
    }, 200);
}

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    const elDetalle = document.getElementById('modalDetalleProducto');
    if (elDetalle) {
        modalDetalle = new bootstrap.Modal(elDetalle, { backdrop: 'static', keyboard: true });
    }
    
    // ========== FILTROS AUTOMÁTICOS ==========
    document.getElementById('filtroAlerta').addEventListener('change', function() {
        paginaActual = 1;
        cargarAlertas();
        actualizarEstadisticas();
    });
    
    document.getElementById('filtroMedicamento').addEventListener('change', function() {
        paginaActual = 1;
        cargarAlertas();
        actualizarEstadisticas();
    });
    
    document.getElementById('filtroSucursal').addEventListener('change', function() {
        paginaActual = 1;
        cargarAlertas();
        actualizarEstadisticas();
    });
    
    const buscarInput = document.getElementById('buscarAlerta');
    buscarInput.addEventListener('input', function() {
        clearTimeout(timeoutBusqueda);
        timeoutBusqueda = setTimeout(() => {
            paginaActual = 1;
            cargarAlertas();
            actualizarEstadisticas();
        }, 500);
    });
    
    cargarAlertas();
    actualizarEstadisticas();
});

function limpiarFiltros() {
    document.getElementById('filtroAlerta').value = 'todos';
    document.getElementById('filtroMedicamento').value = '';
    document.getElementById('filtroSucursal').value = '';
    document.getElementById('buscarAlerta').value = '';
    
    paginaActual = 1;
    cargarAlertas();
    actualizarEstadisticas();
}

function actualizarEstadisticas() {
    const medicamento = document.getElementById('filtroMedicamento').value;
    const sucursal = document.getElementById('filtroSucursal').value;
    const busqueda = document.getElementById('buscarAlerta').value;
    
    let url = `${RUTAS_API.estadisticas}?`;
    if (medicamento) url += `medicamento=${medicamento}&`;
    if (sucursal) url += `sucursal=${sucursal}&`;
    if (busqueda) url += `busqueda=${encodeURIComponent(busqueda)}&`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statCritico').textContent = data.critico || 0;
                document.getElementById('statBajo').textContent = data.bajo || 0;
                document.getElementById('statNormal').textContent = data.normal || 0;
                document.getElementById('statAgotado').textContent = data.agotado || 0;
            }
        })
        .catch(error => console.error('Error:', error));
}

function cargarAlertas() {
    const tbody = document.getElementById('tablaAlertasBody');
    tbody.innerHTML = `<tr><td colspan="10" class="text-center"><div class="spinner-border text-warning"></div><p>Cargando...</p></td></tr>`;
    
    const tipoAlerta = document.getElementById('filtroAlerta').value;
    const medicamento = document.getElementById('filtroMedicamento').value;
    const sucursal = document.getElementById('filtroSucursal').value;
    const busqueda = document.getElementById('buscarAlerta').value;
    
    let url = `${RUTAS_API.listarAlertas}?pagina=${paginaActual}&limite=${filasPorPagina}&tipo_alerta=${tipoAlerta}`;
    if (medicamento) url += `&medicamento=${medicamento}`;
    if (sucursal) url += `&sucursal=${sucursal}`;
    if (busqueda) url += `&busqueda=${encodeURIComponent(busqueda)}`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alertasData = data.alertas;
                renderizarTabla(alertasData);
                actualizarPaginacion(data.total);
            } else {
                tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger">Error: ${data.message}</td></tr>`;
            }
        })
        .catch(() => {
            tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger">Error de conexión</td></tr>`;
        });
}

function renderizarTabla(alertas) {
    const tbody = document.getElementById('tablaAlertasBody');
    if (!alertas || alertas.length === 0) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted">No hay alertas de stock registradas</td></tr>`;
        return;
    }
    
    let html = '';
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    
    alertas.forEach(a => {
        let alertaClass = '';
        let alertaBadge = '';
        let alertaIcon = '';
        
        if (a.cantidad === 0) {
            alertaClass = 'bg-dark bg-opacity-10';
            alertaBadge = '<span class="badge bg-dark">AGOTADO</span>';
            alertaIcon = 'inventory';
        } else if (a.cantidad <= a.stock_minimo) {
            alertaClass = 'bg-danger bg-opacity-10';
            alertaBadge = '<span class="badge bg-danger">CRÍTICO</span>';
            alertaIcon = 'warning';
        } else if (a.cantidad <= a.stock_minimo * 2) {
            alertaClass = 'bg-warning bg-opacity-10';
            alertaBadge = '<span class="badge bg-warning text-dark">BAJO</span>';
            alertaIcon = 'priority_high';
        } else {
            alertaClass = '';
            alertaBadge = '<span class="badge bg-success">NORMAL</span>';
            alertaIcon = 'check_circle';
        }
        
        let estadoLoteBadge = '';
        if (a.estado_lote === 'ACTIVO') {
            estadoLoteBadge = '<span class="badge bg-success">ACTIVO</span>';
        } else if (a.estado_lote === 'VENCIDO') {
            estadoLoteBadge = '<span class="badge bg-danger">VENCIDO</span>';
        } else if (a.estado_lote === 'RETIRADO') {
            estadoLoteBadge = '<span class="badge bg-secondary">RETIRADO</span>';
        } else if (a.estado_lote === 'MERMA') {
            estadoLoteBadge = '<span class="badge bg-info">MERMA</span>';
        } else if (a.estado_lote === 'DAÑADO') {
            estadoLoteBadge = '<span class="badge bg-dark">DAÑADO</span>';
        }
        
        const fechaVen = new Date(a.fecha_vencimiento);
        let vencimientoClass = '';
        let vencimientoBadge = '';
        if (fechaVen < hoy) {
            vencimientoClass = 'text-danger fw-bold';
            vencimientoBadge = '<span class="badge bg-danger">VENCIDO</span>';
        } else if (fechaVen <= new Date(hoy.getTime() + 30 * 24 * 60 * 60 * 1000)) {
            vencimientoClass = 'text-warning fw-bold';
            vencimientoBadge = '<span class="badge bg-warning text-dark">PRÓXIMO</span>';
        }
        
        html += `<tr class="${alertaClass}">
            <td class="ps-4"><code>${escapeHtml(a.numero_lote)}</code></td>
            <td><strong>${escapeHtml(a.medicamento_nombre)}</strong><br><small class="text-muted">${escapeHtml(a.presentacion || '')}</small></td>
            <td>${a.concentracion || '-'} ${a.unidad_abrev || ''}</td>
            <td>${escapeHtml(a.sucursal_nombre)}</td>
            <td class="text-center">
                <span class="fw-bold ${a.cantidad === 0 ? 'text-danger' : (a.cantidad <= a.stock_minimo ? 'text-danger' : (a.cantidad <= a.stock_minimo * 2 ? 'text-warning' : 'text-success'))}">
                    ${a.cantidad} und
                </span>
            </td>
            <td class="text-center">${a.stock_minimo} und</td>
            <td class="text-center">
                <span class="material-symbols-rounded ${a.cantidad === 0 ? 'text-dark' : (a.cantidad <= a.stock_minimo ? 'text-danger' : (a.cantidad <= a.stock_minimo * 2 ? 'text-warning' : 'text-success'))}" style="font-size:18px; vertical-align:middle;">${alertaIcon}</span>
                ${alertaBadge}
            </td>
            <td class="text-center">${estadoLoteBadge}</td>
            <td class="${vencimientoClass}">
                ${formatDate(a.fecha_vencimiento)}<br>
                ${vencimientoBadge}
            </td>
            <td class="text-center">
                <button class="btn btn-sm btn-light text-info" onclick="verDetalleProducto(${a.id_medicamento})" title="Ver detalles">
                    <span class="material-symbols-rounded">visibility</span>
                </button>
            </td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function verDetalleProducto(idMedicamento) {
    const modalBody = document.getElementById('detalleProductoContenido');
    modalBody.innerHTML = `<div class="text-center py-4"><div class="spinner-border text-warning"></div></div>`;
    
    modalDetalle.show();
    scrollAlModal(); // 👈 Hace scroll al modal
    
    fetch(`${RUTAS_API.detalleProducto}?id=${idMedicamento}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.medicamento) {
                const m = data.medicamento;
                
                modalBody.innerHTML = `
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="detalle-item">
                                <small class="text-muted d-block fw-bold text-uppercase">Medicamento</small>
                                <span class="fw-bold fs-5">${escapeHtml(m.nombre)} ${m.concentracion || ''} ${m.unidad_abrev || ''}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="detalle-item">
                                <small class="text-muted d-block fw-bold text-uppercase">Presentación</small>
                                <span>${escapeHtml(m.presentacion || '-')}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="detalle-item">
                                <small class="text-muted d-block fw-bold text-uppercase">Categoría</small>
                                <span>${escapeHtml(m.categoria || '-')}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="detalle-item">
                                <small class="text-muted d-block fw-bold text-uppercase">Laboratorio</small>
                                <span>${escapeHtml(m.laboratorio || '-')}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="detalle-item">
                                <small class="text-muted d-block fw-bold text-uppercase">Requiere Receta</small>
                                <span>${m.requiere_receta ? '<span class="badge bg-danger">Sí</span>' : '<span class="badge bg-success">No</span>'}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="detalle-item">
                                <small class="text-muted d-block fw-bold text-uppercase">Stock Mínimo</small>
                                <span class="fw-bold">${m.stock_minimo} unidades</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="detalle-item">
                                <small class="text-muted d-block fw-bold text-uppercase">Stock Máximo</small>
                                <span class="fw-bold">${m.stock_maximo || 'No definido'} unidades</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="detalle-item">
                                <small class="text-muted d-block fw-bold text-uppercase">Descripción</small>
                                <span>${escapeHtml(m.descripcion || 'Sin descripción')}</span>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                Swal.fire('Error', data.message || 'No se pudo cargar el detalle', 'error');
                modalDetalle.hide();
            }
        })
        .catch(() => {
            Swal.fire('Error de conexión', '', 'error');
            modalDetalle.hide();
        });
}

function exportarPDF() {
    if (!alertasData || alertasData.length === 0) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    const empresaNombre = '<?php echo addslashes($empresa_nombre ?? "Sistema Gestor de Farmacias"); ?>';
    const tipoAlertaTexto = document.getElementById('filtroAlerta').options[document.getElementById('filtroAlerta').selectedIndex]?.text || 'Seleccionado';
    
    let htmlContent = `
        <html>
        <head><meta charset="UTF-8"><title>Reporte de Alertas de Stock</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; font-size: 11px; padding: 20px; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #ffc107; padding-bottom: 10px; }
            .empresa h2 { color: #ffc107; font-size: 18px; }
            .titulo { background: #ffc107; color: #000; padding: 8px; text-align: center; border-radius: 5px; margin-bottom: 15px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #ffc107; color: #000; padding: 8px; text-align: left; font-size: 10px; }
            td { padding: 6px 8px; border-bottom: 1px solid #ddd; font-size: 9px; }
            .footer { text-align: center; margin-top: 20px; font-size: 9px; color: #666; }
        </style>
        </head>
        <body>
            <div class="header">
                <div class="empresa"><h2>${empresaNombre}</h2></div>
                <div class="titulo"><h1>REPORTE DE ALERTAS DE STOCK</h1></div>
                <p><strong>Tipo de Alerta:</strong> ${tipoAlertaTexto}</p>
            </div>
            <table>
                <thead>
                    <tr><th>Lote</th><th>Medicamento</th><th>Sucursal</th><th>Stock</th><th>Stock Mínimo</th><th>Alerta</th><th>Vencimiento</th></tr>
                </thead>
                <tbody>`;
    
    alertasData.forEach(a => {
        let nivel = '';
        if (a.cantidad === 0) nivel = 'AGOTADO';
        else if (a.cantidad <= a.stock_minimo) nivel = 'CRÍTICO';
        else if (a.cantidad <= a.stock_minimo * 2) nivel = 'BAJO';
        else nivel = 'NORMAL';
        
        htmlContent += `<tr>
            <td>${escapeHtml(a.numero_lote)}</td>
            <td>${escapeHtml(a.medicamento_nombre)}</td>
            <td>${escapeHtml(a.sucursal_nombre)}</td>
            <td>${a.cantidad}</td>
            <td>${a.stock_minimo}</td>
            <td>${nivel}</td>
            <td>${formatDate(a.fecha_vencimiento)}</td>
        </tr>`;
    });
    
    htmlContent += `</tbody></table><div class="footer">Reporte generado el ${new Date().toLocaleString('es-DO')}</div></body></html>`;
    
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    
    const opt = { margin: [0.5, 0.5, 0.5, 0.5], filename: `alertas_stock_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' } };
    
    html2pdf().set(opt).from(element).save().then(() => { document.body.removeChild(element); Swal.fire({ icon: 'success', title: 'Exportado', timer: 1500, showConfirmButton: false }); }).catch(() => { document.body.removeChild(element); Swal.fire('Error', 'Error al generar el PDF', 'error'); });
}

function actualizarPaginacion(total) {
    const totalPaginas = Math.ceil(total / filasPorPagina);
    const paginacion = document.getElementById('paginacion');
    if (totalPaginas <= 1) { paginacion.innerHTML = ''; return; }
    
    let html = '';
    html += `<li class="page-item ${paginaActual === 1 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="cambiarPagina(${paginaActual - 1}); return false;">Anterior</a></li>`;
    
    let inicio = Math.max(1, paginaActual - 2);
    let fin = Math.min(totalPaginas, paginaActual + 2);
    if (inicio > 1) html += `<li class="page-item"><a class="page-link" href="#" onclick="cambiarPagina(1); return false;">1</a></li>`;
    if (inicio > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    
    for (let i = inicio; i <= fin; i++) {
        html += `<li class="page-item ${paginaActual === i ? 'active' : ''}"><a class="page-link" href="#" onclick="cambiarPagina(${i}); return false;">${i}</a></li>`;
    }
    
    if (fin < totalPaginas - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    if (fin < totalPaginas) html += `<li class="page-item"><a class="page-link" href="#" onclick="cambiarPagina(${totalPaginas}); return false;">${totalPaginas}</a></li>`;
    
    html += `<li class="page-item ${paginaActual === totalPaginas ? 'disabled' : ''}"><a class="page-link" href="#" onclick="cambiarPagina(${paginaActual + 1}); return false;">Siguiente</a></li>`;
    paginacion.innerHTML = html;
}

function cambiarPagina(pagina) { paginaActual = pagina; cargarAlertas(); }

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('es-DO', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
}
</script>

<style>
.modal { background-color: rgba(0, 0, 0, 0.5) !important; z-index: 1050; }
.modal-dialog { display: flex; align-items: center; justify-content: center; min-height: calc(100% - 3.5rem); margin: 1.75rem auto; }
.modal-dialog-centered { display: flex; align-items: center; justify-content: center; min-height: calc(100% - 1rem); }
.modal.show .modal-dialog { transform: none; margin: 1.75rem auto; }
.modal-content { max-height: 90vh; overflow: hidden; }
.modal-body { overflow-y: auto; max-height: calc(90vh - 120px); }
@media (max-width: 576px) { .modal-dialog { margin: 0.5rem; min-height: calc(100% - 1rem); } .modal-body { max-height: calc(100vh - 140px); } }
.modal-backdrop { display: none !important; }
.pagination-custom { gap: 8px; }
.pagination-custom .page-item .page-link { border: none; border-radius: 10px; padding: 8px 16px; background: #f8f9fa; transition: all 0.3s; color: #555; }
.pagination-custom .page-item.active .page-link { background: #ffc107 !important; color: #000; box-shadow: 0 4px 12px rgba(255,193,7,0.3); }
.pagination-custom .page-item:not(.active):hover .page-link { background: #e9ecef; color: #ffc107; transform: translateY(-2px); }
.table-hover tbody tr:hover { background: rgba(255,193,7,0.05); }
.table thead th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: #6c757d; padding: 15px 12px; background: #f8f9fa; }
.form-control, .form-select { border: 1.5px solid #dee2e6 !important; border-radius: 10px; }
.form-control:focus, .form-select:focus { border-color: #ffc107 !important; box-shadow: 0 0 0 0.25rem rgba(255,193,7,0.1) !important; }
.detalle-item { background-color: #f8f9fa; border: 1.5px solid #eceef0; border-radius: 12px; padding: 12px; height: 100%; }
.bg-success-subtle { background: rgba(25,135,84,0.1); }
code { font-size: 0.85rem; background-color: #f8f9fa; padding: 2px 6px; border-radius: 4px; }
</style>