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
    $stmt = $conexion->query("SELECT id_color, nombre FROM colores WHERE estado = true ORDER BY id_color");
    $colores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'colores' => $colores
    ]);
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar colores: ' . $e->getMessage()
    ]);
}
?>