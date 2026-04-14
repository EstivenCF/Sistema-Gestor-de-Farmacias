<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

$id_venta = intval($data['id_venta']);
$monto = floatval($data['monto']);
$id_metodo_pago = intval($data['id_metodo_pago']);
$referencia = $data['referencia'] ?? null;
$id_usuario = $_SESSION['id_usuario'] ?? 1;

if ($monto <= 0) {
    echo json_encode(['success' => false, 'message' => 'Monto inválido']);
    exit();
}

try {
    $conexion->beginTransaction();
    
    // Verificar saldo pendiente
    $stmt = $conexion->prepare("SELECT total, COALESCE(abonos_acumulados,0) as abonos FROM ventas WHERE id_venta = ? FOR UPDATE");
    $stmt->execute([$id_venta]);
    $venta = $stmt->fetch();
    if (!$venta) throw new Exception('Venta no encontrada');
    
    $saldo_pendiente = $venta['total'] - $venta['abonos'];
    if ($monto > $saldo_pendiente) {
        throw new Exception("El monto excede el saldo pendiente (RD$ " . number_format($saldo_pendiente,2) . ")");
    }
    
    // Insertar abono (el trigger se encargará de actualizar ventas y clientes)
    $stmt = $conexion->prepare("
        INSERT INTO abonos_credito (id_venta, monto, id_metodo_pago, referencia, creado_por)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$id_venta, $monto, $id_metodo_pago, $referencia, $id_usuario]);
    
    // Insertar el pago asociado (opcional, si lo deseas)
    $stmt = $conexion->prepare("
        INSERT INTO pagos (id_venta, id_metodo, monto, referencia, estado)
        VALUES (?, ?, ?, ?, 'COMPLETADO')
    ");
    $stmt->execute([$id_venta, $id_metodo_pago, $monto, $referencia]);
    
    $conexion->commit();
    echo json_encode(['success' => true, 'message' => 'Abono registrado']);
    
} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>