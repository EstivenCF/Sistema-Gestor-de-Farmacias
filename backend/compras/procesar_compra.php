<?php
require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

try {
    $conexion->beginTransaction();
    
    $fecha_esperada = !empty($data['fecha_esperada']) ? $data['fecha_esperada'] : null;
    
    // Insertar compra
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
    
    // Insertar detalles de compra
    foreach ($data['productos'] as $prod) {
        
        if ($prod['tipo'] === 'MEDICAMENTO') {
            // 1. Crear el lote
            $stmtLote = $conexion->prepare("
                INSERT INTO lotes (id_medicamento, numero_lote, fecha_vencimiento, cantidad_inicial, cantidad_actual, costo_unitario, estado, fecha_registro)
                VALUES (?, ?, ?, ?, ?, ?, 'ACTIVO', CURRENT_TIMESTAMP)
                RETURNING id_lote
            ");
            $stmtLote->execute([
                $prod['id_producto'],
                $prod['numero_lote'],
                $prod['fecha_vencimiento'],
                $prod['cantidad'],
                $prod['cantidad'],
                $prod['costo_unitario']
            ]);
            $id_lote = $stmtLote->fetchColumn();
            
            // 2. Insertar detalle de compra
            $stmtDet = $conexion->prepare("
                INSERT INTO detalle_compra 
                (id_compra, id_medicamento, id_lote, cantidad, precio_unitario, descuento_unitario, itbis_unitario, 
                 numero_lote_solicitado, fecha_vencimiento_solicitada, cantidad_recibida)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $stmtDet->execute([
                $id_compra,
                $prod['id_producto'],
                $id_lote,
                $prod['cantidad'],
                $prod['costo_unitario'],
                $prod['descuento_unitario'] ?? 0,
                $prod['aplica_itbis'] ? ($prod['costo_unitario'] * 0.18) : 0,
                $prod['numero_lote'],
                $prod['fecha_vencimiento']
            ]);
            
            // NOTA: El inventario se actualizará en la RECEPCIÓN, no aquí
            
        } 
        else if ($prod['tipo'] === 'ROPA') {
            // Insertar detalle de compra (sin inventario - se hará en recepción)
            $stmtDet = $conexion->prepare("
                INSERT INTO detalle_compra 
                (id_compra, id_producto, id_talla, id_color, cantidad, precio_unitario, descuento_unitario, itbis_unitario, cantidad_recibida)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $stmtDet->execute([
                $id_compra,
                $prod['id_producto'],
                $prod['id_talla'] ?? null,
                $prod['id_color'] ?? null,
                $prod['cantidad'],
                $prod['costo_unitario'],
                $prod['descuento_unitario'] ?? 0,
                $prod['aplica_itbis'] ? ($prod['costo_unitario'] * 0.18) : 0
            ]);
            
            // NOTA: El inventario NO se actualiza aquí, se hará en la RECEPCIÓN
        }
    }
    
    $conexion->commit();
    
    echo json_encode([
        'success' => true,
        'id_compra' => $id_compra,
        'numero_documento' => $data['numero_documento']
    ]);
    
} catch (Exception $e) {
    if (isset($conexion)) $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    if (isset($conexion)) $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error de BD: ' . $e->getMessage()]);
}
?>