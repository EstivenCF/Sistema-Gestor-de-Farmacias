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
    $sql = "SELECT id_proveedor, nombre FROM proveedores ORDER BY nombre";
    $stmt = $conexion->prepare($sql);
    $stmt->execute();
    $proveedores = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'proveedores' => $proveedores]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>