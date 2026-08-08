<?php
// backend/delivery/listar_devoluciones.php
// NUEVO — para la pantalla "Devoluciones" (reemplaza a Tracking).
// Usa la tabla devoluciones que ya existía en el sistema.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$busqueda = trim($_GET['q'] ?? '');

try {
    $where = '';
    $params = [];
    if ($busqueda !== '') {
        $where = "WHERE d.numero_documento ILIKE :q OR c.nombre ILIKE :q OR v.numero_documento ILIKE :q";
        $params[':q'] = "%$busqueda%";
    }

    $stmt = $conexion->prepare("
        SELECT
            d.id_devolucion, d.numero_documento, d.fecha_solicitud, d.fecha_completada,
            d.motivo, d.monto_reembolso, d.es_por_recall,
            td.nombre AS tipo_nombre,
            ed.nombre AS estado_nombre,
            c.nombre  AS cliente_nombre,
            v.numero_documento AS venta_documento,
            u.nombre  AS solicitado_por
        FROM devoluciones d
        LEFT JOIN tipo_devolucion td   ON td.id_tipo   = d.id_tipo
        LEFT JOIN estado_devolucion ed ON ed.id_estado = d.id_estado
        LEFT JOIN clientes c           ON c.id_cliente = d.id_cliente
        LEFT JOIN ventas v             ON v.id_venta   = d.id_venta
        LEFT JOIN usuarios u           ON u.id_usuario = d.id_usuario
        $where
        ORDER BY d.fecha_solicitud DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $devoluciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Detalle de productos por devolución más reciente (opcional, liviano: solo counts)
    echo json_encode(['success' => true, 'devoluciones' => $devoluciones]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
