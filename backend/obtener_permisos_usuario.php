<?php
session_start();
header('Content-Type: application/json');

require_once 'conexion.php';

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

$id_usuario = isset($_GET['id_usuario']) ? intval($_GET['id_usuario']) : 0;

if ($id_usuario <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario inválido']);
    exit();
}

// Obtener permisos del usuario
$stmt = $conexion->prepare("
    SELECT p.nombre as permiso_nombre, up.permitido
    FROM usuario_permiso up
    JOIN permisos p ON up.id_permiso = p.id_permiso
    WHERE up.id_usuario = ? AND up.permitido = TRUE
");
$stmt->execute([$id_usuario]);
$permisos_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mapear permisos a un formato más usable
$permisos_mapeados = [];

foreach ($permisos_db as $p) {
    $permisos_mapeados[$p['permiso_nombre']] = true;
}

// Definir estructura completa de permisos
$permisos_completos = [
    'ventas' => isset($permisos_mapeados['ventas']) ? true : false,
    'inventario' => isset($permisos_mapeados['inventario']) ? true : false,
    'compras' => isset($permisos_mapeados['compras']) ? true : false,
    'clientes' => isset($permisos_mapeados['clientes']) ? true : false,
    'caja' => isset($permisos_mapeados['caja']) ? true : false,
    'administracion' => isset($permisos_mapeados['administracion']) ? true : false,
    'reportes' => isset($permisos_mapeados['reportes']) ? true : false,
    // Submódulos
    'registrar_venta' => isset($permisos_mapeados['registrar_venta']) ? true : false,
    'historial_ventas' => isset($permisos_mapeados['historial_ventas']) ? true : false,
    'pagos' => isset($permisos_mapeados['pagos']) ? true : false,
    'facturacion' => isset($permisos_mapeados['facturacion']) ? true : false,
    'medicamentos' => isset($permisos_mapeados['medicamentos']) ? true : false,
    'categorias' => isset($permisos_mapeados['categorias']) ? true : false,
    'lotes' => isset($permisos_mapeados['lotes']) ? true : false,
    'stock' => isset($permisos_mapeados['stock']) ? true : false,
    'vencimientos' => isset($permisos_mapeados['vencimientos']) ? true : false,
    'registrar_compra' => isset($permisos_mapeados['registrar_compra']) ? true : false,
    'historial_compras' => isset($permisos_mapeados['historial_compras']) ? true : false,
    'proveedores' => isset($permisos_mapeados['proveedores']) ? true : false,
    'clientes_lista' => isset($permisos_mapeados['clientes']) ? true : false,
    'historial_cliente' => isset($permisos_mapeados['historial_cliente']) ? true : false,
    'apertura_caja' => isset($permisos_mapeados['apertura_caja']) ? true : false,
    'cierre_caja' => isset($permisos_mapeados['cierre_caja']) ? true : false,
    'usuarios' => isset($permisos_mapeados['usuarios']) ? true : false,
    'roles' => isset($permisos_mapeados['roles']) ? true : false,
    'permisos_usuarios' => isset($permisos_mapeados['permisos_usuarios']) ? true : false,
    'sucursales' => isset($permisos_mapeados['sucursales']) ? true : false,
    'reporte_ventas' => isset($permisos_mapeados['reporte_ventas']) ? true : false,
    'reporte_inventario' => isset($permisos_mapeados['reporte_inventario']) ? true : false,
    'reporte_vencimientos' => isset($permisos_mapeados['reporte_vencimientos']) ? true : false
];

echo json_encode(['success' => true, 'permisos' => $permisos_completos]);
?>