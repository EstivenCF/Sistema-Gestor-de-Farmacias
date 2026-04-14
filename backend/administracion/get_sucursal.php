<?php
require_once __DIR__ . '/../conexion.php';
header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID requerido']);
    exit();
}

try {
    $stmt = $conexion->prepare("SELECT * FROM sucursales WHERE id_sucursal = ?");
    $stmt->execute([$id]);
    $sucursal = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($sucursal) {
        echo json_encode(['success' => true] + $sucursal);
    } else {
        echo json_encode(['success' => false, 'message' => 'No encontrado']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>