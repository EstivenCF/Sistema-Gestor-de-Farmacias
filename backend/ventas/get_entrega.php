<?php
require_once __DIR__ . '/../conexion.php';
header('Content-Type: application/json');

if (!isset($_GET['id_entrega'])) {
    echo json_encode(['success' => false, 'message' => 'ID de entrega no proporcionado']);
    exit;
}

$id_entrega = intval($_GET['id_entrega']);

try {
    $stmt = $conexion->prepare("
        SELECT 
            id_entrega, 
            id_venta, 
            numero_seguimiento, 
            id_estado, 
            id_repartidor, 
            costo_entrega, 
            direccion_entrega, 
            observaciones 
        FROM entregas 
        WHERE id_entrega = :id
    ");
    $stmt->execute([':id' => $id_entrega]);
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($entrega) {
        // Asegurar que los valores sean devueltos como números y no strings
        $entrega['costo_entrega'] = floatval($entrega['costo_entrega']);
        $entrega['id_repartidor'] = $entrega['id_repartidor'] ? intval($entrega['id_repartidor']) : null;
        
        echo json_encode(['success' => true] + $entrega);
    } else {
        echo json_encode(['success' => false, 'message' => 'Entrega no encontrada']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en BD: ' . $e->getMessage()]);
}
?>