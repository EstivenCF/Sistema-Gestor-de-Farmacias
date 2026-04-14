<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

$termino = $_GET['termino'] ?? '';

if (strlen($termino) < 2) {
    echo json_encode(['success' => false, 'message' => 'Escriba al menos 2 caracteres', 'productos' => []]);
    exit();
}

try {
    $query = "
        SELECT DISTINCT
            l.id_lote,
            l.numero_lote,
            m.id_medicamento,
            m.nombre_completo as nombre,
            p.precio,
            COALESCE(i.cantidad, 0) as stock,
            pres.nombre as presentacion,
            m.concentracion
        FROM lotes l
        JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
        JOIN productos p ON m.id_producto = p.id_producto
        LEFT JOIN inventario i ON l.id_lote = i.id_lote
        LEFT JOIN presentaciones pres ON m.id_presentacion = pres.id_presentacion
        WHERE l.estado = 'ACTIVO'
            AND l.fecha_vencimiento > CURRENT_DATE
            AND (m.nombre_completo ILIKE :termino 
                OR m.nombre ILIKE :termino 
                OR l.numero_lote ILIKE :termino)
        ORDER BY m.nombre_completo ASC
        LIMIT 15
    ";
    
    $stmt = $conexion->prepare($query);
    $stmt->execute([':termino' => "%$termino%"]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Si no hay resultados con nombre_completo, buscar por nombre simple
    if (empty($productos)) {
        $query2 = "
            SELECT DISTINCT
                l.id_lote,
                l.numero_lote,
                m.id_medicamento,
                m.nombre as nombre,
                p.precio,
                COALESCE(i.cantidad, 0) as stock,
                pres.nombre as presentacion,
                m.concentracion
            FROM lotes l
            JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
            JOIN productos p ON m.id_producto = p.id_producto
            LEFT JOIN inventario i ON l.id_lote = i.id_lote
            LEFT JOIN presentaciones pres ON m.id_presentacion = pres.id_presentacion
            WHERE l.estado = 'ACTIVO'
                AND l.fecha_vencimiento > CURRENT_DATE
                AND m.nombre ILIKE :termino
            ORDER BY m.nombre ASC
            LIMIT 15
        ";
        $stmt2 = $conexion->prepare($query2);
        $stmt2->execute([':termino' => "%$termino%"]);
        $productos = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['success' => true, 'productos' => $productos]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'productos' => []]);
}
?>