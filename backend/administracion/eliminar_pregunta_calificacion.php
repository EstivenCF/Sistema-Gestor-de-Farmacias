<?php
// backend/administracion/eliminar_pregunta_calificacion.php
// NUEVO — borra de verdad una pregunta del formulario de calificación.
// Las respuestas de clientes que ya se dieron a esta pregunta NO se pierden
// ni se ven afectadas: respuestas_calificacion guarda su propia "foto"
// (texto_pregunta, categoria, tipo_respuesta) al momento de responder, y su
// columna id_pregunta tiene ON DELETE SET NULL, así que borrar la pregunta
// viva no rompe el historial.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}
if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    echo json_encode(['success' => false, 'message' => 'Solo administradores']); exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id_pregunta = intval($data['id_pregunta'] ?? 0);

if (!$id_pregunta) {
    echo json_encode(['success' => false, 'message' => 'Falta la pregunta']); exit();
}

try {
    $stmt = $conexion->prepare("DELETE FROM preguntas_calificacion WHERE id_pregunta = :id");
    $stmt->execute([':id' => $id_pregunta]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Esa pregunta ya no existe']); exit();
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
