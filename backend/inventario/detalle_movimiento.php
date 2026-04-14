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
    echo json_encode(['success' => false, 'message' => 'ID de movimiento inválido']);
    exit();
}

try {
    $sql = "SELECT m.id_movimiento, m.id_lote, m.id_sucursal, m.tipo, m.cantidad, 
                   m.fecha, m.motivo, m.referencia, m.observaciones,
                   l.numero_lote, l.fecha_vencimiento, l.estado as estado_lote,
                   med.id_medicamento, med.nombre as medicamento_nombre, med.concentracion,
                   u.nombre as unidad, u.abreviatura,
                   p.nombre as presentacion,
                   s.nombre as sucursal_nombre, s.direccion as sucursal_direccion,
                   us.nombre as usuario_nombre
            FROM movimiento_inventario m
            LEFT JOIN lotes l ON m.id_lote = l.id_lote
            LEFT JOIN medicamentos med ON l.id_medicamento = med.id_medicamento
            LEFT JOIN unidades_medida u ON med.id_unidad = u.id_unidad
            LEFT JOIN presentaciones p ON med.id_presentacion = p.id_presentacion
            LEFT JOIN sucursales s ON m.id_sucursal = s.id_sucursal
            LEFT JOIN usuarios us ON m.id_usuario = us.id_usuario
            WHERE m.id_movimiento = :id";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id' => $id_movimiento]);
    $movimiento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($movimiento) {
        echo json_encode(['success' => true, 'movimiento' => $movimiento]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Movimiento no encontrado']);
    }
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>