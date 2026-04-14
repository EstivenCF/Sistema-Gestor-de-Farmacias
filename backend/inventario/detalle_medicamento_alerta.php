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

$id_medicamento = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_medicamento) {
    echo json_encode(['success' => false, 'message' => 'ID de medicamento inválido']);
    exit();
}

try {
    $sql = "SELECT m.*, 
                   u.nombre as unidad_nombre, u.abreviatura as unidad_abrev,
                   p.nombre as presentacion,
                   c.nombre as categoria,
                   l.nombre as laboratorio,
                   prod.precio
            FROM medicamentos m
            LEFT JOIN unidades_medida u ON m.id_unidad = u.id_unidad
            LEFT JOIN presentaciones p ON m.id_presentacion = p.id_presentacion
            LEFT JOIN categorias c ON m.id_categoria = c.id_categoria
            LEFT JOIN laboratorios l ON m.id_laboratorio = l.id_laboratorio
            LEFT JOIN productos prod ON m.id_producto = prod.id_producto
            WHERE m.id_medicamento = :id";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id' => $id_medicamento]);
    $medicamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($medicamento) {
        // Obtener stock total por sucursal
        $stmt = $conexion->prepare("
            SELECT s.nombre as sucursal, COALESCE(SUM(i.cantidad), 0) as stock
            FROM inventario i
            JOIN lotes l ON i.id_lote = l.id_lote
            JOIN sucursales s ON i.id_sucursal = s.id_sucursal
            WHERE l.id_medicamento = :id_medicamento
            GROUP BY s.id_sucursal, s.nombre
            ORDER BY s.nombre
        ");
        $stmt->execute([':id_medicamento' => $id_medicamento]);
        $medicamento['stock_por_sucursal'] = $stmt->fetchAll();
        
        // Stock total
        $medicamento['stock_total'] = array_sum(array_column($medicamento['stock_por_sucursal'], 'stock'));
        
        echo json_encode(['success' => true, 'medicamento' => $medicamento]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Medicamento no encontrado']);
    }
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>