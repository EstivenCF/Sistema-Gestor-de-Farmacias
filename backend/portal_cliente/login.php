<?php
// backend/portal_cliente/login.php
// NUEVO — login del portal de clientes. Usa su propia sesión
// ($_SESSION['id_cliente_portal']), separada de la de los usuarios
// del staff, para que no se puedan mezclar ni pisar una a la otra.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$usuario = trim($data['usuario'] ?? '');
$password = (string)($data['password'] ?? '');

if ($usuario === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Ingresa tu usuario y contraseña']); exit();
}

try {
    $stmt = $conexion->prepare("
        SELECT id_cliente, nombre, contrasena_portal
        FROM clientes
        WHERE usuario_portal = :usuario
    ");
    $stmt->execute([':usuario' => $usuario]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente || !$cliente['contrasena_portal'] || !password_verify($password, $cliente['contrasena_portal'])) {
        echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos']); exit();
    }

    $_SESSION['id_cliente_portal'] = $cliente['id_cliente'];
    $_SESSION['nombre_cliente_portal'] = $cliente['nombre'];

    echo json_encode(['success' => true, 'nombre' => $cliente['nombre']]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
