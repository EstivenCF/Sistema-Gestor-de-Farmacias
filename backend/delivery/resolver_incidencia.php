<?php
require_once __DIR__ . '/../conexion.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['id_incidencia']) || empty($data['resolucion']) || empty($data['resuelto_por'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    $stmt = $conexion->prepare("
        UPDATE incidencias_entrega SET
            resuelto = TRUE,
            resolucion = :resolucion,
            resuelto_por = :resuelto_por,
            fecha_resolucion = NOW()
        WHERE id_incidencia = :id AND resuelto = FALSE
    ");
    $stmt->execute([
        ':id' => $data['id_incidencia'],
        ':resolucion' => $data['resolucion'],
        ':resuelto_por' => $data['resuelto_por']
    ]);
    if ($stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'La incidencia ya estaba resuelta o no existe']);
        exit();
    }
    echo json_encode(['success' => true]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>