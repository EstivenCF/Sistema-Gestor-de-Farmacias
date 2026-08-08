<?php
require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['id_caja']) || !isset($data['monto_final'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

$id_caja = $data['id_caja'];
$monto_final = floatval($data['monto_final']);
$observaciones_cierre = $data['observaciones_cierre'] ?? null;

try {
    $stmtCheck = $conexion->prepare("SELECT id_caja, estado FROM caja WHERE id_caja = :id_caja AND estado = 'ABIERTA'");
    $stmtCheck->execute([':id_caja' => $id_caja]);
    if (!$stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'La caja no existe o ya está cerrada']);
        exit();
    }
    
    $stmt = $conexion->prepare("
        UPDATE caja 
        SET fecha_cierre = NOW(), 
            monto_final = :monto_final, 
            estado = 'CERRADA',
            observaciones_cierre = :observaciones_cierre
        WHERE id_caja = :id_caja
    ");
    $stmt->execute([
        ':monto_final' => $monto_final,
        ':observaciones_cierre' => $observaciones_cierre,
        ':id_caja' => $id_caja
    ]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>