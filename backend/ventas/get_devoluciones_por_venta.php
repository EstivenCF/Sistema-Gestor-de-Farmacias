<?php
require_once __DIR__ . '/../conexion.php';
header('Content-Type: application/json');

if (!isset($_GET['id_venta'])) {
    echo json_encode(['success' => false, 'message' => 'ID de venta no proporcionado']);
    exit;
}

$id_venta = intval($_GET['id_venta']);

try {
    // Se incluyen los estados: 1 (SOLICITADA), 2 (APROBADA), 4 (COMPLETADA)
    // De esta forma, mientras haya una solicitud pendiente, esa cantidad no estará disponible para nuevas devoluciones.
    $stmt = $conexion->prepare("
        SELECT 
            dv.id_detalle,
            SUM(dd.cantidad) as cantidad_devuelta
        FROM detalle_devolucion dd
        JOIN devoluciones d ON dd.id_devolucion = d.id_devolucion
        JOIN detalle_venta dv ON dv.id_lote = dd.id_lote AND dv.id_venta = d.id_venta
        WHERE d.id_venta = ? AND d.id_estado IN (1, 2, 4)
        GROUP BY dv.id_detalle
    ");
    $stmt->execute([$id_venta]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'devueltos' => $resultados]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>