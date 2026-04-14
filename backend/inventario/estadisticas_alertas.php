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
    $where = "WHERE 1=1";
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
    
    // Stock crítico (<=5 unidades y >0)
    $whereCritico = $where . " AND i.cantidad <= 5 AND i.cantidad > 0";
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM inventario i JOIN lotes l ON i.id_lote = l.id_lote JOIN medicamentos m ON l.id_medicamento = m.id_medicamento $whereCritico");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $critico = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Stock bajo (6-10 unidades)
    $whereBajo = $where . " AND i.cantidad BETWEEN 6 AND 10";
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM inventario i JOIN lotes l ON i.id_lote = l.id_lote JOIN medicamentos m ON l.id_medicamento = m.id_medicamento $whereBajo");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $bajo = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Stock normal (>10 unidades)
    $whereNormal = $where . " AND i.cantidad > 10";
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM inventario i JOIN lotes l ON i.id_lote = l.id_lote JOIN medicamentos m ON l.id_medicamento = m.id_medicamento $whereNormal");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $normal = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Stock agotado (0 unidades)
    $whereAgotado = $where . " AND i.cantidad = 0";
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM inventario i JOIN lotes l ON i.id_lote = l.id_lote JOIN medicamentos m ON l.id_medicamento = m.id_medicamento $whereAgotado");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $agotado = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'critico' => (int)$critico,
        'bajo' => (int)$bajo,
        'normal' => (int)$normal,
        'agotado' => (int)$agotado
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'critico' => 0,
        'bajo' => 0,
        'normal' => 0,
        'agotado' => 0
    ]);
}
?>