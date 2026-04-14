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
    // Total de medicamentos
    $stmt = $conexion->query("SELECT COUNT(*) as total FROM medicamentos");
    $total_medicamentos = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Con receta
    $stmt = $conexion->query("SELECT COUNT(*) as total FROM medicamentos WHERE requiere_receta = true");
    $con_receta = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Sin receta
    $stmt = $conexion->query("SELECT COUNT(*) as total FROM medicamentos WHERE requiere_receta = false");
    $sin_receta = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'total_medicamentos' => (int)$total_medicamentos,
        'con_receta' => (int)$con_receta,
        'sin_receta' => (int)$sin_receta
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error en la consulta: ' . $e->getMessage(),
        'total_medicamentos' => 0,
        'con_receta' => 0,
        'sin_receta' => 0
    ]);
}
?>