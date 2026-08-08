<?php
require_once '../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_compra = $_GET['id_compra'] ?? 0;
if (!$id_compra) {
    echo json_encode(['success' => false, 'message' => 'ID de compra requerido']);
    exit;
}

try {
    // Obtener cabecera de la compra
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
        WHERE c.id_compra = ?
    ");
    $stmt->execute([$id_compra]);
    $compra = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$compra) {
        echo json_encode(['success' => false, 'message' => 'Compra no encontrada']);
        exit;
    }

    // Obtener productos con sus lotes y cantidades recibidas
    $stmt = $conexion->prepare("
        SELECT 
            dc.id_detalle,
            dc.cantidad,
            dc.cantidad_recibida,
            dc.precio_unitario,
            m.nombre_completo as producto_nombre,
            l.id_lote,
            l.numero_lote,
            l.fecha_vencimiento,
            COALESCE(dc.cantidad_recibida, 0) as recibido
        FROM detalle_compra dc
        JOIN medicamentos m ON dc.id_medicamento = m.id_medicamento
        LEFT JOIN lotes l ON dc.id_lote = l.id_lote
        WHERE dc.id_compra = ?
        ORDER BY m.nombre_completo
    ");
    $stmt->execute([$id_compra]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Para cada producto que tiene cantidad recibida, obtener la distribución por sucursal
    foreach ($productos as &$prod) {
        if ($prod['recibido'] > 0 && $prod['id_lote']) {
            $stmtDist = $conexion->prepare("
                SELECT 
                    s.nombre as sucursal_nombre,
                    i.cantidad as cantidad_recibida_en_sucursal
                FROM inventario i
                JOIN sucursales s ON i.id_sucursal = s.id_sucursal
                WHERE i.id_lote = ?
                ORDER BY s.nombre
            ");
            $stmtDist->execute([$prod['id_lote']]);
            $prod['distribucion'] = $stmtDist->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $prod['distribucion'] = [];
        }
        // Eliminar id_lote del objeto para no exponerlo innecesariamente
        unset($prod['id_lote']);
    }

    echo json_encode([
        'success' => true,
        'id_compra' => $compra['id_compra'],
        'numero_documento' => $compra['numero_documento'],
        'fecha' => date('d/m/Y H:i', strtotime($compra['fecha'])),
        'fecha_esperada' => $compra['fecha_esperada'] ? date('d/m/Y', strtotime($compra['fecha_esperada'])) : null,
        'proveedor_nombre' => $compra['proveedor_nombre'],
        'sucursal_nombre' => $compra['sucursal_nombre'],
        'usuario_nombre' => $compra['usuario_nombre'],
        'estado_nombre' => $compra['estado_nombre'],
        'subtotal' => floatval($compra['subtotal']),
        'descuento' => floatval($compra['descuento']),
        'itbis' => floatval($compra['itbis']),
        'total' => floatval($compra['total']),
        'observaciones' => $compra['observaciones'],
        'productos' => $productos
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en BD: ' . $e->getMessage()]);
}
?>