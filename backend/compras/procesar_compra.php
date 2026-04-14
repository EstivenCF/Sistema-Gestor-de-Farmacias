<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

try {
    $conexion->beginTransaction();
    
    $fecha_esperada = !empty($data['fecha_esperada']) ? $data['fecha_esperada'] : null;
    
    $stmt = $conexion->prepare("
        INSERT INTO compras (numero_documento, id_proveedor, id_usuario, id_sucursal, observaciones, fecha_esperada, subtotal, descuento, itbis, total, id_estado)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, (SELECT id_estado FROM estado_compra WHERE nombre = 'PENDIENTE'))
        RETURNING id_compra
    ");
    $stmt->execute([
        $data['numero_documento'],
        $data['id_proveedor'],
        $data['id_usuario'],
        $data['id_sucursal'],
        $data['observaciones'] ?? null,
        $fecha_esperada,
        $data['subtotal'],
        $data['descuento'],
        $data['itbis'],
        $data['total']
    ]);
    $id_compra = $stmt->fetchColumn();
    
    foreach ($data['productos'] as $prod) {
        $stmt = $conexion->prepare("
            INSERT INTO detalle_compra (id_compra, id_medicamento, cantidad, precio_unitario, descuento_unitario, itbis_unitario, numero_lote_solicitado, fecha_vencimiento_solicitada)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id_compra,
            $prod['id_medicamento'],
            $prod['cantidad'],
            $prod['costo_unitario'],
            $prod['descuento_unitario'],
            $prod['aplica_itbis'] ? ($prod['costo_unitario'] * 0.18) : 0,
            $prod['numero_lote'] ?? null,
            $prod['fecha_vencimiento'] ?? null
        ]);
    }
    
    $conexion->commit();
    
    echo json_encode([
        'success' => true,
        'id_compra' => $id_compra,
        'numero_documento' => $data['numero_documento']
    ]);
    
} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error de BD: ' . $e->getMessage()]);
}
?>