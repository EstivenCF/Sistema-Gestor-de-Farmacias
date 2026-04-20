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

<!-- FontAwesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

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
    
    /* Badges para tipos de producto */
    .badge-tipo-medicamento {
        background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        color: white;
        font-size: 0.65rem;
        padding: 3px 10px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .badge-tipo-ropa {
        background: linear-gradient(135deg, #17a2b8 0%, #0f6b7a 100%);
        color: white;
        font-size: 0.65rem;
        padding: 3px 10px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    /* Pestañas manuales */
    .tab-btn {
        background: none;
        border: none;
        padding: 10px 24px;
        font-size: 1rem;
        font-weight: 600;
        color: #6c757d;
        border-radius: 12px;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .tab-btn:hover {
        background-color: #e9ecef;
        color: #28a745;
    }
    .tab-btn.active {
        background-color: #28a745;
        color: white;
        box-shadow: 0 2px 8px rgba(40,167,69,0.3);
    }
    .tab-pane {
        display: none;
    }
    .tab-pane.active {
        display: block;
    }
</style>

<div class="dashboard-container">
    <div class="mb-4">
        <h2 class="mb-0 text-success">
            <i class="fas fa-shopping-cart me-2"></i>
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
                        <i class="fas fa-file-invoice me-2 text-success"></i>
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
                        <i class="fas fa-plus-circle me-2"></i>
                        Agregar Producto
                    </button>
                    <p class="text-muted mt-2 mb-0 small">Seleccione productos para esta compra</p>
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
                                    <th>TALLA/COLOR</th>
                                    <th>CANT.</th>
                                    <th>COSTO UNIT.</th>
                                    <th>DTO.</th>
                                    <th>ITBIS</th>
                                    <th>SUBTOTAL</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="carritoBody">
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">
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
                    <i class="fas fa-chart-line me-2 text-success"></i>
                    Resumen de Compra
                </h5>
                <div class="resumen-linea"><span>Subtotal:</span><span id="resumenSubtotal">RD$ 0.00</span></div>
                <div class="resumen-linea"><span>Descuento:</span><span id="resumenDescuento">RD$ 0.00</span></div>
                <div class="resumen-linea"><span>ITBIS (<?php echo $itbis_porcentaje; ?>%):</span><span id="resumenItbis">RD$ 0.00</span></div>
                <div class="resumen-linea total"><span><strong>TOTAL:</strong></span><span id="resumenTotal" class="text-success fw-bold">RD$ 0.00</span></div>
                <hr>
                <div class="d-grid gap-2 mt-3">
                    <button class="btn btn-success btn-lg" onclick="procesarCompra()">
                        <i class="fas fa-check-circle me-1"></i> Registrar Compra
                    </button>
                    <button class="btn btn-outline-secondary" onclick="cancelarCompra()">
                        <i class="fas fa-times-circle me-1"></i> Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA SELECCIONAR PRODUCTOS CON PESTAÑAS -->
<div class="modal fade" id="modalProductos" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-success text-white p-4">
                <h5 class="modal-title d-flex align-items-center">
                    <i class="fas fa-boxes me-2"></i>
                    Seleccionar Producto
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Pestañas manuales -->
                <div class="d-flex gap-2 border-bottom pb-2 mb-4">
                    <button class="tab-btn active" data-tab="medicamentos">
                        <i class="fas fa-capsules me-2"></i> Medicamentos
                    </button>
                    <button class="tab-btn" data-tab="ropa">
                        <i class="fas fa-tshirt me-2"></i> Ropa y Accesorios
                    </button>
                </div>
                
                <!-- Barra de búsqueda -->
                <div class="filtro-busqueda mb-3">
                    <div class="position-relative d-flex align-items-center">
                        <i class="fas fa-search position-absolute" style="left: 18px; color: #28a745; font-size: 1.2rem;"></i>
                        <input type="text" class="form-control form-control-lg" id="filtroNombreProducto" placeholder="Buscar por nombre..." style="padding-left: 48px; border-radius: 50px;">
                    </div>
                </div>
                
                <!-- Contenido de pestañas -->
                <div>
                    <div class="tab-pane active" id="tab-medicamentos">
                        <div class="lista-productos" id="listaMedicamentos">
                            <div class="text-center py-5">
                                <div class="spinner-border text-success"></div>
                                <p class="mt-2">Cargando medicamentos...</p>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane" id="tab-ropa">
                        <div class="lista-productos" id="listaRopa">
                            <div class="text-center py-5">
                                <div class="spinner-border text-success"></div>
                                <p class="mt-2">Cargando ropa...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA DATOS DEL PRODUCTO (MEDICAMENTO) -->
<div class="modal fade" id="modalDatosMedicamento" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-success text-white p-4">
                <h5 class="modal-title d-flex align-items-center">
                    <i class="fas fa-pills me-2"></i>
                    Datos del Medicamento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-3">
                    <i class="fas fa-capsules" style="font-size: 3rem; color: #28a745;"></i>
                </div>
                <h5 class="text-center" id="productoNombreModalMed"></h5>
                <div class="row g-3 mt-2">
                    <div class="col-12">
                        <label class="form-label fw-bold">Número de Lote</label>
                        <input type="text" class="form-control" id="numeroLote" placeholder="Ej: LOTE-001" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Fecha de Vencimiento</label>
                        <input type="date" class="form-control" id="fechaVencimiento" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Cantidad</label>
                        <input type="number" class="form-control" id="cantidadProducto" value="1" min="1" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Costo Unitario (RD$)</label>
                        <input type="number" step="0.01" class="form-control" id="costoUnitario" placeholder="0.00" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Descuento Unitario (RD$)</label>
                        <input type="number" step="0.01" class="form-control" id="descuentoUnitario" value="0">
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="aplicaItbis" checked>
                            <label class="form-check-label" for="aplicaItbis">Aplica ITBIS (<?php echo $itbis_porcentaje; ?>%)</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-end gap-3">
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success px-4" id="btnAgregarMedicamento">
                    <i class="fas fa-plus me-1"></i> Agregar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA DATOS DE ROPA -->
<div class="modal fade" id="modalDatosRopa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-success text-white p-4">
                <h5 class="modal-title d-flex align-items-center">
                    <i class="fas fa-tshirt me-2"></i>
                    Datos de la Ropa
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-3">
                    <i class="fas fa-tshirt" style="font-size: 3rem; color: #17a2b8;"></i>
                </div>
                <h5 class="text-center" id="productoNombreModalRopa"></h5>
                <div class="row g-3 mt-2">
                    <div class="col-12">
                        <label class="form-label fw-bold">Talla</label>
                        <select class="form-select" id="tallaRopa" required>
                            <option value="">Seleccionar talla...</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Color</label>
                        <select class="form-select" id="colorRopa" required>
                            <option value="">Seleccionar color...</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Cantidad</label>
                        <input type="number" class="form-control" id="cantidadRopa" value="1" min="1" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Costo Unitario (RD$)</label>
                        <input type="number" step="0.01" class="form-control" id="costoUnitarioRopa" placeholder="0.00" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Descuento Unitario (RD$)</label>
                        <input type="number" step="0.01" class="form-control" id="descuentoUnitarioRopa" value="0">
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="aplicaItbisRopa" checked>
                            <label class="form-check-label" for="aplicaItbisRopa">Aplica ITBIS (<?php echo $itbis_porcentaje; ?>%)</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-end gap-3">
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success px-4" id="btnAgregarRopa">
                    <i class="fas fa-plus me-1"></i> Agregar
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

// ==================== VARIABLES GLOBALES ====================
let carrito = [];
let productoSeleccionado = null;
let modalProductos = null;
let modalDatosMedicamento = null;
let modalDatosRopa = null;
let medicamentosData = [];
let ropaData = [];

let sucursalActual = null;
let proveedorActual = null;

// ==================== MANEJO DE PESTAÑAS ====================
function initTabs() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tabId = button.getAttribute('data-tab');
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));
            button.classList.add('active');
            const activePane = document.getElementById(`tab-${tabId}`);
            if (activePane) activePane.classList.add('active');
        });
    });
}

// ==================== FUNCIONES COLOR HEX ====================
function getColorHex(colorNombre) {
    const colores = {
        'rojo': '#dc3545', 'azul': '#007bff', 'verde': '#28a745',
        'negro': '#212529', 'blanco': '#f8f9fa', 'amarillo': '#ffc107',
        'gris': '#6c757d', 'morado': '#6f42c1', 'naranja': '#fd7e14',
        'rosa': '#e83e8c', 'celeste': '#17a2b8', 'marrón': '#795548'
    };
    return colores[colorNombre?.toLowerCase()] || '#6c757d';
}

// ==================== CARGAR PRODUCTOS ====================
function cargarProductosModal() {
    if (!sucursalActual) return;
    
    const listaMed = document.getElementById('listaMedicamentos');
    const listaRopa = document.getElementById('listaRopa');
    
    if (listaMed) listaMed.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando medicamentos...</p></div>';
    if (listaRopa) listaRopa.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando ropa...</p></div>';
    
    fetch(BASE_URL + `/backend/ventas/listar_productos_unificado.php?id_sucursal=${sucursalActual}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.productos) {
                medicamentosData = data.productos.filter(p => p.tipo === 'MEDICAMENTO');
                ropaData = data.productos.filter(p => p.tipo === 'ROPA');
                renderizarMedicamentos(medicamentosData);
                renderizarRopa(ropaData);
            } else {
                if (listaMed) listaMed.innerHTML = '<div class="text-center py-5 text-danger">Error al cargar medicamentos</div>';
                if (listaRopa) listaRopa.innerHTML = '<div class="text-center py-5 text-danger">Error al cargar ropa</div>';
            }
        })
        .catch(error => {
            console.error(error);
            if (listaMed) listaMed.innerHTML = '<div class="text-center py-5 text-danger">Error de conexión</div>';
            if (listaRopa) listaRopa.innerHTML = '<div class="text-center py-5 text-danger">Error de conexión</div>';
        });
}

function renderizarMedicamentos(medicamentos) {
    const listaDiv = document.getElementById('listaMedicamentos');
    if (!listaDiv) return;
    
    if (!medicamentos || medicamentos.length === 0) {
        listaDiv.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-capsules" style="font-size: 48px; opacity: 0.3;"></i>
                <p class="text-muted mt-2">No hay medicamentos disponibles</p>
            </div>
        `;
        return;
    }
    
    let html = '<div class="row g-3">';
    medicamentos.forEach(m => {
        const itbisBadge = m.exento_itbis 
            ? '<span class="badge-itbis exento">✓ Exento ITBIS</span>' 
            : '<span class="badge-itbis aplica">📄 Aplica ITBIS</span>';
        
        const productoJson = JSON.stringify(m).replace(/'/g, "\\'");
        
        html += `
            <div class="col-md-6 col-lg-4">
                <div class="producto-card" onclick='seleccionarProducto(${productoJson})'>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="nombre fw-bold">
                            <span class="badge-tipo-medicamento me-1">💊 MED</span>
                            ${escapeHtml(m.nombre)}
                        </div>
                    </div>
                    <div class="small text-secondary mt-2">
                        <i class="fas fa-tag me-1"></i> Presentación: ${escapeHtml(m.presentacion || 'Tabletas')}
                    </div>
                    <div class="mt-2">${itbisBadge}</div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    listaDiv.innerHTML = html;
}

function renderizarRopa(ropa) {
    const listaDiv = document.getElementById('listaRopa');
    if (!listaDiv) return;
    
    if (!ropa || ropa.length === 0) {
        listaDiv.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-tshirt" style="font-size: 48px; opacity: 0.3;"></i>
                <p class="text-muted mt-2">No hay ropa disponible</p>
            </div>
        `;
        return;
    }
    
    let html = '<div class="row g-3">';
    ropa.forEach(r => {
        const itbisBadge = r.exento_itbis 
            ? '<span class="badge-itbis exento">✓ Exento ITBIS</span>' 
            : '<span class="badge-itbis aplica">📄 Aplica ITBIS</span>';
        
        const productoJson = JSON.stringify(r).replace(/'/g, "\\'");
        
        html += `
            <div class="col-md-6 col-lg-4">
                <div class="producto-card" onclick='seleccionarProducto(${productoJson})'>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="nombre fw-bold">
                            <span class="badge-tipo-ropa me-1">👕 ROPA</span>
                            ${escapeHtml(r.nombre)}
                        </div>
                    </div>
                    <div class="mt-2">${itbisBadge}</div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    listaDiv.innerHTML = html;
}

function filtrarProductos() {
    const filtro = document.getElementById('filtroNombreProducto')?.value.toLowerCase() || '';
    const medFiltrados = medicamentosData.filter(m => m.nombre.toLowerCase().includes(filtro));
    const ropaFiltrados = ropaData.filter(r => r.nombre.toLowerCase().includes(filtro));
    renderizarMedicamentos(medFiltrados);
    renderizarRopa(ropaFiltrados);
}

// ==================== SELECCIONAR PRODUCTO ====================
function seleccionarProducto(producto) {
    productoSeleccionado = producto;
    
    if (producto.tipo === 'MEDICAMENTO') {
        document.getElementById('productoNombreModalMed').innerText = producto.nombre;
        document.getElementById('numeroLote').value = '';
        document.getElementById('fechaVencimiento').value = '';
        document.getElementById('cantidadProducto').value = 1;
        document.getElementById('costoUnitario').value = '';
        document.getElementById('descuentoUnitario').value = 0;
        document.getElementById('aplicaItbis').checked = !producto.exento_itbis;
        
        if (modalProductos) modalProductos.hide();
        if (modalDatosMedicamento) modalDatosMedicamento.show();
    } else {
        // Cargar tallas y colores para ropa
        cargarTallas();
        cargarColores();
        
        document.getElementById('productoNombreModalRopa').innerText = producto.nombre;
        document.getElementById('cantidadRopa').value = 1;
        document.getElementById('costoUnitarioRopa').value = '';
        document.getElementById('descuentoUnitarioRopa').value = 0;
        document.getElementById('aplicaItbisRopa').checked = !producto.exento_itbis;
        
        if (modalProductos) modalProductos.hide();
        if (modalDatosRopa) modalDatosRopa.show();
    }
}

function cargarTallas() {
    const select = document.getElementById('tallaRopa');
    if (!select) return;
    
    select.innerHTML = '<option value="">Cargando tallas...</option>';
    fetch(BASE_URL + '/backend/ropa/listar_tallas.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.tallas && data.tallas.length > 0) {
                select.innerHTML = '<option value="">Seleccionar talla...</option>';
                data.tallas.forEach(t => {
                    const option = document.createElement('option');
                    option.value = t.id_talla;
                    option.textContent = t.nombre;
                    select.appendChild(option);
                });
            } else {
                select.innerHTML = '<option value="">No hay tallas disponibles</option>';
                console.warn('No se encontraron tallas');
            }
        })
        .catch(error => {
            console.error('Error al cargar tallas:', error);
            select.innerHTML = '<option value="">Error al cargar tallas</option>';
        });
}

function cargarColores() {
    const select = document.getElementById('colorRopa');
    if (!select) return;
    
    select.innerHTML = '<option value="">Cargando colores...</option>';
    fetch(BASE_URL + '/backend/ropa/listar_colores.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.colores && data.colores.length > 0) {
                select.innerHTML = '<option value="">Seleccionar color...</option>';
                data.colores.forEach(c => {
                    const option = document.createElement('option');
                    option.value = c.id_color;
                    option.textContent = c.nombre;
                    select.appendChild(option);
                });
            } else {
                select.innerHTML = '<option value="">No hay colores disponibles</option>';
                console.warn('No se encontraron colores');
            }
        })
        .catch(error => {
            console.error('Error al cargar colores:', error);
            select.innerHTML = '<option value="">Error al cargar colores</option>';
        });
}

// ==================== AGREGAR AL CARRITO ====================
function agregarMedicamento() {
    const numeroLote = document.getElementById('numeroLote').value.trim();
    const fechaVencimiento = document.getElementById('fechaVencimiento').value;
    const cantidad = parseInt(document.getElementById('cantidadProducto').value);
    const costoUnitario = parseFloat(document.getElementById('costoUnitario').value);
    const descuentoUnitario = parseFloat(document.getElementById('descuentoUnitario').value);
    const aplicaItbis = document.getElementById('aplicaItbis').checked;
    
    if (!numeroLote) { Swal.fire('Error', 'Ingrese el número de lote', 'error'); return; }
    if (!fechaVencimiento) { Swal.fire('Error', 'Ingrese la fecha de vencimiento', 'error'); return; }
    if (cantidad <= 0) { Swal.fire('Error', 'Cantidad inválida', 'error'); return; }
    if (isNaN(costoUnitario) || costoUnitario <= 0) { Swal.fire('Error', 'Costo unitario inválido', 'error'); return; }
    
    const item = {
        tipo: 'MEDICAMENTO',
        id_producto: productoSeleccionado.id_medicamento,
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
    if (modalDatosMedicamento) modalDatosMedicamento.hide();
}

function agregarRopa() {
    const idTalla = document.getElementById('tallaRopa').value;
    const idColor = document.getElementById('colorRopa').value;
    const cantidad = parseInt(document.getElementById('cantidadRopa').value);
    const costoUnitario = parseFloat(document.getElementById('costoUnitarioRopa').value);
    const descuentoUnitario = parseFloat(document.getElementById('descuentoUnitarioRopa').value);
    const aplicaItbis = document.getElementById('aplicaItbisRopa').checked;
    
    if (!idTalla) { Swal.fire('Error', 'Seleccione una talla', 'error'); return; }
    if (!idColor) { Swal.fire('Error', 'Seleccione un color', 'error'); return; }
    if (cantidad <= 0) { Swal.fire('Error', 'Cantidad inválida', 'error'); return; }
    if (isNaN(costoUnitario) || costoUnitario <= 0) { Swal.fire('Error', 'Costo unitario inválido', 'error'); return; }
    
    const item = {
        tipo: 'ROPA',
        id_producto: productoSeleccionado.id_medicamento,
        nombre: productoSeleccionado.nombre,
        id_talla: parseInt(idTalla),
        id_color: parseInt(idColor),
        cantidad: cantidad,
        costo_unitario: costoUnitario,
        descuento_unitario: descuentoUnitario,
        aplica_itbis: aplicaItbis
    };
    carrito.push(item);
    actualizarCarrito();
    if (modalDatosRopa) modalDatosRopa.hide();
}

function actualizarCarrito() {
    const tbody = document.getElementById('carritoBody');
    if (carrito.length === 0) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted py-4"><i class="fas fa-shopping-cart" style="font-size:2rem; opacity:0.3"></i><p>No hay productos agregados</p></td></tr>`;
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
        
        let badgeTipo = '';
        let infoAdicional = '';
        
        if (item.tipo === 'ROPA') {
            badgeTipo = '<span class="badge-tipo-ropa me-1">👕 ROPA</span>';
            infoAdicional = `<small class="text-muted">Talla: ${item.id_talla || 'N/A'} | Color: ${item.id_color || 'N/A'}</small>`;
        } else {
            badgeTipo = '<span class="badge-tipo-medicamento me-1">💊 MED</span>';
            infoAdicional = `<small class="text-muted">Lote: ${escapeHtml(item.numero_lote)}</small><br>
                             <small class="text-muted">Vence: ${item.fecha_vencimiento}</small>`;
        }
        
        html += `
            <tr>
                <td>
                    ${badgeTipo}
                    <strong>${escapeHtml(item.nombre)}</strong>
                    ${infoAdicional}
                </td>
                <td>${item.tipo === 'MEDICAMENTO' ? escapeHtml(item.numero_lote) : 'N/A'}</small></td>
                <td>${item.tipo === 'MEDICAMENTO' ? item.fecha_vencimiento : 'N/A'}</small></td>
                <td>${item.tipo === 'ROPA' ? 'Talla/Color' : '—'}</small></td>
                <td><input type="number" class="cantidad-input" value="${item.cantidad}" min="1" onchange="actualizarCantidad(${idx}, this.value)"></td>
                <td class="text-end">RD$ ${item.costo_unitario.toFixed(2)}</small></td>
                <td class="text-end">RD$ ${item.descuento_unitario.toFixed(2)}</small></td>
                <td class="text-end">${item.aplica_itbis ? `Sí (${ITBIS_PORCENTAJE}%)` : 'No'}</td>
                <td class="fw-bold text-success text-end">RD$ ${totalItem.toFixed(2)}</td>
                <td class="text-center"><button class="btn-eliminar-item" onclick="eliminarProducto(${idx})"><i class="fas fa-trash-alt"></i></button></td>
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
        productos: carrito.map(item => ({
            tipo: item.tipo,
            id_producto: item.id_producto,
            numero_lote: item.numero_lote,
            fecha_vencimiento: item.fecha_vencimiento,
            id_talla: item.id_talla,
            id_color: item.id_color,
            cantidad: item.cantidad,
            costo_unitario: item.costo_unitario,
            descuento_unitario: item.descuento_unitario,
            aplica_itbis: item.aplica_itbis
        })),
        subtotal: subtotal,
        descuento: descuentoTotal,
        itbis: itbisTotal,
        total: total
    };
    
    console.log('Enviando datos:', data);
    
    Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch(BASE_URL + '/backend/compras/procesar_compra.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(res => {
        Swal.close();
        console.log('Response data:', res);
        if (res.success) {
            Swal.fire('Éxito', `Compra ${res.numero_documento} registrada`, 'success')
                .then(() => location.reload());
        } else {
            Swal.fire('Error', res.message || 'Error al registrar compra', 'error');
        }
    })
    .catch(err => { 
        Swal.close(); 
        console.error('Fetch error:', err);
        Swal.fire('Error', 'Error de conexión: ' + err.message, 'error'); 
    });
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

// ==================== INICIALIZACIÓN ====================
document.addEventListener('DOMContentLoaded', function() {
    initTabs();
    
    const elModalProductos = document.getElementById('modalProductos');
    const elModalMed = document.getElementById('modalDatosMedicamento');
    const elModalRopa = document.getElementById('modalDatosRopa');
    
    if (elModalProductos) modalProductos = new bootstrap.Modal(elModalProductos, { backdrop: false, keyboard: true });
    if (elModalMed) modalDatosMedicamento = new bootstrap.Modal(elModalMed, { backdrop: false, keyboard: true });
    if (elModalRopa) modalDatosRopa = new bootstrap.Modal(elModalRopa, { backdrop: false, keyboard: true });
    
    document.getElementById('btnAgregarProducto').addEventListener('click', () => {
        if (!sucursalActual) {
            Swal.fire('Error', 'Primero debe seleccionar una sucursal', 'error');
            return;
        }
        if (modalProductos) modalProductos.show();
    });
    
    document.getElementById('btnAgregarMedicamento').addEventListener('click', agregarMedicamento);
    document.getElementById('btnAgregarRopa').addEventListener('click', agregarRopa);
    
    document.getElementById('sucursal').addEventListener('change', function() {
        sucursalActual = this.value;
        if (carrito.length > 0) {
            Swal.fire({
                title: 'Cambio de sucursal',
                text: 'Los productos del carrito pertenecen a otra sucursal. ¿Desea vaciar el carrito?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, vaciar',
                cancelButtonText: 'No'
            }).then(result => {
                if (result.isConfirmed) { carrito = []; actualizarCarrito(); }
            });
        }
    });
    
    document.getElementById('filtroNombreProducto').addEventListener('input', filtrarProductos);
    
    if (elModalProductos) {
        elModalProductos.addEventListener('show.bs.modal', function(event) {
            if (!sucursalActual) {
                event.preventDefault();
                Swal.fire('Error', 'Primero debe seleccionar una sucursal', 'error');
            } else {
                cargarProductosModal();
            }
        });
    }
    
    sucursalActual = null;
});
</script>