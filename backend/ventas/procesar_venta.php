<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

try {
    $conexion->beginTransaction();

    // Validar y convertir tipos
    $id_condicion = isset($data['id_condicion']) ? (int)$data['id_condicion'] : 1;
    if ($id_condicion < 1 || $id_condicion > 3) $id_condicion = 1;
    $es_credito = ($id_condicion == 2 || $id_condicion == 3);
    $usa_seguro = isset($data['usa_seguro']) ? filter_var($data['usa_seguro'], FILTER_VALIDATE_BOOLEAN) : false;
    $monto_seguro = isset($data['monto_cubre_seguro']) ? (float)$data['monto_cubre_seguro'] : 0.0;
    $monto_paciente = isset($data['monto_paga_paciente']) ? (float)$data['monto_paga_paciente'] : (float)$data['total'];

    // Insertar venta
    $sql = "INSERT INTO ventas 
        (numero_documento, id_usuario, id_cliente, id_sucursal, id_condicion, subtotal, descuento_total, itbis_total, total, es_credito, usa_seguro, monto_cubre_seguro, monto_paga_paciente)
        VALUES (:num_doc, :id_user, :id_cliente, :id_sucursal, :id_condicion, :subtotal, :descuento, :itbis, :total, :credito, :seguro, :monto_seguro, :monto_paciente)
        RETURNING id_venta";
    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':num_doc', $data['numero_documento'], PDO::PARAM_STR);
    $stmt->bindValue(':id_user', $data['id_usuario'], PDO::PARAM_INT);
    $stmt->bindValue(':id_cliente', $data['id_cliente'] ?? null, PDO::PARAM_INT);
    $stmt->bindValue(':id_sucursal', $data['id_sucursal'], PDO::PARAM_INT);
    $stmt->bindValue(':id_condicion', $id_condicion, PDO::PARAM_INT);
    $stmt->bindValue(':subtotal', (float)$data['subtotal'], PDO::PARAM_STR);
    $stmt->bindValue(':descuento', (float)($data['monto_descuento'] ?? 0), PDO::PARAM_STR);
    $stmt->bindValue(':itbis', (float)$data['itbis_total'], PDO::PARAM_STR);
    $stmt->bindValue(':total', (float)$data['total'], PDO::PARAM_STR);
    $stmt->bindValue(':credito', $es_credito, PDO::PARAM_BOOL);
    $stmt->bindValue(':seguro', $usa_seguro, PDO::PARAM_BOOL);
    $stmt->bindValue(':monto_seguro', $monto_seguro, PDO::PARAM_STR);
    $stmt->bindValue(':monto_paciente', $monto_paciente, PDO::PARAM_STR);
    $stmt->execute();
    $id_venta = $stmt->fetchColumn();

    // Detalle de venta
    foreach ($data['productos'] as $prod) {
        $itemSubtotal = (float)$prod['cantidad'] * (float)$prod['precio_unitario'];
        $itemItbis = $prod['aplica_itbis'] ? $itemSubtotal * 0.18 : 0;
        $sqlDet = "INSERT INTO detalle_venta (id_venta, id_lote, id_producto, cantidad, precio_unitario, descuento_unitario, itbis_unitario, subtotal)
                   VALUES (:id_venta, :id_lote, :id_producto, :cant, :precio, :dto, :itbis, :subtotal)";
        $stmtDet = $conexion->prepare($sqlDet);
        $stmtDet->bindValue(':id_venta', $id_venta, PDO::PARAM_INT);
        $stmtDet->bindValue(':id_lote', $prod['id_lote'], PDO::PARAM_INT);
        $stmtDet->bindValue(':id_producto', $prod['id_producto'], PDO::PARAM_INT);
        $stmtDet->bindValue(':cant', $prod['cantidad'], PDO::PARAM_INT);
        $stmtDet->bindValue(':precio', $prod['precio_unitario'], PDO::PARAM_STR);
        $stmtDet->bindValue(':dto', 0, PDO::PARAM_STR);
        $stmtDet->bindValue(':itbis', $itemItbis, PDO::PARAM_STR);
        $stmtDet->bindValue(':subtotal', $itemSubtotal + $itemItbis, PDO::PARAM_STR);
        $stmtDet->execute();
    }

    // Pago al contado
    if ($id_condicion == 1 && !empty($data['id_metodo_pago'])) {
        $sqlPago = "INSERT INTO pagos (id_venta, id_metodo, monto) VALUES (:id_venta, :id_metodo, :monto)";
        $stmtPago = $conexion->prepare($sqlPago);
        $stmtPago->bindValue(':id_venta', $id_venta, PDO::PARAM_INT);
        $stmtPago->bindValue(':id_metodo', $data['id_metodo_pago'], PDO::PARAM_INT);
        $stmtPago->bindValue(':monto', (float)$data['total'], PDO::PARAM_STR);
        $stmtPago->execute();
    }

    // ==================== ENTREGA CORREGIDA ====================
    if (!empty($data['delivery_activo']) && filter_var($data['delivery_activo'], FILTER_VALIDATE_BOOLEAN)) {
        // Obtener el id_estado correspondiente a 'PENDIENTE'
        $stmtEstado = $conexion->prepare("SELECT id_estado FROM estado_entrega WHERE nombre = 'PENDIENTE'");
        $stmtEstado->execute();
        $id_estado_pendiente = $stmtEstado->fetchColumn();
        if (!$id_estado_pendiente) {
            throw new Exception("No se encontró el estado 'PENDIENTE' en la tabla estado_entrega");
        }
        
        $numero_seguimiento = 'DEL-' . date('Ymd') . '-' . str_pad($id_venta, 6, '0', STR_PAD_LEFT);
        $cliente_nombre = '';
        if (!empty($data['id_cliente'])) {
            $stmtCli = $conexion->prepare("SELECT nombre FROM clientes WHERE id_cliente = :id");
            $stmtCli->execute([':id' => $data['id_cliente']]);
            $cliente_nombre = $stmtCli->fetchColumn();
        } else {
            $cliente_nombre = 'Consumidor Final';
        }

        // Usar las columnas correctas según tu esquema:
        // - fecha_asignada (con 'd') en lugar de fecha_asignacion
        // - id_estado en lugar de estado (string)
        // - No se usa estado booleano, se usa id_estado
        $sqlEnt = "INSERT INTO entregas 
            (id_venta, id_cliente, id_sucursal, id_repartidor, numero_seguimiento, direccion_entrega, costo_entrega, creado_por, cliente_nombre, id_estado, fecha_asignada)
            VALUES (:id_venta, :id_cliente, :id_sucursal, :id_repartidor, :numero_seg, :direccion, :costo, :creado_por, :cliente_nombre, :id_estado, NOW())";
        $stmtEnt = $conexion->prepare($sqlEnt);
        $stmtEnt->bindValue(':id_venta', $id_venta, PDO::PARAM_INT);
        $stmtEnt->bindValue(':id_cliente', $data['id_cliente'] ?? null, PDO::PARAM_INT);
        $stmtEnt->bindValue(':id_sucursal', $data['id_sucursal'], PDO::PARAM_INT);
        $stmtEnt->bindValue(':id_repartidor', $data['id_repartidor'] ?? null, PDO::PARAM_INT);
        $stmtEnt->bindValue(':numero_seg', $numero_seguimiento, PDO::PARAM_STR);
        $stmtEnt->bindValue(':direccion', $data['direccion_entrega'], PDO::PARAM_STR);
        $stmtEnt->bindValue(':costo', (float)($data['costo_envio'] ?? 0), PDO::PARAM_STR);
        $stmtEnt->bindValue(':creado_por', $data['id_usuario'], PDO::PARAM_INT);
        $stmtEnt->bindValue(':cliente_nombre', $cliente_nombre, PDO::PARAM_STR);
        $stmtEnt->bindValue(':id_estado', $id_estado_pendiente, PDO::PARAM_INT);
        $stmtEnt->execute();
    }

    $conexion->commit();
    echo json_encode([
        'success' => true,
        'id_venta' => $id_venta,
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