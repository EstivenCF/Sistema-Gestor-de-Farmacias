<?php
require_once __DIR__ . '/../conexion.php';
session_start();
header('Content-Type: application/json');

$id_vehiculo = $_GET['id_vehiculo'] ?? null;
if (!$id_vehiculo) {
    echo json_encode(['success' => false, 'message' => 'ID requerido']);
    exit();
}

try {
    $stmt = $conexion->prepare("
        SELECT v.*, r.nombre AS repartidor_nombre,
               (SELECT t.numero FROM telefonos t
                JOIN repartidor_telefono rt ON t.id_telefono = rt.id_telefono
                WHERE rt.id_repartidor = v.id_repartidor AND t.activo = TRUE LIMIT 1) AS repartidor_telefono
        FROM vehiculos v
        LEFT JOIN repartidores r ON v.id_repartidor = r.id_repartidor
        WHERE v.id_vehiculo = :id
    ");
    $stmt->execute([':id' => $id_vehiculo]);
    $vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$vehiculo) {
        echo json_encode(['success' => false, 'message' => 'Vehículo no encontrado']);
        exit();
    }
    
    // Formatear fecha
    if ($vehiculo['fecha_vencimiento_seguro']) {
        $vehiculo['fecha_vencimiento_seguro'] = date('d/m/Y', strtotime($vehiculo['fecha_vencimiento_seguro']));
        $vehiculo['fecha_vencimiento_seguro_raw'] = $vehiculo['fecha_vencimiento_seguro'];
    }
    
    echo json_encode(['success' => true] + $vehiculo);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>