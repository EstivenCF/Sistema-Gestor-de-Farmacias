<?php
require_once __DIR__ . '/../../backend/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_sesion'])) {
    header("Location: ../index.php");
    exit();
}

$base_url = '/sistema-gestor-de-farmacias';
?>

<style>
    /* ===== ESTILOS (similar a medicamentos.php) ===== */
    .modal-backdrop { display: none !important; }
    .modal { background-color: rgba(0, 0, 0, 0.5) !important; z-index: 1050; }
    .modal-dialog-centered { display: flex; align-items: center; min-height: calc(100% - 1rem); }
    .modal.show .modal-dialog { transform: none; margin: 1.75rem auto; }
    
    .filtros-bar {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    
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
    .table thead th {
        background-color: #f8f9fa;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
        color: #6c757d;
        padding: 12px;
    }
    .form-control, .form-select {
        border: 1.5px solid #dee2e6 !important;
        border-radius: 10px;
    }
    .form-control:focus, .form-select:focus {
        border-color: #28a745 !important;
        box-shadow: 0 0 0 0.25rem rgba(40,167,69,0.1) !important;
    }
    .pagination-custom { gap: 8px; }
    .pagination-custom .page-item .page-link { border: none; border-radius: 10px; padding: 8px 16px; background: #f8f9fa; transition: all 0.3s; color: #555; }
    .pagination-custom .page-item.active .page-link { background: #198754 !important; color: white; }
    .pagination-custom .page-item:not(.active):hover .page-link { background: #e9ecef; color: #198754; transform: translateY(-2px); }
    
    .btn-light { background: #f8f9fa; border: none; width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; transition: all 0.2s; }
    .btn-light:hover { transform: translateY(-2px); background: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    
    .contacto-item {
        background: #f8f9fa;
        padding: 8px 12px;
        border-radius: 8px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .contacto-item .btn-sm {
        padding: 2px 6px;
        font-size: 0.7rem;
    }
    
    /* Botón exportar PDF */
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
</style>

<div class="dashboard-container">
    <div class="header-actions">
        <div>
            <h2 class="mb-0 text-success">
                <span class="material-symbols-rounded align-middle me-2">business</span>
                Gestión de Proveedores
            </h2>
            <p class="text-muted mb-0">Administre los proveedores de la farmacia</p>
        </div>
        <div>
            <button class="btn btn-success shadow-sm" onclick="abrirModalNuevo()">
                <span class="material-symbols-rounded align-middle me-1">add</span>
                Nuevo Proveedor
            </button>
            <button class="btn btn-export-pdf" onclick="exportarPDF()">
                <span class="material-symbols-rounded align-middle me-1">picture_as_pdf</span>
                Exportar a PDF
            </button>
        </div>
    </div>

    <!-- Tarjetas de estadísticas rápidas -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card-total text-center">
                <small>TOTAL PROVEEDORES</small>
                <h3 id="statTotalProveedores">0</h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-total text-center">
                <small>CON TELÉFONO</small>
                <h3 id="statConTelefono">0</h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-total text-center">
                <small>CON CORREO</small>
                <h3 id="statConCorreo">0</h3>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filtros-bar">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted">BUSCAR PROVEEDOR</label>
                <input type="text" class="form-control" id="buscarProveedor" placeholder="Nombre, RNC...">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">RNC</label>
                <input type="text" class="form-control" id="filtroRnc" placeholder="RNC">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">TELÉFONO</label>
                <input type="text" class="form-control" id="filtroTelefono" placeholder="Teléfono">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">CORREO</label>
                <input type="text" class="form-control" id="filtroEmail" placeholder="Email">
            </div>
            <div class="col-md-2">
                <button class="btn btn-quitar-filtros w-100" onclick="limpiarFiltros()">
                    <span class="material-symbols-rounded align-middle me-1">filter_list_off</span>
                    Quitar filtros
                </button>
            </div>
        </div>
    </div>

    <!-- Tabla de proveedores -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaProveedores">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>RNC</th>
                            <th>Dirección</th>
                            <th>Teléfonos</th>
                            <th>Correos</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaProveedoresBody">
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <div class="spinner-border text-success" role="status"></div>
                                <p class="mt-2">Cargando proveedores...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Paginación -->
    <div class="d-flex justify-content-center mt-4">
        <nav>
            <ul class="pagination pagination-custom" id="paginacion"></ul>
        </nav>
    </div>
</div>

<!-- MODAL PARA NUEVO/EDITAR PROVEEDOR (igual que antes) -->
<div class="modal fade" id="modalProveedor" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-success text-white p-4">
                <h5 class="modal-title d-flex align-items-center" id="modalTitulo">
                    <span class="material-symbols-rounded me-2">add_business</span>
                    Nuevo Proveedor
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                <form id="formProveedor">
                    <input type="hidden" id="proveedorId">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">NOMBRE *</label>
                            <input type="text" class="form-control" id="nombreProveedor" required>
                            <div class="invalid-feedback">El nombre es obligatorio</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">RNC</label>
                            <input type="text" class="form-control" id="rncProveedor" placeholder="Ej: 101234567">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">DIRECCIÓN</label>
                            <input type="text" class="form-control" id="direccionProveedor">
                        </div>
                        
                        <!-- Teléfonos -->
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">TELÉFONOS</label>
                            <div id="telefonosContainer">
                                <div class="contacto-item">
                                    <input type="text" class="form-control form-control-sm me-2" placeholder="Número" style="width: 40%;">
                                    <select class="form-select form-select-sm me-2" style="width: 25%;">
                                        <option value="PRINCIPAL">Principal</option>
                                        <option value="SECUNDARIO">Secundario</option>
                                        <option value="TRABAJO">Trabajo</option>
                                    </select>
                                    <div class="form-check form-switch me-2">
                                        <input class="form-check-input" type="checkbox" role="switch">
                                        <label class="form-check-label small">WhatsApp</label>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.contacto-item').remove()">
                                        <span class="material-symbols-rounded">delete</span>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success mt-2" onclick="agregarCampoTelefono()">
                                <span class="material-symbols-rounded align-middle me-1">add</span> Agregar teléfono
                            </button>
                        </div>
                        
                        <!-- Correos -->
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">CORREOS ELECTRÓNICOS</label>
                            <div id="correosContainer">
                                <div class="contacto-item">
                                    <input type="email" class="form-control form-control-sm me-2" placeholder="Email" style="width: 60%;">
                                    <select class="form-select form-select-sm me-2" style="width: 25%;">
                                        <option value="PRINCIPAL">Principal</option>
                                        <option value="SECUNDARIO">Secundario</option>
                                        <option value="TRABAJO">Trabajo</option>
                                    </select>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.contacto-item').remove()">
                                        <span class="material-symbols-rounded">delete</span>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success mt-2" onclick="agregarCampoCorreo()">
                                <span class="material-symbols-rounded align-middle me-1">add</span> Agregar correo
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-end gap-3">
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success px-5" onclick="guardarProveedor()">Guardar Proveedor</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA VER DETALLES -->
<div class="modal fade" id="modalDetalles" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-success text-white p-4">
                <h5 class="modal-title d-flex align-items-center">
                    <span class="material-symbols-rounded me-2">business_center</span>
                    Detalles del Proveedor
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detallesContenido">
                <div class="text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                    <p class="mt-2">Cargando detalles...</p>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-warning" id="btnEditarDesdeDetalle" onclick="editarDesdeDetalle()" style="display:none;">
                    <span class="material-symbols-rounded align-middle me-1">edit</span>
                    Editar
                </button>
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';
const API = {
    listar: BASE_URL + '/backend/compras/listar_proveedores.php',
    guardar: BASE_URL + '/backend/compras/guardar_proveedor.php',
    eliminar: BASE_URL + '/backend/compras/eliminar_proveedor.php',
    detalle: BASE_URL + '/backend/compras/detalle_proveedor.php',
    estadisticas: BASE_URL + '/backend/compras/estadisticas_proveedores.php'
};

let paginaActual = 1;
let filasPorPagina = 10;
let filtros = { busqueda: '', rnc: '', telefono: '', email: '' };
let proveedoresData = [];
let detallesActualId = null;
let modalProveedor, modalDetalles;

// ==================== INICIALIZACIÓN ====================
document.addEventListener('DOMContentLoaded', function() {
    const elProveedor = document.getElementById('modalProveedor');
    const elDetalles = document.getElementById('modalDetalles');
    if (elProveedor) modalProveedor = new bootstrap.Modal(elProveedor, { backdrop: false });
    if (elDetalles) modalDetalles = new bootstrap.Modal(elDetalles, { backdrop: false });
    
    // Eventos de filtros
    document.getElementById('buscarProveedor').addEventListener('input', function() {
        filtros.busqueda = this.value;
        paginaActual = 1;
        cargarProveedores();
    });
    document.getElementById('filtroRnc').addEventListener('input', function() {
        filtros.rnc = this.value;
        paginaActual = 1;
        cargarProveedores();
    });
    document.getElementById('filtroTelefono').addEventListener('input', function() {
        filtros.telefono = this.value;
        paginaActual = 1;
        cargarProveedores();
    });
    document.getElementById('filtroEmail').addEventListener('input', function() {
        filtros.email = this.value;
        paginaActual = 1;
        cargarProveedores();
    });
    
    cargarProveedores();
    actualizarEstadisticas();
});

function actualizarEstadisticas() {
    fetch(API.estadisticas)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statTotalProveedores').textContent = data.total || 0;
                document.getElementById('statConTelefono').textContent = data.con_telefono || 0;
                document.getElementById('statConCorreo').textContent = data.con_correo || 0;
            }
        })
        .catch(err => console.error('Error estadísticas:', err));
}

function cargarProveedores() {
    const tbody = document.getElementById('tablaProveedoresBody');
    tbody.innerHTML = `<tr><td colspan="7" class="text-center"><div class="spinner-border text-success"></div><p>Cargando...</p></td></tr>`;
    
    let url = `${API.listar}?pagina=${paginaActual}&limite=${filasPorPagina}`;
    if (filtros.busqueda) url += `&busqueda=${encodeURIComponent(filtros.busqueda)}`;
    if (filtros.rnc) url += `&rnc=${encodeURIComponent(filtros.rnc)}`;
    if (filtros.telefono) url += `&telefono=${encodeURIComponent(filtros.telefono)}`;
    if (filtros.email) url += `&email=${encodeURIComponent(filtros.email)}`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                proveedoresData = data.proveedores;
                renderizarTabla(proveedoresData);
                actualizarPaginacion(data.total);
            } else {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Error: ${data.message}</td></td>`;
            }
        })
        .catch(() => {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Error de conexión</td></tr>`;
        });
}

function renderizarTabla(proveedores) {
    const tbody = document.getElementById('tablaProveedoresBody');
    if (!proveedores.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">No hay proveedores registrados</td></tr>`;
        return;
    }
    let html = '';
    proveedores.forEach(p => {
        const telefonos = p.telefonos ? p.telefonos.map(t => `${t.numero} (${t.tipo})${t.whatsapp ? ' 📱' : ''}`).join('<br>') : '—';
        const correos = p.correos ? p.correos.map(c => `${c.email} (${c.tipo})`).join('<br>') : '—';
        html += `
            <tr>
                <td class="fw-bold text-success">#${p.id_proveedor}</td>
                <td class="fw-bold">${escapeHtml(p.nombre)}</td>
                <td>${escapeHtml(p.rnc || '—')}</td>
                <td>${escapeHtml(p.direccion || '—')}</td>
                <td>${telefonos}</td>
                <td>${correos}</td>
                <td class="text-center">
                    <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-sm btn-light text-info" onclick="verDetalles(${p.id_proveedor})" title="Ver">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                        <button class="btn btn-sm btn-light text-primary" onclick="editarProveedor(${p.id_proveedor})" title="Editar">
                            <span class="material-symbols-rounded">edit_square</span>
                        </button>
                        <button class="btn btn-sm btn-light text-danger" onclick="eliminarProveedor(${p.id_proveedor}, '${escapeHtml(p.nombre)}')" title="Eliminar">
                            <span class="material-symbols-rounded">delete</span>
                        </button>
                    </div>
                </td>
            </table>
        `;
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

function cambiarPagina(pagina) { paginaActual = pagina; cargarProveedores(); }
function limpiarFiltros() {
    document.getElementById('buscarProveedor').value = '';
    document.getElementById('filtroRnc').value = '';
    document.getElementById('filtroTelefono').value = '';
    document.getElementById('filtroEmail').value = '';
    filtros = { busqueda: '', rnc: '', telefono: '', email: '' };
    paginaActual = 1;
    cargarProveedores();
}

// ==================== GESTIÓN DE TELÉFONOS/CORREOS EN MODAL ====================
function agregarCampoTelefono() {
    const container = document.getElementById('telefonosContainer');
    const newItem = document.createElement('div');
    newItem.className = 'contacto-item';
    newItem.innerHTML = `
        <input type="text" class="form-control form-control-sm me-2" placeholder="Número" style="width: 40%;">
        <select class="form-select form-select-sm me-2" style="width: 25%;">
            <option value="PRINCIPAL">Principal</option>
            <option value="SECUNDARIO">Secundario</option>
            <option value="TRABAJO">Trabajo</option>
        </select>
        <div class="form-check form-switch me-2">
            <input class="form-check-input" type="checkbox" role="switch">
            <label class="form-check-label small">WhatsApp</label>
        </div>
        <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.contacto-item').remove()">
            <span class="material-symbols-rounded">delete</span>
        </button>
    `;
    container.appendChild(newItem);
}

function agregarCampoCorreo() {
    const container = document.getElementById('correosContainer');
    const newItem = document.createElement('div');
    newItem.className = 'contacto-item';
    newItem.innerHTML = `
        <input type="email" class="form-control form-control-sm me-2" placeholder="Email" style="width: 60%;">
        <select class="form-select form-select-sm me-2" style="width: 25%;">
            <option value="PRINCIPAL">Principal</option>
            <option value="SECUNDARIO">Secundario</option>
            <option value="TRABAJO">Trabajo</option>
        </select>
        <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.contacto-item').remove()">
            <span class="material-symbols-rounded">delete</span>
        </button>
    `;
    container.appendChild(newItem);
}

function recolectarContactos(containerId, tipo) {
    const items = document.querySelectorAll(`#${containerId} .contacto-item`);
    const resultados = [];
    items.forEach(item => {
        if (tipo === 'telefono') {
            const input = item.querySelector('input[type="text"]');
            const select = item.querySelector('select');
            const whatsappCheck = item.querySelector('input[type="checkbox"]');
            if (input && input.value.trim()) {
                resultados.push({
                    numero: input.value.trim(),
                    tipo: select ? select.value : 'PRINCIPAL',
                    whatsapp: whatsappCheck ? whatsappCheck.checked : false
                });
            }
        } else {
            const input = item.querySelector('input[type="email"]');
            const select = item.querySelector('select');
            if (input && input.value.trim()) {
                resultados.push({
                    email: input.value.trim(),
                    tipo: select ? select.value : 'PRINCIPAL'
                });
            }
        }
    });
    return resultados;
}

function limpiarContactosContainer(containerId, mantenerUnVacio = true) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    if (mantenerUnVacio) {
        if (containerId === 'telefonosContainer') {
            agregarCampoTelefono();
        } else {
            agregarCampoCorreo();
        }
    }
}

// ==================== CRUD ====================
function abrirModalNuevo() {
    document.getElementById('modalTitulo').innerHTML = '<span class="material-symbols-rounded me-2">add_business</span> Nuevo Proveedor';
    document.getElementById('formProveedor').reset();
    document.getElementById('proveedorId').value = '';
    limpiarContactosContainer('telefonosContainer', true);
    limpiarContactosContainer('correosContainer', true);
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    modalProveedor.show();
}

function editarProveedor(id) {
    Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch(`${API.detalle}?id=${id}`)
        .then(r => r.json())
        .then(data => {
            Swal.close();
            if (data.success && data.proveedor) {
                const p = data.proveedor;
                document.getElementById('modalTitulo').innerHTML = '<span class="material-symbols-rounded me-2">edit_square</span> Editar Proveedor';
                document.getElementById('proveedorId').value = p.id_proveedor;
                document.getElementById('nombreProveedor').value = p.nombre;
                document.getElementById('rncProveedor').value = p.rnc || '';
                document.getElementById('direccionProveedor').value = p.direccion || '';
                
                limpiarContactosContainer('telefonosContainer', false);
                if (p.telefonos && p.telefonos.length) {
                    p.telefonos.forEach(tel => {
                        const container = document.getElementById('telefonosContainer');
                        const newItem = document.createElement('div');
                        newItem.className = 'contacto-item';
                        newItem.innerHTML = `
                            <input type="text" class="form-control form-control-sm me-2" placeholder="Número" style="width: 40%;" value="${escapeHtml(tel.numero)}">
                            <select class="form-select form-select-sm me-2" style="width: 25%;">
                                <option value="PRINCIPAL" ${tel.tipo === 'PRINCIPAL' ? 'selected' : ''}>Principal</option>
                                <option value="SECUNDARIO" ${tel.tipo === 'SECUNDARIO' ? 'selected' : ''}>Secundario</option>
                                <option value="TRABAJO" ${tel.tipo === 'TRABAJO' ? 'selected' : ''}>Trabajo</option>
                            </select>
                            <div class="form-check form-switch me-2">
                                <input class="form-check-input" type="checkbox" role="switch" ${tel.whatsapp ? 'checked' : ''}>
                                <label class="form-check-label small">WhatsApp</label>
                            </div>
                            <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.contacto-item').remove()">
                                <span class="material-symbols-rounded">delete</span>
                            </button>
                        `;
                        container.appendChild(newItem);
                    });
                } else {
                    agregarCampoTelefono();
                }
                
                limpiarContactosContainer('correosContainer', false);
                if (p.correos && p.correos.length) {
                    p.correos.forEach(corr => {
                        const container = document.getElementById('correosContainer');
                        const newItem = document.createElement('div');
                        newItem.className = 'contacto-item';
                        newItem.innerHTML = `
                            <input type="email" class="form-control form-control-sm me-2" placeholder="Email" style="width: 60%;" value="${escapeHtml(corr.email)}">
                            <select class="form-select form-select-sm me-2" style="width: 25%;">
                                <option value="PRINCIPAL" ${corr.tipo === 'PRINCIPAL' ? 'selected' : ''}>Principal</option>
                                <option value="SECUNDARIO" ${corr.tipo === 'SECUNDARIO' ? 'selected' : ''}>Secundario</option>
                                <option value="TRABAJO" ${corr.tipo === 'TRABAJO' ? 'selected' : ''}>Trabajo</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.contacto-item').remove()">
                                <span class="material-symbols-rounded">delete</span>
                            </button>
                        `;
                        container.appendChild(newItem);
                    });
                } else {
                    agregarCampoCorreo();
                }
                
                modalProveedor.show();
            } else {
                Swal.fire('Error', data.message || 'No se pudo cargar el proveedor', 'error');
            }
        })
        .catch(() => { Swal.close(); Swal.fire('Error de conexión', '', 'error'); });
}

function guardarProveedor() {
    const id = document.getElementById('proveedorId').value;
    const nombre = document.getElementById('nombreProveedor').value.trim();
    if (!nombre) {
        Swal.fire('Error', 'El nombre del proveedor es obligatorio', 'error');
        document.getElementById('nombreProveedor').focus();
        return;
    }
    const rnc = document.getElementById('rncProveedor').value.trim();
    const direccion = document.getElementById('direccionProveedor').value.trim();
    const telefonos = recolectarContactos('telefonosContainer', 'telefono');
    const correos = recolectarContactos('correosContainer', 'correo');
    
    const datos = {
        id_proveedor: id ? parseInt(id) : null,
        nombre: nombre,
        rnc: rnc || null,
        direccion: direccion || null,
        telefonos: telefonos,
        correos: correos
    };
    
    Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch(API.guardar, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos)
    })
    .then(r => r.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire({ icon: 'success', title: id ? 'Actualizado' : 'Creado', text: 'Proveedor guardado correctamente', timer: 1500, showConfirmButton: false })
                .then(() => {
                    modalProveedor.hide();
                    cargarProveedores();
                    actualizarEstadisticas();
                });
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(() => { Swal.close(); Swal.fire('Error de conexión', '', 'error'); });
}

function verDetalles(id) {
    detallesActualId = id;
    const modalBody = document.getElementById('detallesContenido');
    modalBody.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando...</p></div>`;
    fetch(`${API.detalle}?id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.proveedor) {
                const p = data.proveedor;
                let telefonosHtml = '<ul class="list-unstyled mb-0">';
                if (p.telefonos && p.telefonos.length) {
                    p.telefonos.forEach(t => {
                        telefonosHtml += `<li><strong>${escapeHtml(t.numero)}</strong> (${t.tipo}) ${t.whatsapp ? '<span class="badge bg-info">WhatsApp</span>' : ''}</li>`;
                    });
                } else {
                    telefonosHtml += '<li class="text-muted">No registrados</li>';
                }
                telefonosHtml += '</ul>';
                
                let correosHtml = '<ul class="list-unstyled mb-0">';
                if (p.correos && p.correos.length) {
                    p.correos.forEach(c => {
                        correosHtml += `<li><strong>${escapeHtml(c.email)}</strong> (${c.tipo})</li>`;
                    });
                } else {
                    correosHtml += '<li class="text-muted">No registrados</li>';
                }
                correosHtml += '</ul>';
                
                modalBody.innerHTML = `
                    <div class="text-center mb-3">
                        <span class="material-symbols-rounded text-success" style="font-size: 4rem;">business</span>
                        <h3 class="fw-bold">${escapeHtml(p.nombre)}</h3>
                        <span class="badge bg-success-subtle text-success">#${p.id_proveedor}</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6"><div class="detalle-item"><small class="text-muted d-block fw-bold">RNC</small><span class="fw-bold">${escapeHtml(p.rnc || '—')}</span></div></div>
                        <div class="col-md-6"><div class="detalle-item"><small class="text-muted d-block fw-bold">Dirección</small><span class="fw-bold">${escapeHtml(p.direccion || '—')}</span></div></div>
                        <div class="col-12"><div class="detalle-item"><small class="text-muted d-block fw-bold">Teléfonos</small>${telefonosHtml}</div></div>
                        <div class="col-12"><div class="detalle-item"><small class="text-muted d-block fw-bold">Correos Electrónicos</small>${correosHtml}</div></div>
                    </div>
                `;
                document.getElementById('btnEditarDesdeDetalle').style.display = 'inline-flex';
                modalDetalles.show();
            } else {
                modalBody.innerHTML = '<div class="text-center text-danger">Error al cargar detalles</div>';
            }
        })
        .catch(() => modalBody.innerHTML = '<div class="text-center text-danger">Error de conexión</div>');
}

function editarDesdeDetalle() {
    if (detallesActualId) {
        modalDetalles.hide();
        editarProveedor(detallesActualId);
    }
}

function eliminarProveedor(id, nombre) {
    Swal.fire({
        title: '¿Eliminar proveedor?',
        html: `<p>¿Eliminar <strong>${escapeHtml(nombre)}</strong>?</p><div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="confirmarEliminacion"><label class="form-check-label" for="confirmarEliminacion">Confirmar eliminación</label></div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Eliminar',
        preConfirm: () => document.getElementById('confirmarEliminacion')?.checked || Swal.showValidationMessage('Confirma la eliminación')
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Eliminando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            fetch(API.eliminar, { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/json' }, 
                body: JSON.stringify({ id_proveedor: id }) 
            })
            .then(r => r.json())
            .then(data => { 
                Swal.close(); 
                if (data.success) { 
                    Swal.fire('¡Eliminado!', '', 'success');
                    cargarProveedores(); 
                    actualizarEstadisticas();
                } else { 
                    Swal.fire('Error', data.message, 'error'); 
                } 
            })
            .catch(() => { Swal.close(); Swal.fire('Error de conexión', '', 'error'); });
        }
    });
}

// ==================== EXPORTAR A PDF ====================
function exportarPDF() {
    const tabla = document.getElementById('tablaProveedores');
    if (!tabla || !document.getElementById('tablaProveedoresBody').children.length) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    // Obtener filtros actuales para mostrarlos en el PDF
    const busqueda = document.getElementById('buscarProveedor').value || 'Sin búsqueda';
    const rnc = document.getElementById('filtroRnc').value || 'Todos';
    const telefono = document.getElementById('filtroTelefono').value || 'Todos';
    const email = document.getElementById('filtroEmail').value || 'Todos';
    
    let htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Reporte de Proveedores</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }
                h1 { color: #28a745; text-align: center; margin-bottom: 5px; }
                .filters { text-align: center; margin-bottom: 20px; font-size: 10px; color: #555; }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
                th { background-color: #28a745; color: white; font-weight: bold; }
                .footer { margin-top: 20px; text-align: right; font-size: 9px; color: #888; }
            </style>
        </head>
        <body>
            <h1>Reporte de Proveedores</h1>
            <div class="filters">
                Búsqueda: ${escapeHtml(busqueda)} | RNC: ${escapeHtml(rnc)} | Teléfono: ${escapeHtml(telefono)} | Correo: ${escapeHtml(email)}
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>RNC</th>
                        <th>Dirección</th>
                        <th>Teléfonos</th>
                        <th>Correos</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    const rows = document.querySelectorAll('#tablaProveedoresBody tr');
    rows.forEach(row => {
        if (row.querySelector('.text-muted')) return;
        const cells = row.querySelectorAll('td');
        if (cells.length >= 6) {
            const id = cells[0]?.innerText || '';
            const nombre = cells[1]?.innerText || '';
            const rncTxt = cells[2]?.innerText || '—';
            const direccion = cells[3]?.innerText || '—';
            const telefonos = cells[4]?.innerHTML.replace(/<br>/g, ', ') || '—';
            const correos = cells[5]?.innerHTML.replace(/<br>/g, ', ') || '—';
            htmlContent += `
                <tr>
                    <td>${escapeHtml(id)}</td>
                    <td>${escapeHtml(nombre)}</td>
                    <td>${escapeHtml(rncTxt)}</td>
                    <td>${escapeHtml(direccion)}</td>
                    <td>${escapeHtml(telefonos)}</td>
                    <td>${escapeHtml(correos)}</td>
                </tr>
            `;
        }
    });
    
    htmlContent += `
                </tbody>
            </table>
            <div class="footer">
                Reporte generado el ${new Date().toLocaleString()} por ${'<?php echo $_SESSION['usuario'] ?? 'Sistema'; ?>'}
            </div>
        </body>
        </html>
    `;
    
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    
    const opt = {
        margin: [0.5, 0.5, 0.5, 0.5],
        filename: `proveedores_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`,
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