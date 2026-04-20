<?php
require_once __DIR__ . '/../conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$id_compra = $_GET['id_compra'] ?? 0;
if (!$id_compra) {
    echo json_encode(['success' => false, 'message' => 'ID de compra no proporcionado']);
    exit();
}

try {
    // Obtener datos de la compra
    $stmt = $conexion->prepare("
        SELECT 
            c.id_compra,
            c.numero_documento,
            c.fecha,
            c.subtotal,
            c.descuento,
            c.itbis,
            c.total,
            c.observaciones,
            c.fecha_esperada,
            p.nombre as proveedor_nombre,
            s.nombre as sucursal_nombre,
            u.nombre as usuario_nombre,
            ec.nombre as estado_nombre
        FROM compras c
        JOIN proveedores p ON c.id_proveedor = p.id_proveedor
        JOIN sucursales s ON c.id_sucursal = s.id_sucursal
        JOIN usuarios u ON c.id_usuario = u.id_usuario
        JOIN estado_compra ec ON c.id_estado = ec.id_estado
        WHERE c.id_compra = :id
    ");
    $stmt->execute([':id' => $id_compra]);
    $compra = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$compra) {
        echo json_encode(['success' => false, 'message' => 'Compra no encontrada']);
        exit();
    }
    
    // Obtener productos de la compra (MEDICAMENTOS)
    $stmt = $conexion->prepare("
        SELECT 
            dc.id_detalle,
            'MEDICAMENTO' as tipo,
            m.id_medicamento as id_producto,
            m.nombre as producto_nombre,
            dc.cantidad,
            COALESCE(dc.cantidad_recibida, 0) as cantidad_recibida,
            (dc.cantidad - COALESCE(dc.cantidad_recibida, 0)) as cantidad_pendiente,
            dc.precio_unitario,
            dc.descuento_unitario,
            dc.itbis_unitario,
            dc.numero_lote_solicitado as numero_lote,
            dc.fecha_vencimiento_solicitada as fecha_vencimiento,
            (dc.cantidad * dc.precio_unitario) as subtotal
        FROM detalle_compra dc
        JOIN medicamentos m ON dc.id_medicamento = m.id_medicamento
        WHERE dc.id_compra = :id AND dc.id_medicamento IS NOT NULL
    ");
    $stmt->execute([':id' => $id_compra]);
    $medicamentos = $stmt->fetchAll();
    
    // Obtener productos de la compra (ROPA)
    $stmt = $conexion->prepare("
        SELECT 
            dc.id_detalle,
            'ROPA' as tipo,
            p.id_producto as id_producto,
            p.nombre as producto_nombre,
            t.id_talla,
            t.nombre as talla_nombre,
            c.id_color,
            c.nombre as color_nombre,
            dc.cantidad,
            COALESCE(dc.cantidad_recibida, 0) as cantidad_recibida,
            (dc.cantidad - COALESCE(dc.cantidad_recibida, 0)) as cantidad_pendiente,
            dc.precio_unitario,
            dc.descuento_unitario,
            dc.itbis_unitario,
            (dc.cantidad * dc.precio_unitario) as subtotal
        FROM detalle_compra dc
        JOIN productos p ON dc.id_producto = p.id_producto
        LEFT JOIN tallas t ON dc.id_talla = t.id_talla
        LEFT JOIN colores c ON dc.id_color = c.id_color
        WHERE dc.id_compra = :id AND dc.id_producto IS NOT NULL
    ");
    $stmt->execute([':id' => $id_compra]);
    $ropa = $stmt->fetchAll();
    
    // Combinar productos
    $productos = array_merge($medicamentos, $ropa);
    
    echo json_encode([
        'success' => true,
        'id_compra' => $compra['id_compra'],
        'numero_documento' => $compra['numero_documento'],
        'fecha' => date('d/m/Y H:i', strtotime($compra['fecha'])),
        'fecha_esperada' => $compra['fecha_esperada'] ? date('d/m/Y', strtotime($compra['fecha_esperada'])) : null,
        'subtotal' => $compra['subtotal'],
        'descuento' => $compra['descuento'],
        'itbis' => $compra['itbis'],
        'total' => $compra['total'],
        'observaciones' => $compra['observaciones'],
        'proveedor_nombre' => $compra['proveedor_nombre'],
        'sucursal_nombre' => $compra['sucursal_nombre'],
        'usuario_nombre' => $compra['usuario_nombre'],
        'estado_nombre' => $compra['estado_nombre'],
        'productos' => $productos
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>