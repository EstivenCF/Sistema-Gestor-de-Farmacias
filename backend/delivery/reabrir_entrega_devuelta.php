<?php
// backend/delivery/reabrir_entrega_devuelta.php
// NUEVO — El cajero (o administrador) reabre una entrega que quedó
// 'DEVUELTA' (el cliente devolvió todo el pedido) para poder despacharla
// de nuevo con productos ya validados (sin vencer, sin defectos).
//
// La entrega vuelve a 'PENDIENTE' y sin repartidor/vehículo asignado,
// exactamente como si fuera una entrega nueva "en cola" — así se reutiliza
// el mismo flujo de Asignar -> Registrar Despacho que ya existe, en vez
// de inventar un camino aparte. Al reasignar, fecha_asignada se refresca
// (ver asignar_entrega_manual.php), y esa fecha es lo que get_detalle_entrega.php
// y registrar_despacho.php usan como límite para no seguir restando la
// devolución de la ronda anterior sobre la cantidad pendiente de esta
// ronda nueva.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}
if (!in_array($_SESSION['rol'] ?? '', ['Cajero', 'Administrador'])) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos para reabrir entregas']); exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id_entrega = intval($data['id_entrega'] ?? 0);
$observaciones = trim($data['observaciones'] ?? '');
$id_usuario = $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? 0);

if (!$id_entrega || !$id_usuario) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']); exit();
}

try {
    $conexion->beginTransaction();

    $stmt = $conexion->prepare("
        SELECT e.id_entrega, se.nombre AS estado_actual
        FROM entregas e
        JOIN estado_entrega se ON se.id_estado = e.id_estado
        WHERE e.id_entrega = :id
        FOR UPDATE OF e
    ");
    $stmt->execute([':id' => $id_entrega]);
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$entrega) { throw new Exception('Entrega no encontrada'); }

    if ($entrega['estado_actual'] !== 'DEVUELTA') {
        throw new Exception('Esta entrega está ' . $entrega['estado_actual'] . ' — solo se puede reabrir una entrega Devolución');
    }

    $conexion->prepare("
        UPDATE entregas
        SET id_estado = (SELECT id_estado FROM estado_entrega WHERE nombre = 'PENDIENTE'),
            id_repartidor = NULL,
            id_vehiculo = NULL,
            fecha_asignada = NULL,
            es_entrega_parcial = FALSE,
            detalle_parcial = NULL,
            modificado_por = :id_usuario,
            fecha_modificacion = NOW()
        WHERE id_entrega = :id
    ")->execute([':id_usuario' => $id_usuario, ':id' => $id_entrega]);

    $obs = 'Entrega reabierta por el cajero para un nuevo despacho con productos validados'
        . ($observaciones !== '' ? ': ' . $observaciones : '.');

    $conexion->prepare("
        INSERT INTO historial_entrega (id_entrega, id_estado, fecha, observacion, id_usuario)
        VALUES (:id_entrega, (SELECT id_estado FROM estado_entrega WHERE nombre = 'PENDIENTE'), NOW(), :obs, :id_usuario)
    ")->execute([
        ':id_entrega' => $id_entrega,
        ':obs'        => $obs,
        ':id_usuario' => $id_usuario,
    ]);

    $conexion->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
