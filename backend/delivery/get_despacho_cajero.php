<?php
// backend/delivery/get_despacho_cajero.php
require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$id_entrega = intval($_GET['id_entrega'] ?? 0);

try {
    if ($id_entrega) {
        $stmt = $conexion->prepare("
            SELECT
                e.id_entrega, e.numero_seguimiento, e.direccion_entrega,
                e.barrio_entrega, e.ciudad_entrega, e.id_venta,
                e.nombre_receptor_autorizado, e.cedula_receptor_autorizado,
                c.nombre AS cliente_nombre,
                r.nombre AS repartidor_nombre,
                v.tipo   AS vehiculo_tipo,
                v.placa  AS vehiculo_placa,
                se.nombre AS estado_nombre
            FROM entregas e
            JOIN clientes c         ON c.id_cliente    = e.id_cliente
            JOIN repartidores r     ON r.id_repartidor = e.id_repartidor
            LEFT JOIN vehiculos v   ON v.id_repartidor = r.id_repartidor AND v.activo = true
            JOIN estado_entrega se  ON se.id_estado    = e.id_estado
            WHERE e.id_entrega = :id
        ");
        $stmt->execute([':id' => $id_entrega]);
        $entrega = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entrega) {
            echo json_encode(['success' => false, 'message' => 'Entrega no encontrada']); exit();
        }

        $stmt = $conexion->prepare("
            SELECT
                dv.id_detalle_venta, dv.cantidad, dv.precio_unitario,
                l.id_lote, l.numero_lote,
                p.id_producto, p.nombre AS producto_nombre
            FROM detalle_venta dv
            JOIN lotes l    ON l.id_lote      = dv.id_lote
            JOIN productos p ON p.id_producto = l.id_producto
            WHERE dv.id_venta = :id_venta
            ORDER BY dv.id_detalle_venta
        ");
        $stmt->execute([':id_venta' => $entrega['id_venta']]);
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conexion->prepare("SELECT * FROM despacho_entrega WHERE id_entrega = :id");
        $stmt->execute([':id' => $id_entrega]);
        $despacho_existente = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success'            => true,
            'entrega'            => $entrega,
            'productos'          => $productos,
            'despacho_existente' => $despacho_existente,
        ]);
    } else {
        $stmt = $conexion->query("
            SELECT
                e.id_entrega, e.numero_seguimiento, e.fecha_pedido,
                c.nombre AS cliente_nombre,
                r.nombre AS repartidor_nombre,
                se.nombre AS estado_nombre
            FROM entregas e
            JOIN clientes c        ON c.id_cliente    = e.id_cliente
            JOIN repartidores r    ON r.id_repartidor = e.id_repartidor
            JOIN estado_entrega se ON se.id_estado    = e.id_estado
            LEFT JOIN despacho_entrega de ON de.id_entrega = e.id_entrega
            WHERE se.nombre IN ('CREADO', 'ASIGNADO')
              AND de.id_despacho IS NULL
            ORDER BY e.fecha_pedido ASC
        ");
        $entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'entregas' => $entregas]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
