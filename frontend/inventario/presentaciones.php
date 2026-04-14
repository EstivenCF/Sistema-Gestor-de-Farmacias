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

// Obtener unidades de medida para el select
$unidades_medida = [];
$categorias = [];

try {
    $stmt = $conexion->query("SELECT id_unidad, nombre, abreviatura FROM unidades_medida ORDER BY nombre");
    $unidades_medida = $stmt->fetchAll();
    
    $stmt = $conexion->query("SELECT id_categoria, nombre, descripcion FROM categorias WHERE activo = true ORDER BY nombre");
    $categorias = $stmt->fetchAll();
} catch(PDOException $e) {}

$base_url = '/Sistema-Gestor-de-Farmacias';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 text-success">
                <span class="material-symbols-rounded align-middle me-2">inventory_2</span>
                Gestión de Presentaciones
            </h2>
            <p class="text-muted mb-0">Administre las presentaciones de medicamentos (tabletas, cápsulas, jarabe...)</p>
        </div>
        <div>
            <button type="button" class="btn btn-success shadow-sm" onclick="abrirModalNuevo()">
                <span class="material-symbols-rounded align-middle me-1">add</span>
                Nueva Presentación
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
                            <h6 class="text-muted mb-1">Total Presentaciones</h6>
                            <h3 class="mb-0 text-success" id="statTotalPresentaciones">0</h3>
                            <small class="text-muted">Registradas en el sistema</small>
                        </div>
                        <span class="material-symbols-rounded text-success" style="font-size:40px;">inventory_2</span>
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
                            <small class="text-muted">Presentaciones en uso</small>
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
                            <small class="text-muted">Presentaciones vacías</small>
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
                            <h6 class="text-muted mb-1">Con Unidad</h6>
                            <h3 class="mb-0 text-secondary" id="statConUnidad">0</h3>
                            <small class="text-muted">Tienen unidad asignada</small>
                        </div>
                        <span class="material-symbols-rounded text-secondary" style="font-size:40px;">straighten</span>
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
                    <label class="form-label fw-bold text-secondary small">BUSCAR PRESENTACIÓN</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <span class="material-symbols-rounded text-muted">search</span>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-0" id="buscarPresentacion" placeholder="Nombre de presentación...">
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

    <!-- TABLA DE PRESENTACIONES -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaPresentaciones">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>PRESENTACIÓN</th>
                            <th>DESCRIPCIÓN</th>
                            <th>UNIDAD</th>
                            <th class="text-center">MEDICAMENTOS</th>
                            <th class="text-center">ESTADO</th>
                            <th class="text-center">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody id="tablaPresentacionesBody">
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <div class="spinner-border text-success" role="status"></div>
                                <p class="mt-2">Cargando presentaciones...</p>
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

<!-- MODAL PARA NUEVO/EDITAR PRESENTACIÓN CON CHECKBOXES -->
<div class="modal fade" id="modalPresentacion" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-success text-white p-4">
                <h5 class="modal-title d-flex align-items-center" id="modalTitulo">
                    <span class="material-symbols-rounded me-2">add_circle</span>
                    Nueva Presentación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                <form id="formPresentacion">
                    <input type="hidden" id="presentacionId">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted">NOMBRE DE LA PRESENTACIÓN *</label>
                        <input type="text" class="form-control form-control-lg border" id="nombrePresentacion" required placeholder="Ej: Tabletas, Cápsulas, Jarabe, Inyección, Gotas...">
                        <div class="invalid-feedback">El nombre de la presentación es obligatorio</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted">UNIDAD DE MEDIDA</label>
                        <select class="form-select form-control-lg border" id="unidadPresentacion">
                            <option value="">Seleccionar unidad...</option>
                            <?php foreach ($unidades_medida as $uni): ?>
                                <option value="<?php echo $uni['id_unidad']; ?>"><?php echo htmlspecialchars($uni['nombre']); ?> (<?php echo htmlspecialchars($uni['abreviatura']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted">Ej: Tabletas → Unidad: Tabletas, Jarabe → Unidad: Mililitros</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted mb-3">CATEGORÍAS ASIGNADAS *</label>
                        <div class="border rounded-3 p-3 bg-light" style="max-height: 280px; overflow-y: auto;">
                            <div id="categoriasCheckboxes" class="row g-3">
                                <!-- Aquí se cargarán los checkboxes dinámicamente -->
                                <div class="col-12 text-center text-muted py-3">
                                    <div class="spinner-border spinner-border-sm text-success" role="status"></div>
                                    Cargando categorías...
                                </div>
                            </div>
                        </div>
                        <div class="form-text text-muted mt-2">Seleccione las categorías donde esta presentación puede ser utilizada</div>
                        <div class="invalid-feedback">Debe seleccionar al menos una categoría para esta presentación</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted">DESCRIPCIÓN</label>
                        <textarea class="form-control border" id="descripcionPresentacion" rows="3" placeholder="Describa las características de esta presentación..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted">ESTADO</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="activoPresentacion" checked>
                            <label class="form-check-label" for="activoPresentacion">
                                <span class="badge bg-success" id="estadoActivo">Activo</span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-end gap-3">
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success px-5 fw-bold shadow-sm" onclick="guardarPresentacion()">Guardar Presentación</button>
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
                    <span class="material-symbols-rounded me-2">inventory_2</span>
                    Detalles de la Presentación
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
    listar: BASE_URL + '/backend/inventario/listar_presentaciones.php',
    guardar: BASE_URL + '/backend/inventario/guardar_presentacion.php',
    eliminar: BASE_URL + '/backend/inventario/eliminar_presentacion.php',
    detalle: BASE_URL + '/backend/inventario/detalle_presentacion.php',
    estadisticas: BASE_URL + '/backend/inventario/estadisticas_presentaciones.php',
    categorias: BASE_URL + '/backend/inventario/listar_categorias_todas.php'
};

// ==================== VARIABLES GLOBALES ====================
let presentacionesData = [];
let paginaActual = 1;
let filasPorPagina = 10;
let filtros = { busqueda: '' };
let detallesActualId = null;
let detallesActualData = null;

let modalPresentacion, modalDetalles;

// ==================== INICIALIZACIÓN ====================
document.addEventListener('DOMContentLoaded', function() {
    const elPresentacion = document.getElementById('modalPresentacion');
    const elDetalles = document.getElementById('modalDetalles');
    
    if (elPresentacion) {
        modalPresentacion = new bootstrap.Modal(elPresentacion, {
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
    document.getElementById('activoPresentacion').addEventListener('change', function() {
        const estado = this.checked ? 'Activo' : 'Inactivo';
        const color = this.checked ? 'bg-success' : 'bg-secondary';
        document.getElementById('estadoActivo').textContent = estado;
        document.getElementById('estadoActivo').className = `badge ${color}`;
    });
    
    const buscarInput = document.getElementById('buscarPresentacion');
    let timeoutBusqueda;
    buscarInput.addEventListener('input', function() {
        clearTimeout(timeoutBusqueda);
        timeoutBusqueda = setTimeout(() => {
            filtros.busqueda = this.value;
            cargarPresentaciones();
        }, 500);
    });
    
    cargarPresentaciones();
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
                document.getElementById('statTotalPresentaciones').textContent = data.total_presentaciones || 0;
                document.getElementById('statConMedicamentos').textContent = data.con_medicamentos || 0;
                document.getElementById('statSinMedicamentos').textContent = data.sin_medicamentos || 0;
                document.getElementById('statConUnidad').textContent = data.con_unidad || 0;
            }
        })
        .catch(error => console.error('Error actualizando estadísticas:', error));
}

function cargarPresentaciones() {
    const tbody = document.getElementById('tablaPresentacionesBody');
    tbody.innerHTML = `<tr><td colspan="7" class="text-center"><div class="spinner-border text-success"></div><p>Cargando...</p></td></tr>`;
    
    let url = `${RUTAS_API.listar}?pagina=${paginaActual}&limite=${filasPorPagina}`;
    if (filtros.busqueda) url += `&busqueda=${encodeURIComponent(filtros.busqueda)}`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                presentacionesData = data.presentaciones;
                renderizarTabla(presentacionesData);
                actualizarPaginacion(data.total);
            } else {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Error: ${data.message}</td></tr>`;
            }
        })
        .catch(() => {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Error de conexión</td></tr>`;
        });
}

function renderizarTabla(presentaciones) {
    const tbody = document.getElementById('tablaPresentacionesBody');
    if (!presentaciones || presentaciones.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">No hay presentaciones registradas</td></tr>`;
        return;
    }
    
    let html = '';
    presentaciones.forEach(p => {
        const medicamentosBadge = p.total_medicamentos > 0 
            ? `<span class="badge bg-success-subtle text-success">${p.total_medicamentos} medicamentos</span>`
            : `<span class="badge bg-secondary-subtle text-secondary">Sin medicamentos</span>`;
        
        const estadoBadge = p.activo 
            ? `<span class="badge bg-success">Activo</span>`
            : `<span class="badge bg-secondary">Inactivo</span>`;
        
        const unidadTexto = p.unidad_nombre ? `${p.unidad_nombre} (${p.unidad_abrev})` : '<span class="text-muted">Sin unidad</span>';
        
        html += `<tr>
            <td class="ps-4"><span class="text-success fw-bold">#${p.id_presentacion}</span></td>
            <td><div class="fw-bold">${escapeHtml(p.nombre)}</div></td>
            <td><small class="text-muted">${escapeHtml(p.descripcion ? (p.descripcion.length > 80 ? p.descripcion.substring(0, 80) + '...' : p.descripcion) : 'Sin descripción')}</small></td>
            <td>${unidadTexto}</td>
            <td class="text-center">${medicamentosBadge}</td>
            <td class="text-center">${estadoBadge}</td>
            <td class="text-center">
                <div class="d-flex justify-content-center gap-2">
                    <button class="btn btn-sm btn-light text-info shadow-sm" onclick="verDetalles(${p.id_presentacion})" title="Ver">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                    <button class="btn btn-sm btn-light text-primary shadow-sm" onclick="editarPresentacion(${p.id_presentacion})" title="Editar">
                        <span class="material-symbols-rounded">edit_square</span>
                    </button>
                    <button class="btn btn-sm btn-light text-danger shadow-sm" onclick="eliminarPresentacion(${p.id_presentacion}, '${escapeHtml(p.nombre)}')" title="Eliminar">
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

function cambiarPagina(pagina) { paginaActual = pagina; cargarPresentaciones(); }

function limpiarFiltros() {
    document.getElementById('buscarPresentacion').value = '';
    filtros = { busqueda: '' };
    paginaActual = 1;
    cargarPresentaciones();
}

// ==================== FUNCIONES PARA CHECKBOXES DE CATEGORÍAS ====================

function cargarCategoriasCheckboxes(selectedIds = []) {
    fetch(RUTAS_API.categorias)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.categorias) {
                const container = document.getElementById('categoriasCheckboxes');
                if (data.categorias.length === 0) {
                    container.innerHTML = '<div class="col-12 text-center text-muted">No hay categorías disponibles. Cree una primero.</div>';
                    return;
                }
                
                let html = '';
                data.categorias.forEach(cat => {
                    const isChecked = selectedIds.includes(cat.id_categoria);
                    html += `
                        <div class="col-md-6">
                            <div class="form-check py-1">
                                <input class="form-check-input categoria-checkbox" type="checkbox" 
                                       value="${cat.id_categoria}" id="cat_${cat.id_categoria}" 
                                       ${isChecked ? 'checked' : ''}>
                                <label class="form-check-label fw-medium" for="cat_${cat.id_categoria}">
                                    ${escapeHtml(cat.nombre)}
                                    ${cat.descripcion ? `<small class="text-muted d-block">${escapeHtml(cat.descripcion.substring(0, 60))}${cat.descripcion.length > 60 ? '...' : ''}</small>` : ''}
                                </label>
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html;
                
                // Remover clase is-invalid si hay selección
                const checkboxes = document.querySelectorAll('.categoria-checkbox');
                checkboxes.forEach(cb => {
                    cb.addEventListener('change', function() {
                        const anyChecked = document.querySelectorAll('.categoria-checkbox:checked').length > 0;
                        if (anyChecked) {
                            document.getElementById('categoriasCheckboxes').parentElement.classList.remove('is-invalid');
                        }
                    });
                });
            } else {
                document.getElementById('categoriasCheckboxes').innerHTML = 
                    '<div class="col-12 text-center text-danger">Error cargando categorías</div>';
            }
        })
        .catch(error => {
            console.error('Error cargando categorías:', error);
            document.getElementById('categoriasCheckboxes').innerHTML = 
                '<div class="col-12 text-center text-danger">Error de conexión</div>';
        });
}

function getCategoriasSeleccionadas() {
    const checkboxes = document.querySelectorAll('.categoria-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

// ==================== CRUD FUNCTIONS ====================

function abrirModalNuevo() {
    document.getElementById('modalTitulo').innerHTML = '<span class="material-symbols-rounded me-2">add_circle</span> Nueva Presentación';
    document.getElementById('formPresentacion').reset();
    document.getElementById('presentacionId').value = '';
    document.getElementById('activoPresentacion').checked = true;
    document.getElementById('estadoActivo').textContent = 'Activo';
    document.getElementById('estadoActivo').className = 'badge bg-success';
    
    // Cargar categorías sin ninguna seleccionada
    cargarCategoriasCheckboxes([]);
    
    // Limpiar clases de validación
    const camposInvalidos = document.querySelectorAll('.is-invalid');
    camposInvalidos.forEach(campo => campo.classList.remove('is-invalid'));
    
    abrirModalCentrado(modalPresentacion);
}

function editarPresentacion(id) {
    Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch(`${RUTAS_API.detalle}?id=${id}`)
        .then(r => r.json())
        .then(data => {
            Swal.close();
            if (data.success && data.presentacion) {
                const p = data.presentacion;
                document.getElementById('modalTitulo').innerHTML = '<span class="material-symbols-rounded me-2">edit_square</span> Editar Presentación';
                document.getElementById('presentacionId').value = p.id_presentacion;
                document.getElementById('nombrePresentacion').value = p.nombre;
                document.getElementById('descripcionPresentacion').value = p.descripcion || '';
                document.getElementById('unidadPresentacion').value = p.id_unidad || '';
                document.getElementById('activoPresentacion').checked = p.activo;
                
                const estado = p.activo ? 'Activo' : 'Inactivo';
                const color = p.activo ? 'bg-success' : 'bg-secondary';
                document.getElementById('estadoActivo').textContent = estado;
                document.getElementById('estadoActivo').className = `badge ${color}`;
                
                // Cargar categorías con las seleccionadas
                const categoriasSeleccionadas = p.categorias_asignadas ? p.categorias_asignadas.map(c => c.id_categoria) : [];
                cargarCategoriasCheckboxes(categoriasSeleccionadas);
                
                abrirModalCentrado(modalPresentacion);
            } else {
                Swal.fire('Error', data.message || 'No se pudo cargar la presentación', 'error');
            }
        })
        .catch(() => {
            Swal.close();
            Swal.fire('Error de conexión', '', 'error');
        });
}

function guardarPresentacion() {
    // Limpiar clases de validación previas
    const camposInvalidos = document.querySelectorAll('.is-invalid');
    camposInvalidos.forEach(campo => campo.classList.remove('is-invalid'));
    
    // Validaciones
    const nombre = document.getElementById('nombrePresentacion').value.trim();
    if (!nombre) {
        document.getElementById('nombrePresentacion').classList.add('is-invalid');
        Swal.fire('Error', 'El nombre de la presentación es obligatorio', 'error');
        document.getElementById('nombrePresentacion').focus();
        return;
    }
    
    // Validar que tenga al menos una categoría seleccionada
    const categoriasSeleccionadas = getCategoriasSeleccionadas();
    if (categoriasSeleccionadas.length === 0) {
        document.getElementById('categoriasCheckboxes').parentElement.classList.add('is-invalid');
        Swal.fire('Error', 'Debe seleccionar al menos una categoría para esta presentación', 'error');
        return;
    }
    
    const datos = {
        id_presentacion: document.getElementById('presentacionId').value || null,
        nombre: nombre,
        descripcion: document.getElementById('descripcionPresentacion').value,
        id_unidad: document.getElementById('unidadPresentacion').value || null,
        activo: document.getElementById('activoPresentacion').checked ? 1 : 0,
        categorias: categoriasSeleccionadas
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
            Swal.fire({ icon: 'success', title: datos.id_presentacion ? '¡Actualizada!' : '¡Creada!', text: 'Presentación guardada correctamente.', timer: 1500, showConfirmButton: false })
            .then(() => {
                modalPresentacion.hide();
                cargarPresentaciones();
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
            if (data.success && data.presentacion) {
                detallesActualData = data.presentacion;
                const p = data.presentacion;
                
                // Mostrar categorías asignadas como badges
                let categoriasHtml = '';
                if (p.categorias_asignadas && p.categorias_asignadas.length > 0) {
                    categoriasHtml = `
                        <div class="mt-4">
                            <h6 class="fw-bold text-muted mb-3">CATEGORÍAS ASIGNADAS</h6>
                            <div class="d-flex flex-wrap gap-2">
                                ${p.categorias_asignadas.map(cat => `<span class="badge bg-success-subtle text-success p-2">${escapeHtml(cat.nombre)}</span>`).join('')}
                            </div>
                        </div>
                    `;
                }
                
                let medicamentosHtml = '';
                if (p.medicamentos_recientes && p.medicamentos_recientes.length > 0) {
                    medicamentosHtml = `
                        <div class="mt-4">
                            <h6 class="fw-bold text-muted mb-3">MEDICAMENTOS CON ESTA PRESENTACIÓN</h6>
                            <div class="list-group">
                                ${p.medicamentos_recientes.map(m => `
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>${escapeHtml(m.nombre)}</strong>
                                            <small class="text-muted d-block">${escapeHtml(m.concentracion || '')}</small>
                                        </div>
                                        <span class="badge bg-success">RD$ ${formatNum(m.precio || 0)}</span>
                                    </div>
                                `).join('')}
                            </div>
                            ${p.total_medicamentos > 5 ? `<p class="text-muted mt-2 small">... y ${p.total_medicamentos - 5} medicamentos más</p>` : ''}
                        </div>
                    `;
                } else {
                    medicamentosHtml = `<div class="alert alert-info mt-3 mb-0">No hay medicamentos registrados con esta presentación</div>`;
                }
                
                modalBody.innerHTML = `
                    <div class="bg-success-subtle rounded-circle d-inline-flex p-4 mb-3">
                        <span class="material-symbols-rounded text-success" style="font-size: 3rem;">inventory_2</span>
                    </div>
                    <h3 class="fw-bold mb-1">${escapeHtml(p.nombre)}</h3>
                    <span class="badge bg-success-subtle text-success mb-4">#${p.id_presentacion}</span>

                    <div class="row g-4 text-start">
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Unidad de Medida</small><span class="fw-bold text-dark">${p.unidad_nombre ? p.unidad_nombre + ' (' + p.unidad_abrev + ')' : '<span class="text-muted">No asignada</span>'}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Estado</small>${p.activo ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>'}</div></div>
                        <div class="col-12"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Descripción</small><span class="text-dark">${escapeHtml(p.descripcion || 'Sin descripción')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Total Medicamentos</small><span class="fw-bold text-success fs-5">${p.total_medicamentos || 0}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Fecha Registro</small><span class="fw-bold text-dark">${p.fecha_registro ? new Date(p.fecha_registro).toLocaleDateString() : 'N/A'}</span></div></div>
                    </div>
                    ${categoriasHtml}
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
    if (!presentacionesData || presentacionesData.length === 0) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    let htmlContent = `
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Reporte de Presentaciones</title>
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
            <h1>Reporte de Presentaciones</h1>
            <p><strong>Fecha de exportación:</strong> ${new Date().toLocaleString()}</p>
            <p><strong>Total de presentaciones:</strong> ${presentacionesData.length}</p>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Unidad</th>
                        <th>Medicamentos</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    presentacionesData.forEach(p => {
        htmlContent += `
            <tr>
                <td>${p.id_presentacion}</td>
                <td>${escapeHtml(p.nombre)}</td>
                <td>${escapeHtml(p.descripcion || '-')}</td>
                <td>${p.unidad_nombre ? p.unidad_nombre + ' (' + p.unidad_abrev + ')' : '-'}</td>
                <td>${p.total_medicamentos || 0}</td>
                <td>${p.activo ? 'Activo' : 'Inactivo'}</td>
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
        filename: `presentaciones_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, letterRendering: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    
    html2pdf().set(opt).from(element).save().then(() => {
        document.body.removeChild(element);
        Swal.fire({ icon: 'success', title: 'Exportado', text: `${presentacionesData.length} presentaciones exportadas a PDF`, timer: 2000, showConfirmButton: false });
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
    
    const p = detallesActualData;
    
    let categoriasTexto = '';
    if (p.categorias_asignadas && p.categorias_asignadas.length > 0) {
        categoriasTexto = p.categorias_asignadas.map(cat => cat.nombre).join(', ');
    } else {
        categoriasTexto = 'Ninguna';
    }
    
    let htmlContent = `
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Detalle de Presentación</title>
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
            <h1>Detalle de Presentación</h1>
            <div class="card">
                <div class="info-row"><span class="label">ID:</span><span class="value">${p.id_presentacion}</span></div>
                <div class="info-row"><span class="label">Nombre:</span><span class="value">${escapeHtml(p.nombre)}</span></div>
                <div class="info-row"><span class="label">Descripción:</span><span class="value">${escapeHtml(p.descripcion || 'Sin descripción')}</span></div>
                <div class="info-row"><span class="label">Unidad de Medida:</span><span class="value">${p.unidad_nombre ? p.unidad_nombre + ' (' + p.unidad_abrev + ')' : 'No asignada'}</span></div>
                <div class="info-row"><span class="label">Categorías Asignadas:</span><span class="value">${escapeHtml(categoriasTexto)}</span></div>
                <div class="info-row"><span class="label">Total Medicamentos:</span><span class="value">${p.total_medicamentos || 0}</span></div>
                <div class="info-row"><span class="label">Estado:</span><span class="value">${p.activo ? 'Activo' : 'Inactivo'}</span></div>
                <div class="info-row"><span class="label">Fecha Registro:</span><span class="value">${p.fecha_registro ? new Date(p.fecha_registro).toLocaleDateString() : 'N/A'}</span></div>
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
        filename: `presentacion_${p.id_presentacion}_${p.nombre}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, letterRendering: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    
    html2pdf().set(opt).from(element).save().then(() => {
        document.body.removeChild(element);
        Swal.fire({ icon: 'success', title: 'Exportado', text: 'Presentación exportada a PDF', timer: 1500, showConfirmButton: false });
    }).catch(() => {
        document.body.removeChild(element);
        Swal.fire('Error', 'Error al generar el PDF', 'error');
    });
}

function editarDesdeDetalle() {
    if (detallesActualId) {
        modalDetalles.hide();
        editarPresentacion(detallesActualId);
    }
}

function eliminarPresentacion(id, nombre) {
    Swal.fire({
        title: '¿Eliminar presentación?',
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
                body: JSON.stringify({ id_presentacion: id }) 
            })
            .then(r => r.json())
            .then(data => { 
                Swal.close(); 
                if (data.success) { 
                    Swal.fire('¡Eliminada!', '', 'success');
                    cargarPresentaciones(); 
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

/* Estilos para checkboxes de categorías */
#categoriasCheckboxes {
    background: white;
    border-radius: 10px;
    padding: 10px;
}

.categoria-checkbox {
    width: 1.2em;
    height: 1.2em;
    margin-top: 0.25em;
    cursor: pointer;
}

.categoria-checkbox:checked {
    background-color: #198754;
    border-color: #198754;
}

.categoria-checkbox:focus {
    box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
}

.form-check {
    padding: 8px 12px;
    margin: 0;
    border-radius: 8px;
    transition: background-color 0.2s;
}

.form-check:hover {
    background-color: rgba(25, 135, 84, 0.05);
}

.form-check-label {
    cursor: pointer;
    margin-left: 8px;
}

.form-check-label small {
    font-size: 0.7rem;
    margin-top: 2px;
    line-height: 1.2;
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