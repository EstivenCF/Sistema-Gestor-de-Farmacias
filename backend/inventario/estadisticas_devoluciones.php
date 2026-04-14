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
$tipo = isset($_GET['tipo']) ? (int)$_GET['tipo'] : '';
$estado = isset($_GET['estado']) ? (int)$_GET['estado'] : '';
$sucursal = isset($_GET['sucursal']) ? (int)$_GET['sucursal'] : '';
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';

try {
    $where = "WHERE 1=1";
    $params = [];
    
    if (!empty($fecha_desde)) {
        $where .= " AND fecha_solicitud >= :fecha_desde";
        $params[':fecha_desde'] = $fecha_desde;
    }
    
    if (!empty($fecha_hasta)) {
        $where .= " AND fecha_solicitud <= :fecha_hasta";
        $params[':fecha_hasta'] = $fecha_hasta;
    }
    
    if (!empty($tipo)) {
        $where .= " AND id_tipo = :tipo";
        $params[':tipo'] = $tipo;
    }
    
    if (!empty($estado)) {
        $where .= " AND id_estado = :estado";
        $params[':estado'] = $estado;
    }
    
    if (!empty($sucursal)) {
        $where .= " AND id_sucursal = :sucursal";
        $params[':sucursal'] = $sucursal;
    }
    
    if (!empty($busqueda)) {
        $where .= " AND (numero_documento ILIKE :busqueda OR id_cliente::text ILIKE :busqueda OR id_proveedor::text ILIKE :busqueda)";
        $params[':busqueda'] = "%$busqueda%";
    }
    
    // Total de devoluciones
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM devoluciones $where");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pendientes (estado 1 = SOLICITADA)
    $wherePendientes = $where . " AND id_estado = 1";
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM devoluciones $wherePendientes");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $pendientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Completadas (estado 4 = COMPLETADA)
    $whereCompletadas = $where . " AND id_estado = 4";
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM devoluciones $whereCompletadas");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $completadas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Rechazadas (estado 3 = RECHAZADA)
    $whereRechazadas = $where . " AND id_estado = 3";
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM devoluciones $whereRechazadas");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $rechazadas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'total' => (int)$total,
        'pendientes' => (int)$pendientes,
        'completadas' => (int)$completadas,
        'rechazadas' => (int)$rechazadas
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'total' => 0,
        'pendientes' => 0,
        'completadas' => 0,
        'rechazadas' => 0
    ]);
}
?>