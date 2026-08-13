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
            <h2 class="mb-0 text-danger">
                <span class="material-symbols-rounded align-middle me-2">event_busy</span>
                Control de Vencimientos
            </h2>
            <p class="text-muted mb-0">Gestione lotes próximos a vencer y lotes vencidos</p>
        </div>
        <div>
            <button type="button" class="btn btn-warning shadow-sm me-2" onclick="marcarVencidos()">
                <span class="material-symbols-rounded align-middle me-1">update</span>
                Marcar Vencidos
            </button>
            <button type="button" class="btn btn-outline-danger shadow-sm" onclick="exportarPDF()">
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
                            <h6 class="text-muted mb-1">Vencidos</h6>
                            <h3 class="mb-0 text-danger" id="statVencidos">0</h3>
                            <small class="text-muted">Lotes vencidos</small>
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
                            <h6 class="text-muted mb-1">Próximos 7 días</h6>
                            <h3 class="mb-0 text-warning" id="stat7Dias">0</h3>
                            <small class="text-muted">Vencen en 7 días</small>
                        </div>
                        <span class="material-symbols-rounded text-warning" style="font-size:40px;">today</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Próximos 15 días</h6>
                            <h3 class="mb-0 text-info" id="stat15Dias">0</h3>
                            <small class="text-muted">Vencen en 15 días</small>
                        </div>
                        <span class="material-symbols-rounded text-info" style="font-size:40px;">schedule</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success bg-opacity-10 border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Próximos 30 días</h6>
                            <h3 class="mb-0 text-success" id="stat30Dias">0</h3>
                            <small class="text-muted">Vencen en 30 días</small>
                        </div>
                        <span class="material-symbols-rounded text-success" style="font-size:40px;">calendar_month</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- NUEVO (Tarea 5): CLASIFICACIÓN DE RIESGO ESTRATÉGICO -->
    <div class="row mb-4">
        <div class="col-12 mb-2">
            <h6 class="text-muted text-uppercase small fw-bold">
                <span class="material-symbols-rounded align-middle me-1" style="font-size:18px;">insights</span>
                Clasificación de riesgo del proceso estratégico
            </h6>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm" style="border-left: 4px solid #dc3545 !important;">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Riesgo Crítico</small>
                    <h4 class="mb-0 text-danger" id="statRiesgoCritico">0</h4>
                    <small class="text-muted">lotes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm" style="border-left: 4px solid #fd7e14 !important;">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Riesgo Moderado</small>
                    <h4 class="mb-0" style="color:#fd7e14;" id="statRiesgoModerado">0</h4>
                    <small class="text-muted">lotes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm" style="border-left: 4px solid #198754 !important;">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Riesgo Bajo</small>
                    <h4 class="mb-0 text-success" id="statRiesgoBajo">0</h4>
                    <small class="text-muted">lotes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm" style="border-left: 4px solid #0d6efd !important;">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Valor Total en Riesgo</small>
                    <h4 class="mb-0 text-primary" id="statValorRiesgo">RD$ 0</h4>
                    <small class="text-muted">lotes activos con stock</small>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTROS (automáticos) -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold text-secondary small">PERIODO</label>
                    <select class="form-select" id="filtroPeriodo">
                        <option value="vencidos">Vencidos</option>
                        <option value="7dias">Próximos 7 días</option>
                        <option value="15dias">Próximos 15 días</option>
                        <option value="30dias" selected>Próximos 30 días</option>
                        <option value="todos">Todos (activos)</option>
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
                        <input type="text" class="form-control border-start-0 ps-0" id="buscarVencimiento" placeholder="Lote, medicamento...">
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

    <!-- TABLA DE LOTES POR VENCER -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaVencimientos">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Lote</th>
                            <th>Medicamento</th>
                            <th>Concentración</th>
                            <th>Sucursal</th>
                            <th class="text-center">Stock</th>
                            <th>Fecha Vencimiento</th>
                            <th>Días Restantes</th>
                            <th>Estado</th>
                            <th class="text-center">Riesgo</th>
                            <th class="text-end">Valor en Riesgo</th>
                            <th class="text-center">Rotación (IRV)</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaVencimientosBody">
                        <tr>
                            <td colspan="12" class="text-center text-muted py-4">
                                <div class="spinner-border text-danger" role="status"></div>
                                <p class="mt-2">Cargando lotes...</p>
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

<!-- MODAL PARA VER DETALLES DEL LOTE -->
<div class="modal fade" id="modalDetalleLote" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-danger text-white p-4">
                <h5 class="modal-title d-flex align-items-center">
                    <span class="material-symbols-rounded me-2">inventory</span>
                    Detalles del Lote
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detalleLoteContenido">
                <div class="text-center py-4">
                    <div class="spinner-border text-danger"></div>
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
    listarVencimientos: BASE_URL + '/backend/inventario/listar_vencimientos.php',
    detalleLote: BASE_URL + '/backend/inventario/detalle_lote.php',
    estadisticas: BASE_URL + '/backend/inventario/estadisticas_vencimientos.php',
    marcarVencidos: BASE_URL + '/backend/inventario/marcar_lotes_vencidos.php',
    evaluarRiesgo: BASE_URL + '/backend/inventario/evaluar_riesgo_vencimiento.php' // NUEVO - Tarea 5
};

// Variables globales
let vencimientosData = [];
let riesgoMap = {}; // NUEVO - Tarea 5: mapa "id_lote-id_sucursal" -> datos de riesgo/IRV
let paginaActual = 1;
let filasPorPagina = 10;
let timeoutBusqueda;

let modalDetalle;

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    const elDetalle = document.getElementById('modalDetalleLote');
    if (elDetalle) {
        modalDetalle = new bootstrap.Modal(elDetalle, { backdrop: 'static', keyboard: true });
    }
    
    // ========== FILTROS AUTOMÁTICOS ==========
    document.getElementById('filtroPeriodo').addEventListener('change', function() {
        paginaActual = 1;
        cargarVencimientos();
        actualizarEstadisticas();
        actualizarRiesgoKPIs();
    });
    
    document.getElementById('filtroMedicamento').addEventListener('change', function() {
        paginaActual = 1;
        cargarVencimientos();
        actualizarEstadisticas();
        actualizarRiesgoKPIs();
    });
    
    document.getElementById('filtroSucursal').addEventListener('change', function() {
        paginaActual = 1;
        cargarVencimientos();
        actualizarEstadisticas();
        actualizarRiesgoKPIs();
    });
    
    const buscarInput = document.getElementById('buscarVencimiento');
    buscarInput.addEventListener('input', function() {
        clearTimeout(timeoutBusqueda);
        timeoutBusqueda = setTimeout(() => {
            paginaActual = 1;
            cargarVencimientos();
            actualizarEstadisticas();
            actualizarRiesgoKPIs();
        }, 500);
    });
    
    cargarVencimientos();
    actualizarEstadisticas();
    actualizarRiesgoKPIs();
});

function limpiarFiltros() {
    document.getElementById('filtroPeriodo').value = '30dias';
    document.getElementById('filtroMedicamento').value = '';
    document.getElementById('filtroSucursal').value = '';
    document.getElementById('buscarVencimiento').value = '';
    
    paginaActual = 1;
    cargarVencimientos();
    actualizarEstadisticas();
    actualizarRiesgoKPIs();
}

function actualizarEstadisticas() {
    const periodo = document.getElementById('filtroPeriodo').value;
    const medicamento = document.getElementById('filtroMedicamento').value;
    const sucursal = document.getElementById('filtroSucursal').value;
    const busqueda = document.getElementById('buscarVencimiento').value;
    
    let url = `${RUTAS_API.estadisticas}?`;
    if (medicamento) url += `medicamento=${medicamento}&`;
    if (sucursal) url += `sucursal=${sucursal}&`;
    if (busqueda) url += `busqueda=${encodeURIComponent(busqueda)}&`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statVencidos').textContent = data.vencidos || 0;
                document.getElementById('stat7Dias').textContent = data.prox_7_dias || 0;
                document.getElementById('stat15Dias').textContent = data.prox_15_dias || 0;
                document.getElementById('stat30Dias').textContent = data.prox_30_dias || 0;
            }
        })
        .catch(error => console.error('Error:', error));
}

function cargarVencimientos() {
    const tbody = document.getElementById('tablaVencimientosBody');
    tbody.innerHTML = `<tr><td colspan="12" class="text-center"><div class="spinner-border text-danger"></div><p>Cargando...</p></td></tr>`;
    
    const periodo = document.getElementById('filtroPeriodo').value;
    const medicamento = document.getElementById('filtroMedicamento').value;
    const sucursal = document.getElementById('filtroSucursal').value;
    const busqueda = document.getElementById('buscarVencimiento').value;
    
    let url = `${RUTAS_API.listarVencimientos}?pagina=${paginaActual}&limite=${filasPorPagina}&periodo=${periodo}`;
    if (medicamento) url += `&medicamento=${medicamento}`;
    if (sucursal) url += `&sucursal=${sucursal}`;
    if (busqueda) url += `&busqueda=${encodeURIComponent(busqueda)}`;

    // NUEVO (Tarea 5): se pide en paralelo el detalle de riesgo/IRV. Es un
    // endpoint aparte (no reemplaza a listar_vencimientos.php) para no tocar
    // la paginación ni los filtros de período que ya funcionaban.
    let urlRiesgo = `${RUTAS_API.evaluarRiesgo}?`;
    if (sucursal) urlRiesgo += `sucursal=${sucursal}&`;
    if (busqueda) urlRiesgo += `busqueda=${encodeURIComponent(busqueda)}&`;

    Promise.all([
        fetch(url).then(r => r.json()),
        fetch(urlRiesgo).then(r => r.json())
    ])
        .then(([data, dataRiesgo]) => {
            if (data.success) {
                vencimientosData = data.lotes;

                // Construir el mapa de riesgo por "id_lote-id_sucursal"
                riesgoMap = {};
                if (dataRiesgo.success) {
                    dataRiesgo.lotes.forEach(r => {
                        riesgoMap[`${r.id_lote}-${r.id_sucursal}`] = r;
                    });
                }

                renderizarTabla(vencimientosData);
                actualizarPaginacion(data.total);
            } else {
                tbody.innerHTML = `<tr><td colspan="12" class="text-center text-danger">Error: ${data.message}</td></tr>`;
            }
        })
        .catch(() => {
            tbody.innerHTML = `<tr><td colspan="12" class="text-center text-danger">Error de conexión</td></tr>`;
        });
}

// NUEVO (Tarea 5): tarjetas KPI de clasificación de riesgo estratégico
function actualizarRiesgoKPIs() {
    const sucursal = document.getElementById('filtroSucursal').value;
    const busqueda = document.getElementById('buscarVencimiento').value;

    let url = `${RUTAS_API.evaluarRiesgo}?`;
    if (sucursal) url += `sucursal=${sucursal}&`;
    if (busqueda) url += `busqueda=${encodeURIComponent(busqueda)}&`;

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statRiesgoCritico').textContent = data.totales_por_riesgo.CRITICO || 0;
                document.getElementById('statRiesgoModerado').textContent = data.totales_por_riesgo.MODERADO || 0;
                document.getElementById('statRiesgoBajo').textContent = data.totales_por_riesgo.BAJO || 0;
                document.getElementById('statValorRiesgo').textContent = 'RD$ ' + Number(data.valor_total_en_riesgo || 0).toLocaleString('es-DO', { minimumFractionDigits: 2 });
            }
        })
        .catch(error => console.error('Error KPIs riesgo:', error));
}

function renderizarTabla(lotes) {
    const tbody = document.getElementById('tablaVencimientosBody');
    if (!lotes || lotes.length === 0) {
        tbody.innerHTML = `<tr><td colspan="12" class="text-center text-muted">No hay lotes en este período</td></tr>`;
        return;
    }
    
    let html = '';
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    
    lotes.forEach(l => {
        const fechaVen = new Date(l.fecha_vencimiento);
        const diasRestantes = Math.ceil((fechaVen - hoy) / (1000 * 60 * 60 * 24));
        
        let diasClass = '';
        let diasBadge = '';
        let filaClass = '';
        
        if (diasRestantes < 0) {
            diasClass = 'text-danger fw-bold';
            diasBadge = '<span class="badge bg-danger">VENCIDO</span>';
            filaClass = 'table-danger';
        } else if (diasRestantes <= 7) {
            diasClass = 'text-danger fw-bold';
            diasBadge = '<span class="badge bg-danger">URGENTE</span>';
            filaClass = 'table-warning';
        } else if (diasRestantes <= 15) {
            diasClass = 'text-warning fw-bold';
            diasBadge = '<span class="badge bg-warning text-dark">PRÓXIMO</span>';
        } else if (diasRestantes <= 30) {
            diasClass = 'text-info fw-bold';
            diasBadge = '<span class="badge bg-info">ATENCIÓN</span>';
        } else {
            diasBadge = '<span class="badge bg-secondary">NORMAL</span>';
        }
        
        let stockBadge = '';
        if (l.cantidad === 0) {
            stockBadge = '<span class="badge bg-danger">AGOTADO</span>';
        } else if (l.cantidad <= 5) {
            stockBadge = '<span class="badge bg-danger">CRÍTICO</span>';
        } else if (l.cantidad <= 10) {
            stockBadge = '<span class="badge bg-warning text-dark">BAJO</span>';
        } else {
            stockBadge = '<span class="badge bg-success">NORMAL</span>';
        }
        
        let estadoLoteBadge = '';
        if (l.estado_lote === 'ACTIVO') {
            estadoLoteBadge = '<span class="badge bg-success">ACTIVO</span>';
        } else if (l.estado_lote === 'VENCIDO') {
            estadoLoteBadge = '<span class="badge bg-danger">VENCIDO</span>';
        } else if (l.estado_lote === 'RETIRADO') {
            estadoLoteBadge = '<span class="badge bg-secondary">RETIRADO</span>';
        } else if (l.estado_lote === 'MERMA') {
            estadoLoteBadge = '<span class="badge bg-info">MERMA</span>';
        } else if (l.estado_lote === 'DAÑADO') {
            estadoLoteBadge = '<span class="badge bg-dark">DAÑADO</span>';
        }
        
        // NUEVO (Tarea 5): datos de riesgo estratégico para este lote+sucursal
        const riesgo = riesgoMap[`${l.id_lote}-${l.id_sucursal}`];
        let riesgoCelda = '<span class="badge bg-secondary">Sin evaluar</span>';
        let valorRiesgoCelda = '-';
        let irvCelda = '<span class="text-muted small">-</span>';

        if (riesgo) {
            const coloresRiesgo = { CRITICO: 'bg-danger', MODERADO: 'bg-warning text-dark', BAJO: 'bg-success' };
            riesgoCelda = `<span class="badge ${coloresRiesgo[riesgo.nivel_riesgo] || 'bg-secondary'}">${riesgo.nivel_riesgo}</span>`;
            valorRiesgoCelda = `<strong>RD$ ${Number(riesgo.valor_en_riesgo).toLocaleString('es-DO', {minimumFractionDigits: 2})}</strong>`;

            if (riesgo.irv === null) {
                irvCelda = '<span class="text-muted small">Sin datos</span>';
            } else {
                const claseIrv = riesgo.estado_venta === 'BAJA_ROTACION' ? 'text-danger fw-bold' : 'text-success fw-bold';
                const etiquetaIrv = riesgo.estado_venta === 'BAJA_ROTACION' ? 'Baja rotación' : 'Rotación normal';
                irvCelda = `<span class="${claseIrv}">${riesgo.irv}%</span><br><small class="text-muted">${etiquetaIrv}</small>`;
            }
        }

        html += `<tr class="${filaClass}">
            <td class="ps-4"><code>${escapeHtml(l.numero_lote)}</code></td>
            <td><strong>${escapeHtml(l.medicamento_nombre)}</strong><br><small class="text-muted">${escapeHtml(l.presentacion || '')}</small></td>
            <td>${l.concentracion || '-'} ${l.unidad_abrev || ''}</td>
            <td>${escapeHtml(l.sucursal_nombre)}</td>
            <td class="text-center">
                <strong>${l.cantidad} und</strong><br>
                ${stockBadge}
            </td>
            <td class="${diasRestantes < 0 ? 'text-danger fw-bold' : ''}">
                ${formatDate(l.fecha_vencimiento)}
            </td>
            <td class="${diasClass} text-center">
                <strong>${diasRestantes < 0 ? 'VENCIDO' : diasRestantes + ' días'}</strong><br>
                ${diasBadge}
            </td>
            <td class="text-center">${estadoLoteBadge}</td>
            <td class="text-center">${riesgoCelda}</td>
            <td class="text-end">${valorRiesgoCelda}</td>
            <td class="text-center">${irvCelda}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-light text-info me-1" onclick="verDetalleLote(${l.id_lote})" title="Ver detalles básicos">
                    <span class="material-symbols-rounded">visibility</span>
                </button>
                <a href="menuprincipal.php?mod=detalle_riesgo_lote&id_lote=${l.id_lote}&id_sucursal=${l.id_sucursal}" class="btn btn-sm btn-light text-danger" title="Ver riesgo y valor económico">
                    <span class="material-symbols-rounded">troubleshoot</span>
                </a>
            </td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function verDetalleLote(id) {
    const modalBody = document.getElementById('detalleLoteContenido');
    modalBody.innerHTML = `<div class="text-center py-4"><div class="spinner-border text-danger"></div></div>`;
    
    fetch(`${RUTAS_API.detalleLote}?id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.lote) {
                const l = data.lote;
                const fechaVen = new Date(l.fecha_vencimiento);
                const hoy = new Date();
                hoy.setHours(0, 0, 0, 0);
                const diasRestantes = Math.ceil((fechaVen - hoy) / (1000 * 60 * 60 * 24));
                
                let alertaHtml = '';
                if (diasRestantes < 0) {
                    alertaHtml = `<div class="alert alert-danger text-center">⚠️ LOTE VENCIDO - Vence: ${formatDate(l.fecha_vencimiento)}</div>`;
                } else if (diasRestantes <= 7) {
                    alertaHtml = `<div class="alert alert-warning text-center">⚡ LOTE POR VENCER EN ${diasRestantes} DÍAS - Vence: ${formatDate(l.fecha_vencimiento)}</div>`;
                } else if (diasRestantes <= 30) {
                    alertaHtml = `<div class="alert alert-info text-center">📅 LOTE POR VENCER EN ${diasRestantes} DÍAS - Vence: ${formatDate(l.fecha_vencimiento)}</div>`;
                }
                
                modalBody.innerHTML = `
                    ${alertaHtml}
                    <div class="row g-3">
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Número Lote</small><span class="fw-bold">${escapeHtml(l.numero_lote)}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">ID Lote</small><span class="fw-bold">#${l.id_lote}</span></div></div>
                        <div class="col-12"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Medicamento</small><span class="fw-bold">${escapeHtml(l.medicamento_nombre)} ${l.concentracion || ''} ${l.unidad || ''}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Presentación</small><span>${escapeHtml(l.presentacion || '-')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Categoría</small><span>${escapeHtml(l.categoria || '-')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Laboratorio</small><span>${escapeHtml(l.laboratorio || '-')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Ubicación</small><span>${escapeHtml(l.ubicacion || '-')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Stock Total</small><span class="fw-bold text-success">${l.stock_total || 0} unidades</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Estado Lote</small><span class="badge ${l.estado === 'ACTIVO' ? 'bg-success' : 'bg-danger'}">${l.estado}</span></div></div>
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

function marcarVencidos() {
    Swal.fire({
        title: '¿Marcar lotes vencidos?',
        text: 'Esta acción marcará automáticamente como VENCIDOS todos los lotes cuya fecha de vencimiento haya pasado',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Sí, marcar vencidos',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            
            fetch(RUTAS_API.marcarVencidos, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire('¡Completado!', data.message, 'success');
                    cargarVencimientos();
                    actualizarEstadisticas();
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(() => {
                Swal.close();
                Swal.fire('Error', 'Error al conectar con el servidor', 'error');
            });
        }
    });
}

function exportarPDF() {
    if (!vencimientosData || vencimientosData.length === 0) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    const empresaNombre = '<?php echo addslashes($empresa_nombre ?? "Sistema Gestor de Farmacias"); ?>';
    const periodoTexto = document.getElementById('filtroPeriodo').options[document.getElementById('filtroPeriodo').selectedIndex]?.text || 'Seleccionado';
    
    let htmlContent = `
        <html>
        <head><meta charset="UTF-8"><title>Reporte de Vencimientos</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; font-size: 11px; padding: 20px; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #dc3545; padding-bottom: 10px; }
            .empresa h2 { color: #dc3545; font-size: 18px; }
            .titulo { background: #dc3545; color: white; padding: 8px; text-align: center; border-radius: 5px; margin-bottom: 15px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #dc3545; color: white; padding: 8px; text-align: left; font-size: 10px; }
            td { padding: 6px 8px; border-bottom: 1px solid #ddd; font-size: 9px; }
            .footer { text-align: center; margin-top: 20px; font-size: 9px; color: #666; }
        </style>
        </head>
        <body>
            <div class="header">
                <div class="empresa"><h2>${empresaNombre}</h2></div>
                <div class="titulo"><h1>REPORTE DE LOTES POR VENCER</h1></div>
                <p><strong>Período:</strong> ${periodoTexto}</p>
            </div>
            <table>
                <thead><tr><th>Lote</th><th>Medicamento</th><th>Sucursal</th><th>Stock</th><th>Fecha Vencimiento</th><th>Días</th><th>Estado</th></tr></thead>
                <tbody>`;
    
    vencimientosData.forEach(l => {
        const fechaVen = new Date(l.fecha_vencimiento);
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        const diasRestantes = Math.ceil((fechaVen - hoy) / (1000 * 60 * 60 * 24));
        
        htmlContent += `<tr>
            <td>${escapeHtml(l.numero_lote)}</td>
            <td>${escapeHtml(l.medicamento_nombre)}</td>
            <td>${escapeHtml(l.sucursal_nombre)}</td>
            <td>${l.cantidad}</td>
            <td>${formatDate(l.fecha_vencimiento)}</td>
            <td>${diasRestantes < 0 ? 'VENCIDO' : diasRestantes}</td>
            <td>${l.estado_lote}</td>
        </tr>`;
    });
    
    htmlContent += `</tbody></table><div class="footer">Reporte generado el ${new Date().toLocaleString('es-DO')}</div></body></html>`;
    
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    
    const opt = { margin: [0.5, 0.5, 0.5, 0.5], filename: `vencimientos_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' } };
    
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

function cambiarPagina(pagina) { paginaActual = pagina; cargarVencimientos(); }

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
.pagination-custom .page-item.active .page-link { background: #dc3545 !important; color: white; box-shadow: 0 4px 12px rgba(220,53,69,0.3); }
.pagination-custom .page-item:not(.active):hover .page-link { background: #e9ecef; color: #dc3545; transform: translateY(-2px); }
.table-hover tbody tr:hover { background: rgba(220,53,69,0.05); }
.table thead th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: #6c757d; padding: 15px 12px; background: #f8f9fa; }
.form-control, .form-select { border: 1.5px solid #dee2e6 !important; border-radius: 10px; }
.form-control:focus, .form-select:focus { border-color: #dc3545 !important; box-shadow: 0 0 0 0.25rem rgba(220,53,69,0.1) !important; }
.detalle-item { background-color: #f8f9fa; border: 1.5px solid #eceef0; border-radius: 12px; padding: 12px; height: 100%; }
.bg-success-subtle { background: rgba(25,135,84,0.1); }
code { font-size: 0.85rem; background-color: #f8f9fa; padding: 2px 6px; border-radius: 4px; }
</style>