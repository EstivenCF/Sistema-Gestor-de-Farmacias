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
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$offset = ($pagina - 1) * $limite;

try {
    $where = "";
    $params = [];
    
    if (!empty($busqueda)) {
        $where = "WHERE (l.numero_lote ILIKE :busqueda OR m.nombre ILIKE :busqueda OR m.concentracion::text ILIKE :busqueda)";
        $params[':busqueda'] = "%$busqueda%";
    }
    
    if (!empty($estado)) {
        $where .= empty($where) ? "WHERE l.estado = :estado" : " AND l.estado = :estado";
        $params[':estado'] = $estado;
    }
    
    // Contar total
    $countSql = "SELECT COUNT(*) as total FROM lotes l LEFT JOIN medicamentos m ON l.id_medicamento = m.id_medicamento $where";
    $stmt = $conexion->prepare($countSql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener datos con costo_lote incluido
    $sql = "SELECT l.*, 
                   m.nombre as medicamento_nombre,
                   m.concentracion,
                   u.abreviatura as unidad_abrev,
                   p.nombre as presentacion,
                   COALESCE(SUM(i.cantidad), 0) as stock_actual
            FROM lotes l
            LEFT JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
            LEFT JOIN unidades_medida u ON m.id_unidad = u.id_unidad
            LEFT JOIN presentaciones p ON m.id_presentacion = p.id_presentacion
            LEFT JOIN inventario i ON l.id_lote = i.id_lote
            $where
            GROUP BY l.id_lote, m.nombre, m.concentracion, u.abreviatura, p.nombre
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
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>