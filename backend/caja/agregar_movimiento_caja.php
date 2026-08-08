<?php
require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['id_caja']) || empty($data['tipo']) || empty($data['monto']) || empty($data['concepto'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

$id_caja = $data['id_caja'];
$tipo = $data['tipo'];
$monto = floatval($data['monto']);
$concepto = trim($data['concepto']);
$id_venta = !empty($data['id_venta']) ? $data['id_venta'] : null;

if (!in_array($tipo, ['INGRESO', 'EGRESO'])) {
    echo json_encode(['success' => false, 'message' => 'Tipo inválido']);
    exit();
}
if ($monto <= 0) {
    echo json_encode(['success' => false, 'message' => 'El monto debe ser mayor a 0']);
    exit();
}

try {
    // Verificar que la caja existe y está abierta
    $stmtCheck = $conexion->prepare("SELECT id_caja, estado FROM caja WHERE id_caja = :id_caja AND estado = 'ABIERTA'");
    $stmtCheck->execute([':id_caja' => $id_caja]);
    if (!$stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'La caja no existe o no está abierta']);
        exit();
    }
    
    $stmt = $conexion->prepare("
        INSERT INTO movimiento_caja (id_caja, tipo, monto, concepto, id_venta, fecha)
        VALUES (:id_caja, :tipo, :monto, :concepto, :id_venta, NOW())
    ");
    $stmt->execute([
        ':id_caja' => $id_caja,
        ':tipo' => $tipo,
        ':monto' => $monto,
        ':concepto' => $concepto,
        ':id_venta' => $id_venta
    ]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>