<?php
require_once __DIR__ . '/../conexion.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['tipo'])) {
    echo json_encode(['success' => false, 'message' => 'El tipo de vehículo es obligatorio']);
    exit();
}

try {
    $stmt = $conexion->prepare("
        INSERT INTO vehiculos (tipo, placa, marca, modelo, color, id_repartidor, seguro_empresa, fecha_vencimiento_seguro, activo)
        VALUES (:tipo, :placa, :marca, :modelo, :color, :id_repartidor, :seguro_empresa, :fecha_vencimiento_seguro, :activo)
        RETURNING id_vehiculo
    ");
    $stmt->execute([
        ':tipo' => $data['tipo'],
        ':placa' => $data['placa'] ?? null,
        ':marca' => $data['marca'] ?? null,
        ':modelo' => $data['modelo'] ?? null,
        ':color' => $data['color'] ?? null,
        ':id_repartidor' => $data['id_repartidor'] ?? null,
        ':seguro_empresa' => $data['seguro_empresa'] ?? null,
        ':fecha_vencimiento_seguro' => $data['fecha_vencimiento_seguro'] ?? null,
        ':activo' => $data['activo'] ?? true
    ]);
    $id = $stmt->fetchColumn();
    echo json_encode(['success' => true, 'id_vehiculo' => $id]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>