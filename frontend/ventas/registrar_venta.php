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

// Rol y sucursal asignada del usuario actual (para bloquear el selector si es Cajero)
$rol_usuario_actual = '';
$id_sucursal_usuario = null;
$nombre_sucursal_usuario = '';
try {
    $stmt = $conexion->prepare("
        SELECT r.nombre AS rol_nombre, u.id_sucursal, s.nombre AS sucursal_nombre
        FROM usuarios u
        LEFT JOIN roles r ON r.id_rol = u.id_rol
        LEFT JOIN sucursales s ON s.id_sucursal = u.id_sucursal
        WHERE u.id_usuario = :id
    ");
    $stmt->execute([':id' => $usuario_actual]);
    $infoUsuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($infoUsuario) {
        $rol_usuario_actual = $infoUsuario['rol_nombre'] ?? '';
        $id_sucursal_usuario = $infoUsuario['id_sucursal'];
        $nombre_sucursal_usuario = $infoUsuario['sucursal_nombre'] ?? '';
    }
} catch (PDOException $e) {}

// El cajero tiene su sucursal fija; solo se bloquea si de verdad tiene una asignada
$sucursal_bloqueada = ($rol_usuario_actual === 'Cajero' && $id_sucursal_usuario);

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

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
    /* (todos los estilos originales se mantienen, solo se añade uno nuevo para el botón reintentar) */
    .btn-reintentar {
        background-color: #ffc107;
        color: #000;
        border: none;
        border-radius: 20px;
        padding: 5px 15px;
        font-size: 0.8rem;
    }
    /* El resto de estilos ya están en el código original, se omiten por brevedad pero se conservan */
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

    .delivery-info {
        background: #e3f2fd;
        border-left: 4px solid #17a2b8;
        padding: 12px;
        border-radius: 8px;
        margin-top: 15px;
    }
    .btn-quitar-delivery {
        background-color: #dc3545;
        color: white;
        border: none;
        border-radius: 20px;
        padding: 4px 12px;
        font-size: 0.75rem;
    }
    .btn-quitar-delivery:hover { background-color: #b02a37; }

    .delivery-modal .modal-content {
        border-radius: 20px;
    }
    .delivery-modal .modal-header {
        background: #17a2b8;
        border-bottom: none;
    }

    .tarjeta-repartidor { border:1.5px solid #dee2e6; border-radius:10px; padding:.7rem .9rem; cursor:pointer; transition:all .15s; }
    .tarjeta-repartidor:hover { border-color:#0dcaf0; background:#f0fdff; }
    .tarjeta-repartidor.selected { border-color:#0dcaf0; background:#e7fbff; box-shadow:0 0 0 2px rgba(13,202,240,.25); }
    .hab-mini { background:#EAF1F8; color:#1F5C99; border-radius:12px; padding:.15em .55em; font-size:.68rem; font-weight:600; margin-right:.25rem; }    

    .direccion-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 12px;
        border-left: 4px solid #17a2b8;
    }
    #direccionEntregaSelect {
        border: 1px solid #dee2e6;
        border-radius: 10px;
        padding: 8px 12px;
        width: 100%;
    }

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

    .badge-itbis.aplica { background-color: #fff3cd; color: #856404; }
    .badge-itbis.exento { background-color: #d4edda; color: #155724; }

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

    .producto-card {
        transition: all 0.2s ease;
        cursor: pointer;
        border: 1px solid #e9ecef;
        border-radius: 16px;
        padding: 16px;
        margin-bottom: 12px;
        background: white;
    }

    .producto-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        border-color: #28a745;
        background: linear-gradient(135deg, #ffffff 0%, #f8fff9 100%);
    }

    .producto-card .nombre {
        font-weight: 600;
        color: #2c3e50;
        font-size: 0.95rem;
    }

    .producto-card .precio {
        color: #28a745;
        font-weight: 700;
        font-size: 1.1rem;
    }

    .producto-card .stock {
        font-size: 0.7rem;
        padding: 4px 10px;
        border-radius: 20px;
        background-color: #e9ecef;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .producto-card .stock.bajo { background-color: #fff3cd; color: #856404; }
    .producto-card .stock.critico { background-color: #f8d7da; color: #721c24; }
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
                            <?php if ($sucursal_bloqueada): ?>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($nombre_sucursal_usuario); ?>" readonly style="background-color: #f8f9fa;">
                                <select class="form-select d-none" id="sucursal">
                                    <option value="<?php echo $id_sucursal_usuario; ?>" selected><?php echo htmlspecialchars($nombre_sucursal_usuario); ?></option>
                                </select>
                            <?php else: ?>
                                <select class="form-select" id="sucursal">
                                    <option value="">Seleccionar sucursal...</option>
                                    <?php foreach ($sucursales as $suc): ?>
                                        <option value="<?php echo $suc['id_sucursal']; ?>"><?php echo htmlspecialchars($suc['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">CLIENTE</label>
                            <select class="form-select" id="cliente">
                                <option value="">Consumidor Final</option>
                                <?php foreach ($clientes as $cli): ?>
                                    <option value="<?php echo $cli['id_cliente']; ?>" 
                                            data-permite-credito="<?php echo $cli['permite_credito']; ?>" 
                                            data-tiene-seguro="<?php echo $cli['tiene_seguro']; ?>">
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
                    <button type="button" class="btn btn-success btn-lg px-5" id="btnAgregarProducto">
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
                    <span class="material-symbols-rounded me-2 text-success"></span>
                    Resumen de Venta
                </h5>
                <div class="resumen-linea"><span>Subtotal:</span><span id="resumenSubtotal">RD$ 0.00</span></div>
                <div class="resumen-linea"><span>ITBIS (<?php echo $itbis_porcentaje; ?>%):</span><span id="resumenItbis">RD$ 0.00</span></div>
                <div class="resumen-linea" id="resumenSeguroLinea" style="display: none;">
                    <span class="texto-seguro">SEGURO MÉDICO:</span>
                    <span id="resumenSeguro" class="text-info fw-bold">- RD$ 0.00</span>
                </div>
                <div class="resumen-linea" id="resumenEnvioLinea" style="display: none;">
                    <span>ENVÍO:</span>
                    <span id="resumenEnvio" class="text-info fw-bold">RD$ 0.00</span>
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
                            <option value="<?php echo $cp['id_condicion']; ?>" <?php echo $cp['id_condicion'] == 1 ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cp['nombre']); ?>
                            </option>
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

                <!-- SECCIÓN RECETA MÉDICA -->
                <div class="mt-3 mb-3 p-3" style="background:#f8f9fa;border-radius:10px;border:1px solid #dee2e6;">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="conReceta" onchange="toggleReceta()">
                        <label class="form-check-label fw-bold small" for="conReceta">Esta venta incluye receta médica</label>
                    </div>
                    <div id="divFotoReceta" style="display:none;">
                        <label class="form-label small text-muted">Foto de la receta (JPG, PNG o PDF, máx. 5MB)</label>
                        <input type="file" class="form-control form-control-sm" id="fotoReceta" accept="image/jpeg,image/png,image/webp,application/pdf">
                    </div>
                </div>

                <!-- SECCIÓN TIPO DE DESPACHO -->
                <div class="mt-3">
                    <label class="form-label fw-bold small text-muted">TIPO DE DESPACHO</label>
                    <div class="d-flex gap-2 mb-2">
                        <button type="button" class="btn btn-outline-success flex-fill" id="btnRetiroPersonal" onclick="seleccionarDespacho('RETIRO_PERSONAL')">
                            <span class="material-symbols-rounded align-middle me-1">storefront</span> Retiro Personal
                        </button>
                        <button type="button" class="btn btn-outline-info flex-fill" id="btnEnviarDelivery" onclick="seleccionarDespacho('DELIVERY')">
                            <span class="material-symbols-rounded align-middle me-1">local_shipping</span> Delivery
                        </button>
                    </div>

                    <div id="deliveryState" style="display: none;" class="delivery-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><span class="material-symbols-rounded" style="font-size: 1rem;">local_shipping</span> Delivery asignado</strong><br>
                                <small>Repartidor: <span id="deliveryRepartidor"></span></small><br>
                                <small>Vehículo: <span id="deliveryVehiculo"></span></small><br>
                                <small>Costo: RD$ <span id="deliveryCosto"></span></small><br>
                                <small>Dirección: <span id="deliveryDireccion"></span></small>
                            </div>
                            <button type="button" class="btn-quitar-delivery" onclick="quitarDelivery()">
                                <span class="material-symbols-rounded" style="font-size: 1rem;">delete</span> Quitar
                            </button>
                        </div>
                    </div>
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

<!-- Modal Productos con pestañas -->
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
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-warning btn-sm" id="btnReintentarCarga" style="display: none;" onclick="cargarProductosModal()">
                        <i class="fas fa-sync-alt me-1"></i> Reintentar
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cerrar</button>
                </div>
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

<!-- MODAL DE DELIVERY -->
<div class="modal fade" id="modalDelivery" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg delivery-modal">
            <div class="modal-header bg-info text-white p-4">
                <h5 class="modal-title d-flex align-items-center">
                    <span class="material-symbols-rounded me-2">local_shipping</span>
                    Configurar Envío a Domicilio
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Nº DOCUMENTO</label>
                        <input type="text" class="form-control" id="modalNumeroDocumento" readonly style="background-color: #f8f9fa;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">FECHA</label>
                        <input type="text" class="form-control" id="modalFechaVenta" readonly style="background-color: #f8f9fa;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">CLIENTE</label>
                        <input type="text" class="form-control" id="modalClienteNombre" readonly style="background-color: #f8f9fa;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">SUCURSAL</label>
                        <input type="text" class="form-control" id="modalSucursalNombre" readonly style="background-color: #f8f9fa;">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-muted">DIRECCIÓN DE ENTREGA *</label>
                    <select class="form-select" id="direccionEntrega" required onchange="calcularCostoEnvioAutomatico()">
                        <option value="">Cargando direcciones...</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-muted">PASO 1 — ELEGIR REPARTIDOR *</label>
                    <div id="listaRepartidoresDisponibles" class="d-flex flex-column gap-2">
                        <p class="text-muted small">Cargando repartidores disponibles...</p>
                    </div>
                    <input type="hidden" id="repartidorSeleccionadoId">
                    <input type="hidden" id="vehiculoSeleccionadoId">
                </div>

                <div class="mb-3" id="pasoVehiculos" style="display:none;">
                    <label class="form-label fw-bold text-muted">PASO 2 — ELEGIR VEHÍCULO *</label>
                    <div id="listaVehiculosRepartidor" class="d-flex flex-wrap"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-muted">COSTO DE ENVÍO (RD$) *</label>
                    <input type="number" step="0.01" class="form-control" id="costoEnvio" value="0" required readonly style="background-color:#f8f9fa;">
                    <small class="text-muted" id="detalleCostoEnvio">Selecciona una dirección para calcular el costo</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-muted">OBSERVACIONES (OPCIONAL)</label>
                    <textarea class="form-control" id="observacionesDelivery" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-end gap-3">
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-info text-white px-4" onclick="guardarDelivery()">
                    <span class="material-symbols-rounded align-middle me-1">check</span> Asignar Delivery
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
let modalCantidad = null;
let modalDelivery = null;
let medicamentosData = [];
let ropaData = [];
let datosSeguroCliente = null;
let coberturaActual = null;
let descuentoSeleccionado = { id: null, valor: 0, esPorcentaje: false, montoAplicado: 0 };

let sucursalActual = null;
let deliveryActivo = false;
let costoEnvio = 0;
let deliveryAsignado = null;
let tipoDespachoSeleccionado = null;

// ==================== FUNCIONES DE DESCUENTO ====================
function aplicarDescuento(base) {
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
        monto = base * (valor / 100);
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

// ==================== MANEJO DE PESTAÑAS MANUALES ====================
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

// ==================== FUNCIÓN AUXILIAR COLOR HEX ====================
function getColorHex(colorNombre) {
    const colores = {
        'rojo': '#dc3545', 'roja': '#dc3545', 'red': '#dc3545',
        'azul': '#007bff', 'blue': '#007bff',
        'verde': '#28a745', 'green': '#28a745',
        'negro': '#212529', 'black': '#212529',
        'blanco': '#f8f9fa', 'white': '#f8f9fa',
        'amarillo': '#ffc107', 'yellow': '#ffc107',
        'gris': '#6c757d', 'gray': '#6c757d', 'grey': '#6c757d',
        'morado': '#6f42c1', 'purple': '#6f42c1',
        'naranja': '#fd7e14', 'orange': '#fd7e14',
        'rosa': '#e83e8c', 'pink': '#e83e8c',
        'celeste': '#17a2b8', 'cyan': '#17a2b8',
        'marrón': '#795548', 'brown': '#795548'
    };
    return colores[colorNombre?.toLowerCase()] || '#6c757d';
}

// ==================== RECÁLCULO ====================
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
    let total = subtotalConDescuento + nuevoItbis;
    
    if (deliveryActivo && costoEnvio > 0) {
        total += costoEnvio;
        document.getElementById('resumenEnvioLinea').style.display = 'flex';
        document.getElementById('resumenEnvio').innerHTML = `RD$ ${costoEnvio.toFixed(2)}`;
    } else {
        document.getElementById('resumenEnvioLinea').style.display = 'none';
    }
    
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
            const montoPacienteSinDescuento = coberturaActual.monto_paga_paciente;
            const descuentoMonto = aplicarDescuento(montoPacienteSinDescuento);
            let totalFinal = montoPacienteSinDescuento - descuentoMonto;
            
            if (deliveryActivo && costoEnvio > 0) {
                totalFinal += costoEnvio;
                document.getElementById('resumenEnvioLinea').style.display = 'flex';
                document.getElementById('resumenEnvio').innerHTML = `RD$ ${costoEnvio.toFixed(2)}`;
            } else {
                document.getElementById('resumenEnvioLinea').style.display = 'none';
            }
            
            document.getElementById('resumenSubtotal').innerHTML = `RD$ ${coberturaActual.subtotal.toFixed(2)}`;
            document.getElementById('resumenItbis').innerHTML = `RD$ ${coberturaActual.itbis_total.toFixed(2)}`;
            document.getElementById('resumenTotal').innerHTML = `RD$ ${totalFinal.toFixed(2)}`;
            document.getElementById('resumenSeguroLinea').style.display = 'flex';
            document.getElementById('resumenSeguro').innerHTML = `- RD$ ${coberturaActual.monto_cubre_seguro.toFixed(2)}`;
            document.getElementById('seguroInfo').style.display = 'block';
            document.getElementById('montoSeguro').innerHTML = `RD$ ${coberturaActual.monto_cubre_seguro.toFixed(2)}`;
            
            if (coberturaActual.requiere_autorizacion && coberturaActual.autorizaciones_faltantes?.length > 0) {
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
        console.error(error);
        datosSeguroCliente = null;
        document.getElementById('cardSeguro').style.display = 'none';
        return false;
    }
}

// ==================== MANEJO DEL CARRITO ====================
function abrirModalProductos() {
    if (!sucursalActual) {
        Swal.fire('Error', 'Primero debe seleccionar una sucursal', 'error');
        return;
    }
    if (modalProductos) modalProductos.show();
}

// ==================== FUNCIONES PARA CARGAR PRODUCTOS ====================
function cargarProductosModal() {
    if (!sucursalActual) return;
    
    const listaMed = document.getElementById('listaMedicamentos');
    const listaRopa = document.getElementById('listaRopa');
    const btnReintentar = document.getElementById('btnReintentarCarga');
    
    if (listaMed) listaMed.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando medicamentos...</p></div>';
    if (listaRopa) listaRopa.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando ropa...</p></div>';
    if (btnReintentar) btnReintentar.style.display = 'none';
    
    fetch(BASE_URL + `/backend/ventas/listar_productos_unificado.php?id_sucursal=${sucursalActual}`)
        .then(response => {
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (data.success && data.productos) {
                medicamentosData = data.productos.filter(p => p.tipo === 'MEDICAMENTO');
                ropaData = data.productos.filter(p => p.tipo === 'ROPA');
                renderizarMedicamentos(medicamentosData);
                renderizarRopa(ropaData);
            } else {
                throw new Error(data.message || 'Error en la respuesta del servidor');
            }
        })
        .catch(error => {
            console.error('Error cargando productos:', error);
            if (listaMed) listaMed.innerHTML = `<div class="text-center py-5 text-danger">
                <i class="fas fa-exclamation-triangle" style="font-size: 2rem;"></i>
                <p>Error al cargar productos: ${error.message}</p>
                <button class="btn btn-warning btn-sm mt-2" onclick="cargarProductosModal()">
                    <i class="fas fa-sync-alt me-1"></i> Reintentar
                </button>
            </div>`;
            if (listaRopa) listaRopa.innerHTML = `<div class="text-center py-5 text-danger">
                <i class="fas fa-exclamation-triangle" style="font-size: 2rem;"></i>
                <p>Error al cargar productos: ${error.message}</p>
                <button class="btn btn-warning btn-sm mt-2" onclick="cargarProductosModal()">
                    <i class="fas fa-sync-alt me-1"></i> Reintentar
                </button>
            </div>`;
            if (btnReintentar) btnReintentar.style.display = 'inline-block';
        });
}

function renderizarMedicamentos(medicamentos) {
    const listaDiv = document.getElementById('listaMedicamentos');
    if (!listaDiv) return;
    
    if (!medicamentos || medicamentos.length === 0) {
        listaDiv.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-capsules" style="font-size: 48px; opacity: 0.3;"></i>
                <p class="text-muted mt-2">No hay medicamentos disponibles en esta sucursal</p>
            </div>
        `;
        return;
    }
    
    let html = '<div class="row g-3">';
    medicamentos.forEach(m => {
        let stockClass = '', stockText = `${m.stock} unidades`;
        if (m.stock < 5) { stockClass = 'critico'; stockText = `⚠️ Stock crítico: ${m.stock}`; }
        else if (m.stock < 10) { stockClass = 'bajo'; stockText = `⚠️ Stock bajo: ${m.stock}`; }
        
        const itbisBadge = m.exento_itbis 
            ? '<span class="badge-itbis exento">✓ Exento ITBIS</span>' 
            : '<span class="badge-itbis aplica">📄 Aplica ITBIS</span>';
        
        const productoJson = JSON.stringify(m).replace(/'/g, "\\'");
        
        html += `
            <div class="col-md-6 col-lg-4">
                <div class="producto-card" onclick='seleccionarProducto(${productoJson})'>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="nombre fw-bold">${escapeHtml(m.nombre)}</div>
                        <div class="precio fs-5 fw-bold">RD$ ${parseFloat(m.precio).toFixed(2)}</div>
                    </div>
                    <div class="small text-secondary mb-2">
                        <i class="fas fa-tag me-1"></i> Lote: ${escapeHtml(m.numero_lote || 'N/A')} | 
                        <i class="far fa-calendar-alt me-1"></i> Vence: ${m.fecha_vencimiento || 'N/A'}
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="stock ${stockClass}"><i class="fas fa-boxes me-1"></i> ${stockText}</span>
                        ${itbisBadge}
                    </div>
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
                <p class="text-muted mt-2">No hay ropa disponible en esta sucursal</p>
            </div>
        `;
        return;
    }
    
    let html = '<div class="row g-3">';
    ropa.forEach(r => {
        let stockClass = '', stockText = `${r.stock} unidades`;
        if (r.stock < 3) { stockClass = 'critico'; stockText = `⚠️ Stock crítico: ${r.stock}`; }
        else if (r.stock < 6) { stockClass = 'bajo'; stockText = `⚠️ Stock bajo: ${r.stock}`; }
        
        const itbisBadge = r.exento_itbis 
            ? '<span class="badge-itbis exento">✓ Exento ITBIS</span>' 
            : '<span class="badge-itbis aplica">📄 Aplica ITBIS</span>';
        
        const tallaBadge = r.talla ? `<span class="badge bg-secondary me-1"><i class="fas fa-ruler me-1"></i>${escapeHtml(r.talla)}</span>` : '';
        const colorHex = getColorHex(r.color);
        const colorBadge = r.color ? `<span class="badge" style="background-color: ${colorHex}; color: white;"><i class="fas fa-palette me-1"></i>${escapeHtml(r.color)}</span>` : '';
        
        const productoJson = JSON.stringify(r).replace(/'/g, "\\'");
        
        html += `
            <div class="col-md-6 col-lg-4">
                <div class="producto-card" onclick='seleccionarProducto(${productoJson})'>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="nombre fw-bold">${escapeHtml(r.nombre)}</div>
                        <div class="precio fs-5 fw-bold">RD$ ${parseFloat(r.precio).toFixed(2)}</div>
                    </div>
                    <div class="small text-secondary mb-2">
                        ${tallaBadge} ${colorBadge}
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="stock ${stockClass}"><i class="fas fa-boxes me-1"></i> ${stockText}</span>
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
    const medFiltrados = medicamentosData.filter(m => m.nombre.toLowerCase().includes(filtroNombre));
    const ropaFiltrados = ropaData.filter(r => r.nombre.toLowerCase().includes(filtroNombre));
    renderizarMedicamentos(medFiltrados);
    renderizarRopa(ropaFiltrados);
}

function seleccionarProducto(producto) {
    console.log('Producto seleccionado:', producto);
    
    productoSeleccionado = {
        tipo: producto.tipo,
        id_lote: producto.id_lote,
        id_producto: producto.id_medicamento,
        id_talla: producto.id_talla,
        id_color: producto.id_color,
        nombre: producto.nombre,
        precio: parseFloat(producto.precio),
        stock: parseInt(producto.stock),
        aplica_itbis: !producto.exento_itbis,
        talla: producto.talla,
        color: producto.color,
        numero_lote: producto.numero_lote
    };
    
    document.getElementById('productoNombreModal').innerText = producto.nombre;
    let infoText = `Stock: ${producto.stock} | Precio: RD$ ${parseFloat(producto.precio).toFixed(2)}`;
    if (producto.tipo === 'ROPA') {
        infoText += ` | Talla: ${producto.talla || 'N/A'} | Color: ${producto.color || 'N/A'}`;
    } else {
        infoText += ` | Lote: ${producto.numero_lote || 'N/A'}`;
    }
    
    document.getElementById('productoInfoModal').innerHTML = infoText;
    document.getElementById('cantidadProducto').value = 1;
    document.getElementById('precioProducto').value = parseFloat(producto.precio).toFixed(2);
    document.getElementById('stockDisponible').innerText = producto.stock;
    document.getElementById('stockAdvertencia').style.display = producto.stock < 10 ? 'block' : 'none';
    
    if (modalProductos) modalProductos.hide();
    if (modalCantidad) modalCantidad.show();
}

function confirmarAgregarProducto() {
    if (!productoSeleccionado) {
        Swal.fire('Error', 'No hay producto seleccionado', 'error');
        return;
    }
    
    const cantidad = parseInt(document.getElementById('cantidadProducto').value);
    const stock = productoSeleccionado.stock;
    
    if (isNaN(cantidad) || cantidad < 1) { 
        Swal.fire('Error', 'Cantidad inválida', 'error'); 
        return; 
    }
    if (cantidad > stock) { 
        Swal.fire('Stock insuficiente', `Solo hay ${stock} unidades disponibles`, 'warning'); 
        return; 
    }
    
    let existente = null;
    if (productoSeleccionado.tipo === 'MEDICAMENTO') {
        existente = carrito.find(item => 
            item.tipo === 'MEDICAMENTO' && 
            item.id_lote === productoSeleccionado.id_lote
        );
    } else {
        existente = carrito.find(item => 
            item.tipo === 'ROPA' && 
            item.id_producto === productoSeleccionado.id_producto &&
            item.id_talla === productoSeleccionado.id_talla &&
            item.id_color === productoSeleccionado.id_color
        );
    }
    
    if (existente) {
        const nuevaCantidad = existente.cantidad + cantidad;
        if (nuevaCantidad > stock) { 
            Swal.fire('Stock insuficiente', `Solo hay ${stock} unidades disponibles`, 'warning'); 
            return; 
        }
        existente.cantidad = nuevaCantidad;
    } else {
        carrito.push({ ...productoSeleccionado, cantidad: cantidad });
    }
    
    actualizarCarrito();
    if (modalCantidad) modalCantidad.hide();
    productoSeleccionado = null;
}

function actualizarCarrito() {
    const tbody = document.getElementById('carritoBody');
    if (carrito.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-shopping-cart" style="font-size: 2rem; opacity: 0.3;"></i><p>No hay productos agregados</p></td></tr>`;
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
        
        let infoAdicional = '', badgeTipo = '';
        if (item.tipo === 'ROPA') {
            badgeTipo = '<span class="badge-tipo-ropa me-1">👕 ROPA</span>';
            infoAdicional = `<br><small class="text-muted">Talla: ${item.talla || 'N/A'} | Color: ${item.color || 'N/A'}</small>`;
        } else {
            badgeTipo = '<span class="badge-tipo-medicamento me-1">💊 MED</span>';
            infoAdicional = `<br><small class="text-muted">Lote: ${item.id_lote}</small>`;
        }
        
        html += `
            <tr>
                <td style="min-width: 200px;">
                    ${badgeTipo}
                    <strong>${escapeHtml(item.nombre)}</strong>
                    ${infoAdicional}
                </td>
                <td><input type="number" class="cantidad-input" value="${item.cantidad}" min="1" max="${item.stock}" onchange="actualizarCantidad(${i}, this.value)"></td>
                <td class="text-end">RD$ ${item.precio.toFixed(2)}</td>
                <td class="${itbisClass} text-end">${itbisText}</td>
                <td class="fw-bold text-success text-end">RD$ ${totalConItbis.toFixed(2)}</td>
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

// ==================== DELIVERY ====================
function abrirModalDelivery() {
    const clienteId = document.getElementById('cliente').value;
    if (!clienteId) {
        Swal.fire('Error', 'Debe seleccionar un cliente antes de asignar delivery', 'error');
        return;
    }
    
    document.getElementById('modalNumeroDocumento').value = document.getElementById('numeroDocumento').value;
    document.getElementById('modalFechaVenta').value = document.getElementById('fechaVenta').value;
    
    const clienteNombre = document.getElementById('cliente').options[document.getElementById('cliente').selectedIndex]?.text || 'Consumidor Final';
    const sucursalNombre = document.getElementById('sucursal').options[document.getElementById('sucursal').selectedIndex]?.text || '';
    
    document.getElementById('modalClienteNombre').value = clienteNombre;
    document.getElementById('modalSucursalNombre').value = sucursalNombre;
    
    cargarDireccionesCliente(clienteId);
    cargarRepartidoresDisponibles();
    
    if (modalDelivery) modalDelivery.show();
}

async function cargarDireccionesCliente(idCliente) {
    const select = document.getElementById('direccionEntrega');
    if (!select) return;
    
    select.innerHTML = '<option value="">Cargando direcciones...</option>';
    try {
        const response = await fetch(BASE_URL + `/backend/clientes/listar_direcciones_cliente.php?id_cliente=${idCliente}`);
        const data = await response.json();
        if (data.success && data.direcciones && data.direcciones.length > 0) {
            select.innerHTML = '';
            data.direcciones.forEach(dir => {
                const option = document.createElement('option');
                option.value = dir.id_direccion;
                option.textContent = dir.direccion_completa || dir.direccion;
                option.dataset.direccion = dir.direccion;
                option.dataset.barrio = dir.barrio || '';
                option.dataset.ciudad = dir.ciudad || '';
                option.dataset.referencia = dir.referencia || '';
                option.dataset.completa = dir.direccion_completa || dir.direccion;
                if (dir.predeterminada) option.selected = true;
                select.appendChild(option);
            });
        } else {
            select.innerHTML = '<option value="">No hay direcciones registradas para este cliente</option>';
        }
    } catch(error) {
        console.error(error);
        select.innerHTML = '<option value="">Error al cargar direcciones</option>';
    }

    // El navegador NO dispara "change" solo por rellenar el <select> con JS
    // (ni aunque solo haya una dirección y quede seleccionada sola) — así
    // que hay que llamar el cálculo a mano justo después de cargar.
    calcularCostoEnvioAutomatico();
}

let repartidoresDisponiblesData = [];

async function cargarRepartidoresDisponibles() {
    const cont = document.getElementById('listaRepartidoresDisponibles');
    if (!cont) return;

    cont.innerHTML = '<p class="text-muted small">Cargando repartidores disponibles...</p>';
    document.getElementById('repartidorSeleccionadoId').value = '';
    document.getElementById('vehiculoSeleccionadoId').value = '';
    document.getElementById('pasoVehiculos').style.display = 'none';
    window.__enColaSel = false;

    try {
        const response = await fetch(BASE_URL + '/backend/ventas/listar_repartidores_disponibles.php');
        const data = await response.json();
        repartidoresDisponiblesData = data.repartidores || [];

        if (!data.success || repartidoresDisponiblesData.length === 0) {
            cont.innerHTML = `
                <div class="alert alert-warning py-2 px-3 mb-2" style="font-size:.85rem;">
                    <span class="material-symbols-rounded align-middle me-1">schedule</span>
                    <strong>No hay repartidores disponibles en este momento.</strong><br>
                    Puedes poner este pedido en la cola de espera: se asignará
                    automáticamente al primer repartidor que quede libre y tenga
                    un vehículo disponible que sepa manejar.
                </div>
                <button type="button" class="btn btn-warning w-100" onclick="marcarParaCola()">
                    <span class="material-symbols-rounded align-middle me-1">schedule</span>
                    Poner en cola de espera
                </button>`;
            return;
        }

        cont.innerHTML = repartidoresDisponiblesData.map(rep => {
            const habs = (rep.habilidades && rep.habilidades.length)
                ? rep.habilidades.map(h => `<span class="hab-mini">${h.tipo_vehiculo}</span>`).join('')
                : '<span class="text-muted" style="font-size:.72rem;">Sin habilidades registradas</span>';
            return `
                <div class="tarjeta-repartidor" id="tarjeta-rep-${rep.id_repartidor}"
                     onclick="elegirRepartidor(${rep.id_repartidor}, '${rep.nombre.replace(/'/g,"")}')">
                    <strong>${rep.nombre}</strong> ${rep.telefono ? `<small class="text-muted">(${rep.telefono})</small>` : ''}
                    <div class="mt-1">${habs}</div>
                </div>`;
        }).join('');

    } catch (error) {
        console.error(error);
        cont.innerHTML = '<p class="text-danger small mb-0">Error al cargar repartidores disponibles.</p>';
    }
}

function elegirRepartidor(idRepartidor, nombreRep) {
    document.querySelectorAll('.tarjeta-repartidor').forEach(t => t.classList.remove('selected'));
    document.getElementById('tarjeta-rep-' + idRepartidor).classList.add('selected');

    document.getElementById('repartidorSeleccionadoId').value = idRepartidor;
    document.getElementById('vehiculoSeleccionadoId').value = '';
    window.__repartidorNombreSel = nombreRep;
    window.__enColaSel = false;

    const pasoVeh = document.getElementById('pasoVehiculos');
    pasoVeh.style.display = 'block';
    document.getElementById('listaVehiculosRepartidor').innerHTML = '<p class="text-muted small">Cargando vehículos...</p>';

    fetch(BASE_URL + `/backend/ventas/listar_vehiculos_para_repartidor.php?id_repartidor=${idRepartidor}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('listaVehiculosRepartidor').innerHTML =
                    `<p class="text-danger small mb-0">${data.message}</p>`;
                return;
            }
            if (!data.vehiculos.length) {
                document.getElementById('listaVehiculosRepartidor').innerHTML =
                    '<p class="text-danger small mb-0">Este repartidor no tiene ningún vehículo disponible ahora mismo.</p>';
                return;
            }
            document.getElementById('listaVehiculosRepartidor').innerHTML = data.vehiculos.map(v => `
                <button type="button" class="btn btn-sm btn-outline-secondary me-2 mb-2"
                    onclick="elegirVehiculo(event, ${v.id_vehiculo}, '${v.tipo}', '${v.placa||''}')">
                    ${v.tipo}${v.placa ? ' ('+v.placa+')' : ''}
                </button>`).join('');
        });
}

function elegirVehiculo(evt, idVehiculo, tipo, placa) {
    document.querySelectorAll('#listaVehiculosRepartidor button').forEach(b => {
        b.classList.remove('btn-primary'); b.classList.add('btn-outline-secondary');
    });
    evt.currentTarget.classList.remove('btn-outline-secondary');
    evt.currentTarget.classList.add('btn-primary');

    document.getElementById('vehiculoSeleccionadoId').value = idVehiculo;
    window.__vehiculoTextoSel = tipo + (placa ? ' (' + placa + ')' : '');
}

function marcarParaCola() {
    window.__enColaSel = true;
    document.getElementById('repartidorSeleccionadoId').value = '';
    document.getElementById('vehiculoSeleccionadoId').value = '';
    window.__repartidorNombreSel = 'En cola de espera';
    window.__vehiculoTextoSel = '(se asignará automáticamente)';
    guardarDelivery();
}

function calcularCostoEnvioAutomatico() {
    const idDireccion = document.getElementById('direccionEntrega').value;
    const idSucursal = document.getElementById('sucursal').value;
    const detalle = document.getElementById('detalleCostoEnvio');
    const inputCosto = document.getElementById('costoEnvio');

    if (!idDireccion || !idSucursal) return;

    detalle.textContent = 'Calculando...';
    fetch(BASE_URL + `/backend/ventas/calcular_costo_envio.php?id_sucursal=${idSucursal}&id_direccion=${idDireccion}`)
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status + ' — revisa que backend/ventas/calcular_costo_envio.php esté subido');
            return r.json();
        })
        .then(data => {
            if (!data.success) {
                console.error('calcular_costo_envio.php:', data.message);
                detalle.textContent = 'No se pudo calcular (' + (data.message || 'ver consola') + ')';
                return;
            }
            inputCosto.value = data.costo_envio.toFixed(2);
            detalle.textContent = data.detalle;
            detalle.className = data.calculado ? 'text-success' : 'text-muted';
        })
        .catch((err) => {
            console.error('calcular_costo_envio.php:', err);
            detalle.textContent = 'Error al calcular el costo (ver consola del navegador).';
        });
}

function guardarDelivery() {
    const direccionSelect = document.getElementById('direccionEntrega');
    const costoInput = document.getElementById('costoEnvio');
    const observacionesText = document.getElementById('observacionesDelivery');
    const idRepartidor = document.getElementById('repartidorSeleccionadoId').value;
    const idVehiculo = document.getElementById('vehiculoSeleccionadoId').value;

    if (!direccionSelect || !costoInput) return;

    const enCola = window.__enColaSel === true;

    const direccionId = direccionSelect.value;
    const costo = parseFloat(costoInput.value);
    const observaciones = observacionesText ? observacionesText.value : '';

    if (!direccionId) { Swal.fire('Error', 'Seleccione una dirección de entrega', 'error'); return; }
    if (!enCola && (!idRepartidor || !idVehiculo)) {
        Swal.fire('Error', 'Selecciona un repartidor y su vehículo (o ponlo en cola de espera si nadie está disponible)', 'error');
        return;
    }
    if (isNaN(costo) || costo < 0) { Swal.fire('Error', 'Costo de envío inválido', 'error'); return; }

    const selectedOpt = direccionSelect.options[direccionSelect.selectedIndex];

    deliveryAsignado = {
        id_repartidor: enCola ? null : parseInt(idRepartidor),
        id_vehiculo: enCola ? null : parseInt(idVehiculo),
        en_cola: enCola,
        nombre_repartidor: window.__repartidorNombreSel || '',
        vehiculo_texto: window.__vehiculoTextoSel || '',
        costo_entrega: costo,
        direccion_entrega: selectedOpt.dataset.direccion || selectedOpt.dataset.completa || selectedOpt.text,
        barrio_entrega: selectedOpt.dataset.barrio || '',
        ciudad_entrega: selectedOpt.dataset.ciudad || 'Santiago',
        referencia_entrega: selectedOpt.dataset.referencia || '',
        direccion_completa: selectedOpt.dataset.completa || selectedOpt.text,
        observaciones: observaciones
    };
    costoEnvio = costo;
    deliveryActivo = true;

    document.getElementById('deliveryRepartidor').innerText = deliveryAsignado.nombre_repartidor;
    document.getElementById('deliveryVehiculo').innerText = deliveryAsignado.vehiculo_texto;
    document.getElementById('deliveryCosto').innerText = deliveryAsignado.costo_entrega.toFixed(2);
    document.getElementById('deliveryDireccion').innerText = deliveryAsignado.direccion_completa;
    document.getElementById('deliveryState').style.display = 'block';
    document.getElementById('btnEnviarDelivery').style.display = 'none';
    document.getElementById('btnRetiroPersonal').style.display = 'none';
    tipoDespachoSeleccionado = 'DELIVERY';

    if (modalDelivery) modalDelivery.hide();
    recalcularTodo();
    Swal.fire('Éxito', enCola ? 'Pedido puesto en la cola de espera' : 'Delivery asignado correctamente', 'success');
}

// ── NUEVO: toggle tipo de despacho ──
function seleccionarDespacho(tipo) {
    tipoDespachoSeleccionado = tipo;
    const btnRetiro = document.getElementById('btnRetiroPersonal');
    const btnDelivery = document.getElementById('btnEnviarDelivery');

    if (tipo === 'DELIVERY') {
        btnDelivery.classList.add('btn-info');
        btnDelivery.classList.remove('btn-outline-info');
        btnRetiro.classList.remove('btn-success');
        btnRetiro.classList.add('btn-outline-success');
        abrirModalDelivery();
    } else {
        btnRetiro.classList.add('btn-success');
        btnRetiro.classList.remove('btn-outline-success');
        btnDelivery.classList.remove('btn-info');
        btnDelivery.classList.add('btn-outline-info');
        if (deliveryActivo) quitarDelivery();
    }
}

// ── NUEVO: toggle receta ──
function toggleReceta() {
    const checked = document.getElementById('conReceta').checked;
    document.getElementById('divFotoReceta').style.display = checked ? 'block' : 'none';
}

function quitarDelivery() {
    if (!deliveryActivo) return;
    
    Swal.fire({
        title: '¿Quitar delivery?',
        text: 'Se eliminará la asignación actual',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, quitar',
        cancelButtonText: 'No'
    }).then((result) => {
        if (result.isConfirmed) {
            deliveryActivo = false;
            costoEnvio = 0;
            deliveryAsignado = null;
            tipoDespachoSeleccionado = null;

            const deliveryState = document.getElementById('deliveryState');
            const btnRetiro = document.getElementById('btnRetiroPersonal');
            const btnDelivery = document.getElementById('btnEnviarDelivery');
            if (deliveryState) deliveryState.style.display = 'none';
            if (btnRetiro) { btnRetiro.style.display = ''; btnRetiro.classList.remove('btn-success'); btnRetiro.classList.add('btn-outline-success'); }
            if (btnDelivery) { btnDelivery.style.display = ''; btnDelivery.classList.remove('btn-info'); btnDelivery.classList.add('btn-outline-info'); }

            recalcularTodo();
            Swal.fire('Delivery eliminado', '', 'success');
        }
    });
}

// ==================== PROCESAR VENTA ====================
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
    
    procesarVentaConfirmado(sucursal, condicionPago, idCliente, tieneSeguro);
}

function procesarVentaConfirmado(sucursal, condicionPago, idCliente, tieneSeguro) {
    const metodoPago = document.getElementById('metodoPago').value;
    let subtotal = 0;
    for (const item of carrito) subtotal += item.precio * item.cantidad;
    let descuentoMonto = 0;
    if (descuentoSeleccionado.id) {
        descuentoMonto = descuentoSeleccionado.esPorcentaje 
            ? subtotal * (descuentoSeleccionado.valor / 100) 
            : descuentoSeleccionado.valor;
    }
    const subtotalConDescuento = subtotal - descuentoMonto;
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
    let montoSeguro = 0, totalPagar = subtotalConDescuento + nuevoItbis;
    if (tieneSeguro && coberturaActual) {
        montoSeguro = coberturaActual.monto_cubre_seguro || 0;
        totalPagar = (subtotalConDescuento + nuevoItbis) - montoSeguro;
        if (totalPagar < 0) totalPagar = 0;
    }
    if (deliveryActivo && costoEnvio > 0) totalPagar += costoEnvio;
    
    // Validación: si eligió despacho pero no marcó ninguno
    if (!deliveryActivo && tipoDespachoSeleccionado !== 'RETIRO_PERSONAL') {
        Swal.fire('Falta información', 'Selecciona el tipo de despacho: Retiro Personal o Delivery.', 'warning');
        return;
    }

    const datos = {
        numero_documento: document.getElementById('numeroDocumento').value,
        id_usuario: ID_USUARIO_ACTUAL,
        id_cliente: idCliente ? parseInt(idCliente) : null,
        id_sucursal: parseInt(sucursal),
        id_condicion: parseInt(condicionPago) || 1,
        id_metodo_pago: (condicionPago === '1' && metodoPago) ? parseInt(metodoPago) : null,
        productos: carrito.map(item => ({
            tipo: item.tipo,
            id_lote: item.id_lote,
            id_producto: item.id_producto,
            id_talla: item.id_talla,
            id_color: item.id_color,
            cantidad: item.cantidad,
            precio_unitario: item.precio,
            aplica_itbis: item.aplica_itbis === true
        })),
        subtotal: subtotal,
        itbis_total: nuevoItbis,
        total: totalPagar,
        usa_seguro: tieneSeguro,
        id_aseguradora: tieneSeguro ? datosSeguroCliente?.id_aseguradora : null,
        monto_cubre_seguro: montoSeguro,
        monto_paga_paciente: totalPagar,
        monto_descuento: descuentoMonto,
        delivery_activo: deliveryActivo,
        id_repartidor: deliveryAsignado ? deliveryAsignado.id_repartidor : null,
        id_vehiculo: deliveryAsignado ? deliveryAsignado.id_vehiculo : null,
        costo_envio: costoEnvio,
        direccion_entrega: deliveryAsignado ? deliveryAsignado.direccion_completa : null,
        tipo_despacho: deliveryActivo ? 'DELIVERY' : 'RETIRO_PERSONAL',
        con_receta: document.getElementById('conReceta').checked
    };

    Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    fetch(BASE_URL + '/backend/ventas/procesar_venta.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos)
    })
    .then(response => response.json())
    .then(async data => {
        if (!data.success) {
            Swal.close();
            Swal.fire('Error', data.message || 'Error al procesar la venta', 'error');
            return;
        }

        // Si hay foto de receta seleccionada, subirla ahora que ya existe id_venta
        const fotoInput = document.getElementById('fotoReceta');
        if (document.getElementById('conReceta').checked && fotoInput.files.length > 0) {
            const fd = new FormData();
            fd.append('id_venta', data.id_venta);
            fd.append('receta', fotoInput.files[0]);
            try {
                await fetch(BASE_URL + '/backend/ventas/subir_receta.php', { method: 'POST', body: fd });
            } catch (e) {
                console.error('Error subiendo receta:', e);
            }
        }

        Swal.close();
        Swal.fire('Éxito', `Venta ${data.numero_documento} registrada correctamente`, 'success')
            .then(() => location.reload());
    })
    .catch(error => {
        Swal.close();
        Swal.fire('Error', 'Error de conexión: ' + error.message, 'error');
        console.error(error);
    });
}

function cancelarVenta() {
    Swal.fire({
        title: '¿Cancelar venta?',
        text: 'Se perderán los datos ingresados',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No'
    }).then((result) => { 
        if (result.isConfirmed) location.reload(); 
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

function actualizarCondicionCredito() {
    const clienteSelect = document.getElementById('cliente');
    const selectedOption = clienteSelect.options[clienteSelect.selectedIndex];
    const permiteCredito = selectedOption?.dataset?.permiteCredito === '1';
    const condicionSelect = document.getElementById('condicionPago');
    const alertCredito = document.getElementById('alertCredito');
    const divMetodo = document.getElementById('divMetodoPago');
    
    if (!permiteCredito && (condicionSelect.value === '2' || condicionSelect.value === '3')) {
        condicionSelect.value = '1';
        Swal.fire('Aviso', 'Este cliente no tiene crédito habilitado', 'info');
        if (alertCredito) alertCredito.style.display = 'none';
        if (divMetodo) divMetodo.style.display = 'block';
    }
    
    if (condicionSelect.value === '2' || condicionSelect.value === '3') {
        if (alertCredito) alertCredito.style.display = 'block';
        if (divMetodo) divMetodo.style.display = 'none';
    } else {
        if (alertCredito) alertCredito.style.display = 'none';
        if (divMetodo) divMetodo.style.display = 'block';
    }
}

// ==================== INICIALIZACIÓN ====================
document.addEventListener('DOMContentLoaded', function() {
    initTabs();
    
    const elModalProductos = document.getElementById('modalProductos');
    const elModalCantidad = document.getElementById('modalCantidad');
    const elModalDelivery = document.getElementById('modalDelivery');
    
    if (elModalProductos) modalProductos = new bootstrap.Modal(elModalProductos, { backdrop: false, keyboard: true });
    if (elModalCantidad) modalCantidad = new bootstrap.Modal(elModalCantidad, { backdrop: false, keyboard: true });
    if (elModalDelivery) modalDelivery = new bootstrap.Modal(elModalDelivery, { backdrop: false, keyboard: true });
    
    const btnAgregar = document.getElementById('btnAgregarProducto');
    if (btnAgregar) btnAgregar.addEventListener('click', abrirModalProductos);
    
    const sucursalSelect = document.getElementById('sucursal');
    if (sucursalSelect) {
        sucursalSelect.addEventListener('change', function() {
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
    }
    
    const clienteSelect = document.getElementById('cliente');
    if (clienteSelect) {
        clienteSelect.addEventListener('change', function() {
            const tieneSeguro = this.options[this.selectedIndex]?.dataset?.tieneSeguro === '1';
            if (!tieneSeguro || !this.value) {
                document.getElementById('cardSeguro').style.display = 'none';
                document.getElementById('seguroInfo').style.display = 'none';
                datosSeguroCliente = null;
            }
            recalcularTodo();
            actualizarCondicionCredito();
            if (deliveryActivo) quitarDelivery();
        });
    }
    
    const condicionSelect = document.getElementById('condicionPago');
    if (condicionSelect) condicionSelect.addEventListener('change', actualizarCondicionCredito);
    
    const filtroInput = document.getElementById('filtroNombreProducto');
    if (filtroInput) filtroInput.addEventListener('input', filtrarProductos);
    
    const descuentoSelect = document.getElementById('selectDescuento');
    if (descuentoSelect) descuentoSelect.addEventListener('change', () => recalcularTodo());
    
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
    
    sucursalActual = document.getElementById('sucursal')?.value || null;
    actualizarCondicionCredito();
});
</script>