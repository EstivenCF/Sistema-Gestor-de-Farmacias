<?php
require_once '../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_devolucion = $_GET['id'] ?? 0;
if (!$id_devolucion) {
    echo json_encode(['success' => false, 'message' => 'ID de devolución requerido']);
    exit;
}

try {
    // Cabecera de la devolución
    $stmt = $conexion->prepare("
        SELECT
            d.id_devolucion,
            d.numero_documento,
            d.fecha_solicitud,
            d.fecha_aprobacion,
            d.fecha_completada,
            d.motivo,
            d.monto_reembolso,
            d.observaciones_validacion,
            d.confirmado_por_cliente, d.fecha_confirmacion_cliente, d.nota_cliente,
            d.id_entrega,
            CASE WHEN COALESCE(d.monto_reembolso, 0) = 0 THEN (
                SELECT COALESCE(SUM(dd2.cantidad * COALESCE(
                    dd2.precio_unitario,
                    l2.costo_unitario,
                    (SELECT dc2.precio_unitario FROM detalle_compra dc2 WHERE dc2.id_lote = l2.id_lote LIMIT 1),
                    0
                )), 0)
                FROM detalle_devolucion dd2
                JOIN lotes l2 ON l2.id_lote = dd2.id_lote
                WHERE dd2.id_devolucion = d.id_devolucion
            ) ELSE d.monto_reembolso END AS monto_reembolso,
            td.id_tipo,
            td.nombre as tipo_nombre,
            ed.id_estado,
            ed.nombre as estado_nombre,
            s.id_sucursal,
            s.nombre as sucursal_nombre,
            c.id_cliente,
            c.nombre as cliente_nombre,
            p.id_proveedor,
            p.nombre as proveedor_nombre,
            ua.nombre as verificado_por_nombre,
            e.numero_seguimiento as entrega_seguimiento,
            r.nombre as entrega_repartidor_nombre
        FROM devoluciones d
        LEFT JOIN tipo_devolucion td ON d.id_tipo = td.id_tipo
        LEFT JOIN estado_devolucion ed ON d.id_estado = ed.id_estado
        LEFT JOIN sucursales s ON d.id_sucursal = s.id_sucursal
        LEFT JOIN clientes c ON d.id_cliente = c.id_cliente
        LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor
        LEFT JOIN usuarios ua ON ua.id_usuario = d.aprobado_por
        LEFT JOIN entregas e ON e.id_entrega = d.id_entrega
        LEFT JOIN repartidores r ON r.id_repartidor = e.id_repartidor
        WHERE d.id_devolucion = ?
    ");
    $stmt->execute([$id_devolucion]);
    $devolucion = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$devolucion) {
        echo json_encode(['success' => false, 'message' => 'Devolución no encontrada']);
        exit;
    }

    // Detalle de productos devueltos (corregido: unir con presentaciones)
    $stmt = $conexion->prepare("
        SELECT 
            dd.id_detalle,
            dd.cantidad,
            COALESCE(
                dd.precio_unitario,
                l.costo_unitario,
                (SELECT dc.precio_unitario FROM detalle_compra dc WHERE dc.id_lote = l.id_lote LIMIT 1),
                0
            ) AS precio_unitario,
            l.numero_lote,
            m.nombre_completo as medicamento_nombre,
            pr.nombre as presentacion
        FROM detalle_devolucion dd
        JOIN lotes l ON dd.id_lote = l.id_lote
        JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
        LEFT JOIN presentaciones pr ON m.id_presentacion = pr.id_presentacion
        WHERE dd.id_devolucion = ?
    ");
    $stmt->execute([$id_devolucion]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $devolucion['detalles'] = $detalles;

    echo json_encode(['success' => true, 'devolucion' => $devolucion]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en BD: ' . $e->getMessage()]);
}
?>