<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

$id_pago = $_GET['id_pago'] ?? null;

if (!$id_pago) {
    echo json_encode(['success' => false, 'message' => 'ID de pago requerido']);
    exit();
}

try {
    $stmt = $conexion->prepare("
        SELECT 
            p.id_pago,
            p.monto,
            p.fecha_transaccion,
            p.referencia,
            p.estado,
            p.monto_seguro,
            p.monto_paciente,
            p.estado_seguro,
            v.id_venta,
            v.numero_documento,
            v.total as total_venta,
            COALESCE(c.nombre, 'Consumidor Final') as cliente_nombre,
            u.nombre as usuario_nombre,
            mp.nombre as metodo_nombre,
            a.nombre as aseguradora_nombre
        FROM pagos p
        JOIN ventas v ON p.id_venta = v.id_venta
        JOIN usuarios u ON v.id_usuario = u.id_usuario
        JOIN metodos_pago mp ON p.id_metodo = mp.id_metodo
        LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
        LEFT JOIN aseguradoras a ON p.id_aseguradora = a.id_aseguradora
        WHERE p.id_pago = ?
    ");
    $stmt->execute([$id_pago]);
    $pago = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pago) {
        echo json_encode(['success' => false, 'message' => 'Pago no encontrado']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'id_pago' => $pago['id_pago'],
        'monto' => $pago['monto'],
        'fecha' => date('d/m/Y H:i', strtotime($pago['fecha_transaccion'])),
        'referencia' => $pago['referencia'],
        'estado' => $pago['estado'],
        'monto_seguro' => $pago['monto_seguro'] ?? 0,
        'monto_paciente' => $pago['monto_paciente'] ?? 0,
        'estado_seguro' => $pago['estado_seguro'],
        'numero_documento' => $pago['numero_documento'],
        'total_venta' => $pago['total_venta'],
        'cliente_nombre' => $pago['cliente_nombre'],
        'usuario_nombre' => $pago['usuario_nombre'],
        'metodo_nombre' => $pago['metodo_nombre'],
        'aseguradora_nombre' => $pago['aseguradora_nombre']
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>