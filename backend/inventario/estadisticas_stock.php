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
    // Stock total
    $stmt = $conexion->query("SELECT COALESCE(SUM(cantidad), 0) as total FROM inventario");
    $stock_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Stock crítico (≤5 unidades)
    $stmt = $conexion->query("SELECT COALESCE(SUM(cantidad), 0) as total FROM inventario WHERE cantidad <= 5 AND cantidad > 0");
    $stock_critico = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Stock bajo (6-10 unidades)
    $stmt = $conexion->query("SELECT COALESCE(SUM(cantidad), 0) as total FROM inventario WHERE cantidad BETWEEN 6 AND 10");
    $stock_bajo = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Lotes por vencer (próximos 30 días)
    $stmt = $conexion->query("SELECT COUNT(DISTINCT l.id_lote) as total 
                              FROM lotes l 
                              WHERE l.fecha_vencimiento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '30 days' 
                              AND l.estado = 'ACTIVO'");
    $lotes_por_vencer = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'stock_total' => (int)$stock_total,
        'stock_critico' => (int)$stock_critico,
        'stock_bajo' => (int)$stock_bajo,
        'lotes_por_vencer' => (int)$lotes_por_vencer
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'stock_total' => 0,
        'stock_critico' => 0,
        'stock_bajo' => 0,
        'lotes_por_vencer' => 0
    ]);
}
?>