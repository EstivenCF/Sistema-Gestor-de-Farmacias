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

$medicamento = isset($_GET['medicamento']) ? (int)$_GET['medicamento'] : '';
$sucursal = isset($_GET['sucursal']) ? (int)$_GET['sucursal'] : '';
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';

try {
    $where = "WHERE l.estado = 'ACTIVO'";
    $params = [];
    
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
    
    // Vencidos
    $whereVencidos = $where . " AND l.fecha_vencimiento < CURRENT_DATE";
    $stmt = $conexion->prepare("SELECT COUNT(DISTINCT i.id_inventario) as total FROM inventario i JOIN lotes l ON i.id_lote = l.id_lote JOIN medicamentos m ON l.id_medicamento = m.id_medicamento $whereVencidos");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $vencidos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Próximos 7 días
    $where7dias = $where . " AND l.fecha_vencimiento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'";
    $stmt = $conexion->prepare("SELECT COUNT(DISTINCT i.id_inventario) as total FROM inventario i JOIN lotes l ON i.id_lote = l.id_lote JOIN medicamentos m ON l.id_medicamento = m.id_medicamento $where7dias");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $prox_7_dias = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Próximos 15 días
    $where15dias = $where . " AND l.fecha_vencimiento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '15 days'";
    $stmt = $conexion->prepare("SELECT COUNT(DISTINCT i.id_inventario) as total FROM inventario i JOIN lotes l ON i.id_lote = l.id_lote JOIN medicamentos m ON l.id_medicamento = m.id_medicamento $where15dias");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $prox_15_dias = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Próximos 30 días
    $where30dias = $where . " AND l.fecha_vencimiento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '30 days'";
    $stmt = $conexion->prepare("SELECT COUNT(DISTINCT i.id_inventario) as total FROM inventario i JOIN lotes l ON i.id_lote = l.id_lote JOIN medicamentos m ON l.id_medicamento = m.id_medicamento $where30dias");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $prox_30_dias = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'vencidos' => (int)$vencidos,
        'prox_7_dias' => (int)$prox_7_dias,
        'prox_15_dias' => (int)$prox_15_dias,
        'prox_30_dias' => (int)$prox_30_dias
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'vencidos' => 0,
        'prox_7_dias' => 0,
        'prox_15_dias' => 0,
        'prox_30_dias' => 0
    ]);
}
?>