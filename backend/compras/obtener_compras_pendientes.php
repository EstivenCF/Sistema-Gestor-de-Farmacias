<?php
require_once __DIR__ . '/../conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$proveedor = $_GET['proveedor'] ?? '';
$fechaDesde = $_GET['fecha_desde'] ?? '';
$fechaHasta = $_GET['fecha_hasta'] ?? '';

try {
    $sql = "SELECT 
                c.id_compra,
                c.numero_documento,
                c.fecha,
                c.subtotal,
                c.itbis,
                c.total,
                c.fecha_esperada,
                c.observaciones,
                c.id_estado,
                p.id_proveedor,
                p.nombre as proveedor_nombre,
                p.rnc as proveedor_rnc,
                p.direccion as proveedor_direccion
            FROM compras c
            LEFT JOIN proveedores p ON c.id_proveedor = p.id_proveedor
            WHERE c.id_estado IN (1, 3)
            ORDER BY c.fecha DESC";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute();
    $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agregar totales
    foreach ($compras as &$compra) {
        $stmt2 = $conexion->prepare("SELECT COALESCE(SUM(cantidad), 0) as total FROM detalle_compra WHERE id_compra = ?");
        $stmt2->execute([$compra['id_compra']]);
        $pedido = $stmt2->fetch(PDO::FETCH_ASSOC);
        $compra['total_pedido'] = (int)$pedido['total'];
        
        $stmt3 = $conexion->prepare("
            SELECT COALESCE(SUM(rd.cantidad_recibida), 0) as total 
            FROM detalle_compra dc
            LEFT JOIN recepcion_detalle rd ON dc.id_detalle = rd.id_detalle
            WHERE dc.id_compra = ?
        ");
        $stmt3->execute([$compra['id_compra']]);
        $recibido = $stmt3->fetch(PDO::FETCH_ASSOC);
        $compra['total_recibido'] = (int)$recibido['total'];
        
        if ($compra['total_recibido'] >= $compra['total_pedido'] && $compra['total_pedido'] > 0) {
            $compra['estado'] = 'COMPLETADA';
        } elseif ($compra['total_recibido'] > 0) {
            $compra['estado'] = 'PARCIAL';
        } else {
            $compra['estado'] = 'PENDIENTE';
        }
    }
    
    if ($proveedor) {
        $compras = array_values(array_filter($compras, fn($c) => $c['id_proveedor'] == $proveedor));
    }
    
    echo json_encode(['success' => true, 'compras' => $compras]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>