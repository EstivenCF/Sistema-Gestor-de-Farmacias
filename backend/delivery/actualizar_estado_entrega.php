<?php
// backend/delivery/actualizar_estado_entrega.php
// NUEVO — cambia el estado de una entrega (en curso, entregada, interrumpida,
// parcial, etc.) y libera el vehículo automáticamente cuando corresponde.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id_entrega     = intval($data['id_entrega'] ?? 0);
$nuevo_estado   = trim($data['estado'] ?? ''); // EN_CAMINO, ENTREGADA, INTERRUMPIDA, PARCIAL, CANCELADA, FALLIDA
$motivo         = trim($data['motivo'] ?? '');        // para INTERRUMPIDA
$detalle_parcial= trim($data['detalle_parcial'] ?? ''); // para PARCIAL
$id_motivo_fallida = intval($data['id_motivo_fallida'] ?? 0); // para FALLIDA
$detalle_fallida   = trim($data['detalle_fallida'] ?? '');    // para FALLIDA (opcional)

$ESTADOS_VALIDOS = ['ASIGNADA','EN_CAMINO','ENTREGADA','INTERRUMPIDA','PARCIAL','CANCELADA','REPROGRAMADA','FALLIDA'];

if (!$id_entrega || !in_array($nuevo_estado, $ESTADOS_VALIDOS)) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']); exit();
}

// El repartidor solo puede tocar SUS PROPIAS entregas, y solo con los
// estados que le corresponden a él ejecutar (cancelar o reprogramar
// son decisiones administrativas, no del repartidor en la calle).
if (($_SESSION['rol'] ?? '') === 'Repartidor') {
    $ESTADOS_PERMITIDOS_REPARTIDOR = ['EN_CAMINO','ENTREGADA','INTERRUMPIDA','PARCIAL','FALLIDA'];
    if (!in_array($nuevo_estado, $ESTADOS_PERMITIDOS_REPARTIDOR)) {
        echo json_encode(['success' => false, 'message' => 'Ese cambio de estado no está permitido desde tu cuenta']); exit();
    }
    $stmtProp = $conexion->prepare("
        SELECT 1 FROM entregas e
        JOIN repartidores r ON r.id_repartidor = e.id_repartidor
        WHERE e.id_entrega = :id AND r.id_usuario = :id_usuario
    ");
    $stmtProp->execute([
        ':id' => $id_entrega,
        ':id_usuario' => $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? 0),
    ]);
    if (!$stmtProp->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Esta entrega no está asignada a ti']); exit();
    }
}
if ($nuevo_estado === 'INTERRUMPIDA' && $motivo === '') {
    echo json_encode(['success' => false, 'message' => 'Debes indicar el motivo de la interrupción']); exit();
}
if ($nuevo_estado === 'PARCIAL' && $detalle_parcial === '') {
    echo json_encode(['success' => false, 'message' => 'Debes indicar el detalle de qué se entregó y qué no']); exit();
}
if ($nuevo_estado === 'FALLIDA' && !$id_motivo_fallida) {
    echo json_encode(['success' => false, 'message' => 'Debes seleccionar el motivo por el cual la entrega no se pudo completar']); exit();
}

// Estados que liberan al repartidor y su vehículo (ya no está "en camino")
$ESTADOS_FINALES = ['ENTREGADA','INTERRUMPIDA','PARCIAL','CANCELADA','FALLIDA'];

try {
    $conexion->beginTransaction();

    $stmt = $conexion->prepare("SELECT id_estado FROM estado_entrega WHERE nombre = :n");
    $stmt->execute([':n' => $nuevo_estado]);
    $id_estado = $stmt->fetchColumn();
    if (!$id_estado) throw new Exception("Estado '$nuevo_estado' no existe en estado_entrega");

    $nombre_motivo_fallida = null;
    if ($nuevo_estado === 'FALLIDA') {
        $stmt = $conexion->prepare("SELECT nombre FROM motivo_fallida WHERE id_motivo = :id AND activo = true");
        $stmt->execute([':id' => $id_motivo_fallida]);
        $nombre_motivo_fallida = $stmt->fetchColumn();
        if (!$nombre_motivo_fallida) throw new Exception('El motivo seleccionado no es válido');
    }

    // Obtener vehículo asignado para liberarlo si aplica
    $stmt = $conexion->prepare("SELECT id_vehiculo FROM entregas WHERE id_entrega = :id");
    $stmt->execute([':id' => $id_entrega]);
    $id_vehiculo = $stmt->fetchColumn();

    $campos = "id_estado = :id_estado, fecha_modificacion = NOW()";
    $params = [':id_estado' => $id_estado, ':id' => $id_entrega];

    if ($nuevo_estado === 'ENTREGADA') {
        $campos .= ", fecha_entrega_real = NOW(), fecha_entrega = NOW()";
    }
    if ($nuevo_estado === 'INTERRUMPIDA') {
        $campos .= ", motivo_interrupcion = :motivo";
        $params[':motivo'] = $motivo;
    }
    if ($nuevo_estado === 'PARCIAL') {
        $campos .= ", es_entrega_parcial = TRUE, detalle_parcial = :detalle";
        $params[':detalle'] = $detalle_parcial;
    }
    if ($nuevo_estado === 'FALLIDA') {
        $campos .= ", id_motivo_fallida = :id_motivo_fallida, observaciones = :obs_fallida";
        $params[':id_motivo_fallida'] = $id_motivo_fallida;
        $params[':obs_fallida'] = $nombre_motivo_fallida . ($detalle_fallida !== '' ? ' — ' . $detalle_fallida : '');
    }
    if ($nuevo_estado === 'EN_CAMINO') {
        $campos .= ", fecha_inicio = NOW()";
    }

    $stmt = $conexion->prepare("UPDATE entregas SET $campos WHERE id_entrega = :id");
    $stmt->execute($params);

    // "Marcar en camino" (agenda.php) es el camino que usa el repartidor
    // cuando el cajero NUNCA pasó por "Despachar" (registrar_despacho.php)
    // — por eso esta entrega no tiene ninguna fila en despacho_entrega.
    // Sin eso, confirmar_entrega_delivery.php topa cualquier confirmación
    // contra "lo despachado en esta ronda", que siempre daría 0, y
    // rechazaría CUALQUIER confirmación (con o sin devolución de por
    // medio) con "No se reconoció ningún producto válido de esta venta,
    // o no había nada despachado en esta ronda". Se genera aquí un
    // despacho de respaldo (ronda 1) con el pedido completo, asumiendo
    // que el repartidor salió con todo lo vendido — así el flujo que ya
    // usan los repartidores (sin pasar por el cajero) sigue funcionando.
    if ($nuevo_estado === 'EN_CAMINO') {
        $stmtChkDesp = $conexion->prepare("SELECT 1 FROM despacho_entrega WHERE id_entrega = :id LIMIT 1");
        $stmtChkDesp->execute([':id' => $id_entrega]);
        if (!$stmtChkDesp->fetchColumn()) {
            $stmtVentaBk = $conexion->prepare("SELECT id_venta FROM entregas WHERE id_entrega = :id");
            $stmtVentaBk->execute([':id' => $id_entrega]);
            $id_venta_bk = $stmtVentaBk->fetchColumn();

            $stmtLineasBk = $conexion->prepare("SELECT id_lote, id_producto, cantidad FROM detalle_venta WHERE id_venta = :id_venta");
            $stmtLineasBk->execute([':id_venta' => $id_venta_bk]);
            $lineasVentaBk = $stmtLineasBk->fetchAll(PDO::FETCH_ASSOC);

            $id_usuario_bk = $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? null);
            if ($lineasVentaBk && $id_usuario_bk) {
                $stmtDespBk = $conexion->prepare("
                    INSERT INTO despacho_entrega (id_entrega, id_ronda, id_usuario_cajero, observaciones)
                    VALUES (:id_entrega, 1, :id_usuario, 'Generado automáticamente: el repartidor salió sin pasar por el paso de Despacho del cajero')
                    RETURNING id_despacho
                ");
                $stmtDespBk->execute([':id_entrega' => $id_entrega, ':id_usuario' => $id_usuario_bk]);
                $id_despacho_bk = $stmtDespBk->fetchColumn();

                $stmtDetBk = $conexion->prepare("
                    INSERT INTO detalle_despacho (id_despacho, id_lote, id_producto, cantidad_despachada)
                    VALUES (:id_despacho, :id_lote, :id_producto, :cantidad)
                ");
                foreach ($lineasVentaBk as $lv) {
                    if ((int) $lv['cantidad'] <= 0) continue;
                    $stmtDetBk->execute([
                        ':id_despacho' => $id_despacho_bk,
                        ':id_lote'     => $lv['id_lote'],
                        ':id_producto' => $lv['id_producto'],
                        ':cantidad'    => $lv['cantidad'],
                    ]);
                }
            }
        }
    }

    // Historial
    $stmt = $conexion->prepare("
        INSERT INTO historial_entrega (id_entrega, id_estado, fecha, observacion, id_usuario)
        VALUES (:id_entrega, :id_estado, NOW(), :obs, :id_usuario)
    ");
    $obs = $nuevo_estado === 'INTERRUMPIDA' ? $motivo
         : ($nuevo_estado === 'PARCIAL' ? $detalle_parcial
         : ($nuevo_estado === 'FALLIDA' ? $nombre_motivo_fallida
         : "Cambio de estado a $nuevo_estado"));
    $stmt->execute([
        ':id_entrega' => $id_entrega,
        ':id_estado'  => $id_estado,
        ':obs'        => $obs,
        ':id_usuario' => $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? null),
    ]);

    // Liberar vehículo si la entrega llegó a un estado final
    if ($id_vehiculo && in_array($nuevo_estado, $ESTADOS_FINALES)) {
        $stmt = $conexion->prepare("UPDATE vehiculos SET estado = 'DISPONIBLE' WHERE id_vehiculo = :id AND estado = 'EN_USO'");
        $stmt->execute([':id' => $id_vehiculo]);
    }

    $conexion->commit();
    echo json_encode(['success' => true, 'estado' => $nuevo_estado]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
