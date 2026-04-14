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
$id_presentacion = isset($data['id_presentacion']) ? (int)$data['id_presentacion'] : 0;

if (!$id_presentacion) {
    echo json_encode(['success' => false, 'message' => 'ID de presentación inválido']);
    exit();
}

try {
    // Verificar si tiene medicamentos asociados
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM medicamentos WHERE id_presentacion = :id");
    $stmt->execute([':id' => $id_presentacion]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => "No se puede eliminar la presentación porque tiene $count medicamento(s) asociado(s)"]);
        exit();
    }
    
    $stmt = $conexion->prepare("DELETE FROM presentaciones WHERE id_presentacion = :id");
    $stmt->execute([':id' => $id_presentacion]);
    
    echo json_encode(['success' => true, 'message' => 'Presentación eliminada correctamente']);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>