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
            e.estado_recepcion, e.comentario_cliente,
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

        // Devoluciones que el repartidor registró en estas entregas y que
        // el cliente todavía no ha confirmado (ni Sí ni No) — son las que
        // hay que mostrarle para que responda antes de que le toque al
        // cajero decidir algo.
        $stmtDev = $conexion->query("
            SELECT d.id_devolucion, d.id_entrega, d.motivo, d.fecha_solicitud
            FROM devoluciones d
            WHERE d.id_entrega IN ($ids) AND d.confirmado_por_cliente IS NULL
            ORDER BY d.fecha_solicitud DESC
        ");
        $devolucionesFilas = $stmtDev->fetchAll(PDO::FETCH_ASSOC);
        $devolucionesPorEntrega = [];
        if ($devolucionesFilas) {
            $idsDev = implode(',', array_map(fn($d) => (int)$d['id_devolucion'], $devolucionesFilas));
            $stmtDetDev = $conexion->query("
                SELECT dd.id_devolucion, COALESCE(m.nombre_completo, m.nombre) AS producto_nombre, dd.cantidad
                FROM detalle_devolucion dd
                LEFT JOIN lotes l ON l.id_lote = dd.id_lote
                LEFT JOIN medicamentos m ON m.id_medicamento = l.id_medicamento
                WHERE dd.id_devolucion IN ($idsDev)
            ");
            $productosPorDevolucion = [];
            foreach ($stmtDetDev->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $productosPorDevolucion[$p['id_devolucion']][] = ['nombre' => $p['producto_nombre'], 'cantidad' => $p['cantidad']];
            }
            foreach ($devolucionesFilas as $d) {
                $d['productos'] = $productosPorDevolucion[$d['id_devolucion']] ?? [];
                $devolucionesPorEntrega[$d['id_entrega']][] = $d;
            }
        }

        // Entregas que el cliente ya calificó (paso 2, aparte de confirmar
        // recepción) — para no volver a invitarlo a calificar de nuevo.
        // persona_correcta IS NOT NULL distingue una calificación real de
        // la fila "molde" que se crea sola (con token, sin respuestas)
        // apenas el repartidor marca la entrega como ENTREGADA.
        $stmtCalif = $conexion->query("
            SELECT DISTINCT id_entrega FROM calificaciones_entrega
            WHERE id_entrega IN ($ids) AND persona_correcta IS NOT NULL
        ");
        $yaCalificado = array_flip(array_map(fn($r) => $r['id_entrega'], $stmtCalif->fetchAll(PDO::FETCH_ASSOC)));

        foreach ($entregas as &$e) {
            $e['productos'] = $productosPorEntrega[$e['id_entrega']] ?? [];
            // Paso 1: ¿el cliente ya respondió (sí o no) que le llegó el
            // pedido? Si no, hay que preguntarle.
            $e['pendiente_confirmar_cliente'] = ($e['estado_nombre'] === 'ENTREGADA' && !$e['confirmado_por_cliente']);
            // Paso 2 (aparte, no bloqueante): ya confirmó que todo estuvo
            // bien y no está en disputa, pero todavía no calificó.
            $e['pendiente_calificar'] = (
                $e['estado_nombre'] === 'ENTREGADA'
                && filter_var($e['confirmado_por_cliente'], FILTER_VALIDATE_BOOLEAN)
                && $e['estado_recepcion'] !== 'EN_DISPUTA'
                && !isset($yaCalificado[$e['id_entrega']])
            );
            // Devoluciones de esta entrega pendientes de que el cliente responda.
            $e['devoluciones_pendientes'] = $devolucionesPorEntrega[$e['id_entrega']] ?? [];
            // Se puede cancelar mientras no esté en camino ni entregada
            $e['puede_cancelar'] = in_array($e['estado_nombre'], ['PENDIENTE', 'ASIGNADA']);
        }
        unset($e);
    }

    echo json_encode(['success' => true, 'entregas' => $entregas]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
