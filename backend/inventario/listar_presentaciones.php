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
$offset = ($pagina - 1) * $limite;

try {
    $where = "";
    $params = [];
    
    if (!empty($busqueda)) {
        $where = "WHERE p.nombre ILIKE :busqueda";
        $params[':busqueda'] = "%$busqueda%";
    }
    
    // Contar total
    $countSql = "SELECT COUNT(*) as total FROM presentaciones p $where";
    $stmt = $conexion->prepare($countSql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener datos
    $sql = "SELECT p.*, 
                   u.nombre as unidad_nombre,
                   u.abreviatura as unidad_abrev,
                   COUNT(m.id_medicamento) as total_medicamentos
            FROM presentaciones p
            LEFT JOIN unidades_medida u ON u.id_unidad = p.id_unidad
            LEFT JOIN medicamentos m ON m.id_presentacion = p.id_presentacion
            $where
            GROUP BY p.id_presentacion, u.id_unidad
            ORDER BY p.nombre
            LIMIT $limite OFFSET $offset";
    
    $stmt = $conexion->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $presentaciones = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'presentaciones' => $presentaciones,
        'total' => (int)$total,
        'pagina' => $pagina,
        'limite' => $limite
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>