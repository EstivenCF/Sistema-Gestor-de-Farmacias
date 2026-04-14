<?php
require_once __DIR__ . '/../conexion.php';
session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

function generarNuevoNumeroDocumento($conexion) {
    try {
        $stmt = $conexion->query("SELECT numero_documento FROM ventas WHERE numero_documento LIKE 'FAC-%' ORDER BY id_venta DESC LIMIT 1");
        $ultimo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ultimo) {
            $numero = intval(substr($ultimo['numero_documento'], 4));
            $nuevo_numero = $numero + 1;
        } else {
            $nuevo_numero = 1;
        }
        return 'FAC-' . str_pad($nuevo_numero, 6, '0', STR_PAD_LEFT);
    } catch(PDOException $e) {
        return 'FAC-' . date('Ymd') . '-0001';
    }
}

try {
    $conexion->beginTransaction();
    $id_usuario = $data['id_usuario'] ?? $_SESSION['id_usuario'] ?? null;
    if (!$id_usuario) throw new Exception('Usuario no identificado');
    
    // Validar stock
    foreach ($data['productos'] as $item) {
        $stmt = $conexion->prepare("
            SELECT i.cantidad, l.estado, l.fecha_vencimiento
            FROM inventario i
            JOIN lotes l ON i.id_lote = l.id_lote
            WHERE i.id_lote = ? AND i.id_sucursal = ?
        ");
        $stmt->execute([$item['id_lote'], $data['id_sucursal']]);
        $stock_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$stock_info) throw new Exception("Producto no encontrado en inventario");
        if ($stock_info['estado'] != 'ACTIVO') throw new Exception("Lote no disponible (Estado: {$stock_info['estado']})");
        if ($stock_info['fecha_vencimiento'] < date('Y-m-d')) throw new Exception("Lote vencido");
        if ($stock_info['cantidad'] < $item['cantidad']) throw new Exception("Stock insuficiente. Disponible: {$stock_info['cantidad']}, Requerido: {$item['cantidad']}");
    }
    
    // Obtener porcentaje ITBIS actual
    $itbis_porcentaje = 18;
    $stmtItbis = $conexion->query("SELECT porcentaje FROM config_itbis WHERE activo = true AND CURRENT_DATE BETWEEN fecha_inicio AND COALESCE(fecha_fin, CURRENT_DATE + INTERVAL '100 years') LIMIT 1");
    $itbis_config = $stmtItbis->fetch();
    if ($itbis_config) $itbis_porcentaje = $itbis_config['porcentaje'];
    
    $subtotal = floatval($data['subtotal']);
    $descuento_total = floatval($data['monto_descuento'] ?? 0);
    $itbis_total = floatval($data['itbis_total']); // ya viene calculado del frontend
    $monto_cubre_seguro = floatval($data['monto_cubre_seguro'] ?? 0);
    $usa_seguro = ($data['usa_seguro'] ?? false) ? 'true' : 'false';
    $total = floatval($data['monto_paga_paciente'] ?? ($subtotal - $descuento_total + $itbis_total - $monto_cubre_seguro));
    
    $numero_documento = generarNuevoNumeroDocumento($conexion);
    $id_cliente = !empty($data['id_cliente']) ? intval($data['id_cliente']) : null;
    $id_metodo_pago = !empty($data['id_metodo_pago']) ? intval($data['id_metodo_pago']) : null;
    $es_credito = ($data['id_condicion'] != 1) ? 'true' : 'false';
    $estado_pago = ($data['id_condicion'] == 1) ? 'PAGADO' : 'PENDIENTE';
    
    $fecha_vencimiento_pago = null;
    if ($data['id_condicion'] != 1) {
        $stmt = $conexion->prepare("SELECT dias_plazo FROM condicion_pago WHERE id_condicion = ?");
        $stmt->execute([$data['id_condicion']]);
        $condicion = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($condicion && $condicion['dias_plazo'] > 0) {
            $fecha_vencimiento_pago = date('Y-m-d', strtotime("+{$condicion['dias_plazo']} days"));
        }
    }
    
    // Insertar venta
    $stmt = $conexion->prepare("
        INSERT INTO ventas (
            numero_documento, fecha, id_usuario, id_cliente, id_sucursal, 
            id_condicion, subtotal, itbis_total, descuento_total, total,
            es_credito, estado_pago, fecha_vencimiento_pago,
            usa_seguro, monto_cubre_seguro, monto_paga_paciente
        ) VALUES (
            ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?
        ) RETURNING id_venta
    ");
    $stmt->execute([
        $numero_documento, $id_usuario, $id_cliente, intval($data['id_sucursal']),
        intval($data['id_condicion']), $subtotal, $itbis_total, $descuento_total, $total,
        $es_credito, $estado_pago, $fecha_vencimiento_pago,
        $usa_seguro, $monto_cubre_seguro, $total
    ]);
    $id_venta = $stmt->fetchColumn();
    if (!$id_venta) throw new Exception('Error al crear la venta');
    
    // Insertar detalles (con el ITBIS unitario recalculado)
    foreach ($data['productos'] as $item) {
        $item_subtotal = floatval($item['precio_unitario']) * intval($item['cantidad']);
        $proporcion = $item_subtotal / $subtotal;
        $item_descuento = $descuento_total * $proporcion;
        $item_subtotal_con_desc = $item_subtotal - $item_descuento;
        $itbis_unitario = 0;
        if ($item['aplica_itbis']) {
            $itbis_unitario = ($item_subtotal_con_desc * ($itbis_porcentaje / 100)) / intval($item['cantidad']);
        }
        $subtotal_item = $item_subtotal_con_desc;
        
        $stmtDet = $conexion->prepare("
            INSERT INTO detalle_venta (id_venta, id_lote, id_producto, cantidad, precio_unitario, itbis_unitario, subtotal)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtDet->execute([
            $id_venta, intval($item['id_lote']), intval($item['id_producto']),
            intval($item['cantidad']), floatval($item['precio_unitario']),
            $itbis_unitario, $subtotal_item
        ]);
    }
    
    // Registrar descuento
    if (!empty($data['id_descuento']) && $descuento_total > 0) {
        $stmtDesc = $conexion->prepare("INSERT INTO venta_descuento (id_venta, id_descuento, monto_descuento, fecha_aplicacion, id_usuario) VALUES (?, ?, ?, NOW(), ?)");
        $stmtDesc->execute([$id_venta, intval($data['id_descuento']), $descuento_total, $id_usuario]);
    }
    
    // Registrar pago si es contado
    if ($data['id_condicion'] == 1 && $id_metodo_pago) {
        $stmtPago = $conexion->prepare("INSERT INTO pagos (id_venta, id_metodo, monto, id_aseguradora, monto_seguro, monto_paciente, estado) VALUES (?, ?, ?, ?, ?, ?, 'COMPLETADO')");
        $stmtPago->execute([$id_venta, $id_metodo_pago, $total, $data['id_aseguradora'] ?? null, $monto_cubre_seguro, $total]);
    }
    
    // Acumular puntos
    if ($id_cliente) {
        $puntos = floor($total);
        $stmtPuntos = $conexion->prepare("INSERT INTO acumulacion_puntos (id_venta, id_cliente, puntos_ganados) VALUES (?, ?, ?)");
        $stmtPuntos->execute([$id_venta, $id_cliente, $puntos]);
        $stmtTarjeta = $conexion->prepare("UPDATE tarjetas_fidelidad SET puntos_acumulados = puntos_acumulados + ? WHERE id_cliente = ? AND activo = true");
        $stmtTarjeta->execute([$puntos, $id_cliente]);
    }
    
    $conexion->commit();
    $siguiente_numero = generarNuevoNumeroDocumento($conexion);
    
    echo json_encode([
        'success' => true,
        'id_venta' => $id_venta,
        'numero_documento' => $numero_documento,
        'nuevo_numero_documento' => $siguiente_numero,
        'total' => number_format($total, 2)
    ]);
    
} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error de BD: ' . $e->getMessage()]);
}
?>