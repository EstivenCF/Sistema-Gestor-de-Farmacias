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

$id_lote = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_lote) {
    echo json_encode(['success' => false, 'message' => 'ID de lote inválido']);
    exit();
}

try {
    // Información principal del lote
    $sql = "SELECT l.*, 
                   m.nombre as medicamento_nombre,
                   m.concentracion,
                   u.nombre as unidad,
                   u.abreviatura,
                   p.nombre as presentacion,
                   c.nombre as categoria,
                   lab.nombre as laboratorio,
                   u2.nombre as usuario_registro
            FROM lotes l
            LEFT JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
            LEFT JOIN unidades_medida u ON m.id_unidad = u.id_unidad
            LEFT JOIN presentaciones p ON m.id_presentacion = p.id_presentacion
            LEFT JOIN categorias c ON m.id_categoria = c.id_categoria
            LEFT JOIN laboratorios lab ON m.id_laboratorio = lab.id_laboratorio
            LEFT JOIN usuarios u2 ON l.registro_por = u2.id_usuario
            WHERE l.id_lote = :id";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id' => $id_lote]);
    $lote = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lote) {
        // Stock por sucursal
        $sqlStock = "SELECT i.id_sucursal, 
                            s.nombre as sucursal_nombre,
                            i.cantidad,
                            s.direccion,
                            s.telefono
                     FROM inventario i
                     JOIN sucursales s ON i.id_sucursal = s.id_sucursal
                     WHERE i.id_lote = :id
                     ORDER BY s.nombre";
        $stmt = $conexion->prepare($sqlStock);
        $stmt->execute([':id' => $id_lote]);
        $lote['stock_por_sucursal'] = $stmt->fetchAll();
        $lote['stock_total'] = array_sum(array_column($lote['stock_por_sucursal'], 'cantidad'));
        
        // Movimientos recientes
        $sqlMov = "SELECT * FROM movimiento_inventario WHERE id_lote = :id ORDER BY fecha DESC LIMIT 10";
        $stmt = $conexion->prepare($sqlMov);
        $stmt->execute([':id' => $id_lote]);
        $lote['movimientos_recientes'] = $stmt->fetchAll();
        
        // HISTORIAL DE CAMBIOS DE ESTADO
        $sqlHist = "SELECT h.id_historial, 
                           h.estado_anterior, 
                           h.estado_nuevo,
                           h.motivo, 
                           h.observaciones, 
                           h.fecha_cambio,
                           COALESCE(u.nombre, 'Sistema') as usuario_nombre
                    FROM historial_estado_lote h
                    LEFT JOIN usuarios u ON h.id_usuario = u.id_usuario
                    WHERE h.id_lote = :id
                    ORDER BY h.fecha_cambio DESC";
        $stmt = $conexion->prepare($sqlHist);
        $stmt->execute([':id' => $id_lote]);
        $lote['historial_estados'] = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'lote' => $lote]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lote no encontrado']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>