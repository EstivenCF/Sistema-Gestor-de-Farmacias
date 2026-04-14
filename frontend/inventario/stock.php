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
            <h2 class="mb-0 text-success">
                <span class="material-symbols-rounded align-middle me-2">inventory_2</span>
                Control de Stock
            </h2>
            <p class="text-muted mb-0">Gestione el inventario por sucursal, realice transferencias y ajustes</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary shadow-sm me-2" onclick="abrirModalTransferencia()">
                <span class="material-symbols-rounded align-middle me-1">swap_horiz</span>
                Transferir Stock
            </button>
            <button type="button" class="btn btn-warning shadow-sm" onclick="abrirModalAjuste()">
                <span class="material-symbols-rounded align-middle me-1">adjust</span>
                Ajustar Stock
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
                            <h6 class="text-muted mb-1">Stock Total</h6>
                            <h3 class="mb-0 text-success" id="statStockTotal">0</h3>
                            <small class="text-muted">Unidades en inventario</small>
                        </div>
                        <span class="material-symbols-rounded text-success" style="font-size:40px;">inventory</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger bg-opacity-10 border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Stock Crítico</h6>
                            <h3 class="mb-0 text-danger" id="statStockCritico">0</h3>
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
                            <h3 class="mb-0 text-warning" id="statStockBajo">0</h3>
                            <small class="text-muted">6-10 unidades</small>
                        </div>
                        <span class="material-symbols-rounded text-warning" style="font-size:40px;">priority_high</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Por Vencer (30d)</h6>
                            <h3 class="mb-0 text-info" id="statPorVencer">0</h3>
                            <small class="text-muted">Lotes próximos a vencer</small>
                        </div>
                        <span class="material-symbols-rounded text-info" style="font-size:40px;">schedule</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold text-secondary small">BUSCAR</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <span class="material-symbols-rounded text-muted">search</span>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-0" id="buscarStock" placeholder="Lote, medicamento, código...">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-secondary small">MEDICAMENTO</label>
                    <select class="form-select" id="filtroMedicamento">
                        <option value="">Todos los medicamentos</option>
                        <?php foreach ($medicamentos_filtro as $med): ?>
                            <option value="<?php echo $med['id_medicamento']; ?>"><?php echo htmlspecialchars($med['nombre_completo'] ?? $med['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-secondary small">SUCURSAL</label>
                    <select class="form-select" id="filtroSucursal">
                        <option value="">Todas las sucursales</option>
                        <?php foreach ($sucursales as $suc): ?>
                            <option value="<?php echo $suc['id_sucursal']; ?>"><?php echo htmlspecialchars($suc['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-secondary small">ESTADO STOCK</label>
                    <select class="form-select" id="filtroEstadoStock">
                        <option value="">Todos</option>
                        <option value="critico">Crítico (≤5)</option>
                        <option value="bajo">Bajo (6-10)</option>
                        <option value="normal">Normal (>10)</option>
                        <option value="agotado">Agotado (0)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-secondary small">ESTADO DEL LOTE</label>
                    <select class="form-select" id="filtroEstadoLote">
                        <option value="">Todos los estados</option>
                        <option value="ACTIVO">✅ Activo</option>
                        <option value="VENCIDO">❌ Vencido</option>
                        <option value="RETIRADO">📤 Retirado</option>
                        <option value="MERMA">📉 Merma</option>
                        <option value="DAÑADO">💔 Dañado</option>
                    </select>
                </div>
                <div class="col-md-12 mt-2">
                    <button class="btn btn-outline-secondary w-100 fw-bold" onclick="limpiarFiltros()">
                        <span class="material-symbols-rounded align-middle me-1">filter_list_off</span>
                        Quitar filtros
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLA DE STOCK -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaStock">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Lote</th>
                            <th>Medicamento</th>
                            <th>Concentración</th>
                            <th>Sucursal</th>
                            <th class="text-center">Cantidad</th>
                            <th>Vencimiento</th>
                            <th>Estado Lote</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaStockBody">
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <div class="spinner-border text-success" role="status"></div>
                                <p class="mt-2">Cargando stock...</p>
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

<!-- MODAL PARA TRANSFERENCIA -->
<div class="modal fade" id="modalTransferencia" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-primary text-white p-4">
                <h5 class="modal-title d-flex align-items-center">
                    <span class="material-symbols-rounded me-2">swap_horiz</span>
                    Transferir Stock
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formTransferencia">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted">LOTE *</label>
                        <select class="form-select" id="transferenciaLote" required>
                            <option value="">Seleccionar lote...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted">SUCURSAL ORIGEN *</label>
                        <select class="form-select" id="transferenciaSucursalOrigen" required>
                            <option value="">Seleccionar sucursal...</option>
                            <?php foreach ($sucursales as $suc): ?>
                                <option value="<?php echo $suc['id_sucursal']; ?>"><?php echo htmlspecialchars($suc['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted">SUCURSAL DESTINO *</label>
                        <select class="form-select" id="transferenciaSucursalDestino" required>
                            <option value="">Seleccionar sucursal...</option>
                            <?php foreach ($sucursales as $suc): ?>
                                <option value="<?php echo $suc['id_sucursal']; ?>"><?php echo htmlspecialchars($suc['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted">CANTIDAD *</label>
                        <input type="number" class="form-control" id="transferenciaCantidad" required min="1" placeholder="0">
                        <small class="text-muted" id="transferenciaStockDisponible"></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted">MOTIVO</label>
                        <textarea class="form-control" id="transferenciaMotivo" rows="2" placeholder="Motivo de la transferencia..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary fw-bold shadow-sm" onclick="realizarTransferencia()">Realizar Transferencia</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA AJUSTE DE STOCK -->
<div class="modal fade" id="modalAjuste" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-warning text-white p-4">
                <h5 class="modal-title d-flex align-items-center">
                    <span class="material-symbols-rounded me-2">adjust</span>
                    Ajustar Stock
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formAjuste">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted">LOTE *</label>
                        <select class="form-select" id="ajusteLote" required>
                            <option value="">Seleccionar lote...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted">SUCURSAL *</label>
                        <select class="form-select" id="ajusteSucursal" required>
                            <option value="">Seleccionar sucursal...</option>
                            <?php foreach ($sucursales as $suc): ?>
                                <option value="<?php echo $suc['id_sucursal']; ?>"><?php echo htmlspecialchars($suc['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted">TIPO DE AJUSTE *</label>
                        <select class="form-select" id="ajusteTipo" required>
                            <option value="">Seleccionar tipo...</option>
                            <option value="ENTRADA">Entrada (+)</option>
                            <option value="SALIDA">Salida (-)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted">CANTIDAD *</label>
                        <input type="number" class="form-control" id="ajusteCantidad" required min="1" placeholder="0">
                        <small class="text-muted" id="ajusteStockActual"></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted">MOTIVO *</label>
                        <select class="form-select" id="ajusteMotivo" required>
                            <option value="">Seleccionar motivo...</option>
                            <option value="MERMA">Merma (pérdida natural)</option>
                            <option value="DAÑADO">Producto dañado</option>
                            <option value="DEVOLUCION_PROVEEDOR">Devolución a proveedor</option>
                            <option value="INVENTARIO_FISICO">Ajuste por inventario físico</option>
                            <option value="OTRO">Otro</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted">OBSERVACIONES</label>
                        <textarea class="form-control" id="ajusteObservaciones" rows="2" placeholder="Detalle del ajuste..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning fw-bold shadow-sm" onclick="realizarAjuste()">Realizar Ajuste</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';
const RUTAS_API = {
    listarStock: BASE_URL + '/backend/inventario/listar_stock.php',
    listarLotes: BASE_URL + '/backend/inventario/listar_lotes_select.php',
    transferir: BASE_URL + '/backend/inventario/transferir_stock.php',
    ajustar: BASE_URL + '/backend/inventario/ajustar_stock.php',
    estadisticas: BASE_URL + '/backend/inventario/estadisticas_stock.php',
    obtenerStockLote: BASE_URL + '/backend/inventario/obtener_stock_lote.php'
};

// Variables globales
let stockData = [];
let paginaActual = 1;
let filasPorPagina = 10;
let filtros = { busqueda: '', medicamento: '', sucursal: '', estado_stock: '', estado_lote: '' };

let modalTransferencia, modalAjuste;

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    const elTransferencia = document.getElementById('modalTransferencia');
    const elAjuste = document.getElementById('modalAjuste');
    
    if (elTransferencia) {
        modalTransferencia = new bootstrap.Modal(elTransferencia, { backdrop: 'static', keyboard: true });
    }
    if (elAjuste) {
        modalAjuste = new bootstrap.Modal(elAjuste, { backdrop: 'static', keyboard: true });
    }
    
    const buscarInput = document.getElementById('buscarStock');
    let timeoutBusqueda;
    buscarInput.addEventListener('input', function() {
        clearTimeout(timeoutBusqueda);
        timeoutBusqueda = setTimeout(() => {
            filtros.busqueda = this.value;
            cargarStock();
        }, 500);
    });
    
    document.getElementById('filtroMedicamento').addEventListener('change', function() {
        filtros.medicamento = this.value;
        cargarStock();
    });
    
    document.getElementById('filtroSucursal').addEventListener('change', function() {
        filtros.sucursal = this.value;
        cargarStock();
    });
    
    document.getElementById('filtroEstadoStock').addEventListener('change', function() {
        filtros.estado_stock = this.value;
        cargarStock();
    });
    
    document.getElementById('filtroEstadoLote').addEventListener('change', function() {
        filtros.estado_lote = this.value;
        cargarStock();
    });
    
    cargarLotesSelect();
    cargarStock();
    actualizarEstadisticas();
});

function cargarLotesSelect() {
    fetch(RUTAS_API.listarLotes)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.lotes) {
                const selectTransferencia = document.getElementById('transferenciaLote');
                const selectAjuste = document.getElementById('ajusteLote');
                
                selectTransferencia.innerHTML = '<option value="">Seleccionar lote...</option>';
                selectAjuste.innerHTML = '<option value="">Seleccionar lote...</option>';
                
                data.lotes.forEach(l => {
                    const option = document.createElement('option');
                    option.value = l.id_lote;
                    option.textContent = `${l.numero_lote} - ${l.medicamento_nombre || 'Sin medicamento'}`;
                    selectTransferencia.appendChild(option.cloneNode(true));
                    selectAjuste.appendChild(option.cloneNode(true));
                });
            }
        })
        .catch(error => console.error('Error cargando lotes:', error));
}

document.getElementById('transferenciaLote')?.addEventListener('change', actualizarStockDisponible);
document.getElementById('transferenciaSucursalOrigen')?.addEventListener('change', actualizarStockDisponible);

function actualizarStockDisponible() {
    const idLote = document.getElementById('transferenciaLote').value;
    const idSucursal = document.getElementById('transferenciaSucursalOrigen').value;
    
    if (idLote && idSucursal) {
        fetch(`${RUTAS_API.obtenerStockLote}?id_lote=${idLote}&id_sucursal=${idSucursal}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const stockSpan = document.getElementById('transferenciaStockDisponible');
                    stockSpan.innerHTML = `Stock disponible: ${data.stock} unidades`;
                    document.getElementById('transferenciaCantidad').max = data.stock;
                }
            })
            .catch(error => console.error('Error:', error));
    }
}

document.getElementById('ajusteLote')?.addEventListener('change', actualizarStockActual);
document.getElementById('ajusteSucursal')?.addEventListener('change', actualizarStockActual);

function actualizarStockActual() {
    const idLote = document.getElementById('ajusteLote').value;
    const idSucursal = document.getElementById('ajusteSucursal').value;
    
    if (idLote && idSucursal) {
        fetch(`${RUTAS_API.obtenerStockLote}?id_lote=${idLote}&id_sucursal=${idSucursal}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const stockSpan = document.getElementById('ajusteStockActual');
                    stockSpan.innerHTML = `Stock actual: ${data.stock} unidades`;
                }
            })
            .catch(error => console.error('Error:', error));
    }
}

function actualizarEstadisticas() {
    fetch(RUTAS_API.estadisticas)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statStockTotal').textContent = data.stock_total || 0;
                document.getElementById('statStockCritico').textContent = data.stock_critico || 0;
                document.getElementById('statStockBajo').textContent = data.stock_bajo || 0;
                document.getElementById('statPorVencer').textContent = data.lotes_por_vencer || 0;
            }
        })
        .catch(error => console.error('Error:', error));
}

function cargarStock() {
    const tbody = document.getElementById('tablaStockBody');
    tbody.innerHTML = `<tr><td colspan="8" class="text-center"><div class="spinner-border text-success"></div><p>Cargando...</p></td></tr>`;
    
    let url = `${RUTAS_API.listarStock}?pagina=${paginaActual}&limite=${filasPorPagina}`;
    if (filtros.busqueda) url += `&busqueda=${encodeURIComponent(filtros.busqueda)}`;
    if (filtros.medicamento) url += `&medicamento=${filtros.medicamento}`;
    if (filtros.sucursal) url += `&sucursal=${filtros.sucursal}`;
    if (filtros.estado_stock) url += `&estado_stock=${filtros.estado_stock}`;
    if (filtros.estado_lote) url += `&estado_lote=${filtros.estado_lote}`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                stockData = data.stock;
                renderizarTabla(stockData);
                actualizarPaginacion(data.total);
            } else {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error: ${data.message}</td></tr>`;
            }
        })
        .catch(() => {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error de conexión</td></tr>`;
        });
}

function renderizarTabla(stock) {
    const tbody = document.getElementById('tablaStockBody');
    if (!stock || stock.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted">No hay stock registrado</td></tr>`;
        return;
    }
    
    let html = '';
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    
    stock.forEach(s => {
        const fechaVen = new Date(s.fecha_vencimiento);
        let cantidadClass = '';
        let cantidadBadge = '';
        
        if (s.cantidad === 0) {
            cantidadClass = 'text-danger fw-bold';
            cantidadBadge = '<span class="badge bg-danger">AGOTADO</span>';
        } else if (s.cantidad <= 5) {
            cantidadClass = 'text-danger fw-bold';
            cantidadBadge = '<span class="badge bg-danger">CRÍTICO</span>';
        } else if (s.cantidad <= 10) {
            cantidadClass = 'text-warning fw-bold';
            cantidadBadge = '<span class="badge bg-warning text-dark">BAJO</span>';
        } else {
            cantidadBadge = '<span class="badge bg-success">NORMAL</span>';
        }
        
        let vencimientoClass = '';
        let vencimientoBadge = '';
        if (fechaVen < hoy) {
            vencimientoClass = 'text-danger fw-bold';
            vencimientoBadge = '<span class="badge bg-danger">VENCIDO</span>';
        } else if (fechaVen <= new Date(hoy.getTime() + 30 * 24 * 60 * 60 * 1000)) {
            vencimientoClass = 'text-warning fw-bold';
            vencimientoBadge = '<span class="badge bg-warning text-dark">PRÓXIMO</span>';
        }
        
        let estadoLoteBadge = '';
        if (s.estado_lote === 'ACTIVO') {
            estadoLoteBadge = '<span class="badge bg-success">ACTIVO</span>';
        } else if (s.estado_lote === 'VENCIDO') {
            estadoLoteBadge = '<span class="badge bg-danger">VENCIDO</span>';
        } else if (s.estado_lote === 'RETIRADO') {
            estadoLoteBadge = '<span class="badge bg-secondary">RETIRADO</span>';
        } else if (s.estado_lote === 'MERMA') {
            estadoLoteBadge = '<span class="badge bg-info">MERMA</span>';
        } else if (s.estado_lote === 'DAÑADO') {
            estadoLoteBadge = '<span class="badge bg-dark">DAÑADO</span>';
        }
        
        html += `<tr>
            <td class="ps-4"><code>${escapeHtml(s.numero_lote)}</code></td>
            <td><strong>${escapeHtml(s.medicamento_nombre)}</strong><br><small class="text-muted">${escapeHtml(s.presentacion || '')}</small></td>
            <td>${s.concentracion || '-'} ${s.unidad_abrev || ''}</td>
            <td>${escapeHtml(s.sucursal_nombre)}</td>
            <td class="text-center ${cantidadClass}">
                <strong>${s.cantidad} und</strong><br>
                ${cantidadBadge}
              </td>
            <td class="${vencimientoClass}">
                ${formatDate(s.fecha_vencimiento)}<br>
                ${vencimientoBadge}
              </td>
            <td class="text-center">${estadoLoteBadge}</td>
            <td class="text-center">
                <div class="d-flex justify-content-center gap-2">
                    <button class="btn btn-sm btn-light text-primary" onclick="abrirModalTransferenciaConLote(${s.id_lote}, ${s.id_sucursal})" title="Transferir">
                        <span class="material-symbols-rounded">swap_horiz</span>
                    </button>
                    <button class="btn btn-sm btn-light text-warning" onclick="abrirModalAjusteConLote(${s.id_lote}, ${s.id_sucursal})" title="Ajustar">
                        <span class="material-symbols-rounded">adjust</span>
                    </button>
                </div>
            </td>
         </tr>`;
    });
    tbody.innerHTML = html;
}

function abrirModalTransferenciaConLote(idLote, idSucursal) {
    document.getElementById('transferenciaLote').value = idLote;
    document.getElementById('transferenciaSucursalOrigen').value = idSucursal;
    document.getElementById('transferenciaSucursalDestino').value = '';
    document.getElementById('transferenciaCantidad').value = '';
    document.getElementById('transferenciaMotivo').value = '';
    actualizarStockDisponible();
    modalTransferencia.show();
}

function abrirModalAjusteConLote(idLote, idSucursal) {
    document.getElementById('ajusteLote').value = idLote;
    document.getElementById('ajusteSucursal').value = idSucursal;
    document.getElementById('ajusteTipo').value = '';
    document.getElementById('ajusteCantidad').value = '';
    document.getElementById('ajusteMotivo').value = '';
    document.getElementById('ajusteObservaciones').value = '';
    actualizarStockActual();
    modalAjuste.show();
}

function abrirModalTransferencia() {
    document.getElementById('transferenciaLote').value = '';
    document.getElementById('transferenciaSucursalOrigen').value = '';
    document.getElementById('transferenciaSucursalDestino').value = '';
    document.getElementById('transferenciaCantidad').value = '';
    document.getElementById('transferenciaMotivo').value = '';
    document.getElementById('transferenciaStockDisponible').innerHTML = '';
    modalTransferencia.show();
}

function abrirModalAjuste() {
    document.getElementById('ajusteLote').value = '';
    document.getElementById('ajusteSucursal').value = '';
    document.getElementById('ajusteTipo').value = '';
    document.getElementById('ajusteCantidad').value = '';
    document.getElementById('ajusteMotivo').value = '';
    document.getElementById('ajusteObservaciones').value = '';
    document.getElementById('ajusteStockActual').innerHTML = '';
    modalAjuste.show();
}

function realizarTransferencia() {
    const datos = {
        id_lote: document.getElementById('transferenciaLote').value,
        id_sucursal_origen: document.getElementById('transferenciaSucursalOrigen').value,
        id_sucursal_destino: document.getElementById('transferenciaSucursalDestino').value,
        cantidad: document.getElementById('transferenciaCantidad').value,
        motivo: document.getElementById('transferenciaMotivo').value
    };
    
    if (!datos.id_lote || !datos.id_sucursal_origen || !datos.id_sucursal_destino || !datos.cantidad || datos.cantidad <= 0) {
        Swal.fire('Error', 'Complete todos los campos requeridos', 'error');
        return;
    }
    
    if (datos.id_sucursal_origen === datos.id_sucursal_destino) {
        Swal.fire('Error', 'La sucursal origen y destino no pueden ser la misma', 'error');
        return;
    }
    
    Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch(RUTAS_API.transferir, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos)
    })
    .then(r => r.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire('¡Transferencia realizada!', data.message, 'success');
            modalTransferencia.hide();
            cargarStock();
            actualizarEstadisticas();
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(() => {
        Swal.close();
        Swal.fire('Error de conexión', '', 'error');
    });
}

function realizarAjuste() {
    const tipo = document.getElementById('ajusteTipo').value;
    const cantidad = parseInt(document.getElementById('ajusteCantidad').value);
    
    const datos = {
        id_lote: document.getElementById('ajusteLote').value,
        id_sucursal: document.getElementById('ajusteSucursal').value,
        tipo: tipo,
        cantidad: cantidad,
        motivo: document.getElementById('ajusteMotivo').value,
        observaciones: document.getElementById('ajusteObservaciones').value
    };
    
    if (!datos.id_lote || !datos.id_sucursal || !datos.tipo || !datos.cantidad || datos.cantidad <= 0 || !datos.motivo) {
        Swal.fire('Error', 'Complete todos los campos requeridos', 'error');
        return;
    }
    
    Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch(RUTAS_API.ajustar, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos)
    })
    .then(r => r.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire('¡Ajuste realizado!', data.message, 'success');
            modalAjuste.hide();
            cargarStock();
            actualizarEstadisticas();
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(() => {
        Swal.close();
        Swal.fire('Error de conexión', '', 'error');
    });
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

function cambiarPagina(pagina) { paginaActual = pagina; cargarStock(); }

function limpiarFiltros() {
    document.getElementById('buscarStock').value = '';
    document.getElementById('filtroMedicamento').value = '';
    document.getElementById('filtroSucursal').value = '';
    document.getElementById('filtroEstadoStock').value = '';
    document.getElementById('filtroEstadoLote').value = '';
    filtros = { busqueda: '', medicamento: '', sucursal: '', estado_stock: '', estado_lote: '' };
    paginaActual = 1;
    cargarStock();
}

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
.pagination-custom .page-item.active .page-link { background: #198754 !important; color: white; box-shadow: 0 4px 12px rgba(25,135,84,0.3); }
.pagination-custom .page-item:not(.active):hover .page-link { background: #e9ecef; color: #198754; transform: translateY(-2px); }
.table-hover tbody tr:hover { background: rgba(25,135,84,0.05); }
.table thead th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: #6c757d; padding: 15px 12px; background: #f8f9fa; }
.form-control, .form-select { border: 1.5px solid #dee2e6 !important; border-radius: 10px; }
.form-control:focus, .form-select:focus { border-color: #198754 !important; box-shadow: 0 0 0 0.25rem rgba(25,135,84,0.1) !important; }
.btn-light { background: #f8f9fa; border: none; width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; transition: all 0.2s; }
.btn-light:hover { transform: translateY(-2px); background: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important; }
</style>