<?php
require_once __DIR__ . '/../conexion.php';
session_start();
header('Content-Type: application/json');

$id_incidencia = $_GET['id_incidencia'] ?? null;
if (!$id_incidencia) {
    echo json_encode(['success' => false, 'message' => 'ID requerido']);
    exit();
}

try {
    $stmt = $conexion->prepare("
        SELECT 
            i.*,
            ti.nombre AS tipo_nombre,
            e.numero_seguimiento,
            e.cliente_nombre,
            r.nombre AS repartidor_nombre,
            (SELECT t.numero FROM telefonos t 
             JOIN repartidor_telefono rt ON t.id_telefono = rt.id_telefono 
             WHERE rt.id_repartidor = r.id_repartidor AND t.activo = TRUE LIMIT 1) AS repartidor_telefono,
            u.nombre AS reportado_por_nombre,
            u2.nombre AS resuelto_por_nombre
        FROM incidencias_entrega i
        LEFT JOIN tipo_incidencia_delivery ti ON i.id_tipo_incidencia = ti.id_tipo_incidencia
        LEFT JOIN entregas e ON i.id_entrega = e.id_entrega
        LEFT JOIN repartidores r ON e.id_repartidor = r.id_repartidor
        LEFT JOIN usuarios u ON i.reportado_por = u.id_usuario
        LEFT JOIN usuarios u2 ON i.resuelto_por = u2.id_usuario
        WHERE i.id_incidencia = :id
    ");
    $stmt->execute([':id' => $id_incidencia]);
    $incidencia = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$incidencia) {
        echo json_encode(['success' => false, 'message' => 'Incidencia no encontrada']);
        exit();
    }
    
    $incidencia['fecha_incidencia_formateada'] = date('d/m/Y H:i', strtotime($incidencia['fecha_incidencia']));
    if ($incidencia['fecha_resolucion']) {
        $incidencia['fecha_resolucion_formateada'] = date('d/m/Y H:i', strtotime($incidencia['fecha_resolucion']));
        $incidencia['fecha_resolucion_raw'] = $incidencia['fecha_resolucion'];
    }
    
    echo json_encode(['success' => true] + $incidencia);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>