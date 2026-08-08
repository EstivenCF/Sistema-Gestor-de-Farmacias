<?php
// backend/portal_cliente/listar_mis_entregas.php
// NUEVO — lista las entregas del cliente que inició sesión en el
// portal, con todo el detalle: hora acordada, quién entrega, quién
// despachó, y qué productos incluye. Marca cuáles ya están listas
// para que el cliente las confirme y califique (el repartidor las
// marcó ENTREGADA, pero el cliente todavía no las confirmó).

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_cliente_portal'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']); exit();
}
$id_cliente = $_SESSION['id_cliente_portal'];

try {
    $stmt = $conexion->prepare("
        SELECT
            e.id_entrega, e.numero_seguimiento, e.direccion_entrega, e.barrio_entrega,
            e.fecha_pedido, e.fecha_programada, e.fecha_asignada, e.fecha_entrega_real,
            e.costo_entrega, e.confirmado_por_cliente,
            se.nombre AS estado_nombre,
            r.nombre AS repartidor_nombre,
            u.nombre AS despachado_por,
            v.numero_documento
        FROM entregas e
        JOIN estado_entrega se ON se.id_estado = e.id_estado
        JOIN ventas v ON v.id_venta = e.id_venta
        LEFT JOIN repartidores r ON r.id_repartidor = e.id_repartidor
        LEFT JOIN usuarios u ON u.id_usuario = e.creado_por
        WHERE e.id_cliente = :id_cliente
          AND se.nombre NOT IN ('CANCELADA')
        ORDER BY e.fecha_pedido DESC
        LIMIT 30
    ");
    $stmt->execute([':id_cliente' => $id_cliente]);
    $entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($entregas) {
        $ids = implode(',', array_map(fn($e) => (int)$e['id_entrega'], $entregas));

        // Productos de cada entrega (vía la venta asociada)
        $stmtProd = $conexion->query("
            SELECT e.id_entrega,
                   COALESCE(m.nombre_completo, m.nombre, p.nombre) AS producto_nombre,
                   dv.cantidad
            FROM entregas e
            JOIN detalle_venta dv ON dv.id_venta = e.id_venta
            LEFT JOIN lotes l ON l.id_lote = dv.id_lote
            LEFT JOIN medicamentos m ON m.id_medicamento = l.id_medicamento
            LEFT JOIN productos p ON p.id_producto = dv.id_producto
            WHERE e.id_entrega IN ($ids)
        ");
        $productosPorEntrega = [];
        foreach ($stmtProd->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $productosPorEntrega[$p['id_entrega']][] = ['nombre' => $p['producto_nombre'], 'cantidad' => $p['cantidad']];
        }

        foreach ($entregas as &$e) {
            $e['productos'] = $productosPorEntrega[$e['id_entrega']] ?? [];
            // Lista para que el cliente confirme/califique: ya la entregó
            // el repartidor, pero el cliente mismo no la ha confirmado.
            $e['pendiente_confirmar_cliente'] = ($e['estado_nombre'] === 'ENTREGADA' && !$e['confirmado_por_cliente']);
            // Se puede cancelar mientras no esté en camino ni entregada
            $e['puede_cancelar'] = in_array($e['estado_nombre'], ['PENDIENTE', 'ASIGNADA']);
        }
        unset($e);
    }

    echo json_encode(['success' => true, 'entregas' => $entregas]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
