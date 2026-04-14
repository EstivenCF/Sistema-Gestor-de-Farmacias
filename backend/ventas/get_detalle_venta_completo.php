<?php
require_once __DIR__ . '/../conexion.php';
session_start();
header('Content-Type: application/json');

$id_venta = $_GET['id_venta'] ?? 0;
if (!$id_venta) {
    echo json_encode(['success' => false, 'message' => 'ID de venta requerido']);
    exit();
}

try {
    // Datos principales de la venta (incluyendo descuento)
    $stmt = $conexion->prepare("
        SELECT 
            v.id_venta, v.numero_documento, 
            TO_CHAR(v.fecha, 'DD/MM/YYYY HH24:MI:SS') as fecha,
            v.subtotal, v.itbis_total, v.descuento_total, v.total,
            v.es_credito, v.abonos_acumulados,
            v.usa_seguro, v.monto_cubre_seguro,
            COALESCE(c.nombre, 'Consumidor Final') as cliente_nombre,
            u.nombre as vendedor_nombre,
            cp.nombre as condicion_pago,
            v.ncf,
            v.estado_pago,
            v.estado_fiscal,
            d.nombre as descuento_nombre
        FROM ventas v
        LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
        JOIN usuarios u ON v.id_usuario = u.id_usuario
        JOIN condicion_pago cp ON v.id_condicion = cp.id_condicion
        LEFT JOIN venta_descuento vd ON v.id_venta = vd.id_venta
        LEFT JOIN descuentos d ON vd.id_descuento = d.id_descuento
        WHERE v.id_venta = :id_venta
    ");
    $stmt->execute([':id_venta' => $id_venta]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$venta) {
        echo json_encode(['success' => false, 'message' => 'Venta no encontrada']);
        exit();
    }
    
    // Detalle de productos
    $stmtProd = $conexion->prepare("
        SELECT 
            dv.cantidad, dv.precio_unitario, dv.subtotal,
            m.nombre_completo as producto_nombre
        FROM detalle_venta dv
        JOIN medicamentos m ON dv.id_producto = m.id_medicamento
        WHERE dv.id_venta = :id_venta
    ");
    $stmtProd->execute([':id_venta' => $id_venta]);
    $productos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);
    
    // Historial de pagos
    $stmtPagos = $conexion->prepare("
        SELECT 
            p.monto, p.referencia, p.estado,
            TO_CHAR(p.fecha_transaccion, 'DD/MM/YYYY HH24:MI:SS') as fecha,
            mp.nombre as metodo_nombre
        FROM pagos p
        JOIN metodos_pago mp ON p.id_metodo = mp.id_metodo
        WHERE p.id_venta = :id_venta
        ORDER BY p.fecha_transaccion ASC
    ");
    $stmtPagos->execute([':id_venta' => $id_venta]);
    $pagos = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'id_venta' => $venta['id_venta'],
        'numero_documento' => $venta['numero_documento'],
        'fecha' => $venta['fecha'],
        'subtotal' => floatval($venta['subtotal']),
        'itbis_total' => floatval($venta['itbis_total']),
        'descuento_total' => floatval($venta['descuento_total'] ?? 0),
        'descuento_nombre' => $venta['descuento_nombre'] ?? '',
        'total' => floatval($venta['total']),
        'es_credito' => $venta['es_credito'] == 't',
        'abonos_acumulados' => floatval($venta['abonos_acumulados'] ?? 0),
        'usa_seguro' => $venta['usa_seguro'] == 't',
        'monto_cubre_seguro' => floatval($venta['monto_cubre_seguro'] ?? 0),
        'cliente_nombre' => $venta['cliente_nombre'],
        'vendedor_nombre' => $venta['vendedor_nombre'],
        'condicion_pago' => $venta['condicion_pago'],
        'ncf' => $venta['ncf'],
        'estado_pago' => $venta['estado_pago'],
        'estado_fiscal' => $venta['estado_fiscal'],
        'productos' => $productos,
        'pagos' => $pagos
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>