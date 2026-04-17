<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$id_venta = isset($_GET['id_venta']) ? (int)$_GET['id_venta'] : 0;
if (!$id_venta) {
    echo json_encode(['success' => false, 'message' => 'ID de venta inválido']);
    exit();
}

try {
    // Datos de la venta (sin LEFT JOIN a descuentos)
    $sql = "SELECT v.*, 
                   COALESCE(c.nombre, 'Consumidor Final') as cliente_nombre,
                   u.nombre as vendedor_nombre,
                   cp.nombre as condicion_pago
            FROM ventas v
            LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
            LEFT JOIN usuarios u ON v.id_usuario = u.id_usuario
            LEFT JOIN condicion_pago cp ON v.id_condicion = cp.id_condicion
            WHERE v.id_venta = :id";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id' => $id_venta]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$venta) {
        echo json_encode(['success' => false, 'message' => 'Venta no encontrada']);
        exit();
    }

    // Productos
    $sqlProd = "SELECT dv.*, 
                       COALESCE(m.nombre_completo, p.nombre) as producto_nombre
                FROM detalle_venta dv
                LEFT JOIN lotes l ON dv.id_lote = l.id_lote
                LEFT JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
                LEFT JOIN productos p ON dv.id_producto = p.id_producto
                WHERE dv.id_venta = :id";
    $stmt = $conexion->prepare($sqlProd);
    $stmt->execute([':id' => $id_venta]);
    $productos = $stmt->fetchAll();

    // Pagos
    $sqlPagos = "SELECT p.*, mp.nombre as metodo_nombre
                 FROM pagos p
                 LEFT JOIN metodos_pago mp ON p.id_metodo = mp.id_metodo
                 WHERE p.id_venta = :id
                 ORDER BY p.fecha_transaccion DESC";
    $stmt = $conexion->prepare($sqlPagos);
    $stmt->execute([':id' => $id_venta]);
    $pagos = $stmt->fetchAll();

    // Delivery
    $sqlDelivery = "SELECT e.id_entrega, e.numero_seguimiento, e.direccion_entrega, 
                           e.costo_entrega, e.cliente_nombre, e.fecha_asignada, e.fecha_entrega_real,
                           e.observaciones,
                           ee.nombre as estado_entrega,
                           r.id_repartidor, r.nombre as repartidor_nombre,
                           t.numero as repartidor_telefono
                    FROM entregas e
                    LEFT JOIN estado_entrega ee ON e.id_estado = ee.id_estado
                    LEFT JOIN repartidores r ON e.id_repartidor = r.id_repartidor
                    LEFT JOIN repartidor_telefono rt ON r.id_repartidor = rt.id_repartidor
                    LEFT JOIN telefonos t ON rt.id_telefono = t.id_telefono AND t.activo = true
                    WHERE e.id_venta = :id_venta";
    $stmt = $conexion->prepare($sqlDelivery);
    $stmt->execute([':id_venta' => $id_venta]);
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);

    $venta['fecha'] = date('d/m/Y H:i', strtotime($venta['fecha']));
    $venta['fecha_vencimiento_pago'] = $venta['fecha_vencimiento_pago'] ? date('d/m/Y', strtotime($venta['fecha_vencimiento_pago'])) : null;

    echo json_encode([
        'success' => true,
        'id_venta' => $venta['id_venta'],
        'numero_documento' => $venta['numero_documento'],
        'fecha' => $venta['fecha'],
        'cliente_nombre' => $venta['cliente_nombre'],
        'vendedor_nombre' => $venta['vendedor_nombre'],
        'condicion_pago' => $venta['condicion_pago'],
        'subtotal' => (float)$venta['subtotal'],
        'itbis_total' => (float)$venta['itbis_total'],
        'descuento_total' => (float)$venta['descuento_total'],
        'total' => (float)$venta['total'],
        'es_credito' => (bool)$venta['es_credito'],
        'estado_pago' => $venta['estado_pago'],
        'abonos_acumulados' => (float)$venta['abonos_acumulados'],
        'fecha_vencimiento_pago' => $venta['fecha_vencimiento_pago'],
        'ncf' => $venta['ncf'],
        'usa_seguro' => (bool)$venta['usa_seguro'],
        'monto_cubre_seguro' => (float)$venta['monto_cubre_seguro'],
        'monto_paga_paciente' => (float)$venta['monto_paga_paciente'],
        'productos' => $productos,
        'pagos' => $pagos,
        'entrega' => $entrega ?: null
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en BD: ' . $e->getMessage()]);
}
?>