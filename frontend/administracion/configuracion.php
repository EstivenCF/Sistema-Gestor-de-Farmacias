<?php
// Verificar acceso
if (!isset($rol_usuario) || $rol_usuario !== 'Administrador') {
    echo "<div class='alert alert-danger m-4'>
            <span class='material-symbols-rounded me-2'>warning</span>
            <strong>Acceso denegado:</strong> Solo administradores pueden acceder a esta sección.
          </div>";
    exit;
}

$base_url = '/sistema-gestor-de-farmacias';

// Procesar guardado de configuración
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_config') {
    try {
        foreach ($_POST as $clave => $valor) {
            if ($clave !== 'action' && $clave !== 'save_config') {
                // Limpiar valor
                $valor = trim($valor);
                
                // Verificar si la configuración existe
                $check = $conexion->prepare("SELECT id_config FROM configuracion_sistema WHERE clave = ?");
                $check->execute([$clave]);
                
                if ($check->fetch()) {
                    $stmt = $conexion->prepare("UPDATE configuracion_sistema SET valor = ? WHERE clave = ?");
                    $stmt->execute([$valor, $clave]);
                } else {
                    $stmt = $conexion->prepare("INSERT INTO configuracion_sistema (clave, valor) VALUES (?, ?)");
                    $stmt->execute([$clave, $valor]);
                }
            }
        }
        $mensaje = "Configuración guardada correctamente";
        $tipo_mensaje = "success";
    } catch (Exception $e) {
        $mensaje = "Error al guardar: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// Cargar configuraciones actuales
$configuraciones = [];
$stmt = $conexion->query("SELECT clave, valor, descripcion FROM configuracion_sistema");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $configuraciones[$row['clave']] = $row;
}

function getConfig($clave, $default = '') {
    global $configuraciones;
    return isset($configuraciones[$clave]) ? $configuraciones[$clave]['valor'] : $default;
}

// NUEVO (mejora final del proceso estratégico de vencimientos): se reutilizan
// las mismas funciones que ya usa el proceso (riesgo_vencimiento_lib.php /
// redistribucion_inteligente_lib.php) para precargar los intervalos y pesos
// actuales en la pestaña "Vencimientos" de esta misma pantalla, en vez de
// duplicar los valores por defecto aquí.
require_once __DIR__ . '/../../backend/inventario/riesgo_vencimiento_lib.php';
require_once __DIR__ . '/../../backend/inventario/redistribucion_inteligente_lib.php';
$venc_intervalos_actuales = obtenerIntervalosAccion($conexion);
$venc_pesos_actuales = obtenerPesosTransferencia($conexion);
$venc_pesos_por_sucursal = obtenerMapaCriteriosPorSucursal($conexion);
$venc_sucursales_criterios = [];
try {
    $venc_sucursales_criterios = $conexion->query("SELECT id_sucursal, nombre FROM sucursales WHERE estado = true ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $venc_sucursales_criterios = [];
}
$venc_preferencias_default = preferenciasCriterioPorDefecto();
?>

<!-- ESTILOS ESPECÍFICOS DEL MÓDULO (sin afectar el sidebar) -->
<style>
    /* Reset de estilos que puedan afectar al sidebar - usando especificidad */
    /*.config-module-wrapper * {
        /* No sobrescribir estilos del sidebar 
    }*/
    
    .config-module-wrapper {
        padding: 0;
        margin: 0;
    }
    
    /* Header de configuración */
    .config-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 15px;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
        color: white;
    }
    
    .config-header h2 {
        margin: 0;
        font-size: 1.8rem;
        font-weight: 600;
    }
    
    .config-header p {
        margin: 0.5rem 0 0 0;
        opacity: 0.9;
    }
    
    /* Pestañas */
    .config-tabs {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 0.5rem;
    }
    
    .config-tab {
        padding: 0.75rem 1.5rem;
        background: transparent;
        border: none;
        border-radius: 10px;
        font-weight: 500;
        color: #6c757d;
        transition: all 0.3s;
        cursor: pointer;
    }
    
    .config-tab:hover {
        background: #f8f9fa;
        color: #0d6efd;
    }
    
    .config-tab.active {
        background: #0d6efd;
        color: white;
    }
    
    /* Tarjetas de configuración */
    .config-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        margin-bottom: 1.5rem;
        overflow: hidden;
        transition: transform 0.3s, box-shadow 0.3s;
    }
    
    .config-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    }
    
    .config-card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #dee2e6;
    }
    
    .config-card-header h5 {
        margin: 0;
        font-weight: 600;
        color: #1a1e2b;
    }
    
    .config-card-header h5 i {
        margin-right: 0.5rem;
        color: #0d6efd;
    }
    
    .config-card-body {
        padding: 1.5rem;
    }
    
    .config-group {
        margin-bottom: 1.5rem;
    }
    
    .config-group label {
        font-weight: 500;
        margin-bottom: 0.5rem;
        color: #495057;
        display: block;
    }
    
    .config-group .form-text {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 0.25rem;
        display: block;
    }
    
    /* Botones */
    .btn-save {
        background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
        border: none;
        padding: 0.75rem 2rem;
        font-weight: 500;
        border-radius: 10px;
        transition: transform 0.3s;
    }
    
    .btn-save:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
    }
    
    /* Switch personalizado */
    .switch-custom {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }
    
    .switch-custom input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }
    
    .slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    
    input:checked + .slider {
        background-color: #0d6efd;
    }
    
    input:checked + .slider:before {
        transform: translateX(26px);
    }
    
    /* Alertas */
    .alert-custom {
        border-radius: 12px;
        border: none;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    /* Tab content */
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .config-header {
            padding: 1rem;
        }
        
        .config-tab {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .config-card-body {
            padding: 1rem;
        }
    }
    
    /* Asegurar que los inputs no rompan el layout */
    .config-group .form-control,
    .config-group .form-select {
        width: 100%;
        padding: 0.5rem 0.75rem;
        font-size: 1rem;
        line-height: 1.5;
        color: #212529;
        background-color: #fff;
        border: 1px solid #ced4da;
        border-radius: 0.5rem;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    .config-group .form-control:focus,
    .config-group .form-select:focus {
        border-color: #86b7fe;
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    
    .form-check-input {
        width: 1em;
        height: 1em;
        margin-top: 0.25em;
        vertical-align: top;
        background-color: #fff;
        background-repeat: no-repeat;
        background-position: center;
        background-size: contain;
        border: 1px solid rgba(0, 0, 0, 0.25);
        appearance: none;
        border-radius: 0.25em;
    }
    
    .form-check-input:checked {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }
    
    .form-check-input:focus {
        border-color: #86b7fe;
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    
    .form-switch .form-check-input {
        width: 2em;
        margin-left: -2.5em;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='rgba(0,0,0,0.25)'/%3e%3c/svg%3e");
        background-position: left center;
        border-radius: 2em;
        transition: background-position 0.15s ease-in-out;
    }
    
    .form-switch .form-check-input:checked {
        background-position: right center;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='%23fff'/%3e%3c/svg%3e");
    }
    
    .form-check {
        display: block;
        min-height: 1.5rem;
        padding-left: 1.5em;
        margin-bottom: 0.125rem;
    }
    
    .form-check .form-check-input {
        float: left;
        margin-left: -1.5em;
    }
    
    .row {
        display: flex;
        flex-wrap: wrap;
        margin-right: -0.75rem;
        margin-left: -0.75rem;
    }
    
    .col-md-6 {
        flex: 0 0 auto;
        width: 50%;
        padding-right: 0.75rem;
        padding-left: 0.75rem;
    }
    
    @media (max-width: 768px) {
        .col-md-6 {
            width: 100%;
        }
    }
    
    .text-center {
        text-align: center;
    }
    
    .mt-4 {
        margin-top: 1.5rem;
    }
    
    .me-2 {
        margin-right: 0.5rem;
    }
    
    .me-1 {
        margin-right: 0.25rem;
    }
    
    .px-3 {
        padding-right: 1rem;
        padding-left: 1rem;
    }
    
    .py-2 {
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }
    
    .bg-light {
        background-color: #f8f9fa;
    }
    
    .text-dark {
        color: #212529;
    }
    
    .badge {
        display: inline-block;
        padding: 0.35em 0.65em;
        font-size: 0.75em;
        font-weight: 700;
        line-height: 1;
        color: #fff;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 0.375rem;
    }
</style>

<!-- CONTENEDOR PRINCIPAL - SIN container-fluid para no afectar el layout -->
<div class="config-module-wrapper p-3">
    
    <!-- Header -->
    <div class="config-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2>
                    <span class="material-symbols-rounded" style="font-size: 2rem; vertical-align: middle;">settings</span>
                    Configuración del Sistema
                </h2>
                <p>Personaliza el comportamiento de PharmaSystem según las necesidades de tu farmacia</p>
            </div>
            <div>
                <span class="badge bg-light text-dark px-3 py-2">
                    <i class="fas fa-shield-alt me-1"></i> Solo Administradores
                </span>
            </div>
        </div>
    </div>
    
    <!-- Mensajes -->
    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-custom alert-dismissible fade show" role="alert">
            <i class="fas <?php echo $tipo_mensaje === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars($mensaje); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Pestañas -->
    <div class="config-tabs">
        <button class="config-tab active" data-tab="general">
            <i class="fas fa-building me-2"></i>General
        </button>
        <button class="config-tab" data-tab="invoice">
            <i class="fas fa-receipt me-2"></i>Facturación
        </button>
        <button class="config-tab" data-tab="inventory">
            <i class="fas fa-boxes me-2"></i>Inventario
        </button>
        <button class="config-tab" data-tab="vencimientos">
            <i class="fas fa-hourglass-half me-2"></i>Vencimientos
        </button>
        <button class="config-tab" data-tab="taxes">
            <i class="fas fa-percent me-2"></i>Impuestos
        </button>
        <button class="config-tab" data-tab="security">
            <i class="fas fa-shield-alt me-2"></i>Seguridad
        </button>
        <button class="config-tab" data-tab="notifications">
            <i class="fas fa-bell me-2"></i>Notificaciones
        </button>
        <button class="config-tab" data-tab="delivery">
            <i class="fas fa-truck me-2"></i>Envíos
        </button>
        <button class="config-tab" data-tab="appearance">
            <i class="fas fa-palette me-2"></i>Apariencia
        </button>
    </div>
    
    <!-- Formulario de configuración -->
    <form method="POST" action="" id="configForm">
        <input type="hidden" name="action" value="save_config">
        
        <!-- Pestaña: General -->
        <div id="tab-general" class="tab-content active">
            <div class="row">
                <div class="col-md-6">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-building"></i> Datos de la Empresa</h5>
                        </div>
                        <div class="config-card-body">
                            <div class="config-group">
                                <label>Nombre de la Empresa</label>
                                <input type="text" name="empresa_nombre" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('empresa_nombre', 'Farmacia Salud+')); ?>">
                                <small class="form-text">Nombre que aparecerá en facturas y reportes</small>
                            </div>
                            <div class="config-group">
                                <label>RNC / NIT</label>
                                <input type="text" name="empresa_rnc" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('empresa_rnc', '101-3456729-4')); ?>">
                            </div>
                            <div class="config-group">
                                <label>Teléfono</label>
                                <input type="text" name="empresa_telefono" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('empresa_telefono', '')); ?>">
                            </div>
                            <div class="config-group">
                                <label>Email</label>
                                <input type="email" name="empresa_email" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('empresa_email', '')); ?>">
                            </div>
                            <div class="config-group">
                                <label>Dirección</label>
                                <textarea name="empresa_direccion" class="form-control" rows="2"><?php echo htmlspecialchars(getConfig('empresa_direccion', '')); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-globe"></i> Configuración Regional</h5>
                        </div>
                        <div class="config-card-body">
                            <div class="config-group">
                                <label>Moneda</label>
                                <select name="moneda" class="form-select">
                                    <option value="DOP" <?php echo getConfig('moneda', 'DOP') == 'DOP' ? 'selected' : ''; ?>>Peso Dominicano (RD$)</option>
                                    <option value="USD" <?php echo getConfig('moneda', 'DOP') == 'USD' ? 'selected' : ''; ?>>Dólar Americano (US$)</option>
                                    <option value="EUR" <?php echo getConfig('moneda', 'DOP') == 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                                </select>
                            </div>
                            <div class="config-group">
                                <label>Formato de Fecha</label>
                                <select name="formato_fecha" class="form-select">
                                    <option value="d/m/Y" <?php echo getConfig('formato_fecha', 'd/m/Y') == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/AAAA</option>
                                    <option value="m/d/Y" <?php echo getConfig('formato_fecha', 'd/m/Y') == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/AAAA</option>
                                    <option value="Y-m-d" <?php echo getConfig('formato_fecha', 'd/m/Y') == 'Y-m-d' ? 'selected' : ''; ?>>AAAA-MM-DD</option>
                                </select>
                            </div>
                            <div class="config-group">
                                <label>Zona Horaria</label>
                                <select name="zona_horaria" class="form-select">
                                    <option value="America/Santo_Domingo" <?php echo getConfig('zona_horaria', 'America/Santo_Domingo') == 'America/Santo_Domingo' ? 'selected' : ''; ?>>Santo Domingo</option>
                                    <option value="America/New_York" <?php echo getConfig('zona_horaria', 'America/Santo_Domingo') == 'America/New_York' ? 'selected' : ''; ?>>Nueva York</option>
                                    <option value="America/Mexico_City" <?php echo getConfig('zona_horaria', 'America/Santo_Domingo') == 'America/Mexico_City' ? 'selected' : ''; ?>>Ciudad de México</option>
                                </select>
                            </div>
                            <div class="config-group">
                                <label>Decimales en Precios</label>
                                <select name="decimales_precios" class="form-select">
                                    <option value="0" <?php echo getConfig('decimales_precios', '2') == '0' ? 'selected' : ''; ?>>0 decimales</option>
                                    <option value="2" <?php echo getConfig('decimales_precios', '2') == '2' ? 'selected' : ''; ?>>2 decimales</option>
                                    <option value="4" <?php echo getConfig('decimales_precios', '2') == '4' ? 'selected' : ''; ?>>4 decimales</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pestaña: Facturación -->
        <div id="tab-invoice" class="tab-content">
            <div class="row">
                <div class="col-md-6">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-file-invoice"></i> Configuración de Facturación</h5>
                        </div>
                        <div class="config-card-body">
                            <div class="config-group">
                                <label>Prefijo de Factura</label>
                                <input type="text" name="factura_prefijo" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('factura_prefijo', 'FAC-')); ?>">
                                <small class="form-text">Ejemplo: FAC-, TICKET-, INV-</small>
                            </div>
                            <div class="config-group">
                                <label>Número de Copias a Imprimir</label>
                                <input type="number" name="copias_impresion" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('copias_impresion', '1')); ?>" min="1" max="5">
                            </div>
                            <div class="config-group">
                                <label>Tipo de Comprobante por Defecto</label>
                                <select name="tipo_comprobante" class="form-select">
                                    <option value="FACTURA" <?php echo getConfig('tipo_comprobante', 'FACTURA') == 'FACTURA' ? 'selected' : ''; ?>>Factura Fiscal</option>
                                    <option value="TICKET" <?php echo getConfig('tipo_comprobante', 'FACTURA') == 'TICKET' ? 'selected' : ''; ?>>Ticket</option>
                                    <option value="BOLETA" <?php echo getConfig('tipo_comprobante', 'FACTURA') == 'BOLETA' ? 'selected' : ''; ?>>Boleta</option>
                                </select>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="impresion_automatica" value="0">
                                    <input class="form-check-input" type="checkbox" name="impresion_automatica" value="1" 
                                           id="impresion_automatica" <?php echo getConfig('impresion_automatica', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="impresion_automatica">Imprimir automáticamente al guardar venta</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <label>Pie de Página Personalizado</label>
                                <textarea name="pie_factura" class="form-control" rows="3"><?php echo htmlspecialchars(getConfig('pie_factura', '¡Gracias por su compra!')); ?></textarea>
                                <small class="form-text">Texto que aparecerá al final de cada factura</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-print"></i> Configuración de Impresión</h5>
                        </div>
                        <div class="config-card-body">
                            <div class="config-group">
                                <label>Tamaño de Papel</label>
                                <select name="tamano_papel" class="form-select">
                                    <option value="80mm" <?php echo getConfig('tamano_papel', '80mm') == '80mm' ? 'selected' : ''; ?>>80mm (Ticket estándar)</option>
                                    <option value="58mm" <?php echo getConfig('tamano_papel', '80mm') == '58mm' ? 'selected' : ''; ?>>58mm (Ticket pequeño)</option>
                                    <option value="A4" <?php echo getConfig('tamano_papel', '80mm') == 'A4' ? 'selected' : ''; ?>>A4 (Carta)</option>
                                </select>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="mostrar_logo_factura" value="0">
                                    <input class="form-check-input" type="checkbox" name="mostrar_logo_factura" value="1" 
                                           id="mostrar_logo_factura" <?php echo getConfig('mostrar_logo_factura', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="mostrar_logo_factura">Mostrar logo en facturas</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="mostrar_itbis_detallado" value="0">
                                    <input class="form-check-input" type="checkbox" name="mostrar_itbis_detallado" value="1" 
                                           id="mostrar_itbis_detallado" <?php echo getConfig('mostrar_itbis_detallado', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="mostrar_itbis_detallado">Mostrar ITBIS desglosado</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pestaña: Inventario -->
        <div id="tab-inventory" class="tab-content">
            <div class="row">
                <div class="col-md-6">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-box"></i> Configuración de Inventario</h5>
                        </div>
                        <div class="config-card-body">
                            <div class="config-group">
                                <label>Stock Mínimo Global</label>
                                <input type="number" name="stock_minimo_global" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('stock_minimo_global', '10')); ?>" min="0">
                                <small class="form-text">Cantidad mínima por defecto para todos los productos</small>
                            </div>
                            <div class="config-group">
                                <label>Días de Anticipación para Alerta de Vencimiento</label>
                                <input type="number" name="dias_alerta_vencimiento" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('dias_alerta_vencimiento', '30')); ?>" min="1" max="365">
                                <small class="form-text">Alertar cuando falten esta cantidad de días para vencer</small>
                            </div>
                            <div class="config-group">
                                <label>Punto de Reorden (%)</label>
                                <input type="number" name="punto_reorden_porcentaje" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('punto_reorden_porcentaje', '20')); ?>" min="0" max="100">
                                <small class="form-text">Porcentaje del stock máximo para generar alerta de reorden</small>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="permitir_stock_negativo" value="0">
                                    <input class="form-check-input" type="checkbox" name="permitir_stock_negativo" value="1" 
                                           id="permitir_stock_negativo" <?php echo getConfig('permitir_stock_negativo', '0') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="permitir_stock_negativo">Permitir stock negativo</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="control_lotes_obligatorio" value="0">
                                    <input class="form-check-input" type="checkbox" name="control_lotes_obligatorio" value="1" 
                                           id="control_lotes_obligatorio" <?php echo getConfig('control_lotes_obligatorio', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="control_lotes_obligatorio">Control de lotes obligatorio</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-chart-line"></i> Métodos de Valoración</h5>
                        </div>
                        <div class="config-card-body">
                            <div class="config-group">
                                <label>Método de Valoración de Inventario</label>
                                <select name="metodo_valoracion" class="form-select">
                                    <option value="PROMEDIO" <?php echo getConfig('metodo_valoracion', 'PROMEDIO') == 'PROMEDIO' ? 'selected' : ''; ?>>Precio Promedio Ponderado</option>
                                    <option value="FIFO" <?php echo getConfig('metodo_valoracion', 'PROMEDIO') == 'FIFO' ? 'selected' : ''; ?>>FIFO (Primero en entrar, primero en salir)</option>
                                    <option value="LIFO" <?php echo getConfig('metodo_valoracion', 'PROMEDIO') == 'LIFO' ? 'selected' : ''; ?>>LIFO (Último en entrar, primero en salir)</option>
                                </select>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="calcular_rentabilidad_automatico" value="0">
                                    <input class="form-check-input" type="checkbox" name="calcular_rentabilidad_automatico" value="1" 
                                           id="calcular_rentabilidad_automatico" <?php echo getConfig('calcular_rentabilidad_automatico', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="calcular_rentabilidad_automatico">Calcular rentabilidad automáticamente</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pestaña: Vencimientos (proceso estratégico de Gestión de Vencimientos) -->
        <div id="tab-vencimientos" class="tab-content">
            <div class="row">
                <div class="col-md-6">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-hourglass-half"></i> Umbrales de riesgo y rotación</h5>
                        </div>
                        <div class="config-card-body">
                            <div class="config-group">
                                <label>Días para riesgo CRÍTICO</label>
                                <input type="number" name="venc_umbral_dias_critico" class="form-control"
                                       value="<?php echo htmlspecialchars(getConfig('venc_umbral_dias_critico', '15')); ?>" min="1">
                                <small class="form-text">Un lote con menos de estos días para vencer se clasifica como riesgo crítico.</small>
                            </div>
                            <div class="config-group">
                                <label>Días para riesgo MODERADO</label>
                                <input type="number" name="venc_umbral_dias_moderado" class="form-control"
                                       value="<?php echo htmlspecialchars(getConfig('venc_umbral_dias_moderado', '30')); ?>" min="1">
                                <small class="form-text">Debe ser mayor al umbral crítico. Entre este valor y el crítico, el lote es riesgo moderado.</small>
                            </div>
                            <div class="config-group">
                                <label>% de venta (IRV) mínimo aceptable</label>
                                <input type="number" name="venc_irv_umbral_minimo" class="form-control"
                                       value="<?php echo htmlspecialchars(getConfig('venc_irv_umbral_minimo', '15')); ?>" min="0" max="100" step="0.01">
                                <small class="form-text">Por debajo de este % se considera "baja rotación" en las pantallas de monitoreo.</small>
                            </div>
                            <div class="config-group">
                                <label>Período para calcular el % de venta (días)</label>
                                <input type="number" name="venc_irv_periodo_dias" class="form-control"
                                       value="<?php echo htmlspecialchars(getConfig('venc_irv_periodo_dias', '30')); ?>" min="1">
                                <small class="form-text">Ventana de ventas usada para calcular el IRV (% de venta) de cada medicamento por sucursal.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-route"></i> Redistribución entre sucursales</h5>
                        </div>
                        <div class="config-card-body">
                            <div class="config-group">
                                <label>Costo de transporte estimado (RD$ por unidad, por km)</label>
                                <input type="number" name="venc_costo_transporte_estimado_unidad" class="form-control"
                                       value="<?php echo htmlspecialchars(getConfig('venc_costo_transporte_estimado_unidad', '5')); ?>" min="0" step="0.01">
                                <small class="form-text">Se multiplica por la distancia real entre sucursales y la cantidad a transferir para estimar el flete.</small>
                            </div>
                            <div class="config-group">
                                <label>Velocidad promedio de transporte (km/h)</label>
                                <input type="number" name="venc_velocidad_promedio_kmh" class="form-control"
                                       value="<?php echo htmlspecialchars(getConfig('venc_velocidad_promedio_kmh', '35')); ?>" min="1">
                                <small class="form-text">Usada para estimar el tiempo de tránsito entre sucursales a partir de la distancia.</small>
                            </div>
                            <div class="config-group">
                                <label>Puntuación mínima de conveniencia para recomendar transferencia (%)</label>
                                <input type="number" name="venc_umbral_conveniencia_minima" class="form-control"
                                       value="<?php echo htmlspecialchars(getConfig('venc_umbral_conveniencia_minima', '50')); ?>" min="0" max="100">
                                <small class="form-text">Si ninguna sucursal alcanza esta puntuación ponderada, el sistema recomienda otra acción (promoción o devolución) en su lugar.</small>
                            </div>
                            <div class="config-group">
                                <label>Tope de costo de transporte vs. valor en riesgo (%)</label>
                                <input type="number" name="venc_max_costo_transporte_pct_valor" class="form-control"
                                       value="<?php echo htmlspecialchars(getConfig('venc_max_costo_transporte_pct_valor', '30')); ?>" min="0" max="100">
                                <small class="form-text">Si el flete estimado supera este % del valor en riesgo del lote, la transferencia se descarta por no ser económicamente viable.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-percentage"></i> Intervalos de acción por % de venta</h5>
                        </div>
                        <div class="config-card-body">
                            <p class="text-muted small mb-3">
                                Define qué acción se sugiere automáticamente según el % de venta (IRV) del lote.
                                Los intervalos deben cubrir de 0% a 100% sin huecos ni traslapes.
                            </p>
                            <div id="tablaIntervalos"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="agregarFilaIntervalo()">
                                <i class="fas fa-plus me-1"></i>Agregar intervalo
                            </button>
                            <div id="avisoIntervalos" class="small mt-2"></div>
                            <input type="hidden" name="venc_intervalos_accion" id="inputIntervalosAccion">
                        </div>
                    </div>
                </div>

                <div class="col-md-5">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-balance-scale"></i> Pesos por defecto de la redistribución</h5>
                        </div>
                        <div class="config-card-body">
                            <p class="text-muted small mb-3">
                                Pesos por defecto. Se usan solo si la sucursal destino todavía no tiene un perfil propio.
                                Cada sucursal puede ponderar distinto más abajo (IRV, inventario, distancia, etc.).
                            </p>
                            <div id="listaPesos"></div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <strong>Suma actual:</strong>
                                <strong id="sumaPesos">0%</strong>
                            </div>
                            <div id="avisoPesos" class="small mt-2"></div>
                            <input type="hidden" name="venc_pesos_transferencia" id="inputPesosTransferencia">
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-12">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-store"></i> Ponderación de criterios por sucursal</h5>
                        </div>
                        <div class="config-card-body">
                            <p class="text-muted small mb-3">
                                No todas las sucursales valoran igual. Ejemplo: en Principal el IRV puede pesar más;
                                en Hatuey puede importar más el inventario y que el IRV no sea alto.
                                El peso es cuánto cuenta el criterio; “prefiere más/menos” indica si un valor alto suma o resta en esa sucursal.
                            </p>
                            <?php if (empty($venc_sucursales_criterios)): ?>
                                <div class="alert alert-warning mb-0">No hay sucursales activas para configurar perfiles.</div>
                            <?php else: ?>
                                <div class="row g-2 align-items-end mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold mb-1">Sucursal</label>
                                        <select id="selectSucursalCriterios" class="form-select" onchange="cambiarSucursalCriterios(this.value)">
                                            <?php foreach ($venc_sucursales_criterios as $suc): ?>
                                                <option value="<?php echo (int)$suc['id_sucursal']; ?>">
                                                    <?php echo htmlspecialchars($suc['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copiarPesosDefaultASucursal()">
                                            Copiar pesos por defecto a esta sucursal
                                        </button>
                                    </div>
                                </div>
                                <div id="listaPesosSucursal"></div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <strong>Suma de esta sucursal:</strong>
                                    <strong id="sumaPesosSucursal">0%</strong>
                                </div>
                                <div id="avisoPesosSucursal" class="small mt-2"></div>
                            <?php endif; ?>
                            <input type="hidden" name="venc_pesos_por_sucursal" id="inputPesosPorSucursal">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pestaña: Impuestos -->
        <div id="tab-taxes" class="tab-content">
            <div class="row">
                <div class="col-md-6">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-percent"></i> Configuración de ITBIS</h5>
                        </div>
                        <div class="config-card-body">
                            <div class="config-group">
                                <label>Porcentaje de ITBIS</label>
                                <input type="number" name="itbis_porcentaje" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('itbis_porcentaje', '18')); ?>" step="0.01" min="0" max="100">
                                <small class="form-text">Porcentaje actual del ITBIS</small>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="itbis_medicamentos_sin_receta" value="0">
                                    <input class="form-check-input" type="checkbox" name="itbis_medicamentos_sin_receta" value="1" 
                                           id="itbis_medicamentos_sin_receta" <?php echo getConfig('itbis_medicamentos_sin_receta', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="itbis_medicamentos_sin_receta">Aplicar ITBIS a medicamentos sin receta</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="itbis_medicamentos_con_receta" value="0">
                                    <input class="form-check-input" type="checkbox" name="itbis_medicamentos_con_receta" value="1" 
                                           id="itbis_medicamentos_con_receta" <?php echo getConfig('itbis_medicamentos_con_receta', '0') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="itbis_medicamentos_con_receta">Aplicar ITBIS a medicamentos con receta</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="itbis_ropa" value="0">
                                    <input class="form-check-input" type="checkbox" name="itbis_ropa" value="1" 
                                           id="itbis_ropa" <?php echo getConfig('itbis_ropa', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="itbis_ropa">Aplicar ITBIS a productos de ropa</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="redondear_itbis" value="0">
                                    <input class="form-check-input" type="checkbox" name="redondear_itbis" value="1" 
                                           id="redondear_itbis" <?php echo getConfig('redondear_itbis', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="redondear_itbis">Redondear ITBIS a 2 decimales</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pestaña: Seguridad -->
        <div id="tab-security" class="tab-content">
            <div class="row">
                <div class="col-md-6">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-lock"></i> Configuración de Seguridad</h5>
                        </div>
                        <div class="config-card-body">
                            <div class="config-group">
                                <label>Intentos Máximos de Login</label>
                                <input type="number" name="max_intentos_login" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('max_intentos_login', '5')); ?>" min="1" max="20">
                                <small class="form-text">Número de intentos fallidos antes de bloquear usuario</small>
                            </div>
                            <div class="config-group">
                                <label>Tiempo de Expiración de Sesión (minutos)</label>
                                <input type="number" name="sesion_expiracion_minutos" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('sesion_expiracion_minutos', '60')); ?>" min="5" max="480">
                                <small class="form-text">Tiempo de inactividad antes de cerrar sesión</small>
                            </div>
                            <div class="config-group">
                                <label>Forzar Cambio de Contraseña (días)</label>
                                <input type="number" name="forzar_cambio_pass_dias" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('forzar_cambio_pass_dias', '90')); ?>" min="0" max="365">
                                <small class="form-text">0 = desactivado</small>
                            </div>
                            <div class="config-group">
                                <label>Longitud Mínima de Contraseña</label>
                                <input type="number" name="pass_longitud_minima" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('pass_longitud_minima', '8')); ?>" min="4" max="20">
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="requerir_mayusculas" value="0">
                                    <input class="form-check-input" type="checkbox" name="requerir_mayusculas" value="1" 
                                           id="requerir_mayusculas" <?php echo getConfig('requerir_mayusculas', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="requerir_mayusculas">Requerir mayúsculas en contraseña</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="requerir_numeros" value="0">
                                    <input class="form-check-input" type="checkbox" name="requerir_numeros" value="1" 
                                           id="requerir_numeros" <?php echo getConfig('requerir_numeros', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="requerir_numeros">Requerir números en contraseña</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="auditar_accesos" value="0">
                                    <input class="form-check-input" type="checkbox" name="auditar_accesos" value="1" 
                                           id="auditar_accesos" <?php echo getConfig('auditar_accesos', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="auditar_accesos">Auditar accesos al sistema</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-database"></i> Backups y Recuperación</h5>
                        </div>
                        <div class="config-card-body">
                            <div class="config-group">
                                <label>Frecuencia de Backup Automático</label>
                                <select name="backup_frecuencia" class="form-select">
                                    <option value="DIARIO" <?php echo getConfig('backup_frecuencia', 'SEMANAL') == 'DIARIO' ? 'selected' : ''; ?>>Diario</option>
                                    <option value="SEMANAL" <?php echo getConfig('backup_frecuencia', 'SEMANAL') == 'SEMANAL' ? 'selected' : ''; ?>>Semanal</option>
                                    <option value="MENSUAL" <?php echo getConfig('backup_frecuencia', 'SEMANAL') == 'MENSUAL' ? 'selected' : ''; ?>>Mensual</option>
                                    <option value="NUNCA" <?php echo getConfig('backup_frecuencia', 'SEMANAL') == 'NUNCA' ? 'selected' : ''; ?>>Nunca (Manual)</option>
                                </select>
                            </div>
                            <div class="config-group">
                                <label>Número de Backups a Conservar</label>
                                <input type="number" name="backup_conservar" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('backup_conservar', '10')); ?>" min="1" max="50">
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="backup_antes_actualizar" value="0">
                                    <input class="form-check-input" type="checkbox" name="backup_antes_actualizar" value="1" 
                                           id="backup_antes_actualizar" <?php echo getConfig('backup_antes_actualizar', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="backup_antes_actualizar">Hacer backup antes de actualizaciones</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pestaña: Notificaciones -->
        <div id="tab-notifications" class="tab-content">
            <div class="row">
                <div class="col-md-6">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-envelope"></i> Configuración de Notificaciones</h5>
                        </div>
                        <div class="config-card-body">
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="notificaciones_activas" value="0">
                                    <input class="form-check-input" type="checkbox" name="notificaciones_activas" value="1" 
                                           id="notificaciones_activas" <?php echo getConfig('notificaciones_activas', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notificaciones_activas">Activar notificaciones del sistema</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="notificar_stock_critico" value="0">
                                    <input class="form-check-input" type="checkbox" name="notificar_stock_critico" value="1" 
                                           id="notificar_stock_critico" <?php echo getConfig('notificar_stock_critico', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notificar_stock_critico">Notificar stock crítico</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="notificar_vencimientos" value="0">
                                    <input class="form-check-input" type="checkbox" name="notificar_vencimientos" value="1" 
                                           id="notificar_vencimientos" <?php echo getConfig('notificar_vencimientos', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notificar_vencimientos">Notificar vencimientos próximos</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <label>Email para Alertas Críticas</label>
                                <input type="email" name="email_alertas_criticas" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('email_alertas_criticas', '')); ?>">
                                <small class="form-text">Email donde recibir alertas importantes</small>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="sonido_notificaciones" value="0">
                                    <input class="form-check-input" type="checkbox" name="sonido_notificaciones" value="1" 
                                           id="sonido_notificaciones" <?php echo getConfig('sonido_notificaciones', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="sonido_notificaciones">Reproducir sonido en notificaciones</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pestaña: Envíos -->
        <div id="tab-delivery" class="tab-content">
            <div class="row">
                <div class="col-md-6">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-truck"></i> Configuración de Envíos</h5>
                        </div>
                        <div class="config-card-body">
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="delivery_activo" value="0">
                                    <input class="form-check-input" type="checkbox" name="delivery_activo" value="1" 
                                           id="delivery_activo" <?php echo getConfig('delivery_activo', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="delivery_activo">Activar servicio de envíos</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <label>Costo por Kilómetro (RD$/km)</label>
                                <input type="number" name="costo_por_km" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('costo_por_km', '15')); ?>" step="1" min="0">
                                <small class="form-text">El sistema calcula la distancia real entre la sucursal y la dirección de entrega, y la multiplica por este valor.</small>
                            </div>
                            <div class="config-group">
                                <label>Costo de Envío por Defecto (respaldo)</label>
                                <input type="number" name="costo_envio_default" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('costo_envio_default', '100')); ?>" step="10" min="0">
                                <small class="form-text">Se usa solo cuando falta la coordenada de la sucursal o de la dirección de entrega, y no se puede calcular por distancia.</small>
                            </div>
                            <div class="config-group">
                                <label>Radio de Cobertura (km)</label>
                                <input type="number" name="radio_cobertura_km" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('radio_cobertura_km', '10')); ?>" step="1" min="1">
                            </div>
                            <div class="config-group">
                                <label>Tiempo Estimado de Entrega (minutos)</label>
                                <input type="number" name="tiempo_entrega_estimado" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('tiempo_entrega_estimado', '45')); ?>" min="5">
                            </div>
                            <div class="config-group">
                                <label>Actualización de Tracking (segundos)</label>
                                <input type="number" name="tracking_intervalo_segundos" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('tracking_intervalo_segundos', '30')); ?>" min="10" max="300">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-clock"></i> Horarios de Envíos</h5>
                        </div>
                        <div class="config-card-body">
                            <div class="config-group">
                                <label>Hora de Inicio de Entregas</label>
                                <input type="time" name="delivery_hora_inicio" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('delivery_hora_inicio', '08:00')); ?>">
                            </div>
                            <div class="config-group">
                                <label>Hora de Fin de Entregas</label>
                                <input type="time" name="delivery_hora_fin" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('delivery_hora_fin', '20:00')); ?>">
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="delivery_domingo" value="0">
                                    <input class="form-check-input" type="checkbox" name="delivery_domingo" value="1" 
                                           id="delivery_domingo" <?php echo getConfig('delivery_domingo', '0') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="delivery_domingo">Entregas los domingos</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preguntas del formulario de calificación (independiente del
                 form principal de esta página: tiene sus propios botones y
                 sus propias llamadas al servidor, para no mezclarse con el
                 guardado de "Configuración del Sistema"). -->
            <div class="row mt-3">
                <div class="col-12">
                    <div class="config-card">
                        <div class="config-card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-layer-group"></i> Secciones del Formulario de Calificación</h5>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirModalCategoria()">
                                <i class="fas fa-plus me-1"></i> Agregar sección
                            </button>
                        </div>
                        <div class="config-card-body">
                            <p class="text-muted small mb-2">Las secciones agrupan las preguntas del formulario (por ejemplo: Repartidor, Producto, Envío). Puedes crear secciones nuevas, renombrarlas o eliminarlas — para eliminar una sección primero tiene que quedar sin preguntas.</p>
                            <div id="listaCategoriasCalif" class="d-flex flex-wrap gap-2">
                                <div class="text-center py-2"><i class="fas fa-spinner fa-spin"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-12">
                    <div class="config-card">
                        <div class="config-card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-star"></i> Preguntas del Formulario de Calificación de Clientes</h5>
                            <button type="button" class="btn btn-sm btn-primary" onclick="abrirModalPregunta()">
                                <i class="fas fa-plus me-1"></i> Agregar pregunta
                            </button>
                        </div>
                        <div class="config-card-body">
                            <p class="text-muted small mb-3">Estas son las preguntas que el cliente ve al calificar una entrega, agrupadas por sección. Puedes cambiar el texto, el tipo de respuesta (estrellas o texto libre), el orden, si es obligatoria, y desactivar las que ya no quieras usar.</p>
                            <div id="tablaPreguntasCalif">
                                <div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pestaña: Apariencia -->
        <div id="tab-appearance" class="tab-content">
            <div class="row">
                <div class="col-md-6">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-palette"></i> Personalización Visual</h5>
                        </div>
                        <div class="config-card-body">
                            <div class="config-group">
                                <label>Tema</label>
                                <select name="tema_sistema" class="form-select">
                                    <option value="claro" <?php echo getConfig('tema_sistema', 'claro') == 'claro' ? 'selected' : ''; ?>>Claro</option>
                                    <option value="oscuro" <?php echo getConfig('tema_sistema', 'claro') == 'oscuro' ? 'selected' : ''; ?>>Oscuro</option>
                                    <option value="auto" <?php echo getConfig('tema_sistema', 'claro') == 'auto' ? 'selected' : ''; ?>>Automático (según sistema)</option>
                                </select>
                            </div>
                            <div class="config-group">
                                <label>Color Primario</label>
                                <input type="color" name="color_primario" class="form-control form-control-color" 
                                       value="<?php echo htmlspecialchars(getConfig('color_primario', '#0d6efd')); ?>" style="height: 50px;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Botón Guardar -->
        <div class="text-center mt-4 mb-4">
            <button type="submit" class="btn btn-save btn-lg text-white">
                <i class="fas fa-save me-2"></i> Guardar Configuración
            </button>
        </div>
    </form>
</div>

<!-- MODAL: agregar/editar pregunta de calificación (independiente del form principal) -->
<div class="modal fade" id="modalPreguntaCalif" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="tituloModalPregunta">Nueva pregunta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="pcIdPregunta">
        <div class="mb-3">
          <label class="form-label">Sección</label>
          <select id="pcCategoria" class="form-select"></select>
        </div>
        <div class="mb-3">
          <label class="form-label">Texto de la pregunta</label>
          <textarea id="pcTexto" class="form-control" rows="2" placeholder="Ej: ¿Cómo fue la atención del repartidor?"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Tipo de respuesta</label>
          <select id="pcTipo" class="form-select">
            <option value="ESTRELLAS">Estrellas (1 a 5)</option>
            <option value="TEXTO">Texto libre</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Orden (las preguntas se muestran de menor a mayor)</label>
          <input type="number" id="pcOrden" class="form-control" value="0" min="0">
        </div>
        <div class="form-check form-switch mb-2">
          <input class="form-check-input" type="checkbox" id="pcObligatoria" checked>
          <label class="form-check-label" for="pcObligatoria">Obligatoria</label>
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="pcActivo" checked>
          <label class="form-check-label" for="pcActivo">Activa (visible para los clientes)</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="guardarPreguntaCalif()">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: agregar/editar sección de calificación -->
<div class="modal fade" id="modalCategoriaCalif" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="tituloModalCategoria">Nueva sección</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="ccIdCategoria">
        <div class="mb-3">
          <label class="form-label">Nombre de la sección</label>
          <input type="text" id="ccNombre" class="form-control" placeholder="Ej: Repartidor, Producto, Envío">
        </div>
        <div class="mb-3">
          <label class="form-label">Orden</label>
          <input type="number" id="ccOrden" class="form-control" value="0" min="0">
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="ccActivo" checked>
          <label class="form-check-label" for="ccActivo">Activa</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="guardarCategoriaCalif()">Guardar</button>
      </div>
    </div>
  </div>
</div>


<!-- NUEVO (mejora final): editor de intervalos de acción y pesos de puntuación
     del proceso estratégico de vencimientos. Reutiliza el mismo <form> y el
     mismo guardado genérico (foreach $_POST -> configuracion_sistema) que ya
     usa el resto de esta pantalla: solo construye 2 campos ocultos con JSON
     antes de enviar, no se creó ningún endpoint nuevo para esto. -->
<script>
const ETIQUETAS_ACCION_VENC = {
    MANTENER: 'Mantener en la sucursal / venta normal',
    PROMOCION: 'Aplicar promoción',
    REDISTRIBUCION: 'Evaluar transferencia a otra sucursal',
    DEVOLUCION_PROVEEDOR: 'Evaluar devolución al proveedor',
};

const ETIQUETAS_PESO_VENC = {
    rotacion_destino: 'Rotación de venta (IRV) en destino',
    demanda_historica: 'Demanda histórica en destino',
    cantidad_disponible_destino: 'Poco stock ya disponible en destino',
    tiempo_restante_vencimiento: 'Margen de tiempo antes del vencimiento',
    distancia: 'Cercanía entre sucursales',
    costo_transporte: 'Costo estimado de transporte',
    probabilidad_venta_antes_vencer: 'Probabilidad de vender antes de vencer',
};

let intervalosVencActuales = <?php echo json_encode(array_map(fn($i) => ['min' => (float)$i['min'], 'max' => (float)$i['max'], 'accion' => $i['accion']], $venc_intervalos_actuales)); ?>;
let pesosVencActuales = <?php echo json_encode($venc_pesos_actuales); ?>;
const PREFERENCIAS_CRITERIO_DEFAULT = <?php echo json_encode($venc_preferencias_default); ?>;
const SUCURSALES_CRITERIOS = <?php echo json_encode($venc_sucursales_criterios); ?>;
let criteriosPorSucursal = <?php echo json_encode($venc_pesos_por_sucursal ?: new stdClass()); ?>;
let sucursalCriteriosActiva = SUCURSALES_CRITERIOS.length ? String(SUCURSALES_CRITERIOS[0].id_sucursal) : null;

function perfilCriteriosSucursal(idSuc) {
    const key = String(idSuc);
    if (!criteriosPorSucursal[key]) {
        criteriosPorSucursal[key] = {
            pesos: { ...pesosVencActuales },
            preferencias: { ...PREFERENCIAS_CRITERIO_DEFAULT },
        };
    }
    if (!criteriosPorSucursal[key].pesos) {
        criteriosPorSucursal[key].pesos = { ...pesosVencActuales };
    }
    if (!criteriosPorSucursal[key].preferencias) {
        criteriosPorSucursal[key].preferencias = { ...PREFERENCIAS_CRITERIO_DEFAULT };
    }
    return criteriosPorSucursal[key];
}

function renderListaPesosSucursal() {
    const cont = document.getElementById('listaPesosSucursal');
    if (!cont || !sucursalCriteriosActiva) return;
    const perfil = perfilCriteriosSucursal(sucursalCriteriosActiva);
    cont.innerHTML = `
        <div class="row g-2 small fw-bold text-muted mb-1 d-none d-md-flex">
            <div class="col-md-5">Criterio</div>
            <div class="col-md-3">Peso</div>
            <div class="col-md-4">Esta sucursal prefiere</div>
        </div>` + Object.keys(ETIQUETAS_PESO_VENC).map(clave => `
        <div class="row g-2 align-items-center mb-2">
            <div class="col-md-5"><small>${ETIQUETAS_PESO_VENC[clave]}</small></div>
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control" min="0" max="100" step="1"
                           value="${perfil.pesos[clave] ?? 0}"
                           oninput="perfilCriteriosSucursal(sucursalCriteriosActiva).pesos['${clave}'] = parseFloat(this.value) || 0; validarPesosSucursalUI();">
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <div class="col-md-4">
                <select class="form-select form-select-sm"
                        onchange="perfilCriteriosSucursal(sucursalCriteriosActiva).preferencias['${clave}'] = this.value;">
                    <option value="mayor" ${(perfil.preferencias[clave] || PREFERENCIAS_CRITERIO_DEFAULT[clave]) === 'mayor' ? 'selected' : ''}>Más es mejor</option>
                    <option value="menor" ${(perfil.preferencias[clave] || PREFERENCIAS_CRITERIO_DEFAULT[clave]) === 'menor' ? 'selected' : ''}>Menos es mejor</option>
                </select>
            </div>
        </div>`).join('');
    validarPesosSucursalUI();
}

function cambiarSucursalCriterios(idSuc) {
    sucursalCriteriosActiva = String(idSuc);
    renderListaPesosSucursal();
}

function copiarPesosDefaultASucursal() {
    if (!sucursalCriteriosActiva) return;
    criteriosPorSucursal[sucursalCriteriosActiva] = {
        pesos: { ...pesosVencActuales },
        preferencias: { ...PREFERENCIAS_CRITERIO_DEFAULT },
    };
    renderListaPesosSucursal();
}

function validarPesosSucursalUI() {
    const aviso = document.getElementById('avisoPesosSucursal');
    const sumaEl = document.getElementById('sumaPesosSucursal');
    if (!aviso || !sumaEl || !sucursalCriteriosActiva) return true;
    const perfil = perfilCriteriosSucursal(sucursalCriteriosActiva);
    const suma = Object.values(perfil.pesos).reduce((acc, v) => acc + (parseFloat(v) || 0), 0);
    sumaEl.textContent = `${suma}%`;
    const valido = Math.abs(suma - 100) <= 0.5;
    aviso.innerHTML = valido
        ? `<div class="alert alert-success py-2 mb-0">Los pesos de esta sucursal suman 100%.</div>`
        : `<div class="alert alert-warning py-2 mb-0">Los pesos de esta sucursal deben sumar 100% (suman ${suma}%).</div>`;
    return valido;
}

function validarTodosPesosSucursal() {
    if (!SUCURSALES_CRITERIOS.length) return true;
    for (const suc of SUCURSALES_CRITERIOS) {
        const perfil = perfilCriteriosSucursal(suc.id_sucursal);
        const suma = Object.values(perfil.pesos).reduce((acc, v) => acc + (parseFloat(v) || 0), 0);
        if (Math.abs(suma - 100) > 0.5) {
            sucursalCriteriosActiva = String(suc.id_sucursal);
            const sel = document.getElementById('selectSucursalCriterios');
            if (sel) sel.value = sucursalCriteriosActiva;
            renderListaPesosSucursal();
            return false;
        }
    }
    return true;
}

function renderTablaIntervalos() {
    const cont = document.getElementById('tablaIntervalos');
    cont.innerHTML = intervalosVencActuales.map((iv, idx) => `
        <div class="row g-2 align-items-center mb-2">
            <div class="col-3">
                <input type="number" class="form-control form-control-sm" min="0" max="100" step="0.01"
                       value="${iv.min}" onchange="intervalosVencActuales[${idx}].min = parseFloat(this.value) || 0; validarIntervalosUI();">
            </div>
            <div class="col-1 text-center text-muted small">a</div>
            <div class="col-3">
                <input type="number" class="form-control form-control-sm" min="0" max="100" step="0.01"
                       value="${iv.max}" onchange="intervalosVencActuales[${idx}].max = parseFloat(this.value) || 0; validarIntervalosUI();">
            </div>
            <div class="col-4">
                <select class="form-select form-select-sm" onchange="intervalosVencActuales[${idx}].accion = this.value; validarIntervalosUI();">
                    ${Object.keys(ETIQUETAS_ACCION_VENC).map(a => `<option value="${a}" ${a === iv.accion ? 'selected' : ''}>${ETIQUETAS_ACCION_VENC[a]}</option>`).join('')}
                </select>
            </div>
            <div class="col-1 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarFilaIntervalo(${idx})" title="Eliminar">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>`).join('');
    validarIntervalosUI();
}

function agregarFilaIntervalo() {
    intervalosVencActuales.push({ min: 0, max: 0, accion: 'PROMOCION' });
    renderTablaIntervalos();
}

function eliminarFilaIntervalo(idx) {
    intervalosVencActuales.splice(idx, 1);
    renderTablaIntervalos();
}

function validarIntervalosUI() {
    const aviso = document.getElementById('avisoIntervalos');
    const errores = [];

    if (intervalosVencActuales.length === 0) {
        errores.push('Debe configurar al menos un intervalo.');
    }

    const ordenados = [...intervalosVencActuales].sort((a, b) => a.min - b.min);
    ordenados.forEach(iv => {
        if (iv.min >= iv.max) errores.push(`El intervalo ${iv.min}%-${iv.max}% tiene el mínimo mayor o igual al máximo.`);
    });
    if (ordenados.length && Math.abs(ordenados[0].min - 0) > 0.02) {
        errores.push('Los intervalos deben cubrir desde 0%.');
    }
    for (let i = 0; i < ordenados.length - 1; i++) {
        const finActual = ordenados[i].max;
        const inicioSig = ordenados[i + 1].min;
        if (inicioSig - finActual > 0.02) errores.push(`Hay un hueco entre ${finActual}% y ${inicioSig}%.`);
        else if (finActual - inicioSig > 0.02) errores.push(`Los intervalos alrededor de ${inicioSig}% se traslapan.`);
    }
    if (ordenados.length && Math.abs(ordenados[ordenados.length - 1].max - 100) > 0.02) {
        errores.push('Los intervalos deben cubrir hasta 100%.');
    }

    if (errores.length) {
        aviso.innerHTML = `<div class="alert alert-warning py-2 mb-0">${errores.join('<br>')}</div>`;
    } else {
        aviso.innerHTML = `<div class="alert alert-success py-2 mb-0">Los intervalos cubren correctamente de 0% a 100%.</div>`;
    }
    return errores.length === 0;
}

function renderListaPesos() {
    const cont = document.getElementById('listaPesos');
    cont.innerHTML = Object.keys(ETIQUETAS_PESO_VENC).map(clave => `
        <div class="row g-2 align-items-center mb-2">
            <div class="col-8"><small>${ETIQUETAS_PESO_VENC[clave]}</small></div>
            <div class="col-4">
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control" min="0" max="100" step="1"
                           value="${pesosVencActuales[clave] ?? 0}"
                           oninput="pesosVencActuales['${clave}'] = parseFloat(this.value) || 0; validarPesosUI();">
                    <span class="input-group-text">%</span>
                </div>
            </div>
        </div>`).join('');
    validarPesosUI();
}

function validarPesosUI() {
    const suma = Object.values(pesosVencActuales).reduce((acc, v) => acc + (parseFloat(v) || 0), 0);
    document.getElementById('sumaPesos').textContent = `${suma}%`;
    const aviso = document.getElementById('avisoPesos');
    const valido = Math.abs(suma - 100) <= 0.5;
    aviso.innerHTML = valido
        ? `<div class="alert alert-success py-2 mb-0">Los pesos suman 100%.</div>`
        : `<div class="alert alert-warning py-2 mb-0">Los pesos deben sumar 100% (suman ${suma}%).</div>`;
    return valido;
}

document.addEventListener('DOMContentLoaded', function() {
    renderTablaIntervalos();
    renderListaPesos();
    if (sucursalCriteriosActiva) {
        SUCURSALES_CRITERIOS.forEach(s => perfilCriteriosSucursal(s.id_sucursal));
        renderListaPesosSucursal();
    }
});

// Antes de enviar el formulario general de Configuración, se validan y
// serializan los 2 campos JSON de este bloque. Si algo no cuadra, se detiene
// el envío (no se guarda una configuración incoherente) y se cambia a esta
// pestaña para que el usuario vea el detalle.
document.getElementById('configForm')?.addEventListener('submit', function(e) {
    const intervalosOk = validarIntervalosUI();
    const pesosOk = validarPesosUI();
    const pesosSucursalOk = validarTodosPesosSucursal();

    if (!intervalosOk || !pesosOk || !pesosSucursalOk) {
        e.preventDefault();
        document.querySelectorAll('.config-tab').forEach(t => t.classList.remove('active'));
        document.querySelector('.config-tab[data-tab="vencimientos"]').classList.add('active');
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById('tab-vencimientos').classList.add('active');
        Swal.fire ? Swal.fire('Revisa la pestaña Vencimientos', 'Los intervalos o los pesos (globales o por sucursal) no son coherentes todavía.', 'warning')
                  : alert('Los intervalos o los pesos de la pestaña Vencimientos no son coherentes todavía.');
        return;
    }

    document.getElementById('inputIntervalosAccion').value = JSON.stringify(intervalosVencActuales);
    document.getElementById('inputPesosTransferencia').value = JSON.stringify(pesosVencActuales);
    const inputPorSuc = document.getElementById('inputPesosPorSucursal');
    if (inputPorSuc) {
        inputPorSuc.value = JSON.stringify(criteriosPorSucursal);
    }
});
</script>

<script>
const BASE_URL = '<?php echo $base_url; ?>';
    // Manejo de pestañas
    document.querySelectorAll('.config-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            // Remover active de todas las pestañas
            document.querySelectorAll('.config-tab').forEach(t => t.classList.remove('active'));
            // Activar esta pestaña
            this.classList.add('active');
            
            // Ocultar todos los contenidos
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Mostrar el contenido correspondiente
            const tabId = this.getAttribute('data-tab');
            document.getElementById(`tab-${tabId}`).classList.add('active');
        });
    });
    
    // Inicializar tooltips de Bootstrap si es necesario
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });
</script>

<script>
// ══════════════ Secciones y preguntas del formulario de calificación ══════════════
// Independiente del <form id="configForm"> de arriba: tiene sus propios
// botones (type="button") y sus propias llamadas fetch, para no mezclarse
// con el guardado de "Configuración del Sistema".
let modalPreguntaCalif, modalCategoriaCalif;
let preguntasCalifCache = [];
let categoriasCalifCache = [];

document.addEventListener('DOMContentLoaded', () => {
    // Esta pantalla se carga DENTRO de menuprincipal.php, y estos modales
    // quedan anidados en el contenedor del módulo. Si algún ancestro tiene
    // CSS "transform" (frecuente en layouts con sidebar animado), el
    // "position: fixed" de Bootstrap se rompe y el modal sale cortado /
    // fuera de centro en vez de cubrir toda la pantalla. Se soluciona
    // moviendo el modal para que sea hijo directo de <body>.
    [document.getElementById('modalPreguntaCalif'), document.getElementById('modalCategoriaCalif')].forEach(el => {
        if (el && el.parentElement !== document.body) document.body.appendChild(el);
    });
    modalPreguntaCalif = new bootstrap.Modal('#modalPreguntaCalif');
    modalCategoriaCalif = new bootstrap.Modal('#modalCategoriaCalif');
    cargarCategoriasCalif().then(cargarPreguntasCalif);
});

// ---------- Secciones (categorías) ----------

function cargarCategoriasCalif() {
    return fetch(BASE_URL + '/backend/administracion/listar_categorias_calificacion.php')
        .then(r => r.json())
        .then(data => {
            const cont = document.getElementById('listaCategoriasCalif');
            if (!data.success) { cont.innerHTML = `<div class="alert alert-danger">${data.message}</div>`; return; }
            categoriasCalifCache = data.categorias;
            if (!categoriasCalifCache.length) {
                cont.innerHTML = '<p class="text-muted small mb-0">No hay secciones todavía. Agrega la primera.</p>';
                return;
            }
            cont.innerHTML = categoriasCalifCache.map(c => `
                <span class="badge ${c.activo ? 'bg-primary' : 'bg-secondary'} d-flex align-items-center gap-2" style="font-size:.85rem;padding:.5em .8em;">
                    ${c.nombre} <small class="opacity-75">(${c.total_preguntas})</small>
                    <i class="fas fa-edit" style="cursor:pointer;" onclick="editarCategoriaCalif(${c.id_categoria})" title="Editar"></i>
                    <i class="fas fa-trash" style="cursor:pointer;" onclick="eliminarCategoriaCalif(${c.id_categoria})" title="Eliminar"></i>
                </span>`).join('');
        })
        .catch(() => { document.getElementById('listaCategoriasCalif').innerHTML = '<div class="alert alert-danger">Error de conexión.</div>'; });
}

function abrirModalCategoria() {
    document.getElementById('tituloModalCategoria').textContent = 'Nueva sección';
    document.getElementById('ccIdCategoria').value = '';
    document.getElementById('ccNombre').value = '';
    document.getElementById('ccOrden').value = categoriasCalifCache.length ? Math.max(...categoriasCalifCache.map(c => c.orden)) + 1 : 1;
    document.getElementById('ccActivo').checked = true;
    modalCategoriaCalif.show();
}

function editarCategoriaCalif(idCategoria) {
    const c = categoriasCalifCache.find(x => x.id_categoria === idCategoria);
    if (!c) return;
    document.getElementById('tituloModalCategoria').textContent = 'Editar sección';
    document.getElementById('ccIdCategoria').value = c.id_categoria;
    document.getElementById('ccNombre').value = c.nombre;
    document.getElementById('ccOrden').value = c.orden;
    document.getElementById('ccActivo').checked = (c.activo === true || c.activo === 't');
    modalCategoriaCalif.show();
}

function guardarCategoriaCalif() {
    const nombre = document.getElementById('ccNombre').value.trim();
    if (!nombre) { alert('La sección necesita un nombre.'); return; }
    fetch(BASE_URL + '/backend/administracion/guardar_categoria_calificacion.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            id_categoria: document.getElementById('ccIdCategoria').value ? parseInt(document.getElementById('ccIdCategoria').value) : null,
            nombre: nombre,
            orden: parseInt(document.getElementById('ccOrden').value || 0),
            activo: document.getElementById('ccActivo').checked,
        })
    }).then(r => r.json()).then(data => {
        if (data.success) {
            modalCategoriaCalif.hide();
            cargarCategoriasCalif().then(cargarPreguntasCalif);
        } else alert('Error: ' + data.message);
    }).catch(() => alert('Error de conexión.'));
}

function eliminarCategoriaCalif(idCategoria) {
    const c = categoriasCalifCache.find(x => x.id_categoria === idCategoria);
    if (!c) return;
    if (!confirm(`¿Eliminar la sección "${c.nombre}"? Solo se puede borrar si ya no tiene preguntas.`)) return;
    fetch(BASE_URL + '/backend/administracion/eliminar_categoria_calificacion.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id_categoria: idCategoria })
    }).then(r => r.json()).then(data => {
        if (data.success) cargarCategoriasCalif().then(cargarPreguntasCalif);
        else alert('No se pudo eliminar: ' + data.message);
    }).catch(() => alert('Error de conexión.'));
}

// ---------- Preguntas ----------

function cargarPreguntasCalif() {
    fetch(BASE_URL + '/backend/administracion/listar_preguntas_calificacion.php')
        .then(r => r.json())
        .then(data => {
            const cont = document.getElementById('tablaPreguntasCalif');
            if (!data.success) { cont.innerHTML = `<div class="alert alert-danger">${data.message}</div>`; return; }
            preguntasCalifCache = data.preguntas;

            if (!categoriasCalifCache.length) {
                cont.innerHTML = '<p class="text-muted text-center py-3">Primero crea al menos una sección arriba.</p>';
                return;
            }

            cont.innerHTML = categoriasCalifCache.map(cat => {
                const preguntas = preguntasCalifCache.filter(p => p.categoria === cat.nombre);
                const filas = preguntas.length ? preguntas.map(p => `
                    <tr class="${!(p.activo === true || p.activo === 't') ? 'table-secondary' : ''}">
                        <td>${p.orden}</td>
                        <td>${p.texto}</td>
                        <td>${p.tipo_respuesta === 'ESTRELLAS' ? '<i class="fas fa-star text-warning"></i> Estrellas' : '<i class="fas fa-align-left"></i> Texto libre'}</td>
                        <td class="text-center">${(p.obligatoria === true || p.obligatoria === 't') ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-minus text-muted"></i>'}</td>
                        <td class="text-center">
                            <div class="form-check form-switch d-flex justify-content-center">
                                <input class="form-check-input" type="checkbox" ${(p.activo === true || p.activo === 't') ? 'checked' : ''} onchange="toggleActivoPregunta(${p.id_pregunta}, this.checked)">
                            </div>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="editarPreguntaCalif(${p.id_pregunta})" title="Editar"><i class="fas fa-edit"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarPreguntaCalif(${p.id_pregunta})" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>`).join('') : `<tr><td colspan="6" class="text-muted text-center small py-2">Sin preguntas en esta sección todavía.</td></tr>`;

                return `
                    <h6 class="mt-3 mb-2 text-primary">${cat.nombre}</h6>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-2">
                            <thead><tr><th style="width:70px;">Orden</th><th>Pregunta</th><th style="width:140px;">Tipo</th><th class="text-center" style="width:100px;">Obligatoria</th><th class="text-center" style="width:80px;">Activa</th><th class="text-center" style="width:70px;">Acción</th></tr></thead>
                            <tbody>${filas}</tbody>
                        </table>
                    </div>`;
            }).join('');
        })
        .catch(() => { document.getElementById('tablaPreguntasCalif').innerHTML = '<div class="alert alert-danger">Error de conexión.</div>'; });
}

function pobladorCategoriasSelect(seleccionado) {
    return categoriasCalifCache.map(c => `<option value="${c.id_categoria}" ${c.id_categoria === seleccionado ? 'selected' : ''}>${c.nombre}</option>`).join('');
}

function abrirModalPregunta() {
    if (!categoriasCalifCache.length) { alert('Primero crea al menos una sección.'); return; }
    document.getElementById('tituloModalPregunta').textContent = 'Nueva pregunta';
    document.getElementById('pcIdPregunta').value = '';
    document.getElementById('pcCategoria').innerHTML = pobladorCategoriasSelect(categoriasCalifCache[0].id_categoria);
    document.getElementById('pcTexto').value = '';
    document.getElementById('pcTipo').value = 'ESTRELLAS';
    document.getElementById('pcOrden').value = preguntasCalifCache.length ? Math.max(...preguntasCalifCache.map(p => p.orden)) + 1 : 1;
    document.getElementById('pcObligatoria').checked = true;
    document.getElementById('pcActivo').checked = true;
    modalPreguntaCalif.show();
}

function eliminarPreguntaCalif(idPregunta) {
    const p = preguntasCalifCache.find(x => x.id_pregunta === idPregunta);
    if (!p) return;
    if (!confirm(`¿Eliminar la pregunta "${p.texto}"? Las respuestas que ya dieron los clientes a esta pregunta se conservan, solo deja de aparecer en el formulario.`)) return;
    fetch(BASE_URL + '/backend/administracion/eliminar_pregunta_calificacion.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id_pregunta: idPregunta })
    }).then(r => r.json()).then(data => {
        if (data.success) cargarPreguntasCalif();
        else alert('No se pudo eliminar: ' + data.message);
    }).catch(() => alert('Error de conexión.'));
}

function editarPreguntaCalif(idPregunta) {
    const p = preguntasCalifCache.find(x => x.id_pregunta === idPregunta);
    if (!p) return;
    document.getElementById('tituloModalPregunta').textContent = 'Editar pregunta';
    document.getElementById('pcIdPregunta').value = p.id_pregunta;
    document.getElementById('pcCategoria').innerHTML = pobladorCategoriasSelect(p.id_categoria);
    document.getElementById('pcTexto').value = p.texto;
    document.getElementById('pcTipo').value = p.tipo_respuesta;
    document.getElementById('pcOrden').value = p.orden;
    document.getElementById('pcObligatoria').checked = (p.obligatoria === true || p.obligatoria === 't');
    document.getElementById('pcActivo').checked = (p.activo === true || p.activo === 't');
    modalPreguntaCalif.show();
}

function toggleActivoPregunta(idPregunta, activo) {
    const p = preguntasCalifCache.find(x => x.id_pregunta === idPregunta);
    if (!p) return;
    guardarPreguntaCalifPayload({
        id_pregunta: p.id_pregunta, id_categoria: p.id_categoria, texto: p.texto,
        tipo_respuesta: p.tipo_respuesta, obligatoria: (p.obligatoria === true || p.obligatoria === 't'),
        orden: p.orden, activo: activo,
    });
}

function guardarPreguntaCalif() {
    const texto = document.getElementById('pcTexto').value.trim();
    if (!texto) { alert('La pregunta necesita un texto.'); return; }
    guardarPreguntaCalifPayload({
        id_pregunta: document.getElementById('pcIdPregunta').value ? parseInt(document.getElementById('pcIdPregunta').value) : null,
        id_categoria: parseInt(document.getElementById('pcCategoria').value),
        texto: texto,
        tipo_respuesta: document.getElementById('pcTipo').value,
        obligatoria: document.getElementById('pcObligatoria').checked,
        orden: parseInt(document.getElementById('pcOrden').value || 0),
        activo: document.getElementById('pcActivo').checked,
    }, true);
}

function guardarPreguntaCalifPayload(payload, cerrarModal) {
    fetch(BASE_URL + '/backend/administracion/guardar_pregunta_calificacion.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    }).then(r => r.json()).then(data => {
        if (data.success) {
            if (cerrarModal) modalPreguntaCalif.hide();
            cargarPreguntasCalif();
        } else {
            alert('Error: ' + data.message);
            cargarPreguntasCalif(); // por si el switch quedó desincronizado
        }
    }).catch(() => alert('Error de conexión.'));
}
</script>
