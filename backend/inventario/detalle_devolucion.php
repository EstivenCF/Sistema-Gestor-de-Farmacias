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
            td.id_tipo,
            td.nombre as tipo_nombre,
            ed.id_estado,
            ed.nombre as estado_nombre,
            s.id_sucursal,
            s.nombre as sucursal_nombre,
            c.id_cliente,
            c.nombre as cliente_nombre,
            p.id_proveedor,
            p.nombre as proveedor_nombre
        FROM devoluciones d
        LEFT JOIN tipo_devolucion td ON d.id_tipo = td.id_tipo
        LEFT JOIN estado_devolucion ed ON d.id_estado = ed.id_estado
        LEFT JOIN sucursales s ON d.id_sucursal = s.id_sucursal
        LEFT JOIN clientes c ON d.id_cliente = c.id_cliente
        LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor
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
            dd.precio_unitario,
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