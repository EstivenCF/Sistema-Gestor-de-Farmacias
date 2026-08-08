<?php
require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['id_proveedor'])) {
    echo json_encode(['success' => false, 'message' => 'ID de proveedor requerido']);
    exit();
}

$id_proveedor = intval($data['id_proveedor']);

try {
    // Verificar si tiene compras asociadas
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM compras WHERE id_proveedor = ?");
    $stmt->execute([$id_proveedor]);
    $compras = $stmt->fetchColumn();
    if ($compras > 0) {
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar el proveedor porque tiene compras registradas']);
        exit();
    }

    // Eliminar relaciones (ON DELETE CASCADE se encarga, pero por si acaso)
    $stmt = $conexion->prepare("DELETE FROM proveedor_telefono WHERE id_proveedor = ?");
    $stmt->execute([$id_proveedor]);
    $stmt = $conexion->prepare("DELETE FROM proveedor_correo WHERE id_proveedor = ?");
    $stmt->execute([$id_proveedor]);
    $stmt = $conexion->prepare("DELETE FROM proveedores WHERE id_proveedor = ?");
    $stmt->execute([$id_proveedor]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>