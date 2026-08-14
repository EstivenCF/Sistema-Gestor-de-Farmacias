<?php
// backend/delivery/registrar_despacho.php
// El cajero confirma los productos y cantidades que entrega físicamente
// al repartidor antes de su salida. Al confirmar, la entrega pasa a
// EN_CAMINO.
//
// ACTUALIZACIÓN — Redespacho tras entrega PARCIAL:
//   Una entrega puede pasar por varias "rondas" de despacho hasta quedar
//   completamente entregada. Ya no se bloquea si "ya tiene un despacho":
//   se permite un despacho nuevo mientras la entrega esté ASIGNADA (primera
//   vez) o PARCIAL (quedó algo pendiente de una ronda anterior). Cada
//   despacho queda guardado con su propio número de ronda (id_ronda), y
//   las cantidades se topan siempre contra lo que de verdad sigue
//   pendiente (pedido - ya entregado en rondas previas), nunca contra lo
//   que mande el navegador.

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
        SELECT e.id_entrega, e.id_venta, se.nombre AS estado_actual
        FROM entregas e JOIN estado_entrega se ON se.id_estado = e.id_estado
        WHERE e.id_entrega = :id FOR UPDATE OF e
    ");
    $stmt->execute([':id' => $id_entrega]);
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entrega) { throw new Exception('Entrega no encontrada'); }
    if (!in_array($entrega['estado_actual'], ['ASIGNADA', 'PARCIAL'])) {
        throw new Exception('Esta entrega está ' . $entrega['estado_actual'] . ' — no se puede despachar de nuevo');
    }

    // Cuánto se pidió realmente, por línea (verdad del servidor)
    $stmt = $conexion->prepare("
        SELECT dv.id_lote, dv.id_producto, dv.cantidad
        FROM detalle_venta dv
        WHERE dv.id_venta = :id_venta
    ");
    $stmt->execute([':id_venta' => $entrega['id_venta']]);
    $pedidoPorClave = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $clave = $row['id_lote'] !== null ? 'L' . $row['id_lote'] : 'P' . $row['id_producto'];
        $pedidoPorClave[$clave] = ($pedidoPorClave[$clave] ?? 0) + (int) $row['cantidad'];
    }

    // Cuánto se ha entregado ya, sumando TODAS las rondas anteriores
    $stmt = $conexion->prepare("
        SELECT dc.id_lote, dc.id_producto, SUM(dc.cantidad_entregada) AS entregado
        FROM detalle_conciliacion dc
        JOIN conciliacion_entrega ce ON ce.id_conciliacion = dc.id_conciliacion
        WHERE ce.id_entrega = :id
        GROUP BY dc.id_lote, dc.id_producto
    ");
    $stmt->execute([':id' => $id_entrega]);
    $entregadoPorClave = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $clave = $row['id_lote'] !== null ? 'L' . $row['id_lote'] : 'P' . $row['id_producto'];
        $entregadoPorClave[$clave] = (int) $row['entregado'];
    }

    // Próxima ronda de despacho para esta entrega. Se toma el máximo entre
    // despacho_entrega Y conciliacion_entrega (no solo despacho_entrega) —
    // hay entregas viejas que llegaron a PARCIAL sin pasar nunca por un
    // despacho_entrega real (datos de antes de este flujo, o un cambio de
    // estado manual del administrador), y si solo miráramos despacho_entrega
    // se podría repetir un número de ronda que la conciliación ya usó.
    $stmt = $conexion->prepare("
        SELECT COALESCE(GREATEST(
            (SELECT MAX(id_ronda) FROM despacho_entrega WHERE id_entrega = :id1),
            (SELECT MAX(id_ronda) FROM conciliacion_entrega WHERE id_entrega = :id2)
        ), 0) + 1
    ");
    $stmt->execute([':id1' => $id_entrega, ':id2' => $id_entrega]);
    $id_ronda = (int) $stmt->fetchColumn();

    // Topar cada línea contra lo que de verdad sigue pendiente
    $lineasValidadas = [];
    foreach ($lineas as $l) {
        $id_lote = $l['id_lote'] ?? null;
        $id_producto = $l['id_producto'] ?? null;
        $clave = $id_lote !== null && $id_lote !== '' ? 'L' . $id_lote : 'P' . $id_producto;
        $pedida_total = $pedidoPorClave[$clave] ?? 0;
        $entregado_previo = $entregadoPorClave[$clave] ?? 0;
        $pendiente = max(0, $pedida_total - $entregado_previo);

        $cantidad = min($pendiente, intval($l['cantidad_despachada'] ?? 0));
        if ($cantidad <= 0) continue;

        $lineasValidadas[] = [
            'id_lote'     => $id_lote !== '' ? $id_lote : null,
            'id_producto' => $id_producto !== '' ? $id_producto : null,
            'cantidad'    => $cantidad,
            'obs'         => $l['observaciones'] ?? null,
        ];
    }

    if (empty($lineasValidadas)) {
        throw new Exception('No queda nada pendiente por despachar en esta entrega, o las cantidades indicadas no son válidas.');
    }

    $stmtDesp = $conexion->prepare("
        INSERT INTO despacho_entrega (id_entrega, id_ronda, id_usuario_cajero, observaciones)
        VALUES (:id_entrega, :id_ronda, :id_cajero, :obs)
        RETURNING id_despacho
    ");
    $stmtDesp->execute([
        ':id_entrega' => $id_entrega,
        ':id_ronda'   => $id_ronda,
        ':id_cajero'  => $id_usuario_cajero,
        ':obs'        => $observaciones !== '' ? $observaciones : null,
    ]);
    $id_despacho = $stmtDesp->fetchColumn();

    $stmtDet = $conexion->prepare("
        INSERT INTO detalle_despacho (id_despacho, id_lote, id_producto, cantidad_despachada, observaciones)
        VALUES (:id_despacho, :id_lote, :id_producto, :cantidad, :obs)
    ");
    foreach ($lineasValidadas as $l) {
        $stmtDet->execute([
            ':id_despacho'  => $id_despacho,
            ':id_lote'      => $l['id_lote'],
            ':id_producto'  => $l['id_producto'],
            ':cantidad'     => $l['cantidad'],
            ':obs'          => $l['obs'],
        ]);
    }

    $stmtEstado = $conexion->prepare("SELECT id_estado FROM estado_entrega WHERE nombre = 'EN_CAMINO'");
    $stmtEstado->execute();
    $id_estado_en_camino = $stmtEstado->fetchColumn();

    $conexion->prepare("UPDATE entregas SET id_estado = :id_estado WHERE id_entrega = :id")
        ->execute([':id_estado' => $id_estado_en_camino, ':id' => $id_entrega]);

    $conexion->prepare("
        INSERT INTO historial_entrega (id_entrega, id_estado, fecha, id_usuario, observacion)
        VALUES (:id_entrega, :id_estado, NOW(), :id_usuario, :obs)
    ")->execute([
        ':id_entrega' => $id_entrega,
        ':id_estado'  => $id_estado_en_camino,
        ':id_usuario' => $id_usuario_cajero,
        ':obs'        => $id_ronda > 1
            ? "Despacho de lo pendiente confirmado (ronda $id_ronda), repartidor sale de nuevo con el pedido"
            : 'Despacho confirmado, repartidor sale con el pedido',
    ]);

    $conexion->commit();
    echo json_encode(['success' => true, 'id_despacho' => $id_despacho, 'id_ronda' => $id_ronda]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
