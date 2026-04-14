<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id_pago = $data['id_pago'] ?? null;

if (!$id_pago) {
    echo json_encode(['success' => false, 'message' => 'ID de pago requerido']);
    exit();
}

try {
    $stmt = $conexion->prepare("
        UPDATE pagos 
        SET estado = 'COMPLETADO' 
        WHERE id_pago = ? AND estado = 'PENDIENTE'
    ");
    $stmt->execute([$id_pago]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo confirmar el pago']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>