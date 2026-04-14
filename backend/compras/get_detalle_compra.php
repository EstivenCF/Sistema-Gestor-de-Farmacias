<?php
require_once __DIR__ . '/../conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

$id_compra = isset($_GET['id_compra']) ? (int)$_GET['id_compra'] : 0;

if (!$id_compra) {
    echo json_encode(['success' => false, 'message' => 'ID de compra inválido']);
    exit();
}

try {
    $sql = "SELECT 
                c.id_compra,
                c.numero_documento,
                TO_CHAR(c.fecha, 'DD/MM/YYYY HH24:MI') as fecha,
                c.subtotal,
                c.descuento,
                c.itbis,
                c.total,
                c.observaciones,
                TO_CHAR(c.fecha_esperada, 'DD/MM/YYYY') as fecha_esperada,
                p.nombre as proveedor_nombre,
                s.nombre as sucursal_nombre,
                u.nombre as usuario_nombre,
                ec.nombre as estado_nombre
            FROM compras c
            JOIN proveedores p ON c.id_proveedor = p.id_proveedor
            JOIN sucursales s ON c.id_sucursal = s.id_sucursal
            JOIN usuarios u ON c.id_usuario = u.id_usuario
            JOIN estado_compra ec ON c.id_estado = ec.id_estado
            WHERE c.id_compra = :id";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id' => $id_compra]);
    $compra = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$compra) {
        echo json_encode(['success' => false, 'message' => 'Compra no encontrada']);
        exit();
    }
    
    $sqlDetalle = "SELECT 
                        dc.id_detalle,
                        dc.id_medicamento,
                        dc.cantidad,
                        COALESCE(dc.cantidad_recibida, 0) as cantidad_recibida,
                        (dc.cantidad - COALESCE(dc.cantidad_recibida, 0)) as cantidad_pendiente,
                        dc.precio_unitario,
                        dc.descuento_unitario,
                        dc.itbis_unitario,
                        dc.numero_lote_solicitado,
                        TO_CHAR(dc.fecha_vencimiento_solicitada, 'YYYY-MM-DD') as fecha_vencimiento_solicitada,
                        m.nombre_completo as producto_nombre
                    FROM detalle_compra dc
                    JOIN medicamentos m ON dc.id_medicamento = m.id_medicamento
                    WHERE dc.id_compra = :id_compra
                      AND (dc.cantidad - COALESCE(dc.cantidad_recibida, 0)) > 0";
    
    $stmt = $conexion->prepare($sqlDetalle);
    $stmt->execute([':id_compra' => $id_compra]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'id_compra' => (int)$compra['id_compra'],
        'numero_documento' => $compra['numero_documento'],
        'fecha' => $compra['fecha'],
        'subtotal' => (float)$compra['subtotal'],
        'descuento' => (float)$compra['descuento'],
        'itbis' => (float)$compra['itbis'],
        'total' => (float)$compra['total'],
        'observaciones' => $compra['observaciones'],
        'fecha_esperada' => $compra['fecha_esperada'],
        'proveedor_nombre' => $compra['proveedor_nombre'],
        'sucursal_nombre' => $compra['sucursal_nombre'],
        'usuario_nombre' => $compra['usuario_nombre'],
        'estado_nombre' => $compra['estado_nombre'],
        'productos' => $productos
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>