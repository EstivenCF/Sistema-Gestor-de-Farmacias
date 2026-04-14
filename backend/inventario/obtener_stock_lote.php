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

$id_lote = isset($_GET['id_lote']) ? (int)$_GET['id_lote'] : 0;
$id_sucursal = isset($_GET['id_sucursal']) ? (int)$_GET['id_sucursal'] : 0;

if (!$id_lote || !$id_sucursal) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
    exit();
}

try {
    $sql = "SELECT COALESCE(cantidad, 0) as cantidad FROM inventario WHERE id_lote = :id_lote AND id_sucursal = :id_sucursal";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id_lote' => $id_lote, ':id_sucursal' => $id_sucursal]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stock = $result ? (int)$result['cantidad'] : 0;
    
    echo json_encode(['success' => true, 'stock' => $stock]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>