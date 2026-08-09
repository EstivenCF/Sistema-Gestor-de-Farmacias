<?php
// backend/delivery/get_detalle_entrega.php
// REEMPLAZA el archivo existente — reescrito completo.
//
// Este archivo era muy viejo y tenía 3 bugs reales:
//   1. JOIN (no LEFT JOIN) con repartidores — una entrega en cola
//      (todavía sin repartidor) desaparecía por completo aunque
//      existiera, mostrando "Entrega no encontrada".
//   2. dv.id_detalle_venta no existe, la columna real es dv.id_detalle.
//   3. Buscaba productos vía lotes.id_producto, columna que no existe
//      (lotes se relaciona con medicamentos, no con productos).
//   Además consultaba tablas huérfanas (despacho_entrega,
//   agenda_delivery_entrega) que ya no se usan en el flujo actual.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$id_entrega = intval($_GET['id_entrega'] ?? 0);
if (!$id_entrega) {
    echo json_encode(['success' => false, 'message' => 'ID de entrega requerido']); exit();
}

try {
    $stmt = $conexion->prepare("
        SELECT
            e.id_entrega, e.numero_seguimiento, e.direccion_entrega, e.barrio_entrega,
            e.ciudad_entrega, e.referencia_entrega, e.latitud_entrega, e.longitud_entrega,
            e.id_venta, e.costo_entrega, e.distancia_km,
            e.fecha_pedido, e.fecha_programada, e.fecha_asignada, e.fecha_entrega_real,
            e.nombre_quien_recibe, e.identificacion_quien_recibe,
            e.observaciones, e.comentario_cliente, e.calificacion,
            e.cancelado_por, e.comentario_cancelacion, e.confirmado_por_cliente,
            c.nombre        AS cliente_nombre,
            c.id_cliente,
            r.nombre        AS repartidor_nombre,
            r.telefono_emergencia AS repartidor_telefono,
            v.tipo          AS vehiculo_tipo,
            v.placa         AS vehiculo_placa,
            se.nombre       AS estado_nombre,
            mf.nombre       AS motivo_fallida_nombre,
            mc.nombre       AS motivo_cancelacion_nombre,
            u.nombre        AS despachado_por,
            (SELECT t.numero FROM cliente_telefono ct
             JOIN telefonos t ON t.id_telefono = ct.id_telefono
             WHERE ct.id_cliente = c.id_cliente AND t.activo = TRUE
             ORDER BY t.id_telefono LIMIT 1) AS cliente_telefono
        FROM entregas e
        JOIN clientes c              ON c.id_cliente   = e.id_cliente
        JOIN estado_entrega se       ON se.id_estado   = e.id_estado
        LEFT JOIN repartidores r     ON r.id_repartidor = e.id_repartidor
        LEFT JOIN vehiculos v        ON v.id_vehiculo   = e.id_vehiculo
        LEFT JOIN motivo_fallida mf  ON mf.id_motivo    = e.id_motivo_fallida
        LEFT JOIN motivo_cancelacion_cliente mc ON mc.id_motivo = e.id_motivo_cancelacion
        LEFT JOIN usuarios u         ON u.id_usuario    = e.creado_por
        WHERE e.id_entrega = :id
    ");
    $stmt->execute([':id' => $id_entrega]);
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entrega) {
        echo json_encode(['success' => false, 'message' => 'Entrega no encontrada']); exit();
    }

    // Un repartidor solo puede ver el detalle de SUS PROPIAS entregas —
    // sin esto, cambiando el id_entrega en la petición podría ver
    // cualquier entrega ajena.
    if (($_SESSION['rol'] ?? '') === 'Repartidor') {
        $stmtProp = $conexion->prepare("
            SELECT 1 FROM entregas e
            JOIN repartidores r ON r.id_repartidor = e.id_repartidor
            WHERE e.id_entrega = :id AND r.id_usuario = :id_usuario
        ");
        $stmtProp->execute([
            ':id' => $id_entrega,
            ':id_usuario' => $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? 0),
        ]);
        if (!$stmtProp->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'Esta entrega no está asignada a ti']); exit();
        }
    }

    // Productos de la venta asociada — vía lotes (medicamentos) o
    // directo por id_producto (ropa/otros), según cómo se vendió cada línea.
    $stmt = $conexion->prepare("
        SELECT
            dv.id_detalle, dv.cantidad, dv.precio_unitario,
            dv.id_lote, dv.id_producto,
            l.numero_lote,
            COALESCE(m.nombre_completo, m.nombre, p.nombre) AS producto_nombre
        FROM detalle_venta dv
        LEFT JOIN lotes l         ON l.id_lote        = dv.id_lote
        LEFT JOIN medicamentos m  ON m.id_medicamento = l.id_medicamento
        LEFT JOIN productos p     ON p.id_producto    = dv.id_producto
        WHERE dv.id_venta = :id_venta
        ORDER BY dv.id_detalle
    ");
    $stmt->execute([':id_venta' => $entrega['id_venta']]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Historial de cambios de estado de esta entrega
    $stmt = $conexion->prepare("
        SELECT h.fecha, h.observacion, se.nombre AS estado_nombre, u.nombre AS realizado_por
        FROM historial_entrega h
        JOIN estado_entrega se ON se.id_estado = h.id_estado
        LEFT JOIN usuarios u ON u.id_usuario = h.id_usuario
        WHERE h.id_entrega = :id
        ORDER BY h.fecha ASC
    ");
    $stmt->execute([':id' => $id_entrega]);
    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'   => true,
        'entrega'   => $entrega,
        'productos' => $productos,
        'historial' => $historial,
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
