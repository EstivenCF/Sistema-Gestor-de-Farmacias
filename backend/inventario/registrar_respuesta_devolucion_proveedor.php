<?php
/**
 * Tarea 5 - Proceso estratégico: Gestión Estratégica de Vencimientos
 * Registra la respuesta del proveedor a una solicitud de devolución
 * previamente generada (gestionar_accion_recuperacion.php la deja en
 * estado ESPERANDO_PROVEEDOR / devoluciones.SOLICITADA).
 *
 * - Si el proveedor APRUEBA: recién aquí se ejecuta el movimiento físico
 *   de inventario (salida del lote) y se cierran devolución + acción como
 *   COMPLETADA. Antes de este endpoint el stock nunca se toca.
 * - Si el proveedor RECHAZA: la devolución queda RECHAZADA y la acción
 *   pasa a RECHAZADA_PROVEEDOR, sin tocar inventario, para que el usuario
 *   pueda reasignar el lote a otra estrategia (redistribución, promoción,
 *   o escalar a Combo/Donación/Provisión de pérdida en el diagnóstico de
 *   causa raíz).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

$id_usuario = $_SESSION['id_usuario'] ?? ($_SESSION['usuario_id'] ?? null);
if (!$id_usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

require_once __DIR__ . '/../conexion.php';

$input = json_decode(file_get_contents('php://input'), true);
$id_accion = isset($input['id_accion']) ? (int)$input['id_accion'] : 0;
$decision = $input['decision'] ?? '';
$observaciones = trim($input['observaciones'] ?? '');

if (!$id_accion || !in_array($decision, ['APROBADA', 'RECHAZADA'], true)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos: se requiere id_accion y decision (APROBADA|RECHAZADA)']);
    exit();
}

if ($decision === 'RECHAZADA' && $observaciones === '') {
    echo json_encode(['success' => false, 'message' => 'Debe indicar el motivo por el que el proveedor rechazó la devolución']);
    exit();
}

try {
    $conexion->beginTransaction();

    $stmt = $conexion->prepare("
        SELECT id_lote, id_sucursal_origen, cantidad_afectada, valor_en_riesgo, estado, id_devolucion
        FROM accion_recuperacion WHERE id_accion = :id_accion FOR UPDATE
    ");
    $stmt->execute([':id_accion' => $id_accion]);
    $accion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$accion) {
        throw new Exception('Acción no encontrada');
    }
    if ($accion['estado'] !== 'ESPERANDO_PROVEEDOR' || !$accion['id_devolucion']) {
        throw new Exception('Esta acción no tiene una solicitud de devolución pendiente de respuesta del proveedor.');
    }

    $id_devolucion = (int)$accion['id_devolucion'];

    $stmt = $conexion->prepare("SELECT id_estado FROM devoluciones WHERE id_devolucion = :id FOR UPDATE");
    $stmt->execute([':id' => $id_devolucion]);
    $id_estado_actual = $stmt->fetchColumn();

    $stmt = $conexion->prepare("SELECT id_estado FROM estado_devolucion WHERE nombre = 'SOLICITADA'");
    $stmt->execute();
    $id_estado_solicitada = $stmt->fetchColumn();

    if ((int)$id_estado_actual !== (int)$id_estado_solicitada) {
        throw new Exception('Esta solicitud ya fue respondida anteriormente.');
    }

    if ($decision === 'APROBADA') {
        $id_lote = (int)$accion['id_lote'];
        $id_sucursal = (int)$accion['id_sucursal_origen'];
        $cantidad = (int)$accion['cantidad_afectada'];

        $stmt = $conexion->prepare("SELECT cantidad FROM inventario WHERE id_lote = :id_lote AND id_sucursal = :id_sucursal FOR UPDATE");
        $stmt->execute([':id_lote' => $id_lote, ':id_sucursal' => $id_sucursal]);
        $stock = $stmt->fetchColumn();
        if ($stock === false) {
            throw new Exception('No hay inventario registrado para ese lote en la sucursal');
        }
        if ($stock < $cantidad) {
            throw new Exception('Stock insuficiente para completar la devolución (' . $stock . ' disponibles, se requieren ' . $cantidad . ')');
        }

        $motivoMovimiento = 'Devolución a proveedor aprobada' . ($observaciones !== '' ? ': ' . $observaciones : '');

        $stmt = $conexion->prepare("
            INSERT INTO movimiento_inventario (id_lote, id_sucursal, tipo, cantidad, motivo, referencia, id_usuario)
            VALUES (:id_lote, :id_sucursal, 'SALIDA', :cantidad, :motivo, 'DEVOLUCION_PROVEEDOR', :id_usuario)
            RETURNING id_movimiento
        ");
        $stmt->execute([
            ':id_lote' => $id_lote, ':id_sucursal' => $id_sucursal, ':cantidad' => $cantidad,
            ':motivo' => $motivoMovimiento, ':id_usuario' => $id_usuario,
        ]);
        $id_movimiento = $stmt->fetchColumn();

        $stmt = $conexion->prepare("UPDATE inventario SET cantidad = cantidad - :cantidad WHERE id_lote = :id_lote AND id_sucursal = :id_sucursal");
        $stmt->execute([':cantidad' => $cantidad, ':id_lote' => $id_lote, ':id_sucursal' => $id_sucursal]);

        $stmt = $conexion->prepare("
            UPDATE devoluciones
            SET id_estado = (SELECT id_estado FROM estado_devolucion WHERE nombre = 'COMPLETADA'),
                fecha_aprobacion = NOW(), fecha_completada = NOW(), aprobado_por = :usuario
            WHERE id_devolucion = :id
        ");
        $stmt->execute([':usuario' => $id_usuario, ':id' => $id_devolucion]);

        $stmt = $conexion->prepare("
            UPDATE accion_recuperacion
            SET estado = 'COMPLETADA', id_movimiento = :mov, fecha_ejecucion = NOW(),
                valor_recuperado_estimado = valor_en_riesgo,
                observaciones = CONCAT(COALESCE(observaciones, ''), CASE WHEN :obs = '' THEN '' ELSE E'\\nRespuesta del proveedor (aprobada): ' || :obs END)
            WHERE id_accion = :id_accion
        ");
        $stmt->execute([':mov' => $id_movimiento, ':obs' => $observaciones, ':id_accion' => $id_accion]);

        $mensajeFinal = 'El proveedor aprobó la devolución. Se registró la salida de inventario y la acción quedó COMPLETADA.';

    } else { // RECHAZADA
        $stmt = $conexion->prepare("
            UPDATE devoluciones
            SET id_estado = (SELECT id_estado FROM estado_devolucion WHERE nombre = 'RECHAZADA'),
                fecha_aprobacion = NOW(), aprobado_por = :usuario
            WHERE id_devolucion = :id
        ");
        $stmt->execute([':usuario' => $id_usuario, ':id' => $id_devolucion]);

        $stmt = $conexion->prepare("
            UPDATE accion_recuperacion
            SET estado = 'RECHAZADA_PROVEEDOR',
                observaciones = CONCAT(COALESCE(observaciones, ''), E'\\nRespuesta del proveedor (rechazada): ' || :obs)
            WHERE id_accion = :id_accion
        ");
        $stmt->execute([':obs' => $observaciones, ':id_accion' => $id_accion]);

        $mensajeFinal = 'Se registró el rechazo del proveedor. El lote sigue en riesgo: puede generar una nueva acción (redistribución, promoción u otra estrategia) desde el detalle del lote.';
    }

    $conexion->commit();
    echo json_encode(['success' => true, 'message' => $mensajeFinal]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
