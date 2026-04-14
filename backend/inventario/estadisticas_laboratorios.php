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
    // Total de laboratorios
    $stmt = $conexion->query("SELECT COUNT(*) as total FROM laboratorios");
    $total_laboratorios = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Laboratorios con medicamentos asociados
    $stmt = $conexion->query("SELECT COUNT(DISTINCT l.id_laboratorio) as total 
                               FROM laboratorios l 
                               INNER JOIN medicamentos m ON m.id_laboratorio = l.id_laboratorio");
    $con_medicamentos = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Laboratorios sin medicamentos
    $sin_medicamentos = $total_laboratorios - $con_medicamentos;
    
    // Laboratorios activos
    $stmt = $conexion->query("SELECT COUNT(*) as total FROM laboratorios WHERE activo = true");
    $activos = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'total_laboratorios' => (int)$total_laboratorios,
        'con_medicamentos' => (int)$con_medicamentos,
        'sin_medicamentos' => (int)$sin_medicamentos,
        'activos' => (int)$activos
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error en la consulta: ' . $e->getMessage(),
        'total_laboratorios' => 0,
        'con_medicamentos' => 0,
        'sin_medicamentos' => 0,
        'activos' => 0
    ]);
}
?>