<?php
// backend/portal_cliente/reportar_no_recibido.php
// NUEVO — El cliente marca desde su Portal que el repartidor dijo que
// entregó el pedido, pero él no recibió nada (o no fue a él). Hasta
// ahora la única opción del cliente ante una entrega ENTREGADA era
// "Confirmar y calificar" — no había forma de decir que algo está mal.
//
// Reutiliza el campo estado_recepcion que ya existía en la tabla
// entregas (pensado para cuando la cédula de quien recibió no coincidía
// con la autorizada, aunque ese chequeo nunca se llegó a usar en la
// práctica) — un valor 'EN_DISPUTA' aquí significa lo mismo en el fondo:
// "esta entrega quedó marcada como entregada, pero algo no cuadra con
// la recepción real". El motivo que escribe el cliente se guarda en
// comentario_cliente para que el staff sepa exactamente qué pasó.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_cliente_portal'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']); exit();
}
$id_cliente = $_SESSION['id_cliente_portal'];

$data = json_decode(file_get_contents('php://input'), true);
$id_entrega = intval($data['id_entrega'] ?? 0);
$comentario = trim($data['comentario'] ?? '');

if (!$id_entrega) {
    echo json_encode(['success' => false, 'message' => 'Falta la entrega']); exit();
}
if ($comentario === '') {
    echo json_encode(['success' => false, 'message' => 'Cuéntanos qué pasó — no llegó nadie, llegó incompleto, etc.']); exit();
}

try {
    $conexion->beginTransaction();

    $stmt = $conexion->prepare("
        SELECT e.id_entrega, se.nombre AS estado_actual, e.confirmado_por_cliente
        FROM entregas e
        JOIN estado_entrega se ON se.id_estado = e.id_estado
        WHERE e.id_entrega = :id AND e.id_cliente = :id_cliente
        FOR UPDATE OF e
    ");
    $stmt->execute([':id' => $id_entrega, ':id_cliente' => $id_cliente]);
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entrega) { throw new Exception('Entrega no encontrada'); }
    if ($entrega['estado_actual'] !== 'ENTREGADA') {
        throw new Exception('Esta entrega todavía no ha sido marcada como entregada por el repartidor');
    }
    if (filter_var($entrega['confirmado_por_cliente'], FILTER_VALIDATE_BOOLEAN)) {
        throw new Exception('Ya respondiste sobre esta entrega antes');
    }

    $conexion->prepare("
        UPDATE entregas
        SET confirmado_por_cliente = TRUE,
            fecha_confirmacion_cliente = NOW(),
            estado_recepcion = 'EN_DISPUTA',
            comentario_cliente = :comentario
        WHERE id_entrega = :id
    ")->execute([
        ':comentario' => $comentario,
        ':id'         => $id_entrega,
    ]);

    $conexion->prepare("
        INSERT INTO historial_entrega (id_entrega, id_estado, fecha, observacion)
        SELECT :id_entrega, e.id_estado, NOW(), :obs
        FROM entregas e WHERE e.id_entrega = :id_entrega
    ")->execute([
        ':id_entrega' => $id_entrega,
        ':obs'        => 'El cliente reporta que NO recibió este pedido (marcado como entregado): ' . $comentario,
    ]);

    $conexion->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
