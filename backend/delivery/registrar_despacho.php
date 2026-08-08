<?php
// backend/delivery/registrar_despacho.php
// NUEVO — P-2: el cajero confirma los productos y cantidades que
// entrega físicamente al repartidor antes de su salida. Al
// confirmar, la entrega pasa de ASIGNADA a EN_CAMINO.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id_entrega = intval($data['id_entrega'] ?? 0);
$lineas = $data['lineas'] ?? [];
$observaciones = trim($data['observaciones'] ?? '');
$id_usuario_cajero = $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? 0);

if (!$id_entrega || empty($lineas) || !$id_usuario_cajero) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos para registrar el despacho']); exit();
}

try {
    $conexion->beginTransaction();

    $stmt = $conexion->prepare("
        SELECT e.id_entrega, se.nombre AS estado_actual
        FROM entregas e JOIN estado_entrega se ON se.id_estado = e.id_estado
        WHERE e.id_entrega = :id FOR UPDATE OF e
    ");
    $stmt->execute([':id' => $id_entrega]);
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entrega) { throw new Exception('Entrega no encontrada'); }
    if ($entrega['estado_actual'] !== 'ASIGNADA') {
        throw new Exception('Esta entrega ya no está en estado ASIGNADA (está ' . $entrega['estado_actual'] . ') — no se puede despachar de nuevo');
    }

    $stmtChk = $conexion->prepare("SELECT 1 FROM despacho_entrega WHERE id_entrega = :id");
    $stmtChk->execute([':id' => $id_entrega]);
    if ($stmtChk->fetchColumn()) {
        throw new Exception('Esta entrega ya tiene un despacho registrado');
    }

    $stmtDesp = $conexion->prepare("
        INSERT INTO despacho_entrega (id_entrega, id_usuario_cajero, observaciones)
        VALUES (:id_entrega, :id_cajero, :obs)
        RETURNING id_despacho
    ");
    $stmtDesp->execute([
        ':id_entrega' => $id_entrega,
        ':id_cajero'  => $id_usuario_cajero,
        ':obs'        => $observaciones !== '' ? $observaciones : null,
    ]);
    $id_despacho = $stmtDesp->fetchColumn();

    $stmtDet = $conexion->prepare("
        INSERT INTO detalle_despacho (id_despacho, id_lote, id_producto, cantidad_despachada, observaciones)
        VALUES (:id_despacho, :id_lote, :id_producto, :cantidad, :obs)
    ");
    foreach ($lineas as $l) {
        $cantidad = intval($l['cantidad_despachada'] ?? 0);
        if ($cantidad <= 0) continue;
        $stmtDet->execute([
            ':id_despacho'  => $id_despacho,
            ':id_lote'      => $l['id_lote'] ?? null,
            ':id_producto'  => $l['id_producto'] ?? null,
            ':cantidad'     => $cantidad,
            ':obs'          => $l['observaciones'] ?? null,
        ]);
    }

    $stmtEstado = $conexion->prepare("SELECT id_estado FROM estado_entrega WHERE nombre = 'EN_CAMINO'");
    $stmtEstado->execute();
    $id_estado_en_camino = $stmtEstado->fetchColumn();

    $conexion->prepare("UPDATE entregas SET id_estado = :id_estado WHERE id_entrega = :id")
        ->execute([':id_estado' => $id_estado_en_camino, ':id' => $id_entrega]);

    $conexion->prepare("
        INSERT INTO historial_entrega (id_entrega, id_estado, fecha, id_usuario, observacion)
        VALUES (:id_entrega, :id_estado, NOW(), :id_usuario, 'Despacho confirmado, repartidor sale con el pedido')
    ")->execute([
        ':id_entrega' => $id_entrega,
        ':id_estado'  => $id_estado_en_camino,
        ':id_usuario' => $id_usuario_cajero,
    ]);

    $conexion->commit();
    echo json_encode(['success' => true, 'id_despacho' => $id_despacho]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
