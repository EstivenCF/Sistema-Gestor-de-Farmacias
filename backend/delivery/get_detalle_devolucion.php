<?php
// backend/delivery/get_detalle_devolucion.php
// NUEVO — para el botón "Ver Detalle" en la pantalla Devoluciones:
// qué productos se devolvieron, cuántos, y a qué precio.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$id_devolucion = intval($_GET['id_devolucion'] ?? 0);
if (!$id_devolucion) {
    echo json_encode(['success' => false, 'message' => 'ID de devolución requerido']); exit();
}

try {
    $stmt = $conexion->prepare("
        SELECT
            d.id_devolucion, d.numero_documento, d.fecha_solicitud, d.fecha_aprobacion,
            d.fecha_completada, d.motivo, d.monto_reembolso, d.es_por_recall,
            d.observaciones_validacion,
            d.confirmado_por_cliente, d.fecha_confirmacion_cliente, d.nota_cliente,
            td.nombre AS tipo_nombre,
            ed.nombre AS estado_nombre,
            c.nombre  AS cliente_nombre,
            v.numero_documento AS venta_documento,
            u.nombre  AS solicitado_por,
            ap.nombre AS aprobado_por,
            e.numero_seguimiento AS entrega_seguimiento
        FROM devoluciones d
        LEFT JOIN tipo_devolucion td   ON td.id_tipo   = d.id_tipo
        LEFT JOIN estado_devolucion ed ON ed.id_estado = d.id_estado
        LEFT JOIN clientes c           ON c.id_cliente = d.id_cliente
        LEFT JOIN ventas v             ON v.id_venta   = d.id_venta
        LEFT JOIN usuarios u           ON u.id_usuario = d.id_usuario
        LEFT JOIN usuarios ap          ON ap.id_usuario = d.aprobado_por
        LEFT JOIN entregas e            ON e.id_entrega = d.id_entrega
        WHERE d.id_devolucion = :id
    ");
    $stmt->execute([':id' => $id_devolucion]);
    $devolucion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$devolucion) {
        echo json_encode(['success' => false, 'message' => 'Devolución no encontrada']); exit();
    }

    $stmt = $conexion->prepare("
        SELECT
            dd.id_detalle, dd.cantidad, dd.precio_unitario,
            (dd.cantidad * dd.precio_unitario) AS subtotal,
            l.numero_lote, l.fecha_vencimiento,
            COALESCE(m.nombre_completo, m.nombre) AS producto_nombre
        FROM detalle_devolucion dd
        LEFT JOIN lotes l        ON l.id_lote       = dd.id_lote
        LEFT JOIN medicamentos m ON m.id_medicamento = l.id_medicamento
        WHERE dd.id_devolucion = :id
        ORDER BY dd.id_detalle
    ");
    $stmt->execute([':id' => $id_devolucion]);
    $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'devolucion' => $devolucion, 'detalle' => $detalle]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
