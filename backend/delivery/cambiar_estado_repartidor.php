<?php
require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['id_repartidor']) || !isset($data['activo'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    $stmt = $conexion->prepare("UPDATE repartidores SET activo = :activo WHERE id_repartidor = :id_repartidor");
    $stmt->execute([
        ':activo' => $data['activo'] ? 't' : 'f',
        ':id_repartidor' => $data['id_repartidor']
    ]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>