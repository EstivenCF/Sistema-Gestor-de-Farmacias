<?php
require_once __DIR__ . '/../../backend/conexion.php';

// Verificar sesión
if (!isset($_SESSION['id_sesion'])) {
    header("Location: ../index.php");
    exit();
}

// Obtener datos para selects
$clientes = [];
$metodos_pago = [];
$condiciones_pago = [];
$sucursales = [];
$usuarios = [];

try {
    $stmt = $conexion->query("SELECT id_cliente, nombre, permite_credito FROM clientes ORDER BY nombre");
    $clientes = $stmt->fetchAll();
    
    $stmt = $conexion->query("SELECT id_metodo, nombre FROM metodos_pago ORDER BY nombre");
    $metodos_pago = $stmt->fetchAll();
    
    $stmt = $conexion->query("SELECT id_condicion, nombre, dias_plazo FROM condicion_pago ORDER BY id_condicion");
    $condiciones_pago = $stmt->fetchAll();
    
    $stmt = $conexion->query("SELECT id_sucursal, nombre FROM sucursales WHERE estado = true ORDER BY nombre");
    $sucursales = $stmt->fetchAll();
    
    $stmt = $conexion->query("SELECT id_usuario, nombre FROM usuarios WHERE estado = true ORDER BY nombre");
    $usuarios = $stmt->fetchAll();
    
} catch(PDOException $e) {}

$numero_documento = '';
try {
    $stmt = $conexion->query("SELECT 'FAC-' || LPAD(COALESCE(MAX(SUBSTRING(numero_documento FROM 5))::INT, 0) + 1, 5, '0') as nuevo_numero FROM ventas WHERE numero_documento LIKE 'FAC-%'");
    $result = $stmt->fetch();
    $numero_documento = $result['nuevo_numero'] ?? 'FAC-00001';
} catch(PDOException $e) {
    $numero_documento = 'FAC-00001';
}

$itbis_porcentaje = 18;
try {
    $stmt = $conexion->query("SELECT porcentaje FROM config_itbis WHERE activo = true AND CURRENT_DATE BETWEEN fecha_inicio AND COALESCE(fecha_fin, CURRENT_DATE + INTERVAL '100 years') LIMIT 1");
    $itbis_config = $stmt->fetch();
    if ($itbis_config) {
        $itbis_porcentaje = $itbis_config['porcentaje'];
    }
} catch(PDOException $e) {}
?>

<style>
    .modal-backdrop { display: none !important; }
    .modal { background-color: rgba(0, 0, 0, 0.5) !important; z-index: 1050; }
    .modal-dialog-centered { display: flex; align-items: center; min-height: calc(100% - 1rem); }
    .modal.show .modal-dialog { transform: none; margin: 1.75rem auto; }
    
    .producto-buscar { position: relative; }
    .resultados-busqueda {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 10px;
        max-height: 300px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .resultado-item {
        padding: 12px 15px;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: all 0.2s;
    }
    .resultado-item:hover { background-color: #e8f5e9; }
    .resultado-item .nombre { font-weight: 600; color: #2c3e50; }
    .resultado-item .precio { color: #28a745; font-weight: 600; }
    .resultado-item .stock { font-size: 12px; color: #6c757d; }
    
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
                            <label class="form-label fw-bold small text-muted">VENDEDOR</label>
                            <select class="form-select" id="vendedor">
                                <option value="">Seleccionar vendedor...</option>
                                <?php foreach ($usuarios as $user): ?>
                                    <option value="<?php echo $user['id_usuario']; ?>"><?php echo htmlspecialchars($user['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">CLIENTE</label>
                            <select class="form-select" id="cliente">
                                <option value="">Consumidor Final</option>
                                <?php foreach ($clientes as $cli): ?>
                                    <option value="<?php echo $cli['id_cliente']; ?>" data-permite-credito="<?php echo $cli['permite_credito']; ?>">
                                        <?php echo htmlspecialchars($cli['nombre']); ?>
                                    </option>
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
                    </div>
                </div>
            </div>

            <!-- Buscador de Productos -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <h5 class="card-title mb-3 d-flex align-items-center">
                        <span class="material-symbols-rounded me-2 text-success">search</span>
                        Agregar Productos
                    </h5>
                    <div class="producto-buscar">
                        <input type="text" class="form-control form-control-lg" id="buscarProducto" placeholder="Buscar por nombre, código o lote..." autocomplete="off">
                        <div class="resultados-busqueda" id="resultadosBusqueda"></div>
                    </div>
                </div>
            </div>

            <!-- Tabla del Carrito -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table tabla-carrito mb-0">
                            <thead>
                                <tr><th>PRODUCTO</th><th>LOTE</th><th>CANT.</th><th>PRECIO</th><th>SUBTOTAL</th><th></th></tr>
                            </thead>
                            <tbody id="carritoBody">
                                <tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-shopping-cart" style="font-size: 2rem; opacity: 0.3;"></i><p class="mt-2">No hay productos agregados</p></td><tr>
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
                <div class="resumen-linea"><span>Descuento:</span><span id="resumenDescuento">RD$ 0.00</span></div>
                <div class="resumen-linea total"><span><strong>TOTAL:</strong></span><span id="resumenTotal" class="text-success fw-bold">RD$ 0.00</span></div>
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let carrito = [];
let timeoutBusqueda;
let itbisPorcentaje = <?php echo $itbis_porcentaje; ?>;

document.addEventListener('DOMContentLoaded', function() {
    const clienteSelect = document.getElementById('cliente');
    if (clienteSelect) {
        clienteSelect.addEventListener('change', actualizarCondicionCredito);
    }
    
    const condicionSelect = document.getElementById('condicionPago');
    if (condicionSelect) {
        condicionSelect.addEventListener('change', function() {
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
    }
    
    const buscarInput = document.getElementById('buscarProducto');
    if (buscarInput) {
        buscarInput.addEventListener('input', function() {
            clearTimeout(timeoutBusqueda);
            const termino = this.value.trim();
            if (termino.length >= 2) {
                timeoutBusqueda = setTimeout(() => buscarProductos(termino), 300);
            } else {
                document.getElementById('resultadosBusqueda').style.display = 'none';
            }
        });
        
        document.addEventListener('click', function(e) {
            const busqueda = document.querySelector('.producto-buscar');
            if (busqueda && !busqueda.contains(e.target)) {
                document.getElementById('resultadosBusqueda').style.display = 'none';
            }
        });
    }
});

function actualizarCondicionCredito() {
    const clienteSelect = document.getElementById('cliente');
    const selectedOption = clienteSelect.options[clienteSelect.selectedIndex];
    const permiteCredito = selectedOption?.dataset?.permiteCredito === '1';
    const condicionSelect = document.getElementById('condicionPago');
    
    if (!permiteCredito && (condicionSelect.value === '2' || condicionSelect.value === '3')) {
        condicionSelect.value = '1';
        Swal.fire('Aviso', 'Este cliente no tiene crédito habilitado', 'info');
        const alertCredito = document.getElementById('alertCredito');
        const divMetodo = document.getElementById('divMetodoPago');
        alertCredito.style.display = 'none';
        divMetodo.style.display = 'block';
    }
}

function buscarProductos(termino) {
    fetch(`../../backend/ventas/buscar_productos_venta.php?termino=${encodeURIComponent(termino)}`)
        .then(response => response.json())
        .then(data => {
            const resultadosDiv = document.getElementById('resultadosBusqueda');
            if (data.success && data.productos && data.productos.length > 0) {
                let html = '';
                data.productos.forEach(p => {
                    const stockText = p.stock > 0 ? `<span class="stock">Stock: ${p.stock}</span>` : '<span class="stock text-danger">Sin stock</span>';
                    html += `
                        <div class="resultado-item" onclick="agregarProducto(${p.id_lote}, '${escapeHtml(p.nombre)}', ${p.precio}, ${p.stock}, ${p.id_medicamento})">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="nombre">${escapeHtml(p.nombre)}</div>
                                    <div class="small text-muted">Lote: ${escapeHtml(p.numero_lote)} | ${escapeHtml(p.presentacion || '')}</div>
                                    ${stockText}
                                </div>
                                <div class="precio">RD$ ${parseFloat(p.precio).toFixed(2).replace('.', ',')}</div>
                            </div>
                        </div>
                    `;
                });
                resultadosDiv.innerHTML = html;
                resultadosDiv.style.display = 'block';
            } else {
                resultadosDiv.innerHTML = '<div class="p-3 text-center text-muted">No se encontraron productos</div>';
                resultadosDiv.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('resultadosBusqueda').innerHTML = '<div class="p-3 text-center text-danger">Error al buscar productos</div>';
            document.getElementById('resultadosBusqueda').style.display = 'block';
        });
}

function agregarProducto(idLote, nombre, precio, stock, idMedicamento) {
    const existente = carrito.find(item => item.id_lote === idLote);
    
    if (existente) {
        const nuevaCantidad = existente.cantidad + 1;
        if (nuevaCantidad > stock) {
            Swal.fire('Stock insuficiente', `Solo hay ${stock} unidades disponibles`, 'warning');
            return;
        }
        existente.cantidad = nuevaCantidad;
    } else {
        if (stock < 1) {
            Swal.fire('Stock insuficiente', 'Este producto no tiene stock disponible', 'warning');
            return;
        }
        carrito.push({
            id_lote: idLote,
            id_producto: idMedicamento,
            nombre: nombre,
            precio: precio,
            cantidad: 1,
            stock: stock
        });
    }
    
    actualizarCarrito();
    document.getElementById('resultadosBusqueda').style.display = 'none';
    document.getElementById('buscarProducto').value = '';
}

function actualizarCarrito() {
    const tbody = document.getElementById('carritoBody');
    
    if (carrito.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-shopping-cart" style="font-size: 2rem; opacity: 0.3;"></i><p class="mt-2">No hay productos agregados</p></td></tr>`;
        actualizarResumen();
        return;
    }
    
    let html = '';
    carrito.forEach((item, index) => {
        const subtotal = item.precio * item.cantidad;
        html += `
            <tr>
                <td><strong>${escapeHtml(item.nombre)}</strong></td>
                <td><small class="text-muted">Lote: ${item.id_lote}</small></td>
                <td><input type="number" class="cantidad-input" value="${item.cantidad}" min="1" max="${item.stock}" onchange="actualizarCantidad(${index}, this.value)"></td>
                <td>RD$ ${item.precio.toFixed(2).replace('.', ',')}</td>
                <td class="fw-bold text-success">RD$ ${subtotal.toFixed(2).replace('.', ',')}</td>
                <td class="text-center"><button class="btn-eliminar-item" onclick="eliminarProducto(${index})"><span class="material-symbols-rounded">delete</span></button></td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
    actualizarResumen();
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

function actualizarResumen() {
    let subtotal = 0;
    carrito.forEach(item => { subtotal += item.precio * item.cantidad; });
    const itbis = subtotal * (itbisPorcentaje / 100);
    const total = subtotal + itbis;
    
    document.getElementById('resumenSubtotal').innerHTML = `RD$ ${subtotal.toFixed(2).replace('.', ',')}`;
    document.getElementById('resumenItbis').innerHTML = `RD$ ${itbis.toFixed(2).replace('.', ',')}`;
    document.getElementById('resumenTotal').innerHTML = `RD$ ${total.toFixed(2).replace('.', ',')}`;
}

function procesarVenta() {
    const vendedor = document.getElementById('vendedor').value;
    const sucursal = document.getElementById('sucursal').value;
    const condicionPago = document.getElementById('condicionPago').value;
    
    if (!vendedor) { Swal.fire('Error', 'Seleccione un vendedor', 'error'); return; }
    if (!sucursal) { Swal.fire('Error', 'Seleccione una sucursal', 'error'); return; }
    if (carrito.length === 0) { Swal.fire('Error', 'Agregue al menos un producto', 'error'); return; }
    if (condicionPago === '1') {
        const metodoPago = document.getElementById('metodoPago').value;
        if (!metodoPago) { Swal.fire('Error', 'Seleccione un método de pago', 'error'); return; }
    }
    
    Swal.fire({
        title: '¿Confirmar venta?',
        html: `<p>Total: <strong>${document.getElementById('resumenTotal').innerHTML}</strong></p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        confirmButtonText: 'Sí, procesar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const datos = {
                numero_documento: document.getElementById('numeroDocumento').value,
                id_usuario: parseInt(document.getElementById('vendedor').value),
                id_cliente: document.getElementById('cliente').value || null,
                id_sucursal: parseInt(document.getElementById('sucursal').value),
                id_condicion: parseInt(document.getElementById('condicionPago').value),
                id_metodo_pago: document.getElementById('metodoPago').value || null,
                productos: carrito.map(item => ({
                    id_lote: item.id_lote,
                    id_producto: item.id_producto,
                    cantidad: item.cantidad,
                    precio_unitario: item.precio
                }))
            };
            
            Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            
            fetch('../../backend/ventas/procesar_venta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(datos)
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire('Éxito', `Venta ${data.numero_documento} registrada por RD$ ${data.total}`, 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message || 'Error al procesar la venta', 'error');
                }
            })
            .catch(error => {
                Swal.close();
                Swal.fire('Error', 'Error de conexión', 'error');
            });
        }
    });
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
    }).then((result) => {
        if (result.isConfirmed) { location.reload(); }
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