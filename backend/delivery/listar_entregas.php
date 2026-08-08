<?php
// backend/delivery/listar_entregas.php
// NUEVO — para la pantalla "Entrega". Lista facturas con delivery,
// filtrable por estado (pendiente, en curso, entregada, interrumpida, parcial, etc.)

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$filtro_estado = trim($_GET['estado'] ?? ''); // '', PENDIENTE, ASIGNADA, EN_CAMINO, ENTREGADA, INTERRUMPIDA, PARCIAL, CANCELADA, FALLIDA
$busqueda      = trim($_GET['q'] ?? '');

try {
    $where = [];
    $params = [];

    if ($filtro_estado !== '') {
        $where[] = "se.nombre = :estado";
        $params[':estado'] = $filtro_estado;
    }
    if ($busqueda !== '') {
        $where[] = "(e.numero_seguimiento ILIKE :q OR c.nombre ILIKE :q OR v.numero_documento ILIKE :q)";
        $params[':q'] = "%$busqueda%";
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "
        SELECT
            e.id_entrega, e.numero_seguimiento, e.direccion_entrega, e.barrio_entrega,
            e.costo_entrega, e.fecha_pedido, e.fecha_asignada, e.fecha_entrega_real,
            e.motivo_interrupcion, e.es_entrega_parcial, e.detalle_parcial,
            se.nombre AS estado_nombre, e.id_estado,
            c.nombre AS cliente_nombre,
            v.numero_documento,
            r.id_repartidor, r.nombre AS repartidor_nombre,
            veh.id_vehiculo, veh.tipo AS vehiculo_tipo, veh.placa AS vehiculo_placa
        FROM entregas e
        JOIN estado_entrega se ON se.id_estado = e.id_estado
        JOIN clientes c        ON c.id_cliente = e.id_cliente
        JOIN ventas v           ON v.id_venta  = e.id_venta
        LEFT JOIN repartidores r ON r.id_repartidor = e.id_repartidor
        LEFT JOIN vehiculos veh  ON veh.id_vehiculo  = e.id_vehiculo
        $whereSql
        ORDER BY e.fecha_pedido DESC
        LIMIT 200
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Conteo por estado, para los chips de filtro
    $stmtC = $conexion->query("
        SELECT se.nombre, COUNT(*) AS total
        FROM entregas e JOIN estado_entrega se ON se.id_estado = e.id_estado
        GROUP BY se.nombre
    ");
    $conteos = [];
    foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $c) $conteos[$c['nombre']] = (int)$c['total'];

    echo json_encode(['success' => true, 'entregas' => $entregas, 'conteos' => $conteos]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
