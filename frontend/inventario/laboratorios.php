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
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 text-success">
                <span class="material-symbols-rounded align-middle me-2">science</span>
                Gestión de Laboratorios
            </h2>
            <p class="text-muted mb-0">Administre los laboratorios farmacéuticos</p>
        </div>
        <div>
            <button type="button" class="btn btn-success shadow-sm" onclick="abrirModalNuevo()">
                <span class="material-symbols-rounded align-middle me-1">add</span>
                Nuevo Laboratorio
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
                            <h6 class="text-muted mb-1">Total Laboratorios</h6>
                            <h3 class="mb-0 text-success" id="statTotalLaboratorios">0</h3>
                            <small class="text-muted">Registrados en el sistema</small>
                        </div>
                        <span class="material-symbols-rounded text-success" style="font-size:40px;">science</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning bg-opacity-10 border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Con Medicamentos</h6>
                            <h3 class="mb-0 text-warning" id="statConMedicamentos">0</h3>
                            <small class="text-muted">Laboratorios con productos</small>
                        </div>
                        <span class="material-symbols-rounded text-warning" style="font-size:40px;">medication</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Sin Medicamentos</h6>
                            <h3 class="mb-0 text-info" id="statSinMedicamentos">0</h3>
                            <small class="text-muted">Laboratorios sin productos</small>
                        </div>
                        <span class="material-symbols-rounded text-info" style="font-size:40px;">inventory</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary bg-opacity-10 border-secondary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Activos</h6>
                            <h3 class="mb-0 text-secondary" id="statActivos">0</h3>
                            <small class="text-muted">Laboratorios activos</small>
                        </div>
                        <span class="material-symbols-rounded text-secondary" style="font-size:40px;">check_circle</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-bold text-secondary small">BUSCAR LABORATORIO</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <span class="material-symbols-rounded text-muted">search</span>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-0" id="buscarLaboratorio" placeholder="Nombre, país o contacto...">
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100 fw-bold" onclick="limpiarFiltros()">
                        <span class="material-symbols-rounded align-middle me-1">filter_list_off</span>
                        Quitar filtros
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLA DE LABORATORIOS -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaLaboratorios">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>LABORATORIO</th>
                            <th>PAÍS</th>
                            <th>CONTACTO</th>
                            <th>TELÉFONO</th>
                            <th>EMAIL</th>
                            <th class="text-center">MEDICAMENTOS</th>
                            <th class="text-center">ESTADO</th>
                            <th class="text-center">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody id="tablaLaboratoriosBody">
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <div class="spinner-border text-success" role="status"></div>
                                <p class="mt-2">Cargando laboratorios...</p>
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

<!-- MODAL PARA NUEVO/EDITAR LABORATORIO -->
<div class="modal fade" id="modalLaboratorio" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-success text-white p-4">
                <h5 class="modal-title d-flex align-items-center" id="modalTitulo">
                    <span class="material-symbols-rounded me-2">add_circle</span>
                    Nuevo Laboratorio
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                <form id="formLaboratorio">
                    <input type="hidden" id="laboratorioId">
                    
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-muted">NOMBRE DEL LABORATORIO *</label>
                            <input type="text" class="form-control form-control-lg border" id="nombreLaboratorio" required placeholder="Ej: Laboratorios ABC, Pharma International...">
                            <div class="invalid-feedback">El nombre del laboratorio es obligatorio</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">PAÍS *</label>
                            <input type="text" class="form-control border" id="paisLaboratorio" required placeholder="Ej: República Dominicana, Estados Unidos...">
                            <div class="invalid-feedback">El país es obligatorio</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">SITIO WEB</label>
                            <input type="url" class="form-control border" id="websiteLaboratorio" placeholder="https://www.ejemplo.com">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-bold text-muted">DIRECCIÓN *</label>
                            <textarea class="form-control border" id="direccionLaboratorio" rows="2" required placeholder="Dirección completa del laboratorio..."></textarea>
                            <div class="invalid-feedback">La dirección es obligatoria</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">TELÉFONO PRINCIPAL</label>
                            <input type="tel" class="form-control border" id="telefonoLaboratorio" placeholder="Ej: 809-555-0000">
                            <div class="invalid-feedback">Debe especificar al menos un número de teléfono</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">TELÉFONO SECUNDARIO</label>
                            <input type="tel" class="form-control border" id="telefono2Laboratorio" placeholder="Ej: 809-555-0001">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">EMAIL PRINCIPAL</label>
                            <input type="email" class="form-control border" id="emailLaboratorio" placeholder="contacto@laboratorio.com">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">EMAIL SECUNDARIO</label>
                            <input type="email" class="form-control border" id="email2Laboratorio" placeholder="ventas@laboratorio.com">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">NOMBRE DE CONTACTO</label>
                            <input type="text" class="form-control border" id="contactoNombreLaboratorio" placeholder="Nombre del representante">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">TELÉFONO DE CONTACTO</label>
                            <input type="tel" class="form-control border" id="contactoTelefonoLaboratorio" placeholder="Teléfono directo del contacto">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-bold text-muted">DESCRIPCIÓN</label>
                            <textarea class="form-control border" id="descripcionLaboratorio" rows="3" placeholder="Información adicional sobre el laboratorio..."></textarea>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-muted">ESTADO</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="activoLaboratorio" checked>
                                <label class="form-check-label" for="activoLaboratorio">
                                    <span class="badge bg-success" id="estadoActivo">Activo</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-end gap-3">
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success px-5 fw-bold shadow-sm" onclick="guardarLaboratorio()">Guardar Laboratorio</button>
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
                    <span class="material-symbols-rounded me-2">science</span>
                    Detalles del Laboratorio
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
const RUTAS_API = {
    listar: BASE_URL + '/backend/inventario/listar_laboratorios.php',
    guardar: BASE_URL + '/backend/inventario/guardar_laboratorio.php',
    eliminar: BASE_URL + '/backend/inventario/eliminar_laboratorio.php',
    detalle: BASE_URL + '/backend/inventario/detalle_laboratorio.php',
    estadisticas: BASE_URL + '/backend/inventario/estadisticas_laboratorios.php'
};

// ==================== VARIABLES GLOBALES ====================
let laboratoriosData = [];
let paginaActual = 1;
let filasPorPagina = 10;
let filtros = { busqueda: '' };
let detallesActualId = null;
let detallesActualData = null;

let modalLaboratorio, modalDetalles;

// ==================== INICIALIZACIÓN ====================
document.addEventListener('DOMContentLoaded', function() {
    const elLaboratorio = document.getElementById('modalLaboratorio');
    const elDetalles = document.getElementById('modalDetalles');
    
    if (elLaboratorio) {
        modalLaboratorio = new bootstrap.Modal(elLaboratorio, {
            backdrop: false,
            keyboard: true
        });
    }
    if (elDetalles) {
        modalDetalles = new bootstrap.Modal(elDetalles, {
            backdrop: false,
            keyboard: true
        });
    }
    
    // Switch de estado
    document.getElementById('activoLaboratorio').addEventListener('change', function() {
        const estado = this.checked ? 'Activo' : 'Inactivo';
        const color = this.checked ? 'bg-success' : 'bg-secondary';
        document.getElementById('estadoActivo').textContent = estado;
        document.getElementById('estadoActivo').className = `badge ${color}`;
    });
    
    const buscarInput = document.getElementById('buscarLaboratorio');
    let timeoutBusqueda;
    buscarInput.addEventListener('input', function() {
        clearTimeout(timeoutBusqueda);
        timeoutBusqueda = setTimeout(() => {
            filtros.busqueda = this.value;
            cargarLaboratorios();
        }, 500);
    });
    
    cargarLaboratorios();
    actualizarEstadisticas();
});

function abrirModalCentrado(modal) {
    modal.show();
}

function actualizarEstadisticas() {
    fetch(RUTAS_API.estadisticas)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statTotalLaboratorios').textContent = data.total_laboratorios || 0;
                document.getElementById('statConMedicamentos').textContent = data.con_medicamentos || 0;
                document.getElementById('statSinMedicamentos').textContent = data.sin_medicamentos || 0;
                document.getElementById('statActivos').textContent = data.activos || 0;
            }
        })
        .catch(error => console.error('Error actualizando estadísticas:', error));
}

function cargarLaboratorios() {
    const tbody = document.getElementById('tablaLaboratoriosBody');
    tbody.innerHTML = `<tr><td colspan="9" class="text-center"><div class="spinner-border text-success"></div><p>Cargando...</p></td></tr>`;
    
    let url = `${RUTAS_API.listar}?pagina=${paginaActual}&limite=${filasPorPagina}`;
    if (filtros.busqueda) url += `&busqueda=${encodeURIComponent(filtros.busqueda)}`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                laboratoriosData = data.laboratorios;
                renderizarTabla(laboratoriosData);
                actualizarPaginacion(data.total);
            } else {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error: ${data.message}</td></tr>`;
            }
        })
        .catch(() => {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error de conexión</td></tr>`;
        });
}

function renderizarTabla(laboratorios) {
    const tbody = document.getElementById('tablaLaboratoriosBody');
    if (!laboratorios || laboratorios.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted">No hay laboratorios registrados</td></tr>`;
        return;
    }
    
    let html = '';
    laboratorios.forEach(l => {
        const medicamentosBadge = l.total_medicamentos > 0 
            ? `<span class="badge bg-success-subtle text-success">${l.total_medicamentos} medicamentos</span>`
            : `<span class="badge bg-secondary-subtle text-secondary">Sin medicamentos</span>`;
        
        const estadoBadge = l.activo 
            ? `<span class="badge bg-success">Activo</span>`
            : `<span class="badge bg-secondary">Inactivo</span>`;
        
        html += `<tr>
            <td class="ps-4"><span class="text-success fw-bold">#${l.id_laboratorio}</span></td>
            <td><div class="fw-bold">${escapeHtml(l.nombre)}</div><small class="text-muted">${escapeHtml(l.descripcion ? l.descripcion.substring(0, 50) + (l.descripcion.length > 50 ? '...' : '') : '')}</small></td>
            <td>${l.pais ? `<span class="badge bg-info-subtle text-info">${escapeHtml(l.pais)}</span>` : '<span class="text-muted">-</span>'}</td>
            <td>${l.contacto_nombre ? escapeHtml(l.contacto_nombre) : '<span class="text-muted">-</span>'}</td>
            <td>${l.telefono ? escapeHtml(l.telefono) : (l.telefono2 ? escapeHtml(l.telefono2) : '<span class="text-muted">-</span>')}</td>
            <td>${l.email ? `<small>${escapeHtml(l.email)}</small>` : '<span class="text-muted">-</span>'}</td>
            <td class="text-center">${medicamentosBadge}</td>
            <td class="text-center">${estadoBadge}</td>
            <td class="text-center">
                <div class="d-flex justify-content-center gap-2">
                    <button class="btn btn-sm btn-light text-info shadow-sm" onclick="verDetalles(${l.id_laboratorio})" title="Ver">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                    <button class="btn btn-sm btn-light text-primary shadow-sm" onclick="editarLaboratorio(${l.id_laboratorio})" title="Editar">
                        <span class="material-symbols-rounded">edit_square</span>
                    </button>
                    <button class="btn btn-sm btn-light text-danger shadow-sm" onclick="eliminarLaboratorio(${l.id_laboratorio}, '${escapeHtml(l.nombre)}')" title="Eliminar">
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

function cambiarPagina(pagina) { paginaActual = pagina; cargarLaboratorios(); }

function limpiarFiltros() {
    document.getElementById('buscarLaboratorio').value = '';
    filtros = { busqueda: '' };
    paginaActual = 1;
    cargarLaboratorios();
}

function abrirModalNuevo() {
    document.getElementById('modalTitulo').innerHTML = '<span class="material-symbols-rounded me-2">add_circle</span> Nuevo Laboratorio';
    document.getElementById('formLaboratorio').reset();
    document.getElementById('laboratorioId').value = '';
    document.getElementById('activoLaboratorio').checked = true;
    document.getElementById('estadoActivo').textContent = 'Activo';
    document.getElementById('estadoActivo').className = 'badge bg-success';
    
    // Limpiar clases de validación
    const camposInvalidos = document.querySelectorAll('.is-invalid');
    camposInvalidos.forEach(campo => campo.classList.remove('is-invalid'));
    
    abrirModalCentrado(modalLaboratorio);
}

function editarLaboratorio(id) {
    Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch(`${RUTAS_API.detalle}?id=${id}`)
        .then(r => r.json())
        .then(data => {
            Swal.close();
            if (data.success && data.laboratorio) {
                const l = data.laboratorio;
                document.getElementById('modalTitulo').innerHTML = '<span class="material-symbols-rounded me-2">edit_square</span> Editar Laboratorio';
                document.getElementById('laboratorioId').value = l.id_laboratorio;
                document.getElementById('nombreLaboratorio').value = l.nombre;
                document.getElementById('paisLaboratorio').value = l.pais || '';
                document.getElementById('direccionLaboratorio').value = l.direccion || '';
                document.getElementById('telefonoLaboratorio').value = l.telefono || '';
                document.getElementById('telefono2Laboratorio').value = l.telefono2 || '';
                document.getElementById('emailLaboratorio').value = l.email || '';
                document.getElementById('email2Laboratorio').value = l.email2 || '';
                document.getElementById('contactoNombreLaboratorio').value = l.contacto_nombre || '';
                document.getElementById('contactoTelefonoLaboratorio').value = l.contacto_telefono || '';
                document.getElementById('websiteLaboratorio').value = l.website || '';
                document.getElementById('descripcionLaboratorio').value = l.descripcion || '';
                document.getElementById('activoLaboratorio').checked = l.activo;
                
                const estado = l.activo ? 'Activo' : 'Inactivo';
                const color = l.activo ? 'bg-success' : 'bg-secondary';
                document.getElementById('estadoActivo').textContent = estado;
                document.getElementById('estadoActivo').className = `badge ${color}`;
                
                // Limpiar clases de validación
                const camposInvalidos = document.querySelectorAll('.is-invalid');
                camposInvalidos.forEach(campo => campo.classList.remove('is-invalid'));
                
                abrirModalCentrado(modalLaboratorio);
            } else {
                Swal.fire('Error', data.message || 'No se pudo cargar el laboratorio', 'error');
            }
        })
        .catch(() => {
            Swal.close();
            Swal.fire('Error de conexión', '', 'error');
        });
}

function guardarLaboratorio() {
    const form = document.getElementById('formLaboratorio');
    
    // Limpiar clases de validación previas
    const camposInvalidos = document.querySelectorAll('.is-invalid');
    camposInvalidos.forEach(campo => campo.classList.remove('is-invalid'));
    
    // ==================== VALIDACIONES FRONTEND ====================
    let isValid = true;
    
    // Validar nombre
    const nombre = document.getElementById('nombreLaboratorio').value.trim();
    if (!nombre) {
        document.getElementById('nombreLaboratorio').classList.add('is-invalid');
        isValid = false;
    }
    
    // Validar país
    const pais = document.getElementById('paisLaboratorio').value.trim();
    if (!pais) {
        document.getElementById('paisLaboratorio').classList.add('is-invalid');
        isValid = false;
    }
    
    // Validar dirección
    const direccion = document.getElementById('direccionLaboratorio').value.trim();
    if (!direccion) {
        document.getElementById('direccionLaboratorio').classList.add('is-invalid');
        isValid = false;
    }
    
    // Validar que tenga al menos un teléfono
    const telefono = document.getElementById('telefonoLaboratorio').value.trim();
    const telefono2 = document.getElementById('telefono2Laboratorio').value.trim();
    if (!telefono && !telefono2) {
        document.getElementById('telefonoLaboratorio').classList.add('is-invalid');
        document.getElementById('telefono2Laboratorio').classList.add('is-invalid');
        isValid = false;
    }
    
    if (!isValid) {
        Swal.fire('Error', 'Por favor complete todos los campos obligatorios (*) y asegúrese de tener al menos un teléfono', 'error');
        return;
    }
    
    const datos = {
        id_laboratorio: document.getElementById('laboratorioId').value || null,
        nombre: nombre,
        pais: pais,
        direccion: direccion,
        telefono: telefono,
        telefono2: telefono2,
        email: document.getElementById('emailLaboratorio').value,
        email2: document.getElementById('email2Laboratorio').value,
        contacto_nombre: document.getElementById('contactoNombreLaboratorio').value,
        contacto_telefono: document.getElementById('contactoTelefonoLaboratorio').value,
        website: document.getElementById('websiteLaboratorio').value,
        descripcion: document.getElementById('descripcionLaboratorio').value,
        activo: document.getElementById('activoLaboratorio').checked ? 1 : 0
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
            Swal.fire({ icon: 'success', title: datos.id_laboratorio ? '¡Actualizado!' : '¡Creado!', text: 'Laboratorio guardado correctamente.', timer: 1500, showConfirmButton: false })
            .then(() => {
                modalLaboratorio.hide();
                cargarLaboratorios();
                actualizarEstadisticas();
            });
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(() => {
        Swal.close();
        Swal.fire('Error de conexión', '', 'error');
    });
}

function verDetalles(id) {
    detallesActualId = id;
    const modalBody = document.getElementById('detallesContenido');
    
    modalBody.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-success" role="status"></div><p class="mt-2">Cargando detalles...</p></div>`;
    
    fetch(`${RUTAS_API.detalle}?id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.laboratorio) {
                detallesActualData = data.laboratorio;
                const l = data.laboratorio;
                
                let medicamentosHtml = '';
                if (l.medicamentos_recientes && l.medicamentos_recientes.length > 0) {
                    medicamentosHtml = `
                        <div class="mt-4">
                            <h6 class="fw-bold text-muted mb-3">MEDICAMENTOS DE ESTE LABORATORIO</h6>
                            <div class="list-group">
                                ${l.medicamentos_recientes.map(m => `
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>${escapeHtml(m.nombre)}</strong>
                                            <small class="text-muted d-block">${escapeHtml(m.concentracion || '')} - ${escapeHtml(m.categoria || '')}</small>
                                        </div>
                                        <span class="badge bg-success">RD$ ${formatNum(m.precio || 0)}</span>
                                    </div>
                                `).join('')}
                            </div>
                            ${l.total_medicamentos > 5 ? `<p class="text-muted mt-2 small">... y ${l.total_medicamentos - 5} medicamentos más</p>` : ''}
                        </div>
                    `;
                } else {
                    medicamentosHtml = `<div class="alert alert-info mt-3 mb-0">No hay medicamentos registrados de este laboratorio</div>`;
                }
                
                modalBody.innerHTML = `
                    <div class="bg-success-subtle rounded-circle d-inline-flex p-4 mb-3">
                        <span class="material-symbols-rounded text-success" style="font-size: 3rem;">science</span>
                    </div>
                    <h3 class="fw-bold mb-1">${escapeHtml(l.nombre)}</h3>
                    <span class="badge bg-success-subtle text-success mb-4">#${l.id_laboratorio}</span>

                    <div class="row g-4 text-start">
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">País</small><span class="fw-bold text-dark">${escapeHtml(l.pais || 'No especificado')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Estado</small>${l.activo ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>'}</div></div>
                        <div class="col-12"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Dirección</small><span class="text-dark">${escapeHtml(l.direccion || 'No especificada')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Teléfono Principal</small><span class="fw-bold text-dark">${escapeHtml(l.telefono || 'No especificado')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Teléfono Secundario</small><span class="fw-bold text-dark">${escapeHtml(l.telefono2 || 'No especificado')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Email Principal</small><span class="fw-bold text-dark">${escapeHtml(l.email || 'No especificado')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Email Secundario</small><span class="fw-bold text-dark">${escapeHtml(l.email2 || 'No especificado')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Contacto</small><span class="fw-bold text-dark">${escapeHtml(l.contacto_nombre || 'No especificado')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Teléfono Contacto</small><span class="fw-bold text-dark">${escapeHtml(l.contacto_telefono || 'No especificado')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Sitio Web</small><span class="fw-bold text-dark">${l.website ? `<a href="${escapeHtml(l.website)}" target="_blank">${escapeHtml(l.website)}</a>` : 'No especificado'}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Total Medicamentos</small><span class="fw-bold text-success fs-5">${l.total_medicamentos || 0}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Categorías</small><span class="fw-bold text-dark">${l.total_categorias || 0}</span></div></div>
                        <div class="col-12"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Descripción</small><span class="text-dark">${escapeHtml(l.descripcion || 'Sin descripción')}</span></div></div>
                    </div>
                    ${medicamentosHtml}
                `;
                
                document.getElementById('btnEditarDesdeDetalle').style.display = 'inline-flex';
                abrirModalCentrado(modalDetalles);
            } else {
                Swal.fire('Error', data.message || 'No se pudo cargar los detalles', 'error');
            }
        })
        .catch(() => {
            Swal.fire('Error de conexión', 'No se pudo contactar el servidor', 'error');
        });
}

function exportarPDF() {
    if (!laboratoriosData || laboratoriosData.length === 0) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    let htmlContent = `
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Reporte de Laboratorios</title>
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
            <h1>Reporte de Laboratorios</h1>
            <p><strong>Fecha de exportación:</strong> ${new Date().toLocaleString()}</p>
            <p><strong>Total de laboratorios:</strong> ${laboratoriosData.length}</p>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>País</th>
                        <th>Contacto</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Medicamentos</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    laboratoriosData.forEach(l => {
        htmlContent += `
            <tr>
                <td>${l.id_laboratorio}</td>
                <td>${escapeHtml(l.nombre)}</td>
                <td>${escapeHtml(l.pais || '-')}</td>
                <td>${escapeHtml(l.contacto_nombre || '-')}</td>
                <td>${escapeHtml(l.telefono || l.telefono2 || '-')}</td>
                <td>${escapeHtml(l.email || '-')}</td>
                <td>${l.total_medicamentos || 0}</td>
                <td>${l.activo ? 'Activo' : 'Inactivo'}</td>
            </tr>
        `;
    });
    
    htmlContent += `
                </tbody>
            </table>
            <div class="footer">
                <p>Reporte generado por el Sistema Gestor de Farmacias</p>
            </div>
        </body>
        </html>
    `;
    
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    
    const opt = {
        margin: [0.5, 0.5, 0.5, 0.5],
        filename: `laboratorios_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, letterRendering: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    
    html2pdf().set(opt).from(element).save().then(() => {
        document.body.removeChild(element);
        Swal.fire({ icon: 'success', title: 'Exportado', text: `${laboratoriosData.length} laboratorios exportados a PDF`, timer: 2000, showConfirmButton: false });
    }).catch(() => {
        document.body.removeChild(element);
        Swal.fire('Error', 'Error al generar el PDF', 'error');
    });
}

function exportarIndividualPDF() {
    if (!detallesActualData) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    const l = detallesActualData;
    
    let htmlContent = `
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Detalle de Laboratorio</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #198754; text-align: center; }
                .card { border: 1px solid #ddd; border-radius: 10px; padding: 20px; margin-top: 20px; }
                .info-row { margin-bottom: 10px; }
                .label { font-weight: bold; display: inline-block; width: 180px; }
                .value { display: inline-block; }
                hr { margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <h1>Detalle de Laboratorio</h1>
            <div class="card">
                <div class="info-row"><span class="label">ID:</span><span class="value">${l.id_laboratorio}</span></div>
                <div class="info-row"><span class="label">Nombre:</span><span class="value">${escapeHtml(l.nombre)}</span></div>
                <div class="info-row"><span class="label">País:</span><span class="value">${escapeHtml(l.pais || 'No especificado')}</span></div>
                <div class="info-row"><span class="label">Dirección:</span><span class="value">${escapeHtml(l.direccion || 'No especificada')}</span></div>
                <div class="info-row"><span class="label">Teléfono Principal:</span><span class="value">${escapeHtml(l.telefono || 'No especificado')}</span></div>
                <div class="info-row"><span class="label">Teléfono Secundario:</span><span class="value">${escapeHtml(l.telefono2 || 'No especificado')}</span></div>
                <div class="info-row"><span class="label">Email Principal:</span><span class="value">${escapeHtml(l.email || 'No especificado')}</span></div>
                <div class="info-row"><span class="label">Email Secundario:</span><span class="value">${escapeHtml(l.email2 || 'No especificado')}</span></div>
                <div class="info-row"><span class="label">Contacto:</span><span class="value">${escapeHtml(l.contacto_nombre || 'No especificado')}</span></div>
                <div class="info-row"><span class="label">Teléfono Contacto:</span><span class="value">${escapeHtml(l.contacto_telefono || 'No especificado')}</span></div>
                <div class="info-row"><span class="label">Sitio Web:</span><span class="value">${escapeHtml(l.website || 'No especificado')}</span></div>
                <div class="info-row"><span class="label">Descripción:</span><span class="value">${escapeHtml(l.descripcion || 'Sin descripción')}</span></div>
                <div class="info-row"><span class="label">Total Medicamentos:</span><span class="value">${l.total_medicamentos || 0}</span></div>
                <div class="info-row"><span class="label">Categorías:</span><span class="value">${l.total_categorias || 0}</span></div>
                <div class="info-row"><span class="label">Estado:</span><span class="value">${l.activo ? 'Activo' : 'Inactivo'}</span></div>
            </div>
            <div class="footer">
                <p>Reporte generado por el Sistema Gestor de Farmacias</p>
            </div>
        </body>
        </html>
    `;
    
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    
    const opt = {
        margin: [0.5, 0.5, 0.5, 0.5],
        filename: `laboratorio_${l.id_laboratorio}_${l.nombre}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, letterRendering: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    
    html2pdf().set(opt).from(element).save().then(() => {
        document.body.removeChild(element);
        Swal.fire({ icon: 'success', title: 'Exportado', text: 'Laboratorio exportado a PDF', timer: 1500, showConfirmButton: false });
    }).catch(() => {
        document.body.removeChild(element);
        Swal.fire('Error', 'Error al generar el PDF', 'error');
    });
}

function editarDesdeDetalle() {
    if (detallesActualId) {
        modalDetalles.hide();
        editarLaboratorio(detallesActualId);
    }
}

function eliminarLaboratorio(id, nombre) {
    Swal.fire({
        title: '¿Eliminar laboratorio?',
        html: `<p>¿Eliminar <strong>${escapeHtml(nombre)}</strong>?</p><div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="confirmarEliminacion"><label class="form-check-label" for="confirmarEliminacion">Confirmar eliminación</label></div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Eliminar',
        preConfirm: () => document.getElementById('confirmarEliminacion')?.checked || Swal.showValidationMessage('Confirma la eliminación')
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Eliminando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            fetch(RUTAS_API.eliminar, { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/json' }, 
                body: JSON.stringify({ id_laboratorio: id }) 
            })
            .then(r => r.json())
            .then(data => { 
                Swal.close(); 
                if (data.success) { 
                    Swal.fire('¡Eliminado!', '', 'success');
                    cargarLaboratorios(); 
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
    });
}

function formatNum(n) { return parseFloat(n).toFixed(2).replace('.', ','); }
function escapeHtml(str) { if (!str) return ''; return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m])); }
</script>

<style>
/* ELIMINAR BACKDROP COMPLETAMENTE */
.modal-backdrop {
    display: none !important;
}

.modal {
    background-color: rgba(0, 0, 0, 0.5) !important;
    z-index: 1050;
}

/* Centrar modales */
.modal-dialog-centered {
    display: flex;
    align-items: center;
    min-height: calc(100% - 1rem);
}

.modal.show .modal-dialog {
    transform: none;
    margin: 1.75rem auto;
}

/* Estilos para campos inválidos */
.is-invalid {
    border-color: #dc3545 !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.is-invalid:focus {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

.invalid-feedback {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875em;
    color: #dc3545;
}

/* Responsive */
@media (max-width: 576px) {
    .modal-dialog {
        margin: 0.5rem;
    }
    .modal-body {
        padding: 1rem !important;
        max-height: 60vh !important;
    }
}

.pagination-custom { gap: 8px; }
.pagination-custom .page-item .page-link { border: none; border-radius: 10px; padding: 8px 16px; background: #f8f9fa; transition: all 0.3s; color: #555; }
.pagination-custom .page-item.active .page-link { background: #198754 !important; color: white; box-shadow: 0 4px 12px rgba(25,135,84,0.3); }
.pagination-custom .page-item:not(.active):hover .page-link { background: #e9ecef; color: #198754; transform: translateY(-2px); }

.badge.bg-success-subtle { background: rgba(25,135,84,0.1); color: #198754; }
.badge.bg-danger-subtle { background: rgba(220,53,69,0.1); color: #dc3545; }
.badge.bg-secondary-subtle { background: rgba(108,117,125,0.1); color: #6c757d; }
.badge.bg-info-subtle { background: rgba(13,202,240,0.1); color: #0dcaf0; }

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
</style>