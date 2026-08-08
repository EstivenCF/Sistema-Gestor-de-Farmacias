<?php
require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id_entrega = intval($data['id_entrega'] ?? 0);
$id_estado = intval($data['id_estado'] ?? 0);
$id_repartidor = !empty($data['id_repartidor']) ? intval($data['id_repartidor']) : null;
$costo_entrega = floatval($data['costo_entrega'] ?? 0);
$direccion_entrega = trim($data['direccion_entrega'] ?? '');
$observaciones = trim($data['observaciones'] ?? '');

if (!$id_entrega || !$id_estado || !$direccion_entrega) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    // Obtener el estado actual para saber si se está entregando
    $stmt = $conexion->prepare("SELECT id_estado FROM entregas WHERE id_entrega = :id");
    $stmt->execute([':id' => $id_entrega]);
    $estado_anterior = $stmt->fetchColumn();
    
    $sql = "UPDATE entregas SET 
                id_estado = :id_estado,
                id_repartidor = :id_repartidor,
                costo_entrega = :costo_entrega,
                direccion_entrega = :direccion_entrega,
                observaciones = :observaciones,
                fecha_modificacion = NOW()";
    
    $params = [
        ':id_estado' => $id_estado,
        ':id_repartidor' => $id_repartidor,
        ':costo_entrega' => $costo_entrega,
        ':direccion_entrega' => $direccion_entrega,
        ':observaciones' => $observaciones,
        ':id' => $id_entrega
    ];
    
    // Si el nuevo estado es ENTREGADA (id_estado = 4) y antes no lo era, registrar fecha_real
    if ($id_estado == 4 && $estado_anterior != 4) {
        $sql .= ", fecha_entrega_real = NOW()";
    }
    // Si se cancela (id_estado = 5), se puede registrar también, pero no es obligatorio.
    
    $sql .= " WHERE id_entrega = :id";
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    
    // Registrar en historial_entrega
    $historial = $conexion->prepare("INSERT INTO historial_entrega (id_entrega, id_estado, observacion, id_usuario) VALUES (:id_entrega, :id_estado, :observacion, :id_usuario)");
    $historial->execute([
        ':id_entrega' => $id_entrega,
        ':id_estado' => $id_estado,
        ':observacion' => $observaciones,
        ':id_usuario' => $_SESSION['id_usuario'] ?? 1
    ]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en BD: ' . $e->getMessage()]);
}