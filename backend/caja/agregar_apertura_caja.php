<?php
require_once __DIR__ . '/../conexion.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['id_sucursal']) || empty($data['id_usuario']) || empty($data['numero_caja']) || !isset($data['monto_inicial'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

$id_sucursal = $data['id_sucursal'];
$id_usuario = $data['id_usuario'];
$numero_caja = trim($data['numero_caja']);
$monto_inicial = floatval($data['monto_inicial']);
$observaciones = $data['observaciones'] ?? null;

try {
    $stmtCheck = $conexion->prepare("SELECT id_caja FROM caja WHERE id_sucursal = :id_sucursal AND numero_caja = :numero_caja AND estado = 'ABIERTA'");
    $stmtCheck->execute([':id_sucursal' => $id_sucursal, ':numero_caja' => $numero_caja]);
    if ($stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => "La caja '$numero_caja' ya está abierta en esta sucursal"]);
        exit();
    }
    
    $stmt = $conexion->prepare("
        INSERT INTO caja (id_sucursal, id_usuario, fecha_apertura, monto_inicial, estado, observaciones, numero_caja)
        VALUES (:id_sucursal, :id_usuario, NOW(), :monto_inicial, 'ABIERTA', :observaciones, :numero_caja)
        RETURNING id_caja
    ");
    $stmt->execute([
        ':id_sucursal' => $id_sucursal,
        ':id_usuario' => $id_usuario,
        ':monto_inicial' => $monto_inicial,
        ':observaciones' => $observaciones,
        ':numero_caja' => $numero_caja
    ]);
    $id_caja = $stmt->fetchColumn();
    echo json_encode(['success' => true, 'id_caja' => $id_caja]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>