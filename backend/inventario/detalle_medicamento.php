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

$id_medicamento = $_GET['id'] ?? 0;

if (!$id_medicamento) {
    echo json_encode(['success' => false, 'message' => 'ID de medicamento no especificado']);
    exit();
}

try {
    $stmt = $conexion->prepare("
        SELECT 
            m.id_medicamento,
            m.nombre,
            m.concentracion,
            m.descripcion,
            m.id_categoria,
            COALESCE(c.nombre, '') as categoria_nombre,
            m.requiere_receta,
            m.stock_minimo,
            m.stock_maximo,
            m.punto_reorden,
            m.proveedor_preferido,
            COALESCE(pv.nombre, '') as proveedor_nombre,
            m.id_unidad,
            COALESCE(u.nombre, '') as unidad_nombre,
            COALESCE(u.abreviatura, '') as unidad_abreviatura,
            m.id_laboratorio,
            COALESCE(l.nombre, '') as laboratorio_nombre,
            m.exento_itbis,
            m.fecha_registro,
            m.id_presentacion,
            COALESCE(p.nombre, '') as presentacion_nombre,
            prod.id_producto,
            COALESCE(prod.precio, 0) as precio
        FROM medicamentos m
        LEFT JOIN categorias c ON m.id_categoria = c.id_categoria
        LEFT JOIN proveedores pv ON m.proveedor_preferido = pv.id_proveedor
        LEFT JOIN unidades_medida u ON m.id_unidad = u.id_unidad
        LEFT JOIN laboratorios l ON m.id_laboratorio = l.id_laboratorio
        LEFT JOIN presentaciones p ON m.id_presentacion = p.id_presentacion
        LEFT JOIN productos prod ON m.id_producto = prod.id_producto
        WHERE m.id_medicamento = ?
    ");
    
    $stmt->execute([$id_medicamento]);
    $medicamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($medicamento) {
        $medicamento['requiere_receta'] = (bool)$medicamento['requiere_receta'];
        $medicamento['exento_itbis'] = (bool)$medicamento['exento_itbis'];
        
        echo json_encode([
            'success' => true,
            'medicamento' => $medicamento
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Medicamento no encontrado']);
    }
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>