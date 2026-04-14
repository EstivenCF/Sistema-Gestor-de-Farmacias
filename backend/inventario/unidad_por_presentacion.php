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

$id_presentacion = $_GET['id_presentacion'] ?? 0;

if (!$id_presentacion) {
    echo json_encode(['success' => false, 'message' => 'ID de presentación no especificado']);
    exit();
}

try {
    $stmt = $conexion->prepare("
        SELECT u.id_unidad, u.nombre, u.abreviatura
        FROM presentaciones p
        JOIN unidades_medida u ON p.id_unidad = u.id_unidad
        WHERE p.id_presentacion = ?
    ");
    $stmt->execute([$id_presentacion]);
    $unidad = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'unidad' => $unidad
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>