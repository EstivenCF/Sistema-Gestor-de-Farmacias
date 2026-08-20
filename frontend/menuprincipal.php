<?php
session_start();
include '../backend/conexion.php';

if (!isset($_SESSION['id_sesion'])) {
    header("Location: index.php");
    exit();
}

// Asegurar que id_usuario esté en sesión
if (!isset($_SESSION['id_usuario']) && isset($_SESSION['usuario'])) {
    $stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE usuario = ?");
    $stmt->execute([$_SESSION['usuario']]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['id_usuario'] = $user['id_usuario'];
    }
}

// 🔎 VALIDAR SESIÓN EN BD
$sql = "SELECT * FROM sesiones 
        WHERE id_sesion = :id 
        AND activa = TRUE 
        AND fecha_expiracion > NOW()";

$stmt = $conexion->prepare($sql);
$stmt->execute([':id' => $_SESSION['id_sesion']]);

$sesion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sesion) {

    // 🔥 cerrar sesión en BD si está expirada
    if (isset($_SESSION['id_sesion'])) {
        $sqlClose = "UPDATE sesiones 
                     SET activa = FALSE, fecha_cierre = NOW() 
                     WHERE id_sesion = :id";

        $stmtClose = $conexion->prepare($sqlClose);
        $stmtClose->execute([':id' => $_SESSION['id_sesion']]);
    }

    session_destroy();

    header("Location: index.php?error=Sesión expirada");
    exit();
}

// Después de obtener $rol_usuario (ej: 'Administrador', 'Cajero', etc.)
// Definir la matriz de permisos de menú

$menu_por_rol = [
    'Administrador' => [
        'dashboard' => true,
        'ventas' => ['registrar_venta', 'historial_ventas', 'pagos'],
        'inventario' => ['medicamentos', 'categorias', 'presentaciones', 'laboratorios', 'principios_activos', 'lotes', 'stock', 'movimientos_inventario', 'vencimientos', 'alertas_stock', 'devoluciones', 'recall'],
        'compras' => ['registrar_compra', 'historial_compras', 'proveedores', 'recepcion'],
        'clientes' => ['clientes'],
        'delivery' => ['repartidores', 'entregas', 'vehiculos', 'tracking', 'incidencias_delivery'],
        'caja' => ['apertura_caja', 'cierre_caja', 'movimientos_caja'],
        'ropa' => ['gestion_ropa', 'tipo_ropa', 'marcas', 'fabricantes', 'colores', 'tallas'],
        'administracion' => ['sucursales', 'empresa', 'usuarios', 'consulta_usuarios', 'roles', 'permisos_usuarios', 'desbloquear_usuarios', 'configuracion', 'ofertas', 'seguros_medicos'],
        'seguridad' => ['sesiones', 'auditoria'], // ← Eliminado 'logs'
        'reportes' => ['reporte_ventas', 'reporte_inventario', 'reporte_vencimientos', 'rentabilidad_medicamentos', 'rotacion_productos']
    ],

    'Cajero' => [
        'dashboard' => true,
        'ventas' => ['registrar_venta', 'historial_ventas', 'pagos'],
        'clientes' => ['clientes'],
        'caja' => ['apertura_caja', 'cierre_caja', 'movimientos_caja'],
    ],

    'Vendedor' => [
        'dashboard' => true,
        'ventas' => ['registrar_venta', 'historial_ventas'],
        'inventario' => ['medicamentos', 'stock'],
        'clientes' => ['clientes'],
    ],

    'Encargado Inventario' => [
        'dashboard' => true,
        'inventario' => ['medicamentos', 'categorias', 'presentaciones', 'laboratorios', 'principios_activos', 'lotes', 'stock', 'movimientos_inventario', 'vencimientos', 'alertas_stock', 'devoluciones'],
        'reportes' => ['reporte_inventario', 'reporte_vencimientos'],
    ],

    'Gestor Compras' => [
        'dashboard' => true,
        'compras' => ['registrar_compra', 'historial_compras', 'proveedores', 'recepcion'],
        'inventario' => ['medicamentos', 'lotes', 'stock'],
        'reportes' => ['reporte_inventario'],
    ]
];

// Función para verificar si un submódulo es visible para el rol actual
function esModuloVisible($rol, $categoria, $submodulo)
{
    global $menu_por_rol;
    if (!isset($menu_por_rol[$rol])) return false;
    if (!isset($menu_por_rol[$rol][$categoria])) return false;
    return in_array($submodulo, $menu_por_rol[$rol][$categoria]);
}

// Función para verificar si el usuario tiene acceso al módulo
function tieneAccesoModulo($rol, $modulo)
{
    global $menu_por_rol;

    if ($modulo === 'dashboard' || $modulo === 'notificaciones') {
        return true;
    }

    if (!isset($menu_por_rol[$rol])) {
        return false;
    }

    foreach ($menu_por_rol[$rol] as $categoria => $permisos) {
        if (is_array($permisos) && in_array($modulo, $permisos)) {
            return true;
        }
    }

    return false;
}

// Consulta para obtener imagen_url, nombre real y el nombre del ROL
$queryUser = $conexion->prepare("
    SELECT u.imagen_url, u.nombre, r.nombre AS nombre_rol 
    FROM usuarios u 
    JOIN roles r ON u.id_rol = r.id_rol 
    WHERE u.usuario = ?
");
$queryUser->execute([$_SESSION['usuario']]);
$user_data = $queryUser->fetch(PDO::FETCH_ASSOC);

$foto_perfil = !empty($user_data['imagen_url']) ? "../" . $user_data['imagen_url'] : "../assets/img/usuarios/default.png";
$usuario_nombre_real = $user_data['nombre'] ?? $_SESSION['usuario'];
$rol_usuario = $user_data['nombre_rol'] ?? 'Sin Rol';

$usuario_nombre = $_SESSION['usuario'] ?? 'Usuario';

// --- NUEVA CONSULTA PARA LA IMAGEN ---
$queryUser = $conexion->prepare("SELECT imagen_url FROM usuarios WHERE usuario = ?");
$queryUser->execute([$usuario_nombre]);
$userData = $queryUser->fetch(PDO::FETCH_ASSOC);

$foto_perfil = ($userData && !empty($userData['imagen_url']))
    ? $userData['imagen_url']
    : '/sistema-gestor-de-farmacias/assets/img/usuarios/default';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSystem</title>
    <link rel="icon" type="image/x-icon" sizes="32x32" href="../assets/img/Icon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="stylemenuprincipal.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <?php
    $notificaciones = [];

    try {
        $stmt = $conexion->query("SELECT * FROM generar_alertas_inventario()");
        $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $notificaciones = [];
    }

    $total_notificaciones = count($notificaciones);

    $notificaciones_vencimiento = array_filter($notificaciones, function ($n) {
        return $n['tipo_alerta'] === 'VENCIMIENTO';
    });
    $notificaciones_stock_critico = array_filter($notificaciones, function ($n) {
        return $n['tipo_alerta'] === 'STOCK_CRITICO';
    });
    $notificaciones_stock_agotado = array_filter($notificaciones, function ($n) {
        return $n['tipo_alerta'] === 'STOCK_AGOTADO';
    });
    $notificaciones_recall = array_filter($notificaciones, function ($n) {
        return $n['tipo_alerta'] === 'RECALL';
    });
    ?>

    <nav class="top-navbar">
        <div class="navbar-left">
            <a href="menuprincipal.php?mod=dashboard" class="header-logo-nav">
                <img src="../assets/img/Icon.png" alt="logo_img">
                <span class="logo-text-nav">PharmaSystem</span>
            </a>
            <button id="toggleSidebar" class="btn-toggle-sidebar me-3">
                <span class="material-symbols-rounded">menu</span>
            </button>
        </div>

        <div class="navbar-right d-flex align-items-center gap-3">
            <div class="search-wrapper" id="searchWrapper">
                <div class="search-form-dynamic d-flex align-items-center">
                    <input type="text" id="searchInput" class="search-input-dynamic" placeholder="En que podemos ayudarte?" autocomplete="off">
                    <div id="searchResults" class="search-results"></div>
                    <button type="button" class="btn-nav-round search-toggle-btn" id="searchToggle">
                        <span class="material-symbols-rounded">search</span>
                    </button>
                </div>
            </div>

            <div class="notifications-dropdown">
                <button class="btn-nav-round position-relative" id="notifBell" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="material-symbols-rounded">notifications</span>
                    <?php if ($total_notificaciones > 0): ?>
                        <span class="badge-noti"><?php echo $total_notificaciones; ?></span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow notif-dropdown">
                    <li class="dropdown-header fw-bold d-flex justify-content-between align-items-center">
                        Notificaciones
                        <?php if ($total_notificaciones > 0): ?>
                            <span class="badge bg-danger rounded-pill"><?php echo $total_notificaciones; ?></span>
                        <?php endif; ?>
                    </li>

                    <?php if ($total_notificaciones === 0): ?>
                        <li>
                            <div class="notif-empty text-center">
                                <i class="fas fa-bell-slash"></i>
                                <p>No hay notificaciones</p>
                            </div>
                        </li>
                    <?php else: ?>

                        <?php foreach ($notificaciones_vencimiento as $n): ?>
                            <li>
                                <a class="dropdown-item notif-item" href="menuprincipal.php?mod=<?php echo $n['url_destino']; ?>">
                                    <div class="notif-icon bg-danger">
                                        <i class="fas fa-calendar-times"></i>
                                    </div>
                                    <div class="notif-content">
                                        <p class="notif-title">
                                            <?php echo htmlspecialchars($n['medicamento_nombre']); ?>
                                        </p>
                                        <span class="notif-sub">
                                            Lote: <?php echo htmlspecialchars($n['numero_lote']); ?> |
                                            Vence en <?php echo $n['dias_restantes']; ?> días
                                        </span>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>

                        <?php foreach ($notificaciones_stock_critico as $n): ?>
                            <li>
                                <a class="dropdown-item notif-item" href="menuprincipal.php?mod=<?php echo $n['url_destino']; ?>">
                                    <div class="notif-icon bg-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div class="notif-content">
                                        <p class="notif-title">
                                            <?php echo htmlspecialchars($n['medicamento_nombre']); ?>
                                        </p>
                                        <span class="notif-sub">
                                            Lote: <?php echo htmlspecialchars($n['numero_lote']); ?> |
                                            Stock: <?php echo $n['cantidad']; ?> / <?php echo $n['stock_minimo']; ?> und
                                        </span>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>

                        <?php foreach ($notificaciones_stock_agotado as $n): ?>
                            <li>
                                <a class="dropdown-item notif-item" href="menuprincipal.php?mod=<?php echo $n['url_destino']; ?>">
                                    <div class="notif-icon bg-dark">
                                        <i class="fas fa-box-open"></i>
                                    </div>
                                    <div class="notif-content">
                                        <p class="notif-title">
                                            <?php echo htmlspecialchars($n['medicamento_nombre']); ?>
                                        </p>
                                        <span class="notif-sub">
                                            Lote: <?php echo htmlspecialchars($n['numero_lote']); ?> |
                                            Stock AGOTADO
                                        </span>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>

                        <?php foreach ($notificaciones_recall as $n): ?>
                            <li>
                                <a class="dropdown-item notif-item" href="menuprincipal.php?mod=<?php echo $n['url_destino']; ?>">
                                    <div class="notif-icon bg-danger">
                                        <i class="fas fa-skull-crossbones"></i>
                                    </div>
                                    <div class="notif-content">
                                        <p class="notif-title">
                                            <?php echo htmlspecialchars(substr($n['medicamento_nombre'], 0, 50)); ?>...
                                        </p>
                                        <span class="notif-sub">
                                            Alerta: <?php echo htmlspecialchars($n['numero_alerta']); ?> |
                                            Riesgo: <?php echo $n['nivel_riesgo']; ?> |
                                            Entidad: <?php echo htmlspecialchars($n['entidad_emisora']); ?>
                                        </span>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>

                    <?php endif; ?>

                    <li class="dropdown-footer">
                        <a href="menuprincipal.php?mod=notificaciones" class="btn-ver-todas line">
                            Ver todas
                        </a>
                    </li>
                </ul>
            </div>
            <div class="dropdown">
                <div class="user-info-desktop d-flex align-items-center justify-content-center" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="fb-avatar-wrapper">
                        <img src="<?php echo $foto_perfil; ?>" alt="User" class="rounded-circle shadow-sm" style="width: 40px; height: 40px; object-fit: cover;">

                        <div class="fb-caret-circle">
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>
                    </div>
                </div>

                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 py-2" aria-labelledby="userDropdown" style="border-radius: 15px; min-width: 220px;">
                    <li class="px-3 py-2 border-bottom mb-2">
                        <div class="d-flex align-items-center">
                            <div class="me-2">
                                <img src="<?php echo $foto_perfil; ?>" alt="User" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                            </div>
                            <div>
                                <p class="mb-0 fw-bold" style="font-size: 0.9rem;"><?php echo htmlspecialchars($usuario_nombre_real); ?></p>
                                <small class="text-muted"><?php echo htmlspecialchars($rol_usuario); ?></small>
                            </div>
                        </div>
                    </li>

                    <!-- ⚠️ SOLO ADMINISTRADORES PUEDEN VER ESTAS OPCIONES -->
                    <?php if ($rol_usuario === 'Administrador'): ?>
                        <li>
                            <a class="dropdown-item d-flex align-items-center py-2" href="menuprincipal.php?mod=usuarios">
                                <span class="material-symbols-rounded me-2" style="font-size: 1.3rem;">manage_accounts</span>
                                Mi Usuario
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center py-2" href="menuprincipal.php?mod=permisos_usuarios">
                                <span class="material-symbols-rounded me-2" style="font-size: 1.3rem;">key</span>
                                Permisos
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                    <?php endif; ?>
                    <li>
                        <a class="dropdown-item d-flex align-items-center py-2 text-danger" href="javascript:void(0);" onclick="confirmLogout()">
                            <span class="material-symbols-rounded me-2" style="font-size: 1.3rem;">logout</span>
                            Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="navbar-center"></div>

    <div class="layout">
        <aside class="sidebar">
            <nav class="sidebar-nav">
                <ul class="nav-list primary-nav list-unstyled" id="sidebarAccordion">

                    <!-- Dashboard -->
                    <li class="nav-item">
                        <a href="menuprincipal.php?mod=dashboard" class="nav-link">
                            <span class="material-symbols-rounded">dashboard</span>
                            <span class="nav-label">Dashboard</span>
                        </a>
                    </li>

                    <!-- MÓDULO VENTAS -->
                    <?php if (!empty($menu_por_rol[$rol_usuario]['ventas'] ?? [])): ?>
                        <li class="nav-item has-submenu">
                            <a href="#submenuVentas" class="nav-link dropdown-toggle" data-bs-toggle="collapse" data-bs-target="#submenuVentas">
                                <span class="material-symbols-rounded">point_of_sale</span>
                                <span class="nav-label">Ventas</span>
                                <span class="material-symbols-rounded dropdown-arrow">expand_more</span>
                            </a>
                            <ul class="dropdown-menu collapse list-unstyled" id="submenuVentas" data-bs-parent="#sidebarAccordion">
                                <?php if (in_array('registrar_venta', $menu_por_rol[$rol_usuario]['ventas'])): ?>
                                    <li><a href="menuprincipal.php?mod=registrar_venta" class="nav-link dropdown-link"><span class="material-symbols-rounded">add_shopping_cart</span> Registrar Venta</a></li>
                                <?php endif; ?>
                                <?php if (in_array('historial_ventas', $menu_por_rol[$rol_usuario]['ventas'])): ?>
                                    <li><a href="menuprincipal.php?mod=historial_ventas" class="nav-link dropdown-link"><span class="material-symbols-rounded">history</span> Historial de Ventas</a></li>
                                <?php endif; ?>
                                <?php if (in_array('pagos', $menu_por_rol[$rol_usuario]['ventas'])): ?>
                                    <li><a href="menuprincipal.php?mod=pagos" class="nav-link dropdown-link"><span class="material-symbols-rounded">payments</span> Pagos</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- MÓDULO INVENTARIO -->
                    <?php if (!empty($menu_por_rol[$rol_usuario]['inventario'] ?? [])): ?>
                        <li class="nav-item has-submenu">
                            <a href="#submenuInventario" class="nav-link dropdown-toggle" data-bs-toggle="collapse" data-bs-target="#submenuInventario">
                                <span class="material-symbols-rounded">inventory_2</span>
                                <span class="nav-label">Inventario</span>
                                <span class="material-symbols-rounded dropdown-arrow">expand_more</span>
                            </a>
                            <ul class="dropdown-menu collapse list-unstyled" id="submenuInventario" data-bs-parent="#sidebarAccordion">
                                <?php if (in_array('medicamentos', $menu_por_rol[$rol_usuario]['inventario'])): ?>
                                    <li><a href="menuprincipal.php?mod=medicamentos" class="nav-link dropdown-link"><span class="material-symbols-rounded">medication</span> Medicamentos</a></li>
                                <?php endif; ?>
                                <?php if (in_array('categorias', $menu_por_rol[$rol_usuario]['inventario'])): ?>
                                    <li><a href="menuprincipal.php?mod=categorias" class="nav-link dropdown-link"><span class="material-symbols-rounded">category</span> Categorías</a></li>
                                <?php endif; ?>
                                <?php if (in_array('presentaciones', $menu_por_rol[$rol_usuario]['inventario'])): ?>
                                    <li><a href="menuprincipal.php?mod=presentaciones" class="nav-link dropdown-link"><span class="material-symbols-rounded">view_in_ar</span> Presentaciones</a></li>
                                <?php endif; ?>
                                <?php if (in_array('laboratorios', $menu_por_rol[$rol_usuario]['inventario'])): ?>
                                    <li><a href="menuprincipal.php?mod=laboratorios" class="nav-link dropdown-link"><span class="material-symbols-rounded">science</span> Laboratorios</a></li>
                                <?php endif; ?>
                                <?php if (in_array('lotes', $menu_por_rol[$rol_usuario]['inventario'])): ?>
                                    <li><a href="menuprincipal.php?mod=lotes" class="nav-link dropdown-link"><span class="material-symbols-rounded">inventory</span> Lotes</a></li>
                                <?php endif; ?>
                                <?php if (in_array('stock', $menu_por_rol[$rol_usuario]['inventario'])): ?>
                                    <li><a href="menuprincipal.php?mod=stock" class="nav-link dropdown-link"><span class="material-symbols-rounded">warehouse</span> Control de Stock</a></li>
                                <?php endif; ?>
                                <?php if (in_array('movimientos_inventario', $menu_por_rol[$rol_usuario]['inventario'])): ?>
                                    <li><a href="menuprincipal.php?mod=movimientos_inventario" class="nav-link dropdown-link"><span class="material-symbols-rounded">swap_horiz</span> Movimientos</a></li>
                                <?php endif; ?>
                                <?php if (in_array('vencimientos', $menu_por_rol[$rol_usuario]['inventario'])): ?>
                                    <li><a href="menuprincipal.php?mod=vencimientos" class="nav-link dropdown-link"><span class="material-symbols-rounded">event_busy</span> Vencimientos</a></li>
                                <?php endif; ?>
                                <?php if (in_array('alertas_stock', $menu_por_rol[$rol_usuario]['inventario'])): ?>
                                    <li><a href="menuprincipal.php?mod=alertas_stock" class="nav-link dropdown-link"><span class="material-symbols-rounded">warning</span> Alertas</a></li>
                                <?php endif; ?>
                                <?php if (in_array('devoluciones', $menu_por_rol[$rol_usuario]['inventario'])): ?>
                                    <li><a href="menuprincipal.php?mod=devoluciones" class="nav-link dropdown-link"><span class="material-symbols-rounded">assignment_return</span> Devoluciones</a></li>
                                <?php endif; ?>
                                <?php if (in_array('recall', $menu_por_rol[$rol_usuario]['inventario'])): ?>
                                    <li><a href="menuprincipal.php?mod=recall" class="nav-link dropdown-link"><span class="material-symbols-rounded">report</span> Retiros (Recall)</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- MÓDULO COMPRAS -->
                    <?php if (!empty($menu_por_rol[$rol_usuario]['compras'] ?? [])): ?>
                        <li class="nav-item has-submenu">
                            <a href="#submenuCompras" class="nav-link dropdown-toggle" data-bs-toggle="collapse" data-bs-target="#submenuCompras">
                                <span class="material-symbols-rounded">shopping_cart_checkout</span>
                                <span class="nav-label">Compras</span>
                                <span class="material-symbols-rounded dropdown-arrow">expand_more</span>
                            </a>
                            <ul class="dropdown-menu collapse list-unstyled" id="submenuCompras" data-bs-parent="#sidebarAccordion">
                                <?php if (in_array('registrar_compra', $menu_por_rol[$rol_usuario]['compras'])): ?>
                                    <li><a href="menuprincipal.php?mod=registrar_compra" class="nav-link dropdown-link"><span class="material-symbols-rounded">shopping_bag</span> Registrar Compra</a></li>
                                <?php endif; ?>
                                <?php if (in_array('historial_compras', $menu_por_rol[$rol_usuario]['compras'])): ?>
                                    <li><a href="menuprincipal.php?mod=historial_compras" class="nav-link dropdown-link"><span class="material-symbols-rounded">receipt_long</span> Historial de Compras</a></li>
                                <?php endif; ?>
                                <?php if (in_array('proveedores', $menu_por_rol[$rol_usuario]['compras'])): ?>
                                    <li><a href="menuprincipal.php?mod=proveedores" class="nav-link dropdown-link"><span class="material-symbols-rounded">badge</span> Proveedores</a></li>
                                <?php endif; ?>
                                <!-- ✅ NUEVO: Recepcion -->
                                <?php if (in_array('recepcion', $menu_por_rol[$rol_usuario]['compras'])): ?>
                                    <li><a href="menuprincipal.php?mod=recepcion" class="nav-link dropdown-link"><span class="material-symbols-rounded">inbox</span> Recepción</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- MÓDULO CLIENTES -->
                    <?php if (!empty($menu_por_rol[$rol_usuario]['clientes'] ?? [])): ?>
                        <li class="nav-item has-submenu">
                            <a href="#submenuClientes" class="nav-link dropdown-toggle" data-bs-toggle="collapse" data-bs-target="#submenuClientes">
                                <span class="material-symbols-rounded">groups</span>
                                <span class="nav-label">Clientes</span>
                                <span class="material-symbols-rounded dropdown-arrow">expand_more</span>
                            </a>
                            <ul class="dropdown-menu collapse list-unstyled" id="submenuClientes" data-bs-parent="#sidebarAccordion">
                                <?php if (in_array('clientes', $menu_por_rol[$rol_usuario]['clientes'])): ?>
                                    <li><a href="menuprincipal.php?mod=clientes" class="nav-link dropdown-link"><span class="material-symbols-rounded">person</span> Lista de Clientes</a></li>
                                <?php endif; ?>
                                <!-- Opción "Historial" eliminada -->
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- MÓDULO DELIVERY (SOLO ADMIN) -->
                    <?php if (!empty($menu_por_rol[$rol_usuario]['delivery'] ?? [])): ?>
                        <li class="nav-item has-submenu">
                            <a href="#submenuDelivery" class="nav-link dropdown-toggle" data-bs-toggle="collapse" data-bs-target="#submenuDelivery">
                                <span class="material-symbols-rounded">local_shipping</span>
                                <span class="nav-label">Envíos</span>
                                <span class="material-symbols-rounded dropdown-arrow">expand_more</span>
                            </a>
                            <ul class="dropdown-menu collapse list-unstyled" id="submenuDelivery" data-bs-parent="#sidebarAccordion">
                                <?php if (in_array('repartidores', $menu_por_rol[$rol_usuario]['delivery'])): ?>
                                    <li><a href="menuprincipal.php?mod=repartidores" class="nav-link dropdown-link"><span class="material-symbols-rounded">person</span> Repartidores</a></li>
                                <?php endif; ?>
                                <?php if (in_array('entregas', $menu_por_rol[$rol_usuario]['delivery'])): ?>
                                    <li><a href="menuprincipal.php?mod=entregas" class="nav-link dropdown-link"><span class="material-symbols-rounded">inventory_2</span> Entregas</a></li>
                                <?php endif; ?>
                                <?php if (in_array('vehiculos', $menu_por_rol[$rol_usuario]['delivery'])): ?>
                                    <li><a href="menuprincipal.php?mod=vehiculos" class="nav-link dropdown-link"><span class="material-symbols-rounded">directions_car</span> Vehículos</a></li>
                                <?php endif; ?>
                                <?php if (in_array('tracking', $menu_por_rol[$rol_usuario]['delivery'])): ?>
                                    <li><a href="menuprincipal.php?mod=tracking" class="nav-link dropdown-link"><span class="material-symbols-rounded">location_on</span> Tracking</a></li>
                                <?php endif; ?>
                                <?php if (in_array('incidencias_delivery', $menu_por_rol[$rol_usuario]['delivery'])): ?>
                                    <li><a href="menuprincipal.php?mod=incidencias_delivery" class="nav-link dropdown-link"><span class="material-symbols-rounded">report_problem</span> Incidencias</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- MÓDULO CAJA -->
                    <?php if (!empty($menu_por_rol[$rol_usuario]['caja'] ?? [])): ?>
                        <li class="nav-item has-submenu">
                            <a href="#submenuCaja" class="nav-link dropdown-toggle" data-bs-toggle="collapse" data-bs-target="#submenuCaja">
                                <span class="material-symbols-rounded">payments</span>
                                <span class="nav-label">Caja</span>
                                <span class="material-symbols-rounded dropdown-arrow">expand_more</span>
                            </a>
                            <ul class="dropdown-menu collapse list-unstyled" id="submenuCaja" data-bs-parent="#sidebarAccordion">
                                <?php if (in_array('apertura_caja', $menu_por_rol[$rol_usuario]['caja'])): ?>
                                    <li><a href="menuprincipal.php?mod=apertura_caja" class="nav-link dropdown-link"><span class="material-symbols-rounded">lock_open</span> Apertura</a></li>
                                <?php endif; ?>
                                <?php if (in_array('cierre_caja', $menu_por_rol[$rol_usuario]['caja'])): ?>
                                    <li><a href="menuprincipal.php?mod=cierre_caja" class="nav-link dropdown-link"><span class="material-symbols-rounded">lock</span> Cierre</a></li>
                                <?php endif; ?>
                                <?php if (in_array('movimientos_caja', $menu_por_rol[$rol_usuario]['caja'])): ?>
                                    <li><a href="menuprincipal.php?mod=movimientos_caja" class="nav-link dropdown-link"><span class="material-symbols-rounded">sync_alt</span> Movimientos</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- MÓDULO ROPA (SOLO ADMIN) -->
                    <?php if (!empty($menu_por_rol[$rol_usuario]['ropa'] ?? [])): ?>
                        <li class="nav-item has-submenu">
                            <a href="#submenuRopa" class="nav-link dropdown-toggle" data-bs-toggle="collapse" data-bs-target="#submenuRopa">
                                <span class="material-symbols-rounded">checkroom</span>
                                <span class="nav-label">Ropa</span>
                                <span class="material-symbols-rounded dropdown-arrow">expand_more</span>
                            </a>
                            <ul class="dropdown-menu collapse list-unstyled" id="submenuRopa" data-bs-parent="#sidebarAccordion">
                                <?php if (in_array('gestion_ropa', $menu_por_rol[$rol_usuario]['ropa'])): ?>
                                    <li><a href="menuprincipal.php?mod=gestion_ropa" class="nav-link dropdown-link"><span class="material-symbols-rounded">apparel</span> Productos</a></li>
                                <?php endif; ?>
                                <?php if (in_array('tipo_ropa', $menu_por_rol[$rol_usuario]['ropa'])): ?>
                                    <li><a href="menuprincipal.php?mod=tipo_ropa" class="nav-link dropdown-link"><span class="material-symbols-rounded">style</span> Tipos</a></li>
                                <?php endif; ?>
                                <?php if (in_array('marcas', $menu_por_rol[$rol_usuario]['ropa'])): ?>
                                    <li><a href="menuprincipal.php?mod=marcas" class="nav-link dropdown-link"><span class="material-symbols-rounded">sell</span> Marcas</a></li>
                                <?php endif; ?>
                                <?php if (in_array('fabricantes', $menu_por_rol[$rol_usuario]['ropa'])): ?>
                                    <li><a href="menuprincipal.php?mod=fabricantes" class="nav-link dropdown-link"><span class="material-symbols-rounded">factory</span> Fabricantes</a></li>
                                <?php endif; ?>
                                <?php if (in_array('colores', $menu_por_rol[$rol_usuario]['ropa'])): ?>
                                    <li><a href="menuprincipal.php?mod=colores" class="nav-link dropdown-link"><span class="material-symbols-rounded">palette</span> Colores</a></li>
                                <?php endif; ?>
                                <?php if (in_array('tallas', $menu_por_rol[$rol_usuario]['ropa'])): ?>
                                    <li><a href="menuprincipal.php?mod=tallas" class="nav-link dropdown-link"><span class="material-symbols-rounded">straighten</span> Tallas</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- MÓDULO ADMINISTRACIÓN (SOLO ADMIN) -->
                    <?php if (!empty($menu_por_rol[$rol_usuario]['administracion'] ?? [])): ?>
                        <li class="nav-item has-submenu">
                            <a href="#submenuAdmin" class="nav-link dropdown-toggle" data-bs-toggle="collapse" data-bs-target="#submenuAdmin">
                                <span class="material-symbols-rounded">manage_accounts</span>
                                <span class="nav-label">Administración</span>
                                <span class="material-symbols-rounded dropdown-arrow">expand_more</span>
                            </a>
                            <ul class="dropdown-menu collapse list-unstyled" id="submenuAdmin" data-bs-parent="#sidebarAccordion">
                                <?php if (in_array('sucursales', $menu_por_rol[$rol_usuario]['administracion'])): ?>
                                    <li><a href="menuprincipal.php?mod=sucursales" class="nav-link dropdown-link"><span class="material-symbols-rounded">store</span> Sucursales</a></li>
                                <?php endif; ?>
                                <?php if (in_array('empresa', $menu_por_rol[$rol_usuario]['administracion'])): ?>
                                    <li><a href="menuprincipal.php?mod=empresa" class="nav-link dropdown-link"><span class="material-symbols-rounded">business</span> Empresa</a></li>
                                <?php endif; ?>
                                <?php if (in_array('usuarios', $menu_por_rol[$rol_usuario]['administracion'])): ?>
                                    <li><a href="menuprincipal.php?mod=usuarios" class="nav-link dropdown-link"><span class="material-symbols-rounded">group</span> Usuarios</a></li>
                                <?php endif; ?>
                                <?php if (in_array('roles', $menu_por_rol[$rol_usuario]['administracion'])): ?>
                                    <li><a href="menuprincipal.php?mod=roles" class="nav-link dropdown-link"><span class="material-symbols-rounded">admin_panel_settings</span> Roles</a></li>
                                <?php endif; ?>
                                <?php if (in_array('permisos_usuarios', $menu_por_rol[$rol_usuario]['administracion'])): ?>
                                    <li><a href="menuprincipal.php?mod=permisos_usuarios" class="nav-link dropdown-link"><span class="material-symbols-rounded">shield_person</span> Permisos de Usuarios</a></li>
                                <?php endif; ?>
                                <?php if (in_array('desbloquear_usuarios', $menu_por_rol[$rol_usuario]['administracion'])): ?>
                                    <li><a href="menuprincipal.php?mod=desbloquear_usuarios" class="nav-link dropdown-link"><span class="material-symbols-rounded">lock_open</span> Desbloqueo de Usuarios</a></li>
                                <?php endif; ?>
                                <?php if (in_array('ofertas', $menu_por_rol[$rol_usuario]['administracion'])): ?>
                                    <li><a href="menuprincipal.php?mod=ofertas" class="nav-link dropdown-link"><span class="material-symbols-rounded">local_offer</span> Ofertas</a></li>
                                <?php endif; ?>
                                <?php if (in_array('seguros_medicos', $menu_por_rol[$rol_usuario]['administracion'])): ?>
                                    <li><a href="menuprincipal.php?mod=seguros_medicos" class="nav-link dropdown-link"><span class="material-symbols-rounded">health_and_safety</span> Seguros Médicos</a></li>
                                <?php endif; ?>
                                <?php if (in_array('configuracion', $menu_por_rol[$rol_usuario]['administracion'])): ?>
                                    <li><a href="menuprincipal.php?mod=configuracion" class="nav-link dropdown-link"><span class="material-symbols-rounded">settings</span> Configuración</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- MÓDULO SEGURIDAD (SOLO ADMIN) -->
                    <?php if (!empty($menu_por_rol[$rol_usuario]['seguridad'] ?? [])): ?>
                        <li class="nav-item has-submenu">
                            <a href="#submenuSeguridad" class="nav-link dropdown-toggle" data-bs-toggle="collapse" data-bs-target="#submenuSeguridad">
                                <span class="material-symbols-rounded">security</span>
                                <span class="nav-label">Seguridad</span>
                                <span class="material-symbols-rounded dropdown-arrow">expand_more</span>
                            </a>
                            <ul class="dropdown-menu collapse list-unstyled" id="submenuSeguridad" data-bs-parent="#sidebarAccordion">
                                <?php if (in_array('sesiones', $menu_por_rol[$rol_usuario]['seguridad'])): ?>
                                    <li><a href="menuprincipal.php?mod=sesiones" class="nav-link dropdown-link"><span class="material-symbols-rounded">vpn_key</span> Sesiones Activas</a></li>
                                <?php endif; ?>
                                <?php if (in_array('auditoria', $menu_por_rol[$rol_usuario]['seguridad'])): ?>
                                    <li><a href="menuprincipal.php?mod=auditoria" class="nav-link dropdown-link"><span class="material-symbols-rounded">policy</span> Auditoría</a></li>
                                <?php endif; ?>
                                <!-- El enlace a Logs ha sido eliminado -->
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- MÓDULO REPORTES -->
                    <?php if (!empty($menu_por_rol[$rol_usuario]['reportes'] ?? [])): ?>
                        <li class="nav-item has-submenu">
                            <a href="#submenuReportes" class="nav-link dropdown-toggle" data-bs-toggle="collapse" data-bs-target="#submenuReportes">
                                <span class="material-symbols-rounded">analytics</span>
                                <span class="nav-label">Reportes</span>
                                <span class="material-symbols-rounded dropdown-arrow">expand_more</span>
                            </a>
                            <ul class="dropdown-menu collapse list-unstyled" id="submenuReportes" data-bs-parent="#sidebarAccordion">
                                <?php if (in_array('reporte_ventas', $menu_por_rol[$rol_usuario]['reportes'])): ?>
                                    <li><a href="menuprincipal.php?mod=reporte_ventas" class="nav-link dropdown-link"><span class="material-symbols-rounded">bar_chart</span> Ventas</a></li>
                                <?php endif; ?>
                                <?php if (in_array('reporte_inventario', $menu_por_rol[$rol_usuario]['reportes'])): ?>
                                    <li><a href="menuprincipal.php?mod=reporte_inventario" class="nav-link dropdown-link"><span class="material-symbols-rounded">inventory</span> Inventario</a></li>
                                <?php endif; ?>
                                <?php if (in_array('reporte_vencimientos', $menu_por_rol[$rol_usuario]['reportes'])): ?>
                                    <li><a href="menuprincipal.php?mod=reporte_vencimientos" class="nav-link dropdown-link"><span class="material-symbols-rounded">notification_important</span> Vencimientos</a></li>
                                <?php endif; ?>
                                <?php if (in_array('rentabilidad_medicamentos', $menu_por_rol[$rol_usuario]['reportes'])): ?>
                                    <li><a href="menuprincipal.php?mod=rentabilidad_medicamentos" class="nav-link dropdown-link"><span class="material-symbols-rounded">monetization_on</span> Rentabilidad</a></li>
                                <?php endif; ?>
                                <?php if (in_array('rotacion_productos', $menu_por_rol[$rol_usuario]['reportes'])): ?>
                                    <li><a href="menuprincipal.php?mod=rotacion_productos" class="nav-link dropdown-link"><span class="material-symbols-rounded">published_with_changes</span> Rotación</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                </ul>

                <div class="logout-container">
                    <a href="javascript:void(0)" class="nav-link logout-link" onclick="confirmLogout()">
                        <span class="material-symbols-rounded">logout</span>
                        <span class="nav-label">Cerrar Sesión</span>
                    </a>
                </div>
            </nav>
        </aside>

        <div class="main-content">
            <div class="content-area p-3">
                <?php
                // Sistema de carga de módulos unificado
                if (isset($_GET['mod'])) {
                    $mod = $_GET['mod'];
                    $found = false;
                    $folders = ['ventas', 'inventario', 'compras', 'clientes', 'delivery', 'caja', 'ropa', 'administracion', 'seguridad', 'reportes'];

                    if ($mod == 'dashboard') {
                        include 'dashboard.php';
                        $found = true;
                    } elseif ($mod == 'notificaciones') {
                        if (file_exists("notificaciones.php")) {
                            include 'notificaciones.php';
                            $found = true;
                        }
                    } else {
                        // 🔐 VALIDACIÓN DE ACCESO ANTES DE INCLUIR EL MÓDULO
                        if (!tieneAccesoModulo($rol_usuario, $mod)) {
                            echo "<div class='alert alert-danger m-4'>
                                    <span class='material-symbols-rounded me-2'>warning</span>
                                    <strong>Acceso denegado:</strong> No tienes permisos para acceder a este módulo.
                                </div>";
                            $found = true;
                        } else {
                            foreach ($folders as $folder) {
                                if (file_exists("$folder/$mod.php")) {
                                    include "$folder/$mod.php";
                                    $found = true;
                                    break;
                                }
                            }
                        }
                    }

                    if (!$found) {
                        echo "<h2 class='alert alert-danger'>Módulo '$mod' no encontrado</h2>";
                    }
                } else {
                    include 'dashboard.php';
                }
                ?>
            </div>
        </div>
    </div>

    <script>
        function confirmLogout() {
            Swal.fire({
                title: "¿Estás seguro?",
                text: "¿Realmente deseas cerrar tu sesión?",
                icon: "warning",
                showCancelButton: true,
                reverseButtons: true,
                confirmButtonColor: "#dc3545",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "Confirmar",
                cancelButtonText: "Cancelar"
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "../backend/cerrar_sesion.php";
                }
            });
        }

        function marcarNotificacionLeida(idNotificacion) {
            fetch('../backend/marcar_notificacion_leida.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id_notificacion: idNotificacion
                })
            });
        }

        function limpiarNotificaciones() {
            Swal.fire({
                title: "¿Limpiar todas las notificaciones?",
                text: "Esta acción marcará todas las notificaciones como leídas",
                icon: "question",
                showCancelButton: true,
                confirmButtonColor: "#28a745",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "Sí, limpiar",
                cancelButtonText: "Cancelar"
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('../backend/limpiar_notificaciones.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Notificaciones limpiadas',
                                    text: 'Todas las notificaciones han sido marcadas como leídas',
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error', 'No se pudieron limpiar las notificaciones', 'error');
                            }
                        })
                        .catch(error => {
                            Swal.fire('Error', 'Error de conexión', 'error');
                        });
                }
            });
        }

        // 🚀 ATAJO DE TECLADO: Ctrl + Shift + S para cerrar sesión
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'S' || e.key === 's')) {
                e.preventDefault();
                confirmLogout();
            }
        });
    </script>

    <script src="scriptmenuprincipal.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        // Forzar que el sidebar siempre esté expandido
        (function() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            if (sidebar && mainContent) {
                // Remover clase collapsed
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('sidebar-collapsed');
                // Guardar estado expandido en localStorage
                localStorage.setItem('sidebarStatus', 'expanded');
            }
        })();
    </script>
</body>

</html>