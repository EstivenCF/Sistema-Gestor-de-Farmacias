<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$id_venta = $_GET['id_venta'] ?? 0;
if (!$id_venta) {
    echo json_encode(['success' => false, 'message' => 'ID de venta no proporcionado']);
    exit();
}

// Obtener datos de la venta
$stmt = $conexion->prepare("
    SELECT v.*, c.nombre as cliente, u.nombre as vendedor
    FROM ventas v
    LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
    LEFT JOIN usuarios u ON v.id_usuario = u.id_usuario
    WHERE v.id_venta = :id_venta
");
$stmt->execute([':id_venta' => $id_venta]);
$venta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venta) {
    echo json_encode(['success' => false, 'message' => 'Venta no encontrada']);
    exit();
}

// Obtener detalles de productos (medicamentos o productos generales)
$stmtDet = $conexion->prepare("
    SELECT dv.cantidad, dv.precio_unitario, dv.descuento_unitario, dv.subtotal,
           COALESCE(m.nombre_completo, p.nombre) as producto_nombre
    FROM detalle_venta dv
    LEFT JOIN lotes l ON dv.id_lote = l.id_lote
    LEFT JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
    LEFT JOIN productos p ON dv.id_producto = p.id_producto
    WHERE dv.id_venta = :id_venta
");
$stmtDet->execute([':id_venta' => $id_venta]);
$detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'venta' => [
        'numero_documento' => $venta['numero_documento'],
        'fecha' => date('d/m/Y H:i', strtotime($venta['fecha'])),
        'cliente' => $venta['cliente'],
        'vendedor' => $venta['vendedor'],
        'subtotal' => $venta['subtotal'],
        'descuento_total' => $venta['descuento_total'],
        'itbis_total' => $venta['itbis_total'],
        'total' => $venta['total'],
        'es_credito' => $venta['es_credito'],
        'estado_pago' => $venta['estado_pago'],
        'abonos_acumulados' => $venta['abonos_acumulados']
    ],
    'detalles' => $detalles
]);
?>