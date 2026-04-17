<?php
require_once __DIR__ . '/../conexion.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['id_venta'], $data['motivo'], $data['id_tipo'], $data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$id_venta = intval($data['id_venta']);
$motivo = trim($data['motivo']);
$id_tipo = intval($data['id_tipo']);
$id_usuario = $_SESSION['id_sesion'];
$items = $data['items']; // array de {id_detalle_venta, cantidad}

try {
    $conexion->beginTransaction();

    // Obtener información de la venta: cliente, sucursal
    $stmt = $conexion->prepare("SELECT id_cliente, id_sucursal FROM ventas WHERE id_venta = ?");
    $stmt->execute([$id_venta]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$venta) {
        throw new Exception('Venta no encontrada');
    }
    $id_cliente = $venta['id_cliente'];
    $id_sucursal = $venta['id_sucursal'];

    // Generar número de documento para la devolución
    $numero_documento = 'DEV-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM devoluciones WHERE numero_documento = ?");
    $stmt->execute([$numero_documento]);
    if ($stmt->fetchColumn() > 0) {
        $numero_documento = 'DEV-' . date('YmdHis') . mt_rand(10, 99);
    }

    // Insertar cabecera de devolución con estado SOLICITADA (id_estado = 1)
    $stmt = $conexion->prepare("
        INSERT INTO devoluciones 
        (numero_documento, id_venta, id_cliente, id_sucursal, fecha_solicitud, motivo, id_usuario, id_tipo, id_estado, monto_reembolso)
        VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, 1, 0)
    ");
    $stmt->execute([$numero_documento, $id_venta, $id_cliente, $id_sucursal, $motivo, $id_usuario, $id_tipo]);
    $id_devolucion = $conexion->lastInsertId();

    $monto_total_devuelto = 0;

    // Procesar cada item
    foreach ($items as $item) {
        $id_detalle = intval($item['id_detalle_venta']);
        $cantidad = intval($item['cantidad']);
        if ($cantidad <= 0) continue;

        // Obtener detalle de venta: id_lote, precio_unitario
        $stmt = $conexion->prepare("
            SELECT id_lote, precio_unitario 
            FROM detalle_venta 
            WHERE id_detalle = ? AND id_venta = ?
        ");
        $stmt->execute([$id_detalle, $id_venta]);
        $detalle = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$detalle) {
            throw new Exception("Detalle de venta no encontrado: $id_detalle");
        }
        $precio_unitario = $detalle['precio_unitario'];
        $id_lote = $detalle['id_lote'];

        $monto_item = $precio_unitario * $cantidad;
        $monto_total_devuelto += $monto_item;

        // Insertar detalle_devolucion
        $stmt = $conexion->prepare("
            INSERT INTO detalle_devolucion (id_devolucion, id_lote, cantidad, precio_unitario)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$id_devolucion, $id_lote, $cantidad, $precio_unitario]);

        // NOTA: No se ajusta inventario ni se crea reembolso hasta que la devolución sea APROBADA.
        // Por ahora solo registramos la solicitud.
    }

    // Actualizar monto_reembolso en la devolución (monto solicitado)
    $stmt = $conexion->prepare("UPDATE devoluciones SET monto_reembolso = ? WHERE id_devolucion = ?");
    $stmt->execute([$monto_total_devuelto, $id_devolucion]);

    // No se crea reembolso automático, ni se ajusta inventario. Eso se hará cuando un supervisor apruebe.
    // Tampoco se modifica saldo de crédito del cliente.

    $conexion->commit();
    echo json_encode(['success' => true, 'message' => 'Solicitud de devolución registrada correctamente. Queda pendiente de aprobación.', 'id_devolucion' => $id_devolucion]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>