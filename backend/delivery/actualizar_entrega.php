<?php
require_once __DIR__ . '/../conexion.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['id_entrega']) || empty($data['cliente_nombre']) || empty($data['direccion_entrega'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    $stmt = $conexion->prepare("
        UPDATE entregas SET
            cliente_nombre = :cliente,
            direccion_entrega = :direccion,
            id_repartidor = :repartidor,
            fecha_asignacion = :fecha_asignacion,
            fecha_entrega_real = :fecha_entrega_real,
            estado = :estado,
            observaciones = :observaciones
        WHERE id_entrega = :id
    ");
    $stmt->execute([
        ':id' => $data['id_entrega'],
        ':cliente' => $data['cliente_nombre'],
        ':direccion' => $data['direccion_entrega'],
        ':repartidor' => $data['id_repartidor'] ?? null,
        ':fecha_asignacion' => $data['fecha_asignacion'],
        ':fecha_entrega_real' => $data['fecha_entrega_real'] ?? null,
        ':estado' => $data['estado'],
        ':observaciones' => $data['observaciones'] ?? null
    ]);
    echo json_encode(['success' => true]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>