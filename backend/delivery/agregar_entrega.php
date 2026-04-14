<?php
require_once __DIR__ . '/../conexion.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['cliente_nombre']) || empty($data['direccion_entrega'])) {
    echo json_encode(['success' => false, 'message' => 'Cliente y dirección son obligatorios']);
    exit();
}

try {
    $stmt = $conexion->prepare("
        INSERT INTO entregas (cliente_nombre, direccion_entrega, id_repartidor, fecha_asignacion, fecha_entrega_real, estado, observaciones)
        VALUES (:cliente, :direccion, :repartidor, :fecha_asignacion, :fecha_entrega_real, :estado, :observaciones)
        RETURNING id_entrega
    ");
    $stmt->execute([
        ':cliente' => $data['cliente_nombre'],
        ':direccion' => $data['direccion_entrega'],
        ':repartidor' => $data['id_repartidor'] ?? null,
        ':fecha_asignacion' => $data['fecha_asignacion'] ?? date('Y-m-d H:i:s'),
        ':fecha_entrega_real' => $data['fecha_entrega_real'] ?? null,
        ':estado' => $data['estado'] ?? 'pendiente',
        ':observaciones' => $data['observaciones'] ?? null
    ]);
    $id = $stmt->fetchColumn();
    echo json_encode(['success' => true, 'id_entrega' => $id]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>