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
    // Total de presentaciones
    $stmt = $conexion->query("SELECT COUNT(*) as total FROM presentaciones");
    $total_presentaciones = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Presentaciones con medicamentos asociados
    $stmt = $conexion->query("SELECT COUNT(DISTINCT p.id_presentacion) as total 
                               FROM presentaciones p 
                               INNER JOIN medicamentos m ON m.id_presentacion = p.id_presentacion");
    $con_medicamentos = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Presentaciones sin medicamentos
    $sin_medicamentos = $total_presentaciones - $con_medicamentos;
    
    // Unidades de medida asociadas
    $stmt = $conexion->query("SELECT COUNT(DISTINCT id_unidad) as total FROM presentaciones WHERE id_unidad IS NOT NULL");
    $con_unidad = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'total_presentaciones' => (int)$total_presentaciones,
        'con_medicamentos' => (int)$con_medicamentos,
        'sin_medicamentos' => (int)$sin_medicamentos,
        'con_unidad' => (int)$con_unidad
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error en la consulta: ' . $e->getMessage(),
        'total_presentaciones' => 0,
        'con_medicamentos' => 0,
        'sin_medicamentos' => 0,
        'con_unidad' => 0
    ]);
}
?>