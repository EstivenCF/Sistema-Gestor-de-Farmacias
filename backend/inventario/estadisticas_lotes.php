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

try {
    // Total de lotes
    $stmt = $conexion->query("SELECT COUNT(*) as total FROM lotes");
    $total_lotes = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Lotes activos
    $stmt = $conexion->query("SELECT COUNT(*) as total FROM lotes WHERE estado = 'ACTIVO'");
    $activos = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Lotes vencidos
    $stmt = $conexion->query("SELECT COUNT(*) as total FROM lotes WHERE estado = 'VENCIDO' OR fecha_vencimiento < CURRENT_DATE");
    $vencidos = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Lotes por vencer (próximos 30 días)
    $stmt = $conexion->query("SELECT COUNT(*) as total FROM lotes WHERE fecha_vencimiento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '30 days' AND estado = 'ACTIVO'");
    $por_vencer = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Stock total en inventario
    $stmt = $conexion->query("SELECT COALESCE(SUM(i.cantidad), 0) as total FROM inventario i");
    $stock_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'total_lotes' => (int)$total_lotes,
        'activos' => (int)$activos,
        'vencidos' => (int)$vencidos,
        'por_vencer' => (int)$por_vencer,
        'stock_total' => (int)$stock_total
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error en la consulta: ' . $e->getMessage(),
        'total_lotes' => 0,
        'activos' => 0,
        'vencidos' => 0,
        'por_vencer' => 0,
        'stock_total' => 0
    ]);
}
?>