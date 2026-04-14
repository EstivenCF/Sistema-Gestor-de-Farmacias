<?php
require_once __DIR__ . '/../conexion.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['id_vehiculo']) || empty($data['tipo'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    $stmt = $conexion->prepare("
        UPDATE vehiculos SET
            tipo = :tipo,
            placa = :placa,
            marca = :marca,
            modelo = :modelo,
            color = :color,
            id_repartidor = :id_repartidor,
            seguro_empresa = :seguro_empresa,
            fecha_vencimiento_seguro = :fecha_vencimiento_seguro,
            activo = :activo
        WHERE id_vehiculo = :id
    ");
    $stmt->execute([
        ':id' => $data['id_vehiculo'],
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
    echo json_encode(['success' => true]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>