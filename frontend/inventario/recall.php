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

// Obtener medicamentos para el filtro
$medicamentos_filtro = [];
try {
    $stmt = $conexion->query("SELECT id_medicamento, nombre_completo, nombre FROM medicamentos ORDER BY nombre");
    $medicamentos_filtro = $stmt->fetchAll();
} catch(PDOException $e) {}

// Niveles de riesgo
$niveles_riesgo = ['ALTO', 'MEDIO', 'BAJO'];
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 text-danger">
                <span class="material-symbols-rounded align-middle me-2">warning</span>
                Alertas Sanitarias (Recall)
            </h2>
            <p class="text-muted mb-0">Gestione alertas de medicamentos retirados del mercado por problemas de calidad o seguridad</p>
        </div>
        <div>
            <button type="button" class="btn btn-danger shadow-sm" onclick="abrirModalNuevaAlerta()">
                <span class="material-symbols-rounded align-middle me-1">add</span>
                Nueva Alerta
            </button>
            <button type="button" class="btn btn-outline-danger ms-2" onclick="exportarPDF()">
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
                            <h6 class="text-muted mb-1">Alertas Activas</h6>
                            <h3 class="mb-0 text-danger" id="statActivas">0</h3>
                            <small class="text-muted">En curso</small>
                        </div>
                        <span class="material-symbols-rounded text-danger" style="font-size:40px;">notification_important</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning bg-opacity-10 border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Alto Riesgo</h6>
                            <h3 class="mb-0 text-warning" id="statAlto">0</h3>
                            <small class="text-muted">Prioridad máxima</small>
                        </div>
                        <span class="material-symbols-rounded text-warning" style="font-size:40px;">emergency</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success bg-opacity-10 border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Resueltas</h6>
                            <h3 class="mb-0 text-success" id="statResueltas">0</h3>
                            <small class="text-muted">Finalizadas</small>
                        </div>
                        <span class="material-symbols-rounded text-success" style="font-size:40px;">check_circle</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Lotes Afectados</h6>
                            <h3 class="mb-0 text-info" id="statLotesAfectados">0</h3>
                            <small class="text-muted">Total</small>
                        </div>
                        <span class="material-symbols-rounded text-info" style="font-size:40px;">inventory</span>
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
                    <label class="form-label fw-bold text-secondary small">NIVEL RIESGO</label>
                    <select class="form-select" id="filtroRiesgo">
                        <option value="">Todos</option>
                        <option value="ALTO">Alto</option>
                        <option value="MEDIO">Medio</option>
                        <option value="BAJO">Bajo</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-secondary small">ESTADO</label>
                    <select class="form-select" id="filtroEstado">
                        <option value="">Todos</option>
                        <option value="ACTIVA">Activa</option>
                        <option value="EN_INVESTIGACION">En Investigación</option>
                        <option value="RESUELTA">Resuelta</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold text-secondary small">BUSCAR</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <span class="material-symbols-rounded text-muted">search</span>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-0" id="buscarAlerta" placeholder="Número alerta, entidad, medicamento, lote...">
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

    <!-- TABLA DE ALERTAS -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaAlertas">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">N° Alerta</th>
                            <th>Fecha</th>
                            <th>Entidad</th>
                            <th>Descripción</th>
                            <th>Nivel Riesgo</th>
                            <th>Estado</th>
                            <th class="text-center">Lotes</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaAlertasBody">
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <div class="spinner-border text-danger" role="status"></div>
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

<!-- MODAL PARA NUEVA/EDITAR ALERTA -->
<div class="modal fade" id="modalAlerta" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-danger text-white p-4">
                <h5 class="modal-title d-flex align-items-center" id="modalTitulo">
                    <span class="material-symbols-rounded me-2">add</span>
                    Nueva Alerta Sanitaria
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                <form id="formAlerta">
                    <input type="hidden" id="alertaId">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">NÚMERO ALERTA *</label>
                            <input type="text" class="form-control" id="numeroAlerta" required placeholder="Ej: ALERTA-2024-001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">FECHA NOTIFICACIÓN *</label>
                            <input type="date" class="form-control" id="fechaNotificacion" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">ENTIDAD EMISORA *</label>
                            <input type="text" class="form-control" id="entidadEmisora" required placeholder="Ej: DIGEMAPS, FDA, EMA...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">NIVEL DE RIESGO *</label>
                            <select class="form-select" id="nivelRiesgo" required>
                                <option value="">Seleccionar...</option>
                                <option value="ALTO">⚠️ Alto</option>
                                <option value="MEDIO">⚡ Medio</option>
                                <option value="BAJO">ℹ️ Bajo</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">ESTADO</label>
                            <select class="form-select" id="estadoAlerta">
                                <option value="ACTIVA">🟡 Activa</option>
                                <option value="EN_INVESTIGACION">🔵 En Investigación</option>
                                <option value="RESUELTA">🟢 Resuelta</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-muted">DESCRIPCIÓN *</label>
                            <textarea class="form-control" id="descripcionAlerta" rows="3" required placeholder="Describa el motivo de la alerta, lotes afectados, riesgos..."></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-muted">LOTES AFECTADOS</label>
                            <div class="table-responsive">
                                <table class="table table-sm" id="tablaLotesAlerta">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Medicamento</th>
                                            <th>Número Lote</th>
                                            <th>Vencimiento</th>
                                            <th>Stock Actual</th>
                                            <th style="width:40px"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="lotesAlertaBody">
                                        <tr id="filaLoteVacia">
                                            <td colspan="5" class="text-center text-muted">No hay lotes agregados</td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="5">
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="agregarLoteAlerta()">
                                                    <span class="material-symbols-rounded align-middle me-1" style="font-size:16px;">add</span>
                                                    Agregar lote
                                                </button>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-muted">DOCUMENTO URL</label>
                            <input type="url" class="form-control" id="documentoUrl" placeholder="https://...">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-end gap-3">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger px-5 fw-bold shadow-sm" onclick="guardarAlerta()">Guardar Alerta</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA VER DETALLES -->
<div class="modal fade" id="modalDetalles" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-danger text-white p-4">
                <h5 class="modal-title d-flex align-items-center">
                    <span class="material-symbols-rounded me-2">info</span>
                    Detalles de la Alerta
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detallesContenido">
                <div class="text-center py-5">
                    <div class="spinner-border text-danger" role="status"></div>
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';
const RUTAS_API = {
    listar: BASE_URL + '/backend/inventario/listar_recall.php',
    guardar: BASE_URL + '/backend/inventario/guardar_recall.php',
    detalle: BASE_URL + '/backend/inventario/detalle_recall.php',
    estadisticas: BASE_URL + '/backend/inventario/estadisticas_recall.php',
    listarLotes: BASE_URL + '/backend/inventario/listar_lotes_select.php'
};

// Variables globales
let alertasData = [];
let paginaActual = 1;
let filasPorPagina = 10;
let filtros = { fecha_desde: '', fecha_hasta: '', riesgo: '', estado: '', busqueda: '' };
let timeoutBusqueda;
let detallesActualId = null;
let detallesActualData = null;
let lotesAlerta = [];

let modalAlerta, modalDetalles;

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    const elAlerta = document.getElementById('modalAlerta');
    const elDetalles = document.getElementById('modalDetalles');
    
    if (elAlerta) {
        modalAlerta = new bootstrap.Modal(elAlerta, { backdrop: 'static', keyboard: true });
    }
    if (elDetalles) {
        modalDetalles = new bootstrap.Modal(elDetalles, { backdrop: 'static', keyboard: true });
    }
    
    // Fechas por defecto: últimos 90 días
    const hoy = new Date();
    const hace90Dias = new Date();
    hace90Dias.setDate(hoy.getDate() - 90);
    
    document.getElementById('filtroFechaDesde').value = hace90Dias.toISOString().split('T')[0];
    document.getElementById('filtroFechaHasta').value = hoy.toISOString().split('T')[0];
    document.getElementById('fechaNotificacion').value = hoy.toISOString().split('T')[0];
    
    filtros.fecha_desde = document.getElementById('filtroFechaDesde').value;
    filtros.fecha_hasta = document.getElementById('filtroFechaHasta').value;
    
    // Filtros automáticos
    document.getElementById('filtroFechaDesde').addEventListener('change', function() {
        filtros.fecha_desde = this.value;
        paginaActual = 1;
        cargarAlertas();
        actualizarEstadisticas();
    });
    
    document.getElementById('filtroFechaHasta').addEventListener('change', function() {
        filtros.fecha_hasta = this.value;
        paginaActual = 1;
        cargarAlertas();
        actualizarEstadisticas();
    });
    
    document.getElementById('filtroRiesgo').addEventListener('change', function() {
        filtros.riesgo = this.value;
        paginaActual = 1;
        cargarAlertas();
        actualizarEstadisticas();
    });
    
    document.getElementById('filtroEstado').addEventListener('change', function() {
        filtros.estado = this.value;
        paginaActual = 1;
        cargarAlertas();
        actualizarEstadisticas();
    });
    
    const buscarInput = document.getElementById('buscarAlerta');
    buscarInput.addEventListener('input', function() {
        clearTimeout(timeoutBusqueda);
        timeoutBusqueda = setTimeout(() => {
            filtros.busqueda = this.value;
            paginaActual = 1;
            cargarAlertas();
            actualizarEstadisticas();
        }, 500);
    });
    
    cargarLotesSelect();
    cargarAlertas();
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

function cargarLotesSelect() {
    fetch(RUTAS_API.listarLotes)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.lotes) {
                window.lotesDisponibles = data.lotes;
            }
        })
        .catch(error => console.error('Error cargando lotes:', error));
}

function agregarLoteAlerta() {
    if (!window.lotesDisponibles || window.lotesDisponibles.length === 0) {
        Swal.fire('Error', 'No hay lotes disponibles', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Agregar lote afectado',
        html: `
            <div class="mb-3">
                <label class="form-label">Lote</label>
                <select class="form-select" id="selectLoteAlerta">
                    <option value="">Seleccionar lote...</option>
                    ${window.lotesDisponibles.map(l => `<option value="${l.id_lote}" data-numero="${l.numero_lote}" data-medicamento="${l.medicamento_nombre}">${l.numero_lote} - ${l.medicamento_nombre}</option>`).join('')}
                </select>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Agregar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const loteSelect = document.getElementById('selectLoteAlerta');
            const idLote = loteSelect.value;
            
            if (!idLote) {
                Swal.showValidationMessage('Seleccione un lote');
                return false;
            }
            
            const lote = window.lotesDisponibles.find(l => l.id_lote == idLote);
            return { id_lote: idLote, numero_lote: lote.numero_lote, medicamento: lote.medicamento_nombre };
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            if (lotesAlerta.some(l => l.id_lote === result.value.id_lote)) {
                Swal.fire('Advertencia', 'Este lote ya está agregado', 'warning');
                return;
            }
            lotesAlerta.push(result.value);
            renderizarLotesAlerta();
        }
    });
}

function eliminarLoteAlerta(index) {
    lotesAlerta.splice(index, 1);
    renderizarLotesAlerta();
}

function renderizarLotesAlerta() {
    const tbody = document.getElementById('lotesAlertaBody');
    
    if (lotesAlerta.length === 0) {
        tbody.innerHTML = '<tr id="filaLoteVacia"><td colspan="5" class="text-center text-muted">No hay lotes agregados</td></tr>';
        return;
    }
    
    let html = '';
    lotesAlerta.forEach((l, index) => {
        html += `<tr>
            <td><strong>${escapeHtml(l.medicamento)}</strong></td>
            <td><code>${escapeHtml(l.numero_lote)}</code></td>
            <td>-</td>
            <td>-</td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="eliminarLoteAlerta(${index})"><span class="material-symbols-rounded">delete</span></button></td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function actualizarEstadisticas() {
    let url = `${RUTAS_API.estadisticas}?`;
    if (filtros.fecha_desde) url += `fecha_desde=${filtros.fecha_desde}&`;
    if (filtros.fecha_hasta) url += `fecha_hasta=${filtros.fecha_hasta}&`;
    if (filtros.riesgo) url += `riesgo=${filtros.riesgo}&`;
    if (filtros.estado) url += `estado=${filtros.estado}&`;
    if (filtros.busqueda) url += `busqueda=${encodeURIComponent(filtros.busqueda)}&`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statActivas').textContent = data.activas || 0;
                document.getElementById('statAlto').textContent = data.alto_riesgo || 0;
                document.getElementById('statResueltas').textContent = data.resueltas || 0;
                document.getElementById('statLotesAfectados').textContent = data.lotes_afectados || 0;
            }
        })
        .catch(error => console.error('Error:', error));
}

function cargarAlertas() {
    const tbody = document.getElementById('tablaAlertasBody');
    tbody.innerHTML = `<tr><td colspan="8" class="text-center"><div class="spinner-border text-danger"></div><p>Cargando...</p></td></tr>`;
    
    let url = `${RUTAS_API.listar}?pagina=${paginaActual}&limite=${filasPorPagina}`;
    if (filtros.fecha_desde) url += `&fecha_desde=${filtros.fecha_desde}`;
    if (filtros.fecha_hasta) url += `&fecha_hasta=${filtros.fecha_hasta}`;
    if (filtros.riesgo) url += `&riesgo=${filtros.riesgo}`;
    if (filtros.estado) url += `&estado=${filtros.estado}`;
    if (filtros.busqueda) url += `&busqueda=${encodeURIComponent(filtros.busqueda)}`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alertasData = data.alertas;
                renderizarTabla(alertasData);
                actualizarPaginacion(data.total);
            } else {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error: ${data.message}</td></tr>`;
            }
        })
        .catch(() => {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error de conexión</td></tr>`;
        });
}

function renderizarTabla(alertas) {
    const tbody = document.getElementById('tablaAlertasBody');
    if (!alertas || alertas.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted">No hay alertas registradas</td></tr>`;
        return;
    }
    
    let html = '';
    
    alertas.forEach(a => {
        let riesgoBadge = '';
        switch(a.nivel_riesgo) {
            case 'ALTO': riesgoBadge = '<span class="badge bg-danger">⚠️ ALTO</span>'; break;
            case 'MEDIO': riesgoBadge = '<span class="badge bg-warning text-dark">⚡ MEDIO</span>'; break;
            case 'BAJO': riesgoBadge = '<span class="badge bg-info">ℹ️ BAJO</span>'; break;
            default: riesgoBadge = '<span class="badge bg-secondary">' + a.nivel_riesgo + '</span>';
        }
        
        let estadoBadge = '';
        switch(a.estado) {
            case 'ACTIVA': estadoBadge = '<span class="badge bg-danger">ACTIVA</span>'; break;
            case 'EN_INVESTIGACION': estadoBadge = '<span class="badge bg-info">EN INVESTIGACIÓN</span>'; break;
            case 'RESUELTA': estadoBadge = '<span class="badge bg-success">RESUELTA</span>'; break;
            default: estadoBadge = '<span class="badge bg-secondary">' + a.estado + '</span>';
        }
        
        html += `<tr>
            <td class="ps-4"><code>${escapeHtml(a.numero_alerta)}</code></td>
            <td><small>${formatDate(a.fecha_notificacion)}</small></td>
            <td>${escapeHtml(a.entidad_emisora)}</td>
            <td><small>${escapeHtml(a.descripcion ? a.descripcion.substring(0, 60) : '-')}${a.descripcion && a.descripcion.length > 60 ? '...' : ''}</small></td>
            <td>${riesgoBadge}</td>
            <td>${estadoBadge}</td>
            <td class="text-center"><span class="badge bg-secondary">${a.total_lotes || 0}</span></td>
            <td class="text-center">
                <div class="d-flex justify-content-center gap-2">
                    <button class="btn btn-sm btn-light text-info" onclick="verDetalles(${a.id_alerta})" title="Ver">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                    <button class="btn btn-sm btn-light text-primary" onclick="editarAlerta(${a.id_alerta})" title="Editar">
                        <span class="material-symbols-rounded">edit_square</span>
                    </button>
                </div>
            </td>
         </tr>`;
    });
    tbody.innerHTML = html;
}

function abrirModalNuevaAlerta() {
    document.getElementById('modalTitulo').innerHTML = '<span class="material-symbols-rounded me-2">add</span> Nueva Alerta Sanitaria';
    document.getElementById('formAlerta').reset();
    document.getElementById('alertaId').value = '';
    document.getElementById('fechaNotificacion').value = new Date().toISOString().split('T')[0];
    document.getElementById('nivelRiesgo').value = '';
    document.getElementById('estadoAlerta').value = 'ACTIVA';
    lotesAlerta = [];
    renderizarLotesAlerta();
    modalAlerta.show();
}

function editarAlerta(id) {
    Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch(`${RUTAS_API.detalle}?id=${id}`)
        .then(r => r.json())
        .then(data => {
            Swal.close();
            if (data.success && data.alerta) {
                const a = data.alerta;
                document.getElementById('modalTitulo').innerHTML = '<span class="material-symbols-rounded me-2">edit</span> Editar Alerta';
                document.getElementById('alertaId').value = a.id_alerta;
                document.getElementById('numeroAlerta').value = a.numero_alerta;
                document.getElementById('fechaNotificacion').value = a.fecha_notificacion;
                document.getElementById('entidadEmisora').value = a.entidad_emisora;
                document.getElementById('nivelRiesgo').value = a.nivel_riesgo;
                document.getElementById('estadoAlerta').value = a.estado || 'ACTIVA';
                document.getElementById('descripcionAlerta').value = a.descripcion;
                document.getElementById('documentoUrl').value = a.documento_url || '';
                
                if (a.lotes && a.lotes.length > 0) {
                    lotesAlerta = a.lotes.map(l => ({
                        id_lote: l.id_lote,
                        numero_lote: l.numero_lote,
                        medicamento: l.medicamento_nombre
                    }));
                    renderizarLotesAlerta();
                }
                
                modalAlerta.show();
            } else {
                Swal.fire('Error', data.message || 'No se pudo cargar la alerta', 'error');
            }
        })
        .catch(() => {
            Swal.close();
            Swal.fire('Error de conexión', '', 'error');
        });
}

function guardarAlerta() {
    const idAlerta = document.getElementById('alertaId').value;
    const numeroAlerta = document.getElementById('numeroAlerta').value;
    const fechaNotificacion = document.getElementById('fechaNotificacion').value;
    const entidadEmisora = document.getElementById('entidadEmisora').value;
    const nivelRiesgo = document.getElementById('nivelRiesgo').value;
    const estado = document.getElementById('estadoAlerta').value;
    const descripcion = document.getElementById('descripcionAlerta').value;
    const documentoUrl = document.getElementById('documentoUrl').value;
    
    if (!numeroAlerta || !fechaNotificacion || !entidadEmisora || !nivelRiesgo || !descripcion) {
        Swal.fire('Error', 'Complete los campos requeridos', 'error');
        return;
    }
    
    const datos = {
        id_alerta: idAlerta || null,
        numero_alerta: numeroAlerta,
        fecha_notificacion: fechaNotificacion,
        entidad_emisora: entidadEmisora,
        nivel_riesgo: nivelRiesgo,
        estado: estado,
        descripcion: descripcion,
        documento_url: documentoUrl,
        lotes: lotesAlerta.map(l => ({ id_lote: l.id_lote }))
    };
    
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
            Swal.fire({ icon: 'success', title: datos.id_alerta ? '¡Actualizada!' : '¡Creada!', text: data.message, timer: 1500, showConfirmButton: false })
            .then(() => {
                modalAlerta.hide();
                cargarAlertas();
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
    modalBody.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-danger" role="status"></div><p class="mt-2">Cargando detalles...</p></div>`;
    
    fetch(`${RUTAS_API.detalle}?id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.alerta) {
                detallesActualData = data.alerta;
                const a = data.alerta;
                
                let riesgoBadge = '';
                switch(a.nivel_riesgo) {
                    case 'ALTO': riesgoBadge = '<span class="badge bg-danger">⚠️ ALTO</span>'; break;
                    case 'MEDIO': riesgoBadge = '<span class="badge bg-warning text-dark">⚡ MEDIO</span>'; break;
                    case 'BAJO': riesgoBadge = '<span class="badge bg-info">ℹ️ BAJO</span>'; break;
                    default: riesgoBadge = '<span class="badge bg-secondary">' + a.nivel_riesgo + '</span>';
                }
                
                let estadoBadge = '';
                switch(a.estado) {
                    case 'ACTIVA': estadoBadge = '<span class="badge bg-danger">ACTIVA</span>'; break;
                    case 'EN_INVESTIGACION': estadoBadge = '<span class="badge bg-info">EN INVESTIGACIÓN</span>'; break;
                    case 'RESUELTA': estadoBadge = '<span class="badge bg-success">RESUELTA</span>'; break;
                    default: estadoBadge = '<span class="badge bg-secondary">' + a.estado + '</span>';
                }
                
                let lotesHtml = '';
                if (a.lotes && a.lotes.length > 0) {
                    lotesHtml = `
                        <div class="mt-4">
                            <h6 class="fw-bold text-muted mb-3">LOTES AFECTADOS</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="bg-light">
                                        <tr><th>Medicamento</th><th>Número Lote</th><th>Vencimiento</th><th>Estado</th></tr>
                                    </thead>
                                    <tbody>
                                        ${a.lotes.map(l => `
                                            <tr>
                                                <td><strong>${escapeHtml(l.medicamento_nombre)}</strong>${l.concentracion ? `<br><small>${l.concentracion}</small>` : ''}</td>
                                                <td><code>${escapeHtml(l.numero_lote)}</code></td>
                                                <td>${formatDate(l.fecha_vencimiento)}</td>
                                                <td><span class="badge ${l.estado === 'ACTIVO' ? 'bg-success' : 'bg-danger'}">${l.estado}</span></td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                }
                
                modalBody.innerHTML = `
                    <div class="bg-danger-subtle rounded-circle d-inline-flex p-4 mb-3">
                        <span class="material-symbols-rounded text-danger" style="font-size: 3rem;">warning</span>
                    </div>
                    <h3 class="fw-bold mb-1">${escapeHtml(a.numero_alerta)}</h3>
                    <span class="badge bg-danger-subtle text-danger mb-4">#${a.id_alerta}</span>
                    
                    <div class="row g-4 text-start mt-2">
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Fecha Notificación</small><span class="fw-bold">${formatDate(a.fecha_notificacion)}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Entidad Emisora</small><span class="fw-bold">${escapeHtml(a.entidad_emisora)}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Nivel Riesgo</small><span>${riesgoBadge}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Estado</small><span>${estadoBadge}</span></div></div>
                        <div class="col-12"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Descripción</small><span>${escapeHtml(a.descripcion)}</span></div></div>
                        ${a.documento_url ? `<div class="col-12"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Documento</small><a href="${escapeHtml(a.documento_url)}" target="_blank">Ver documento</a></div></div>` : ''}
                    </div>
                    ${lotesHtml}
                `;
                
                document.getElementById('btnEditarDesdeDetalle').style.display = 'inline-flex';
                modalDetalles.show();
                scrollAlModal();
            } else {
                Swal.fire('Error', data.message || 'No se pudo cargar los detalles', 'error');
            }
        })
        .catch(() => {
            Swal.fire('Error de conexión', 'No se pudo contactar el servidor', 'error');
        });
}

function editarDesdeDetalle() {
    if (detallesActualId) {
        modalDetalles.hide();
        editarAlerta(detallesActualId);
    }
}

function exportarPDF() {
    if (!alertasData || alertasData.length === 0) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    const empresaNombre = '<?php echo addslashes($empresa_nombre ?? "Sistema Gestor de Farmacias"); ?>';
    
    let htmlContent = `
        <html>
        <head><meta charset="UTF-8"><title>Reporte de Alertas Sanitarias</title>
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
                <div class="titulo"><h1>REPORTE DE ALERTAS SANITARIAS (RECALL)</h1></div>
            </div>
            <table>
                <thead>
                    <tr><th>N° Alerta</th><th>Fecha</th><th>Entidad</th><th>Nivel Riesgo</th><th>Estado</th><th>Lotes</th></tr>
                </thead>
                <tbody>`;
    
    alertasData.forEach(a => {
        htmlContent += `<tr>
            <td>${escapeHtml(a.numero_alerta)}</td>
            <td>${formatDate(a.fecha_notificacion)}</td>
            <td>${escapeHtml(a.entidad_emisora)}</td>
            <td>${a.nivel_riesgo}</td>
            <td>${a.estado || 'ACTIVA'}</td>
            <td>${a.total_lotes || 0}</td>
        </tr>`;
    });
    
    htmlContent += `</tbody></table><div class="footer">Reporte generado el ${new Date().toLocaleString('es-DO')}</div></body></html>`;
    
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    
    const opt = { margin: [0.5, 0.5, 0.5, 0.5], filename: `recall_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' } };
    
    html2pdf().set(opt).from(element).save().then(() => { document.body.removeChild(element); Swal.fire({ icon: 'success', title: 'Exportado', timer: 1500, showConfirmButton: false }); }).catch(() => { document.body.removeChild(element); Swal.fire('Error', 'Error al generar el PDF', 'error'); });
}

function exportarIndividualPDF() {
    if (!detallesActualData) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    const a = detallesActualData;
    const empresaNombre = '<?php echo addslashes($empresa_nombre ?? "Sistema Gestor de Farmacias"); ?>';
    
    let lotesHtml = '';
    if (a.lotes && a.lotes.length > 0) {
        lotesHtml = `
            <h3 style="margin-top:20px;">Lotes Afectados</h3>
            <table style="width:100%; border-collapse:collapse; margin-top:10px;">
                <thead>
                    <tr style="background:#dc3545; color:white;">
                        <th style="padding:8px; text-align:left;">Medicamento</th>
                        <th style="padding:8px; text-align:left;">Número Lote</th>
                        <th style="padding:8px; text-align:left;">Vencimiento</th>
                        <th style="padding:8px; text-align:left;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    ${a.lotes.map(l => `
                        <tr>
                            <td style="padding:6px; border-bottom:1px solid #ddd;">${escapeHtml(l.medicamento_nombre)}${l.concentracion ? '<br><small>' + l.concentracion + '</small>' : ''}</td>
                            <td style="padding:6px; border-bottom:1px solid #ddd;">${escapeHtml(l.numero_lote)}</td>
                            <td style="padding:6px; border-bottom:1px solid #ddd;">${formatDate(l.fecha_vencimiento)}</td>
                            <td style="padding:6px; border-bottom:1px solid #ddd;">${l.estado}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }
    
    let estadoTexto = '';
    switch(a.estado) {
        case 'ACTIVA': estadoTexto = 'Activa'; break;
        case 'EN_INVESTIGACION': estadoTexto = 'En Investigación'; break;
        case 'RESUELTA': estadoTexto = 'Resuelta'; break;
        default: estadoTexto = a.estado;
    }
    
    const htmlContent = `<!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"><title>Alerta ${escapeHtml(a.numero_alerta)}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #dc3545; padding-bottom: 10px; }
        .empresa h2 { color: #dc3545; }
        .titulo { background: #dc3545; color: white; padding: 8px; text-align: center; border-radius: 5px; margin-bottom: 20px; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 20px; }
        .info-item { padding: 8px; background: #f8f9fa; border-radius: 5px; }
        .info-label { font-weight: bold; color: #dc3545; font-size: 10px; text-transform: uppercase; }
        .info-value { font-size: 12px; margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #dc3545; color: white; padding: 8px; text-align: left; }
        td { padding: 6px 8px; border-bottom: 1px solid #ddd; }
        .footer { text-align: center; margin-top: 30px; font-size: 10px; color: #666; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
    </head>
    <body>
        <div class="header">
            <div class="empresa"><h2>${empresaNombre}</h2></div>
            <div class="titulo"><h1>ALERTA SANITARIA (RECALL)</h1></div>
        </div>
        
        <div class="info-grid">
            <div class="info-item"><div class="info-label">Número Alerta</div><div class="info-value">${escapeHtml(a.numero_alerta)}</div></div>
            <div class="info-item"><div class="info-label">ID Alerta</div><div class="info-value">#${a.id_alerta}</div></div>
            <div class="info-item"><div class="info-label">Fecha Notificación</div><div class="info-value">${formatDate(a.fecha_notificacion)}</div></div>
            <div class="info-item"><div class="info-label">Entidad Emisora</div><div class="info-value">${escapeHtml(a.entidad_emisora)}</div></div>
            <div class="info-item"><div class="info-label">Nivel Riesgo</div><div class="info-value">${a.nivel_riesgo}</div></div>
            <div class="info-item"><div class="info-label">Estado</div><div class="info-value">${estadoTexto}</div></div>
            <div class="info-item"><div class="info-label">Descripción</div><div class="info-value">${escapeHtml(a.descripcion)}</div></div>
            ${a.documento_url ? `<div class="info-item"><div class="info-label">Documento</div><div class="info-value"><a href="${escapeHtml(a.documento_url)}" target="_blank">Ver documento</a></div></div>` : ''}
        </div>
        
        ${lotesHtml}
        
        <div class="footer">Reporte generado el ${new Date().toLocaleString('es-DO')}</div>
    </body>
    </html>`;
    
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    
    const opt = { margin: [0.4, 0.4, 0.4, 0.4], filename: `recall_${a.numero_alerta}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' } };
    
    Swal.fire({ title: 'Generando PDF...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    html2pdf().set(opt).from(element).save().then(() => { document.body.removeChild(element); Swal.fire({ icon: 'success', title: 'PDF generado', timer: 1500, showConfirmButton: false }); }).catch(() => { document.body.removeChild(element); Swal.fire('Error', 'Error al generar el PDF', 'error'); });
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

function limpiarFiltros() {
    const hoy = new Date();
    const hace90Dias = new Date();
    hace90Dias.setDate(hoy.getDate() - 90);
    
    document.getElementById('filtroFechaDesde').value = hace90Dias.toISOString().split('T')[0];
    document.getElementById('filtroFechaHasta').value = hoy.toISOString().split('T')[0];
    document.getElementById('filtroRiesgo').value = '';
    document.getElementById('filtroEstado').value = '';
    document.getElementById('buscarAlerta').value = '';
    
    filtros = { 
        fecha_desde: document.getElementById('filtroFechaDesde').value,
        fecha_hasta: document.getElementById('filtroFechaHasta').value,
        riesgo: '', 
        estado: '', 
        busqueda: '' 
    };
    paginaActual = 1;
    cargarAlertas();
    actualizarEstadisticas();
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
.pagination-custom { gap: 8px; }
.pagination-custom .page-item .page-link { border: none; border-radius: 10px; padding: 8px 16px; background: #f8f9fa; transition: all 0.3s; color: #555; }
.pagination-custom .page-item.active .page-link { background: #dc3545 !important; color: white; box-shadow: 0 4px 12px rgba(220,53,69,0.3); }
.pagination-custom .page-item:not(.active):hover .page-link { background: #e9ecef; color: #dc3545; transform: translateY(-2px); }
.table-hover tbody tr:hover { background: rgba(220,53,69,0.05); }
.table thead th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: #6c757d; padding: 15px 12px; background: #f8f9fa; }
.form-control, .form-select { border: 1.5px solid #dee2e6 !important; border-radius: 10px; }
.form-control:focus, .form-select:focus { border-color: #dc3545 !important; box-shadow: 0 0 0 0.25rem rgba(220,53,69,0.1) !important; }
.detalle-item { background-color: #f8f9fa; border: 1.5px solid #eceef0; border-radius: 12px; padding: 12px; height: 100%; }
.bg-danger-subtle { background: rgba(220,53,69,0.1); }
code { font-size: 0.85rem; background-color: #f8f9fa; padding: 2px 6px; border-radius: 4px; }
</style>