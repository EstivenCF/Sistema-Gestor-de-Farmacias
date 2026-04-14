<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

require_once __DIR__ . '/../conexion.php';

$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$tipo = isset($_GET['tipo']) ? (int)$_GET['tipo'] : '';
$estado = isset($_GET['estado']) ? (int)$_GET['estado'] : '';
$sucursal = isset($_GET['sucursal']) ? (int)$_GET['sucursal'] : '';
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';
$offset = ($pagina - 1) * $limite;

try {
    $where = "WHERE 1=1";
    $params = [];
    
    if (!empty($fecha_desde)) {
        $where .= " AND d.fecha_solicitud >= :fecha_desde";
        $params[':fecha_desde'] = $fecha_desde;
    }
    
    if (!empty($fecha_hasta)) {
        $where .= " AND d.fecha_solicitud <= :fecha_hasta";
        $params[':fecha_hasta'] = $fecha_hasta;
    }
    
    if (!empty($tipo)) {
        $where .= " AND d.id_tipo = :tipo";
        $params[':tipo'] = $tipo;
    }
    
    if (!empty($estado)) {
        $where .= " AND d.id_estado = :estado";
        $params[':estado'] = $estado;
    }
    
    if (!empty($sucursal)) {
        $where .= " AND d.id_sucursal = :sucursal";
        $params[':sucursal'] = $sucursal;
    }
    
    if (!empty($busqueda)) {
        $where .= " AND (d.numero_documento ILIKE :busqueda OR c.nombre ILIKE :busqueda OR p.nombre ILIKE :busqueda)";
        $params[':busqueda'] = "%$busqueda%";
    }
    
    // Contar total
    $countSql = "SELECT COUNT(*) as total 
                 FROM devoluciones d
                 LEFT JOIN clientes c ON d.id_cliente = c.id_cliente
                 LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor
                 $where";
    $stmt = $conexion->prepare($countSql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener datos
    $sql = "SELECT d.*, 
                   td.nombre as tipo_nombre,
                   ed.nombre as estado_nombre,
                   c.nombre as cliente_nombre,
                   p.nombre as proveedor_nombre,
                   s.nombre as sucursal_nombre,
                   u.nombre as usuario_nombre,
                   u2.nombre as aprobador_nombre
            FROM devoluciones d
            LEFT JOIN tipo_devolucion td ON d.id_tipo = td.id_tipo
            LEFT JOIN estado_devolucion ed ON d.id_estado = ed.id_estado
            LEFT JOIN clientes c ON d.id_cliente = c.id_cliente
            LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor
            LEFT JOIN sucursales s ON d.id_sucursal = s.id_sucursal
            LEFT JOIN usuarios u ON d.id_usuario = u.id_usuario
            LEFT JOIN usuarios u2 ON d.aprobado_por = u2.id_usuario
            $where
            ORDER BY d.fecha_solicitud DESC, d.id_devolucion DESC
            LIMIT $limite OFFSET $offset";
    
    $stmt = $conexion->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $devoluciones = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'devoluciones' => $devoluciones,
        'total' => (int)$total,
        'pagina' => $pagina,
        'limite' => $limite
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>