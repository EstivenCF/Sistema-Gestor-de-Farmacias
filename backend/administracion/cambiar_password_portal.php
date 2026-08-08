<?php
// backend/administracion/cambiar_password_portal.php
// REEMPLAZA el archivo existente — ahora también permite cambiar el
// nombre de usuario del portal, no solo la contraseña. La contraseña
// es opcional: si se deja en blanco, no se toca.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id_cliente = intval($data['id_cliente'] ?? 0);
$usuario_nuevo = trim($data['usuario_nuevo'] ?? '');
$password_nueva = (string)($data['password_nueva'] ?? '');

if (!$id_cliente || $usuario_nuevo === '') {
    echo json_encode(['success' => false, 'message' => 'El usuario no puede quedar vacío']); exit();
}
if ($password_nueva !== '' && strlen($password_nueva) < 4) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 4 caracteres']); exit();
}

try {
    $conexion->beginTransaction();

    // Confirmar que el cliente existe y ya tiene acceso
    $stmt = $conexion->prepare("SELECT usuario_portal FROM clientes WHERE id_cliente = :id");
    $stmt->execute([':id' => $id_cliente]);
    $usuario_actual = $stmt->fetchColumn();
    if ($usuario_actual === false || $usuario_actual === null) {
        throw new Exception('Este cliente no tiene acceso al portal');
    }

    // Si el usuario cambió, confirmar que el nuevo no esté en uso por otro cliente
    if ($usuario_nuevo !== $usuario_actual) {
        $stmtChk = $conexion->prepare("SELECT 1 FROM clientes WHERE usuario_portal = :u AND id_cliente != :id");
        $stmtChk->execute([':u' => $usuario_nuevo, ':id' => $id_cliente]);
        if ($stmtChk->fetchColumn()) {
            throw new Exception("El usuario '$usuario_nuevo' ya está en uso por otro cliente");
        }
    }

    if ($password_nueva !== '') {
        $conexion->prepare("UPDATE clientes SET usuario_portal = :u, contrasena_portal = :p WHERE id_cliente = :id")
            ->execute([
                ':u' => $usuario_nuevo,
                ':p' => password_hash($password_nueva, PASSWORD_DEFAULT),
                ':id' => $id_cliente,
            ]);
    } else {
        $conexion->prepare("UPDATE clientes SET usuario_portal = :u WHERE id_cliente = :id")
            ->execute([':u' => $usuario_nuevo, ':id' => $id_cliente]);
    }

    $conexion->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
