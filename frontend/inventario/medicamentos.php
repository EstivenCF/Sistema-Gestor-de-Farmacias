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

// ==================== OBTENER DATOS PARA FILTROS ====================
$categorias = [];
$laboratorios = [];
$presentaciones = [];
$unidades_medida = [];
$proveedores = [];

try {
    $stmt = $conexion->query("SELECT id_categoria, nombre FROM categorias ORDER BY nombre");
    $categorias = $stmt->fetchAll();
    
    $stmt = $conexion->query("SELECT id_laboratorio, nombre FROM laboratorios WHERE activo = true ORDER BY nombre");
    $laboratorios = $stmt->fetchAll();
    
    $stmt = $conexion->query("SELECT id_presentacion, nombre FROM presentaciones ORDER BY nombre");
    $presentaciones = $stmt->fetchAll();
    
    $stmt = $conexion->query("SELECT id_unidad, nombre, abreviatura FROM unidades_medida ORDER BY nombre");
    $unidades_medida = $stmt->fetchAll();
    
    $stmt = $conexion->query("SELECT id_proveedor, nombre FROM proveedores ORDER BY nombre");
    $proveedores = $stmt->fetchAll();
    
} catch(PDOException $e) {}

$itbis_porcentaje = 18;
try {
    $stmt = $conexion->query("SELECT porcentaje FROM config_itbis WHERE activo = true AND CURRENT_DATE BETWEEN fecha_inicio AND COALESCE(fecha_fin, CURRENT_DATE + INTERVAL '100 years') LIMIT 1");
    $itbis_config = $stmt->fetch();
    if ($itbis_config) {
        $itbis_porcentaje = $itbis_config['porcentaje'];
    }
} catch(PDOException $e) {}

$base_url = '/Sistema-Gestor-de-Farmacias';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 text-success">
                <span class="material-symbols-rounded align-middle me-2">medication</span>
                Gestión de Medicamentos
            </h2>
            <p class="text-muted mb-0">Administre el catálogo de medicamentos de la farmacia</p>
        </div>
        <div>
            <button type="button" class="btn btn-success shadow-sm" onclick="abrirModalNuevo()">
                <span class="material-symbols-rounded align-middle me-1">add</span>
                Nuevo Medicamento
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
                            <h6 class="text-muted mb-1">Total Medicamentos</h6>
                            <h3 class="mb-0 text-success" id="statTotalMedicamentos">0</h3>
                            <small class="text-muted">Registrados en el sistema</small>
                        </div>
                        <span class="material-symbols-rounded text-success" style="font-size:40px;">medication</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning bg-opacity-10 border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Con Receta</h6>
                            <h3 class="mb-0 text-warning" id="statConReceta">0</h3>
                            <small class="text-muted">Requieren prescripción médica</small>
                        </div>
                        <span class="material-symbols-rounded text-warning" style="font-size:40px;">medical_information</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Sin Receta</h6>
                            <h3 class="mb-0 text-info" id="statSinReceta">0</h3>
                            <small class="text-muted">Venta libre</small>
                        </div>
                        <span class="material-symbols-rounded text-info" style="font-size:40px;">local_pharmacy</span>
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
                    <label class="form-label fw-bold text-secondary small">BUSCAR MEDICAMENTO</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <span class="material-symbols-rounded text-muted">search</span>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-0" id="buscarMedicamento" placeholder="Nombre...">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-secondary small">CATEGORÍA</label>
                    <select class="form-select" id="filtroCategoria">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['id_categoria']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-secondary small">PRESENTACIÓN</label>
                    <select class="form-select" id="filtroPresentacion">
                        <option value="">Todas</option>
                        <?php foreach ($presentaciones as $pres): ?>
                            <option value="<?php echo $pres['id_presentacion']; ?>"><?php echo htmlspecialchars($pres['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-secondary small">LABORATORIO</label>
                    <select class="form-select" id="filtroLaboratorio">
                        <option value="">Todos</option>
                        <?php foreach ($laboratorios as $lab): ?>
                            <option value="<?php echo $lab['id_laboratorio']; ?>"><?php echo htmlspecialchars($lab['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-secondary small">PROVEEDOR</label>
                    <select class="form-select" id="filtroProveedor">
                        <option value="">Todos</option>
                        <?php foreach ($proveedores as $prov): ?>
                            <option value="<?php echo $prov['id_proveedor']; ?>"><?php echo htmlspecialchars($prov['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label fw-bold text-secondary small">RECETA</label>
                    <select class="form-select" id="filtroReceta">
                        <option value="">Todos</option>
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100 fw-bold" onclick="limpiarFiltros()">
                        <span class="material-symbols-rounded align-middle me-1">filter_list_off</span>
                        Quitar filtros
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLA DE MEDICAMENTOS -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaMedicamentos">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>MEDICAMENTO</th>
                            <th>PRESENTACIÓN</th>
                            <th>CATEGORÍA</th>
                            <th>LABORATORIO</th>
                            <th>PROVEEDOR</th>
                            <th>PRECIO</th>
                            <th>RECETA</th>
                            <th class="text-center">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody id="tablaMedicamentosBody">
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <div class="spinner-border text-success" role="status"></div>
                                <p class="mt-2">Cargando medicamentos...</p>
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

<!-- MODAL PARA NUEVO/EDITAR MEDICAMENTO -->
<div class="modal fade" id="modalMedicamento" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-success text-white p-4">
                <h5 class="modal-title d-flex align-items-center" id="modalTitulo">
                    <span class="material-symbols-rounded me-2">add_circle</span>
                    Nuevo Medicamento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                <form id="formMedicamento">
                    <input type="hidden" id="medicamentoId">
                    <input type="hidden" id="productoId">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">NOMBRE DEL MEDICAMENTO *</label>
                            <input type="text" class="form-control form-control-lg border" id="nombreMedicamento" required>
                            <div class="invalid-feedback">El nombre del medicamento es obligatorio</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">CONCENTRACIÓN *</label>
                            <input type="text" class="form-control form-control-lg border" id="concentracion" required placeholder="Ej: 500">
                            <div class="invalid-feedback">La concentración es obligatoria</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">UNIDAD *</label>
                            <select class="form-select form-control-lg border" id="unidadMedida" required disabled>
                                <option value="">Primero seleccione una presentación</option>
                            </select>
                            <div class="invalid-feedback">La unidad de medida es obligatoria</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">DESCRIPCIÓN</label>
                            <textarea class="form-control border" id="descripcionMedicamento" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">CATEGORÍA *</label>
                            <select class="form-select form-control-lg border" id="categoriaMedicamento" required>
                                <option value="">Seleccionar categoría...</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id_categoria']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Debe seleccionar una categoría</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">PRESENTACIÓN *</label>
                            <select class="form-select form-control-lg border" id="presentacionMedicamento" required disabled>
                                <option value="">Primero seleccione una categoría</option>
                            </select>
                            <div class="invalid-feedback">Debe seleccionar una presentación</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">LABORATORIO</label>
                            <select class="form-select form-control-lg border" id="laboratorioMedicamento">
                                <option value="">Seleccionar laboratorio...</option>
                                <?php foreach ($laboratorios as $lab): ?>
                                    <option value="<?php echo $lab['id_laboratorio']; ?>"><?php echo htmlspecialchars($lab['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">PROVEEDOR PREFERIDO</label>
                            <select class="form-select form-control-lg border" id="proveedorPreferido">
                                <option value="">Seleccionar proveedor...</option>
                                <?php foreach ($proveedores as $prov): ?>
                                    <option value="<?php echo $prov['id_proveedor']; ?>"><?php echo htmlspecialchars($prov['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">STOCK MÍNIMO *</label>
                            <input type="number" class="form-control border" id="stockMinimo" min="0" value="5" required>
                            <div class="invalid-feedback">El stock mínimo es obligatorio</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">STOCK MÁXIMO</label>
                            <input type="number" class="form-control border" id="stockMaximo" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">PUNTO DE REORDEN *</label>
                            <input type="number" class="form-control border" id="puntoReorden" min="0" value="10" required>
                            <div class="invalid-feedback">El punto de reorden es obligatorio</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-success">PRECIO UNITARIO (RD$) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white border-success">$</span>
                                <input type="number" step="0.01" class="form-control border" id="precioUnitario" required>
                            </div>
                            <div class="invalid-feedback">El precio debe ser mayor a 0</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">ITBIS</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="exentoItbis">
                                <label class="form-check-label" for="exentoItbis">
                                    <span class="badge bg-success" id="estadoItbis">Aplica ITBIS (<?php echo $itbis_porcentaje; ?>%)</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">REQUIERE RECETA</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="requiereReceta">
                                <label class="form-check-label" for="requiereReceta">
                                    <span class="badge bg-success" id="estadoReceta">No requiere receta</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning mt-3 mb-0" id="alertLabProv" style="display: none; font-size: 0.85rem;">
                        <span class="material-symbols-rounded align-middle me-1" style="font-size: 18px;">warning</span>
                        Debe seleccionar al menos un Laboratorio o un Proveedor Preferido.
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-end gap-3">
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success px-5 fw-bold shadow-sm" onclick="guardarMedicamento()">Guardar Medicamento</button>
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
                    <span class="material-symbols-rounded me-2">medication</span>
                    Detalles del Medicamento
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
    listar: BASE_URL + '/backend/inventario/listar_medicamentos.php',
    guardar: BASE_URL + '/backend/inventario/guardar_medicamento.php',
    eliminar: BASE_URL + '/backend/inventario/eliminar_medicamento.php',
    detalle: BASE_URL + '/backend/inventario/detalle_medicamento.php',
    estadisticas: BASE_URL + '/backend/inventario/estadisticas_medicamentos.php'
};

// ==================== VARIABLES GLOBALES ====================
let medicamentosData = [];
let paginaActual = 1;
let filasPorPagina = 10;
let filtros = { busqueda: '', categoria: '', presentacion: '', laboratorio: '', proveedor: '', receta: '' };
let detallesActualId = null;
let detallesActualData = null;
let itbisPorcentaje = <?php echo $itbis_porcentaje; ?>;

let modalMedicamento, modalDetalles;

// ==================== INICIALIZACIÓN ====================
document.addEventListener('DOMContentLoaded', function() {
    const elMedicamento = document.getElementById('modalMedicamento');
    const elDetalles = document.getElementById('modalDetalles');
    
    if (elMedicamento) {
        modalMedicamento = new bootstrap.Modal(elMedicamento, {
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
    
    document.getElementById('requiereReceta').addEventListener('change', function() {
        const estado = this.checked ? 'Requiere receta' : 'No requiere receta';
        const color = this.checked ? 'bg-danger' : 'bg-success';
        document.getElementById('estadoReceta').textContent = estado;
        document.getElementById('estadoReceta').className = `badge ${color}`;
    });
    
    document.getElementById('exentoItbis').addEventListener('change', function() {
        const estado = this.checked ? 'Exento de ITBIS' : `Aplica ITBIS (${itbisPorcentaje}%)`;
        const color = this.checked ? 'bg-secondary' : 'bg-success';
        document.getElementById('estadoItbis').textContent = estado;
        document.getElementById('estadoItbis').className = `badge ${color}`;
    });
    
    document.getElementById('laboratorioMedicamento').addEventListener('change', validarLabProv);
    document.getElementById('proveedorPreferido').addEventListener('change', validarLabProv);
    
    document.getElementById('categoriaMedicamento').addEventListener('change', function() {
        cargarPresentacionesPorCategoria(this.value);
        // Limpiar validación cuando cambia
        this.classList.remove('is-invalid');
    });
    
    document.getElementById('presentacionMedicamento').addEventListener('change', function() {
        if (this.value) {
            cargarUnidadPorPresentacion(this.value);
            this.classList.remove('is-invalid');
        }
    });
    
    document.getElementById('unidadMedida').addEventListener('change', function() {
        if (this.value) {
            this.classList.remove('is-invalid');
        }
    });
    
    // Validaciones en tiempo real para campos requeridos
    const camposRequeridos = ['nombreMedicamento', 'concentracion', 'precioUnitario', 'stockMinimo', 'puntoReorden'];
    camposRequeridos.forEach(campo => {
        const input = document.getElementById(campo);
        if (input) {
            input.addEventListener('input', function() {
                if (this.value) {
                    this.classList.remove('is-invalid');
                }
            });
        }
    });
    
    const buscarInput = document.getElementById('buscarMedicamento');
    let timeoutBusqueda;
    buscarInput.addEventListener('input', function() {
        clearTimeout(timeoutBusqueda);
        timeoutBusqueda = setTimeout(() => {
            filtros.busqueda = this.value;
            cargarMedicamentos();
        }, 500);
    });
    
    document.getElementById('filtroCategoria').addEventListener('change', function() {
        filtros.categoria = this.value;
        cargarMedicamentos();
    });
    document.getElementById('filtroPresentacion').addEventListener('change', function() {
        filtros.presentacion = this.value;
        cargarMedicamentos();
    });
    document.getElementById('filtroLaboratorio').addEventListener('change', function() {
        filtros.laboratorio = this.value;
        cargarMedicamentos();
    });
    document.getElementById('filtroProveedor').addEventListener('change', function() {
        filtros.proveedor = this.value;
        cargarMedicamentos();
    });
    document.getElementById('filtroReceta').addEventListener('change', function() {
        filtros.receta = this.value;
        cargarMedicamentos();
    });
    
    cargarMedicamentos();
    actualizarEstadisticas();
});

function validarLabProv() {
    const lab = document.getElementById('laboratorioMedicamento').value;
    const prov = document.getElementById('proveedorPreferido').value;
    const alertDiv = document.getElementById('alertLabProv');
    
    if (!lab && !prov) {
        alertDiv.style.display = 'block';
        return false;
    } else {
        alertDiv.style.display = 'none';
        return true;
    }
}

function cargarPresentacionesPorCategoria(categoriaId) {
    const presentacionSelect = document.getElementById('presentacionMedicamento');
    const unidadSelect = document.getElementById('unidadMedida');
    
    presentacionSelect.innerHTML = '<option value="">Cargando...</option>';
    presentacionSelect.disabled = true;
    unidadSelect.innerHTML = '<option value="">Primero seleccione una presentación</option>';
    unidadSelect.disabled = true;
    
    if (!categoriaId) {
        presentacionSelect.innerHTML = '<option value="">Primero seleccione una categoría</option>';
        return;
    }
    
    fetch(`${BASE_URL}/backend/inventario/presentaciones_por_categoria.php?id_categoria=${categoriaId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.presentaciones.length > 0) {
                presentacionSelect.disabled = false;
                presentacionSelect.innerHTML = '<option value="">Seleccionar presentación...</option>';
                data.presentaciones.forEach(p => {
                    const option = document.createElement('option');
                    option.value = p.id_presentacion;
                    option.textContent = p.nombre;
                    presentacionSelect.appendChild(option);
                });
            } else {
                presentacionSelect.innerHTML = '<option value="">No hay presentaciones para esta categoría</option>';
            }
        })
        .catch(() => {
            presentacionSelect.innerHTML = '<option value="">Error al cargar presentaciones</option>';
        });
}

function cargarUnidadPorPresentacion(presentacionId) {
    const unidadSelect = document.getElementById('unidadMedida');
    
    if (!presentacionId) {
        unidadSelect.innerHTML = '<option value="">Primero seleccione una presentación</option>';
        unidadSelect.disabled = true;
        return;
    }
    
    unidadSelect.innerHTML = '<option value="">Cargando...</option>';
    unidadSelect.disabled = true;
    
    fetch(`${BASE_URL}/backend/inventario/unidad_por_presentacion.php?id_presentacion=${presentacionId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.unidad) {
                unidadSelect.disabled = false;
                unidadSelect.innerHTML = `<option value="${data.unidad.id_unidad}">${data.unidad.nombre} (${data.unidad.abreviatura})</option>`;
            } else {
                unidadSelect.innerHTML = '<option value="">No se encontró unidad</option>';
            }
        })
        .catch(() => {
            unidadSelect.innerHTML = '<option value="">Error al cargar unidad</option>';
        });
}

function abrirModalCentrado(modal) {
    modal.show();
}

function actualizarEstadisticas() {
    fetch(RUTAS_API.estadisticas)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statTotalMedicamentos').textContent = data.total_medicamentos || 0;
                document.getElementById('statConReceta').textContent = data.con_receta || 0;
                document.getElementById('statSinReceta').textContent = data.sin_receta || 0;
            }
        })
        .catch(error => console.error('Error actualizando estadísticas:', error));
}

function cargarMedicamentos() {
    const tbody = document.getElementById('tablaMedicamentosBody');
    tbody.innerHTML = `<tr><td colspan="9" class="text-center"><div class="spinner-border text-success"></div><p>Cargando...</p></td></tr>`;
    
    let url = `${RUTAS_API.listar}?pagina=${paginaActual}&limite=${filasPorPagina}`;
    if (filtros.busqueda) url += `&busqueda=${encodeURIComponent(filtros.busqueda)}`;
    if (filtros.categoria) url += `&categoria=${filtros.categoria}`;
    if (filtros.presentacion) url += `&presentacion=${filtros.presentacion}`;
    if (filtros.laboratorio) url += `&laboratorio=${filtros.laboratorio}`;
    if (filtros.proveedor) url += `&proveedor=${filtros.proveedor}`;
    if (filtros.receta === '1' || filtros.receta === '0') url += `&receta=${filtros.receta}`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                medicamentosData = data.medicamentos;
                renderizarTabla(medicamentosData);
                actualizarPaginacion(data.total);
            } else {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error: ${data.message}</td></tr>`;
            }
        })
        .catch(() => {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error de conexión</td></tr>`;
        });
}

function renderizarTabla(medicamentos) {
    const tbody = document.getElementById('tablaMedicamentosBody');
    if (!medicamentos || medicamentos.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted">No hay medicamentos registrados</td></tr>`;
        return;
    }
    
    let html = '';
    medicamentos.forEach(m => {
        const recetaClass = m.requiere_receta ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success';
        const recetaTexto = m.requiere_receta ? 'Sí requiere' : 'No requiere';
        
        html += `<tr>
            <td class="ps-4"><span class="text-success fw-bold">#${m.id_medicamento}</span></td>
            <td><div class="fw-bold">${escapeHtml(m.nombre)}</div><small class="text-muted">${escapeHtml(m.concentracion || '')} ${escapeHtml(m.unidad_abrev || '')}</small></td>
            <td class="fw-bold">${escapeHtml(m.presentacion || '-')}</td>
            <td><span class="badge bg-success-subtle text-success">${escapeHtml(m.categoria_nombre || '-')}</span></td>
            <td><small>${escapeHtml(m.laboratorio_nombre || '-')}</small></td>
            <td><small>${escapeHtml(m.proveedor_nombre || '-')}</small></td>
            <td class="fw-bold text-success">RD$ ${formatNum(m.precio)}</td>
            <td><span class="badge ${recetaClass}">${recetaTexto}</span></td>
            <td class="text-center">
                <div class="d-flex justify-content-center gap-2">
                    <button class="btn btn-sm btn-light text-info shadow-sm" onclick="verDetalles(${m.id_medicamento})" title="Ver">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                    <button class="btn btn-sm btn-light text-primary shadow-sm" onclick="editarMedicamento(${m.id_medicamento})" title="Editar">
                        <span class="material-symbols-rounded">edit_square</span>
                    </button>
                    <button class="btn btn-sm btn-light text-danger shadow-sm" onclick="eliminarMedicamento(${m.id_medicamento}, '${escapeHtml(m.nombre)}')" title="Eliminar">
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

function cambiarPagina(pagina) { paginaActual = pagina; cargarMedicamentos(); }

function limpiarFiltros() {
    document.getElementById('buscarMedicamento').value = '';
    document.getElementById('filtroCategoria').value = '';
    document.getElementById('filtroPresentacion').value = '';
    document.getElementById('filtroLaboratorio').value = '';
    document.getElementById('filtroProveedor').value = '';
    document.getElementById('filtroReceta').value = '';
    filtros = { busqueda: '', categoria: '', presentacion: '', laboratorio: '', proveedor: '', receta: '' };
    paginaActual = 1;
    cargarMedicamentos();
}

function abrirModalNuevo() {
    document.getElementById('modalTitulo').innerHTML = '<span class="material-symbols-rounded me-2">add_circle</span> Nuevo Medicamento';
    document.getElementById('formMedicamento').reset();
    document.getElementById('medicamentoId').value = '';
    document.getElementById('productoId').value = '';
    document.getElementById('estadoReceta').textContent = 'No requiere receta';
    document.getElementById('estadoReceta').className = 'badge bg-success';
    document.getElementById('estadoItbis').textContent = `Aplica ITBIS (${itbisPorcentaje}%)`;
    document.getElementById('estadoItbis').className = 'badge bg-success';
    document.getElementById('requiereReceta').checked = false;
    document.getElementById('exentoItbis').checked = false;
    document.getElementById('stockMinimo').value = '5';
    document.getElementById('puntoReorden').value = '10';
    document.getElementById('alertLabProv').style.display = 'none';
    
    document.getElementById('presentacionMedicamento').innerHTML = '<option value="">Primero seleccione una categoría</option>';
    document.getElementById('presentacionMedicamento').disabled = true;
    document.getElementById('unidadMedida').innerHTML = '<option value="">Primero seleccione una presentación</option>';
    document.getElementById('unidadMedida').disabled = true;
    
    // Limpiar clases de validación
    const camposInvalidos = document.querySelectorAll('.is-invalid');
    camposInvalidos.forEach(campo => campo.classList.remove('is-invalid'));
    
    abrirModalCentrado(modalMedicamento);
}

function editarMedicamento(id) {
    Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch(`${RUTAS_API.detalle}?id=${id}`)
        .then(r => r.json())
        .then(data => {
            Swal.close();
            if (data.success && data.medicamento) {
                const m = data.medicamento;
                document.getElementById('modalTitulo').innerHTML = '<span class="material-symbols-rounded me-2">edit_square</span> Editar Medicamento';
                document.getElementById('medicamentoId').value = m.id_medicamento;
                document.getElementById('productoId').value = m.id_producto;
                document.getElementById('nombreMedicamento').value = m.nombre;
                document.getElementById('concentracion').value = m.concentracion || '';
                document.getElementById('descripcionMedicamento').value = m.descripcion || '';
                document.getElementById('categoriaMedicamento').value = m.id_categoria || '';
                document.getElementById('laboratorioMedicamento').value = m.id_laboratorio || '';
                document.getElementById('proveedorPreferido').value = m.proveedor_preferido || '';
                document.getElementById('stockMinimo').value = m.stock_minimo || 5;
                document.getElementById('stockMaximo').value = m.stock_maximo || '';
                document.getElementById('puntoReorden').value = m.punto_reorden || 10;
                document.getElementById('precioUnitario').value = m.precio || '';
                document.getElementById('requiereReceta').checked = m.requiere_receta;
                document.getElementById('exentoItbis').checked = m.exento_itbis;
                document.getElementById('alertLabProv').style.display = 'none';
                
                const recetaEstado = m.requiere_receta ? 'Requiere receta' : 'No requiere receta';
                const recetaColor = m.requiere_receta ? 'bg-danger' : 'bg-success';
                document.getElementById('estadoReceta').textContent = recetaEstado;
                document.getElementById('estadoReceta').className = `badge ${recetaColor}`;
                
                const itbisEstado = m.exento_itbis ? 'Exento de ITBIS' : `Aplica ITBIS (${itbisPorcentaje}%)`;
                const itbisColor = m.exento_itbis ? 'bg-secondary' : 'bg-success';
                document.getElementById('estadoItbis').textContent = itbisEstado;
                document.getElementById('estadoItbis').className = `badge ${itbisColor}`;
                
                if (m.id_categoria) {
                    cargarPresentacionesPorCategoria(m.id_categoria);
                    setTimeout(() => {
                        if (m.id_presentacion) {
                            document.getElementById('presentacionMedicamento').value = m.id_presentacion;
                            cargarUnidadPorPresentacion(m.id_presentacion);
                            setTimeout(() => {
                                if (m.id_unidad) {
                                    document.getElementById('unidadMedida').value = m.id_unidad;
                                }
                            }, 200);
                        }
                    }, 300);
                }
                
                // Limpiar clases de validación
                const camposInvalidos = document.querySelectorAll('.is-invalid');
                camposInvalidos.forEach(campo => campo.classList.remove('is-invalid'));
                
                abrirModalCentrado(modalMedicamento);
            } else {
                Swal.fire('Error', data.message || 'No se pudo cargar el medicamento', 'error');
            }
        })
        .catch(() => {
            Swal.close();
            Swal.fire('Error de conexión', '', 'error');
        });
}

function guardarMedicamento() {
    const form = document.getElementById('formMedicamento');
    
    // Limpiar clases de validación previas
    const camposInvalidos = document.querySelectorAll('.is-invalid');
    camposInvalidos.forEach(campo => campo.classList.remove('is-invalid'));
    
    // ==================== VALIDACIONES FRONTEND ====================
    let isValid = true;
    
    // Validar nombre
    const nombre = document.getElementById('nombreMedicamento').value.trim();
    if (!nombre) {
        document.getElementById('nombreMedicamento').classList.add('is-invalid');
        Swal.fire('Error', 'El nombre del medicamento es obligatorio', 'error');
        document.getElementById('nombreMedicamento').focus();
        return;
    }
    
    // Validar concentración
    const concentracion = document.getElementById('concentracion').value.trim();
    if (!concentracion) {
        document.getElementById('concentracion').classList.add('is-invalid');
        Swal.fire('Error', 'La concentración del medicamento es obligatoria', 'error');
        document.getElementById('concentracion').focus();
        return;
    }
    
    // Validar categoría
    const categoria = document.getElementById('categoriaMedicamento').value;
    if (!categoria) {
        document.getElementById('categoriaMedicamento').classList.add('is-invalid');
        Swal.fire('Error', 'Debe seleccionar una categoría para el medicamento', 'error');
        document.getElementById('categoriaMedicamento').focus();
        return;
    }
    
    // Validar presentación (¡Este es el que te faltaba!)
    const presentacion = document.getElementById('presentacionMedicamento').value;
    if (!presentacion) {
        document.getElementById('presentacionMedicamento').classList.add('is-invalid');
        Swal.fire('Error', 'Debe seleccionar una presentación para el medicamento', 'error');
        document.getElementById('presentacionMedicamento').focus();
        return;
    }
    
    // Validar unidad de medida
    const unidad = document.getElementById('unidadMedida').value;
    if (!unidad) {
        document.getElementById('unidadMedida').classList.add('is-invalid');
        Swal.fire('Error', 'La unidad de medida es obligatoria. Primero seleccione una presentación válida', 'error');
        document.getElementById('presentacionMedicamento').focus();
        return;
    }
    
    // Validar precio
    const precio = parseFloat(document.getElementById('precioUnitario').value);
    if (!precio || precio <= 0) {
        document.getElementById('precioUnitario').classList.add('is-invalid');
        Swal.fire('Error', 'El precio unitario debe ser mayor a 0', 'error');
        document.getElementById('precioUnitario').focus();
        return;
    }
    
    // Validar stock mínimo
    const stockMinimo = parseInt(document.getElementById('stockMinimo').value);
    if (isNaN(stockMinimo) || stockMinimo < 0) {
        document.getElementById('stockMinimo').classList.add('is-invalid');
        Swal.fire('Error', 'El stock mínimo debe ser un número válido', 'error');
        document.getElementById('stockMinimo').focus();
        return;
    }
    
    // Validar punto de reorden
    const puntoReorden = parseInt(document.getElementById('puntoReorden').value);
    if (isNaN(puntoReorden) || puntoReorden < 0) {
        document.getElementById('puntoReorden').classList.add('is-invalid');
        Swal.fire('Error', 'El punto de reorden debe ser un número válido', 'error');
        document.getElementById('puntoReorden').focus();
        return;
    }
    
    // Validar laboratorio o proveedor
    const laboratorio = document.getElementById('laboratorioMedicamento').value;
    const proveedor = document.getElementById('proveedorPreferido').value;
    if (!laboratorio && !proveedor) {
        Swal.fire('Error', 'Debe seleccionar al menos un Laboratorio o un Proveedor Preferido', 'error');
        return;
    }
    
    // Si pasó todas las validaciones, proceder a guardar
    const medicamentoId = document.getElementById('medicamentoId').value;
    const productoId = document.getElementById('productoId').value;
    const stockMaximo = document.getElementById('stockMaximo').value;
    
    const datos = {
        id_medicamento: medicamentoId && medicamentoId !== '' ? parseInt(medicamentoId) : null,
        id_producto: productoId && productoId !== '' ? parseInt(productoId) : null,
        nombre: nombre,
        concentracion: concentracion,
        descripcion: document.getElementById('descripcionMedicamento').value,
        id_categoria: parseInt(categoria),
        id_presentacion: parseInt(presentacion),
        id_laboratorio: laboratorio && laboratorio !== '' ? parseInt(laboratorio) : null,
        id_unidad: parseInt(unidad),
        proveedor_preferido: proveedor && proveedor !== '' ? parseInt(proveedor) : null,
        stock_minimo: stockMinimo,
        stock_maximo: stockMaximo && stockMaximo !== '' ? parseInt(stockMaximo) : null,
        punto_reorden: puntoReorden,
        requiere_receta: document.getElementById('requiereReceta').checked ? 1 : 0,
        exento_itbis: document.getElementById('exentoItbis').checked ? 1 : 0,
        precio: precio
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
            Swal.fire({ 
                icon: 'success', 
                title: datos.id_medicamento ? '¡Actualizado!' : '¡Creado!', 
                text: 'Medicamento guardado correctamente.', 
                timer: 1500, 
                showConfirmButton: false 
            })
            .then(() => {
                modalMedicamento.hide();
                cargarMedicamentos();
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
            if (data.success && data.medicamento) {
                detallesActualData = data.medicamento;
                const m = data.medicamento;
                
                modalBody.innerHTML = `
                    <div class="bg-success-subtle rounded-circle d-inline-flex p-4 mb-3">
                        <span class="material-symbols-rounded text-success" style="font-size: 3rem;">medication</span>
                    </div>
                    <h3 class="fw-bold mb-1">${escapeHtml(m.nombre)}</h3>
                    <span class="badge bg-success-subtle text-success mb-4">#${m.id_medicamento}</span>

                    <div class="row g-4 text-start">
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Concentración</small><span class="fw-bold text-dark">${escapeHtml(m.concentracion ? m.concentracion + ' ' + (m.unidad_abreviatura || '') : '-')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Presentación</small><span class="fw-bold text-dark">${escapeHtml(m.presentacion_nombre || '-')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Categoría</small><span class="fw-bold text-dark">${escapeHtml(m.categoria_nombre || '-')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Laboratorio</small><span class="fw-bold text-dark">${escapeHtml(m.laboratorio_nombre || '-')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Proveedor</small><span class="fw-bold text-dark">${escapeHtml(m.proveedor_nombre || '-')}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Precio</small><span class="fw-bold text-success fs-5">RD$ ${formatNum(m.precio)}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Receta</small>${m.requiere_receta ? '<span class="badge bg-danger">Requiere receta</span>' : '<span class="badge bg-success">No requiere receta</span>'}</div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">ITBIS</small>${m.exento_itbis ? '<span class="badge bg-secondary">Exento</span>' : '<span class="badge bg-success">Aplica</span>'}</div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Stock Mínimo</small><span class="fw-bold text-dark">${m.stock_minimo || 0}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Stock Máximo</small><span class="fw-bold text-dark">${m.stock_maximo || 'Sin límite'}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Punto Reorden</small><span class="fw-bold text-dark">${m.punto_reorden || 0}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Fecha Registro</small><span class="fw-bold text-dark">${escapeHtml(m.fecha_registro || 'N/A')}</span></div></div>
                        <div class="col-12"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Descripción</small><span class="text-dark">${escapeHtml(m.descripcion || 'Sin descripción')}</span></div></div>
                    </div>
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
    if (!medicamentosData || medicamentosData.length === 0) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    let htmlContent = `
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Reporte de Medicamentos</title>
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
            <h1>Reporte de Medicamentos</h1>
            <p><strong>Fecha de exportación:</strong> ${new Date().toLocaleString()}</p>
            <p><strong>Total de medicamentos:</strong> ${medicamentosData.length}</p>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Concentración</th>
                        <th>Presentación</th>
                        <th>Categoría</th>
                        <th>Laboratorio</th>
                        <th>Proveedor</th>
                        <th>Precio</th>
                        <th>Receta</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    medicamentosData.forEach(m => {
        htmlContent += `
            <tr>
                <td>${m.id_medicamento}</td>
                <td>${escapeHtml(m.nombre)}</td>
                <td>${escapeHtml(m.concentracion || '-')} ${escapeHtml(m.unidad_abrev || '')}</td>
                <td>${escapeHtml(m.presentacion || '-')}</td>
                <td>${escapeHtml(m.categoria_nombre || '-')}</td>
                <td>${escapeHtml(m.laboratorio_nombre || '-')}</td>
                <td>${escapeHtml(m.proveedor_nombre || '-')}</td>
                <td>RD$ ${formatNum(m.precio)}</td>
                <td>${m.requiere_receta ? 'Sí' : 'No'}</td>
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
        filename: `medicamentos_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, letterRendering: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    
    html2pdf().set(opt).from(element).save().then(() => {
        document.body.removeChild(element);
        Swal.fire({ icon: 'success', title: 'Exportado', text: `${medicamentosData.length} medicamentos exportados a PDF`, timer: 2000, showConfirmButton: false });
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
    
    const m = detallesActualData;
    
    let htmlContent = `
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Detalle de Medicamento</title>
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
            <h1>Detalle de Medicamento</h1>
            <div class="card">
                <div class="info-row"><span class="label">ID:</span><span class="value">${m.id_medicamento}</span></div>
                <div class="info-row"><span class="label">Nombre:</span><span class="value">${escapeHtml(m.nombre)}</span></div>
                <div class="info-row"><span class="label">Concentración:</span><span class="value">${escapeHtml(m.concentracion || '-')} ${escapeHtml(m.unidad_abreviatura || '')}</span></div>
                <div class="info-row"><span class="label">Presentación:</span><span class="value">${escapeHtml(m.presentacion_nombre || '-')}</span></div>
                <div class="info-row"><span class="label">Categoría:</span><span class="value">${escapeHtml(m.categoria_nombre || '-')}</span></div>
                <div class="info-row"><span class="label">Laboratorio:</span><span class="value">${escapeHtml(m.laboratorio_nombre || '-')}</span></div>
                <div class="info-row"><span class="label">Proveedor:</span><span class="value">${escapeHtml(m.proveedor_nombre || '-')}</span></div>
                <div class="info-row"><span class="label">Precio:</span><span class="value">RD$ ${formatNum(m.precio)}</span></div>
                <div class="info-row"><span class="label">Requiere Receta:</span><span class="value">${m.requiere_receta ? 'Sí' : 'No'}</span></div>
                <div class="info-row"><span class="label">Exento ITBIS:</span><span class="value">${m.exento_itbis ? 'Sí' : 'No'}</span></div>
                <div class="info-row"><span class="label">Stock Mínimo:</span><span class="value">${m.stock_minimo || 0}</span></div>
                <div class="info-row"><span class="label">Stock Máximo:</span><span class="value">${m.stock_maximo || 'Sin límite'}</span></div>
                <div class="info-row"><span class="label">Punto de Reorden:</span><span class="value">${m.punto_reorden || 0}</span></div>
                <div class="info-row"><span class="label">Fecha Registro:</span><span class="value">${escapeHtml(m.fecha_registro || 'N/A')}</span></div>
                <hr>
                <div class="info-row"><span class="label">Descripción:</span></div>
                <div class="info-row"><span class="value">${escapeHtml(m.descripcion || 'Sin descripción')}</span></div>
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
        filename: `medicamento_${m.id_medicamento}_${m.nombre}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, letterRendering: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    
    html2pdf().set(opt).from(element).save().then(() => {
        document.body.removeChild(element);
        Swal.fire({ icon: 'success', title: 'Exportado', text: 'Medicamento exportado a PDF', timer: 1500, showConfirmButton: false });
    }).catch(() => {
        document.body.removeChild(element);
        Swal.fire('Error', 'Error al generar el PDF', 'error');
    });
}

function editarDesdeDetalle() {
    if (detallesActualId) {
        modalDetalles.hide();
        editarMedicamento(detallesActualId);
    }
}

function eliminarMedicamento(id, nombre) {
    Swal.fire({
        title: '¿Eliminar medicamento?',
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
                body: JSON.stringify({ id_medicamento: id }) 
            })
            .then(r => r.json())
            .then(data => { 
                Swal.close(); 
                if (data.success) { 
                    Swal.fire('¡Eliminado!', '', 'success');
                    cargarMedicamentos(); 
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