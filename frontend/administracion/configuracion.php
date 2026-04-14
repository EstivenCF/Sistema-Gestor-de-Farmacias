<?php
// Verificar acceso
if (!isset($rol_usuario) || $rol_usuario !== 'Administrador') {
    echo "<div class='alert alert-danger m-4'>
            <span class='material-symbols-rounded me-2'>warning</span>
            <strong>Acceso denegado:</strong> Solo administradores pueden acceder a esta sección.
          </div>";
    exit;
}

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
            <i class="fas fa-truck me-2"></i>Delivery
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
                                    <input class="form-check-input" type="checkbox" name="mostrar_logo_factura" value="1" 
                                           id="mostrar_logo_factura" <?php echo getConfig('mostrar_logo_factura', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="mostrar_logo_factura">Mostrar logo en facturas</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
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
                                    <input class="form-check-input" type="checkbox" name="permitir_stock_negativo" value="1" 
                                           id="permitir_stock_negativo" <?php echo getConfig('permitir_stock_negativo', '0') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="permitir_stock_negativo">Permitir stock negativo</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
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
                                    <input class="form-check-input" type="checkbox" name="itbis_medicamentos_sin_receta" value="1" 
                                           id="itbis_medicamentos_sin_receta" <?php echo getConfig('itbis_medicamentos_sin_receta', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="itbis_medicamentos_sin_receta">Aplicar ITBIS a medicamentos sin receta</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="itbis_medicamentos_con_receta" value="1" 
                                           id="itbis_medicamentos_con_receta" <?php echo getConfig('itbis_medicamentos_con_receta', '0') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="itbis_medicamentos_con_receta">Aplicar ITBIS a medicamentos con receta</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="itbis_ropa" value="1" 
                                           id="itbis_ropa" <?php echo getConfig('itbis_ropa', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="itbis_ropa">Aplicar ITBIS a productos de ropa</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
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
                                    <input class="form-check-input" type="checkbox" name="requerir_mayusculas" value="1" 
                                           id="requerir_mayusculas" <?php echo getConfig('requerir_mayusculas', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="requerir_mayusculas">Requerir mayúsculas en contraseña</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="requerir_numeros" value="1" 
                                           id="requerir_numeros" <?php echo getConfig('requerir_numeros', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="requerir_numeros">Requerir números en contraseña</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
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
                                    <input class="form-check-input" type="checkbox" name="notificaciones_activas" value="1" 
                                           id="notificaciones_activas" <?php echo getConfig('notificaciones_activas', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notificaciones_activas">Activar notificaciones del sistema</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="notificar_stock_critico" value="1" 
                                           id="notificar_stock_critico" <?php echo getConfig('notificar_stock_critico', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notificar_stock_critico">Notificar stock crítico</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <div class="form-check form-switch">
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
        
        <!-- Pestaña: Delivery -->
        <div id="tab-delivery" class="tab-content">
            <div class="row">
                <div class="col-md-6">
                    <div class="config-card">
                        <div class="config-card-header">
                            <h5><i class="fas fa-truck"></i> Configuración de Delivery</h5>
                        </div>
                        <div class="config-card-body">
                            <div class="config-group">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="delivery_activo" value="1" 
                                           id="delivery_activo" <?php echo getConfig('delivery_activo', '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="delivery_activo">Activar servicio de delivery</label>
                                </div>
                            </div>
                            <div class="config-group">
                                <label>Costo de Envío por Defecto</label>
                                <input type="number" name="costo_envio_default" class="form-control" 
                                       value="<?php echo htmlspecialchars(getConfig('costo_envio_default', '100')); ?>" step="10" min="0">
                                <small class="form-text">Costo base para envíos (RD$)</small>
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
                            <h5><i class="fas fa-clock"></i> Horarios de Delivery</h5>
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
                                    <input class="form-check-input" type="checkbox" name="delivery_domingo" value="1" 
                                           id="delivery_domingo" <?php echo getConfig('delivery_domingo', '0') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="delivery_domingo">Entregas los domingos</label>
                                </div>
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

<script>
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