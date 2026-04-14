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
$id_sucursal = isset($data['id_sucursal']) ? (int)$data['id_sucursal'] : 0;
$tipo = isset($data['tipo']) ? $data['tipo'] : '';
$cantidad = isset($data['cantidad']) ? (int)$data['cantidad'] : 0;
$motivo = trim($data['motivo'] ?? '');
$observaciones = trim($data['observaciones'] ?? '');

if (!$id_lote || !$id_sucursal || !$tipo || $cantidad <= 0 || !$motivo) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

if ($tipo !== 'ENTRADA' && $tipo !== 'SALIDA') {
    echo json_encode(['success' => false, 'message' => 'Tipo de ajuste inválido']);
    exit();
}

try {
    $conexion->beginTransaction();
    
    if ($tipo === 'SALIDA') {
        // Verificar stock disponible
        $stmt = $conexion->prepare("SELECT cantidad FROM inventario WHERE id_lote = :id_lote AND id_sucursal = :id_sucursal");
        $stmt->execute([':id_lote' => $id_lote, ':id_sucursal' => $id_sucursal]);
        $stock_actual = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stock_actual || $stock_actual['cantidad'] < $cantidad) {
            $conexion->rollBack();
            echo json_encode(['success' => false, 'message' => 'Stock insuficiente para realizar la salida']);
            exit();
        }
        
        // Restar stock
        $stmt = $conexion->prepare("UPDATE inventario SET cantidad = cantidad - :cantidad WHERE id_lote = :id_lote AND id_sucursal = :id_sucursal");
        $stmt->execute([':cantidad' => $cantidad, ':id_lote' => $id_lote, ':id_sucursal' => $id_sucursal]);
        
    } else {
        // Sumar stock
        $stmt = $conexion->prepare("INSERT INTO inventario (id_lote, id_sucursal, cantidad) 
                                    VALUES (:id_lote, :id_sucursal, :cantidad)
                                    ON CONFLICT (id_lote, id_sucursal) 
                                    DO UPDATE SET cantidad = inventario.cantidad + :cantidad");
        $stmt->execute([':id_lote' => $id_lote, ':id_sucursal' => $id_sucursal, ':cantidad' => $cantidad]);
    }
    
    // Registrar movimiento
    $stmt = $conexion->prepare("INSERT INTO movimiento_inventario (id_lote, id_sucursal, tipo, cantidad, motivo, referencia, id_usuario, observaciones) 
                                VALUES (:id_lote, :id_sucursal, :tipo, :cantidad, :motivo, 'AJUSTE MANUAL', :id_usuario, :observaciones)");
    $stmt->execute([
        ':id_lote' => $id_lote,
        ':id_sucursal' => $id_sucursal,
        ':tipo' => $tipo,
        ':cantidad' => $cantidad,
        ':motivo' => $motivo,
        ':id_usuario' => $id_usuario_sesion,
        ':observaciones' => $observaciones
    ]);
    
    $conexion->commit();
    echo json_encode(['success' => true, 'message' => "Ajuste de $tipo realizado: $cantidad unidades"]);
    
} catch(PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>