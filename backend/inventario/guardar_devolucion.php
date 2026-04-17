<?php
require_once __DIR__ . '/../conexion.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Si solo se envía id_devolucion e id_estado => es una actualización de estado
if (isset($data['id_devolucion']) && isset($data['id_estado']) && !isset($data['id_tipo'])) {
    $id_devolucion = intval($data['id_devolucion']);
    $id_estado = intval($data['id_estado']);
    
    try {
        $conexion->beginTransaction();
        
        // Actualizar solo el estado
        $stmt = $conexion->prepare("UPDATE devoluciones SET id_estado = ? WHERE id_devolucion = ?");
        $stmt->execute([$id_estado, $id_devolucion]);
        
        // Si el estado cambia a APROBADA (2) o COMPLETADA (4), aquí podrías agregar lógica de ajuste de inventario y reembolso
        // (opcional, según tu flujo de negocio)
        
        $conexion->commit();
        echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
    } catch (Exception $e) {
        $conexion->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Si no, es una creación/edición completa (como antes)
if (!$data || !isset($data['id_tipo'], $data['fecha'], $data['id_sucursal'], $data['motivo'], $data['detalles'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos para creación/edición completa']);
    exit;
}

$id_devolucion = $data['id_devolucion'] ?? null;
$id_tipo = intval($data['id_tipo']);
$fecha = $data['fecha'];
$id_sucursal = intval($data['id_sucursal']);
$id_estado = intval($data['id_estado']);
$motivo = trim($data['motivo']);
$id_cliente = isset($data['id_cliente']) ? intval($data['id_cliente']) : null;
$id_proveedor = isset($data['id_proveedor']) ? intval($data['id_proveedor']) : null;
$monto_reembolso = floatval($data['monto_reembolso']);
$detalles = $data['detalles']; // array de {id_lote, cantidad, precio_unitario}
$id_usuario = $_SESSION['id_sesion'];

try {
    $conexion->beginTransaction();
    
    if ($id_devolucion) {
        // Actualizar cabecera existente
        $stmt = $conexion->prepare("
            UPDATE devoluciones 
            SET id_tipo = ?, fecha_solicitud = ?, id_sucursal = ?, id_estado = ?, motivo = ?, 
                id_cliente = ?, id_proveedor = ?, monto_reembolso = ?
            WHERE id_devolucion = ?
        ");
        $stmt->execute([$id_tipo, $fecha, $id_sucursal, $id_estado, $motivo, $id_cliente, $id_proveedor, $monto_reembolso, $id_devolucion]);
        
        // Eliminar detalles antiguos y volver a insertar
        $stmt = $conexion->prepare("DELETE FROM detalle_devolucion WHERE id_devolucion = ?");
        $stmt->execute([$id_devolucion]);
    } else {
        // Insertar nueva devolución
        $numero_documento = 'DEV-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM devoluciones WHERE numero_documento = ?");
        $stmt->execute([$numero_documento]);
        if ($stmt->fetchColumn() > 0) {
            $numero_documento = 'DEV-' . date('YmdHis') . mt_rand(10, 99);
        }
        
        $stmt = $conexion->prepare("
            INSERT INTO devoluciones 
            (numero_documento, id_venta, id_cliente, id_proveedor, id_sucursal, fecha_solicitud, motivo, id_usuario, id_tipo, id_estado, monto_reembolso)
            VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$numero_documento, $id_cliente, $id_proveedor, $id_sucursal, $fecha, $motivo, $id_usuario, $id_tipo, $id_estado, $monto_reembolso]);
        $id_devolucion = $conexion->lastInsertId();
    }
    
    // Insertar detalles (si los hay)
    foreach ($detalles as $det) {
        $stmt = $conexion->prepare("
            INSERT INTO detalle_devolucion (id_devolucion, id_lote, cantidad, precio_unitario)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$id_devolucion, $det['id_lote'], $det['cantidad'], $det['precio_unitario']]);
    }
    
    $conexion->commit();
    echo json_encode(['success' => true, 'message' => $id_devolucion ? 'Devolución actualizada' : 'Devolución creada']);
} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>