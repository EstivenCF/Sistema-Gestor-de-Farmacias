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
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : '30dias';
$medicamento = isset($_GET['medicamento']) ? (int)$_GET['medicamento'] : '';
$sucursal = isset($_GET['sucursal']) ? (int)$_GET['sucursal'] : '';
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';
$offset = ($pagina - 1) * $limite;

try {
    $where = "WHERE l.estado = 'ACTIVO' AND i.cantidad > 0";
    $params = [];
    
    // Filtrar por período de vencimiento
    switch($periodo) {
        case 'vencidos':
            $where .= " AND l.fecha_vencimiento < CURRENT_DATE";
            break;
        case '7dias':
            $where .= " AND l.fecha_vencimiento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'";
            break;
        case '15dias':
            $where .= " AND l.fecha_vencimiento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '15 days'";
            break;
        case '30dias':
            $where .= " AND l.fecha_vencimiento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '30 days'";
            break;
        case 'todos':
        default:
            $where .= " AND l.fecha_vencimiento >= CURRENT_DATE";
            break;
    }
    
    if (!empty($medicamento)) {
        $where .= " AND l.id_medicamento = :medicamento";
        $params[':medicamento'] = $medicamento;
    }
    
    if (!empty($sucursal)) {
        $where .= " AND i.id_sucursal = :sucursal";
        $params[':sucursal'] = $sucursal;
    }
    
    if (!empty($busqueda)) {
        $where .= " AND (l.numero_lote ILIKE :busqueda OR m.nombre ILIKE :busqueda)";
        $params[':busqueda'] = "%$busqueda%";
    }
    
    // Contar total
    $countSql = "SELECT COUNT(DISTINCT i.id_inventario) as total 
                 FROM inventario i
                 JOIN lotes l ON i.id_lote = l.id_lote
                 JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
                 $where";
    $stmt = $conexion->prepare($countSql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener datos
    $sql = "SELECT i.id_inventario, i.id_lote, i.id_sucursal, i.cantidad,
                   l.numero_lote, l.fecha_vencimiento, l.estado as estado_lote,
                   m.id_medicamento, m.nombre as medicamento_nombre, m.concentracion,
                   u.abreviatura as unidad_abrev,
                   p.nombre as presentacion,
                                     s.nombre as sucursal_nombre,
                                     EXISTS (
                                             SELECT 1
                                             FROM detalle_devolucion dd
                                             JOIN devoluciones d ON d.id_devolucion = dd.id_devolucion
                                             JOIN estado_devolucion ed ON ed.id_estado = d.id_estado
                                             WHERE dd.id_lote = i.id_lote
                                                 AND ed.nombre = 'COMPLETADA'
                                     ) AS devuelto
            FROM inventario i
            JOIN lotes l ON i.id_lote = l.id_lote
            JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
            LEFT JOIN unidades_medida u ON m.id_unidad = u.id_unidad
            LEFT JOIN presentaciones p ON m.id_presentacion = p.id_presentacion
            JOIN sucursales s ON i.id_sucursal = s.id_sucursal
            $where
            ORDER BY l.fecha_vencimiento ASC
            LIMIT $limite OFFSET $offset";
    
    $stmt = $conexion->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $lotes = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'lotes' => $lotes,
        'total' => (int)$total,
        'pagina' => $pagina,
        'limite' => $limite
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>