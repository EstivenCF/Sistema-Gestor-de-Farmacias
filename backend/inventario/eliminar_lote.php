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
$id_lote = isset($data['id_lote']) ? (int)$data['id_lote'] : 0;

if (!$id_lote) {
    echo json_encode(['success' => false, 'message' => 'ID de lote inválido']);
    exit();
}

try {
    // Verificar si tiene inventario asociado
    $stmt = $conexion->prepare("SELECT SUM(cantidad) as total FROM inventario WHERE id_lote = :id");
    $stmt->execute([':id' => $id_lote]);
    $stock = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    if ($stock > 0) {
        echo json_encode(['success' => false, 'message' => "No se puede eliminar el lote porque tiene $stock unidades en inventario"]);
        exit();
    }
    
    // Verificar si tiene movimientos asociados
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM movimiento_inventario WHERE id_lote = :id");
    $stmt->execute([':id' => $id_lote]);
    $movimientos = $stmt->fetchColumn();
    
    if ($movimientos > 0) {
        echo json_encode(['success' => false, 'message' => "No se puede eliminar el lote porque tiene $movimientos movimiento(s) de inventario asociados"]);
        exit();
    }
    
    $stmt = $conexion->prepare("DELETE FROM lotes WHERE id_lote = :id");
    $stmt->execute([':id' => $id_lote]);
    
    echo json_encode(['success' => true, 'message' => 'Lote eliminado correctamente']);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>