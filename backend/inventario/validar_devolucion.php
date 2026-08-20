<?php
// backend/inventario/validar_devolucion.php
// NUEVO — Reemplaza el paso "SOLICITADA -> APROBADA/RECHAZADA" que antes se
// hacía desde un combo box genérico sin pedir nada (guardar_devolucion.php).
// Aquí sí se exige indicar qué se verificó del producto devuelto antes de
// aprobar o rechazar — así queda un responsable, una fecha y una nota real
// de la revisión física, no solo un cambio de estado a ciegas.
//
// Solo se puede usar mientras la devolución está SOLICITADA. Una vez
// aprobada o rechazada, el resto del ciclo (COMPLETADA/ANULADA) se sigue
// manejando con el editor de estado genérico, que ya existía.

require_once '../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}
$rol_sesion = $_SESSION['rol'] ?? '';
if (!in_array($rol_sesion, ['Administrador', 'Inventario', 'Cajero'])) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos para verificar devoluciones']); exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$id_devolucion = intval($input['id_devolucion'] ?? 0);
$accion = $input['accion'] ?? ''; // 'APROBAR' | 'RECHAZAR'
$observaciones = trim($input['observaciones'] ?? '');
$id_usuario = $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? 0);
if (!$id_usuario) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

if (!$id_devolucion || !in_array($accion, ['APROBAR', 'RECHAZAR'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']); exit();
}
if ($observaciones === '') {
    echo json_encode(['success' => false, 'message' => 'Indica qué se verificó del producto devuelto antes de ' . ($accion === 'APROBAR' ? 'aprobar' : 'rechazar')]); exit();
}

try {
    $conexion->beginTransaction();

    $stmt = $conexion->prepare("
        SELECT d.id_devolucion, d.id_entrega, d.confirmado_por_cliente, ed.nombre AS estado_actual
        FROM devoluciones d
        JOIN estado_devolucion ed ON ed.id_estado = d.id_estado
        WHERE d.id_devolucion = :id
        FOR UPDATE OF d
    ");
    $stmt->execute([':id' => $id_devolucion]);
    $dev = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dev) { throw new Exception('Devolución no encontrada'); }

    if ($dev['estado_actual'] !== 'SOLICITADA') {
        throw new Exception('Esta devolución ya fue procesada (está ' . $dev['estado_actual'] . ') — no se puede verificar de nuevo');
    }

    // El Cajero solo confirma devoluciones que vienen de una entrega de
    // delivery (las que ve en Delivery > Devoluciones) — las de mostrador,
    // proveedor o merma se quedan exclusivas de Administrador/Inventario.
    if ($rol_sesion === 'Cajero' && empty($dev['id_entrega'])) {
        throw new Exception('Esta devolución no es de una entrega de delivery — solo Inventario o Administrador pueden verificarla');
    }

    // Si la devolución viene de una entrega de delivery, primero tiene que
    // pasar por el cliente (Portal de Clientes): sin su respuesta (Sí o
    // No) todavía no le toca al cajero/Inventario decidir nada.
    if (!empty($dev['id_entrega']) && $dev['confirmado_por_cliente'] === null) {
        throw new Exception('Todavía no hay respuesta del cliente sobre esta devolución — espera a que confirme desde su Portal antes de aprobar o rechazar');
    }

    $nombre_estado_nuevo = $accion === 'APROBAR' ? 'APROBADA' : 'RECHAZADA';

    $conexion->prepare("
        UPDATE devoluciones
        SET id_estado = (SELECT id_estado FROM estado_devolucion WHERE nombre = :estado),
            aprobado_por = :id_usuario,
            fecha_aprobacion = NOW(),
            observaciones_validacion = :obs
        WHERE id_devolucion = :id
    ")->execute([
        ':estado'     => $nombre_estado_nuevo,
        ':id_usuario' => $id_usuario,
        ':obs'        => $observaciones,
        ':id'         => $id_devolucion,
    ]);

    // Si esta devolución vino de una entrega de delivery, se deja también
    // en la línea de tiempo de la entrega, para que quede todo junto.
    if (!empty($dev['id_entrega'])) {
        $conexion->prepare("
            INSERT INTO historial_entrega (id_entrega, id_estado, fecha, observacion, id_usuario)
            SELECT :id_entrega, e.id_estado, NOW(), :obs, :id_usuario
            FROM entregas e WHERE e.id_entrega = :id_entrega
        ")->execute([
            ':id_entrega' => $dev['id_entrega'],
            ':obs'        => 'Devolución ' . strtolower($nombre_estado_nuevo) . ' por Inventario: ' . $observaciones,
            ':id_usuario' => $id_usuario,
        ]);
    }

    $conexion->commit();
    echo json_encode(['success' => true, 'estado_final' => $nombre_estado_nuevo]);

} catch (Exception $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
