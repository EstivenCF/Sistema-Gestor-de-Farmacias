<?php
require_once '../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion']) || !isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_devolucion = $input['id_devolucion'] ?? 0;
$id_estado = $input['id_estado'] ?? 0;

if (!$id_devolucion || !$id_estado) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$id_usuario = $_SESSION['id_usuario'];

try {
    $conexion->beginTransaction();

    // Obtener estado actual
    $stmt = $conexion->prepare("SELECT id_estado FROM devoluciones WHERE id_devolucion = ?");
    $stmt->execute([$id_devolucion]);
    $estado_actual = $stmt->fetchColumn();

    if (!$estado_actual) {
        throw new Exception("Devolución no encontrada");
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