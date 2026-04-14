<?php
require_once __DIR__ . '/../conexion.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['id_entrega']) || empty($data['estado'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

$estado = $data['estado'];
if (!in_array($estado, ['pendiente', 'en_camino', 'entregado', 'cancelado'])) {
    echo json_encode(['success' => false, 'message' => 'Estado no válido']);
    exit();
}

try {
    // Si se marca como entregado, actualizar fecha_entrega_real automáticamente
    $fecha_entrega = null;
    if ($estado === 'entregado') {
        $fecha_entrega = date('Y-m-d H:i:s');
    }
    $stmt = $conexion->prepare("
        UPDATE entregas SET estado = :estado, fecha_entrega_real = COALESCE(:fecha_entrega, fecha_entrega_real)
        WHERE id_entrega = :id
    ");
    $stmt->execute([
        ':estado' => $estado,
        ':fecha_entrega' => $fecha_entrega,
        ':id' => $data['id_entrega']
    ]);
    echo json_encode(['success' => true]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>