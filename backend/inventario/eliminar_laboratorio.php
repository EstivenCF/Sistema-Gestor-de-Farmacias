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
$id_laboratorio = isset($data['id_laboratorio']) ? (int)$data['id_laboratorio'] : 0;

if (!$id_laboratorio) {
    echo json_encode(['success' => false, 'message' => 'ID de laboratorio inválido']);
    exit();
}

try {
    // Verificar si tiene medicamentos asociados
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM medicamentos WHERE id_laboratorio = :id");
    $stmt->execute([':id' => $id_laboratorio]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => "No se puede eliminar el laboratorio porque tiene $count medicamento(s) asociado(s)"]);
        exit();
    }
    
    $stmt = $conexion->prepare("DELETE FROM laboratorios WHERE id_laboratorio = :id");
    $stmt->execute([':id' => $id_laboratorio]);
    
    echo json_encode(['success' => true, 'message' => 'Laboratorio eliminado correctamente']);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>