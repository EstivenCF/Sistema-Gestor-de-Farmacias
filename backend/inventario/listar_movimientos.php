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
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$sucursal = isset($_GET['sucursal']) ? (int)$_GET['sucursal'] : '';
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';
$offset = ($pagina - 1) * $limite;

try {
    $where = "WHERE 1=1";
    $params = [];
    
    if (!empty($fecha_desde)) {
        $where .= " AND m.fecha >= :fecha_desde";
        $params[':fecha_desde'] = $fecha_desde . ' 00:00:00';
    }
    
    if (!empty($fecha_hasta)) {
        $where .= " AND m.fecha <= :fecha_hasta";
        $params[':fecha_hasta'] = $fecha_hasta . ' 23:59:59';
    }
    
    if (!empty($tipo)) {
        $where .= " AND m.tipo = :tipo";
        $params[':tipo'] = $tipo;
    }
    
    if (!empty($sucursal)) {
        $where .= " AND m.id_sucursal = :sucursal";
        $params[':sucursal'] = $sucursal;
    }
    
    if (!empty($busqueda)) {
        $where .= " AND (l.numero_lote ILIKE :busqueda OR med.nombre ILIKE :busqueda OR m.referencia ILIKE :busqueda)";
        $params[':busqueda'] = "%$busqueda%";
    }
    
    // Contar total
    $countSql = "SELECT COUNT(*) as total 
                 FROM movimiento_inventario m
                 LEFT JOIN lotes l ON m.id_lote = l.id_lote
                 LEFT JOIN medicamentos med ON l.id_medicamento = med.id_medicamento
                 $where";
    $stmt = $conexion->prepare($countSql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener datos
    $sql = "SELECT m.id_movimiento, m.id_lote, m.id_sucursal, m.tipo, m.cantidad, 
                   m.fecha, m.motivo, m.referencia, m.observaciones,
                   l.numero_lote,
                   med.nombre as medicamento_nombre, med.concentracion,
                   u.abreviatura as unidad_abrev,
                   p.nombre as presentacion,
                   s.nombre as sucursal_nombre,
                   us.nombre as usuario_nombre
            FROM movimiento_inventario m
            LEFT JOIN lotes l ON m.id_lote = l.id_lote
            LEFT JOIN medicamentos med ON l.id_medicamento = med.id_medicamento
            LEFT JOIN unidades_medida u ON med.id_unidad = u.id_unidad
            LEFT JOIN presentaciones p ON med.id_presentacion = p.id_presentacion
            LEFT JOIN sucursales s ON m.id_sucursal = s.id_sucursal
            LEFT JOIN usuarios us ON m.id_usuario = us.id_usuario
            $where
            ORDER BY m.fecha DESC, m.id_movimiento DESC
            LIMIT $limite OFFSET $offset";
    
    $stmt = $conexion->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $movimientos = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'movimientos' => $movimientos,
        'total' => (int)$total,
        'pagina' => $pagina,
        'limite' => $limite
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>