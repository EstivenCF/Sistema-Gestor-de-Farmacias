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
    $sql = "SELECT l.id_lote, l.numero_lote, m.nombre as medicamento_nombre 
            FROM lotes l
            LEFT JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
            ORDER BY l.numero_lote ASC";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute();
    $lotes = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'lotes' => $lotes]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>