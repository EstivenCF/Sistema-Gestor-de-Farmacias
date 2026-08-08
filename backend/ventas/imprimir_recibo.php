<?php
require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$id_venta = $_GET['id_venta'] ?? null;
if (!$id_venta) {
    die('ID de venta no especificado');
}

try {
    // Obtener datos de la venta (sin c.telefono)
    $stmt = $conexion->prepare("
        SELECT 
            v.numero_documento, v.fecha, v.subtotal, v.itbis_total, v.total,
            v.usa_seguro, v.monto_cubre_seguro,
            COALESCE(c.nombre, 'Consumidor Final') as cliente_nombre,
            c.direccion,
            u.nombre as vendedor,
            cp.nombre as condicion_pago
        FROM ventas v
        LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
        JOIN usuarios u ON v.id_usuario = u.id_usuario
        JOIN condicion_pago cp ON v.id_condicion = cp.id_condicion
        WHERE v.id_venta = :id_venta
    ");
    $stmt->execute([':id_venta' => $id_venta]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$venta) {
        die('Venta no encontrada');
    }
    
    // Obtener productos
    $stmt = $conexion->prepare("
        SELECT 
            dv.cantidad, dv.precio_unitario, dv.subtotal,
            COALESCE(p.nombre, m.nombre_completo) as producto_nombre
        FROM detalle_venta dv
        LEFT JOIN productos p ON dv.id_producto = p.id_producto
        LEFT JOIN medicamentos m ON dv.id_lote = m.id_medicamento
        WHERE dv.id_venta = :id_venta
    ");
    $stmt->execute([':id_venta' => $id_venta]);
    $productos = $stmt->fetchAll();
    
    $total_original = $venta['subtotal'] + $venta['itbis_total'];
    $descuento_seguro = $venta['usa_seguro'] ? $venta['monto_cubre_seguro'] : 0;
    $total_con_descuento = $venta['total'];
    
} catch(PDOException $e) {
    die('Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Recibo - <?php echo $venta['numero_documento']; ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .recibo { max-width: 300px; margin: auto; border: 1px solid #ccc; padding: 15px; border-radius: 10px; }
        .header { text-align: center; border-bottom: 1px dashed #ccc; margin-bottom: 15px; }
        .header h2 { margin: 0; font-size: 18px; }
        .linea { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .total { font-weight: bold; border-top: 1px solid #000; margin-top: 10px; padding-top: 10px; }
        .seguro { color: #007bff; }
        .footer { text-align: center; margin-top: 20px; font-size: 10px; border-top: 1px dashed #ccc; padding-top: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border-bottom: 1px dotted #ccc; padding: 5px 0; text-align: left; }
        th { font-weight: bold; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
<div class="recibo">
    <div class="header">
        <h2>FARMACIA SALUD+</h2>
        <p>Av Central #1, Santiago<br>Tel: 809-555-0001</p>
        <p><strong>RECIBO DE VENTA</strong><br><?php echo $venta['numero_documento']; ?></p>
    </div>
    
    <div class="linea"><span>Fecha:</span><span><?php echo date('d/m/Y H:i', strtotime($venta['fecha'])); ?></span></div>
    <div class="linea"><span>Cliente:</span><span><?php echo htmlspecialchars($venta['cliente_nombre']); ?></span></div>
    <div class="linea"><span>Vendedor:</span><span><?php echo htmlspecialchars($venta['vendedor']); ?></span></div>
    <div class="linea"><span>Condición:</span><span><?php echo htmlspecialchars($venta['condicion_pago']); ?></span></div>
    
    <table>
        <thead>
            <tr><th>Producto</th><th>Cant</th><th>Precio</th><th>Subtotal</th></tr>
        </thead>
        <tbody>
            <?php foreach ($productos as $p): ?>
            <tr>
                <td><?php echo htmlspecialchars($p['producto_nombre']); ?></td>
                <td class="text-right"><?php echo $p['cantidad']; ?></td>
                <td class="text-right">RD$ <?php echo number_format($p['precio_unitario'], 2); ?></td>
                <td class="text-right">RD$ <?php echo number_format($p['subtotal'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="linea"><span>Subtotal:</span><span>RD$ <?php echo number_format($venta['subtotal'], 2); ?></span></div>
    <div class="linea"><span>ITBIS (18%):</span><span>RD$ <?php echo number_format($venta['itbis_total'], 2); ?></span></div>
    
    <?php if ($descuento_seguro > 0): ?>
    <div class="linea seguro"><span>SEGURO MÉDICO (descuento):</span><span>- RD$ <?php echo number_format($descuento_seguro, 2); ?></span></div>
    <?php endif; ?>
    
    <div class="linea total"><span>TOTAL A PAGAR:</span><span>RD$ <?php echo number_format($total_con_descuento, 2); ?></span></div>
    
    <div class="footer">
        <p>¡Gracias por su compra!<br>Este recibo no es factura fiscal.</p>
    </div>
</div>
<script>window.print();</script>
</body>
</html>