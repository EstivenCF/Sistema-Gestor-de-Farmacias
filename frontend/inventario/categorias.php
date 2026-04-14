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
                <span class="material-symbols-rounded align-middle me-2">category</span>
                Gestión de Categorías
            </h2>
            <p class="text-muted mb-0">Administre las categorías de medicamentos</p>
        </div>
        <div>
            <button type="button" class="btn btn-success shadow-sm" onclick="abrirModalNuevo()">
                <span class="material-symbols-rounded align-middle me-1">add</span>
                Nueva Categoría
            </button>
            <button type="button" class="btn btn-outline-success ms-2" onclick="exportarPDF()">
                <span class="material-symbols-rounded align-middle me-1">picture_as_pdf</span>
                Exportar PDF
            </button>
        </div>
    </div>

    <!-- ESTADÍSTICAS RÁPIDAS -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-success bg-opacity-10 border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Categorías</h6>
                            <h3 class="mb-0 text-success" id="statTotalCategorias">0</h3>
                            <small class="text-muted">Registradas en el sistema</small>
                        </div>
                        <span class="material-symbols-rounded text-success" style="font-size:40px;">category</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning bg-opacity-10 border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Con Medicamentos</h6>
                            <h3 class="mb-0 text-warning" id="statConMedicamentos">0</h3>
                            <small class="text-muted">Categorías con productos</small>
                        </div>
                        <span class="material-symbols-rounded text-warning" style="font-size:40px;">medication</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Sin Medicamentos</h6>
                            <h3 class="mb-0 text-info" id="statSinMedicamentos">0</h3>
                            <small class="text-muted">Categorías vacías</small>
                        </div>
                        <span class="material-symbols-rounded text-info" style="font-size:40px;">inventory</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold text-secondary small">BUSCAR CATEGORÍA</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <span class="material-symbols-rounded text-muted">search</span>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-0" id="buscarCategoria" placeholder="Nombre de categoría...">
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

    <!-- TABLA DE CATEGORÍAS -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaCategorias">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>CATEGORÍA</th>
                            <th>DESCRIPCIÓN</th>
                            <th class="text-center">MEDICAMENTOS</th>
                            <th class="text-center">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody id="tablaCategoriasBody">
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <div class="spinner-border text-success" role="status"></div>
                                <p class="mt-2">Cargando categorías...</p>
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

<!-- MODAL PARA NUEVO/EDITAR CATEGORÍA -->
<div class="modal fade" id="modalCategoria" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-success text-white p-4">
                <h5 class="modal-title d-flex align-items-center" id="modalTitulo">
                    <span class="material-symbols-rounded me-2">add_circle</span>
                    Nueva Categoría
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                <form id="formCategoria">
                    <input type="hidden" id="categoriaId">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted">NOMBRE DE LA CATEGORÍA *</label>
                        <input type="text" class="form-control form-control-lg border" id="nombreCategoria" required placeholder="Ej: Analgésicos, Antibióticos, Vitaminas...">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted">DESCRIPCIÓN</label>
                        <textarea class="form-control border" id="descripcionCategoria" rows="4" placeholder="Describa los tipos de medicamentos que pertenecen a esta categoría..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-end gap-3">
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success px-5 fw-bold shadow-sm" onclick="guardarCategoria()">Guardar Categoría</button>
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
                    <span class="material-symbols-rounded me-2">category</span>
                    Detalles de la Categoría
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
    listar: BASE_URL + '/backend/inventario/listar_categorias.php',
    guardar: BASE_URL + '/backend/inventario/guardar_categoria.php',
    eliminar: BASE_URL + '/backend/inventario/eliminar_categoria.php',
    detalle: BASE_URL + '/backend/inventario/detalle_categoria.php',
    estadisticas: BASE_URL + '/backend/inventario/estadisticas_categorias.php'
};

// ==================== VARIABLES GLOBALES ====================
let categoriasData = [];
let paginaActual = 1;
let filasPorPagina = 10;
let filtros = { busqueda: '' };
let detallesActualId = null;
let detallesActualData = null;

let modalCategoria, modalDetalles;

// ==================== INICIALIZACIÓN ====================
document.addEventListener('DOMContentLoaded', function() {
    const elCategoria = document.getElementById('modalCategoria');
    const elDetalles = document.getElementById('modalDetalles');
    
    if (elCategoria) {
        modalCategoria = new bootstrap.Modal(elCategoria, {
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
    
    const buscarInput = document.getElementById('buscarCategoria');
    let timeoutBusqueda;
    buscarInput.addEventListener('input', function() {
        clearTimeout(timeoutBusqueda);
        timeoutBusqueda = setTimeout(() => {
            filtros.busqueda = this.value;
            cargarCategorias();
        }, 500);
    });
    
    cargarCategorias();
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
                document.getElementById('statTotalCategorias').textContent = data.total_categorias || 0;
                document.getElementById('statConMedicamentos').textContent = data.con_medicamentos || 0;
                document.getElementById('statSinMedicamentos').textContent = data.sin_medicamentos || 0;
            }
        })
        .catch(error => console.error('Error actualizando estadísticas:', error));
}

function cargarCategorias() {
    const tbody = document.getElementById('tablaCategoriasBody');
    tbody.innerHTML = `<tr><td colspan="5" class="text-center"><div class="spinner-border text-success"></div><p>Cargando...</p></td></tr>`;
    
    let url = `${RUTAS_API.listar}?pagina=${paginaActual}&limite=${filasPorPagina}`;
    if (filtros.busqueda) url += `&busqueda=${encodeURIComponent(filtros.busqueda)}`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                categoriasData = data.categorias;
                renderizarTabla(categoriasData);
                actualizarPaginacion(data.total);
            } else {
                tbody.innerHTML = `<td><td colspan="5" class="text-center text-danger">Error: ${data.message}</td></tr>`;
            }
        })
        .catch(() => {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Error de conexión</td></tr>`;
        });
}

function renderizarTabla(categorias) {
    const tbody = document.getElementById('tablaCategoriasBody');
    if (!categorias || categorias.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">No hay categorías registradas</td></tr>`;
        return;
    }
    
    let html = '';
    categorias.forEach(c => {
        const medicamentosBadge = c.total_medicamentos > 0 
            ? `<span class="badge bg-success-subtle text-success">${c.total_medicamentos} medicamentos</span>`
            : `<span class="badge bg-secondary-subtle text-secondary">Sin medicamentos</span>`;
        
        html += `<tr>
            <td class="ps-4"><span class="text-success fw-bold">#${c.id_categoria}</span></td>
            <td><div class="fw-bold">${escapeHtml(c.nombre)}</div></td>
            <td><small class="text-muted">${escapeHtml(c.descripcion ? (c.descripcion.length > 80 ? c.descripcion.substring(0, 80) + '...' : c.descripcion) : 'Sin descripción')}</small></td>
            <td class="text-center">${medicamentosBadge}</td>
            <td class="text-center">
                <div class="d-flex justify-content-center gap-2">
                    <button class="btn btn-sm btn-light text-info shadow-sm" onclick="verDetalles(${c.id_categoria})" title="Ver">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                    <button class="btn btn-sm btn-light text-primary shadow-sm" onclick="editarCategoria(${c.id_categoria})" title="Editar">
                        <span class="material-symbols-rounded">edit_square</span>
                    </button>
                    <button class="btn btn-sm btn-light text-danger shadow-sm" onclick="eliminarCategoria(${c.id_categoria}, '${escapeHtml(c.nombre)}')" title="Eliminar">
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

function cambiarPagina(pagina) { paginaActual = pagina; cargarCategorias(); }

function limpiarFiltros() {
    document.getElementById('buscarCategoria').value = '';
    filtros = { busqueda: '' };
    paginaActual = 1;
    cargarCategorias();
}

function abrirModalNuevo() {
    document.getElementById('modalTitulo').innerHTML = '<span class="material-symbols-rounded me-2">add_circle</span> Nueva Categoría';
    document.getElementById('formCategoria').reset();
    document.getElementById('categoriaId').value = '';
    abrirModalCentrado(modalCategoria);
}

function editarCategoria(id) {
    Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch(`${RUTAS_API.detalle}?id=${id}`)
        .then(r => r.json())
        .then(data => {
            Swal.close();
            if (data.success && data.categoria) {
                const c = data.categoria;
                document.getElementById('modalTitulo').innerHTML = '<span class="material-symbols-rounded me-2">edit_square</span> Editar Categoría';
                document.getElementById('categoriaId').value = c.id_categoria;
                document.getElementById('nombreCategoria').value = c.nombre;
                document.getElementById('descripcionCategoria').value = c.descripcion || '';
                abrirModalCentrado(modalCategoria);
            } else {
                Swal.fire('Error', data.message || 'No se pudo cargar la categoría', 'error');
            }
        })
        .catch(() => {
            Swal.close();
            Swal.fire('Error de conexión', '', 'error');
        });
}

function guardarCategoria() {
    const form = document.getElementById('formCategoria');
    if (!form.checkValidity()) { 
        form.classList.add('was-validated'); 
        Swal.fire('Error', 'Por favor complete todos los campos obligatorios (*)', 'error');
        return; 
    }
    
    const datos = {
        id_categoria: document.getElementById('categoriaId').value || null,
        nombre: document.getElementById('nombreCategoria').value,
        descripcion: document.getElementById('descripcionCategoria').value
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
            Swal.fire({ icon: 'success', title: datos.id_categoria ? '¡Actualizada!' : '¡Creada!', text: 'Categoría guardada correctamente.', timer: 1500, showConfirmButton: false })
            .then(() => {
                modalCategoria.hide();
                cargarCategorias();
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
            if (data.success && data.categoria) {
                detallesActualData = data.categoria;
                const c = data.categoria;
                
                let medicamentosHtml = '';
                if (c.medicamentos_recientes && c.medicamentos_recientes.length > 0) {
                    medicamentosHtml = `
                        <div class="mt-4">
                            <h6 class="fw-bold text-muted mb-3">MEDICAMENTOS EN ESTA CATEGORÍA</h6>
                            <div class="list-group">
                                ${c.medicamentos_recientes.map(m => `
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>${escapeHtml(m.nombre)}</strong>
                                            <small class="text-muted d-block">${escapeHtml(m.concentracion || '')}</small>
                                        </div>
                                        <span class="badge bg-success">RD$ ${formatNum(m.precio || 0)}</span>
                                    </div>
                                `).join('')}
                            </div>
                            ${c.total_medicamentos > 5 ? `<p class="text-muted mt-2 small">... y ${c.total_medicamentos - 5} medicamentos más</p>` : ''}
                        </div>
                    `;
                } else {
                    medicamentosHtml = `<div class="alert alert-info mt-3 mb-0">No hay medicamentos registrados en esta categoría</div>`;
                }
                
                modalBody.innerHTML = `
                    <div class="bg-success-subtle rounded-circle d-inline-flex p-4 mb-3">
                        <span class="material-symbols-rounded text-success" style="font-size: 3rem;">category</span>
                    </div>
                    <h3 class="fw-bold mb-1">${escapeHtml(c.nombre)}</h3>
                    <span class="badge bg-success-subtle text-success mb-4">#${c.id_categoria}</span>

                    <div class="row g-4 text-start">
                        <div class="col-12"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Descripción</small><span class="text-dark">${escapeHtml(c.descripcion || 'Sin descripción')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Total Medicamentos</small><span class="fw-bold text-success fs-5">${c.total_medicamentos || 0}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Laboratorios Asociados</small><span class="fw-bold text-dark">${c.total_laboratorios || 0}</span></div></div>
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
    if (!categoriasData || categoriasData.length === 0) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    let htmlContent = `
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Reporte de Categorías</title>
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
            <h1>Reporte de Categorías</h1>
            <p><strong>Fecha de exportación:</strong> ${new Date().toLocaleString()}</p>
            <p><strong>Total de categorías:</strong> ${categoriasData.length}</p>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Medicamentos</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    categoriasData.forEach(c => {
        htmlContent += `
            <tr>
                <td>${c.id_categoria}</td>
                <td>${escapeHtml(c.nombre)}</td>
                <td>${escapeHtml(c.descripcion || '-')}</td>
                <td>${c.total_medicamentos || 0}</td>
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
        filename: `categorias_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, letterRendering: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    
    html2pdf().set(opt).from(element).save().then(() => {
        document.body.removeChild(element);
        Swal.fire({ icon: 'success', title: 'Exportado', text: `${categoriasData.length} categorías exportadas a PDF`, timer: 2000, showConfirmButton: false });
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
    
    const c = detallesActualData;
    
    let htmlContent = `
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Detalle de Categoría</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #198754; text-align: center; }
                .card { border: 1px solid #ddd; border-radius: 10px; padding: 20px; margin-top: 20px; }
                .info-row { margin-bottom: 10px; }
                .label { font-weight: bold; display: inline-block; width: 150px; }
                .value { display: inline-block; }
                hr { margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <h1>Detalle de Categoría</h1>
            <div class="card">
                <div class="info-row"><span class="label">ID:</span><span class="value">${c.id_categoria}</span></div>
                <div class="info-row"><span class="label">Nombre:</span><span class="value">${escapeHtml(c.nombre)}</span></div>
                <div class="info-row"><span class="label">Descripción:</span><span class="value">${escapeHtml(c.descripcion || 'Sin descripción')}</span></div>
                <div class="info-row"><span class="label">Total Medicamentos:</span><span class="value">${c.total_medicamentos || 0}</span></div>
                <div class="info-row"><span class="label">Laboratorios Asociados:</span><span class="value">${c.total_laboratorios || 0}</span></div>
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
        filename: `categoria_${c.id_categoria}_${c.nombre}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, letterRendering: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    
    html2pdf().set(opt).from(element).save().then(() => {
        document.body.removeChild(element);
        Swal.fire({ icon: 'success', title: 'Exportado', text: 'Categoría exportada a PDF', timer: 1500, showConfirmButton: false });
    }).catch(() => {
        document.body.removeChild(element);
        Swal.fire('Error', 'Error al generar el PDF', 'error');
    });
}

function editarDesdeDetalle() {
    if (detallesActualId) {
        modalDetalles.hide();
        editarCategoria(detallesActualId);
    }
}

function eliminarCategoria(id, nombre) {
    Swal.fire({
        title: '¿Eliminar categoría?',
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
                body: JSON.stringify({ id_categoria: id }) 
            })
            .then(r => r.json())
            .then(data => { 
                Swal.close(); 
                if (data.success) { 
                    Swal.fire('¡Eliminada!', '', 'success');
                    cargarCategorias(); 
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