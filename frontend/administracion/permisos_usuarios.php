<?php
if (!isset($_SESSION['id_sesion'])) {
    header("Location: index.php");
    exit();
}

// Verificar que solo administradores puedan acceder
if ($rol_usuario !== 'Administrador') {
    echo "<div class='alert alert-danger m-4'>
            <span class='material-symbols-rounded me-2'>warning</span>
            <strong>Acceso denegado:</strong> Solo los administradores pueden gestionar permisos.
          </div>";
    exit();
}

// Obtener todos los usuarios
$stmt_usuarios = $conexion->prepare("
    SELECT u.id_usuario, u.nombre, u.usuario, u.imagen_url, r.nombre as rol_nombre, r.id_rol, u.estado
    FROM usuarios u
    JOIN roles r ON u.id_rol = r.id_rol
    WHERE u.estado = TRUE
    ORDER BY u.nombre
");
$stmt_usuarios->execute();
$usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);

// Usuario seleccionado (por defecto el primero)
$usuario_seleccionado_id = isset($_GET['id_usuario']) ? intval($_GET['id_usuario']) : ($usuarios[0]['id_usuario'] ?? 0);

// Obtener datos del usuario seleccionado incluyendo la imagen
$stmt_usuario = $conexion->prepare("
    SELECT u.id_usuario, u.nombre, u.usuario, u.imagen_url, r.nombre as rol_nombre, r.id_rol
    FROM usuarios u
    JOIN roles r ON u.id_rol = r.id_rol
    WHERE u.id_usuario = ?
");
$stmt_usuario->execute([$usuario_seleccionado_id]);
$usuario_actual = $stmt_usuario->fetch(PDO::FETCH_ASSOC);

$imagen_perfil = '/sistema-gestor-de-farmacias/assets/img/usuarios/default.png';

if (!empty($usuario_actual['imagen_url'])) {
    $imagen_perfil = $usuario_actual['imagen_url'];
}

// Obtener permisos actuales del usuario (desde usuario_permiso)
$stmt_permisos_actuales = $conexion->prepare("
    SELECT p.nombre 
    FROM usuario_permiso up
    JOIN permisos p ON up.id_permiso = p.id_permiso
    WHERE up.id_usuario = ? AND up.permitido = TRUE
");
$stmt_permisos_actuales->execute([$usuario_seleccionado_id]);
$permisos_actuales_nombres = $stmt_permisos_actuales->fetchAll(PDO::FETCH_COLUMN);

// Convertir a array asociativo para fácil acceso
$permisos_actuales_map = [];
foreach ($permisos_actuales_nombres as $perm) {
    $permisos_actuales_map[$perm] = true;
}
?>

<div class="container-fluid px-4 py-4">
    <!-- Título -->
    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h3 class="mb-0 text-success fw-bold">
                <span class="material-symbols-rounded align-middle me-2" style="font-size: 32px;">shield_person</span>
                Control de Acceso al Sistema
            </h3>
            <p class="text-muted mb-0">Gestione los permisos y niveles de acceso para cada usuario</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Panel Izquierdo: Selección de Usuario -->
        <div class="col-lg-3">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                    <h5 class="card-title mb-0">
                        <span class="material-symbols-rounded me-1 text-primary">person</span>
                        Datos Generales
                    </h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <label class="form-label fw-semibold mb-2 text-muted">USUARIO A CONFIGURAR</label>
                    <select class="form-select form-select-lg mb-3 border-primary" id="selectUsuario" onchange="cambiarUsuario()" style="border-radius: 12px;">
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?php echo $u['id_usuario']; ?>" 
                                data-rol="<?php echo htmlspecialchars($u['rol_nombre']); ?>"
                                data-imagen="<?php echo htmlspecialchars($u['imagen_url'] ?? ''); ?>"
                                <?php echo $usuario_seleccionado_id == $u['id_usuario'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['nombre']); ?> (<?php echo htmlspecialchars($u['usuario']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <!-- Tarjeta de usuario en VERDE -->
                    <div class="text-center p-4 rounded-4 mt-3" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); box-shadow: 0 4px 15px rgba(5, 150, 105, 0.3);">
                        <div class="mb-3">
                            <div class="user-avatar-circle mx-auto mb-2" style="width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; overflow: hidden; border: 3px solid rgba(255,255,255,0.5);">
                                <img src="<?php echo $imagen_perfil; ?>" alt="Foto de perfil" class="user-avatar-img" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" 
                                     onerror="this.src='../assets/img/usuarios/default.png'">
                            </div>
                        </div>
                        <h5 class="text-white mb-1"><?php echo htmlspecialchars($usuario_actual['nombre'] ?? ''); ?></h5>
                        <p class="text-white-50 mb-0">ID: <?php echo str_pad($usuario_actual['id_usuario'] ?? 0, 3, '0', STR_PAD_LEFT); ?></p>
                        <div class="mt-2">
                            <span class="badge bg-white text-success px-3 py-2 rounded-pill">
                                Rol Actual: <?php echo htmlspecialchars($usuario_actual['rol_nombre'] ?? ''); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Botón para cargar permisos por defecto según el rol -->
                    <button type="button" class="btn btn-outline-success w-100 mt-3 rounded-pill" onclick="cargarPermisosPorRol()" id="btnCargarPorRol" style="border-radius: 50px !important;">
                        <span class="material-symbols-rounded me-1">auto_awesome</span>
                        Cargar permisos por defecto del rol
                    </button>
                </div>
            </div>
        </div>

        <!-- Panel Derecho: Permisos -->
        <div class="col-lg-9">
            <form id="formPermisos" method="POST">
                <input type="hidden" name="id_usuario" id="id_usuario" value="<?php echo $usuario_seleccionado_id; ?>">
                <input type="hidden" name="accion" value="guardar_permisos">
                <input type="hidden" name="permisos_json" id="permisos_json">
                
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                    <div class="card-header bg-white border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="card-title mb-0">
                                <span class="material-symbols-rounded me-1 text-primary">toggle_on</span>
                                Permisos del Sistema
                            </h5>
                            <small class="text-muted">Active o desactive los permisos usando los interruptores</small>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="toggleAllPermissions(true)" style="border-radius: 50px !important;">
                                <span class="material-symbols-rounded me-1">select_all</span>
                                Seleccionar Todos
                            </button>
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="toggleAllPermissions(false)" style="border-radius: 50px !important;">
                                <span class="material-symbols-rounded me-1">deselect</span>
                                Deseleccionar Todos
                            </button>
                            <button type="submit" class="btn btn-success rounded-pill px-4" id="btnGuardar" style="border-radius: 50px !important; background: linear-gradient(135deg, #059669 0%, #10b981 100%); border: none;">
                                <span class="material-symbols-rounded me-1">save</span>
                                Guardar Cambios
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-4" id="permisosContainer">
                            <!-- Dashboard (siempre visible y activo) -->
                            <div class="col-md-6 col-xl-4">
                                <div class="card modulo-card h-100 border-0 shadow-sm rounded-4">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="module-icon p-2 bg-success bg-opacity-10 rounded-3">
                                                    <span class="material-symbols-rounded text-success">dashboard</span>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold">Dashboard</h6>
                                                    <small class="text-muted">Panel principal</small>
                                                </div>
                                            </div>
                                            <label class="ios-switch">
                                                <input type="checkbox" class="modulo-switch" data-modulo="dashboard" checked disabled>
                                                <span class="slider round"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Ventas -->
                            <div class="col-md-6 col-xl-4">
                                <div class="card modulo-card h-100 border-0 shadow-sm rounded-4">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="module-icon p-2 bg-info bg-opacity-10 rounded-3">
                                                    <span class="material-symbols-rounded text-info">point_of_sale</span>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold">Ventas</h6>
                                                    <small class="text-muted">Módulo de ventas</small>
                                                </div>
                                            </div>
                                            <label class="ios-switch">
                                                <input type="checkbox" class="modulo-switch" data-modulo="ventas" id="switch_ventas" <?php echo isset($permisos_actuales_map['ventas']) ? 'checked' : ''; ?>>
                                                <span class="slider round"></span>
                                            </label>
                                        </div>
                                        <div class="subpermisos ps-4 mt-2" id="subpermisos_ventas" style="display: <?php echo isset($permisos_actuales_map['ventas']) ? 'block' : 'none'; ?>;">
                                            <div class="small text-muted mb-1">Submódulos:</div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="registrar_venta" id="perm_registrar_venta" <?php echo isset($permisos_actuales_map['registrar_venta']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_registrar_venta">Registrar Venta</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="historial_ventas" id="perm_historial_ventas" <?php echo isset($permisos_actuales_map['historial_ventas']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_historial_ventas">Historial de Ventas</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="pagos" id="perm_pagos" <?php echo isset($permisos_actuales_map['pagos']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_pagos">Pagos</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="facturacion" id="perm_facturacion" <?php echo isset($permisos_actuales_map['facturacion']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_facturacion">Facturación</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Inventario -->
                            <div class="col-md-6 col-xl-4">
                                <div class="card modulo-card h-100 border-0 shadow-sm rounded-4">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="module-icon p-2 bg-warning bg-opacity-10 rounded-3">
                                                    <span class="material-symbols-rounded text-warning">inventory_2</span>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold">Inventario</h6>
                                                    <small class="text-muted">Gestión de inventario</small>
                                                </div>
                                            </div>
                                            <label class="ios-switch">
                                                <input type="checkbox" class="modulo-switch" data-modulo="inventario" id="switch_inventario" <?php echo isset($permisos_actuales_map['inventario']) ? 'checked' : ''; ?>>
                                                <span class="slider round"></span>
                                            </label>
                                        </div>
                                        <div class="subpermisos ps-4 mt-2" id="subpermisos_inventario" style="display: <?php echo isset($permisos_actuales_map['inventario']) ? 'block' : 'none'; ?>;">
                                            <div class="small text-muted mb-1">Submódulos:</div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="medicamentos" id="perm_medicamentos" <?php echo isset($permisos_actuales_map['medicamentos']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_medicamentos">Medicamentos</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="categorias" id="perm_categorias" <?php echo isset($permisos_actuales_map['categorias']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_categorias">Categorías</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="lotes" id="perm_lotes" <?php echo isset($permisos_actuales_map['lotes']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_lotes">Lotes</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="stock" id="perm_stock" <?php echo isset($permisos_actuales_map['stock']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_stock">Control de Stock</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="vencimientos" id="perm_vencimientos" <?php echo isset($permisos_actuales_map['vencimientos']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_vencimientos">Vencimientos</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Compras -->
                            <div class="col-md-6 col-xl-4">
                                <div class="card modulo-card h-100 border-0 shadow-sm rounded-4">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="module-icon p-2 bg-danger bg-opacity-10 rounded-3">
                                                    <span class="material-symbols-rounded text-danger">shopping_cart_checkout</span>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold">Compras</h6>
                                                    <small class="text-muted">Gestión de compras</small>
                                                </div>
                                            </div>
                                            <label class="ios-switch">
                                                <input type="checkbox" class="modulo-switch" data-modulo="compras" id="switch_compras" <?php echo isset($permisos_actuales_map['compras']) ? 'checked' : ''; ?>>
                                                <span class="slider round"></span>
                                            </label>
                                        </div>
                                        <div class="subpermisos ps-4 mt-2" id="subpermisos_compras" style="display: <?php echo isset($permisos_actuales_map['compras']) ? 'block' : 'none'; ?>;">
                                            <div class="small text-muted mb-1">Submódulos:</div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="registrar_compra" id="perm_registrar_compra" <?php echo isset($permisos_actuales_map['registrar_compra']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_registrar_compra">Registrar Compra</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="historial_compras" id="perm_historial_compras" <?php echo isset($permisos_actuales_map['historial_compras']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_historial_compras">Historial de Compras</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="proveedores" id="perm_proveedores" <?php echo isset($permisos_actuales_map['proveedores']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_proveedores">Proveedores</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Clientes -->
                            <div class="col-md-6 col-xl-4">
                                <div class="card modulo-card h-100 border-0 shadow-sm rounded-4">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="module-icon p-2 bg-primary bg-opacity-10 rounded-3">
                                                    <span class="material-symbols-rounded text-primary">groups</span>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold">Clientes</h6>
                                                    <small class="text-muted">Gestión de clientes</small>
                                                </div>
                                            </div>
                                            <label class="ios-switch">
                                                <input type="checkbox" class="modulo-switch" data-modulo="clientes" id="switch_clientes" <?php echo isset($permisos_actuales_map['clientes']) ? 'checked' : ''; ?>>
                                                <span class="slider round"></span>
                                            </label>
                                        </div>
                                        <div class="subpermisos ps-4 mt-2" id="subpermisos_clientes" style="display: <?php echo isset($permisos_actuales_map['clientes']) ? 'block' : 'none'; ?>;">
                                            <div class="small text-muted mb-1">Submódulos:</div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="clientes_lista" id="perm_clientes_lista" <?php echo isset($permisos_actuales_map['clientes_lista']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_clientes_lista">Lista de Clientes</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="historial_cliente" id="perm_historial_cliente" <?php echo isset($permisos_actuales_map['historial_cliente']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_historial_cliente">Historial</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- DELIVERY (NUEVO) -->
                            <div class="col-md-6 col-xl-4">
                                <div class="card modulo-card h-100 border-0 shadow-sm rounded-4">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="module-icon p-2 rounded-3" style="background-color: rgba(139, 92, 246, 0.1);">
                                                    <span class="material-symbols-rounded" style="color: #8b5cf6;">local_shipping</span>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold">Delivery</h6>
                                                    <small class="text-muted">Gestión de entregas</small>
                                                </div>
                                            </div>
                                            <label class="ios-switch">
                                                <input type="checkbox" class="modulo-switch" data-modulo="delivery" id="switch_delivery" <?php echo isset($permisos_actuales_map['delivery']) ? 'checked' : ''; ?>>
                                                <span class="slider round"></span>
                                            </label>
                                        </div>
                                        <div class="subpermisos ps-4 mt-2" id="subpermisos_delivery" style="display: <?php echo isset($permisos_actuales_map['delivery']) ? 'block' : 'none'; ?>;">
                                            <div class="small text-muted mb-1">Submódulos:</div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="repartidores" id="perm_repartidores" <?php echo isset($permisos_actuales_map['repartidores']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_repartidores">Repartidores</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="entregas" id="perm_entregas" <?php echo isset($permisos_actuales_map['entregas']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_entregas">Entregas</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="vehiculos" id="perm_vehiculos" <?php echo isset($permisos_actuales_map['vehiculos']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_vehiculos">Vehículos</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="tracking" id="perm_tracking" <?php echo isset($permisos_actuales_map['tracking']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_tracking">Tracking</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="incidencias_delivery" id="perm_incidencias_delivery" <?php echo isset($permisos_actuales_map['incidencias_delivery']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_incidencias_delivery">Incidencias</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Caja -->
                            <div class="col-md-6 col-xl-4">
                                <div class="card modulo-card h-100 border-0 shadow-sm rounded-4">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="module-icon p-2 bg-secondary bg-opacity-10 rounded-3">
                                                    <span class="material-symbols-rounded text-secondary">payments</span>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold">Caja</h6>
                                                    <small class="text-muted">Gestión de caja</small>
                                                </div>
                                            </div>
                                            <label class="ios-switch">
                                                <input type="checkbox" class="modulo-switch" data-modulo="caja" id="switch_caja" <?php echo isset($permisos_actuales_map['caja']) ? 'checked' : ''; ?>>
                                                <span class="slider round"></span>
                                            </label>
                                        </div>
                                        <div class="subpermisos ps-4 mt-2" id="subpermisos_caja" style="display: <?php echo isset($permisos_actuales_map['caja']) ? 'block' : 'none'; ?>;">
                                            <div class="small text-muted mb-1">Submódulos:</div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="apertura_caja" id="perm_apertura_caja" <?php echo isset($permisos_actuales_map['apertura_caja']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_apertura_caja">Apertura de Caja</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="cierre_caja" id="perm_cierre_caja" <?php echo isset($permisos_actuales_map['cierre_caja']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_cierre_caja">Cierre de Caja</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ROPA (NUEVO) -->
                            <div class="col-md-6 col-xl-4">
                                <div class="card modulo-card h-100 border-0 shadow-sm rounded-4">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="module-icon p-2 rounded-3" style="background-color: rgba(236, 72, 153, 0.1);">
                                                    <span class="material-symbols-rounded" style="color: #ec4899;">checkroom</span>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold">Ropa</h6>
                                                    <small class="text-muted">Gestión de prendas</small>
                                                </div>
                                            </div>
                                            <label class="ios-switch">
                                                <input type="checkbox" class="modulo-switch" data-modulo="ropa" id="switch_ropa" <?php echo isset($permisos_actuales_map['ropa']) ? 'checked' : ''; ?>>
                                                <span class="slider round"></span>
                                            </label>
                                        </div>
                                        <div class="subpermisos ps-4 mt-2" id="subpermisos_ropa" style="display: <?php echo isset($permisos_actuales_map['ropa']) ? 'block' : 'none'; ?>;">
                                            <div class="small text-muted mb-1">Submódulos:</div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="gestion_ropa" id="perm_gestion_ropa" <?php echo isset($permisos_actuales_map['gestion_ropa']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_gestion_ropa">Productos</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="tipo_ropa" id="perm_tipo_ropa" <?php echo isset($permisos_actuales_map['tipo_ropa']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_tipo_ropa">Tipos</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="marcas" id="perm_marcas" <?php echo isset($permisos_actuales_map['marcas']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_marcas">Marcas</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="fabricantes" id="perm_fabricantes" <?php echo isset($permisos_actuales_map['fabricantes']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_fabricantes">Fabricantes</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="colores" id="perm_colores" <?php echo isset($permisos_actuales_map['colores']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_colores">Colores</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="tallas" id="perm_tallas" <?php echo isset($permisos_actuales_map['tallas']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_tallas">Tallas</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Administración -->
                            <div class="col-md-6 col-xl-4" id="modulo_administracion">
                                <div class="card modulo-card h-100 border-0 shadow-sm rounded-4">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="module-icon p-2 bg-dark bg-opacity-10 rounded-3">
                                                    <span class="material-symbols-rounded text-dark">manage_accounts</span>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold">Administración</h6>
                                                    <small class="text-muted">Configuración del sistema</small>
                                                </div>
                                            </div>
                                            <label class="ios-switch">
                                                <input type="checkbox" class="modulo-switch" data-modulo="administracion" id="switch_administracion" <?php echo isset($permisos_actuales_map['administracion']) ? 'checked' : ''; ?>>
                                                <span class="slider round"></span>
                                            </label>
                                        </div>
                                        <div class="subpermisos ps-4 mt-2" id="subpermisos_administracion" style="display: <?php echo isset($permisos_actuales_map['administracion']) ? 'block' : 'none'; ?>;">
                                            <div class="small text-muted mb-1">Submódulos:</div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="sucursales" id="perm_sucursales" <?php echo isset($permisos_actuales_map['sucursales']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_sucursales">Sucursales</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="empresa" id="perm_empresa" <?php echo isset($permisos_actuales_map['empresa']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_empresa">Empresa</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="usuarios" id="perm_usuarios" <?php echo isset($permisos_actuales_map['usuarios']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_usuarios">Usuarios</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="roles" id="perm_roles" <?php echo isset($permisos_actuales_map['roles']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_roles">Roles</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="permisos_usuarios" id="perm_permisos_usuarios" <?php echo isset($permisos_actuales_map['permisos_usuarios']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_permisos_usuarios">Permisos de Usuarios</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="desbloqueo_usuarios" id="perm_desbloqueo_usuarios" <?php echo isset($permisos_actuales_map['desbloqueo_usuarios']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_desbloquear_usuarios">Desbloqueo de Usuarios</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="sucursales" id="perm_sucursales" <?php echo isset($permisos_actuales_map['sucursales']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_configuracion">Configuración</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- SEGURIDAD (NUEVO) -->
                            <div class="col-md-6 col-xl-4">
                                <div class="card modulo-card h-100 border-0 shadow-sm rounded-4">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="module-icon p-2 rounded-3" style="background-color: rgba(239, 68, 68, 0.1);">
                                                    <span class="material-symbols-rounded" style="color: #ef4444;">security</span>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold">Seguridad</h6>
                                                    <small class="text-muted">Seguridad del sistema</small>
                                                </div>
                                            </div>
                                            <label class="ios-switch">
                                                <input type="checkbox" class="modulo-switch" data-modulo="seguridad" id="switch_seguridad" <?php echo isset($permisos_actuales_map['seguridad']) ? 'checked' : ''; ?>>
                                                <span class="slider round"></span>
                                            </label>
                                        </div>
                                        <div class="subpermisos ps-4 mt-2" id="subpermisos_seguridad" style="display: <?php echo isset($permisos_actuales_map['seguridad']) ? 'block' : 'none'; ?>;">
                                            <div class="small text-muted mb-1">Submódulos:</div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="sesiones" id="perm_sesiones" <?php echo isset($permisos_actuales_map['sesiones']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_sesiones">Sesiones Activas</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="auditoria" id="perm_auditoria" <?php echo isset($permisos_actuales_map['auditoria']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_auditoria">Auditoría</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="logs" id="perm_logs" <?php echo isset($permisos_actuales_map['logs']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_logs">Logs</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Reportes -->
                            <div class="col-md-6 col-xl-4">
                                <div class="card modulo-card h-100 border-0 shadow-sm rounded-4">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="module-icon p-2 bg-success bg-opacity-10 rounded-3">
                                                    <span class="material-symbols-rounded text-success">analytics</span>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold">Reportes</h6>
                                                    <small class="text-muted">Reportes del sistema</small>
                                                </div>
                                            </div>
                                            <label class="ios-switch">
                                                <input type="checkbox" class="modulo-switch" data-modulo="reportes" id="switch_reportes" <?php echo isset($permisos_actuales_map['reportes']) ? 'checked' : ''; ?>>
                                                <span class="slider round"></span>
                                            </label>
                                        </div>
                                        <div class="subpermisos ps-4 mt-2" id="subpermisos_reportes" style="display: <?php echo isset($permisos_actuales_map['reportes']) ? 'block' : 'none'; ?>;">
                                            <div class="small text-muted mb-1">Submódulos:</div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="reporte_ventas" id="perm_reporte_ventas" <?php echo isset($permisos_actuales_map['reporte_ventas']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_reporte_ventas">Reporte de Ventas</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="reporte_inventario" id="perm_reporte_inventario" <?php echo isset($permisos_actuales_map['reporte_inventario']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_reporte_inventario">Reporte de Inventario</label>
                                            </div>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input subpermiso-switch" type="checkbox" data-permiso="reporte_vencimientos" id="perm_reporte_vencimientos" <?php echo isset($permisos_actuales_map['reporte_vencimientos']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="perm_reporte_vencimientos">Reporte de Vencimientos</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* iOS Switch Styles */
.ios-switch {
    position: relative;
    display: inline-block;
    width: 51px;
    height: 31px;
}

.ios-switch input {
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
    transition: 0.3s;
    border-radius: 34px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 27px;
    width: 27px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

input:checked + .slider {
    background-color: #10b981;
}

input:checked + .slider:before {
    transform: translateX(20px);
}

input:focus + .slider {
    box-shadow: 0 0 1px #10b981;
}

input:disabled + .slider {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Subpermisos switches */
.form-switch .form-check-input {
    width: 2em;
    height: 1em;
    cursor: pointer;
}

.form-switch .form-check-input:checked {
    background-color: #10b981;
    border-color: #10b981;
}

.modulo-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border-radius: 16px !important;
    overflow: hidden;
}

.modulo-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1) !important;
}

.user-avatar-circle {
    transition: transform 0.3s ease;
}

.user-avatar-circle:hover {
    transform: scale(1.05);
}

.subpermisos {
    border-left: 2px solid #e5e7eb;
    margin-left: 8px;
    padding-left: 12px;
    transition: all 0.3s ease;
}

/* Tarjetas con border-radius consistente */
.card, .rounded-4 {
    border-radius: 16px !important;
}

.rounded-pill {
    border-radius: 50px !important;
}

.form-select, .form-control {
    border-radius: 12px !important;
}

/* Scroll personalizado */
#permisosContainer {
    max-height: calc(100vh - 280px);
    overflow-y: auto;
    padding-right: 5px;
}

#permisosContainer::-webkit-scrollbar {
    width: 6px;
}

#permisosContainer::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

#permisosContainer::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 10px;
}

#permisosContainer::-webkit-scrollbar-thumb:hover {
    background: #10b981;
}

/* Animación de carga */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.modulo-card {
    animation: fadeIn 0.3s ease-out;
}
</style>

<script>
// Estado actual de los permisos para detectar cambios
let estadoOriginal = {};

// Inicializar los event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar estado original
    inicializarEstadoOriginal();
    
    // Configurar event listeners para mostrar/ocultar submódulos
    document.querySelectorAll('.modulo-switch').forEach(sw => {
        if (!sw.disabled) {
            sw.addEventListener('change', function() {
                const card = this.closest('.modulo-card');
                const subpermisosDiv = card.querySelector('.subpermisos');
                if (subpermisosDiv) {
                    subpermisosDiv.style.display = this.checked ? 'block' : 'none';
                    if (!this.checked) {
                        const subSwitches = card.querySelectorAll('.subpermiso-switch');
                        subSwitches.forEach(sub => sub.checked = false);
                    }
                }
            });
        }
    });
});

function inicializarEstadoOriginal() {
    document.querySelectorAll('.modulo-switch').forEach(sw => {
        if (!sw.disabled) {
            estadoOriginal[sw.getAttribute('data-modulo')] = sw.checked;
        }
    });
    document.querySelectorAll('.subpermiso-switch').forEach(sw => {
        estadoOriginal[sw.getAttribute('data-permiso')] = sw.checked;
    });
}

function cambiarUsuario() {
    const select = document.getElementById('selectUsuario');
    const userId = select.value;
    window.location.href = 'menuprincipal.php?mod=permisos_usuarios&id_usuario=' + userId;
}

function toggleAllPermissions(checked) {
    const moduloSwitches = document.querySelectorAll('.modulo-switch');
    moduloSwitches.forEach(sw => {
        if (!sw.disabled) {
            sw.checked = checked;
            const card = sw.closest('.modulo-card');
            const subpermisosDiv = card.querySelector('.subpermisos');
            if (subpermisosDiv) {
                subpermisosDiv.style.display = checked ? 'block' : 'none';
                if (!checked) {
                    const subSwitches = card.querySelectorAll('.subpermiso-switch');
                    subSwitches.forEach(sub => sub.checked = false);
                }
            }
        }
    });
}

function cargarPermisosPorRol() {
    const select = document.getElementById('selectUsuario');
    const selectedOption = select.options[select.selectedIndex];
    const rol = selectedOption.getAttribute('data-rol');
    
    const permisosPorDefecto = {
        'Administrador': {
            'ventas': true, 'inventario': true, 'compras': true,
            'clientes': true, 'caja': true, 'administracion': true, 'reportes': true,
            'delivery': true, 'ropa': true, 'seguridad': true,
            'registrar_venta': true, 'historial_ventas': true, 'pagos': true, 'facturacion': true,
            'medicamentos': true, 'categorias': true, 'lotes': true, 'stock': true, 'vencimientos': true,
            'registrar_compra': true, 'historial_compras': true, 'proveedores': true,
            'clientes_lista': true, 'historial_cliente': true,
            'apertura_caja': true, 'cierre_caja': true,
            'usuarios': true, 'empresa': true, 'roles': true, 'permisos_usuarios': true, 'sucursales': true,
            'reporte_ventas': true, 'reporte_inventario': true, 'reporte_vencimientos': true,
            'repartidores': true, 'entregas': true, 'vehiculos': true, 'tracking': true, 'incidencias_delivery': true,
            'gestion_ropa': true, 'tipo_ropa': true, 'marcas': true, 'fabricantes': true, 'colores': true, 'tallas': true,
            'sesiones': true, 'auditoria': true, 'logs': true
        },
        'Cajero': {
            'ventas': true, 'clientes': true, 'caja': true,
            'inventario': false, 'compras': false, 'administracion': false, 'reportes': false,
            'delivery': false, 'ropa': false, 'seguridad': false,
            'registrar_venta': true, 'historial_ventas': true, 'pagos': true, 'facturacion': false,
            'clientes_lista': true, 'historial_cliente': true,
            'apertura_caja': true, 'cierre_caja': true
        },
        'Vendedor': {
            'ventas': true, 'clientes': true, 'inventario': true,
            'caja': false, 'compras': false, 'administracion': false, 'reportes': false,
            'delivery': false, 'ropa': false, 'seguridad': false,
            'registrar_venta': true, 'historial_ventas': true,
            'medicamentos': true, 'stock': true,
            'clientes_lista': true, 'historial_cliente': true
        },
        'Encargado Inventario': {
            'inventario': true, 'reportes': true,
            'ventas': false, 'clientes': false, 'caja': false, 'compras': false, 'administracion': false,
            'delivery': false, 'ropa': false, 'seguridad': false,
            'medicamentos': true, 'categorias': true, 'lotes': true, 'stock': true, 'vencimientos': true,
            'reporte_inventario': true, 'reporte_vencimientos': true
        },
        'Gestor Compras': {
            'compras': true, 'inventario': true, 'reportes': true,
            'ventas': false, 'clientes': false, 'caja': false, 'administracion': false,
            'delivery': false, 'ropa': false, 'seguridad': false,
            'registrar_compra': true, 'historial_compras': true, 'proveedores': true,
            'medicamentos': true, 'lotes': true, 'stock': true,
            'reporte_inventario': true
        },
        'Gestor Delivery': {
            'delivery': true, 'clientes': true, 'ventas': true,
            'inventario': false, 'compras': false, 'caja': false, 'administracion': false, 'reportes': false,
            'ropa': false, 'seguridad': false,
            'repartidores': true, 'entregas': true, 'vehiculos': true, 'tracking': true, 'incidencias_delivery': true,
            'clientes_lista': true, 'historial_cliente': true,
            'registrar_venta': true, 'historial_ventas': true
        },
        'Gestor Tienda (Ropa)': {
            'ropa': true, 'ventas': true, 'clientes': true, 'inventario': true,
            'compras': false, 'caja': false, 'delivery': false, 'administracion': false, 'reportes': false, 'seguridad': false,
            'gestion_ropa': true, 'tipo_ropa': true, 'marcas': true, 'fabricantes': true, 'colores': true, 'tallas': true,
            'registrar_venta': true, 'historial_ventas': true,
            'clientes_lista': true,
            'medicamentos': false, 'stock': true
        },
        'Auditor Seguridad': {
            'seguridad': true, 'reportes': true,
            'ventas': false, 'inventario': false, 'compras': false, 'clientes': false, 'caja': false, 'administracion': false,
            'delivery': false, 'ropa': false,
            'sesiones': true, 'auditoria': true, 'logs': true,
            'reporte_ventas': true, 'reporte_inventario': true
        }
    };
    
    const permisos = permisosPorDefecto[rol] || permisosPorDefecto['Cajero'];
    
    // Aplicar los permisos a los switches
    for (const [permiso, valor] of Object.entries(permisos)) {
        const switchElem = document.getElementById(`switch_${permiso}`);
        if (switchElem) {
            switchElem.checked = valor;
            const card = switchElem.closest('.modulo-card');
            const subpermisosDiv = card?.querySelector('.subpermisos');
            if (subpermisosDiv) {
                subpermisosDiv.style.display = valor ? 'block' : 'none';
            }
        }
        
        const subSwitch = document.querySelector(`.subpermiso-switch[data-permiso="${permiso}"]`);
        if (subSwitch) {
            subSwitch.checked = valor;
        }
    }
    
    Swal.fire({
        icon: 'success',
        title: 'Permisos cargados',
        text: `Se han cargado los permisos por defecto para el rol: ${rol}`,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Aceptar'
    });
}

function hayCambios() {
    const estadoActual = obtenerEstadoActual();
    return JSON.stringify(estadoOriginal) !== JSON.stringify(estadoActual);
}

function obtenerEstadoActual() {
    const estado = {};
    
    document.querySelectorAll('.modulo-switch').forEach(sw => {
        if (!sw.disabled) {
            estado[sw.getAttribute('data-modulo')] = sw.checked;
        }
    });
    
    document.querySelectorAll('.subpermiso-switch').forEach(sw => {
        estado[sw.getAttribute('data-permiso')] = sw.checked;
    });
    
    return estado;
}

// Procesar formulario con AJAX
document.getElementById('formPermisos').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!hayCambios()) {
        Swal.fire({
            icon: 'info',
            title: 'Sin cambios',
            text: 'No has realizado ningún cambio en los permisos. Realiza una modificación antes de guardar.',
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Aceptar'
        });
        return;
    }
    
    const userId = document.getElementById('id_usuario').value;
    const estadoActual = obtenerEstadoActual();
    
    Swal.fire({
        title: 'Guardando permisos...',
        text: 'Por favor espere',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        const response = await fetch('../backend/procesar_permisos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id_usuario: userId,
                permisos: estadoActual,
                accion: 'guardar_permisos'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            estadoOriginal = estadoActual;
            
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: result.message,
                confirmButtonColor: '#10b981',
                confirmButtonText: 'Aceptar'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: result.message,
                confirmButtonColor: '#d33'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Ocurrió un error al guardar los permisos',
            confirmButtonColor: '#d33'
        });
    }
});
</script>