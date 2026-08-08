<?php
// backend/administracion/revocar_acceso_portal.php
// NUEVO — le quita a un cliente el acceso al portal de seguimiento.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id_cliente = intval($data['id_cliente'] ?? 0);

if (!$id_cliente) {
    echo json_encode(['success' => false, 'message' => 'ID de cliente requerido']); exit();
}

try {
    $stmt = $conexion->prepare("
        UPDATE clientes SET usuario_portal = NULL, contrasena_portal = NULL
        WHERE id_cliente = :id AND usuario_portal IS NOT NULL
    ");
    $stmt->execute([':id' => $id_cliente]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Este cliente no tenía acceso al portal']); exit();
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
