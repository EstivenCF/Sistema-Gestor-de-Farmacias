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
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';
$medicamento = isset($_GET['medicamento']) ? (int)$_GET['medicamento'] : '';
$sucursal = isset($_GET['sucursal']) ? (int)$_GET['sucursal'] : '';
$estado_stock = isset($_GET['estado_stock']) ? $_GET['estado_stock'] : '';
$estado_lote = isset($_GET['estado_lote']) ? $_GET['estado_lote'] : '';
$offset = ($pagina - 1) * $limite;

try {
    $where = "WHERE 1=1";
    $params = [];
    
    if (!empty($busqueda)) {
        $where .= " AND (l.numero_lote ILIKE :busqueda OR m.nombre ILIKE :busqueda OR l.codigo_barras ILIKE :busqueda)";
        $params[':busqueda'] = "%$busqueda%";
    }
    
    if (!empty($medicamento)) {
        $where .= " AND l.id_medicamento = :medicamento";
        $params[':medicamento'] = $medicamento;
    }
    
    if (!empty($sucursal)) {
        $where .= " AND i.id_sucursal = :sucursal";
        $params[':sucursal'] = $sucursal;
    }
    
    if (!empty($estado_lote)) {
        $where .= " AND l.estado = :estado_lote";
        $params[':estado_lote'] = $estado_lote;
    }
    
    if (!empty($estado_stock)) {
        switch($estado_stock) {
            case 'critico':
                $where .= " AND i.cantidad <= 5 AND i.cantidad > 0";
                break;
            case 'bajo':
                $where .= " AND i.cantidad BETWEEN 6 AND 10";
                break;
            case 'normal':
                $where .= " AND i.cantidad > 10";
                break;
            case 'agotado':
                $where .= " AND i.cantidad = 0";
                break;
        }
    }
    
    // Contar total
    $countSql = "SELECT COUNT(*) as total 
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
    $sql = "SELECT i.id_inventario, i.id_lote, i.id_sucursal, 
                   COALESCE(i.cantidad, 0) as cantidad,
                   l.numero_lote, l.fecha_vencimiento, l.estado as estado_lote,
                   m.id_medicamento, m.nombre as medicamento_nombre, m.concentracion,
                   u.abreviatura as unidad_abrev,
                   p.nombre as presentacion,
                   s.nombre as sucursal_nombre
            FROM inventario i
            JOIN lotes l ON i.id_lote = l.id_lote
            JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
            LEFT JOIN unidades_medida u ON m.id_unidad = u.id_unidad
            LEFT JOIN presentaciones p ON m.id_presentacion = p.id_presentacion
            JOIN sucursales s ON i.id_sucursal = s.id_sucursal
            $where
            ORDER BY 
                l.estado = 'ACTIVO' DESC,
                CASE WHEN i.cantidad <= 5 AND i.cantidad > 0 THEN 1
                     WHEN i.cantidad BETWEEN 6 AND 10 THEN 2
                     WHEN i.cantidad > 10 THEN 3
                     ELSE 4 END,
                m.nombre ASC
            LIMIT $limite OFFSET $offset";
    
    $stmt = $conexion->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $stock = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'stock' => $stock,
        'total' => (int)$total,
        'pagina' => $pagina,
        'limite' => $limite
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>