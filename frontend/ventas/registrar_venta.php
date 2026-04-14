<?php
require_once __DIR__ . '/../../backend/conexion.php';

if (!isset($_SESSION['id_sesion'])) {
    session_start();
    if (!isset($_SESSION['id_sesion'])) {
        header("Location: ../index.php");
        exit();
    }
}

// Obtener datos para selects
$clientes = [];
$metodos_pago = [];
$condiciones_pago = [];
$sucursales = [];
$descuentosDisponibles = [];

try {
    $stmt = $conexion->query("SELECT id_cliente, nombre, permite_credito, tiene_seguro FROM clientes ORDER BY nombre");
    $clientes = $stmt->fetchAll();
    
    $stmt = $conexion->query("SELECT id_metodo, nombre FROM metodos_pago ORDER BY nombre");
    $metodos_pago = $stmt->fetchAll();
    
    $stmt = $conexion->query("SELECT id_condicion, nombre, dias_plazo FROM condicion_pago ORDER BY id_condicion");
    $condiciones_pago = $stmt->fetchAll();
    
    $stmt = $conexion->query("SELECT id_sucursal, nombre FROM sucursales WHERE estado = true ORDER BY nombre");
    $sucursales = $stmt->fetchAll();
    
    // Cargar descuentos activos
    $stmtDesc = $conexion->query("
        SELECT id_descuento, nombre, valor_descuento, es_porcentaje 
        FROM descuentos 
        WHERE activo = true 
        AND fecha_inicio <= CURRENT_DATE 
        AND fecha_fin >= CURRENT_DATE
        ORDER BY prioridad DESC, valor_descuento DESC
    ");
    $descuentosDisponibles = $stmtDesc->fetchAll();
    
} catch(PDOException $e) {}

function generarNuevoNumeroDocumento($conexion) {
    try {
        $stmt = $conexion->query("
            SELECT numero_documento 
            FROM ventas 
            WHERE numero_documento LIKE 'FAC-%' 
            ORDER BY id_venta DESC 
            LIMIT 1
        ");
        $ultimo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ultimo) {
            $numero = intval(substr($ultimo['numero_documento'], 4));
            $nuevo_numero = $numero + 1;
        } else {
            $nuevo_numero = 1;
        }
        return 'FAC-' . str_pad($nuevo_numero, 6, '0', STR_PAD_LEFT);
    } catch(PDOException $e) {
        return 'FAC-' . date('Ymd') . '-0001';
    }
}

$numero_documento = generarNuevoNumeroDocumento($conexion);
$usuario_actual = $_SESSION['id_usuario'] ?? 1;

$itbis_porcentaje = 18;
try {
    $stmt = $conexion->query("SELECT porcentaje FROM config_itbis WHERE activo = true AND CURRENT_DATE BETWEEN fecha_inicio AND COALESCE(fecha_fin, CURRENT_DATE + INTERVAL '100 years') LIMIT 1");
    $itbis_config = $stmt->fetch();
    if ($itbis_config) {
        $itbis_porcentaje = $itbis_config['porcentaje'];
    }
} catch(PDOException $e) {}

$base_url = '/sistema-gestor-de-farmacias';
?>

<style>
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
        align-items: center;
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
    .producto-card .precio {
        color: #28a745;
        font-weight: 700;
        font-size: 1rem;
    }
    .producto-card .stock {
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 20px;
        background-color: #e9ecef;
    }
    .producto-card .stock.bajo { background-color: #ffc107; color: #000; }
    .producto-card .stock.critico { background-color: #dc3545; color: #fff; }
    
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
    
    .badge-itbis {
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 20px;
    }
    .badge-itbis.aplica { background-color: #28a745; color: white; }
    .badge-itbis.exento { background-color: #6c757d; color: white; }
    
    .seguro-info {
        background: #e7f3ff;
        border-left: 4px solid #007bff;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    
    .autorizacion-pendiente {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    
    .badge-cobertura {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .badge-cobertura.alta { background-color: #28a745; color: white; }
    .badge-cobertura.media { background-color: #ffc107; color: #000; }
    .badge-cobertura.baja { background-color: #dc3545; color: white; }
    
    .texto-seguro { color: #007bff; font-weight: 600; }
    #selectDescuento { width: auto; min-width: 150px; font-size: 0.85rem; }
</style>

<div class="dashboard-container">
    <div class="mb-4">
        <h2 class="mb-0 text-success">
            <span class="material-symbols-rounded align-middle me-2">point_of_sale</span>
            Registrar Venta
        </h2>
        <p class="text-muted mb-0">Complete los datos del cliente y agregue los productos</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Datos de la Venta -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <h5 class="card-title mb-3 d-flex align-items-center">
                        <span class="material-symbols-rounded me-2 text-success">receipt</span>
                        Datos de la Venta
                    </h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Nº DOCUMENTO</label>
                            <input type="text" class="form-control" id="numeroDocumento" value="<?php echo $numero_documento; ?>" readonly style="background-color: #f8f9fa;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">FECHA</label>
                            <input type="text" class="form-control" id="fechaVenta" value="<?php echo date('d/m/Y H:i'); ?>" readonly style="background-color: #f8f9fa;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">SUCURSAL</label>
                            <select class="form-select" id="sucursal">
                                <option value="">Seleccionar sucursal...</option>
                                <?php foreach ($sucursales as $suc): ?>
                                    <option value="<?php echo $suc['id_sucursal']; ?>"><?php echo htmlspecialchars($suc['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">CLIENTE</label>
                            <select class="form-select" id="cliente">
                                <option value="">Consumidor Final</option>
                                <?php foreach ($clientes as $cli): ?>
                                    <option value="<?php echo $cli['id_cliente']; ?>" data-permite-credito="<?php echo $cli['permite_credito']; ?>" data-tiene-seguro="<?php echo $cli['tiene_seguro']; ?>">
                                        <?php echo htmlspecialchars($cli['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección de Seguro -->
            <div class="card shadow-sm border-0 mb-4" id="cardSeguro" style="display: none;">
                <div class="card-body p-4">
                    <h5 class="card-title mb-3 d-flex align-items-center">
                        <span class="material-symbols-rounded me-2 text-info">health_and_safety</span>
                        Información del Seguro Médico
                    </h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">ASEGURADORA</label>
                            <input type="text" class="form-control" id="aseguradoraNombre" readonly style="background-color: #f8f9fa; font-weight: 600;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Nº PÓLIZA / CARNET</label>
                            <input type="text" class="form-control" id="numeroPoliza" readonly style="background-color: #f8f9fa;">
                        </div>
                        <div class="col-md-12">
                            <div class="seguro-info" id="seguroInfo" style="display: none;">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Cobertura del seguro:</strong>
                                        <div class="mt-1">
                                            <span class="badge-cobertura" id="coberturaBadge">0%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <strong>Monto cubierto:</strong>
                                        <div class="mt-1 text-success fw-bold fs-5" id="montoSeguro">RD$ 0.00</div>
                                    </div>
                                </div>
                            </div>
                            <div class="autorizacion-pendiente" id="autorizacionPendiente" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                        <strong>⚠️ Se requieren autorizaciones</strong><br>
                                        <span id="autorizacionesFaltantes"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botón para agregar productos -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4 text-center">
                    <button type="button" class="btn btn-success btn-lg px-5" onclick="abrirModalProductos()">
                        <span class="material-symbols-rounded align-middle me-2">add_shopping_cart</span>
                        Agregar Producto
                    </button>
                    <p class="text-muted mt-2 mb-0 small">Haga clic para buscar y seleccionar productos</p>
                </div>
            </div>

            <!-- Tabla del Carrito -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table tabla-carrito mb-0">
                            <thead>
                                <tr>
                                    <th>PRODUCTO</th>
                                    <th>LOTE</th>
                                    <th>CANT.</th>
                                    <th>PRECIO</th>
                                    <th>ITBIS</th>
                                    <th>SUBTOTAL</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="carritoBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
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
                    Resumen de Venta
                </h5>
                <div class="resumen-linea"><span>Subtotal:</span><span id="resumenSubtotal">RD$ 0.00</span></div>
                <div class="resumen-linea"><span>ITBIS (<?php echo $itbis_porcentaje; ?>%):</span><span id="resumenItbis">RD$ 0.00</span></div>
                <div class="resumen-linea" id="resumenSeguroLinea" style="display: none;">
                    <span class="texto-seguro">SEGURO MÉDICO:</span>
                    <span id="resumenSeguro" class="text-info fw-bold">- RD$ 0.00</span>
                </div>
                <div class="resumen-linea">
                    <span>Descuento:</span>
                    <select id="selectDescuento" class="form-select form-select-sm" style="width: auto; min-width: 120px;">
                        <option value="">Ninguno</option>
                        <?php foreach ($descuentosDisponibles as $desc): ?>
                            <option value="<?php echo $desc['id_descuento']; ?>" 
                                    data-valor="<?php echo $desc['valor_descuento']; ?>"
                                    data-es_porcentaje="<?php echo $desc['es_porcentaje']; ?>">
                                <?php echo htmlspecialchars($desc['nombre']); ?> 
                                (<?php echo $desc['es_porcentaje'] ? $desc['valor_descuento'].'%' : 'RD$ '.number_format($desc['valor_descuento'],2); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="resumen-linea" id="resumenDescuentoLinea">
                    <span>Monto descontado:</span>
                    <span id="resumenDescuento">RD$ 0.00</span>
                </div>
                <div class="resumen-linea total"><span><strong>TOTAL A PAGAR:</strong></span><span id="resumenTotal" class="text-success fw-bold">RD$ 0.00</span></div>
                <hr>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted">CONDICIÓN DE PAGO</label>
                    <select class="form-select" id="condicionPago">
                        <?php foreach ($condiciones_pago as $cp): ?>
                            <option value="<?php echo $cp['id_condicion']; ?>"><?php echo htmlspecialchars($cp['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3" id="divMetodoPago">
                    <label class="form-label fw-bold small text-muted">MÉTODO DE PAGO</label>
                    <select class="form-select" id="metodoPago">
                        <?php foreach ($metodos_pago as $mp): ?>
                            <option value="<?php echo $mp['id_metodo']; ?>"><?php echo htmlspecialchars($mp['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="alert alert-info small" id="alertCredito" style="display: none;">
                    <i class="fas fa-info-circle me-1"></i> Esta venta se registrará a crédito.
                </div>
                <div class="d-grid gap-2 mt-3">
                    <button class="btn btn-success btn-lg" onclick="procesarVenta()">
                        <span class="material-symbols-rounded align-middle me-1">check_circle</span> Procesar Venta
                    </button>
                    <button class="btn btn-outline-secondary" onclick="cancelarVenta()">
                        <span class="material-symbols-rounded align-middle me-1">cancel</span> Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Productos -->
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
                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <input type="text" class="form-control form-control-lg" id="filtroNombreProducto" placeholder="🔍 Buscar por nombre...">
                        </div>
                    </div>
                </div>
                <div class="lista-productos" id="listaProductosModal">
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

<!-- Modal Cantidad -->
<div class="modal fade" id="modalCantidad" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-success text-white p-4">
                <h5 class="modal-title d-flex align-items-center">
                    <span class="material-symbols-rounded me-2">shopping_cart</span>
                    Cantidad a agregar
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-3">
                    <i class="fas fa-pills" style="font-size: 3rem; color: #28a745;"></i>
                </div>
                <h5 class="text-center" id="productoNombreModal"></h5>
                <p class="text-center text-muted" id="productoInfoModal"></p>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Cantidad</label>
                        <input type="number" class="form-control form-control-lg text-center" id="cantidadProducto" value="1" min="1">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Precio unitario (RD$)</label>
                        <input type="number" step="0.01" class="form-control form-control-lg text-center" id="precioProducto" readonly style="background-color: #f8f9fa;">
                    </div>
                </div>
                <div class="alert alert-warning mt-3" id="stockAdvertencia" style="display: none;">
                    <i class="fas fa-exclamation-triangle me-1"></i> Stock disponible: <span id="stockDisponible"></span> unidades
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-end gap-3">
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success px-4" onclick="confirmarAgregarProducto()">
                    <span class="material-symbols-rounded align-middle me-1">add</span>
                    Agregar al carrito
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
let modalCantidad = null;
let productosData = [];
let datosSeguroCliente = null;
let coberturaActual = null;
let descuentoSeleccionado = { id: null, valor: 0, esPorcentaje: false, montoAplicado: 0 };

// ==================== DESCUENTO ====================
function aplicarDescuento(subtotal) {
    const select = document.getElementById('selectDescuento');
    const selectedOption = select.options[select.selectedIndex];
    if (!selectedOption.value) {
        descuentoSeleccionado = { id: null, valor: 0, esPorcentaje: false, montoAplicado: 0 };
        document.getElementById('resumenDescuento').innerHTML = 'RD$ 0.00';
        return 0;
    }
    const valor = parseFloat(selectedOption.dataset.valor);
    const esPorcentaje = selectedOption.dataset.es_porcentaje === '1';
    let monto = 0;
    if (esPorcentaje) {
        monto = subtotal * (valor / 100);
    } else {
        monto = valor;
    }
    descuentoSeleccionado = {
        id: parseInt(selectedOption.value),
        valor: valor,
        esPorcentaje: esPorcentaje,
        montoAplicado: monto
    };
    document.getElementById('resumenDescuento').innerHTML = `RD$ ${monto.toFixed(2)}`;
    return monto;
}

// ==================== RECÁLCULO CON ITBIS CORREGIDO ====================
function recalcularTodo() {
    if (carrito.length === 0) {
        document.getElementById('resumenSubtotal').innerHTML = 'RD$ 0.00';
        document.getElementById('resumenItbis').innerHTML = 'RD$ 0.00';
        document.getElementById('resumenDescuento').innerHTML = 'RD$ 0.00';
        document.getElementById('resumenTotal').innerHTML = 'RD$ 0.00';
        document.getElementById('resumenSeguroLinea').style.display = 'none';
        document.getElementById('seguroInfo').style.display = 'none';
        return;
    }
    
    const clienteSelect = document.getElementById('cliente');
    const idCliente = clienteSelect.value;
    const tieneSeguro = idCliente && clienteSelect.options[clienteSelect.selectedIndex]?.dataset?.tieneSeguro === '1';
    
    if (tieneSeguro && idCliente) {
        calcularConSeguro(idCliente);
    } else {
        calcularSinSeguro();
    }
}

function calcularSinSeguro() {
    let subtotal = 0;
    for (const item of carrito) {
        subtotal += item.precio * item.cantidad;
    }
    const descuentoMonto = aplicarDescuento(subtotal);
    const subtotalConDescuento = subtotal - descuentoMonto;
    
    // Recalcular ITBIS sobre el subtotal con descuento (proporcional)
    let nuevoItbis = 0;
    if (subtotal > 0) {
        for (const item of carrito) {
            if (item.aplica_itbis) {
                const itemSubtotal = item.precio * item.cantidad;
                const proporcion = itemSubtotal / subtotal;
                const itemDescuento = descuentoMonto * proporcion;
                const itemSubtotalConDesc = itemSubtotal - itemDescuento;
                nuevoItbis += itemSubtotalConDesc * (ITBIS_PORCENTAJE / 100);
            }
        }
    }
    const total = subtotalConDescuento + nuevoItbis;
    
    document.getElementById('resumenSubtotal').innerHTML = `RD$ ${subtotal.toFixed(2)}`;
    document.getElementById('resumenItbis').innerHTML = `RD$ ${nuevoItbis.toFixed(2)}`;
    document.getElementById('resumenTotal').innerHTML = `RD$ ${total.toFixed(2)}`;
    document.getElementById('resumenSeguroLinea').style.display = 'none';
    document.getElementById('seguroInfo').style.display = 'none';
}

async function calcularConSeguro(idCliente) {
    if (!datosSeguroCliente || datosSeguroCliente.id_cliente != idCliente) {
        await cargarDatosSeguroCliente(idCliente);
    }
    if (!datosSeguroCliente) {
        calcularSinSeguro();
        return;
    }
    
    const productosParaCalcular = carrito.map(item => ({
        id_medicamento: item.id_producto,
        nombre: item.nombre,
        precio_unitario: item.precio,
        cantidad: item.cantidad,
        aplica_itbis: item.aplica_itbis
    }));
    
    try {
        const response = await fetch(BASE_URL + '/backend/ventas/calcular_cobertura.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_cliente: parseInt(idCliente),
                productos: productosParaCalcular
            })
        });
        const data = await response.json();
        if (data.success) {
            coberturaActual = data.data;
            // Aplicar descuento sobre el monto que paga el paciente (después del seguro)
            const montoPacienteSinDescuento = coberturaActual.monto_paga_paciente;
            const descuentoMonto = aplicarDescuento(montoPacienteSinDescuento);
            const totalFinal = montoPacienteSinDescuento - descuentoMonto;
            
            document.getElementById('resumenSubtotal').innerHTML = `RD$ ${coberturaActual.subtotal.toFixed(2)}`;
            document.getElementById('resumenItbis').innerHTML = `RD$ ${coberturaActual.itbis_total.toFixed(2)}`;
            document.getElementById('resumenTotal').innerHTML = `RD$ ${totalFinal.toFixed(2)}`;
            document.getElementById('resumenSeguroLinea').style.display = 'flex';
            document.getElementById('resumenSeguro').innerHTML = `- RD$ ${coberturaActual.monto_cubre_seguro.toFixed(2)}`;
            document.getElementById('seguroInfo').style.display = 'block';
            document.getElementById('montoSeguro').innerHTML = `RD$ ${coberturaActual.monto_cubre_seguro.toFixed(2)}`;
            
            if (coberturaActual.requiere_autorizacion && coberturaActual.autorizaciones_faltantes.length > 0) {
                document.getElementById('autorizacionPendiente').style.display = 'block';
                document.getElementById('autorizacionesFaltantes').innerHTML = coberturaActual.autorizaciones_faltantes.join(', ');
            } else {
                document.getElementById('autorizacionPendiente').style.display = 'none';
            }
        } else {
            calcularSinSeguro();
        }
    } catch(error) {
        console.error(error);
        calcularSinSeguro();
    }
}

async function cargarDatosSeguroCliente(idCliente) {
    try {
        const response = await fetch(BASE_URL + `/backend/ventas/get_poliza_cliente.php?id_cliente=${idCliente}`);
        const data = await response.json();
        if (data.success && data.poliza) {
            datosSeguroCliente = {
                id_cliente: idCliente,
                id_aseguradora: data.poliza.id_aseguradora,
                nombre: data.poliza.aseguradora_nombre,
                poliza: data.poliza.numero_poliza,
                cobertura: data.poliza.cobertura_porcentaje || 80
            };
            document.getElementById('aseguradoraNombre').value = datosSeguroCliente.nombre;
            document.getElementById('numeroPoliza').value = datosSeguroCliente.poliza;
            document.getElementById('cardSeguro').style.display = 'block';
            const badge = document.getElementById('coberturaBadge');
            badge.innerText = datosSeguroCliente.cobertura + '%';
            if (datosSeguroCliente.cobertura >= 80) badge.className = 'badge-cobertura alta';
            else if (datosSeguroCliente.cobertura >= 50) badge.className = 'badge-cobertura media';
            else badge.className = 'badge-cobertura baja';
            return true;
        } else {
            datosSeguroCliente = null;
            document.getElementById('cardSeguro').style.display = 'none';
            return false;
        }
    } catch(error) {
        datosSeguroCliente = null;
        document.getElementById('cardSeguro').style.display = 'none';
        return false;
    }
}

// ==================== MANEJO DEL CARRITO ====================
function abrirModalProductos() { if (modalProductos) modalProductos.show(); }
function cargarProductosModal() {
    const listaDiv = document.getElementById('listaProductosModal');
    listaDiv.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando...</p></div>';
    fetch(BASE_URL + '/backend/ventas/listar_productos_venta.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.productos) {
                productosData = data.productos;
                renderizarProductosModal(productosData);
            } else {
                listaDiv.innerHTML = '<div class="text-center py-5 text-danger">Error al cargar productos</div>';
            }
        })
        .catch(error => { listaDiv.innerHTML = '<div class="text-center py-5 text-danger">Error de conexión</div>'; });
}
function renderizarProductosModal(productos) {
    const listaDiv = document.getElementById('listaProductosModal');
    if (!productos || productos.length === 0) {
        listaDiv.innerHTML = '<div class="text-center py-5 text-muted">No hay productos disponibles</div>';
        return;
    }
    let html = '<div class="row">';
    productos.forEach(p => {
        let stockClass = '';
        let stockText = `Stock: ${p.stock}`;
        if (p.stock < 5) { stockClass = 'critico'; stockText = `⚠️ Stock crítico: ${p.stock}`; }
        else if (p.stock < 10) { stockClass = 'bajo'; stockText = `⚠️ Stock bajo: ${p.stock}`; }
        const itbisBadge = p.exento_itbis ? '<span class="badge-itbis exento">Exento ITBIS</span>' : '<span class="badge-itbis aplica">Aplica ITBIS</span>';
        html += `
            <div class="col-md-6 col-lg-4">
                <div class="producto-card" onclick="seleccionarProducto(${p.id_lote}, '${escapeHtml(p.nombre)}', ${p.precio}, ${p.stock}, ${p.id_medicamento}, ${p.exento_itbis ? 0 : 1})">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="nombre">${escapeHtml(p.nombre)}</div>
                        <div class="precio">RD$ ${parseFloat(p.precio).toFixed(2)}</div>
                    </div>
                    <div class="small text-muted mt-1">Lote: ${escapeHtml(p.numero_lote)}</div>
                    <div class="mt-2 d-flex justify-content-between align-items-center">
                        <span class="stock ${stockClass}">${stockText}</span>
                        ${itbisBadge}
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    listaDiv.innerHTML = html;
}
function filtrarProductos() {
    const filtroNombre = document.getElementById('filtroNombreProducto')?.value.toLowerCase() || '';
    let filtrados = productosData.filter(p => p.nombre.toLowerCase().includes(filtroNombre));
    renderizarProductosModal(filtrados);
}
function seleccionarProducto(idLote, nombre, precio, stock, idMedicamento, aplicaItbis) {
    productoSeleccionado = {
        id_lote: idLote,
        id_producto: idMedicamento,
        nombre: nombre,
        precio: precio,
        stock: stock,
        aplica_itbis: aplicaItbis === 1
    };
    document.getElementById('productoNombreModal').innerText = nombre;
    document.getElementById('productoInfoModal').innerHTML = `Stock disponible: ${stock} unidades | Precio: RD$ ${precio.toFixed(2)}`;
    document.getElementById('cantidadProducto').value = 1;
    document.getElementById('precioProducto').value = precio.toFixed(2);
    document.getElementById('stockDisponible').innerText = stock;
    document.getElementById('stockAdvertencia').style.display = stock < 10 ? 'block' : 'none';
    if (modalProductos) modalProductos.hide();
    if (modalCantidad) modalCantidad.show();
}
function confirmarAgregarProducto() {
    const cantidad = parseInt(document.getElementById('cantidadProducto').value);
    const stock = productoSeleccionado.stock;
    if (isNaN(cantidad) || cantidad < 1) { Swal.fire('Error', 'Cantidad inválida', 'error'); return; }
    if (cantidad > stock) { Swal.fire('Stock insuficiente', `Solo hay ${stock} unidades disponibles`, 'warning'); return; }
    const existente = carrito.find(item => item.id_lote === productoSeleccionado.id_lote);
    if (existente) {
        const nuevaCantidad = existente.cantidad + cantidad;
        if (nuevaCantidad > stock) { Swal.fire('Stock insuficiente', `Solo hay ${stock} unidades disponibles en total`, 'warning'); return; }
        existente.cantidad = nuevaCantidad;
    } else {
        carrito.push({
            id_lote: productoSeleccionado.id_lote,
            id_producto: productoSeleccionado.id_producto,
            nombre: productoSeleccionado.nombre,
            precio: productoSeleccionado.precio,
            cantidad: cantidad,
            stock: stock,
            aplica_itbis: productoSeleccionado.aplica_itbis
        });
    }
    actualizarCarrito();
    if (modalCantidad) modalCantidad.hide();
    productoSeleccionado = null;
}
function actualizarCarrito() {
    const tbody = document.getElementById('carritoBody');
    if (carrito.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-shopping-cart" style="font-size: 2rem; opacity: 0.3;"></i><p class="mt-2">No hay productos agregados</p></td></tr>`;
        recalcularTodo();
        return;
    }
    let html = '';
    for (let i = 0; i < carrito.length; i++) {
        const item = carrito[i];
        const subtotal = item.precio * item.cantidad;
        const itbis = item.aplica_itbis ? (subtotal * ITBIS_PORCENTAJE / 100) : 0;
        const totalConItbis = subtotal + itbis;
        const itbisText = item.aplica_itbis ? `Aplica (${ITBIS_PORCENTAJE}%)` : 'Exento';
        const itbisClass = item.aplica_itbis ? 'text-success' : 'text-muted';
        html += `
            <tr>
                <td><strong>${escapeHtml(item.nombre)}</strong></td>
                <td><small class="text-muted">Lote: ${item.id_lote}</small></td>
                <td><input type="number" class="cantidad-input" value="${item.cantidad}" min="1" max="${item.stock}" onchange="actualizarCantidad(${i}, this.value)"></td>
                <td>RD$ ${item.precio.toFixed(2)}</td>
                <td class="${itbisClass}">${itbisText}</td>
                <td class="fw-bold text-success">RD$ ${totalConItbis.toFixed(2)}</td>
                <td class="text-center"><button class="btn-eliminar-item" onclick="eliminarProducto(${i})"><span class="material-symbols-rounded">delete</span></button></td>
            </tr>
        `;
    }
    tbody.innerHTML = html;
    recalcularTodo();
}
function actualizarCantidad(index, nuevaCantidad) {
    nuevaCantidad = parseInt(nuevaCantidad);
    if (isNaN(nuevaCantidad) || nuevaCantidad < 1) nuevaCantidad = 1;
    if (nuevaCantidad > carrito[index].stock) {
        Swal.fire('Stock insuficiente', `Solo hay ${carrito[index].stock} unidades disponibles`, 'warning');
        nuevaCantidad = carrito[index].stock;
    }
    carrito[index].cantidad = nuevaCantidad;
    actualizarCarrito();
}
function eliminarProducto(index) {
    carrito.splice(index, 1);
    actualizarCarrito();
}
function procesarVenta() {
    const sucursal = document.getElementById('sucursal').value;
    const condicionPago = document.getElementById('condicionPago').value;
    const idCliente = document.getElementById('cliente').value;
    const tieneSeguro = datosSeguroCliente && coberturaActual?.monto_cubre_seguro > 0;
    if (!sucursal) { Swal.fire('Error', 'Seleccione una sucursal', 'error'); return; }
    if (carrito.length === 0) { Swal.fire('Error', 'Agregue al menos un producto', 'error'); return; }
    if (condicionPago === '1') {
        const metodoPago = document.getElementById('metodoPago').value;
        if (!metodoPago) { Swal.fire('Error', 'Seleccione un método de pago', 'error'); return; }
    }
    if (coberturaActual?.requiere_autorizacion && coberturaActual.autorizaciones_faltantes?.length > 0) {
        Swal.fire({
            title: '⚠️ Autorizaciones requeridas',
            html: `Los siguientes medicamentos requieren autorización del seguro:<br><strong>${coberturaActual.autorizaciones_faltantes.join(', ')}</strong><br><br>El seguro NO cubrirá estos medicamentos. ¿Desea continuar?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar sin seguro para estos',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) procesarVentaConfirmado(sucursal, condicionPago, idCliente, tieneSeguro);
        });
        return;
    }
    procesarVentaConfirmado(sucursal, condicionPago, idCliente, tieneSeguro);
}
function procesarVentaConfirmado(sucursal, condicionPago, idCliente, tieneSeguro) {
    const metodoPago = document.getElementById('metodoPago').value;
    let subtotal = 0;
    for (const item of carrito) {
        subtotal += item.precio * item.cantidad;
    }
    let descuentoMonto = 0;
    if (descuentoSeleccionado.id) {
        if (descuentoSeleccionado.esPorcentaje) {
            descuentoMonto = subtotal * (descuentoSeleccionado.valor / 100);
        } else {
            descuentoMonto = descuentoSeleccionado.valor;
        }
    }
    const subtotalConDescuento = subtotal - descuentoMonto;
    // Recalcular ITBIS sobre el subtotal con descuento
    let nuevoItbis = 0;
    if (subtotal > 0) {
        for (const item of carrito) {
            if (item.aplica_itbis) {
                const itemSubtotal = item.precio * item.cantidad;
                const proporcion = itemSubtotal / subtotal;
                const itemDescuento = descuentoMonto * proporcion;
                const itemSubtotalConDesc = itemSubtotal - itemDescuento;
                nuevoItbis += itemSubtotalConDesc * (ITBIS_PORCENTAJE / 100);
            }
        }
    }
    let montoSeguro = 0;
    let totalPagar = subtotalConDescuento + nuevoItbis;
    if (tieneSeguro && coberturaActual) {
        montoSeguro = coberturaActual.monto_cubre_seguro || 0;
        totalPagar = (subtotalConDescuento + nuevoItbis) - montoSeguro;
        if (totalPagar < 0) totalPagar = 0;
    }
    const datos = {
        numero_documento: document.getElementById('numeroDocumento').value,
        id_usuario: ID_USUARIO_ACTUAL,
        id_cliente: idCliente ? parseInt(idCliente) : null,
        id_sucursal: parseInt(sucursal),
        id_condicion: parseInt(condicionPago),
        id_metodo_pago: condicionPago === '1' ? (metodoPago ? parseInt(metodoPago) : null) : null,
        productos: carrito.map(item => ({
            id_lote: parseInt(item.id_lote),
            id_producto: parseInt(item.id_producto),
            cantidad: parseInt(item.cantidad),
            precio_unitario: parseFloat(item.precio),
            aplica_itbis: item.aplica_itbis === true
        })),
        subtotal: subtotal,
        itbis_total: nuevoItbis,
        total: totalPagar,
        usa_seguro: tieneSeguro,
        id_aseguradora: tieneSeguro ? datosSeguroCliente?.id_aseguradora : null,
        monto_cubre_seguro: parseFloat(montoSeguro),
        monto_paga_paciente: totalPagar,
        id_descuento: descuentoSeleccionado.id,
        monto_descuento: descuentoMonto
    };
    Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch(BASE_URL + '/backend/ventas/procesar_venta.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos)
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire('Éxito', `Venta ${data.numero_documento} registrada`, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', data.message || 'Error al procesar la venta', 'error');
        }
    })
    .catch(error => { Swal.close(); Swal.fire('Error', 'Error de conexión', 'error'); console.error(error); });
}
function cancelarVenta() {
    Swal.fire({
        title: '¿Cancelar venta?',
        text: 'Se perderán todos los datos ingresados',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No'
    }).then((result) => { if (result.isConfirmed) location.reload(); });
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
document.addEventListener('DOMContentLoaded', function() {
    const elModalProductos = document.getElementById('modalProductos');
    const elModalCantidad = document.getElementById('modalCantidad');
    if (elModalProductos) modalProductos = new bootstrap.Modal(elModalProductos, { backdrop: false, keyboard: true });
    if (elModalCantidad) modalCantidad = new bootstrap.Modal(elModalCantidad, { backdrop: false, keyboard: true });
    document.getElementById('cliente').addEventListener('change', function() {
        const tieneSeguro = this.options[this.selectedIndex]?.dataset?.tieneSeguro === '1';
        if (!tieneSeguro || !this.value) {
            document.getElementById('cardSeguro').style.display = 'none';
            document.getElementById('seguroInfo').style.display = 'none';
            datosSeguroCliente = null;
        }
        recalcularTodo();
        actualizarCondicionCredito();
    });
    document.getElementById('condicionPago').addEventListener('change', function() {
        const alertCredito = document.getElementById('alertCredito');
        const divMetodo = document.getElementById('divMetodoPago');
        if (this.value === '2' || this.value === '3') {
            alertCredito.style.display = 'block';
            divMetodo.style.display = 'none';
        } else {
            alertCredito.style.display = 'none';
            divMetodo.style.display = 'block';
        }
    });
    document.getElementById('filtroNombreProducto')?.addEventListener('input', filtrarProductos);
    document.getElementById('selectDescuento').addEventListener('change', () => recalcularTodo());
    if (elModalProductos) elModalProductos.addEventListener('show.bs.modal', cargarProductosModal);
});
function actualizarCondicionCredito() {
    const clienteSelect = document.getElementById('cliente');
    const selectedOption = clienteSelect.options[clienteSelect.selectedIndex];
    const permiteCredito = selectedOption?.dataset?.permiteCredito === '1';
    const condicionSelect = document.getElementById('condicionPago');
    if (!permiteCredito && (condicionSelect.value === '2' || condicionSelect.value === '3')) {
        condicionSelect.value = '1';
        Swal.fire('Aviso', 'Este cliente no tiene crédito habilitado', 'info');
        document.getElementById('alertCredito').style.display = 'none';
        document.getElementById('divMetodoPago').style.display = 'block';
    }
}
</script>