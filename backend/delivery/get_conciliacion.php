<?php
// backend/delivery/get_conciliacion.php
// NUEVO — P-4: trae lo que se despachó vs. lo que hay registrado
// como entregado, para que el cajero compare y valide. Por defecto
// "entregado" se sugiere igual a "despachado" (el caso normal, sin
// problemas) — el cajero puede ajustarlo si hubo una entrega parcial
// o alguna diferencia, antes de validar.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
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
            e.id_entrega, e.numero_seguimiento, e.fecha_entrega_real, e.nombre_quien_recibe,
            se.nombre AS estado_nombre,
            r.nombre AS repartidor_nombre,
            v.tipo AS vehiculo_tipo, v.placa AS vehiculo_placa,
            de.id_despacho, de.fecha_despacho,
            cu.nombre AS despachado_por,
            ce.id_conciliacion, ce.estado AS estado_conciliacion, ce.observaciones_repartidor
        FROM entregas e
        JOIN estado_entrega se ON se.id_estado = e.id_estado
        LEFT JOIN repartidores r ON r.id_repartidor = e.id_repartidor
        LEFT JOIN vehiculos v ON v.id_vehiculo = e.id_vehiculo
        LEFT JOIN despacho_entrega de ON de.id_entrega = e.id_entrega
        LEFT JOIN usuarios cu ON cu.id_usuario = de.id_usuario_cajero
        LEFT JOIN conciliacion_entrega ce ON ce.id_entrega = e.id_entrega
        WHERE e.id_entrega = :id
    ");
    $stmt->execute([':id' => $id_entrega]);
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entrega) {
        echo json_encode(['success' => false, 'message' => 'Entrega no encontrada']); exit();
    }
    if (!$entrega['id_despacho']) {
        echo json_encode(['success' => false, 'message' => 'Esta entrega todavía no tiene un despacho registrado']); exit();
    }
    if ($entrega['estado_conciliacion'] === 'VALIDADO') {
        echo json_encode(['success' => false, 'message' => 'Esta entrega ya fue validada y cerrada']); exit();
    }

    // Si el repartidor ya reportó cuánto entregó de verdad (al confirmar la
    // entrega), se usa ESO — no un valor adivinado igual al despacho.
    if ($entrega['id_conciliacion']) {
        $stmt = $conexion->prepare("
            SELECT
                dc.id_lote, dc.id_producto, dc.cantidad_despachada, dc.cantidad_entregada, dc.motivo_diferencia,
                l.numero_lote,
                COALESCE(m.nombre_completo, m.nombre, p.nombre) AS producto_nombre
            FROM detalle_conciliacion dc
            LEFT JOIN lotes l ON l.id_lote = dc.id_lote
            LEFT JOIN medicamentos m ON m.id_medicamento = l.id_medicamento
            LEFT JOIN productos p ON p.id_producto = dc.id_producto
            WHERE dc.id_conciliacion = :id_conciliacion
            ORDER BY dc.id_detalle
        ");
        $stmt->execute([':id_conciliacion' => $entrega['id_conciliacion']]);
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Respaldo: el repartidor confirmó por el camino viejo (sin
        // detalle) — se sugiere entregado = despachado, como antes.
        $stmt = $conexion->prepare("
            SELECT
                dd.id_lote, dd.id_producto, dd.cantidad_despachada,
                dd.cantidad_despachada AS cantidad_entregada, NULL AS motivo_diferencia,
                l.numero_lote,
                COALESCE(m.nombre_completo, m.nombre, p.nombre) AS producto_nombre
            FROM detalle_despacho dd
            LEFT JOIN lotes l ON l.id_lote = dd.id_lote
            LEFT JOIN medicamentos m ON m.id_medicamento = l.id_medicamento
            LEFT JOIN productos p ON p.id_producto = dd.id_producto
            WHERE dd.id_despacho = :id_despacho
            ORDER BY dd.id_detalle
        ");
        $stmt->execute([':id_despacho' => $entrega['id_despacho']]);
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(['success' => true, 'entrega' => $entrega, 'productos' => $productos]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
