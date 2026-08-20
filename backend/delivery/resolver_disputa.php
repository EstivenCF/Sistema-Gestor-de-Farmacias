<?php
// backend/delivery/resolver_disputa.php
// NUEVO — Antes, una entrega marcada EN_DISPUTA (el cliente reportó que no
// recibió nada) no bloqueaba nada: el cajero podía "Conciliar y Cerrar" el
// pedido como si nada hubiera pasado. Este endpoint obliga al cajero a
// resolver la disputa primero — llamando al cliente y al repartidor para
// aclarar la situación — antes de poder conciliar.
//
// Dos resoluciones posibles:
//   CONFIRMADA  -> fue un malentendido, el cliente sí recibió el pedido.
//                  Se limpia la disputa y la entrega queda lista para
//                  conciliar normalmente.
//   REDESPACHO  -> el cliente tenía razón, no recibió nada. Se limpia la
//                  disputa y se reabre la entrega a PENDIENTE (mismo reset
//                  que reabrir_entrega_devuelta.php) para asignar
//                  repartidor y despachar de nuevo.
// En ambos casos la nota del cajero (obligatoria) queda en historial_entrega.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}
$rol = $_SESSION['rol'] ?? '';
if (!in_array($rol, ['Cajero', 'Administrador'])) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos para resolver disputas de entrega']); exit();
}
$id_usuario = $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? 0);

$data = json_decode(file_get_contents('php://input'), true);
$id_entrega = intval($data['id_entrega'] ?? 0);
$resolucion = $data['resolucion'] ?? ''; // 'CONFIRMADA' | 'REDESPACHO'
$nota = trim($data['nota'] ?? '');

if (!$id_entrega || !in_array($resolucion, ['CONFIRMADA', 'REDESPACHO']) || !$id_usuario) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos para resolver la disputa']); exit();
}
if ($nota === '') {
    echo json_encode(['success' => false, 'message' => 'La nota es obligatoria — cuenta qué te dijeron el cliente y el repartidor al llamarlos']); exit();
}

try {
    $conexion->beginTransaction();

    $stmt = $conexion->prepare("
        SELECT e.id_entrega, e.estado_recepcion
        FROM entregas e
        WHERE e.id_entrega = :id
        FOR UPDATE OF e
    ");
    $stmt->execute([':id' => $id_entrega]);
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entrega) { throw new Exception('Entrega no encontrada'); }
    if ($entrega['estado_recepcion'] !== 'EN_DISPUTA') {
        throw new Exception('Esta entrega no tiene una disputa abierta');
    }

    if ($resolucion === 'CONFIRMADA') {
        $conexion->prepare("
            UPDATE entregas SET estado_recepcion = 'CONFIRMADO' WHERE id_entrega = :id
        ")->execute([':id' => $id_entrega]);

        $conexion->prepare("
            INSERT INTO historial_entrega (id_entrega, id_estado, fecha, observacion, id_usuario)
            SELECT :id, e.id_estado, NOW(), :obs, :uid FROM entregas e WHERE e.id_entrega = :id2
        ")->execute([
            ':id'  => $id_entrega, ':id2' => $id_entrega, ':uid' => $id_usuario,
            ':obs' => 'Disputa resuelta — se llamó al cliente y al repartidor, fue un malentendido: el cliente confirma que sí recibió el pedido. ' . $nota,
        ]);

    } else { // REDESPACHO
        $id_estado_pendiente = $conexion->query("SELECT id_estado FROM estado_entrega WHERE nombre = 'PENDIENTE'")->fetchColumn();
        if (!$id_estado_pendiente) { throw new Exception('No se encontró el estado PENDIENTE'); }

        $conexion->prepare("
            UPDATE entregas
            SET estado_recepcion    = 'CONFIRMADO',
                id_estado           = :pend,
                id_repartidor       = NULL,
                id_vehiculo         = NULL,
                fecha_asignada      = NULL,
                es_entrega_parcial  = FALSE,
                detalle_parcial     = NULL,
                modificado_por      = :uid,
                fecha_modificacion  = NOW()
            WHERE id_entrega = :id
        ")->execute([':pend' => $id_estado_pendiente, ':uid' => $id_usuario, ':id' => $id_entrega]);

        $conexion->prepare("
            INSERT INTO historial_entrega (id_entrega, id_estado, fecha, observacion, id_usuario)
            VALUES (:id, :pend, NOW(), :obs, :uid)
        ")->execute([
            ':id'  => $id_entrega, ':pend' => $id_estado_pendiente, ':uid' => $id_usuario,
            ':obs' => 'Disputa resuelta — se llamó al cliente y al repartidor: el cliente tenía razón, no recibió el pedido. Se reabre para redespacho. ' . $nota,
        ]);
    }

    $conexion->commit();
    echo json_encode(['success' => true, 'resolucion' => $resolucion]);

} catch (Exception $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
