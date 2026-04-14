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

$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$sucursal = isset($_GET['sucursal']) ? (int)$_GET['sucursal'] : '';
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';

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
        $where .= " AND (l.numero_lote ILIKE :busqueda OR med.nombre ILIKE :busqueda)";
        $params[':busqueda'] = "%$busqueda%";
    }
    
    // Total de movimientos
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM movimiento_inventario m LEFT JOIN lotes l ON m.id_lote = l.id_lote LEFT JOIN medicamentos med ON l.id_medicamento = med.id_medicamento $where");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_movimientos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de entradas (suma de cantidades)
    $whereEntradas = $where . " AND m.tipo = 'ENTRADA'";
    $stmt = $conexion->prepare("SELECT COALESCE(SUM(m.cantidad), 0) as total FROM movimiento_inventario m LEFT JOIN lotes l ON m.id_lote = l.id_lote LEFT JOIN medicamentos med ON l.id_medicamento = med.id_medicamento $whereEntradas");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_entradas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de salidas (suma de cantidades)
    $whereSalidas = $where . " AND m.tipo = 'SALIDA'";
    $stmt = $conexion->prepare("SELECT COALESCE(SUM(m.cantidad), 0) as total FROM movimiento_inventario m LEFT JOIN lotes l ON m.id_lote = l.id_lote LEFT JOIN medicamentos med ON l.id_medicamento = med.id_medicamento $whereSalidas");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_salidas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de transferencias (conteo)
    $whereTransferencias = $where . " AND m.tipo = 'TRANSFERENCIA'";
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM movimiento_inventario m LEFT JOIN lotes l ON m.id_lote = l.id_lote LEFT JOIN medicamentos med ON l.id_medicamento = med.id_medicamento $whereTransferencias");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_transferencias = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'total_movimientos' => (int)$total_movimientos,
        'total_entradas' => (int)$total_entradas,
        'total_salidas' => (int)$total_salidas,
        'total_transferencias' => (int)$total_transferencias
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'total_movimientos' => 0,
        'total_entradas' => 0,
        'total_salidas' => 0,
        'total_transferencias' => 0
    ]);
}
?>