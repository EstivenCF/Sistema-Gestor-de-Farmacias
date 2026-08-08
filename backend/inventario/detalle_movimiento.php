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

$id_movimiento = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_movimiento) {
    echo json_encode(['success' => false, 'message' => 'ID de movimiento requerido']);
    exit();
}

try {
    // Intentar obtener de movimiento_inventario (medicamentos)
    $sql_med = "
        SELECT 
            m.id_movimiento,
            m.fecha,
            m.tipo,
            m.id_lote,
            NULL AS id_producto,
            NULL AS id_talla,
            NULL AS id_color,
            l.numero_lote,
            med.nombre_completo AS item_nombre,
            pres.nombre AS presentacion,
            med.concentracion,
            uni.abreviatura AS unidad_abrev,
            NULL AS producto_nombre,
            NULL AS talla_nombre,
            NULL AS color_nombre,
            m.cantidad,
            m.id_sucursal,
            s.nombre AS sucursal_nombre,
            m.motivo,
            m.referencia,
            m.observaciones,
            u.nombre AS usuario_nombre,
            'MEDICAMENTO' AS tipo_item
        FROM movimiento_inventario m
        JOIN lotes l ON m.id_lote = l.id_lote
        JOIN medicamentos med ON l.id_medicamento = med.id_medicamento
        LEFT JOIN presentaciones pres ON med.id_presentacion = pres.id_presentacion
        LEFT JOIN unidades_medida uni ON med.id_unidad = uni.id_unidad
        JOIN sucursales s ON m.id_sucursal = s.id_sucursal
        LEFT JOIN usuarios u ON m.id_usuario = u.id_usuario
        WHERE m.id_movimiento = :id
    ";
    
    $stmt = $conexion->prepare($sql_med);
    $stmt->bindValue(':id', $id_movimiento, PDO::PARAM_INT);
    $stmt->execute();
    $movimiento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si no se encuentra, buscar en movimiento_inventario_productos (ropa)
    if (!$movimiento) {
        $sql_ropa = "
            SELECT 
                mp.id_movimiento,
                mp.fecha,
                mp.tipo,
                NULL AS id_lote,
                mp.id_producto,
                mp.id_talla,
                mp.id_color,
                NULL AS numero_lote,
                NULL AS item_nombre,
                NULL AS presentacion,
                NULL AS concentracion,
                NULL AS unidad_abrev,
                p.nombre AS producto_nombre,
                t.nombre AS talla_nombre,
                c.nombre AS color_nombre,
                mp.cantidad,
                mp.id_sucursal,
                s.nombre AS sucursal_nombre,
                mp.motivo,
                mp.referencia,
                mp.observaciones,
                u.nombre AS usuario_nombre,
                'ROPA' AS tipo_item
            FROM movimiento_inventario_productos mp
            JOIN productos p ON mp.id_producto = p.id_producto
            LEFT JOIN tallas t ON mp.id_talla = t.id_talla
            LEFT JOIN colores c ON mp.id_color = c.id_color
            JOIN sucursales s ON mp.id_sucursal = s.id_sucursal
            LEFT JOIN usuarios u ON mp.id_usuario = u.id_usuario
            WHERE mp.id_movimiento = :id
        ";
        $stmt = $conexion->prepare($sql_ropa);
        $stmt->bindValue(':id', $id_movimiento, PDO::PARAM_INT);
        $stmt->execute();
        $movimiento = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($movimiento) {
        // Formatear campos para la vista
        if ($movimiento['tipo_item'] == 'MEDICAMENTO') {
            $movimiento['nombre_mostrar'] = $movimiento['item_nombre'];
            $movimiento['detalle_mostrar'] = trim($movimiento['presentacion'] . ' ' . $movimiento['concentracion'] . ' ' . $movimiento['unidad_abrev']);
            $movimiento['lote_mostrar'] = $movimiento['numero_lote'];
        } else {
            $movimiento['nombre_mostrar'] = $movimiento['producto_nombre'];
            $movimiento['detalle_mostrar'] = 'Talla: ' . ($movimiento['talla_nombre'] ?? '-') . ' | Color: ' . ($movimiento['color_nombre'] ?? '-');
            $movimiento['lote_mostrar'] = 'N/A';
        }
        
        echo json_encode(['success' => true, 'movimiento' => $movimiento]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Movimiento no encontrado']);
    }
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>