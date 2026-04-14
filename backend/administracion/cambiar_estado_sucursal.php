<?php
require_once __DIR__ . '/../conexion.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['id_sucursal']) || !isset($data['activo'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    $stmt = $conexion->prepare("UPDATE sucursales SET estado = ?::boolean WHERE id_sucursal = ?");
    $stmt->execute([$data['activo'] ? 'true' : 'false', $data['id_sucursal']]);
    echo json_encode(['success' => true]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>