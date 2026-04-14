<?php
require_once __DIR__ . '/../conexion.php';

header('Content-Type: application/json');

$tipo = $_GET['tipo'] ?? 'venta'; // 'venta' o 'compra'

try {
    if ($tipo === 'compra') {
        // Modo compra: listar todos los medicamentos con su stock total (suma de lotes activos)
        $query = "
            SELECT 
                m.id_medicamento,
                m.nombre_completo as nombre,
                m.exento_itbis,
                COALESCE(p.nombre, '') as presentacion,
                COALESCE(c.nombre, '') as categoria,
                COALESCE(SUM(l.cantidad_actual), 0) as stock_total
            FROM medicamentos m
            LEFT JOIN presentaciones p ON m.id_presentacion = p.id_presentacion
            LEFT JOIN categorias c ON m.id_categoria = c.id_categoria
            LEFT JOIN lotes l ON m.id_medicamento = l.id_medicamento AND l.estado = 'ACTIVO'
            WHERE m.id_producto IS NOT NULL
            GROUP BY m.id_medicamento, m.nombre_completo, m.exento_itbis, p.nombre, c.nombre
            ORDER BY m.nombre_completo ASC
        ";
        $stmt = $conexion->prepare($query);
        $stmt->execute();
        $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'productos' => $medicamentos]);
        
    } else {
        // Modo venta: listar lotes con stock disponible (comportamiento original)
        $query = "
            SELECT DISTINCT
                l.id_lote,
                l.numero_lote,
                m.id_medicamento,
                m.nombre,
                p.precio,
                COALESCE(i.cantidad, 0) as stock,
                m.requiere_receta,
                p.exento_itbis
            FROM lotes l
            JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
            JOIN productos p ON m.id_producto = p.id_producto
            LEFT JOIN inventario i ON l.id_lote = i.id_lote
            WHERE l.estado = 'ACTIVO'
                AND l.fecha_vencimiento > CURRENT_DATE
                AND COALESCE(i.cantidad, 0) > 0
            ORDER BY m.nombre ASC
            LIMIT 100
        ";
        $stmt = $conexion->prepare($query);
        $stmt->execute();
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'productos' => $productos]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'productos' => []]);
}
?>