<?php
require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion']) && !isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$id_accion = isset($input['id_accion']) ? (int)$input['id_accion'] : 0;
$id_proveedor = isset($input['id_proveedor']) ? (int)$input['id_proveedor'] : 0;
$motivo = trim($input['motivo'] ?? 'Devolución a proveedor');

if (!$id_accion || !$id_proveedor) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    $conexion->beginTransaction();

    // Obtener datos de la acción
    $stmt = $conexion->prepare("SELECT id_lote, id_sucursal_origen, cantidad_afectada, valor_en_riesgo FROM accion_recuperacion WHERE id_accion = :id_accion FOR UPDATE");
    $stmt->execute([':id_accion' => $id_accion]);
    $accion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$accion) {
        throw new Exception('Acción no encontrada');
    }

    $id_lote = (int)$accion['id_lote'];
    $id_sucursal = (int)$accion['id_sucursal_origen'];
    $cantidad = (int)$accion['cantidad_afectada'];

    if ($cantidad <= 0) {
        throw new Exception('Cantidad inválida en la acción');
    }

    // Verificar stock disponible
    $stmt = $conexion->prepare("SELECT cantidad FROM inventario WHERE id_lote = :id_lote AND id_sucursal = :id_sucursal FOR UPDATE");
    $stmt->execute([':id_lote' => $id_lote, ':id_sucursal' => $id_sucursal]);
    $stock = $stmt->fetchColumn();
    if ($stock === false) {
        throw new Exception('No hay inventario registrado para ese lote en la sucursal');
    }
    if ($stock < $cantidad) {
        throw new Exception('Stock insuficiente para completar la devolución');
    }

    // Numero de documento
    $numero_doc = 'DEV-P-' . date('Ymd') . '-' . str_pad($id_accion, 4, '0', STR_PAD_LEFT);

    // Obtener id_tipo for PROVEEDOR y estado COMPLETADA (si existen)
    $stmt = $conexion->prepare("SELECT id_tipo FROM tipo_devolucion WHERE nombre = 'PROVEEDOR'");
    $stmt->execute();
    $id_tipo = $stmt->fetchColumn() ?: null;

    $stmt = $conexion->prepare("SELECT id_estado FROM estado_devolucion WHERE nombre = 'COMPLETADA'");
    $stmt->execute();
    $id_estado_completada = $stmt->fetchColumn() ?: null;

    // Insertar cabecera de devolución
    $stmt = $conexion->prepare("INSERT INTO devoluciones (numero_documento, id_sucursal, motivo, id_usuario, id_tipo, id_estado) VALUES (:num, :suc, :mot, :usuario, :tipo, :estado) RETURNING id_devolucion");
    $stmt->execute([
        ':num' => $numero_doc,
        ':suc' => $id_sucursal,
        ':mot' => $motivo,
        ':usuario' => $_SESSION['id_usuario'] ?? ($_SESSION['usuario_id'] ?? null),
        ':tipo' => $id_tipo,
        ':estado' => $id_estado_completada ?? (int)1
    ]);
    $id_devolucion = $stmt->fetchColumn();

    if (!$id_devolucion) throw new Exception('No se pudo crear la devolución');

    // Insertar detalle_devolucion
    $stmt = $conexion->prepare("INSERT INTO detalle_devolucion (id_devolucion, id_lote, cantidad) VALUES (:id_devolucion, :id_lote, :cantidad)");
    $stmt->execute([':id_devolucion' => $id_devolucion, ':id_lote' => $id_lote, ':cantidad' => $cantidad]);

    // Registrar movimiento de salida y ajustar inventario
    $stmt = $conexion->prepare("INSERT INTO movimiento_inventario (id_lote, id_sucursal, tipo, cantidad, motivo, referencia, id_usuario) VALUES (:id_lote, :id_sucursal, 'SALIDA', :cantidad, :motivo, 'DEVOLUCION_PROVEEDOR', :id_usuario) RETURNING id_movimiento");
    $stmt->execute([
        ':id_lote' => $id_lote,
        ':id_sucursal' => $id_sucursal,
        ':cantidad' => $cantidad,
        ':motivo' => $motivo,
        ':id_usuario' => $_SESSION['id_usuario'] ?? ($_SESSION['usuario_id'] ?? null)
    ]);
    $id_movimiento = $stmt->fetchColumn();

    $stmt = $conexion->prepare("UPDATE inventario SET cantidad = cantidad - :cantidad WHERE id_lote = :id_lote AND id_sucursal = :id_sucursal");
    $stmt->execute([':cantidad' => $cantidad, ':id_lote' => $id_lote, ':id_sucursal' => $id_sucursal]);

    // Marcar la acción como COMPLETADA
    $stmt = $conexion->prepare("UPDATE accion_recuperacion SET estado = 'COMPLETADA', id_movimiento = :id_mov, fecha_ejecucion = NOW(), valor_recuperado_estimado = valor_en_riesgo WHERE id_accion = :id_accion");
    $stmt->execute([':id_mov' => $id_movimiento, ':id_accion' => $id_accion]);

    $conexion->commit();
    echo json_encode(['success' => true, 'id_devolucion' => (int)$id_devolucion]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
