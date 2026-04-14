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
    $stmt = $conexion->query("SELECT id_categoria, nombre FROM categorias WHERE activo = true ORDER BY nombre");
    $categorias = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'categorias' => $categorias
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>