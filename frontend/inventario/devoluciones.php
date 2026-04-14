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

// Obtener tipos de devolución
$tipos_devolucion = [];
try {
    $stmt = $conexion->query("SELECT id_tipo, nombre FROM tipo_devolucion ORDER BY nombre");
    $tipos_devolucion = $stmt->fetchAll();
} catch(PDOException $e) {}

// Obtener estados de devolución
$estados_devolucion = [];
try {
    $stmt = $conexion->query("SELECT id_estado, nombre FROM estado_devolucion ORDER BY id_estado");
    $estados_devolucion = $stmt->fetchAll();
} catch(PDOException $e) {}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 text-primary">
                <span class="material-symbols-rounded align-middle me-2">swap_vert</span>
                Gestión de Devoluciones
            </h2>
            <p class="text-muted mb-0">Administre devoluciones de clientes, proveedores y ajustes de inventario</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary shadow-sm" onclick="abrirModalNuevaDevolucion()">
                <span class="material-symbols-rounded align-middle me-1">add</span>
                Nueva Devolución
            </button>
            <button type="button" class="btn btn-outline-primary ms-2" onclick="exportarPDF()">
                <span class="material-symbols-rounded align-middle me-1">picture_as_pdf</span>
                Exportar PDF
            </button>
        </div>
    </div>

    <!-- ESTADÍSTICAS RÁPIDAS -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary bg-opacity-10 border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Devoluciones</h6>
                            <h3 class="mb-0 text-primary" id="statTotal">0</h3>
                            <small class="text-muted">Registradas</small>
                        </div>
                        <span class="material-symbols-rounded text-primary" style="font-size:40px;">swap_vert</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning bg-opacity-10 border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Pendientes</h6>
                            <h3 class="mb-0 text-warning" id="statPendientes">0</h3>
                            <small class="text-muted">Por aprobar</small>
                        </div>
                        <span class="material-symbols-rounded text-warning" style="font-size:40px;">pending</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success bg-opacity-10 border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Completadas</h6>
                            <h3 class="mb-0 text-success" id="statCompletadas">0</h3>
                            <small class="text-muted">Finalizadas</small>
                        </div>
                        <span class="material-symbols-rounded text-success" style="font-size:40px;">check_circle</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger bg-opacity-10 border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Rechazadas</h6>
                            <h3 class="mb-0 text-danger" id="statRechazadas">0</h3>
                            <small class="text-muted">No aprobadas</small>
                        </div>
                        <span class="material-symbols-rounded text-danger" style="font-size:40px;">cancel</span>
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
                    <label class="form-label fw-bold text-secondary small">TIPO</label>
                    <select class="form-select" id="filtroTipo">
                        <option value="">Todos</option>
                        <?php foreach ($tipos_devolucion as $tipo): ?>
                            <option value="<?php echo $tipo['id_tipo']; ?>"><?php echo htmlspecialchars($tipo['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-secondary small">ESTADO</label>
                    <select class="form-select" id="filtroEstado">
                        <option value="">Todos</option>
                        <?php foreach ($estados_devolucion as $estado): ?>
                            <option value="<?php echo $estado['id_estado']; ?>"><?php echo htmlspecialchars($estado['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold text-secondary small">SUCURSAL</label>
                    <select class="form-select" id="filtroSucursal">
                        <option value="">Todas</option>
                        <?php foreach ($sucursales as $suc): ?>
                            <option value="<?php echo $suc['id_sucursal']; ?>"><?php echo htmlspecialchars($suc['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-bold text-secondary small">BUSCAR</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <span class="material-symbols-rounded text-muted">search</span>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-0" id="buscarDevolucion" placeholder="N° documento, cliente, proveedor...">
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

    <!-- TABLA DE DEVOLUCIONES -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaDevoluciones">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">N° Documento</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Cliente/Proveedor</th>
                            <th>Sucursal</th>
                            <th>Motivo</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaDevolucionesBody">
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="mt-2">Cargando devoluciones...</p>
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

<!-- MODAL PARA NUEVA/EDITAR DEVOLUCIÓN -->
<div class="modal fade" id="modalDevolucion" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-primary text-white p-4">
                <h5 class="modal-title d-flex align-items-center" id="modalTitulo">
                    <span class="material-symbols-rounded me-2">add</span>
                    Nueva Devolución
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                <form id="formDevolucion">
                    <input type="hidden" id="devolucionId">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">TIPO DE DEVOLUCIÓN *</label>
                            <select class="form-select" id="tipoDevolucion" required>
                                <option value="">Seleccionar tipo...</option>
                                <?php foreach ($tipos_devolucion as $tipo): ?>
                                    <option value="<?php echo $tipo['id_tipo']; ?>"><?php echo htmlspecialchars($tipo['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">FECHA *</label>
                            <input type="date" class="form-control" id="fechaDevolucion" required>
                        </div>
                        <div class="col-md-6" id="divCliente" style="display: none;">
                            <label class="form-label fw-bold text-muted">CLIENTE *</label>
                            <select class="form-select" id="idCliente">
                                <option value="">Seleccionar cliente...</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="divProveedor" style="display: none;">
                            <label class="form-label fw-bold text-muted">PROVEEDOR *</label>
                            <select class="form-select" id="idProveedor">
                                <option value="">Seleccionar proveedor...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">SUCURSAL *</label>
                            <select class="form-select" id="sucursalDevolucion" required>
                                <option value="">Seleccionar sucursal...</option>
                                <?php foreach ($sucursales as $suc): ?>
                                    <option value="<?php echo $suc['id_sucursal']; ?>"><?php echo htmlspecialchars($suc['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted">ESTADO</label>
                            <select class="form-select" id="estadoDevolucion">
                                <?php foreach ($estados_devolucion as $estado): ?>
                                    <option value="<?php echo $estado['id_estado']; ?>"><?php echo htmlspecialchars($estado['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-muted">MOTIVO *</label>
                            <textarea class="form-control" id="motivoDevolucion" rows="2" required placeholder="Explique el motivo de la devolución..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-muted">PRODUCTOS A DEVOLVER</label>
                            <div class="table-responsive">
                                <table class="table table-sm" id="tablaProductosDevolucion">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Producto</th>
                                            <th>Lote</th>
                                            <th>Cantidad</th>
                                            <th>Precio Unitario</th>
                                            <th>Subtotal</th>
                                            <th style="width:40px"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="productosDevolucionBody">
                                        <tr id="filaProductoVacia">
                                            <td colspan="6" class="text-center text-muted">No hay productos agregados</td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="6">
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="agregarProductoDevolucion()">
                                                    <span class="material-symbols-rounded align-middle me-1" style="font-size:16px;">add</span>
                                                    Agregar producto
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class="bg-light">
                                            <td colspan="4" class="text-end fw-bold">TOTAL:</td>
                                            <td class="fw-bold text-success" id="totalDevolucion">RD$ 0.00</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-end gap-3">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary px-5 fw-bold shadow-sm" onclick="guardarDevolucion()">Guardar Devolución</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA VER DETALLES -->
<div class="modal fade" id="modalDetalles" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-primary text-white p-4">
                <h5 class="modal-title d-flex align-items-center">
                    <span class="material-symbols-rounded me-2">info</span>
                    Detalles de la Devolución
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detallesContenido">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
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
    listar: BASE_URL + '/backend/inventario/listar_devoluciones.php',
    guardar: BASE_URL + '/backend/inventario/guardar_devolucion.php',
    detalle: BASE_URL + '/backend/inventario/detalle_devolucion.php',
    estadisticas: BASE_URL + '/backend/inventario/estadisticas_devoluciones.php',
    listarClientes: BASE_URL + '/backend/clientes/listar_clientes_select.php',
    listarProveedores: BASE_URL + '/backend/proveedores/listar_proveedores_select.php',
    listarLotes: BASE_URL + '/backend/inventario/listar_lotes_select.php'
};

// Variables globales
let devolucionesData = [];
let paginaActual = 1;
let filasPorPagina = 10;
let filtros = { fecha_desde: '', fecha_hasta: '', tipo: '', estado: '', sucursal: '', busqueda: '' };
let timeoutBusqueda;
let detallesActualId = null;
let detallesActualData = null;
let productosDevolucion = [];

let modalDevolucion, modalDetalles;

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    const elDevolucion = document.getElementById('modalDevolucion');
    const elDetalles = document.getElementById('modalDetalles');
    
    if (elDevolucion) {
        modalDevolucion = new bootstrap.Modal(elDevolucion, { backdrop: 'static', keyboard: true });
    }
    if (elDetalles) {
        modalDetalles = new bootstrap.Modal(elDetalles, { backdrop: 'static', keyboard: true });
    }
    
    // Fechas por defecto: últimos 30 días
    const hoy = new Date();
    const hace30Dias = new Date();
    hace30Dias.setDate(hoy.getDate() - 30);
    
    document.getElementById('filtroFechaDesde').value = hace30Dias.toISOString().split('T')[0];
    document.getElementById('filtroFechaHasta').value = hoy.toISOString().split('T')[0];
    document.getElementById('fechaDevolucion').value = hoy.toISOString().split('T')[0];
    
    filtros.fecha_desde = document.getElementById('filtroFechaDesde').value;
    filtros.fecha_hasta = document.getElementById('filtroFechaHasta').value;
    
    // Mostrar/ocultar campos según tipo de devolución
    document.getElementById('tipoDevolucion').addEventListener('change', function() {
        const tipo = this.value;
        const divCliente = document.getElementById('divCliente');
        const divProveedor = document.getElementById('divProveedor');
        
        if (tipo === '1') { // CLIENTE
            divCliente.style.display = 'block';
            divProveedor.style.display = 'none';
            document.getElementById('idCliente').required = true;
            document.getElementById('idProveedor').required = false;
        } else if (tipo === '2') { // PROVEEDOR
            divCliente.style.display = 'none';
            divProveedor.style.display = 'block';
            document.getElementById('idCliente').required = false;
            document.getElementById('idProveedor').required = true;
        } else {
            divCliente.style.display = 'none';
            divProveedor.style.display = 'none';
            document.getElementById('idCliente').required = false;
            document.getElementById('idProveedor').required = false;
        }
    });
    
    // Filtros automáticos
    document.getElementById('filtroFechaDesde').addEventListener('change', function() {
        filtros.fecha_desde = this.value;
        paginaActual = 1;
        cargarDevoluciones();
        actualizarEstadisticas();
    });
    
    document.getElementById('filtroFechaHasta').addEventListener('change', function() {
        filtros.fecha_hasta = this.value;
        paginaActual = 1;
        cargarDevoluciones();
        actualizarEstadisticas();
    });
    
    document.getElementById('filtroTipo').addEventListener('change', function() {
        filtros.tipo = this.value;
        paginaActual = 1;
        cargarDevoluciones();
        actualizarEstadisticas();
    });
    
    document.getElementById('filtroEstado').addEventListener('change', function() {
        filtros.estado = this.value;
        paginaActual = 1;
        cargarDevoluciones();
        actualizarEstadisticas();
    });
    
    document.getElementById('filtroSucursal').addEventListener('change', function() {
        filtros.sucursal = this.value;
        paginaActual = 1;
        cargarDevoluciones();
        actualizarEstadisticas();
    });
    
    const buscarInput = document.getElementById('buscarDevolucion');
    buscarInput.addEventListener('input', function() {
        clearTimeout(timeoutBusqueda);
        timeoutBusqueda = setTimeout(() => {
            filtros.busqueda = this.value;
            paginaActual = 1;
            cargarDevoluciones();
            actualizarEstadisticas();
        }, 500);
    });
    
    cargarClientesSelect();
    cargarProveedoresSelect();
    cargarLotesSelect();
    cargarDevoluciones();
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

function cargarClientesSelect() {
    fetch(RUTAS_API.listarClientes)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.clientes) {
                const select = document.getElementById('idCliente');
                select.innerHTML = '<option value="">Seleccionar cliente...</option>';
                data.clientes.forEach(c => {
                    const option = document.createElement('option');
                    option.value = c.id_cliente;
                    option.textContent = c.nombre;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error cargando clientes:', error));
}

function cargarProveedoresSelect() {
    fetch(RUTAS_API.listarProveedores)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.proveedores) {
                const select = document.getElementById('idProveedor');
                select.innerHTML = '<option value="">Seleccionar proveedor...</option>';
                data.proveedores.forEach(p => {
                    const option = document.createElement('option');
                    option.value = p.id_proveedor;
                    option.textContent = p.nombre;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error cargando proveedores:', error));
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

function agregarProductoDevolucion() {
    if (!window.lotesDisponibles || window.lotesDisponibles.length === 0) {
        Swal.fire('Error', 'No hay lotes disponibles', 'error');
        return;
    }
    
    // Crear modal de selección de producto
    Swal.fire({
        title: 'Agregar producto',
        html: `
            <div class="mb-3">
                <label class="form-label">Lote</label>
                <select class="form-select" id="selectLoteProducto">
                    <option value="">Seleccionar lote...</option>
                    ${window.lotesDisponibles.map(l => `<option value="${l.id_lote}" data-numero="${l.numero_lote}" data-medicamento="${l.medicamento_nombre}">${l.numero_lote} - ${l.medicamento_nombre}</option>`).join('')}
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Cantidad</label>
                <input type="number" class="form-control" id="cantidadProducto" min="1" value="1">
            </div>
            <div class="mb-3">
                <label class="form-label">Precio Unitario</label>
                <input type="number" step="0.01" class="form-control" id="precioProducto" min="0" value="0">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Agregar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const loteSelect = document.getElementById('selectLoteProducto');
            const idLote = loteSelect.value;
            const cantidad = document.getElementById('cantidadProducto').value;
            const precio = document.getElementById('precioProducto').value;
            
            if (!idLote) {
                Swal.showValidationMessage('Seleccione un lote');
                return false;
            }
            if (!cantidad || cantidad <= 0) {
                Swal.showValidationMessage('Cantidad inválida');
                return false;
            }
            if (!precio || precio < 0) {
                Swal.showValidationMessage('Precio inválido');
                return false;
            }
            
            const lote = window.lotesDisponibles.find(l => l.id_lote == idLote);
            return { id_lote: idLote, numero_lote: lote.numero_lote, medicamento: lote.medicamento_nombre, cantidad: parseInt(cantidad), precio: parseFloat(precio) };
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const p = result.value;
            productosDevolucion.push(p);
            renderizarProductosDevolucion();
        }
    });
}

function eliminarProductoDevolucion(index) {
    productosDevolucion.splice(index, 1);
    renderizarProductosDevolucion();
}

function renderizarProductosDevolucion() {
    const tbody = document.getElementById('productosDevolucionBody');
    let total = 0;
    
    if (productosDevolucion.length === 0) {
        tbody.innerHTML = '<tr id="filaProductoVacia"><td colspan="6" class="text-center text-muted">No hay productos agregados</td></tr>';
        document.getElementById('totalDevolucion').innerHTML = 'RD$ 0.00';
        return;
    }
    
    let html = '';
    productosDevolucion.forEach((p, index) => {
        const subtotal = p.cantidad * p.precio;
        total += subtotal;
        html += `<tr>
            <td>${escapeHtml(p.medicamento)}</td>
            <td><code>${escapeHtml(p.numero_lote)}</code></td>
            <td>${p.cantidad}</td>
            <td>RD$ ${formatNum(p.precio)}</td>
            <td>RD$ ${formatNum(subtotal)}</td>
            <td><button type="button" class="btn btn-sm btn-danger" onclick="eliminarProductoDevolucion(${index})"><span class="material-symbols-rounded">delete</span></button></td>
        </tr>`;
    });
    tbody.innerHTML = html;
    document.getElementById('totalDevolucion').innerHTML = `RD$ ${formatNum(total)}`;
}

function actualizarEstadisticas() {
    let url = `${RUTAS_API.estadisticas}?`;
    if (filtros.fecha_desde) url += `fecha_desde=${filtros.fecha_desde}&`;
    if (filtros.fecha_hasta) url += `fecha_hasta=${filtros.fecha_hasta}&`;
    if (filtros.tipo) url += `tipo=${filtros.tipo}&`;
    if (filtros.estado) url += `estado=${filtros.estado}&`;
    if (filtros.sucursal) url += `sucursal=${filtros.sucursal}&`;
    if (filtros.busqueda) url += `busqueda=${encodeURIComponent(filtros.busqueda)}&`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statTotal').textContent = data.total || 0;
                document.getElementById('statPendientes').textContent = data.pendientes || 0;
                document.getElementById('statCompletadas').textContent = data.completadas || 0;
                document.getElementById('statRechazadas').textContent = data.rechazadas || 0;
            }
        })
        .catch(error => console.error('Error:', error));
}

function cargarDevoluciones() {
    const tbody = document.getElementById('tablaDevolucionesBody');
    tbody.innerHTML = `<tr><td colspan="9" class="text-center"><div class="spinner-border text-primary"></div><p>Cargando...</p></td></tr>`;
    
    let url = `${RUTAS_API.listar}?pagina=${paginaActual}&limite=${filasPorPagina}`;
    if (filtros.fecha_desde) url += `&fecha_desde=${filtros.fecha_desde}`;
    if (filtros.fecha_hasta) url += `&fecha_hasta=${filtros.fecha_hasta}`;
    if (filtros.tipo) url += `&tipo=${filtros.tipo}`;
    if (filtros.estado) url += `&estado=${filtros.estado}`;
    if (filtros.sucursal) url += `&sucursal=${filtros.sucursal}`;
    if (filtros.busqueda) url += `&busqueda=${encodeURIComponent(filtros.busqueda)}`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                devolucionesData = data.devoluciones;
                renderizarTabla(devolucionesData);
                actualizarPaginacion(data.total);
            } else {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error: ${data.message}</td></tr>`;
            }
        })
        .catch(() => {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error de conexión</td></tr>`;
        });
}

function renderizarTabla(devoluciones) {
    const tbody = document.getElementById('tablaDevolucionesBody');
    if (!devoluciones || devoluciones.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted">No hay devoluciones registradas</td></tr>`;
        return;
    }
    
    let html = '';
    
    devoluciones.forEach(d => {
        let estadoBadge = '';
        switch(d.estado_nombre) {
            case 'SOLICITADA': estadoBadge = '<span class="badge bg-warning text-dark">SOLICITADA</span>'; break;
            case 'APROBADA': estadoBadge = '<span class="badge bg-info">APROBADA</span>'; break;
            case 'RECHAZADA': estadoBadge = '<span class="badge bg-danger">RECHAZADA</span>'; break;
            case 'COMPLETADA': estadoBadge = '<span class="badge bg-success">COMPLETADA</span>'; break;
            case 'ANULADA': estadoBadge = '<span class="badge bg-secondary">ANULADA</span>'; break;
            default: estadoBadge = '<span class="badge bg-secondary">' + d.estado_nombre + '</span>';
        }
        
        let tipoBadge = '';
        switch(d.tipo_nombre) {
            case 'CLIENTE': tipoBadge = '<span class="badge bg-primary">CLIENTE</span>'; break;
            case 'PROVEEDOR': tipoBadge = '<span class="badge bg-success">PROVEEDOR</span>'; break;
            case 'MERMA': tipoBadge = '<span class="badge bg-warning text-dark">MERMA</span>'; break;
            case 'AJUSTE': tipoBadge = '<span class="badge bg-secondary">AJUSTE</span>'; break;
            default: tipoBadge = '<span class="badge bg-secondary">' + d.tipo_nombre + '</span>';
        }
        
        const clienteProveedor = d.cliente_nombre || d.proveedor_nombre || '-';
        
        html += `<tr>
            <td class="ps-4"><code>${escapeHtml(d.numero_documento)}</code></td>
            <td><small>${formatDate(d.fecha_solicitud)}</small></td>
            <td>${tipoBadge}</td>
            <td>${escapeHtml(clienteProveedor)}</td>
            <td>${escapeHtml(d.sucursal_nombre || '-')}</td>
            <td><small>${escapeHtml(d.motivo ? d.motivo.substring(0, 50) : '-')}${d.motivo && d.motivo.length > 50 ? '...' : ''}</small></td>
            <td class="text-success fw-bold">RD$ ${formatNum(d.monto_reembolso || 0)}</td>
            <td>${estadoBadge}</td>
            <td class="text-center">
                <div class="d-flex justify-content-center gap-2">
                    <button class="btn btn-sm btn-light text-info" onclick="verDetalles(${d.id_devolucion})" title="Ver">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                    <button class="btn btn-sm btn-light text-primary" onclick="editarDevolucion(${d.id_devolucion})" title="Editar">
                        <span class="material-symbols-rounded">edit_square</span>
                    </button>
                </div>
            </td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function abrirModalNuevaDevolucion() {
    document.getElementById('modalTitulo').innerHTML = '<span class="material-symbols-rounded me-2">add</span> Nueva Devolución';
    document.getElementById('formDevolucion').reset();
    document.getElementById('devolucionId').value = '';
    document.getElementById('fechaDevolucion').value = new Date().toISOString().split('T')[0];
    document.getElementById('tipoDevolucion').value = '';
    document.getElementById('divCliente').style.display = 'none';
    document.getElementById('divProveedor').style.display = 'none';
    productosDevolucion = [];
    renderizarProductosDevolucion();
    modalDevolucion.show();
}

function editarDevolucion(id) {
    Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch(`${RUTAS_API.detalle}?id=${id}`)
        .then(r => r.json())
        .then(data => {
            Swal.close();
            if (data.success && data.devolucion) {
                const d = data.devolucion;
                document.getElementById('modalTitulo').innerHTML = '<span class="material-symbols-rounded me-2">edit</span> Editar Devolución';
                document.getElementById('devolucionId').value = d.id_devolucion;
                document.getElementById('tipoDevolucion').value = d.id_tipo;
                document.getElementById('fechaDevolucion').value = d.fecha_solicitud.split('T')[0];
                document.getElementById('sucursalDevolucion').value = d.id_sucursal;
                document.getElementById('estadoDevolucion').value = d.id_estado;
                document.getElementById('motivoDevolucion').value = d.motivo;
                
                if (d.id_cliente) {
                    document.getElementById('idCliente').value = d.id_cliente;
                    document.getElementById('divCliente').style.display = 'block';
                    document.getElementById('divProveedor').style.display = 'none';
                } else if (d.id_proveedor) {
                    document.getElementById('idProveedor').value = d.id_proveedor;
                    document.getElementById('divCliente').style.display = 'none';
                    document.getElementById('divProveedor').style.display = 'block';
                }
                
                if (d.detalles && d.detalles.length > 0) {
                    productosDevolucion = d.detalles.map(item => ({
                        id_lote: item.id_lote,
                        numero_lote: item.numero_lote,
                        medicamento: item.medicamento_nombre,
                        cantidad: item.cantidad,
                        precio: parseFloat(item.precio_unitario)
                    }));
                    renderizarProductosDevolucion();
                }
                
                modalDevolucion.show();
            } else {
                Swal.fire('Error', data.message || 'No se pudo cargar la devolución', 'error');
            }
        })
        .catch(() => {
            Swal.close();
            Swal.fire('Error de conexión', '', 'error');
        });
}

function guardarDevolucion() {
    const idDevolucion = document.getElementById('devolucionId').value;
    const idTipo = document.getElementById('tipoDevolucion').value;
    const fecha = document.getElementById('fechaDevolucion').value;
    const idSucursal = document.getElementById('sucursalDevolucion').value;
    const idEstado = document.getElementById('estadoDevolucion').value;
    const motivo = document.getElementById('motivoDevolucion').value;
    const idCliente = document.getElementById('idCliente').value;
    const idProveedor = document.getElementById('idProveedor').value;
    
    if (!idTipo || !fecha || !idSucursal || !motivo) {
        Swal.fire('Error', 'Complete los campos requeridos', 'error');
        return;
    }
    
    if (idTipo === '1' && !idCliente) {
        Swal.fire('Error', 'Seleccione un cliente', 'error');
        return;
    }
    
    if (idTipo === '2' && !idProveedor) {
        Swal.fire('Error', 'Seleccione un proveedor', 'error');
        return;
    }
    
    if (productosDevolucion.length === 0) {
        Swal.fire('Error', 'Agregue al menos un producto', 'error');
        return;
    }
    
    const total = productosDevolucion.reduce((sum, p) => sum + (p.cantidad * p.precio), 0);
    
    const datos = {
        id_devolucion: idDevolucion || null,
        id_tipo: parseInt(idTipo),
        fecha: fecha,
        id_sucursal: parseInt(idSucursal),
        id_estado: parseInt(idEstado),
        motivo: motivo,
        id_cliente: idCliente ? parseInt(idCliente) : null,
        id_proveedor: idProveedor ? parseInt(idProveedor) : null,
        monto_reembolso: total,
        detalles: productosDevolucion.map(p => ({
            id_lote: p.id_lote,
            cantidad: p.cantidad,
            precio_unitario: p.precio
        }))
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
            Swal.fire({ icon: 'success', title: datos.id_devolucion ? '¡Actualizada!' : '¡Creada!', text: data.message, timer: 1500, showConfirmButton: false })
            .then(() => {
                modalDevolucion.hide();
                cargarDevoluciones();
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
    modalBody.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Cargando detalles...</p></div>`;
    
    fetch(`${RUTAS_API.detalle}?id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.devolucion) {
                detallesActualData = data.devolucion;
                const d = data.devolucion;
                
                let estadoBadge = '';
                switch(d.estado_nombre) {
                    case 'SOLICITADA': estadoBadge = '<span class="badge bg-warning text-dark">SOLICITADA</span>'; break;
                    case 'APROBADA': estadoBadge = '<span class="badge bg-info">APROBADA</span>'; break;
                    case 'RECHAZADA': estadoBadge = '<span class="badge bg-danger">RECHAZADA</span>'; break;
                    case 'COMPLETADA': estadoBadge = '<span class="badge bg-success">COMPLETADA</span>'; break;
                    case 'ANULADA': estadoBadge = '<span class="badge bg-secondary">ANULADA</span>'; break;
                    default: estadoBadge = '<span class="badge bg-secondary">' + d.estado_nombre + '</span>';
                }
                
                let tipoBadge = '';
                switch(d.tipo_nombre) {
                    case 'CLIENTE': tipoBadge = '<span class="badge bg-primary">CLIENTE</span>'; break;
                    case 'PROVEEDOR': tipoBadge = '<span class="badge bg-success">PROVEEDOR</span>'; break;
                    case 'MERMA': tipoBadge = '<span class="badge bg-warning text-dark">MERMA</span>'; break;
                    case 'AJUSTE': tipoBadge = '<span class="badge bg-secondary">AJUSTE</span>'; break;
                    default: tipoBadge = '<span class="badge bg-secondary">' + d.tipo_nombre + '</span>';
                }
                
                let detallesHtml = '';
                if (d.detalles && d.detalles.length > 0) {
                    detallesHtml = `
                        <div class="mt-4">
                            <h6 class="fw-bold text-muted mb-3">PRODUCTOS DEVUELTOS</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="bg-light">
                                        <tr><th>Medicamento</th><th>Lote</th><th class="text-center">Cantidad</th><th class="text-center">Precio Unitario</th><th class="text-center">Subtotal</th></tr>
                                    </thead>
                                    <tbody>
                                        ${d.detalles.map(item => `
                                            <tr>
                                                <td><strong>${escapeHtml(item.medicamento_nombre)}</strong><br><small>${escapeHtml(item.presentacion || '')}</small></td>
                                                <td><code>${escapeHtml(item.numero_lote)}</code></td>
                                                <td class="text-center">${item.cantidad}</td>
                                                <td class="text-center">RD$ ${formatNum(item.precio_unitario)}</td>
                                                <td class="text-center text-success fw-bold">RD$ ${formatNum(item.cantidad * item.precio_unitario)}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                    <tfoot class="bg-light">
                                        <tr><td colspan="4" class="text-end"><strong>TOTAL DEVUELTO</strong></td><td class="text-center"><strong class="text-success">RD$ ${formatNum(d.monto_reembolso || 0)}</strong></td></tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    `;
                }
                
                modalBody.innerHTML = `
                    <div class="bg-primary-subtle rounded-circle d-inline-flex p-4 mb-3">
                        <span class="material-symbols-rounded text-primary" style="font-size: 3rem;">swap_vert</span>
                    </div>
                    <h3 class="fw-bold mb-1">Devolución ${escapeHtml(d.numero_documento)}</h3>
                    <span class="badge bg-primary-subtle text-primary mb-4">#${d.id_devolucion}</span>
                    
                    <div class="row g-4 text-start mt-2">
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Tipo</small><span>${tipoBadge}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Estado</small><span>${estadoBadge}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Fecha Solicitud</small><span class="fw-bold">${formatDate(d.fecha_solicitud)}</span></div></div>
                        <div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Sucursal</small><span>${escapeHtml(d.sucursal_nombre || '-')}</span></div></div>
                        ${d.cliente_nombre ? `<div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Cliente</small><span class="fw-bold">${escapeHtml(d.cliente_nombre)}</span></div></div>` : ''}
                        ${d.proveedor_nombre ? `<div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Proveedor</small><span class="fw-bold">${escapeHtml(d.proveedor_nombre)}</span></div></div>` : ''}
                        <div class="col-12"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Motivo</small><span>${escapeHtml(d.motivo || '-')}</span></div></div>
                        ${d.fecha_aprobacion ? `<div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Fecha Aprobación</small><span>${formatDate(d.fecha_aprobacion)}</span></div></div>` : ''}
                        ${d.fecha_completada ? `<div class="col-6"><div class="detalle-item"><small class="text-muted d-block fw-bold text-uppercase">Fecha Completada</small><span>${formatDate(d.fecha_completada)}</span></div></div>` : ''}
                    </div>
                    ${detallesHtml}
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
        editarDevolucion(detallesActualId);
    }
}

function exportarPDF() {
    if (!devolucionesData || devolucionesData.length === 0) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    const empresaNombre = '<?php echo addslashes($empresa_nombre ?? "Sistema Gestor de Farmacias"); ?>';
    
    let htmlContent = `
        <html>
        <head><meta charset="UTF-8"><title>Reporte de Devoluciones</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; font-size: 11px; padding: 20px; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; }
            .empresa h2 { color: #0d6efd; font-size: 18px; }
            .titulo { background: #0d6efd; color: white; padding: 8px; text-align: center; border-radius: 5px; margin-bottom: 15px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #0d6efd; color: white; padding: 8px; text-align: left; font-size: 10px; }
            td { padding: 6px 8px; border-bottom: 1px solid #ddd; font-size: 9px; }
            .footer { text-align: center; margin-top: 20px; font-size: 9px; color: #666; }
        </style>
        </head>
        <body>
            <div class="header">
                <div class="empresa"><h2>${empresaNombre}</h2></div>
                <div class="titulo"><h1>REPORTE DE DEVOLUCIONES</h1></div>
            </div>
            <table>
                <thead>
                    <tr><th>N° Documento</th><th>Fecha</th><th>Tipo</th><th>Cliente/Proveedor</th><th>Sucursal</th><th>Motivo</th><th>Monto</th><th>Estado</th></tr>
                </thead>
                <tbody>`;
    
    devolucionesData.forEach(d => {
        const clienteProveedor = d.cliente_nombre || d.proveedor_nombre || '-';
        htmlContent += `<tr>
            <td>${escapeHtml(d.numero_documento)}</td>
            <td>${formatDate(d.fecha_solicitud)}</td>
            <td>${d.tipo_nombre}</td>
            <td>${escapeHtml(clienteProveedor)}</td>
            <td>${escapeHtml(d.sucursal_nombre || '-')}</td>
            <td>${escapeHtml(d.motivo ? d.motivo.substring(0, 50) : '-')}</td>
            <td>RD$ ${formatNum(d.monto_reembolso || 0)}</td>
            <td>${d.estado_nombre}</td>
        </tr>`;
    });
    
    htmlContent += `</tbody></table><div class="footer">Reporte generado el ${new Date().toLocaleString('es-DO')}</div></body></html>`;
    
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    
    const opt = { margin: [0.5, 0.5, 0.5, 0.5], filename: `devoluciones_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' } };
    
    html2pdf().set(opt).from(element).save().then(() => { document.body.removeChild(element); Swal.fire({ icon: 'success', title: 'Exportado', timer: 1500, showConfirmButton: false }); }).catch(() => { document.body.removeChild(element); Swal.fire('Error', 'Error al generar el PDF', 'error'); });
}

function exportarIndividualPDF() {
    if (!detallesActualData) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    const d = detallesActualData;
    const empresaNombre = '<?php echo addslashes($empresa_nombre ?? "Sistema Gestor de Farmacias"); ?>';
    
    let detallesHtml = '';
    if (d.detalles && d.detalles.length > 0) {
        detallesHtml = `
            <h3 style="margin-top:20px;">Productos Devueltos</h3>
            <table style="width:100%; border-collapse:collapse; margin-top:10px;">
                <thead>
                    <tr style="background:#0d6efd; color:white;">
                        <th style="padding:8px; text-align:left;">Medicamento</th>
                        <th style="padding:8px; text-align:left;">Lote</th>
                        <th style="padding:8px; text-align:center;">Cantidad</th>
                        <th style="padding:8px; text-align:center;">Precio Unitario</th>
                        <th style="padding:8px; text-align:center;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    ${d.detalles.map(item => `
                        <tr>
                            <td style="padding:6px; border-bottom:1px solid #ddd;">${escapeHtml(item.medicamento_nombre)}</td>
                            <td style="padding:6px; border-bottom:1px solid #ddd;">${escapeHtml(item.numero_lote)}</td>
                            <td style="padding:6px; border-bottom:1px solid #ddd; text-align:center;">${item.cantidad}</td>
                            <td style="padding:6px; border-bottom:1px solid #ddd; text-align:center;">RD$ ${formatNum(item.precio_unitario)}</td>
                            <td style="padding:6px; border-bottom:1px solid #ddd; text-align:center;">RD$ ${formatNum(item.cantidad * item.precio_unitario)}</td>
                        </tr>
                    `).join('')}
                </tbody>
                <tfoot>
                    <tr style="background:#f0f0f0;">
                        <td colspan="4" style="padding:8px; text-align:right;"><strong>TOTAL</strong></td>
                        <td style="padding:8px; text-align:center;"><strong>RD$ ${formatNum(d.monto_reembolso || 0)}</strong></td>
                    </tr>
                </tfoot>
            </table>
        `;
    }
    
    let estadoBadge = '';
    switch(d.estado_nombre) {
        case 'SOLICITADA': estadoBadge = 'SOLICITADA'; break;
        case 'APROBADA': estadoBadge = 'APROBADA'; break;
        case 'RECHAZADA': estadoBadge = 'RECHAZADA'; break;
        case 'COMPLETADA': estadoBadge = 'COMPLETADA'; break;
        default: estadoBadge = d.estado_nombre;
    }
    
    let tipoBadge = '';
    switch(d.tipo_nombre) {
        case 'CLIENTE': tipoBadge = 'CLIENTE'; break;
        case 'PROVEEDOR': tipoBadge = 'PROVEEDOR'; break;
        case 'MERMA': tipoBadge = 'MERMA'; break;
        case 'AJUSTE': tipoBadge = 'AJUSTE'; break;
        default: tipoBadge = d.tipo_nombre;
    }
    
    const htmlContent = `<!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"><title>Devolución ${escapeHtml(d.numero_documento)}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; }
        .empresa h2 { color: #0d6efd; }
        .titulo { background: #0d6efd; color: white; padding: 8px; text-align: center; border-radius: 5px; margin-bottom: 20px; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 20px; }
        .info-item { padding: 8px; background: #f8f9fa; border-radius: 5px; }
        .info-label { font-weight: bold; color: #0d6efd; font-size: 10px; text-transform: uppercase; }
        .info-value { font-size: 12px; margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #0d6efd; color: white; padding: 8px; text-align: left; }
        td { padding: 6px 8px; border-bottom: 1px solid #ddd; }
        .footer { text-align: center; margin-top: 30px; font-size: 10px; color: #666; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
    </head>
    <body>
        <div class="header">
            <div class="empresa"><h2>${empresaNombre}</h2></div>
            <div class="titulo"><h1>REPORTE DE DEVOLUCIÓN</h1></div>
        </div>
        
        <div class="info-grid">
            <div class="info-item"><div class="info-label">Número Documento</div><div class="info-value">${escapeHtml(d.numero_documento)}</div></div>
            <div class="info-item"><div class="info-label">ID Devolución</div><div class="info-value">#${d.id_devolucion}</div></div>
            <div class="info-item"><div class="info-label">Tipo</div><div class="info-value">${tipoBadge}</div></div>
            <div class="info-item"><div class="info-label">Estado</div><div class="info-value">${estadoBadge}</div></div>
            <div class="info-item"><div class="info-label">Fecha Solicitud</div><div class="info-value">${formatDate(d.fecha_solicitud)}</div></div>
            <div class="info-item"><div class="info-label">Sucursal</div><div class="info-value">${escapeHtml(d.sucursal_nombre || '-')}</div></div>
            ${d.cliente_nombre ? `<div class="info-item"><div class="info-label">Cliente</div><div class="info-value">${escapeHtml(d.cliente_nombre)}</div></div>` : ''}
            ${d.proveedor_nombre ? `<div class="info-item"><div class="info-label">Proveedor</div><div class="info-value">${escapeHtml(d.proveedor_nombre)}</div></div>` : ''}
            <div class="info-item"><div class="info-label">Motivo</div><div class="info-value">${escapeHtml(d.motivo || '-')}</div></div>
            ${d.fecha_aprobacion ? `<div class="info-item"><div class="info-label">Fecha Aprobación</div><div class="info-value">${formatDate(d.fecha_aprobacion)}</div></div>` : ''}
            ${d.fecha_completada ? `<div class="info-item"><div class="info-label">Fecha Completada</div><div class="info-value">${formatDate(d.fecha_completada)}</div></div>` : ''}
        </div>
        
        ${detallesHtml}
        
        <div class="footer">Reporte generado el ${new Date().toLocaleString('es-DO')}</div>
    </body>
    </html>`;
    
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    
    const opt = { margin: [0.4, 0.4, 0.4, 0.4], filename: `devolucion_${d.numero_documento}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' } };
    
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

function cambiarPagina(pagina) { paginaActual = pagina; cargarDevoluciones(); }

function limpiarFiltros() {
    const hoy = new Date();
    const hace30Dias = new Date();
    hace30Dias.setDate(hoy.getDate() - 30);
    
    document.getElementById('filtroFechaDesde').value = hace30Dias.toISOString().split('T')[0];
    document.getElementById('filtroFechaHasta').value = hoy.toISOString().split('T')[0];
    document.getElementById('filtroTipo').value = '';
    document.getElementById('filtroEstado').value = '';
    document.getElementById('filtroSucursal').value = '';
    document.getElementById('buscarDevolucion').value = '';
    
    filtros = { 
        fecha_desde: document.getElementById('filtroFechaDesde').value,
        fecha_hasta: document.getElementById('filtroFechaHasta').value,
        tipo: '', 
        estado: '', 
        sucursal: '', 
        busqueda: '' 
    };
    paginaActual = 1;
    cargarDevoluciones();
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
.pagination-custom .page-item.active .page-link { background: #0d6efd !important; color: white; box-shadow: 0 4px 12px rgba(13,110,253,0.3); }
.pagination-custom .page-item:not(.active):hover .page-link { background: #e9ecef; color: #0d6efd; transform: translateY(-2px); }
.table-hover tbody tr:hover { background: rgba(13,110,253,0.05); }
.table thead th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: #6c757d; padding: 15px 12px; background: #f8f9fa; }
.form-control, .form-select { border: 1.5px solid #dee2e6 !important; border-radius: 10px; }
.form-control:focus, .form-select:focus { border-color: #0d6efd !important; box-shadow: 0 0 0 0.25rem rgba(13,110,253,0.1) !important; }
.detalle-item { background-color: #f8f9fa; border: 1.5px solid #eceef0; border-radius: 12px; padding: 12px; height: 100%; }
.bg-primary-subtle { background: rgba(13,110,253,0.1); }
code { font-size: 0.85rem; background-color: #f8f9fa; padding: 2px 6px; border-radius: 4px; }
</style>