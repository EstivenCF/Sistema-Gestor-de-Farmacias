<?php
require_once __DIR__ . '/../conexion.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$id_sucursal = isset($_GET['id_sucursal']) ? intval($_GET['id_sucursal']) : 0;

if (!$id_sucursal) {
    echo json_encode(['success' => false, 'message' => 'Sucursal requerida']);
    exit();
}

try {
    $productos = [];
    
    // 1. MEDICAMENTOS (con lotes) - PostgreSQL usa CURRENT_DATE
    $queryMedicamentos = "
        SELECT 
            'MEDICAMENTO' as tipo,
            m.id_medicamento,
            COALESCE(m.nombre_completo, m.nombre) as nombre,
            l.id_lote,
            l.numero_lote,
            DATE(l.fecha_vencimiento) as fecha_vencimiento,
            COALESCE(i.cantidad, 0) as stock,
            COALESCE(p.precio, 0) as precio,
            COALESCE(m.exento_itbis, false) as exento_itbis,
            NULL as talla,
            NULL as color,
            NULL as id_talla,
            NULL as id_color
        FROM inventario i
        INNER JOIN lotes l ON i.id_lote = l.id_lote
        INNER JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
        INNER JOIN productos p ON m.id_producto = p.id_producto
        WHERE i.id_sucursal = :sucursal
          AND l.estado = 'ACTIVO'
          AND l.fecha_vencimiento >= CURRENT_DATE
          AND i.cantidad > 0
        ORDER BY m.nombre_completo
    ";
    
    // 2. ROPA (productos sin lote)
    $queryRopa = "
        SELECT 
            'ROPA' as tipo,
            p.id_producto as id_medicamento,
            p.nombre,
            NULL as id_lote,
            NULL as numero_lote,
            NULL as fecha_vencimiento,
            COALESCE(ip.cantidad, 0) as stock,
            COALESCE(p.precio, 0) as precio,
            COALESCE(p.exento_itbis, false) as exento_itbis,
            t.nombre as talla,
            c.nombre as color,
            ip.id_talla,
            ip.id_color
        FROM inventario_productos ip
        INNER JOIN productos p ON ip.id_producto = p.id_producto
        LEFT JOIN tallas t ON ip.id_talla = t.id_talla
        LEFT JOIN colores c ON ip.id_color = c.id_color
        WHERE ip.id_sucursal = :sucursal
          AND p.tipo_producto = 'ROPA'
          AND p.estado = true
          AND ip.cantidad > 0
        ORDER BY p.nombre
    ";
    
    $stmtMed = $conexion->prepare($queryMedicamentos);
    $stmtMed->execute([':sucursal' => $id_sucursal]);
    $medicamentos = $stmtMed->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtRopa = $conexion->prepare($queryRopa);
    $stmtRopa->execute([':sucursal' => $id_sucursal]);
    $ropa = $stmtRopa->fetchAll(PDO::FETCH_ASSOC);
    
    // Combinar resultados
    $productos = array_merge($medicamentos, $ropa);
    
    // Convertir tipos correctamente
    foreach ($productos as &$p) {
        $p['id_medicamento'] = intval($p['id_medicamento']);
        $p['stock'] = intval($p['stock']);
        $p['precio'] = floatval($p['precio']);
        $p['exento_itbis'] = $p['exento_itbis'] === 't' || $p['exento_itbis'] === true || $p['exento_itbis'] === '1';
        $p['id_lote'] = $p['id_lote'] ? intval($p['id_lote']) : null;
        $p['id_talla'] = $p['id_talla'] ? intval($p['id_talla']) : null;
        $p['id_color'] = $p['id_color'] ? intval($p['id_color']) : null;
    }
    
    echo json_encode([
        'success' => true,
        'productos' => $productos,
        'total_medicamentos' => count($medicamentos),
        'total_ropa' => count($ropa),
        'sucursal_id' => $id_sucursal
    ]);
    
} catch(PDOException $e) {
    error_log("Error SQL: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}
?>