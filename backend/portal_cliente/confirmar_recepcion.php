<?php
// backend/portal_cliente/confirmar_recepcion.php
// NUEVO — Paso 1 de la confirmación del cliente: un simple sí/no de si
// le entregaron el pedido a él (o a alguien autorizado). Antes esto
// estaba mezclado con la calificación en un solo botón "Confirmar y
// calificar" que empujaba directo a la encuesta, y además existía un
// checkbox suelto "¿se entregó a la persona correcta?" dentro de esa
// encuesta que no hacía nada si se marcaba que no (no generaba ninguna
// alerta ni disputa).
//
// Ahora:
//   - Si el cliente dice que SÍ, se llama este endpoint: confirma la
//     recepción y, aparte y sin obligar, se le invita a calificar
//     (ver confirmar_y_calificar.php, que ya no confirma nada por su
//     cuenta, solo guarda la calificación).
//   - Si dice que NO — ya sea porque no recibió nada o porque no fue la
//     persona correcta quien lo recibió — se usa el mismo flujo de
//     disputa que ya existe (ver reportar_no_recibido.php) en vez de
//     este endpoint, para que el caso quede igual de visible para el
//     cajero que cualquier otra disputa.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_cliente_portal'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']); exit();
}
$id_cliente = $_SESSION['id_cliente_portal'];

$data = json_decode(file_get_contents('php://input'), true);
$id_entrega = intval($data['id_entrega'] ?? 0);

if (!$id_entrega) {
    echo json_encode(['success' => false, 'message' => 'Falta la entrega']); exit();
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
        throw new Exception('Ya confirmaste esta entrega antes');
    }

    $conexion->prepare("
        UPDATE entregas
        SET confirmado_por_cliente = TRUE,
            fecha_confirmacion_cliente = NOW(),
            estado_recepcion = 'CONFIRMADO'
        WHERE id_entrega = :id
    ")->execute([':id' => $id_entrega]);

    $conexion->prepare("
        INSERT INTO historial_entrega (id_entrega, id_estado, fecha, observacion)
        SELECT :id, e.id_estado, NOW(), 'El cliente confirmó la recepción de su pedido.'
        FROM entregas e WHERE e.id_entrega = :id2
    ")->execute([':id' => $id_entrega, ':id2' => $id_entrega]);

    $conexion->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
