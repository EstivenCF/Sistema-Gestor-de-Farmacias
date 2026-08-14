<?php
// backend/administracion/eliminar_categoria_calificacion.php
// NUEVO — borra de verdad una sección, pero solo si ya no tiene ninguna
// pregunta asignada (para no dejar preguntas huérfanas ni romper el
// desglose de calificaciones ya guardadas). Si todavía tiene preguntas,
// avisa cuántas hay para que el admin las mueva o las borre primero.

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
$id_categoria = intval($data['id_categoria'] ?? 0);

if (!$id_categoria) {
    echo json_encode(['success' => false, 'message' => 'Falta la sección']); exit();
}

try {
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM preguntas_calificacion WHERE id_categoria = :id");
    $stmt->execute([':id' => $id_categoria]);
    $total = (int) $stmt->fetchColumn();

    if ($total > 0) {
        echo json_encode([
            'success' => false,
            'message' => "Esta sección todavía tiene $total pregunta(s). Muévelas a otra sección o bórralas antes de eliminarla.",
        ]);
        exit();
    }

    $conexion->prepare("DELETE FROM categorias_calificacion WHERE id_categoria = :id")->execute([':id' => $id_categoria]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
