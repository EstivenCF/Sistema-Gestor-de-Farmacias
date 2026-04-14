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

$id_devolucion = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_devolucion) {
    echo json_encode(['success' => false, 'message' => 'ID de devolución inválido']);
    exit();
}

try {
    // Información principal de la devolución (SIN cliente.telefono ni proveedor.telefono)
    $sql = "SELECT d.*, 
                   td.nombre as tipo_nombre,
                   ed.nombre as estado_nombre,
                   c.nombre as cliente_nombre,
                   p.nombre as proveedor_nombre,
                   s.nombre as sucursal_nombre,
                   u.nombre as usuario_nombre,
                   u2.nombre as aprobador_nombre
            FROM devoluciones d
            LEFT JOIN tipo_devolucion td ON d.id_tipo = td.id_tipo
            LEFT JOIN estado_devolucion ed ON d.id_estado = ed.id_estado
            LEFT JOIN clientes c ON d.id_cliente = c.id_cliente
            LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor
            LEFT JOIN sucursales s ON d.id_sucursal = s.id_sucursal
            LEFT JOIN usuarios u ON d.id_usuario = u.id_usuario
            LEFT JOIN usuarios u2 ON d.aprobado_por = u2.id_usuario
            WHERE d.id_devolucion = :id";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id' => $id_devolucion]);
    $devolucion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($devolucion) {
        // Obtener detalles de productos devueltos
        $sql = "SELECT dd.*, 
                       l.numero_lote,
                       m.nombre as medicamento_nombre,
                       m.concentracion,
                       p.nombre as presentacion,
                       u.abreviatura as unidad_abrev
                FROM detalle_devolucion dd
                JOIN lotes l ON dd.id_lote = l.id_lote
                JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
                LEFT JOIN presentaciones p ON m.id_presentacion = p.id_presentacion
                LEFT JOIN unidades_medida u ON m.id_unidad = u.id_unidad
                WHERE dd.id_devolucion = :id";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([':id' => $id_devolucion]);
        $devolucion['detalles'] = $stmt->fetchAll();
        
        // Obtener reembolsos asociados
        $sql = "SELECT r.*, mp.nombre as metodo_pago_nombre
                FROM reembolsos r
                LEFT JOIN metodos_pago mp ON r.id_metodo_pago = mp.id_metodo
                WHERE r.id_devolucion = :id";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([':id' => $id_devolucion]);
        $devolucion['reembolsos'] = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'devolucion' => $devolucion]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Devolución no encontrada']);
    }
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>