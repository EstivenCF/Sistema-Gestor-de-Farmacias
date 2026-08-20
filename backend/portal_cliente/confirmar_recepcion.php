<?php
// backend/portal_cliente/confirmar_recepcion.php
// Paso 1 de la confirmación del cliente. Antes esto estaba mezclado con
// la calificación en un solo botón "Confirmar y calificar" que empujaba
// directo a la encuesta, y además existía un checkbox suelto "¿se
// entregó a la persona correcta?" dentro de esa encuesta que no hacía
// nada si se marcaba que no (no generaba ninguna alerta ni disputa).
//
// ACTUALIZACIÓN — cantidad por medicamento: ya no basta con un sí/no
// genérico. El cliente ahora indica cuánto recibió de CADA medicamento
// (productos: [{id_lote, id_producto, cantidad_recibida}]). Se compara
// contra lo que el cajero de verdad despachó al repartidor en la ronda
// ACTUAL de esta entrega (despacho_entrega/detalle_despacho filtrado por
// entregas.ronda_actual — única fuente de verdad, ver PATCH 25/25 en
// Farmacia.sql):
//   - Si coincide todo -> se confirma normal (estado_recepcion =
//     'CONFIRMADO'), y aparte y sin obligar, se le invita a calificar
//     (ver confirmar_y_calificar.php).
//   - Si no coincide (el cajero despachó completo pero el cliente dice
//     que le entregaron menos, o cualquier otra diferencia) -> se manda
//     al mismo flujo de disputa que ya existe para "no recibí nada"
//     (estado_recepcion = 'EN_DISPUTA'), para que el cajero tenga que
//     llamar al cliente y al repartidor a aclarar la situación antes de
//     poder conciliar (ver resolver_disputa.php).
//
// Si dice que NO recibió nada en absoluto, se sigue usando
// reportar_no_recibido.php en vez de este endpoint — mismo resultado
// final (EN_DISPUTA), pero con su propio comentario libre.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_cliente_portal'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']); exit();
}
$id_cliente = $_SESSION['id_cliente_portal'];

$data = json_decode(file_get_contents('php://input'), true);
$id_entrega = intval($data['id_entrega'] ?? 0);
$productosInput = $data['productos'] ?? []; // [{id_lote, id_producto, cantidad_recibida}]

if (!$id_entrega) {
    echo json_encode(['success' => false, 'message' => 'Falta la entrega']); exit();
}
if (empty($productosInput)) {
    echo json_encode(['success' => false, 'message' => 'Debes indicar cuánto recibiste de cada medicamento']); exit();
}

try {
    $conexion->beginTransaction();

    $stmt = $conexion->prepare("
        SELECT e.id_entrega, e.ronda_actual, se.nombre AS estado_actual, e.confirmado_por_cliente
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

    // Lo que de verdad se despachó en la ronda actual — la verdad del
    // servidor, nunca lo que mande el navegador.
    $stmt = $conexion->prepare("
        SELECT dd.id_lote, dd.id_producto,
               COALESCE(m.nombre_completo, m.nombre, p.nombre) AS nombre,
               SUM(dd.cantidad_despachada) AS despachado
        FROM despacho_entrega de
        JOIN detalle_despacho dd ON dd.id_despacho = de.id_despacho
        LEFT JOIN lotes l ON l.id_lote = dd.id_lote
        LEFT JOIN medicamentos m ON m.id_medicamento = l.id_medicamento
        LEFT JOIN productos p ON p.id_producto = dd.id_producto
        WHERE de.id_entrega = :id AND de.id_ronda = :ronda
        GROUP BY dd.id_lote, dd.id_producto, m.nombre_completo, m.nombre, p.nombre
    ");
    $stmt->execute([':id' => $id_entrega, ':ronda' => $entrega['ronda_actual']]);
    $despachadoPorClave = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $clave = $row['id_lote'] !== null ? 'L' . $row['id_lote'] : 'P' . $row['id_producto'];
        $despachadoPorClave[$clave] = ['nombre' => $row['nombre'], 'cantidad' => (int) $row['despachado']];
    }

    $recibidoPorClave = [];
    foreach ($productosInput as $p) {
        $id_lote = $p['id_lote'] ?? null;
        $id_producto = $p['id_producto'] ?? null;
        $clave = ($id_lote !== null && $id_lote !== '') ? 'L' . $id_lote : 'P' . $id_producto;
        $recibidoPorClave[$clave] = max(0, intval($p['cantidad_recibida'] ?? 0));
    }

    $discrepancias = [];
    foreach ($despachadoPorClave as $clave => $info) {
        $recibido = $recibidoPorClave[$clave] ?? 0;
        if ($recibido !== $info['cantidad']) {
            $discrepancias[] = "{$info['nombre']}: dice haber recibido $recibido de {$info['cantidad']}";
        }
    }

    if ($discrepancias) {
        $comentario = 'El cliente confirmó la recepción, pero reporta cantidades distintas a lo despachado — '
            . implode('; ', $discrepancias) . '.';

        $conexion->prepare("
            UPDATE entregas
            SET confirmado_por_cliente = TRUE,
                fecha_confirmacion_cliente = NOW(),
                estado_recepcion = 'EN_DISPUTA',
                comentario_cliente = :comentario
            WHERE id_entrega = :id
        ")->execute([':comentario' => $comentario, ':id' => $id_entrega]);

        $conexion->prepare("
            INSERT INTO historial_entrega (id_entrega, id_estado, fecha, observacion)
            SELECT :id, e.id_estado, NOW(), :obs
            FROM entregas e WHERE e.id_entrega = :id2
        ")->execute([':id' => $id_entrega, ':id2' => $id_entrega, ':obs' => $comentario]);

        $conexion->commit();
        echo json_encode(['success' => true, 'disputa' => true]);
        exit();
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
        SELECT :id, e.id_estado, NOW(), 'El cliente confirmó la recepción de su pedido — las cantidades coinciden con lo despachado.'
        FROM entregas e WHERE e.id_entrega = :id2
    ")->execute([':id' => $id_entrega, ':id2' => $id_entrega]);

    $conexion->commit();
    echo json_encode(['success' => true, 'disputa' => false]);

} catch (Exception $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
