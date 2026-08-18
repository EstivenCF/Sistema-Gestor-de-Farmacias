<?php
// backend/delivery/registrar_devolucion_entrega.php
// NUEVO — el repartidor marca que el cliente devolvió uno o más productos
// en el momento de la entrega (ej: llegó vencido y el cliente se dio
// cuenta ahí mismo). Reutiliza el MISMO sistema de Devoluciones que ya
// existe en Inventario (tabla devoluciones/detalle_devolucion), así que
// lo que se registre aquí aparece automáticamente en Inventario >
// Devoluciones — no es un módulo aparte.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}
if (!in_array($_SESSION['rol'] ?? '', ['Repartidor', 'Administrador'])) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']); exit();
}

$data          = json_decode(file_get_contents('php://input'), true);
$id_entrega    = intval($data['id_entrega'] ?? 0);
$id_motivo     = intval($data['id_motivo'] ?? 0);
$detalle_texto = trim($data['detalle'] ?? '');
$itemsInput    = $data['items'] ?? []; // [{id_detalle, cantidad}]
$id_usuario    = $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? 0);

if (!$id_entrega || !$id_motivo || !$id_usuario) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos para registrar la devolución']); exit();
}

// Al menos un producto con cantidad > 0
$itemsConCantidad = array_filter($itemsInput, fn($i) => intval($i['cantidad'] ?? 0) > 0);
if (empty($itemsConCantidad)) {
    echo json_encode(['success' => false, 'message' => 'Indica la cantidad de al menos un producto devuelto']); exit();
}

try {
    $conexion->beginTransaction();

    // La entrega debe existir y, si es Repartidor, ser suya.
    $stmt = $conexion->prepare("
        SELECT e.id_entrega, e.id_venta, e.id_cliente, e.id_sucursal, e.id_vehiculo,
               e.fecha_asignada, se.nombre AS estado_actual
        FROM entregas e
        JOIN estado_entrega se ON se.id_estado = e.id_estado
        LEFT JOIN repartidores r ON r.id_repartidor = e.id_repartidor
        WHERE e.id_entrega = :id
          AND (:rol != 'Repartidor' OR r.id_usuario = :id_usuario)
        FOR UPDATE OF e
    ");
    $stmt->execute([
        ':id'         => $id_entrega,
        ':rol'        => $_SESSION['rol'],
        ':id_usuario' => $id_usuario,
    ]);
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$entrega) { throw new Exception('Esta entrega no está asignada a ti, o no existe'); }

    // Validar que el motivo elegido exista de verdad en el catálogo
    $stmt = $conexion->prepare("SELECT nombre FROM motivo_devolucion_delivery WHERE id_motivo = :id AND activo = true");
    $stmt->execute([':id' => $id_motivo]);
    $nombre_motivo = $stmt->fetchColumn();
    if (!$nombre_motivo) { throw new Exception('El motivo seleccionado no es válido'); }

    // Verdad del servidor: lo realmente vendido en esta venta (nunca lo
    // que mande el navegador) — de aquí sale el id_lote y el precio real
    // para armar el detalle de la devolución.
    $stmt = $conexion->prepare("
        SELECT id_detalle, id_lote, id_producto, cantidad, precio_unitario
        FROM detalle_venta
        WHERE id_venta = :id_venta
    ");
    $stmt->execute([':id_venta' => $entrega['id_venta']]);
    $detalleVentaPorId = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $detalleVentaPorId[$row['id_detalle']] = $row;
    }

    $lineasDevolucion = [];
    $montoTotal = 0;
    foreach ($itemsConCantidad as $item) {
        $id_detalle = intval($item['id_detalle'] ?? 0);
        if (!isset($detalleVentaPorId[$id_detalle])) continue; // línea que no pertenece a esta venta, se ignora

        $real = $detalleVentaPorId[$id_detalle];
        // Tope real: nunca más de lo que de verdad se vendió en esa línea
        $cantidad = min(intval($real['cantidad']), max(0, intval($item['cantidad'] ?? 0)));
        if ($cantidad <= 0) continue;

        $lineasDevolucion[] = [
            'id_lote'         => $real['id_lote'],
            'cantidad'        => $cantidad,
            'precio_unitario' => $real['precio_unitario'],
        ];
        $montoTotal += $cantidad * (float) $real['precio_unitario'];
    }

    if (empty($lineasDevolucion)) {
        throw new Exception('No se reconoció ningún producto válido de esta entrega');
    }

    $motivo_final = $nombre_motivo . ($detalle_texto !== '' ? ' — ' . $detalle_texto : '');
    $numero_documento = 'DEV' . date('ymdHis') . rand(100, 999); // 19 caracteres, cabe en VARCHAR(20)

    $stmt = $conexion->prepare("
        INSERT INTO devoluciones (
            numero_documento, id_venta, id_sucursal, id_cliente, motivo,
            id_usuario, id_tipo, id_estado, monto_reembolso,
            id_entrega, id_motivo_devolucion_delivery
        ) VALUES (
            :num, :id_venta, :id_sucursal, :id_cliente, :motivo,
            :id_usuario,
            (SELECT id_tipo FROM tipo_devolucion WHERE nombre = 'CLIENTE'),
            (SELECT id_estado FROM estado_devolucion WHERE nombre = 'SOLICITADA'),
            :monto, :id_entrega, :id_motivo
        )
        RETURNING id_devolucion
    ");
    $stmt->execute([
        ':num'         => $numero_documento,
        ':id_venta'    => $entrega['id_venta'],
        ':id_sucursal' => $entrega['id_sucursal'],
        ':id_cliente'  => $entrega['id_cliente'],
        ':motivo'      => $motivo_final,
        ':id_usuario'  => $id_usuario,
        ':monto'       => $montoTotal,
        ':id_entrega'  => $id_entrega,
        ':id_motivo'   => $id_motivo,
    ]);
    $id_devolucion = $stmt->fetchColumn();

    $stmtDet = $conexion->prepare("
        INSERT INTO detalle_devolucion (id_devolucion, id_lote, cantidad, precio_unitario)
        VALUES (:id_devolucion, :id_lote, :cantidad, :precio_unitario)
    ");
    foreach ($lineasDevolucion as $l) {
        $stmtDet->execute([
            ':id_devolucion'   => $id_devolucion,
            ':id_lote'         => $l['id_lote'],
            ':cantidad'        => $l['cantidad'],
            ':precio_unitario' => $l['precio_unitario'],
        ]);
    }

    // Dejarlo también en el historial de la entrega, para que se vea en
    // su línea de tiempo (no solo en Inventario > Devoluciones).
    $stmtHist = $conexion->prepare("
        INSERT INTO historial_entrega (id_entrega, id_estado, fecha, observacion, id_usuario)
        SELECT :id_entrega, e.id_estado, NOW(), :obs, :id_usuario
        FROM entregas e WHERE e.id_entrega = :id_entrega
    ");
    $stmtHist->execute([
        ':id_entrega' => $id_entrega,
        ':obs'        => 'Devolución registrada por el repartidor (' . $numero_documento . '): ' . $motivo_final,
        ':id_usuario' => $id_usuario,
    ]);

    // ── Cerrar la entrega automáticamente si ya no queda nada pendiente ──
    // Un producto que el cliente acaba de devolver no se puede seguir
    // ofreciendo como "pendiente" en el paso de Confirmar Entrega (si no,
    // se podría marcar como entregado Y como devuelto a la vez). Si esta
    // devolución dejó TODO el pedido cubierto (nada pendiente en ninguna
    // línea) y la entrega todavía estaba EN_CAMINO, se cierra sola —
    // el repartidor ya no tiene nada que confirmar a mano.
    $entregaCerrada = false;
    $estadoFinalCierre = null;
    if ($entrega['estado_actual'] === 'EN_CAMINO') {
        $stmtPedido = $conexion->prepare("SELECT id_lote, id_producto, cantidad FROM detalle_venta WHERE id_venta = :id_venta");
        $stmtPedido->execute([':id_venta' => $entrega['id_venta']]);
        $pedido = $stmtPedido->fetchAll(PDO::FETCH_ASSOC);

        $stmtConc = $conexion->prepare("
            SELECT dc.id_lote, dc.id_producto, SUM(dc.cantidad_entregada) AS entregado
            FROM detalle_conciliacion dc
            JOIN conciliacion_entrega ce ON ce.id_conciliacion = dc.id_conciliacion
            WHERE ce.id_entrega = :id
              AND ce.fecha_conciliacion >= COALESCE(:fecha_asignada::timestamp, '-infinity')
            GROUP BY dc.id_lote, dc.id_producto
        ");
        $stmtConc->execute([':id' => $id_entrega, ':fecha_asignada' => $entrega['fecha_asignada']]);
        $entregadoPorClave = [];
        foreach ($stmtConc->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $clave = $row['id_lote'] !== null ? 'L' . $row['id_lote'] : 'P' . $row['id_producto'];
            $entregadoPorClave[$clave] = (int) $row['entregado'];
        }

        $stmtDev = $conexion->prepare("
            SELECT dd.id_lote, SUM(dd.cantidad) AS devuelto
            FROM detalle_devolucion dd
            JOIN devoluciones d ON d.id_devolucion = dd.id_devolucion
            WHERE d.id_entrega = :id
              AND d.fecha_solicitud >= COALESCE(:fecha_asignada::timestamp, '-infinity')
            GROUP BY dd.id_lote
        ");
        $stmtDev->execute([':id' => $id_entrega, ':fecha_asignada' => $entrega['fecha_asignada']]);
        $devueltoPorLote = [];
        foreach ($stmtDev->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['id_lote'] === null) continue;
            $devueltoPorLote['L' . $row['id_lote']] = (int) $row['devuelto'];
        }

        $quedaPendiente = false;
        foreach ($pedido as $linea) {
            $clave = $linea['id_lote'] !== null ? 'L' . $linea['id_lote'] : 'P' . $linea['id_producto'];
            $pendiente = (int) $linea['cantidad'] - ($entregadoPorClave[$clave] ?? 0) - ($devueltoPorLote[$clave] ?? 0);
            if ($pendiente > 0) { $quedaPendiente = true; break; }
        }

        if (!$quedaPendiente) {
            // 'DEVUELTA' es un estado terminal aparte de 'PARCIAL': no se
            // espera ninguna segunda ronda ni redespacho, porque el
            // cliente no se quedó con nada del pedido.
            $stmtCierre = $conexion->prepare("
                UPDATE entregas
                SET id_estado = (SELECT id_estado FROM estado_entrega WHERE nombre = 'DEVUELTA'),
                    es_entrega_parcial = FALSE,
                    detalle_parcial = 'El cliente devolvió todos los productos en el momento de la entrega.',
                    fecha_modificacion = NOW()
                WHERE id_entrega = :id
            ");
            $stmtCierre->execute([':id' => $id_entrega]);

            // Nada que redespachar (el cliente no quiere el pedido), así
            // que el vehículo queda libre de una vez.
            if (!empty($entrega['id_vehiculo'])) {
                $conexion->prepare("UPDATE vehiculos SET estado = 'DISPONIBLE' WHERE id_vehiculo = :id AND estado = 'EN_USO'")
                    ->execute([':id' => $entrega['id_vehiculo']]);
            }

            $conexion->prepare("
                INSERT INTO historial_entrega (id_entrega, id_estado, fecha, observacion, id_usuario)
                VALUES (:id_entrega, (SELECT id_estado FROM estado_entrega WHERE nombre = 'DEVUELTA'), NOW(), :obs, :id_usuario)
            ")->execute([
                ':id_entrega' => $id_entrega,
                ':obs'        => 'Entrega cerrada automáticamente: todo el pedido fue devuelto por el cliente.',
                ':id_usuario' => $id_usuario,
            ]);

            $entregaCerrada = true;
            $estadoFinalCierre = 'DEVUELTA';
        }
    }

    $conexion->commit();
    echo json_encode([
        'success'          => true,
        'id_devolucion'    => $id_devolucion,
        'numero_documento' => $numero_documento,
        'monto_reembolso'  => $montoTotal,
        'entrega_cerrada'  => $entregaCerrada,
        'estado_final'     => $estadoFinalCierre,
    ]);

} catch (Exception $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
