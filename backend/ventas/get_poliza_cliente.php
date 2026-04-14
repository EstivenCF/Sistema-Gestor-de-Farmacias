<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

$id_cliente = $_GET['id_cliente'] ?? null;

if (!$id_cliente) {
    echo json_encode(['success' => false, 'message' => 'ID de cliente requerido']);
    exit();
}

try {
    $stmt = $conexion->prepare("
        SELECT 
            p.id_poliza,
            p.id_aseguradora,
            p.numero_poliza,
            p.numero_carnet,
            p.cobertura_porcentaje,
            a.nombre as aseguradora_nombre,
            a.porcentaje_cobertura_default
        FROM polizas p
        JOIN aseguradoras a ON p.id_aseguradora = a.id_aseguradora
        WHERE p.id_cliente = ? 
            AND p.activo = TRUE
            AND CURRENT_DATE BETWEEN p.fecha_inicio AND p.fecha_fin
        LIMIT 1
    ");
    $stmt->execute([$id_cliente]);
    $poliza = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($poliza) {
        // Si la póliza no tiene porcentaje específico, usar el de la aseguradora
        if (!$poliza['cobertura_porcentaje']) {
            $poliza['cobertura_porcentaje'] = $poliza['porcentaje_cobertura_default'];
        }
        echo json_encode(['success' => true, 'poliza' => $poliza]);
    } else {
        echo json_encode(['success' => true, 'poliza' => null]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>