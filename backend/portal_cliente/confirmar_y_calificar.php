<?php
// backend/portal_cliente/confirmar_y_calificar.php
// NUEVO — el cliente confirma que recibió su entrega y califica en un
// solo paso, en 3 bloques: repartidor, el medicamento, y general
// (puntualidad, persona correcta).

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_cliente_portal'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']); exit();
}
$id_cliente = $_SESSION['id_cliente_portal'];

$data = json_decode(file_get_contents('php://input'), true);
$id_entrega = intval($data['id_entrega'] ?? 0);

// Bloque 1: repartidor
$atencion = intval($data['atencion'] ?? 0);
// Bloque 2: el medicamento
$estado_producto = intval($data['estado_producto'] ?? 0);
$presentacion = intval($data['presentacion'] ?? 0);
// Bloque 3: general
$puntualidad = intval($data['puntualidad'] ?? 0);
$persona_correcta = filter_var($data['persona_correcta'] ?? true, FILTER_VALIDATE_BOOLEAN);
$puntuacion_general = intval($data['puntuacion_general'] ?? 0);
$comentario = trim($data['comentario'] ?? '');

foreach (['atencion' => $atencion, 'estado_producto' => $estado_producto, 'presentacion' => $presentacion,
          'puntualidad' => $puntualidad, 'puntuacion_general' => $puntuacion_general] as $campo => $valor) {
    if ($valor < 1 || $valor > 5) {
        echo json_encode(['success' => false, 'message' => "Falta calificar: $campo (debe ser de 1 a 5 estrellas)"]); exit();
    }
}

try {
    $conexion->beginTransaction();

    $stmt = $conexion->prepare("
        SELECT e.id_entrega, se.nombre AS estado_actual
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

    $stmtChk = $conexion->prepare("SELECT confirmado_por_cliente FROM entregas WHERE id_entrega = :id");
    $stmtChk->execute([':id' => $id_entrega]);
    if (filter_var($stmtChk->fetchColumn(), FILTER_VALIDATE_BOOLEAN)) {
        throw new Exception('Ya confirmaste y calificaste esta entrega antes');
    }

    $conexion->prepare("
        UPDATE entregas
        SET confirmado_por_cliente = TRUE, fecha_confirmacion_cliente = NOW()
        WHERE id_entrega = :id
    ")->execute([':id' => $id_entrega]);

    $conexion->prepare("
        INSERT INTO calificaciones_entrega
            (id_entrega, id_cliente, puntualidad, atencion, estado_producto, presentacion,
             puntuacion_general, persona_correcta, comentario)
        VALUES
            (:id_entrega, :id_cliente, :puntualidad, :atencion, :estado_producto, :presentacion,
             :puntuacion_general, :persona_correcta, :comentario)
    ")->execute([
        ':id_entrega'         => $id_entrega,
        ':id_cliente'         => $id_cliente,
        ':puntualidad'        => $puntualidad,
        ':atencion'           => $atencion,
        ':estado_producto'    => $estado_producto,
        ':presentacion'       => $presentacion,
        ':puntuacion_general' => $puntuacion_general,
        ':persona_correcta'   => $persona_correcta ? 't' : 'f',
        ':comentario'         => $comentario !== '' ? $comentario : null,
    ]);

    $conexion->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
