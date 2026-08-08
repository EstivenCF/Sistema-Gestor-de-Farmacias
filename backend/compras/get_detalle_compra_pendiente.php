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
    // Obtener datos de la compra
    $stmt = $conexion->prepare("
        SELECT c.id_compra, c.numero_documento, c.fecha, p.nombre as proveedor_nombre
        FROM compras c
        JOIN proveedores p ON c.id_proveedor = p.id_proveedor
        WHERE c.id_compra = ?
    ");
    $stmt->execute([$id_compra]);
    $compra = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$compra) {
        echo json_encode(['success' => false, 'message' => 'Compra no encontrada']);
        exit;
    }

    // Obtener detalles con cantidades pendientes (incluyendo productos sin lote)
    $stmt = $conexion->prepare("
        SELECT 
            dc.id_detalle,
            dc.id_lote,
            dc.cantidad,
            dc.cantidad_recibida,
            dc.precio_unitario,
            (dc.cantidad - COALESCE(dc.cantidad_recibida, 0)) as pendiente,
            m.nombre_completo as producto_nombre,
            m.id_medicamento,
            COALESCE(l.numero_lote, 'Pendiente') as numero_lote,
            l.fecha_vencimiento
        FROM detalle_compra dc
        JOIN medicamentos m ON dc.id_medicamento = m.id_medicamento
        LEFT JOIN lotes l ON dc.id_lote = l.id_lote
        WHERE dc.id_compra = ?
          AND (dc.cantidad - COALESCE(dc.cantidad_recibida, 0)) > 0
        ORDER BY m.nombre_completo
    ");
    $stmt->execute([$id_compra]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear fecha de vencimiento para cada producto (si existe)
    foreach ($productos as &$prod) {
        if ($prod['fecha_vencimiento']) {
            $prod['fecha_vencimiento'] = date('d/m/Y', strtotime($prod['fecha_vencimiento']));
        }
    }

    echo json_encode([
        'success' => true,
        'id_compra' => $compra['id_compra'],
        'numero_documento' => $compra['numero_documento'],
        'fecha' => date('d/m/Y H:i', strtotime($compra['fecha'])),
        'proveedor_nombre' => $compra['proveedor_nombre'],
        'productos' => $productos
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en BD: ' . $e->getMessage()]);
}
?>