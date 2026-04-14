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
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 text-success">
                <span class="material-symbols-rounded align-middle me-2">history</span>
                Movimientos de Inventario
            </h2>
            <p class="text-muted mb-0">Historial de entradas, salidas, ajustes y transferencias de stock</p>
        </div>
        <div>
            <button type="button" class="btn btn-outline-success shadow-sm" onclick="exportarPDF()">
                <span class="material-symbols-rounded align-middle me-1">picture_as_pdf</span>
                Exportar PDF
            </button>
        </div>
    </div>

    <!-- ESTADÍSTICAS RÁPIDAS -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success bg-opacity-10 border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Movimientos</h6>
                            <h3 class="mb-0 text-success" id="statTotalMovimientos">0</h3>
                            <small class="text-muted">Registros</small>
                        </div>
                        <span class="material-symbols-rounded text-success" style="font-size:40px;">receipt_long</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary bg-opacity-10 border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Entradas</h6>
                            <h3 class="mb-0 text-primary" id="statEntradas">0</h3>
                            <small class="text-muted">Unidades ingresadas</small>
                        </div>
                        <span class="material-symbols-rounded text-primary" style="font-size:40px;">arrow_downward</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger bg-opacity-10 border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Salidas</h6>
                            <h3 class="mb-0 text-danger" id="statSalidas">0</h3>
                            <small class="text-muted">Unidades salidas</small>
                        </div>
                        <span class="material-symbols-rounded text-danger" style="font-size:40px;">arrow_upward</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Transferencias</h6>
                            <h3 class="mb-0 text-info" id="statTransferencias">0</h3>
                            <small class="text-muted">Movimientos</small>
                        </div>
                        <span class="material-symbols-rounded text-info" style="font-size:40px;">swap_horiz</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTROS (automáticos) -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold text-secondary small">FECHA DESDE</label>
                    <input type="date" class="form-control" id="filtroFechaDesde">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-secondary small">FECHA HASTA</label>
                    <input type="date" class="form-control" id="filtroFechaHasta">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-secondary small">TIPO MOVIMIENTO</label>
                    <select class="form-select" id="filtroTipo">
                        <option value="">Todos</option>
                        <option value="ENTRADA">Entrada</option>
                        <option value="SALIDA">Salida</option>
                        <option value="AJUSTE">Ajuste</option>
                        <option value="TRANSFERENCIA">Transferencia</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-secondary small">SUCURSAL</label>
                    <select class="form-select" id="filtroSucursal">
                        <option value="">Todas</option>
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
                        <input type="text" class="form-control border-start-0 ps-0" id="buscarMovimiento" placeholder="Lote, medicamento, referencia...">
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

    <!-- TABLA DE MOVIMIENTOS -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaMovimientos">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Lote</th>
                            <th>Medicamento</th>
                            <th class="text-center">Cantidad</th>
                            <th>Sucursal</th>
                            <th>Motivo</th>
                            <th>Referencia</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody id="tablaMovimientosBody">
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <div class="spinner-border text-success" role="status"></div>
                                <p class="mt-2">Cargando movimientos...</p>
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

<!-- MODAL PARA VER DETALLES DEL MOVIMIENTO -->
<div class="modal fade" id="modalDetalleMovimiento" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-success text-white p-4">
                <h5 class="modal-title d-flex align-items-center">
                    <span class="material-symbols-rounded me-2">info</span>
                    Detalle del Movimiento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detalleMovimientoContenido">
                <div class="text-center py-4">
                    <div class="spinner-border text-success"></div>
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
    listarMovimientos: BASE_URL + '/backend/inventario/listar_movimientos.php',
    detalleMovimiento: BASE_URL + '/backend/inventario/detalle_movimiento.php',
    estadisticas: BASE_URL + '/backend/inventario/estadisticas_movimientos.php'
};

// Variables globales
let movimientosData = [];
let paginaActual = 1;
let filasPorPagina = 10;
let timeoutBusqueda;
let timeoutEstadisticas;

let modalDetalle;

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    const elDetalle = document.getElementById('modalDetalleMovimiento');
    if (elDetalle) {
        modalDetalle = new bootstrap.Modal(elDetalle, { backdrop: 'static', keyboard: true });
    }
    
    // Fechas por defecto: últimos 30 días
    const hoy = new Date();
    const hace30Dias = new Date();
    hace30Dias.setDate(hoy.getDate() - 30);
    
    document.getElementById('filtroFechaDesde').value = hace30Dias.toISOString().split('T')[0];
    document.getElementById('filtroFechaHasta').value = hoy.toISOString().split('T')[0];
    
    // ========== FILTROS AUTOMÁTICOS ==========
    // Cambio en fecha desde
    document.getElementById('filtroFechaDesde').addEventListener('change', function() {
        paginaActual = 1;
        cargarMovimientos();
        actualizarEstadisticas();
    });
    
    // Cambio en fecha hasta
    document.getElementById('filtroFechaHasta').addEventListener('change', function() {
        paginaActual = 1;
        cargarMovimientos();
        actualizarEstadisticas();
    });
    
    // Cambio en tipo de movimiento
    document.getElementById('filtroTipo').addEventListener('change', function() {
        paginaActual = 1;
        cargarMovimientos();
        actualizarEstadisticas();
    });
    
    // Cambio en sucursal
    document.getElementById('filtroSucursal').addEventListener('change', function() {
        paginaActual = 1;
        cargarMovimientos();
        actualizarEstadisticas();
    });
    
    // Búsqueda con debounce (como en las otras ventanas)
    const buscarInput = document.getElementById('buscarMovimiento');
    buscarInput.addEventListener('input', function() {
        clearTimeout(timeoutBusqueda);
        clearTimeout(timeoutEstadisticas);
        timeoutBusqueda = setTimeout(() => {
            paginaActual = 1;
            cargarMovimientos();
        }, 500);
        timeoutEstadisticas = setTimeout(() => {
            actualizarEstadisticas();
        }, 600);
    });
    
    cargarMovimientos();
    actualizarEstadisticas();
});

function limpiarFiltros() {
    const hoy = new Date();
    const hace30Dias = new Date();
    hace30Dias.setDate(hoy.getDate() - 30);
    
    document.getElementById('filtroFechaDesde').value = hace30Dias.toISOString().split('T')[0];
    document.getElementById('filtroFechaHasta').value = hoy.toISOString().split('T')[0];
    document.getElementById('filtroTipo').value = '';
    document.getElementById('filtroSucursal').value = '';
    document.getElementById('buscarMovimiento').value = '';
    
    paginaActual = 1;
    cargarMovimientos();
    actualizarEstadisticas();
}

function actualizarEstadisticas() {
    const fecha_desde = document.getElementById('filtroFechaDesde').value;
    const fecha_hasta = document.getElementById('filtroFechaHasta').value;
    const tipo = document.getElementById('filtroTipo').value;
    const sucursal = document.getElementById('filtroSucursal').value;
    const busqueda = document.getElementById('buscarMovimiento').value;
    
    let url = `${RUTAS_API.estadisticas}?`;
    if (fecha_desde) url += `fecha_desde=${fecha_desde}&`;
    if (fecha_hasta) url += `fecha_hasta=${fecha_hasta}&`;
    if (tipo) url += `tipo=${tipo}&`;
    if (sucursal) url += `sucursal=${sucursal}&`;
    if (busqueda) url += `busqueda=${encodeURIComponent(busqueda)}&`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statTotalMovimientos').textContent = data.total_movimientos || 0;
                document.getElementById('statEntradas').textContent = data.total_entradas || 0;
                document.getElementById('statSalidas').textContent = data.total_salidas || 0;
                document.getElementById('statTransferencias').textContent = data.total_transferencias || 0;
            }
        })
        .catch(error => console.error('Error:', error));
}

function cargarMovimientos() {
    const tbody = document.getElementById('tablaMovimientosBody');
    tbody.innerHTML = `<tr><td colspan="10" class="text-center"><div class="spinner-border text-success"></div><p>Cargando...</p></td></tr>`;
    
    const fecha_desde = document.getElementById('filtroFechaDesde').value;
    const fecha_hasta = document.getElementById('filtroFechaHasta').value;
    const tipo = document.getElementById('filtroTipo').value;
    const sucursal = document.getElementById('filtroSucursal').value;
    const busqueda = document.getElementById('buscarMovimiento').value;
    
    let url = `${RUTAS_API.listarMovimientos}?pagina=${paginaActual}&limite=${filasPorPagina}`;
    if (fecha_desde) url += `&fecha_desde=${fecha_desde}`;
    if (fecha_hasta) url += `&fecha_hasta=${fecha_hasta}`;
    if (tipo) url += `&tipo=${tipo}`;
    if (sucursal) url += `&sucursal=${sucursal}`;
    if (busqueda) url += `&busqueda=${encodeURIComponent(busqueda)}`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                movimientosData = data.movimientos;
                renderizarTabla(movimientosData);
                actualizarPaginacion(data.total);
            } else {
                tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger">Error: ${data.message}</td></tr>`;
            }
        })
        .catch(() => {
            tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger">Error de conexión</td></tr>`;
        });
}

function renderizarTabla(movimientos) {
    const tbody = document.getElementById('tablaMovimientosBody');
    if (!movimientos || movimientos.length === 0) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted">No hay movimientos registrados</td></tr>`;
        return;
    }
    
    let html = '';
    
    movimientos.forEach(m => {
        let tipoBadge = '';
        let tipoIcon = '';
        
        switch(m.tipo) {
            case 'ENTRADA':
                tipoBadge = '<span class="badge bg-success">ENTRADA</span>';
                tipoIcon = 'arrow_downward';
                break;
            case 'SALIDA':
                tipoBadge = '<span class="badge bg-danger">SALIDA</span>';
                tipoIcon = 'arrow_upward';
                break;
            case 'AJUSTE':
                tipoBadge = '<span class="badge bg-warning text-dark">AJUSTE</span>';
                tipoIcon = 'adjust';
                break;
            case 'TRANSFERENCIA':
                tipoBadge = '<span class="badge bg-info">TRANSFERENCIA</span>';
                tipoIcon = 'swap_horiz';
                break;
            default:
                tipoBadge = '<span class="badge bg-secondary">' + m.tipo + '</span>';
                tipoIcon = 'help';
        }
        
        let cantidadClass = m.tipo === 'ENTRADA' ? 'text-success' : (m.tipo === 'SALIDA' ? 'text-danger' : 'text-warning');
        let cantidadSigno = m.tipo === 'ENTRADA' ? '+' : (m.tipo === 'SALIDA' ? '-' : '');
        
        html += `<tr>
            <td class="ps-4"><span class="text-success fw-bold">#${m.id_movimiento}</span></td>
            <td><small>${formatDate(m.fecha)}</small><br><small class="text-muted">${formatTime(m.fecha)}</small></td>
            <td>
                <span class="material-symbols-rounded ${m.tipo === 'ENTRADA' ? 'text-success' : (m.tipo === 'SALIDA' ? 'text-danger' : 'text-info')}" style="font-size:18px; vertical-align:middle;">${tipoIcon}</span>
                ${tipoBadge}
            </td>
            <td><code class="bg-light p-1 rounded">${escapeHtml(m.numero_lote || '-')}</code></td>
            <td>
                <strong>${escapeHtml(m.medicamento_nombre || '-')}</strong><br>
                <small class="text-muted">${escapeHtml(m.presentacion || '')} ${escapeHtml(m.concentracion || '')} ${escapeHtml(m.unidad_abrev || '')}</small>
            </td>
            <td class="text-center ${cantidadClass} fw-bold">
                ${cantidadSigno} ${m.cantidad} und
            </td>
            <td>${escapeHtml(m.sucursal_nombre || '-')}</td>
            <td><small>${escapeHtml(m.motivo || '-')}</small></td>
            <td><small>${escapeHtml(m.referencia || '-')}</small></td>
            <td><small>${escapeHtml(m.usuario_nombre || 'Sistema')}</small></td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function verDetalle(id) {
    const modalBody = document.getElementById('detalleMovimientoContenido');
    modalBody.innerHTML = `<div class="text-center py-4"><div class="spinner-border text-success"></div></div>`;
    
    fetch(`${RUTAS_API.detalleMovimiento}?id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.movimiento) {
                const m = data.movimiento;
                
                let tipoBadge = '';
                switch(m.tipo) {
                    case 'ENTRADA': tipoBadge = '<span class="badge bg-success">ENTRADA</span>'; break;
                    case 'SALIDA': tipoBadge = '<span class="badge bg-danger">SALIDA</span>'; break;
                    case 'AJUSTE': tipoBadge = '<span class="badge bg-warning text-dark">AJUSTE</span>'; break;
                    case 'TRANSFERENCIA': tipoBadge = '<span class="badge bg-info">TRANSFERENCIA</span>'; break;
                    default: tipoBadge = '<span class="badge bg-secondary">' + m.tipo + '</span>';
                }
                
                modalBody.innerHTML = `
                    <div class="text-center mb-4">
                        <div class="bg-success-subtle rounded-circle d-inline-flex p-3 mb-2">
                            <span class="material-symbols-rounded text-success" style="font-size: 2rem;">receipt_long</span>
                        </div>
                        <h4 class="fw-bold">Movimiento #${m.id_movimiento}</h4>
                        ${tipoBadge}
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="detalle-item">
                                <small class="text-muted d-block fw-bold text-uppercase">Fecha</small>
                                <span class="fw-bold">${formatDate(m.fecha)} ${formatTime(m.fecha)}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="detalle-item">
                                <small class="text-muted d-block fw-bold text-uppercase">Cantidad</small>
                                <span class="fw-bold ${m.tipo === 'ENTRADA' ? 'text-success' : (m.tipo === 'SALIDA' ? 'text-danger' : 'text-warning')}">${m.cantidad} unidades</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="detalle-item">
                                <small class="text-muted d-block fw-bold text-uppercase">Lote</small>
                                <span class="fw-bold">${escapeHtml(m.numero_lote || '-')}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="detalle-item">
                                <small class="text-muted d-block fw-bold text-uppercase">Medicamento</small>
                                <span class="fw-bold">${escapeHtml(m.medicamento_nombre || '-')}</span>
                                <br><small>${escapeHtml(m.presentacion || '')} ${escapeHtml(m.concentracion || '')}</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="detalle-item">
                                <small class="text-muted d-block fw-bold text-uppercase">Sucursal</small>
                                <span class="fw-bold">${escapeHtml(m.sucursal_nombre || '-')}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="detalle-item">
                                <small class="text-muted d-block fw-bold text-uppercase">Usuario</small>
                                <span class="fw-bold">${escapeHtml(m.usuario_nombre || 'Sistema')}</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="detalle-item">
                                <small class="text-muted d-block fw-bold text-uppercase">Motivo</small>
                                <span>${escapeHtml(m.motivo || '-')}</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="detalle-item">
                                <small class="text-muted d-block fw-bold text-uppercase">Referencia</small>
                                <span>${escapeHtml(m.referencia || '-')}</span>
                            </div>
                        </div>
                        ${m.observaciones ? `
                        <div class="col-12">
                            <div class="detalle-item">
                                <small class="text-muted d-block fw-bold text-uppercase">Observaciones</small>
                                <span>${escapeHtml(m.observaciones)}</span>
                            </div>
                        </div>` : ''}
                    </div>
                `;
                modalDetalle.show();
            } else {
                Swal.fire('Error', data.message || 'No se pudo cargar el detalle', 'error');
            }
        })
        .catch(() => {
            Swal.fire('Error de conexión', '', 'error');
        });
}

function exportarPDF() {
    if (!movimientosData || movimientosData.length === 0) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    const empresaNombre = '<?php echo addslashes($empresa_nombre ?? "Sistema Gestor de Farmacias"); ?>';
    
    let htmlContent = `
        <html>
        <head><meta charset="UTF-8"><title>Reporte de Movimientos</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; font-size: 11px; padding: 20px; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #198754; padding-bottom: 10px; }
            .empresa h2 { color: #198754; font-size: 18px; }
            .titulo { background: #198754; color: white; padding: 8px; text-align: center; border-radius: 5px; margin-bottom: 15px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #198754; color: white; padding: 8px; text-align: left; font-size: 10px; }
            td { padding: 6px 8px; border-bottom: 1px solid #ddd; font-size: 9px; }
            .footer { text-align: center; margin-top: 20px; font-size: 9px; color: #666; }
        </style>
        </head>
        <body>
            <div class="header">
                <div class="empresa"><h2>${empresaNombre}</h2></div>
                <div class="titulo"><h1>REPORTE DE MOVIMIENTOS DE INVENTARIO</h1></div>
            </div>
            <table>
                <thead>
                    <tr><th>ID</th><th>Fecha</th><th>Tipo</th><th>Lote</th><th>Medicamento</th><th>Cantidad</th><th>Sucursal</th><th>Motivo</th></tr>
                </thead>
                <tbody>`;
    
    movimientosData.forEach(m => {
        htmlContent += `<tr>
            <td>${m.id_movimiento}</td>
            <td>${formatDate(m.fecha)}</td>
            <td>${m.tipo}</td>
            <td>${escapeHtml(m.numero_lote || '-')}</td>
            <td>${escapeHtml(m.medicamento_nombre || '-')}</td>
            <td>${m.cantidad}</td>
            <td>${escapeHtml(m.sucursal_nombre || '-')}</td>
            <td>${escapeHtml(m.motivo || '-')}</td>
        </tr>`;
    });
    
    htmlContent += `
                </tbody>
            </table>
            <div class="footer">Reporte generado el ${new Date().toLocaleString('es-DO')}</div>
        </body>
        </html>`;
    
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    
    const opt = { margin: [0.5, 0.5, 0.5, 0.5], filename: `movimientos_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' } };
    
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

function cambiarPagina(pagina) { paginaActual = pagina; cargarMovimientos(); }

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('es-DO', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

function formatTime(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleTimeString('es-DO', { hour: '2-digit', minute: '2-digit' });
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
.pagination-custom .page-item.active .page-link { background: #198754 !important; color: white; box-shadow: 0 4px 12px rgba(25,135,84,0.3); }
.pagination-custom .page-item:not(.active):hover .page-link { background: #e9ecef; color: #198754; transform: translateY(-2px); }
.table-hover tbody tr:hover { background: rgba(25,135,84,0.05); }
.table thead th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: #6c757d; padding: 15px 12px; background: #f8f9fa; }
.form-control, .form-select { border: 1.5px solid #dee2e6 !important; border-radius: 10px; }
.form-control:focus, .form-select:focus { border-color: #198754 !important; box-shadow: 0 0 0 0.25rem rgba(25,135,84,0.1) !important; }
.detalle-item { background-color: #f8f9fa; border: 1.5px solid #eceef0; border-radius: 12px; padding: 12px; height: 100%; }
.bg-success-subtle { background: rgba(25,135,84,0.1); }
code { font-size: 0.85rem; background-color: #f8f9fa; padding: 2px 6px; border-radius: 4px; }
</style>