<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

require_once __DIR__ . '/../conexion.php';

$data = json_decode(file_get_contents('php://input'), true);
$id_medicamento = $data['id_medicamento'] ?? 0;

if (!$id_medicamento) {
    echo json_encode(['success' => false, 'message' => 'ID de medicamento no especificado']);
    exit();
}

try {
    $conexion->beginTransaction();
    
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM lotes WHERE id_medicamento = ?");
    $stmt->execute([$id_medicamento]);
    $lotes_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($lotes_count > 0) {
        $conexion->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'No se puede eliminar el medicamento porque tiene lotes asociados.'
        ]);
        exit();
    }
    
    $stmt = $conexion->prepare("DELETE FROM medicamento_principio WHERE id_medicamento = ?");
    $stmt->execute([$id_medicamento]);
    
    $stmt = $conexion->prepare("SELECT id_producto FROM medicamentos WHERE id_medicamento = ?");
    $stmt->execute([$id_medicamento]);
    $id_producto = $stmt->fetch(PDO::FETCH_ASSOC)['id_producto'] ?? null;
    
    $stmt = $conexion->prepare("DELETE FROM medicamentos WHERE id_medicamento = ?");
    $stmt->execute([$id_medicamento]);
    
    if ($id_producto) {
        $stmt = $conexion->prepare("DELETE FROM productos WHERE id_producto = ?");
        $stmt->execute([$id_producto]);
    }
    
    $conexion->commit();
    echo json_encode(['success' => true, 'message' => 'Medicamento eliminado correctamente']);
    
} catch(PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>