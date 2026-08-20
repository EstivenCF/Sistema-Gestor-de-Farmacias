<?php
require_once '../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}
if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Inventario'])) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos para editar devoluciones']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_devolucion = $input['id_devolucion'] ?? 0;
$id_estado = $input['id_estado'] ?? 0;

if (!$id_devolucion || !$id_estado) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$id_usuario = $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? 0);
if (!$id_usuario) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $conexion->beginTransaction();

    // Obtener estado actual
    $stmt = $conexion->prepare("
        SELECT d.id_estado, ed.nombre AS estado_nombre
        FROM devoluciones d JOIN estado_devolucion ed ON ed.id_estado = d.id_estado
        WHERE d.id_devolucion = ?
    ");
    $stmt->execute([$id_devolucion]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$actual) {
        throw new Exception("Devolución no encontrada");
    }

    // El paso SOLICITADA -> APROBADA/RECHAZADA ya no se hace desde aquí:
    // ese cambio exige una nota de verificación y se hace con los botones
    // "Aprobar"/"Rechazar" (backend/inventario/validar_devolucion.php).
    // Este editor genérico sigue sirviendo para el resto del ciclo
    // (por ejemplo pasar de APROBADA a COMPLETADA, o anular).
    if ($actual['estado_nombre'] === 'SOLICITADA') {
        throw new Exception('Esta devolución todavía está pendiente de verificar — usa los botones "Aprobar" o "Rechazar", no el editor de estado');
    }

    // Actualizar estado
    $stmt = $conexion->prepare("UPDATE devoluciones SET id_estado = ?, aprobado_por = ? WHERE id_devolucion = ?");
    $stmt->execute([$id_estado, $id_usuario, $id_devolucion]);

    // Si el nuevo estado es APROBADA (id_estado = 2 según tu tabla), registrar fecha de aprobación
    if ($id_estado == 2) {
        $stmt = $conexion->prepare("UPDATE devoluciones SET fecha_aprobacion = NOW() WHERE id_devolucion = ?");
        $stmt->execute([$id_devolucion]);
    }
    // Si es COMPLETADA (id_estado = 4), registrar fecha de completada y ajustar inventario (opcional)
    if ($id_estado == 4) {
        $stmt = $conexion->prepare("UPDATE devoluciones SET fecha_completada = NOW() WHERE id_devolucion = ?");
        $stmt->execute([$id_devolucion]);
        // Aquí podrías agregar lógica para ajustar inventario, reembolsos, etc.
    }

    $conexion->commit();
    echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>