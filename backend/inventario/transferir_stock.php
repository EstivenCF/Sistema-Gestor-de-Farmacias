<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

require_once __DIR__ . '/../conexion.php';

$id_usuario_sesion = $_SESSION['id_usuario'] ?? $_SESSION['usuario_id'] ?? 1;

$data = json_decode(file_get_contents('php://input'), true);

$id_lote = isset($data['id_lote']) ? (int)$data['id_lote'] : 0;
$id_sucursal_origen = isset($data['id_sucursal_origen']) ? (int)$data['id_sucursal_origen'] : 0;
$id_sucursal_destino = isset($data['id_sucursal_destino']) ? (int)$data['id_sucursal_destino'] : 0;
$cantidad = isset($data['cantidad']) ? (int)$data['cantidad'] : 0;
$motivo = trim($data['motivo'] ?? 'Transferencia entre sucursales');

if (!$id_lote || !$id_sucursal_origen || !$id_sucursal_destino || $cantidad <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

if ($id_sucursal_origen === $id_sucursal_destino) {
    echo json_encode(['success' => false, 'message' => 'La sucursal origen y destino no pueden ser la misma']);
    exit();
}

try {
    $conexion->beginTransaction();
    
    // Verificar stock disponible en origen
    $stmt = $conexion->prepare("SELECT cantidad FROM inventario WHERE id_lote = :id_lote AND id_sucursal = :id_sucursal");
    $stmt->execute([':id_lote' => $id_lote, ':id_sucursal' => $id_sucursal_origen]);
    $stock_origen = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stock_origen || $stock_origen['cantidad'] < $cantidad) {
        $conexion->rollBack();
        echo json_encode(['success' => false, 'message' => 'Stock insuficiente en sucursal origen']);
        exit();
    }
    
    // Restar stock en origen
    $stmt = $conexion->prepare("UPDATE inventario SET cantidad = cantidad - :cantidad WHERE id_lote = :id_lote AND id_sucursal = :id_sucursal");
    $stmt->execute([':cantidad' => $cantidad, ':id_lote' => $id_lote, ':id_sucursal' => $id_sucursal_origen]);
    
    // Sumar stock en destino
    $stmt = $conexion->prepare("INSERT INTO inventario (id_lote, id_sucursal, cantidad) 
                                VALUES (:id_lote, :id_sucursal, :cantidad)
                                ON CONFLICT (id_lote, id_sucursal) 
                                DO UPDATE SET cantidad = inventario.cantidad + :cantidad");
    $stmt->execute([':id_lote' => $id_lote, ':id_sucursal' => $id_sucursal_destino, ':cantidad' => $cantidad]);
    
    // Registrar movimiento de salida en origen
    $stmt = $conexion->prepare("INSERT INTO movimiento_inventario (id_lote, id_sucursal, tipo, cantidad, motivo, referencia, id_usuario) 
                                VALUES (:id_lote, :id_sucursal, 'SALIDA', :cantidad, :motivo, 'TRANSFERENCIA', :id_usuario)");
    $stmt->execute([
        ':id_lote' => $id_lote,
        ':id_sucursal' => $id_sucursal_origen,
        ':cantidad' => $cantidad,
        ':motivo' => $motivo,
        ':id_usuario' => $id_usuario_sesion
    ]);
    
    // Registrar movimiento de entrada en destino
    $stmt = $conexion->prepare("INSERT INTO movimiento_inventario (id_lote, id_sucursal, tipo, cantidad, motivo, referencia, id_usuario) 
                                VALUES (:id_lote, :id_sucursal, 'ENTRADA', :cantidad, :motivo, 'TRANSFERENCIA', :id_usuario)");
    $stmt->execute([
        ':id_lote' => $id_lote,
        ':id_sucursal' => $id_sucursal_destino,
        ':cantidad' => $cantidad,
        ':motivo' => $motivo,
        ':id_usuario' => $id_usuario_sesion
    ]);
    
    $conexion->commit();
    echo json_encode(['success' => true, 'message' => "$cantidad unidades transferidas correctamente"]);
    
} catch(PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>