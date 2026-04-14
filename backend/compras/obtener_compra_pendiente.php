<?php
require_once __DIR__ . '/../conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$idCompra = $_GET['id_compra'] ?? 0;

if (!$idCompra) {
    echo json_encode(['success' => false, 'error' => 'ID de compra requerido']);
    exit();
}

try {
    $sqlCompra = "SELECT 
                    c.id_compra,
                    c.numero_documento,
                    c.fecha,
                    c.subtotal,
                    c.itbis,
                    c.total,
                    c.fecha_esperada,
                    c.observaciones,
                    p.id_proveedor,
                    p.nombre as proveedor_nombre,
                    p.rnc as proveedor_rnc,
                    p.direccion as proveedor_direccion
                FROM compras c
                LEFT JOIN proveedores p ON c.id_proveedor = p.id_proveedor
                WHERE c.id_compra = :id_compra";
    
    $stmt = $conexion->prepare($sqlCompra);
    $stmt->execute([':id_compra' => $idCompra]);
    $compra = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$compra) {
        echo json_encode(['success' => false, 'error' => 'Compra no encontrada']);
        exit();
    }
    
    $sqlDetalles = "SELECT 
                        dc.id_detalle,
                        dc.cantidad,
                        dc.precio_unitario,
                        dc.descuento_unitario,
                        l.id_lote,
                        l.numero_lote,
                        l.fecha_vencimiento,
                        m.id_medicamento,
                        m.nombre_completo as medicamento_nombre,
                        COALESCE(rd.cantidad_recibida, 0) as cantidad_recibida
                    FROM detalle_compra dc
                    JOIN lotes l ON dc.id_lote = l.id_lote
                    JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
                    LEFT JOIN recepcion_detalle rd ON dc.id_detalle = rd.id_detalle
                    WHERE dc.id_compra = :id_compra";
    
    $stmt = $conexion->prepare($sqlDetalles);
    $stmt->execute([':id_compra' => $idCompra]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'compra' => $compra,
        'detalles' => $detalles
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>