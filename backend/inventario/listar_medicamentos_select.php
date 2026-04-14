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
    $sql = "SELECT m.id_medicamento, 
                   CONCAT(m.nombre, ' ', m.concentracion, ' ', u.abreviatura, ' - ', p.nombre) as nombre_completo,
                   prod.precio
            FROM medicamentos m
            LEFT JOIN unidades_medida u ON m.id_unidad = u.id_unidad
            LEFT JOIN presentaciones p ON m.id_presentacion = p.id_presentacion
            LEFT JOIN productos prod ON m.id_producto = prod.id_producto
            WHERE m.id_medicamento IS NOT NULL
            ORDER BY m.nombre";
    
    $stmt = $conexion->query($sql);
    $medicamentos = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'medicamentos' => $medicamentos
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>