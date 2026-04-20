<?php
require_once __DIR__ . '/../conexion.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    $stmt = $conexion->query("SELECT id_talla, nombre FROM tallas WHERE estado = true ORDER BY id_talla");
    $tallas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'tallas' => $tallas
    ]);
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar tallas: ' . $e->getMessage()
    ]);
}
?>