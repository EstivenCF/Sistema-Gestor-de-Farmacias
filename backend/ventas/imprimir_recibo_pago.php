<?php
require_once __DIR__ . '/../conexion.php';

$id_pago = $_GET['id_pago'] ?? null;

if (!$id_pago) {
    die('ID de pago no especificado');
}

$stmt = $conexion->prepare("
    SELECT 
        p.*,
        v.numero_documento,
        v.total as total_venta,
        COALESCE(c.nombre, 'Consumidor Final') as cliente_nombre,
        u.nombre as usuario_nombre,
        mp.nombre as metodo_nombre
    FROM pagos p
    JOIN ventas v ON p.id_venta = v.id_venta
    JOIN usuarios u ON v.id_usuario = u.id_usuario
    JOIN metodos_pago mp ON p.id_metodo = mp.id_metodo
    LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
    WHERE p.id_pago = ?
");
$stmt->execute([$id_pago]);
$pago = $stmt->fetch();

if (!$pago) {
    die('Pago no encontrado');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Recibo de Pago <?php echo $pago['numero_documento']; ?></title>
    <style>
        body { font-family: 'Courier New', monospace; margin: 0; padding: 20px; width: 300px; margin: 0 auto; }
        .recibo { border: 1px solid #ddd; padding: 15px; }
        .header { text-align: center; margin-bottom: 15px; }
        .header h2 { margin: 0; font-size: 16px; }
        .linea { border-top: 1px dashed #000; margin: 10px 0; }
        .info { font-size: 12px; margin-bottom: 10px; }
        .total { font-weight: bold; margin-top: 10px; text-align: right; }
        .footer { text-align: center; font-size: 10px; margin-top: 15px; }
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="recibo">
        <div class="header">
            <h2>FARMACIA SALUD+</h2>
            <p>RNC: 101010101<br>Av. Principal #123, Santiago</p>
            <div class="linea"></div>
            <strong>RECIBO DE PAGO</strong>
            <div class="linea"></div>
        </div>
        
        <div class="info">
            <strong>Nº Factura:</strong> <?php echo $pago['numero_documento']; ?><br>
            <strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($pago['fecha_transaccion'])); ?><br>
            <strong>Cliente:</strong> <?php echo $pago['cliente_nombre']; ?><br>
            <strong>Método:</strong> <?php echo $pago['metodo_nombre']; ?><br>
            <?php if ($pago['referencia']): ?>
                <strong>Referencia:</strong> <?php echo $pago['referencia']; ?><br>
            <?php endif; ?>
        </div>
        
        <div class="linea"></div>
        
        <div class="total">
            Total Venta: RD$ <?php echo number_format($pago['total_venta'], 2); ?><br>
            <strong>MONTO PAGADO: RD$ <?php echo number_format($pago['monto'], 2); ?></strong>
        </div>
        
        <?php if ($pago['monto_seguro'] > 0): ?>
            <div class="linea"></div>
            <div class="info">
                <strong>Seguro:</strong> RD$ <?php echo number_format($pago['monto_seguro'], 2); ?><br>
                <strong>Paciente:</strong> RD$ <?php echo number_format($pago['monto_paciente'], 2); ?>
            </div>
        <?php endif; ?>
        
        <div class="linea"></div>
        
        <div class="footer">
            ¡Gracias por su pago!<br>
            Este recibo es un comprobante de pago válido
        </div>
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()">Imprimir</button>
        <button onclick="window.close()">Cerrar</button>
    </div>
    
    <script>window.onload = function() { window.print(); }</script>
</body>
</html>