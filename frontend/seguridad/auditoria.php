<?php
// auditoria.php - Módulo de auditoría del sistema (sin backdrop)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../backend/conexion.php';

if (!isset($_SESSION['id_sesion']) || !isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener filtros
$modulo = $_GET['modulo'] ?? '';
$usuario = $_GET['usuario'] ?? '';
$accion = $_GET['accion'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Paginación real: antes se traían siempre los últimos 500 registros y
// punto — si había más, los más viejos simplemente dejaban de aparecer
// sin ningún aviso. Ahora se pagina de verdad y se muestra el total real.
$porPagina = 50;
$pagina = max(1, intval($_GET['pagina'] ?? 1));

// Construir la condición WHERE una sola vez, para usarla igual en el
// conteo total, en la lista paginada, y en las tarjetas de resumen (antes
// las tarjetas siempre mostraban el total global sin importar los filtros
// que se hubieran aplicado en el formulario de arriba).
$where = " WHERE 1=1";
$params = [];

if (!empty($modulo)) {
    $where .= " AND a.tabla_afectada = :modulo";
    $params[':modulo'] = $modulo;
}
if (!empty($usuario)) {
    $where .= " AND a.id_usuario = :usuario";
    $params[':usuario'] = $usuario;
}
if (!empty($accion)) {
    $where .= " AND a.accion = :accion";
    $params[':accion'] = $accion;
}
if (!empty($fecha_desde)) {
    $where .= " AND a.fecha >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde . ' 00:00:00';
}
if (!empty($fecha_hasta)) {
    $where .= " AND a.fecha <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta . ' 23:59:59';
}

// Total de registros que cumplen el filtro, para calcular las páginas
$stmtTotal = $conexion->prepare("SELECT COUNT(*) FROM auditoria_cambios a" . $where);
$stmtTotal->execute($params);
$totalRegistros = (int) $stmtTotal->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

$sql = "SELECT a.*, u.nombre as usuario_nombre, u.usuario as usuario_login
        FROM auditoria_cambios a
        LEFT JOIN usuarios u ON a.id_usuario = u.id_usuario"
        . $where .
        " ORDER BY a.fecha DESC LIMIT :limite OFFSET :desplazamiento";

$stmt = $conexion->prepare($sql);
foreach ($params as $llave => $valor) {
    $stmt->bindValue($llave, $valor);
}
$stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
$stmt->bindValue(':desplazamiento', $offset, PDO::PARAM_INT);
$stmt->execute();
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de módulos únicos para el filtro (todas las opciones
// posibles, sin filtrar — son las opciones del selector, no el resultado)
$stmtMod = $conexion->query("SELECT DISTINCT tabla_afectada FROM auditoria_cambios ORDER BY tabla_afectada");
$modulos = $stmtMod->fetchAll(PDO::FETCH_COLUMN);

// Obtener lista de usuarios para filtro (tampoco se filtra, son opciones)
$stmtUsu = $conexion->query("SELECT DISTINCT u.id_usuario, u.nombre FROM auditoria_cambios a JOIN usuarios u ON a.id_usuario = u.id_usuario ORDER BY u.nombre");
$usuariosFiltro = $stmtUsu->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas por módulo — ahora SÍ respetan los filtros aplicados arriba
$stmtStats = $conexion->prepare("
    SELECT tabla_afectada, COUNT(*) as total, 
           SUM(CASE WHEN accion = 'INSERT' THEN 1 ELSE 0 END) as inserts,
           SUM(CASE WHEN accion = 'UPDATE' THEN 1 ELSE 0 END) as updates,
           SUM(CASE WHEN accion = 'DELETE' THEN 1 ELSE 0 END) as deletes
    FROM auditoria_cambios a" . $where . "
    GROUP BY tabla_afectada
    ORDER BY total DESC
");
$stmtStats->execute($params);
$stats = $stmtStats->fetchAll(PDO::FETCH_ASSOC);

// Para armar los links de paginación conservando los filtros actuales
function urlAuditoriaPagina($numPagina) {
    $qs = $_GET;
    $qs['pagina'] = $numPagina;
    return '?' . http_build_query($qs);
}

// ── Catálogos pequeños para poder mostrar la auditoría de permisos
//    en español legible en vez de JSON crudo con solo IDs ──
$stmtCatPermisos = $conexion->query("SELECT id_permiso, nombre FROM permisos");
$catalogoPermisos = [];
foreach ($stmtCatPermisos->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $catalogoPermisos[$p['id_permiso']] = $p['nombre'];
}

$stmtCatUsuarios = $conexion->query("SELECT id_usuario, nombre FROM usuarios");
$catalogoUsuarios = [];
foreach ($stmtCatUsuarios->fetchAll(PDO::FETCH_ASSOC) as $u) {
    $catalogoUsuarios[$u['id_usuario']] = $u['nombre'];
}

$stmtCatRoles = $conexion->query("SELECT id_rol, nombre FROM roles");
$catalogoRoles = [];
foreach ($stmtCatRoles->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $catalogoRoles[$r['id_rol']] = $r['nombre'];
}

$stmtCatModulos = $conexion->query("SELECT id_modulo, nombre FROM modulos");
$catalogoModulos = [];
foreach ($stmtCatModulos->fetchAll(PDO::FETCH_ASSOC) as $m) {
    $catalogoModulos[$m['id_modulo']] = $m['nombre'];
}

// ── Nombre amigable y módulo del sistema al que pertenece cada tabla
//    auditada, para que la pantalla diga "Módulo: Delivery — Entregas"
//    en vez de solo el nombre técnico de la tabla en la base de datos.
$nombresTabla = [
    'usuarios' => 'Usuarios', 'roles' => 'Roles', 'permisos' => 'Catálogo de Permisos',
    'rol_permiso' => 'Permisos por Rol', 'usuario_permiso' => 'Permisos por Usuario',
    'configuracion_sistema' => 'Configuración del Sistema', 'sucursales' => 'Sucursales',
    'medicamentos' => 'Medicamentos', 'productos' => 'Productos', 'lotes' => 'Lotes',
    'inventario' => 'Inventario (medicamentos)', 'inventario_productos' => 'Inventario (productos)',
    'movimiento_inventario' => 'Movimientos de Inventario',
    'ventas' => 'Ventas', 'detalle_venta' => 'Detalle de Venta', 'pagos' => 'Pagos',
    'abonos_credito' => 'Abonos a Crédito', 'descuentos' => 'Descuentos', 'cupones' => 'Cupones',
    'compras' => 'Compras', 'detalle_compra' => 'Detalle de Compra', 'proveedores' => 'Proveedores',
    'clientes' => 'Clientes',
    'repartidores' => 'Repartidores', 'vehiculos' => 'Vehículos', 'entregas' => 'Entregas',
    'devoluciones' => 'Devoluciones', 'tarifas_envio' => 'Tarifas de Envío',
    'caja' => 'Caja', 'movimiento_caja' => 'Movimientos de Caja',
    'calificaciones_entrega' => 'Calificaciones de Clientes', 'preguntas_calificacion' => 'Preguntas de Calificación',
    'categorias_calificacion' => 'Secciones de Calificación', 'respuestas_calificacion' => 'Respuestas de Calificación',
    'despacho_entrega' => 'Despacho de Entregas', 'detalle_despacho' => 'Detalle de Despacho',
    'conciliacion_entrega' => 'Conciliación de Entregas', 'detalle_conciliacion' => 'Detalle de Conciliación',
    'incidencias_entrega' => 'Incidencias de Entrega',
    'limites_credito_cliente' => 'Límites de Crédito', 'configuracion_credito' => 'Configuración de Crédito',
    'polizas' => 'Pólizas de Seguro', 'autorizaciones_seguro' => 'Autorizaciones de Seguro',
    'ordenes_compra' => 'Órdenes de Compra', 'detalle_orden_compra' => 'Detalle de Orden de Compra',
];
$modulosPorTabla = [
    'usuarios' => 'Seguridad', 'roles' => 'Seguridad', 'permisos' => 'Seguridad',
    'rol_permiso' => 'Seguridad', 'usuario_permiso' => 'Seguridad',
    'configuracion_sistema' => 'Administración', 'sucursales' => 'Administración',
    'medicamentos' => 'Inventario', 'productos' => 'Inventario', 'lotes' => 'Inventario',
    'inventario' => 'Inventario', 'inventario_productos' => 'Inventario', 'movimiento_inventario' => 'Inventario',
    'ventas' => 'Ventas', 'detalle_venta' => 'Ventas', 'pagos' => 'Ventas',
    'abonos_credito' => 'Ventas', 'descuentos' => 'Ventas', 'cupones' => 'Ventas',
    'compras' => 'Compras', 'detalle_compra' => 'Compras', 'proveedores' => 'Compras',
    'clientes' => 'Clientes',
    'repartidores' => 'Envíos', 'vehiculos' => 'Envíos', 'entregas' => 'Envíos',
    'devoluciones' => 'Envíos', 'tarifas_envio' => 'Envíos',
    'caja' => 'Caja', 'movimiento_caja' => 'Caja',
    'calificaciones_entrega' => 'Envíos', 'preguntas_calificacion' => 'Envíos',
    'categorias_calificacion' => 'Envíos', 'respuestas_calificacion' => 'Envíos',
    'despacho_entrega' => 'Envíos', 'detalle_despacho' => 'Envíos',
    'conciliacion_entrega' => 'Envíos', 'detalle_conciliacion' => 'Envíos',
    'incidencias_entrega' => 'Envíos',
    'limites_credito_cliente' => 'Clientes', 'configuracion_credito' => 'Clientes',
    'polizas' => 'Seguros', 'autorizaciones_seguro' => 'Seguros',
    'ordenes_compra' => 'Compras', 'detalle_orden_compra' => 'Compras',
];
?>

<script>
    // Catálogos para traducir IDs a nombres legibles en el detalle de auditoría
    const CATALOGO_PERMISOS = <?php echo json_encode($catalogoPermisos, JSON_UNESCAPED_UNICODE); ?>;
    const CATALOGO_USUARIOS = <?php echo json_encode($catalogoUsuarios, JSON_UNESCAPED_UNICODE); ?>;
    const CATALOGO_ROLES = <?php echo json_encode($catalogoRoles, JSON_UNESCAPED_UNICODE); ?>;
    const CATALOGO_MODULOS = <?php echo json_encode($catalogoModulos, JSON_UNESCAPED_UNICODE); ?>;
    const NOMBRES_TABLA = <?php echo json_encode($nombresTabla, JSON_UNESCAPED_UNICODE); ?>;
    const MODULOS_POR_TABLA = <?php echo json_encode($modulosPorTabla, JSON_UNESCAPED_UNICODE); ?>;
</script>

<div class="auditoria-container">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
        <h2 class="h3">
            <span class="material-symbols-rounded align-middle">policy</span>
            Auditoría de Cambios
        </h2>
        <button class="btn btn-outline-secondary btn-sm" onclick="window.location.reload()">
            <span class="material-symbols-rounded fs-6">refresh</span> Refrescar
        </button>
    </div>

    <!-- Tarjetas de resumen por módulo -->
    <div class="row g-3 mb-4">
        <?php foreach ($stats as $stat): ?>
            <div class="col-md-3 col-sm-6">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge bg-primary mb-2"><?php echo strtoupper($stat['tabla_afectada']); ?></span>
                                <h5 class="card-title mb-0"><?php echo $stat['total']; ?> cambios</h5>
                            </div>
                            <span class="material-symbols-rounded text-muted">database</span>
                        </div>
                        <div class="mt-2 small text-muted">
                            <span class="text-success">+<?php echo $stat['inserts']; ?> ins</span> |
                            <span class="text-warning">~<?php echo $stat['updates']; ?> upd</span> |
                            <span class="text-danger">-<?php echo $stat['deletes']; ?> del</span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($stats)): ?>
            <div class="col-12">
                <div class="alert alert-info">No hay registros de auditoría aún. Los triggers se activarán con cambios en los datos.</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="mod" value="auditoria">
                <div class="col-md-3">
                    <label class="form-label small">Módulo (Tabla)</label>
                    <select name="modulo" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($modulos as $m): ?>
                            <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $modulo == $m ? 'selected' : ''; ?>><?php echo htmlspecialchars($m); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Usuario</label>
                    <select name="usuario" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($usuariosFiltro as $u): ?>
                            <option value="<?php echo $u['id_usuario']; ?>" <?php echo $usuario == $u['id_usuario'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Acción</label>
                    <select name="accion" class="form-select">
                        <option value="">Todas</option>
                        <option value="INSERT" <?php echo $accion == 'INSERT' ? 'selected' : ''; ?>>INSERT</option>
                        <option value="UPDATE" <?php echo $accion == 'UPDATE' ? 'selected' : ''; ?>>UPDATE</option>
                        <option value="DELETE" <?php echo $accion == 'DELETE' ? 'selected' : ''; ?>>DELETE</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                </div>
                <div class="col-md-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                    <a href="?mod=auditoria" class="btn btn-link">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de registros -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="px-3 pt-3 small text-muted">
                <?php if ($totalRegistros > 0): ?>
                    Mostrando <?php echo ($offset + 1); ?>–<?php echo min($offset + $porPagina, $totalRegistros); ?>
                    de <?php echo $totalRegistros; ?> registro<?php echo $totalRegistros == 1 ? '' : 's'; ?>
                    (página <?php echo $pagina; ?> de <?php echo $totalPaginas; ?>)
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>Módulo</th>
                            <th>ID Registro</th>
                            <th>Acción</th>
                            <th>Usuario</th>
                            <th>IP</th>
                            <th>Detalles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($registros) === 0): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-search fa-2x mb-2 d-block"></i>
                                    No se encontraron registros de auditoría con los filtros actuales.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($registros as $reg): ?>
                                <tr>
                                    <td nowrap><?php echo date('d/m/Y H:i:s', strtotime($reg['fecha'])); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($reg['tabla_afectada']); ?></span></td>
                                    <td><?php echo $reg['id_registro']; ?></td>
                                    <td>
                                        <?php
                                        $badgeClass = '';
                                        if ($reg['accion'] == 'INSERT') $badgeClass = 'success';
                                        elseif ($reg['accion'] == 'UPDATE') $badgeClass = 'warning';
                                        else $badgeClass = 'danger';
                                        ?>
                                        <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo $reg['accion']; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($reg['usuario_nombre'] ?? 'Sistema'); ?></td>
                                    <td><?php echo htmlspecialchars($reg['ip'] ?? '-'); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info ver-detalle" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalDetalle"
                                                data-anteriores='<?php echo htmlspecialchars($reg['datos_anteriores'] ?? 'null'); ?>'
                                                data-nuevos='<?php echo htmlspecialchars($reg['datos_nuevos'] ?? 'null'); ?>'
                                                data-accion="<?php echo $reg['accion']; ?>"
                                                data-tabla="<?php echo $reg['tabla_afectada']; ?>"
                                                data-id="<?php echo $reg['id_registro']; ?>">
                                            <span class="material-symbols-rounded fs-6">visibility</span> Ver
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPaginas > 1): ?>
            <nav class="d-flex justify-content-center py-3">
                <ul class="pagination mb-0">
                    <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo htmlspecialchars(urlAuditoriaPagina(max(1, $pagina - 1))); ?>">Anterior</a>
                    </li>
                    <?php
                    // Ventana de páginas alrededor de la actual, para no pintar
                    // cientos de números si hay muchísimas páginas.
                    $desde = max(1, $pagina - 2);
                    $hasta = min($totalPaginas, $pagina + 2);
                    for ($p = $desde; $p <= $hasta; $p++):
                    ?>
                        <li class="page-item <?php echo $p == $pagina ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars(urlAuditoriaPagina($p)); ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $pagina >= $totalPaginas ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo htmlspecialchars(urlAuditoriaPagina(min($totalPaginas, $pagina + 1))); ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal centrado, hijo directo de <body> (ver script más abajo) -->
<div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true" data-bs-backdrop="false" data-bs-keyboard="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle del cambio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 p-2 bg-light rounded">
                    <div><strong>Módulo:</strong> <span id="detalleModulo"></span></div>
                    <div><strong>Qué se modificó:</strong> <span id="detalleTabla"></span> <span class="text-muted">(ID de registro: <span id="detalleId"></span>)</span></div>
                    <div><strong>Tipo de cambio:</strong> <span id="detalleAccion"></span></div>
                </div>
                <p id="detalleResumen" class="fst-italic"></p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:25%;">Campo</th>
                                <th>Antes</th>
                                <th>Ahora</th>
                            </tr>
                        </thead>
                        <tbody id="detalleDiffBody"></tbody>
                    </table>
                </div>
                <p class="small text-muted mt-2 mb-0"><span class="badge bg-warning text-dark">resaltado</span> = el campo cambió respecto al valor anterior.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Esta pantalla se carga DENTRO de menuprincipal.php. Si algún ancestro
    // del layout tiene CSS "transform" (común en sidebars animados), el
    // "position: fixed" del modal de Bootstrap se rompe y sale cortado /
    // descentrado según donde esté el scroll. Se soluciona moviendo el
    // modal para que sea hijo directo de <body>.
    document.addEventListener('DOMContentLoaded', () => {
        const el = document.getElementById('modalDetalle');
        if (el && el.parentElement !== document.body) document.body.appendChild(el);
    });

    // Traduce un valor de un campo a algo legible: IDs conocidos se
    // cambian por su nombre, booleanos por Sí/No, vacíos por "—".
    function traducirValorCampo(campo, valor) {
        if (valor === null || valor === undefined) return '<span class="text-muted">— (vacío)</span>';
        if (campo === 'id_usuario' && CATALOGO_USUARIOS[valor]) return `${CATALOGO_USUARIOS[valor]} <span class="text-muted">(#${valor})</span>`;
        if (campo === 'id_permiso' && CATALOGO_PERMISOS[valor]) return `${CATALOGO_PERMISOS[valor]} <span class="text-muted">(#${valor})</span>`;
        if (campo === 'id_rol' && CATALOGO_ROLES[valor]) return `${CATALOGO_ROLES[valor]} <span class="text-muted">(#${valor})</span>`;
        if (campo === 'id_modulo' && CATALOGO_MODULOS[valor]) return `${CATALOGO_MODULOS[valor]} <span class="text-muted">(#${valor})</span>`;
        if (valor === true || valor === 't') return '<span class="text-success fw-semibold">Sí</span>';
        if (valor === false || valor === 'f') return '<span class="text-danger fw-semibold">No</span>';
        return String(valor);
    }

    // Frase corta de resumen para INSERT / DELETE (para UPDATE ya se ve
    // claro campo por campo en la tabla de abajo).
    function resumenAccion(accion, tabla, ant, nue) {
        if (accion === 'INSERT') return 'Se creó un nuevo registro en ' + (NOMBRES_TABLA[tabla] || tabla) + '.';
        if (accion === 'DELETE') return 'Se eliminó un registro de ' + (NOMBRES_TABLA[tabla] || tabla) + '.';
        return 'Se modificó un registro existente. Los campos resaltados en amarillo son los que cambiaron.';
    }

    // Llenar modal con datos JSON: una tabla Campo | Antes | Ahora que
    // sirve para cualquier tabla auditada, resaltando lo que cambió.
    document.querySelectorAll('.ver-detalle').forEach(btn => {
        btn.addEventListener('click', function() {
            const anteriores = this.dataset.anteriores;
            const nuevos = this.dataset.nuevos;
            const accion = this.dataset.accion;
            const tabla = this.dataset.tabla;
            const id = this.dataset.id;

            document.getElementById('detalleModulo').innerText = (MODULOS_POR_TABLA[tabla] || 'Sistema') + ' — ' + (NOMBRES_TABLA[tabla] || tabla);
            document.getElementById('detalleTabla').innerText = NOMBRES_TABLA[tabla] || tabla;
            document.getElementById('detalleId').innerText = id;
            document.getElementById('detalleAccion').innerText = accion;

            const ant = anteriores && anteriores !== 'null' ? JSON.parse(anteriores) : null;
            const nue = nuevos && nuevos !== 'null' ? JSON.parse(nuevos) : null;

            document.getElementById('detalleResumen').innerText = resumenAccion(accion, tabla, ant, nue);

            const campos = Array.from(new Set([
                ...(ant ? Object.keys(ant) : []),
                ...(nue ? Object.keys(nue) : []),
            ]));

            const filas = campos.map(campo => {
                const va = ant ? ant[campo] : undefined;
                const vn = nue ? nue[campo] : undefined;
                const cambio = JSON.stringify(va) !== JSON.stringify(vn);
                return `<tr class="${cambio ? 'table-warning' : ''}">
                    <td class="fw-semibold">${campo}</td>
                    <td>${ant ? traducirValorCampo(campo, va) : '<span class="text-muted">—</span>'}</td>
                    <td>${nue ? traducirValorCampo(campo, vn) : '<span class="text-muted">—</span>'}</td>
                </tr>`;
            }).join('');

            document.getElementById('detalleDiffBody').innerHTML = filas || '<tr><td colspan="3" class="text-center text-muted">Sin datos para mostrar.</td></tr>';
        });
    });
</script>

<!-- Estilo adicional para eliminar cualquier backdrop residual -->
<style>
    .modal-backdrop {
        display: none !important;
    }
    body.modal-open {
        overflow: auto !important;
        padding-right: 0 !important;
    }
</style>