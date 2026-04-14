<?php
require_once __DIR__ . '/../conexion.php';
session_start();
header('Content-Type: application/json');

$id_entrega = $_GET['id_entrega'] ?? null;
if (!$id_entrega) {
    echo json_encode(['success' => false, 'message' => 'ID requerido']);
    exit();
}

try {
    $stmt = $conexion->prepare("
        SELECT e.*, r.nombre AS repartidor_nombre,
               (SELECT t.numero FROM telefonos t
                JOIN repartidor_telefono rt ON t.id_telefono = rt.id_telefono
                WHERE rt.id_repartidor = e.id_repartidor AND t.activo = TRUE LIMIT 1) AS repartidor_telefono
        FROM entregas e
        LEFT JOIN repartidores r ON e.id_repartidor = r.id_repartidor
        WHERE e.id_entrega = :id
    ");
    $stmt->execute([':id' => $id_entrega]);
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$entrega) {
        echo json_encode(['success' => false, 'message' => 'Entrega no encontrada']);
        exit();
    }
    
    // Formatear fechas para mostrar
    $entrega['fecha_asignacion'] = date('d/m/Y H:i', strtotime($entrega['fecha_asignacion']));
    $entrega['fecha_asignacion_raw'] = $entrega['fecha_asignacion']; // guardar original
    if ($entrega['fecha_entrega_real']) {
        $entrega['fecha_entrega_real'] = date('d/m/Y H:i', strtotime($entrega['fecha_entrega_real']));
        $entrega['fecha_entrega_real_raw'] = $entrega['fecha_entrega_real'];
    }
    $estados = ['pendiente'=>'Pendiente', 'en_camino'=>'En camino', 'entregado'=>'Entregado', 'cancelado'=>'Cancelado'];
    $entrega['estado_texto'] = $estados[$entrega['estado']] ?? $entrega['estado'];
    
    echo json_encode(['success' => true] + $entrega);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>