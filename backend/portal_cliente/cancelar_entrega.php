<?php
// backend/portal_cliente/cancelar_entrega.php
// NUEVO — el cliente cancela su propia entrega, con motivo obligatorio
// (del catálogo) y comentario opcional. Si ya tenía vehículo asignado,
// se libera.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_cliente_portal'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']); exit();
}
$id_cliente = $_SESSION['id_cliente_portal'];

$data = json_decode(file_get_contents('php://input'), true);
$id_entrega = intval($data['id_entrega'] ?? 0);
$id_motivo = intval($data['id_motivo'] ?? 0);
$comentario = trim($data['comentario'] ?? '');

if (!$id_entrega || !$id_motivo) {
    echo json_encode(['success' => false, 'message' => 'Indica el motivo de la cancelación']); exit();
}

try {
    $conexion->beginTransaction();

    // Verificar que la entrega sea de este cliente y que se pueda cancelar
    $stmt = $conexion->prepare("
        SELECT e.id_entrega, e.id_vehiculo, se.nombre AS estado_actual
        FROM entregas e
        JOIN estado_entrega se ON se.id_estado = e.id_estado
        WHERE e.id_entrega = :id AND e.id_cliente = :id_cliente
        FOR UPDATE OF e
    ");
    $stmt->execute([':id' => $id_entrega, ':id_cliente' => $id_cliente]);
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entrega) {
        throw new Exception('Entrega no encontrada');
    }
    if (!in_array($entrega['estado_actual'], ['PENDIENTE', 'ASIGNADA'])) {
        throw new Exception('Esta entrega ya está ' . strtolower($entrega['estado_actual']) . ' y no se puede cancelar desde aquí');
    }

    $stmtEstado = $conexion->prepare("SELECT id_estado FROM estado_entrega WHERE nombre = 'CANCELADA'");
    $stmtEstado->execute();
    $id_estado_cancelada = $stmtEstado->fetchColumn();

    $stmt = $conexion->prepare("
        UPDATE entregas
        SET id_estado = :id_estado, cancelado_por = 'CLIENTE',
            id_motivo_cancelacion = :id_motivo, comentario_cancelacion = :comentario,
            fecha_modificacion = NOW()
        WHERE id_entrega = :id_entrega
    ");
    $stmt->execute([
        ':id_estado'   => $id_estado_cancelada,
        ':id_motivo'   => $id_motivo,
        ':comentario'  => $comentario,
        ':id_entrega'  => $id_entrega,
    ]);

    if (!empty($entrega['id_vehiculo'])) {
        $conexion->prepare("UPDATE vehiculos SET estado = 'DISPONIBLE' WHERE id_vehiculo = :id AND estado = 'EN_USO'")
            ->execute([':id' => $entrega['id_vehiculo']]);
    }

    $stmtMotivo = $conexion->prepare("SELECT nombre FROM motivo_cancelacion_cliente WHERE id_motivo = :id");
    $stmtMotivo->execute([':id' => $id_motivo]);
    $nombreMotivo = $stmtMotivo->fetchColumn();

    $conexion->prepare("
        INSERT INTO historial_entrega (id_entrega, id_estado, fecha, observacion)
        VALUES (:id_entrega, :id_estado, NOW(), :obs)
    ")->execute([
        ':id_entrega' => $id_entrega,
        ':id_estado'  => $id_estado_cancelada,
        ':obs'        => 'Cancelada por el cliente: ' . $nombreMotivo . ($comentario !== '' ? ' — ' . $comentario : ''),
    ]);

    $conexion->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
