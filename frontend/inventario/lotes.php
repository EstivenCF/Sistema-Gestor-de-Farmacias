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

// Obtener sucursales para el select
$sucursales = [];
try {
    $stmt = $conexion->query("SELECT id_sucursal, nombre FROM sucursales WHERE estado = true ORDER BY nombre");
    $sucursales = $stmt->fetchAll();
} catch(PDOException $e) {}

// Datos de la empresa para el PDF
$empresa_nombre = '';
$empresa_rnc = '';
$empresa_direccion = '';
$empresa_telefono = '';
$empresa_email = '';
try {
    $stmt = $conexion->query("SELECT nombre, rnc, direccion FROM empresa LIMIT 1");
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($emp) {
        $empresa_nombre = $emp['nombre'];
        $empresa_rnc = $emp['rnc'];
        $empresa_direccion = $emp['direccion'];
    }
    $stmt = $conexion->query("SELECT t.numero FROM empresa_telefono et JOIN telefonos t ON et.id_telefono = t.id_telefono LIMIT 1");
    $tel = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($tel) $empresa_telefono = $tel['numero'];
    $stmt = $conexion->query("SELECT c.email FROM empresa_correo ec JOIN correos c ON ec.id_correo = c.id_correo LIMIT 1");
    $mail = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($mail) $empresa_email = $mail['email'];
} catch(PDOException $e) {}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 text-success">
                <span class="material-symbols-rounded align-middle me-2">inventory</span>
                Gestión de Lotes
            </h2>
            <p class="text-muted mb-0">Administre los lotes de medicamentos y controle fechas de vencimiento</p>
        </div>
        <div>
            <button type="button" class="btn btn-success shadow-sm" onclick="abrirModalNuevo()">
                <span class="material-symbols-rounded align-middle me-1">add</span>
                Nuevo Lote
            </button>
            <button type="button" class="btn btn-outline-success ms-2" onclick="exportarPDF()">
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
                            <h6 class="text-muted mb-1">Total Lotes</h6>
                            <h3 class="mb-0 text-success" id="statTotalLotes">0</h3>
                            <small class="text-muted">Registrados</small>
                        </div>
                        <span class="material-symbols-rounded text-success" style="font-size:40px;">inventory</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary bg-opacity-10 border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Activos</h6>
                            <h3 class="mb-0 text-primary" id="statActivos">0</h3>
                            <small class="text-muted">En circulación</small>
                        </div>
                        <span class="material-symbols-rounded text-primary" style="font-size:40px;">check_circle</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger bg-opacity-10 border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Vencidos</h6>
                            <h3 class="mb-0 text-danger" id="statVencidos">0</h3>
                            <small class="text-muted">Expirados</small>
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
                            <h6 class="text-muted mb-1">Por Vencer</h6>
                            <h3 class="mb-0 text-warning" id="statPorVencer">0</h3>
                            <small class="text-muted">Próximos 30 días</small>
                        </div>
                        <span class="material-symbols-rounded text-warning" style="font-size:40px;">schedule</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-bold text-secondary small">BUSCAR</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <span class="material-symbols-rounded text-muted">search</span>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-0" id="buscarLote" placeholder="Número de lote, medicamento...">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-secondary small">ESTADO</label>
                    <select class="form-select" id="filtroEstado">
                        <option value="">Todos</option>
                        <option value="ACTIVO">Activos</option>
                        <option value="VENCIDO">Vencidos</option>
                        <option value="RETIRADO">Retirados</option>
                        <option value="MERMA">Merma</option>
                        <option value="DAÑADO">Dañado</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button class="btn btn-outline-secondary w-50 fw-bold" onclick="limpiarFiltros()">
                        <span class="material-symbols-rounded align-middle me-1">filter_list_off</span>
                        Quitar filtros
                    </button>
                    <button class="btn btn-warning w-50 fw-bold" onclick="marcarVencidos()">
                        <span class="material-symbols-rounded align-middle me-1">update</span>
                        Marcar Vencidos
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLA DE LOTES -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaLotes">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>MEDICAMENTO</th>
                            <th>NÚMERO DE LOTE</th>
                            <th>FECHA VENCIMIENTO</th>
                            <th>ESTADO</th>
                            <th>STOCK</th>
                            <th>COSTO LOTE</th>
                            <th>UBICACIÓN</th>
                            <th class="text-center">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody id="tablaLotesBody">
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <div class="spinner-border text-success" role="status"></div>
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

<!-- MODAL PARA NUEVO/EDITAR LOTE -->
<div class="modal fade" id="modalLote" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-success text-white p-4">
                <h5 class="modal-title d-flex align-items-center" id="modalTitulo">
                    <span class="material-symbols-rounded me-2">add_circle</span>
                    Nuevo Lote
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                <form id="formLote">
                    <input type="hidden" id="loteId">
                    
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-muted">MEDICAMENTO *</label>
                            <select class="form-select form-control-lg border" id="medicamentoLote" required>
                                <option value="">Seleccionar medicamento...</option>
                            </select>
                            <div class="invalid-feedback">Debe seleccionar un medicamento</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">NÚMERO DE LOTE *</label>
                            <input type="text" class="form-control border" id="numeroLote" required placeholder="Ej: L-001-2024">
                            <div class="invalid-feedback">El número de lote es obligatorio</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">FECHA DE VENCIMIENTO *</label>
                            <input type="date" class="form-control border" id="fechaVencimiento" required>
                            <div class="invalid-feedback">La fecha de vencimiento es obligatoria</div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-muted">CANTIDAD INICIAL *</label>
                            <input type="number" class="form-control border" id="cantidadInicial" required min="1" placeholder="0">
                            <div class="invalid-feedback">La cantidad inicial es obligatoria y debe ser mayor a 0</div>
                            <small class="text-muted">Unidades que ingresan a inventario</small>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-muted">COSTO DEL LOTE *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white">RD$</span>
                                <input type="number" step="0.01" class="form-control border" id="costoLote" required placeholder="0.00">
                            </div>
                            <div class="invalid-feedback">El costo del lote es obligatorio</div>
                            <small class="text-muted">Costo total de compra de este lote</small>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-muted">SUCURSAL DESTINO *</label>
                            <select class="form-select border" id="sucursalLote" required>
                                <option value="">Seleccionar sucursal...</option>
                                <?php foreach ($sucursales as $suc): ?>
                                    <option value="<?php echo $suc['id_sucursal']; ?>"><?php echo htmlspecialchars($suc['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Debe seleccionar una sucursal</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">CÓDIGO DE BARRAS</label>
                            <input type="text" class="form-control border" id="codigoBarras" placeholder="Código de barras del producto">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">UBICACIÓN (Estante)</label>
                            <input type="text" class="form-control border" id="ubicacion" placeholder="Ej: Estante A1, Fila 3">
                            <small class="text-muted">Ubicación física dentro de la sucursal</small>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-muted">ESTADO</label>
                            <select class="form-select border" id="estadoLote">
                                <option value="ACTIVO">Activo</option>
                                <option value="VENCIDO">Vencido</option>
                                <option value="RETIRADO">Retirado</option>
                                <option value="MERMA">Merma</option>
                                <option value="DAÑADO">Dañado</option>
                            </select>
                        </div>
                        
                        <div class="col-md-12" id="divMotivoCambio" style="display: none;">
                            <label class="form-label fw-bold text-muted">MOTIVO DEL CAMBIO DE ESTADO *</label>
                            <textarea class="form-control border" id="motivoCambio" rows="2" placeholder="Explique detalladamente por qué cambia el estado de este lote..."></textarea>
                            <div class="invalid-feedback">Debe especificar un motivo para cambiar el estado</div>
                            <small class="text-muted">Este motivo quedará registrado en el historial del lote</small>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-bold text-muted">OBSERVACIONES</label>
                            <textarea class="form-control border" id="observacionesLote" rows="2" placeholder="Observaciones adicionales..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-end gap-3">
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success px-5 fw-bold shadow-sm" onclick="guardarLote()">Guardar Lote</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA VER DETALLES -->
<div class="modal fade" id="modalDetalles" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-success text-white p-4">
                <h5 class="modal-title d-flex align-items-center">
                    <span class="material-symbols-rounded me-2">inventory</span>
                    Detalles del Lote
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;" id="detallesContenido">
                <div class="text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                    <p class="mt-2">Cargando detalles...</p>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-secondary" onclick="exportarIndividualPDF()">
                        <span class="material-symbols-rounded align-middle me-1">picture_as_pdf</span>
                        Exportar PDF
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-warning" id="btnEditarDesdeDetalle" onclick="editarDesdeDetalle()" style="display:none;">
                        <span class="material-symbols-rounded align-middle me-1">edit</span>
                        Editar
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';
const EMPRESA_NOMBRE = '<?php echo addslashes($empresa_nombre); ?>';
const EMPRESA_RNC = '<?php echo addslashes($empresa_rnc); ?>';
const EMPRESA_DIRECCION = '<?php echo addslashes($empresa_direccion); ?>';
const EMPRESA_TELEFONO = '<?php echo addslashes($empresa_telefono); ?>';
const EMPRESA_EMAIL = '<?php echo addslashes($empresa_email); ?>';

const RUTAS_API = {
    listar: BASE_URL + '/backend/inventario/listar_lotes.php',
    guardar: BASE_URL + '/backend/inventario/guardar_lote.php',
    eliminar: BASE_URL + '/backend/inventario/eliminar_lote.php',
    detalle: BASE_URL + '/backend/inventario/detalle_lote.php',
    estadisticas: BASE_URL + '/backend/inventario/estadisticas_lotes.php',
    medicamentos: BASE_URL + '/backend/inventario/listar_medicamentos_select.php'
};

// ==================== VARIABLES GLOBALES ====================
let lotesData = [];
let paginaActual = 1;
let filasPorPagina = 10;
let filtros = { busqueda: '', estado: '' };
let detallesActualId = null;
let detallesActualData = null;

let modalLote, modalDetalles;

// ==================== INICIALIZACIÓN ====================
document.addEventListener('DOMContentLoaded', function() {
    const elLote = document.getElementById('modalLote');
    const elDetalles = document.getElementById('modalDetalles');
    
    if (elLote) {
        modalLote = new bootstrap.Modal(elLote, {
            backdrop: 'static',
            keyboard: true
        });
        
        // Limpiar campo loteId cuando se cierra el modal (importante)
        elLote.addEventListener('hidden.bs.modal', function() {
            document.getElementById('loteId').value = '';
            document.getElementById('formLote').reset();
            // También resetear estado visual
            document.getElementById('estadoLote').value = 'ACTIVO';
            document.getElementById('estadoLote').setAttribute('data-estado-anterior', 'ACTIVO');
            document.getElementById('divMotivoCambio').style.display = 'none';
            document.getElementById('motivoCambio').value = '';
            document.getElementById('motivoCambio').required = false;
            document.getElementById('cantidadInicial').disabled = false;
            document.getElementById('costoLote').disabled = false;
            document.getElementById('sucursalLote').disabled = false;
        });
    }
    if (elDetalles) {
        modalDetalles = new bootstrap.Modal(elDetalles, {
            backdrop: 'static',
            keyboard: true
        });
    }
    
    // Mostrar campo de motivo cuando cambia el estado (solo en edición)
    document.getElementById('estadoLote').addEventListener('change', function() {
        const loteId = document.getElementById('loteId').value;
        const estadoAnterior = this.getAttribute('data-estado-anterior') || 'ACTIVO';
        const divMotivo = document.getElementById('divMotivoCambio');
        const motivoInput = document.getElementById('motivoCambio');
        
        if (loteId && estadoAnterior !== this.value) {
            divMotivo.style.display = 'block';
            motivoInput.required = true;
        } else {
            divMotivo.style.display = 'none';
            motivoInput.required = false;
            motivoInput.value = '';
        }
    });
    
    const buscarInput = document.getElementById('buscarLote');
    let timeoutBusqueda;
    buscarInput.addEventListener('input', function() {
        clearTimeout(timeoutBusqueda);
        timeoutBusqueda = setTimeout(() => {
            filtros.busqueda = this.value;
            cargarLotes();
        }, 500);
    });
    
    document.getElementById('filtroEstado').addEventListener('change', function() {
        filtros.estado = this.value;
        cargarLotes();
    });
    
    cargarMedicamentosSelect();
    cargarLotes();
    actualizarEstadisticas();
});

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

function abrirModalCentrado(modal) {
    modal.show();
}

function cargarMedicamentosSelect() {
    fetch(RUTAS_API.medicamentos)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.medicamentos) {
                const select = document.getElementById('medicamentoLote');
                select.innerHTML = '<option value="">Seleccionar medicamento...</option>';
                data.medicamentos.forEach(m => {
                    const option = document.createElement('option');
                    option.value = m.id_medicamento;
                    option.textContent = m.nombre_completo;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error cargando medicamentos:', error));
}

function actualizarEstadisticas() {
    fetch(RUTAS_API.estadisticas)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statTotalLotes').textContent = data.total_lotes || 0;
                document.getElementById('statActivos').textContent = data.activos || 0;
                document.getElementById('statVencidos').textContent = data.vencidos || 0;
                document.getElementById('statPorVencer').textContent = data.por_vencer || 0;
            }
        })
        .catch(error => console.error('Error actualizando estadísticas:', error));
}

function cargarLotes() {
    const tbody = document.getElementById('tablaLotesBody');
    tbody.innerHTML = `<tr><td colspan="9" class="text-center"><div class="spinner-border text-success"></div><p>Cargando...</p></td></tr>`;
    
    let url = `${RUTAS_API.listar}?pagina=${paginaActual}&limite=${filasPorPagina}`;
    if (filtros.busqueda) url += `&busqueda=${encodeURIComponent(filtros.busqueda)}`;
    if (filtros.estado) url += `&estado=${filtros.estado}`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                lotesData = data.lotes;
                renderizarTabla(lotesData);
                actualizarPaginacion(data.total);
            } else {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error: ${data.message}</td></tr>`;
            }
        })
        .catch(() => {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error de conexión</td></tr>`;
        });
}

function renderizarTabla(lotes) {
    const tbody = document.getElementById('tablaLotesBody');
    if (!lotes || lotes.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted">No hay lotes registrados</td></tr>`;
        return;
    }
    
    let html = '';
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    
    lotes.forEach(l => {
        const fechaVen = new Date(l.fecha_vencimiento);
        let estadoBadge = '';
        
        if (l.estado === 'ACTIVO') {
            if (fechaVen < hoy) {
                estadoBadge = '<span class="badge bg-danger">VENCIDO</span>';
            } else if (fechaVen <= new Date(hoy.getTime() + 30 * 24 * 60 * 60 * 1000)) {
                estadoBadge = '<span class="badge bg-warning text-dark">PRÓXIMO A VENCER</span>';
            } else {
                estadoBadge = '<span class="badge bg-success">ACTIVO</span>';
            }
        } else if (l.estado === 'VENCIDO') {
            estadoBadge = '<span class="badge bg-danger">VENCIDO</span>';
        } else if (l.estado === 'RETIRADO') {
            estadoBadge = '<span class="badge bg-secondary">RETIRADO</span>';
        } else if (l.estado === 'MERMA') {
            estadoBadge = '<span class="badge bg-info">MERMA</span>';
        } else if (l.estado === 'DAÑADO') {
            estadoBadge = '<span class="badge bg-dark">DAÑADO</span>';
        }
        
        const stockBadge = (l.stock_actual || 0) > 0 
            ? `<span class="badge bg-success-subtle text-success">${l.stock_actual || 0} unidades</span>`
            : `<span class="badge bg-danger-subtle text-danger">Sin stock</span>`;
        
        const medicamentoTexto = l.medicamento_nombre 
            ? `${l.medicamento_nombre} ${l.concentracion || ''} ${l.unidad_abrev || ''}`
            : 'Medicamento no disponible';
        
        html += `<tr>
            <td class="ps-4"><span class="text-success fw-bold">#${l.id_lote}</span></td>
            <td>
                <div class="fw-bold">${escapeHtml(medicamentoTexto)}</div>
                <small class="text-muted">${escapeHtml(l.presentacion || '')}</small>
            </td>
            <td><code class="bg-light p-1 rounded">${escapeHtml(l.numero_lote)}</code></td>
            <td class="${fechaVen < hoy ? 'text-danger fw-bold' : (fechaVen <= new Date(hoy.getTime() + 30 * 24 * 60 * 60 * 1000) ? 'text-warning fw-bold' : '')}">
                ${formatDate(l.fecha_vencimiento)}
                ${fechaVen < hoy ? '<br><small class="text-danger">VENCIDO</small>' : ''}
            </td>
            <td>${estadoBadge}</td>
            <td>${stockBadge}</td>
            <td>${l.costo_lote ? `RD$ ${formatNum(l.costo_lote)}` : '-'}</td>
            <td><small>${escapeHtml(l.ubicacion || '-')}</small></td>
            <td class="text-center">
                <div class="d-flex justify-content-center gap-2">
                    <button class="btn btn-sm btn-light text-info shadow-sm" onclick="verDetalles(${l.id_lote})" title="Ver">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                    <button class="btn btn-sm btn-light text-primary shadow-sm" onclick="editarLote(${l.id_lote})" title="Editar">
                        <span class="material-symbols-rounded">edit_square</span>
                    </button>
                    <button class="btn btn-sm btn-light text-danger shadow-sm" onclick="eliminarLote(${l.id_lote}, '${escapeHtml(l.numero_lote)}')" title="Eliminar">
                        <span class="material-symbols-rounded">delete</span>
                    </button>
                </div>
            </td>
        </tr>`;
    });
    tbody.innerHTML = html;
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

function cambiarPagina(pagina) { paginaActual = pagina; cargarLotes(); }

function limpiarFiltros() {
    document.getElementById('buscarLote').value = '';
    document.getElementById('filtroEstado').value = '';
    filtros = { busqueda: '', estado: '' };
    paginaActual = 1;
    cargarLotes();
}

function marcarVencidos() {
    Swal.fire({
        title: '¿Marcar lotes vencidos?',
        text: 'Esta acción marcará automáticamente como VENCIDOS todos los lotes cuya fecha de vencimiento haya pasado',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        confirmButtonText: 'Sí, marcar vencidos',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Procesando...',
                text: 'Verificando lotes vencidos',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            fetch(BASE_URL + '/backend/inventario/marcar_lotes_vencidos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire('¡Completado!', data.message, 'success');
                    cargarLotes();
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

function abrirModalNuevo() {
    // Limpiar completamente el campo oculto y el formulario
    document.getElementById('modalTitulo').innerHTML = '<span class="material-symbols-rounded me-2">add_circle</span> Nuevo Lote';
    document.getElementById('formLote').reset();
    
    // Forzar limpieza del ID del lote
    const loteIdField = document.getElementById('loteId');
    loteIdField.value = '';
    loteIdField.removeAttribute('value');
    delete loteIdField.dataset.id;
    
    // Resetear campos específicos
    document.getElementById('estadoLote').value = 'ACTIVO';
    document.getElementById('estadoLote').setAttribute('data-estado-anterior', 'ACTIVO');
    document.getElementById('cantidadInicial').value = '';
    document.getElementById('cantidadInicial').disabled = false;
    document.getElementById('costoLote').value = '';
    document.getElementById('costoLote').disabled = false;
    document.getElementById('costoLote').readOnly = false;
    document.getElementById('sucursalLote').disabled = false;
    document.getElementById('divMotivoCambio').style.display = 'none';
    document.getElementById('motivoCambio').value = '';
    document.getElementById('motivoCambio').required = false;
    
    // Limpiar validaciones
    const camposInvalidos = document.querySelectorAll('.is-invalid');
    camposInvalidos.forEach(campo => campo.classList.remove('is-invalid'));
    
    abrirModalCentrado(modalLote);
}

function editarLote(id) {
    Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch(`${RUTAS_API.detalle}?id=${id}`)
        .then(r => r.json())
        .then(data => {
            Swal.close();
            if (data.success && data.lote) {
                const l = data.lote;
                document.getElementById('modalTitulo').innerHTML = '<span class="material-symbols-rounded me-2">edit_square</span> Editar Lote';
                document.getElementById('loteId').value = l.id_lote;
                document.getElementById('medicamentoLote').value = l.id_medicamento;
                document.getElementById('numeroLote').value = l.numero_lote;
                document.getElementById('fechaVencimiento').value = l.fecha_vencimiento;
                document.getElementById('cantidadInicial').value = l.cantidad_inicial || '';
                document.getElementById('cantidadInicial').disabled = true;
                document.getElementById('costoLote').value = l.costo_lote || '';
                document.getElementById('costoLote').disabled = true;
                document.getElementById('costoLote').readOnly = true;
                document.getElementById('codigoBarras').value = l.codigo_barras || '';
                document.getElementById('ubicacion').value = l.ubicacion || '';
                document.getElementById('estadoLote').value = l.estado;
                document.getElementById('estadoLote').setAttribute('data-estado-anterior', l.estado);
                document.getElementById('observacionesLote').value = l.observaciones || '';
                document.getElementById('sucursalLote').disabled = true;
                document.getElementById('divMotivoCambio').style.display = 'none';
                document.getElementById('motivoCambio').value = '';
                document.getElementById('motivoCambio').required = false;
                
                abrirModalCentrado(modalLote);
            } else {
                Swal.fire('Error', data.message || 'No se pudo cargar el lote', 'error');
            }
        })
        .catch(() => {
            Swal.close();
            Swal.fire('Error de conexión', '', 'error');
        });
}

function guardarLote() {
    const camposInvalidos = document.querySelectorAll('.is-invalid');
    camposInvalidos.forEach(campo => campo.classList.remove('is-invalid'));
    
    const idMedicamento = document.getElementById('medicamentoLote').value;
    if (!idMedicamento) {
        document.getElementById('medicamentoLote').classList.add('is-invalid');
        Swal.fire('Error', 'Debe seleccionar un medicamento', 'error');
        document.getElementById('medicamentoLote').focus();
        return;
    }
    
    const numeroLote = document.getElementById('numeroLote').value.trim();
    if (!numeroLote) {
        document.getElementById('numeroLote').classList.add('is-invalid');
        Swal.fire('Error', 'El número de lote es obligatorio', 'error');
        document.getElementById('numeroLote').focus();
        return;
    }
    
    const fechaVencimiento = document.getElementById('fechaVencimiento').value;
    if (!fechaVencimiento) {
        document.getElementById('fechaVencimiento').classList.add('is-invalid');
        Swal.fire('Error', 'La fecha de vencimiento es obligatoria', 'error');
        document.getElementById('fechaVencimiento').focus();
        return;
    }
    
    const cantidadInicial = document.getElementById('cantidadInicial').value;
    if (!cantidadInicial || parseInt(cantidadInicial) <= 0) {
        document.getElementById('cantidadInicial').classList.add('is-invalid');
        Swal.fire('Error', 'La cantidad inicial debe ser mayor a 0', 'error');
        document.getElementById('cantidadInicial').focus();
        return;
    }
    
    const costoLote = document.getElementById('costoLote').value;
    if (!costoLote || parseFloat(costoLote) <= 0) {
        document.getElementById('costoLote').classList.add('is-invalid');
        Swal.fire('Error', 'El costo del lote debe ser mayor a 0', 'error');
        document.getElementById('costoLote').focus();
        return;
    }
    
    const sucursal = document.getElementById('sucursalLote').value;
    if (!sucursal && !document.getElementById('loteId').value) {
        document.getElementById('sucursalLote').classList.add('is-invalid');
        Swal.fire('Error', 'Debe seleccionar una sucursal destino', 'error');
        document.getElementById('sucursalLote').focus();
        return;
    }
    
    const loteId = document.getElementById('loteId').value;
    const estadoAnterior = document.getElementById('estadoLote').getAttribute('data-estado-anterior');
    const estadoNuevo = document.getElementById('estadoLote').value;
    const motivoCambio = document.getElementById('motivoCambio').value;
    
    if (loteId && estadoAnterior && estadoAnterior !== estadoNuevo && !motivoCambio) {
        document.getElementById('motivoCambio').classList.add('is-invalid');
        Swal.fire('Error', 'Debe especificar un motivo para cambiar el estado del lote', 'error');
        document.getElementById('motivoCambio').focus();
        return;
    }
    
    const datos = {
        id_lote: loteId ? parseInt(loteId) : null,
        id_medicamento: parseInt(idMedicamento),
        numero_lote: numeroLote,
        fecha_vencimiento: fechaVencimiento,
        cantidad_inicial: parseInt(cantidadInicial),
        costo_lote: parseFloat(costoLote),
        codigo_barras: document.getElementById('codigoBarras').value,
        ubicacion: document.getElementById('ubicacion').value,
        observaciones: document.getElementById('observacionesLote').value,
        estado: estadoNuevo,
        motivo_cambio: motivoCambio,
        id_sucursal: sucursal ? parseInt(sucursal) : null
    };
    
    // Opcional: depuración
    console.log("Enviando datos:", datos);
    
    Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch(RUTAS_API.guardar, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos)
    })
    .then(r => r.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire({ icon: 'success', title: datos.id_lote ? '¡Actualizado!' : '¡Creado!', text: data.message, timer: 1500, showConfirmButton: false })
            .then(() => {
                modalLote.hide();
                cargarLotes();
                actualizarEstadisticas();
            });
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(error => {
        Swal.close();
        console.error('Error:', error);
        Swal.fire('Error de conexión', 'No se pudo conectar con el servidor', 'error');
    });
}

function verDetalles(id) {
    detallesActualId = id;
    const modalBody = document.getElementById('detallesContenido');
    
    modalBody.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-success" role="status"></div><p class="mt-2">Cargando detalles...</p></div>`;
    
    fetch(`${RUTAS_API.detalle}?id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.lote) {
                detallesActualData = data.lote;
                const l = data.lote;
                
                let stockSucursalHtml = '';
                if (l.stock_por_sucursal && l.stock_por_sucursal.length > 0) {
                    stockSucursalHtml = `
                        <div class="mt-4">
                            <h6 class="fw-bold text-muted mb-3">STOCK POR SUCURSAL</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="bg-light"><tr><th>Sucursal</th><th>Dirección</th><th class="text-center">Cantidad</th><th class="text-center">Teléfono</th></tr></thead>
                                    <tbody>
                                        ${l.stock_por_sucursal.map(s => `<tr><td><strong>${escapeHtml(s.sucursal_nombre)}</strong></td><td><small>${escapeHtml(s.direccion || '-')}</small></td><td class="text-center"><span class="badge ${s.cantidad > 0 ? 'bg-success' : 'bg-secondary'}">${s.cantidad} unidades</span></td><td class="text-center">${escapeHtml(s.telefono || '-')}</td></tr>`).join('')}
                                    </tbody>
                                    <tfoot class="bg-light"><tr><td colspan="2"><strong>TOTAL GENERAL</strong></td><td class="text-center"><strong class="text-success">${l.stock_total || 0} unidades</strong></td><td></td></tr>
                                </table>
                            </div>
                        </div>
                    `;
                } else {
                    stockSucursalHtml = `<div class="alert alert-warning mt-3 mb-0">Este lote no tiene stock en ninguna sucursal</div>`;
                }
                
                let movimientosHtml = '';
                if (l.movimientos_recientes && l.movimientos_recientes.length > 0) {
                    movimientosHtml = `
                        <div class="mt-4">
                            <h6 class="fw-bold text-muted mb-3">MOVIMIENTOS RECIENTES</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead class="bg-light"><tr><th>Tipo</th><th>Cantidad</th><th>Sucursal</th><th>Fecha</th><th>Motivo</th><th>Usuario</th></tr></thead>
                                    <tbody>
                                        ${l.movimientos_recientes.map(m => `<tr><td><span class="badge ${m.tipo === 'ENTRADA' ? 'bg-success' : 'bg-danger'}">${m.tipo}</span></td><td>${m.cantidad}</td><td><small>${m.id_sucursal || '-'}</small></td><td><small>${formatDate(m.fecha)}</small></td><td><small>${escapeHtml(m.motivo || '-')}</small></td><td><small>${escapeHtml(m.id_usuario || '-')}</small></td></tr>`).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                }
                
                let historialHtml = '';
                if (l.historial_estados && l.historial_estados.length > 0) {
                    historialHtml = `
                        <div class="mt-4">
                            <h6 class="fw-bold text-muted mb-3">HISTORIAL DE CAMBIOS DE ESTADO</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead class="bg-light"><tr><th>Fecha</th><th>Estado Anterior</th><th>Estado Nuevo</th><th>Motivo</th><th>Observaciones</th><th>Usuario</th></tr></thead>
                                    <tbody>
                                        ${l.historial_estados.map(h => `<tr><td><small>${formatDate(h.fecha_cambio)}</small></td><td><span class="badge ${h.estado_anterior === 'ACTIVO' ? 'bg-success' : 'bg-secondary'}">${h.estado_anterior}</span></td><td><span class="badge ${h.estado_nuevo === 'ACTIVO' ? 'bg-success' : (h.estado_nuevo === 'VENCIDO' ? 'bg-danger' : 'bg-secondary')}">${h.estado_nuevo}</span></td><td><small>${escapeHtml(h.motivo)}</small></td><td><small>${escapeHtml(h.observaciones || '-')}</small></td><td><small>${escapeHtml(h.usuario_nombre || 'Sistema')}</small></td></tr>`).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                }
                
                const fechaVen = new Date(l.fecha_vencimiento);
                const hoy = new Date();
                hoy.setHours(0, 0, 0, 0);
                
                let estadoAlerta = '';
                if (fechaVen < hoy) {
                    estadoAlerta = '<span class="badge bg-danger">LOTE VENCIDO</span>';
                } else if (fechaVen <= new Date(hoy.getTime() + 30 * 24 * 60 * 60 * 1000)) {
                    estadoAlerta = '<span class="badge bg-warning text-dark">PRÓXIMO A VENCER</span>';
                } else {
                    estadoAlerta = '<span class="badge bg-success">VIGENTE</span>';
                }
                
                modalBody.innerHTML = `
                    <div class="bg-success-subtle rounded-circle d-inline-flex p-4 mb-3">
                        <span class="material-symbols-rounded text-success" style="font-size: 3rem;">inventory</span>
                    </div>
                    <h3 class="fw-bold mb-1">Lote ${escapeHtml(l.numero_lote)}</h3>
                    <span class="badge bg-success-subtle text-success mb-4">#${l.id_lote}</span>
                    ${estadoAlerta}

                    <div class="row g-4 text-start mt-2">
                        <div class="col-12"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Medicamento</small><span class="fw-bold text-dark fs-5">${escapeHtml(l.medicamento_nombre)} ${l.concentracion || ''} ${l.unidad || ''}</span><br><small class="text-muted">${escapeHtml(l.presentacion || '')} - ${escapeHtml(l.categoria || '')}</small></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Laboratorio</small><span class="fw-bold text-dark">${escapeHtml(l.laboratorio || 'No especificado')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Fecha Vencimiento</small><span class="fw-bold text-dark ${fechaVen < hoy ? 'text-danger' : ''}">${formatDate(l.fecha_vencimiento)}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Cantidad Inicial</small><span class="fw-bold text-dark">${l.cantidad_inicial || 0} unidades</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Costo del Lote</small><span class="fw-bold text-success fs-5">RD$ ${formatNum(l.costo_lote || 0)}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Código de Barras</small><span class="fw-bold text-dark">${escapeHtml(l.codigo_barras || 'No registrado')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Ubicación</small><span class="fw-bold text-dark">${escapeHtml(l.ubicacion || 'No asignada')}</span></div></div>
                        <div class="col-12"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Estado</small>${l.estado === 'ACTIVO' ? '<span class="badge bg-success">ACTIVO</span>' : (l.estado === 'VENCIDO' ? '<span class="badge bg-danger">VENCIDO</span>' : (l.estado === 'RETIRADO' ? '<span class="badge bg-secondary">RETIRADO</span>' : (l.estado === 'MERMA' ? '<span class="badge bg-info">MERMA</span>' : '<span class="badge bg-dark">DAÑADO</span>')))}</div></div>
                        <div class="col-12"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Observaciones</small><span class="text-dark">${escapeHtml(l.observaciones || 'Sin observaciones')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Fecha Registro</small><span class="fw-bold text-dark">${formatDate(l.fecha_registro)}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Registrado Por</small><span class="fw-bold text-dark">${escapeHtml(l.usuario_registro || 'Sistema')}</span></div></div>
                    </div>
                    ${stockSucursalHtml}
                    ${movimientosHtml}
                    ${historialHtml}
                `;
                
                document.getElementById('btnEditarDesdeDetalle').style.display = 'inline-flex';
                abrirModalCentrado(modalDetalles);
                scrollAlModal();
            } else {
                Swal.fire('Error', data.message || 'No se pudo cargar los detalles', 'error');
            }
        })
        .catch(() => {
            Swal.fire('Error de conexión', 'No se pudo contactar el servidor', 'error');
        });
}

function exportarPDF() {
    if (!lotesData || lotesData.length === 0) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    let htmlContent = `
        <html>
        <head><meta charset="UTF-8"><title>Reporte de Lotes</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #198754; text-align: center; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #198754; color: white; }
            .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
        </style>
        </head>
        <body>
            <h1>Reporte de Lotes</h1>
            <p><strong>Fecha de exportación:</strong> ${new Date().toLocaleString()}</p>
            <p><strong>Total de lotes:</strong> ${lotesData.length}</p>
            <table><thead><tr><th>ID</th><th>Medicamento</th><th>Número Lote</th><th>Fecha Vencimiento</th><th>Estado</th><th>Stock</th><th>Costo Lote</th></tr></thead><tbody>`;
    
    lotesData.forEach(l => {
        const medicamentoTexto = l.medicamento_nombre ? `${l.medicamento_nombre} ${l.concentracion || ''} ${l.unidad_abrev || ''}` : 'N/A';
        htmlContent += `<tr><td>${l.id_lote}</td><td>${escapeHtml(medicamentoTexto)}</td><td>${escapeHtml(l.numero_lote)}</td><td>${formatDate(l.fecha_vencimiento)}</td><td>${l.estado}</td><td>${l.stock_actual || 0}</td><td>${l.costo_lote ? `RD$ ${formatNum(l.costo_lote)}` : '-'}</td></tr>`;
    });
    
    htmlContent += `</tbody></table><div class="footer"><p>Reporte generado por el Sistema Gestor de Farmacias</p></div></body></html>`;
    
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    
    const opt = { margin: [0.5, 0.5, 0.5, 0.5], filename: `lotes_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2, letterRendering: true }, jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' } };
    
    html2pdf().set(opt).from(element).save().then(() => { document.body.removeChild(element); Swal.fire({ icon: 'success', title: 'Exportado', text: `${lotesData.length} lotes exportados a PDF`, timer: 2000, showConfirmButton: false }); }).catch(() => { document.body.removeChild(element); Swal.fire('Error', 'Error al generar el PDF', 'error'); });
}

function exportarIndividualPDF() {
    if (!detallesActualData) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }

    const l = detallesActualData;
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const fechaVen = new Date(l.fecha_vencimiento);
    const vencido = fechaVen < hoy;
    const porVencer = !vencido && fechaVen <= new Date(hoy.getTime() + 30 * 24 * 60 * 60 * 1000);
    
    function getEstadoTexto(estado) {
        const map = { 'ACTIVO': 'Activo', 'VENCIDO': 'Vencido', 'RETIRADO': 'Retirado', 'MERMA': 'Merma', 'DAÑADO': 'Dañado' };
        return map[estado] || estado;
    }
    
    function getEstadoColor(estado) {
        const map = { 'ACTIVO': '#198754', 'VENCIDO': '#dc3545', 'RETIRADO': '#6c757d', 'MERMA': '#0dcaf0', 'DAÑADO': '#212529' };
        return map[estado] || '#6c757d';
    }
    
    // Stock por sucursal
    let stockRows = '';
    if (l.stock_por_sucursal && l.stock_por_sucursal.length > 0) {
        l.stock_por_sucursal.forEach(s => {
            stockRows += `<tr>
                <td style="border:1px solid #dee2e6; padding:6px;">${escapeHtml(s.sucursal_nombre)}</td>
                <td style="border:1px solid #dee2e6; padding:6px;">${escapeHtml(s.direccion || '-')}</td>
                <td style="border:1px solid #dee2e6; padding:6px; text-align:center;">${s.cantidad}</td>
                <td style="border:1px solid #dee2e6; padding:6px;">${escapeHtml(s.telefono || '-')}</td>
            </table>`;
        });
    } else {
        stockRows = `<tr><td colspan="4" style="border:1px solid #dee2e6; padding:6px; text-align:center;">Sin stock registrado</td></tr>`;
    }
    
    // Movimientos (solo últimos 5)
    let movRows = '';
    if (l.movimientos_recientes && l.movimientos_recientes.length > 0) {
        const ultimosMov = l.movimientos_recientes.slice(0, 5);
        ultimosMov.forEach(m => {
            movRows += `<tr>
                <td style="border:1px solid #dee2e6; padding:6px;"><span style="background:${m.tipo === 'ENTRADA' ? '#198754' : '#dc3545'}; color:white; padding:2px 6px; border-radius:10px; font-size:9px;">${m.tipo}</span></td>
                <td style="border:1px solid #dee2e6; padding:6px; text-align:center;">${m.cantidad}</td>
                <td style="border:1px solid #dee2e6; padding:6px;">${formatDate(m.fecha)}</td>
                <td style="border:1px solid #dee2e6; padding:6px;">${escapeHtml(m.motivo || '-')}</td>
            </tr>`;
        });
    } else {
        movRows = `<tr><td colspan="4" style="border:1px solid #dee2e6; padding:6px; text-align:center;">Sin movimientos registrados</td></tr>`;
    }
    
    const fechaActual = new Date();
    const fechaGeneracion = fechaActual.toLocaleDateString('es-DO') + ' ' + fechaActual.toLocaleTimeString('es-DO');
    
    const htmlContent = `<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Reporte Lote ${escapeHtml(l.numero_lote)}</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; padding: 15px; background: #fff; }
            .header { margin-bottom: 15px; border-bottom: 3px solid #198754; padding-bottom: 10px; text-align: center; }
            .empresa h2 { color: #198754; font-size: 18px; margin: 0; }
            .empresa p { color: #666; font-size: 9px; margin: 3px 0; }
            .titulo-reporte { background: #198754; color: white; padding: 6px; text-align: center; border-radius: 5px; margin-bottom: 15px; }
            .titulo-reporte h1 { font-size: 14px; margin: 0; }
            .info-lote { background: #f8f9fa; border-radius: 6px; padding: 10px; margin-bottom: 15px; border: 1px solid #dee2e6; }
            .info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
            .info-label { font-weight: bold; color: #198754; font-size: 8px; text-transform: uppercase; margin-bottom: 2px; }
            .info-value { font-size: 10px; font-weight: 500; }
            .seccion { margin-bottom: 15px; }
            .seccion-titulo { background: #198754; color: white; padding: 5px 10px; border-radius: 4px 4px 0 0; font-weight: bold; font-size: 10px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #e9ecef; padding: 5px; text-align: left; font-weight: bold; font-size: 9px; border: 1px solid #dee2e6; }
            td { padding: 4px 5px; border: 1px solid #dee2e6; font-size: 9px; }
            .footer { margin-top: 15px; text-align: center; font-size: 8px; color: #999; border-top: 1px solid #dee2e6; padding-top: 8px; }
            .alerta-vencido { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 5px; border-radius: 4px; text-align: center; margin-bottom: 10px; font-size: 10px; }
            .alerta-proximo { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 5px; border-radius: 4px; text-align: center; margin-bottom: 10px; font-size: 10px; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="empresa">
                <h2>${EMPRESA_NOMBRE || 'Sistema Gestor de Farmacias'}</h2>
                ${EMPRESA_RNC ? `<p>RNC: ${EMPRESA_RNC}</p>` : ''}
                ${EMPRESA_DIRECCION ? `<p>${EMPRESA_DIRECCION}</p>` : ''}
                ${EMPRESA_TELEFONO ? `<p>Tel: ${EMPRESA_TELEFONO}</p>` : ''}
                ${EMPRESA_EMAIL ? `<p>Email: ${EMPRESA_EMAIL}</p>` : ''}
            </div>
        </div>
        
        <div class="titulo-reporte"><h1>REPORTE DE LOTE</h1></div>
        
        ${vencido ? `<div class="alerta-vencido">⚠️ LOTE VENCIDO - Vence: ${formatDate(l.fecha_vencimiento)}</div>` : (porVencer ? `<div class="alerta-proximo">⚡ PRÓXIMO A VENCER - Vence: ${formatDate(l.fecha_vencimiento)}</div>` : '')}
        
        <!-- INFORMACIÓN GENERAL -->
        <div class="info-lote">
            <div class="info-grid">
                <div><div class="info-label">Número Lote</div><div class="info-value">${escapeHtml(l.numero_lote)}</div></div>
                <div><div class="info-label">ID Lote</div><div class="info-value">#${l.id_lote}</div></div>
                <div><div class="info-label">Estado</div><div class="info-value"><span style="background:${getEstadoColor(l.estado)}; color:white; padding:2px 8px; border-radius:15px; font-size:9px;">${getEstadoTexto(l.estado)}</span></div></div>
                <div><div class="info-label">Medicamento</div><div class="info-value">${escapeHtml(l.medicamento_nombre)} ${l.concentracion || ''}</div></div>
                <div><div class="info-label">Presentación</div><div class="info-value">${escapeHtml(l.presentacion || '-')}</div></div>
                <div><div class="info-label">Categoría</div><div class="info-value">${escapeHtml(l.categoria || '-')}</div></div>
                <div><div class="info-label">Laboratorio</div><div class="info-value">${escapeHtml(l.laboratorio || '-')}</div></div>
                <div><div class="info-label">Vencimiento</div><div class="info-value">${formatDate(l.fecha_vencimiento)}</div></div>
                <div><div class="info-label">Registro</div><div class="info-value">${formatDate(l.fecha_registro)}</div></div>
                <div><div class="info-label">Cantidad Inicial</div><div class="info-value">${l.cantidad_inicial || 0} und</div></div>
                <div><div class="info-label">Stock Actual</div><div class="info-value"><strong>${l.stock_total || 0} und</strong></div></div>
                <div><div class="info-label">Costo Lote</div><div class="info-value">RD$ ${formatNum(l.costo_lote || 0)}</div></div>
                <div><div class="info-label">Código Barras</div><div class="info-value">${escapeHtml(l.codigo_barras || '-')}</div></div>
                <div><div class="info-label">Ubicación</div><div class="info-value">${escapeHtml(l.ubicacion || '-')}</div></div>
                <div><div class="info-label">Registrado Por</div><div class="info-value">${escapeHtml(l.usuario_registro || 'Sistema')}</div></div>
            </div>
            ${l.observaciones ? `<div style="margin-top:8px; padding-top:6px; border-top:1px solid #dee2e6;"><strong>Observaciones:</strong> ${escapeHtml(l.observaciones)}</div>` : ''}
        </div>
        
        <!-- STOCK POR SUCURSAL -->
        <div class="seccion">
            <div class="seccion-titulo">STOCK POR SUCURSAL</div>
            <table style="width:100%;">
                <thead><tr><th>Sucursal</th><th>Dirección</th><th>Cantidad</th><th>Teléfono</th></tr></thead>
                <tbody>${stockRows}</tbody>
                <tfoot><tr style="background:#e8f5e9;"><td colspan="2"><strong>TOTAL</strong></td><td style="text-align:center;"><strong>${l.stock_total || 0} und</strong></td><td></td></tr></tfoot>
            </table>
        </div>
        
        <!-- MOVIMIENTOS RECIENTES -->
        <div class="seccion">
            <div class="seccion-titulo">MOVIMIENTOS RECIENTES</div>
            <table style="width:100%;">
                <thead><tr><th>Tipo</th><th>Cantidad</th><th>Fecha</th><th>Motivo</th></tr></thead>
                <tbody>${movRows}</tbody>
            </table>
        </div>
        
        <div class="footer">
            Reporte generado el ${fechaGeneracion}
        </div>
    </body>
    </html>`;
    
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    
    const opt = {
        margin: [0.3, 0.3, 0.3, 0.3],
        filename: `lote_${l.id_lote}_${escapeHtml(l.numero_lote).replace(/[^a-zA-Z0-9]/g, '_')}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, letterRendering: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    
    Swal.fire({ title: 'Generando PDF...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    html2pdf().set(opt).from(element).save().then(() => {
        document.body.removeChild(element);
        Swal.fire({ icon: 'success', title: 'PDF generado', text: `Lote ${l.numero_lote} exportado`, timer: 1500, showConfirmButton: false });
    }).catch((err) => {
        document.body.removeChild(element);
        console.error(err);
        Swal.fire('Error', 'No se pudo generar el PDF', 'error');
    });
}

function editarDesdeDetalle() {
    if (detallesActualId) {
        modalDetalles.hide();
        editarLote(detallesActualId);
    }
}

function eliminarLote(id, numeroLote) {
    Swal.fire({
        title: '¿Eliminar lote?',
        html: `<p>¿Eliminar el lote <strong>${escapeHtml(numeroLote)}</strong>?</p><div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="confirmarEliminacion"><label class="form-check-label" for="confirmarEliminacion">Confirmar eliminación</label></div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Eliminar',
        preConfirm: () => document.getElementById('confirmarEliminacion')?.checked || Swal.showValidationMessage('Confirma la eliminación')
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Eliminando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            fetch(RUTAS_API.eliminar, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id_lote: id }) })
            .then(r => r.json())
            .then(data => { Swal.close(); if (data.success) { Swal.fire('¡Eliminado!', '', 'success'); cargarLotes(); actualizarEstadisticas(); } else { Swal.fire('Error', data.message, 'error'); } })
            .catch(() => { Swal.close(); Swal.fire('Error de conexión', '', 'error'); });
        }
    });
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('es-DO', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

function formatNum(n) { return parseFloat(n).toFixed(2).replace('.', ','); }
function escapeHtml(str) { if (!str) return ''; return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m])); }
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
.is-invalid { border-color: #dc3545 !important; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right calc(0.375em + 0.1875rem) center; background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem); }
.invalid-feedback { display: block; width: 100%; margin-top: 0.25rem; font-size: 0.875em; color: #dc3545; }
.pagination-custom { gap: 8px; }
.pagination-custom .page-item .page-link { border: none; border-radius: 10px; padding: 8px 16px; background: #f8f9fa; transition: all 0.3s; color: #555; }
.pagination-custom .page-item.active .page-link { background: #198754 !important; color: white; box-shadow: 0 4px 12px rgba(25,135,84,0.3); }
.pagination-custom .page-item:not(.active):hover .page-link { background: #e9ecef; color: #198754; transform: translateY(-2px); }
.badge.bg-success-subtle { background: rgba(25,135,84,0.1); color: #198754; }
.badge.bg-danger-subtle { background: rgba(220,53,69,0.1); color: #dc3545; }
.badge.bg-secondary-subtle { background: rgba(108,117,125,0.1); color: #6c757d; }
.badge.bg-info-subtle { background: rgba(13,202,240,0.1); color: #0dcaf0; }
.badge.bg-warning-subtle { background: rgba(255,193,7,0.1); color: #ffc107; }
.table-hover tbody tr:hover { background: rgba(25,135,84,0.05); }
.table thead th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: #6c757d; padding: 15px 12px; background: #f8f9fa; }
.form-control, .form-select { border: 1.5px solid #dee2e6 !important; border-radius: 10px; }
.form-control:focus, .form-select:focus { border-color: #198754 !important; box-shadow: 0 0 0 0.25rem rgba(25,135,84,0.1) !important; }
.btn-cancelar { background-color: #f1f3f5; color: #495057; border: 1.5px solid #dee2e6; border-radius: 10px; padding: 10px 25px; font-weight: 600; transition: all 0.2s ease; }
.btn-cancelar:hover { background-color: #e9ecef; border-color: #ced4da; color: #212529; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
.btn-light { background: #f8f9fa; border: none; width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; transition: all 0.2s; }
.btn-light:hover { transform: translateY(-2px); background: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important; }
.detalle-item { background-color: #f8f9fa; border: 1.5px solid #eceef0; border-radius: 12px; padding: 12px; height: 100%; transition: all 0.3s ease; }
.detalle-item:hover { background-color: #ffffff; border-color: #19875440; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
.bg-success-subtle { background: rgba(25,135,84,0.1); }
code { font-size: 0.85rem; background-color: #f8f9fa; padding: 2px 6px; border-radius: 4px; }
</style>