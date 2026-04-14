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
    // Total de categorías
    $stmt = $conexion->query("SELECT COUNT(*) as total FROM categorias");
    $total_categorias = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Categorías con medicamentos asociados
    $stmt = $conexion->query("SELECT COUNT(DISTINCT c.id_categoria) as total 
                               FROM categorias c 
                               INNER JOIN medicamentos m ON m.id_categoria = c.id_categoria");
    $con_medicamentos = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Categorías sin medicamentos
    $sin_medicamentos = $total_categorias - $con_medicamentos;
    
    echo json_encode([
        'success' => true,
        'total_categorias' => (int)$total_categorias,
        'con_medicamentos' => (int)$con_medicamentos,
        'sin_medicamentos' => (int)$sin_medicamentos
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error en la consulta: ' . $e->getMessage(),
        'total_categorias' => 0,
        'con_medicamentos' => 0,
        'sin_medicamentos' => 0
    ]);
}
?>