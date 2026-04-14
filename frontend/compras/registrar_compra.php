<?php
require_once __DIR__ . '/../../backend/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_sesion'])) {
    header("Location: ../index.php");
    exit();
}

// Obtener listas para selects
$proveedores = [];
$sucursales = [];
$usuarios = [];

try {
    $proveedores = $conexion->query("SELECT id_proveedor, nombre FROM proveedores ORDER BY nombre")->fetchAll();
    $sucursales = $conexion->query("SELECT id_sucursal, nombre FROM sucursales WHERE estado = true ORDER BY nombre")->fetchAll();
    $usuarios = $conexion->query("SELECT id_usuario, nombre FROM usuarios WHERE estado = true ORDER BY nombre")->fetchAll();
} catch(PDOException $e) {}

// Generar número de compra
function generarNumeroCompra($conexion) {
    try {
        $stmt = $conexion->query("SELECT numero_documento FROM compras WHERE numero_documento LIKE 'COMP-%' ORDER BY id_compra DESC LIMIT 1");
        $ultimo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ultimo) {
            $numero = intval(substr($ultimo['numero_documento'], 5));
            $nuevo = $numero + 1;
        } else {
            $nuevo = 1;
        }
        return 'COMP-' . str_pad($nuevo, 6, '0', STR_PAD_LEFT);
    } catch(PDOException $e) {
        return 'COMP-' . date('Ymd') . '-0001';
    }
}

$numero_compra = generarNumeroCompra($conexion);
$usuario_actual = $_SESSION['id_usuario'] ?? 1;
$itbis_porcentaje = 18;
$base_url = '/sistema-gestor-de-farmacias';
?>

<style>
    /* ===== ESTILOS ===== */
    .modal-backdrop { display: none !important; }
    .modal { background-color: rgba(0, 0, 0, 0.5) !important; z-index: 1050; }
    .modal-dialog-centered { display: flex; align-items: center; min-height: calc(100% - 1rem); }
    .modal.show .modal-dialog { transform: none; margin: 1.75rem auto; }
    
    .tabla-carrito th {
        background-color: #f8f9fa;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
        color: #6c757d;
        padding: 12px;
    }
    .tabla-carrito td { vertical-align: middle; padding: 10px; }
    .cantidad-input {
        width: 70px;
        text-align: center;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 5px;
    }
    .btn-eliminar-item {
        background: none;
        border: none;
        color: #dc3545;
        cursor: pointer;
    }
    .resumen-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
        position: sticky;
        top: 20px;
    }
    .resumen-linea {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #dee2e6;
    }
    .resumen-linea.total {
        font-size: 1.2rem;
        font-weight: 700;
        color: #28a745;
        border-bottom: none;
    }
    .form-control, .form-select { border: 1.5px solid #dee2e6 !important; border-radius: 10px; }
    .form-control:focus, .form-select:focus { border-color: #28a745 !important; box-shadow: 0 0 0 0.25rem rgba(40,167,69,0.1) !important; }
    .dashboard-container { padding: 20px; animation: fadeSlideIn 0.5s ease-out; }
    @keyframes fadeSlideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .card { border-radius: 12px; overflow: hidden; }
    .btn-cancelar { background-color: #f1f3f5; color: #495057; border: 1.5px solid #dee2e6; border-radius: 10px; padding: 10px 25px; font-weight: 600; }
    .btn-cancelar:hover { background-color: #e9ecef; }
    
    .producto-card {
        transition: all 0.2s ease;
        cursor: pointer;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        padding: 12px;
        margin-bottom: 10px;
    }
    .producto-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: #28a745;
        background-color: #f8fff9;
    }
    .producto-card .nombre {
        font-weight: 600;
        color: #2c3e50;
        font-size: 0.95rem;
    }
    .badge-itbis {
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 20px;
    }
    .badge-itbis.aplica { background-color: #28a745; color: white; }
    .badge-itbis.exento { background-color: #6c757d; color: white; }
    .filtro-busqueda {
        position: sticky;
        top: 0;
        background: white;
        z-index: 10;
        padding-bottom: 10px;
    }
    .lista-productos {
        max-height: 500px;
        overflow-y: auto;
    }
    .badge-stock {
        background-color: #6c757d;
        color: white;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 0.7rem;
        margin-left: 8px;
    }
</style>

<div class="dashboard-container">
    <div class="mb-4">
        <h2 class="mb-0 text-success">
            <span class="material-symbols-rounded align-middle me-2">shopping_cart_checkout</span>
            Registrar Compra
        </h2>
        <p class="text-muted mb-0">Ingrese los datos de la compra a proveedores</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Datos de la Compra -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <h5 class="card-title mb-3 d-flex align-items-center">
                        <span class="material-symbols-rounded me-2 text-success">receipt</span>
                        Datos de la Compra
                    </h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Nº DOCUMENTO</label>
                            <input type="text" class="form-control" id="numeroDocumento" value="<?php echo $numero_compra; ?>" readonly style="background-color: #f8f9fa;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">FECHA</label>
                            <input type="text" class="form-control" id="fechaCompra" value="<?php echo date('d/m/Y H:i'); ?>" readonly style="background-color: #f8f9fa;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">USUARIO</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['usuario'] ?? 'Administrador'); ?>" readonly style="background-color: #f8f9fa;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">PROVEEDOR</label>
                            <select class="form-select" id="proveedor">
                                <option value="">Seleccionar proveedor...</option>
                                <?php foreach ($proveedores as $prov): ?>
                                    <option value="<?php echo $prov['id_proveedor']; ?>"><?php echo htmlspecialchars($prov['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">SUCURSAL</label>
                            <select class="form-select" id="sucursal">
                                <option value="">Seleccionar sucursal...</option>
                                <?php foreach ($sucursales as $suc): ?>
                                    <option value="<?php echo $suc['id_sucursal']; ?>"><?php echo htmlspecialchars($suc['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">OBSERVACIONES</label>
                            <textarea class="form-control" id="observaciones" rows="2" placeholder="Notas adicionales..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">FECHA ESPERADA DE ENTREGA</label>
                            <input type="date" class="form-control" id="fechaEsperada">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botón agregar producto -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4 text-center">
                    <button type="button" class="btn btn-success btn-lg px-5" id="btnAgregarProducto">
                        <span class="material-symbols-rounded align-middle me-2">add_shopping_cart</span>
                        Agregar Producto
                    </button>
                    <p class="text-muted mt-2 mb-0 small">Seleccione medicamentos para esta compra</p>
                </div>
            </div>

            <!-- Tabla del carrito de compras -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table tabla-carrito mb-0">
                            <thead>
                                <tr>
                                    <th>PRODUCTO</th>
                                    <th>LOTE</th>
                                    <th>VENCE</th>
                                    <th>CANT.</th>
                                    <th>PRECIO UNIT.</th>
                                    <th>DTO.</th>
                                    <th>ITBIS</th>
                                    <th>SUBTOTAL</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="carritoBody">
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="fas fa-shopping-cart" style="font-size: 2rem; opacity: 0.3;"></i>
                                        <p class="mt-2">No hay productos agregados</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="resumen-card">
                <h5 class="mb-3 d-flex align-items-center">
                    <span class="material-symbols-rounded me-2 text-success">summary</span>
                    Resumen de Compra
                </h5>
                <div class="resumen-linea"><span>Subtotal:</span><span id="resumenSubtotal">RD$ 0.00</span></div>
                <div class="resumen-linea"><span>Descuento:</span><span id="resumenDescuento">RD$ 0.00</span></div>
                <div class="resumen-linea"><span>ITBIS (<?php echo $itbis_porcentaje; ?>%):</span><span id="resumenItbis">RD$ 0.00</span></div>
                <div class="resumen-linea total"><span><strong>TOTAL:</strong></span><span id="resumenTotal" class="text-success fw-bold">RD$ 0.00</span></div>
                <hr>
                <div class="d-grid gap-2 mt-3">
                    <button class="btn btn-success btn-lg" onclick="procesarCompra()">
                        <span class="material-symbols-rounded align-middle me-1">check_circle</span> Registrar Compra
                    </button>
                    <button class="btn btn-outline-secondary" onclick="cancelarCompra()">
                        <span class="material-symbols-rounded align-middle me-1">cancel</span> Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA SELECCIONAR PRODUCTOS -->
<div class="modal fade" id="modalProductos" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-success text-white p-4">
                <h5 class="modal-title d-flex align-items-center">
                    <span class="material-symbols-rounded me-2">medication</span>
                    Seleccionar Producto
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="filtro-busqueda">
                    <input type="text" class="form-control form-control-lg" id="filtroNombreProducto" placeholder="🔍 Buscar por nombre...">
                </div>
                <div class="lista-productos mt-3" id="listaProductosModal">
                    <div class="text-center py-5">
                        <div class="spinner-border text-success" role="status"></div>
                        <p class="mt-2">Cargando productos...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA REGISTRAR LOTE Y CANTIDAD -->
<div class="modal fade" id="modalLote" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-success text-white p-4">
                <h5 class="modal-title d-flex align-items-center">
                    <span class="material-symbols-rounded me-2">inventory</span>
                    Datos del Lote
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-3">
                    <i class="fas fa-pills" style="font-size: 3rem; color: #28a745;"></i>
                </div>
                <h5 class="text-center" id="productoNombreModal"></h5>
                <div class="row g-3 mt-2">
                    <div class="col-12">
                        <label class="form-label fw-bold">Número de Lote</label>
                        <input type="text" class="form-control" id="numeroLote" placeholder="Ej: LOTE-001">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Fecha de Vencimiento</label>
                        <input type="date" class="form-control" id="fechaVencimiento">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Cantidad</label>
                        <input type="number" class="form-control" id="cantidadLote" value="1" min="1">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Costo Unitario (RD$)</label>
                        <input type="number" step="0.01" class="form-control" id="costoUnitario" placeholder="0.00">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Descuento Unitario (RD$)</label>
                        <input type="number" step="0.01" class="form-control" id="descuentoUnitario" value="0">
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="aplicaItbis" checked>
                            <label class="form-check-label" for="aplicaItbis">Aplica ITBIS (18%)</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-end gap-3">
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success px-4" id="btnAgregarLote">
                    <span class="material-symbols-rounded align-middle me-1">add</span>
                    Agregar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';
const ID_USUARIO_ACTUAL = <?php echo $usuario_actual; ?>;
const ITBIS_PORCENTAJE = <?php echo $itbis_porcentaje; ?>;

let carrito = [];
let productoSeleccionado = null;
let modalProductos = null;
let modalLote = null;
let productosData = [];

// ==================== INICIALIZACIÓN ====================
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar modales
    const elModalProductos = document.getElementById('modalProductos');
    const elModalLote = document.getElementById('modalLote');
    
    if (elModalProductos) {
        modalProductos = new bootstrap.Modal(elModalProductos, { backdrop: false });
    }
    if (elModalLote) {
        modalLote = new bootstrap.Modal(elModalLote, { backdrop: false });
    }
    
    // Evento para el botón "Agregar Producto"
    const btnAgregarProducto = document.getElementById('btnAgregarProducto');
    if (btnAgregarProducto) {
        btnAgregarProducto.addEventListener('click', function() {
            if (modalProductos) modalProductos.show();
        });
    }
    
    // Evento para el botón "Agregar" dentro del modal de lote
    const btnAgregarLote = document.getElementById('btnAgregarLote');
    if (btnAgregarLote) {
        btnAgregarLote.addEventListener('click', agregarProductoCompra);
    }
    
    // Filtro de búsqueda en el modal de productos
    const filtroNombre = document.getElementById('filtroNombreProducto');
    if (filtroNombre) {
        filtroNombre.addEventListener('input', filtrarProductos);
    }
    
    // Cargar productos cuando se abre el modal
    if (elModalProductos) {
        elModalProductos.addEventListener('show.bs.modal', cargarProductosModal);
    }
});

// ==================== CARGAR PRODUCTOS (MODO COMPRA) ====================
function cargarProductosModal() {
    const listaDiv = document.getElementById('listaProductosModal');
    listaDiv.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando...</p></div>';
    
    fetch(BASE_URL + '/backend/ventas/listar_productos_venta.php?tipo=compra')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.productos) {
                productosData = data.productos;
                renderizarProductosModal(productosData);
            } else {
                listaDiv.innerHTML = '<div class="text-center py-5 text-danger">Error al cargar productos</div>';
            }
        })
        .catch(() => listaDiv.innerHTML = '<div class="text-center py-5 text-danger">Error de conexión</div>');
}

function renderizarProductosModal(productos) {
    const listaDiv = document.getElementById('listaProductosModal');
    if (!productos.length) {
        listaDiv.innerHTML = '<div class="text-center py-5 text-muted">No hay productos disponibles</div>';
        return;
    }
    let html = '<div class="row">';
    productos.forEach(p => {
        const itbisBadge = p.exento_itbis ? 
            '<span class="badge-itbis exento">Exento ITBIS</span>' : 
            '<span class="badge-itbis aplica">Aplica ITBIS</span>';
        // Mostrar stock total si existe
        const stockBadge = (p.stock_total !== undefined) ? `<span class="badge-stock">Stock: ${p.stock_total}</span>` : '';
        html += `
            <div class="col-md-6 col-lg-4">
                <div class="producto-card" onclick="seleccionarProducto(${p.id_medicamento}, '${escapeHtml(p.nombre)}', ${p.exento_itbis ? 0 : 1})">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="nombre">${escapeHtml(p.nombre)}</div>
                        ${stockBadge}
                    </div>
                    <div class="small text-muted mt-1">${escapeHtml(p.presentacion || '')} - ${escapeHtml(p.categoria || '')}</div>
                    <div class="mt-2">${itbisBadge}</div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    listaDiv.innerHTML = html;
}

function filtrarProductos() {
    const filtro = document.getElementById('filtroNombreProducto').value.toLowerCase();
    const filtrados = productosData.filter(p => p.nombre.toLowerCase().includes(filtro));
    renderizarProductosModal(filtrados);
}

function seleccionarProducto(idMedicamento, nombre, aplicaItbisDefault) {
    productoSeleccionado = { id_medicamento: idMedicamento, nombre: nombre, aplica_itbis_default: aplicaItbisDefault };
    document.getElementById('productoNombreModal').innerText = nombre;
    document.getElementById('numeroLote').value = '';
    document.getElementById('fechaVencimiento').value = '';
    document.getElementById('cantidadLote').value = 1;
    document.getElementById('costoUnitario').value = '';
    document.getElementById('descuentoUnitario').value = 0;
    document.getElementById('aplicaItbis').checked = aplicaItbisDefault === 1;
    if (modalProductos) modalProductos.hide();
    if (modalLote) modalLote.show();
}

// ==================== AGREGAR AL CARRITO ====================
function agregarProductoCompra() {
    const numeroLote = document.getElementById('numeroLote').value.trim();
    const fechaVencimiento = document.getElementById('fechaVencimiento').value;
    const cantidad = parseInt(document.getElementById('cantidadLote').value);
    const costoUnitario = parseFloat(document.getElementById('costoUnitario').value);
    const descuentoUnitario = parseFloat(document.getElementById('descuentoUnitario').value);
    const aplicaItbis = document.getElementById('aplicaItbis').checked;
    
    if (!numeroLote) { Swal.fire('Error', 'Ingrese el número de lote', 'error'); return; }
    if (!fechaVencimiento) { Swal.fire('Error', 'Ingrese la fecha de vencimiento', 'error'); return; }
    if (cantidad <= 0) { Swal.fire('Error', 'Cantidad inválida', 'error'); return; }
    if (isNaN(costoUnitario) || costoUnitario <= 0) { Swal.fire('Error', 'Costo unitario inválido', 'error'); return; }
    
    const item = {
        id_medicamento: productoSeleccionado.id_medicamento,
        nombre: productoSeleccionado.nombre,
        numero_lote: numeroLote,
        fecha_vencimiento: fechaVencimiento,
        cantidad: cantidad,
        costo_unitario: costoUnitario,
        descuento_unitario: descuentoUnitario,
        aplica_itbis: aplicaItbis
    };
    carrito.push(item);
    actualizarCarrito();
    if (modalLote) modalLote.hide();
}

function actualizarCarrito() {
    const tbody = document.getElementById('carritoBody');
    if (carrito.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-shopping-cart" style="font-size:2rem; opacity:0.3"></i><p>No hay productos agregados</p></td></tr>`;
        actualizarResumen();
        return;
    }
    let html = '';
    carrito.forEach((item, idx) => {
        const subtotal = item.cantidad * item.costo_unitario;
        const descuento = item.cantidad * item.descuento_unitario;
        const base = subtotal - descuento;
        const itbis = item.aplica_itbis ? base * (ITBIS_PORCENTAJE / 100) : 0;
        const totalItem = base + itbis;
        html += `
            <tr>
                <td><strong>${escapeHtml(item.nombre)}</strong></td>
                <td>${escapeHtml(item.numero_lote)}</small></td>
                <td>${item.fecha_vencimiento}</small></td>
                <td><input type="number" class="cantidad-input" value="${item.cantidad}" min="1" onchange="actualizarCantidad(${idx}, this.value)"></td>
                <td>RD$ ${item.costo_unitario.toFixed(2)}</small></td>
                <td>RD$ ${item.descuento_unitario.toFixed(2)}</small></td>
                <td>${item.aplica_itbis ? `Sí (${ITBIS_PORCENTAJE}%)` : 'No'}</td>
                <td class="fw-bold text-success">RD$ ${totalItem.toFixed(2)}</td>
                <td class="text-center"><button class="btn-eliminar-item" onclick="eliminarProducto(${idx})"><span class="material-symbols-rounded">delete</span></button></td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
    actualizarResumen();
}

function actualizarCantidad(index, nuevaCantidad) {
    let cant = parseInt(nuevaCantidad);
    if (isNaN(cant) || cant < 1) cant = 1;
    carrito[index].cantidad = cant;
    actualizarCarrito();
}

function eliminarProducto(index) {
    carrito.splice(index, 1);
    actualizarCarrito();
}

function actualizarResumen() {
    let subtotal = 0, descuento = 0, itbis = 0;
    for (const item of carrito) {
        const sub = item.cantidad * item.costo_unitario;
        const dto = item.cantidad * item.descuento_unitario;
        subtotal += sub;
        descuento += dto;
        const base = sub - dto;
        if (item.aplica_itbis) itbis += base * (ITBIS_PORCENTAJE / 100);
    }
    const total = subtotal - descuento + itbis;
    document.getElementById('resumenSubtotal').innerHTML = `RD$ ${subtotal.toFixed(2)}`;
    document.getElementById('resumenDescuento').innerHTML = `RD$ ${descuento.toFixed(2)}`;
    document.getElementById('resumenItbis').innerHTML = `RD$ ${itbis.toFixed(2)}`;
    document.getElementById('resumenTotal').innerHTML = `RD$ ${total.toFixed(2)}`;
}

// ==================== PROCESAR COMPRA ====================
function procesarCompra() {
    const idProveedor = document.getElementById('proveedor').value;
    const idSucursal = document.getElementById('sucursal').value;
    const observaciones = document.getElementById('observaciones').value;
    const fechaEsperada = document.getElementById('fechaEsperada').value;
    
    if (!idProveedor) { Swal.fire('Error', 'Seleccione un proveedor', 'error'); return; }
    if (!idSucursal) { Swal.fire('Error', 'Seleccione una sucursal', 'error'); return; }
    if (carrito.length === 0) { Swal.fire('Error', 'Agregue al menos un producto', 'error'); return; }
    
    let subtotal = 0, descuentoTotal = 0, itbisTotal = 0;
    for (const item of carrito) {
        const sub = item.cantidad * item.costo_unitario;
        const dto = item.cantidad * item.descuento_unitario;
        subtotal += sub;
        descuentoTotal += dto;
        const base = sub - dto;
        if (item.aplica_itbis) itbisTotal += base * (ITBIS_PORCENTAJE / 100);
    }
    const total = subtotal - descuentoTotal + itbisTotal;
    
    const data = {
        numero_documento: document.getElementById('numeroDocumento').value,
        id_proveedor: parseInt(idProveedor),
        id_usuario: ID_USUARIO_ACTUAL,
        id_sucursal: parseInt(idSucursal),
        observaciones: observaciones,
        fecha_esperada: fechaEsperada || null,
        productos: carrito.map(p => ({
            id_medicamento: p.id_medicamento,
            numero_lote: p.numero_lote,
            fecha_vencimiento: p.fecha_vencimiento,
            cantidad: p.cantidad,
            costo_unitario: p.costo_unitario,
            descuento_unitario: p.descuento_unitario,
            aplica_itbis: p.aplica_itbis
        })),
        subtotal: subtotal,
        descuento: descuentoTotal,
        itbis: itbisTotal,
        total: total
    };
    
    Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch(BASE_URL + '/backend/compras/procesar_compra.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        Swal.close();
        if (res.success) {
            Swal.fire('Éxito', `Compra ${res.numero_documento} registrada`, 'success')
                .then(() => location.reload());
        } else {
            Swal.fire('Error', res.message || 'Error al registrar compra', 'error');
        }
    })
    .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
}

function cancelarCompra() {
    Swal.fire({
        title: '¿Cancelar compra?',
        text: 'Se perderán los datos ingresados',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No'
    }).then(res => { if (res.isConfirmed) location.reload(); });
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