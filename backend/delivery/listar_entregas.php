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

    // ACTUALIZACIÓN — hora estimada de llegada, hora de salida
    // recomendada, y avisos de atraso:
    //   - Si el repartidor ya salió (fecha_inicio, se pone al marcar
    //     EN_CAMINO): la llegada estimada es fecha_inicio + tiempo de
    //     viaje. entrega_atrasada se marca si ya se pasó esa hora y
    //     sigue EN_CAMINO — es la señal para el botón "Llamar al
    //     repartidor".
    //   - Si TODAVÍA NO ha salido pero el cliente pidió una hora
    //     específica (fecha_programada — "hora acordada"): esa hora ES
    //     la llegada objetivo (antes se ignoraba y se mostraba "ahora +
    //     tiempo de viaje", lo que no tenía nada que ver con lo acordado
    //     con el cliente). De ahí se calcula hacia atrás la hora de
    //     salida recomendada, y salida_atrasada avisa si ya debería
    //     haber salido para cumplirla y todavía sigue ASIGNADA (sin
    //     despachar).
    //   - Si no ha salido y no hay hora acordada: se usa el estimado de
    //     siempre (fecha_asignada + tiempo de viaje, asumiendo que sale
    //     ya mismo).
    //
    // El teléfono del repartidor es el suyo propio (repartidor_telefono),
    // NO telefono_emergencia (que es el contacto de emergencia, otra
    // persona) — para que el botón de llamar de verdad marque al
    // repartidor.
    $sql = "
        SELECT
            e.id_entrega, e.numero_seguimiento, e.direccion_entrega, e.barrio_entrega,
            e.costo_entrega, e.fecha_pedido, e.fecha_asignada, e.fecha_entrega_real,
            e.motivo_interrupcion, e.es_entrega_parcial, e.detalle_parcial,
            e.estado_recepcion, e.comentario_cliente, e.confirmado_por_cliente,
            e.distancia_km, e.tiempo_estimado_minutos, e.fecha_programada,
            se.nombre AS estado_nombre, e.id_estado,
            c.nombre AS cliente_nombre,
            v.numero_documento,
            r.id_repartidor, r.nombre AS repartidor_nombre,
            (SELECT t.numero FROM repartidor_telefono rt
             JOIN telefonos t ON t.id_telefono = rt.id_telefono
             WHERE rt.id_repartidor = r.id_repartidor AND t.activo = TRUE
             ORDER BY t.id_telefono LIMIT 1) AS repartidor_telefono,
            veh.id_vehiculo, veh.tipo AS vehiculo_tipo, veh.placa AS vehiculo_placa,
            (SELECT ce.estado FROM conciliacion_entrega ce
             WHERE ce.id_entrega = e.id_entrega
             ORDER BY ce.id_ronda DESC LIMIT 1) AS conciliacion_estado,
            CASE
                WHEN e.fecha_inicio IS NOT NULL AND e.tiempo_estimado_minutos IS NOT NULL THEN
                    e.fecha_inicio + (e.tiempo_estimado_minutos || ' minutes')::interval
                WHEN e.fecha_inicio IS NULL AND e.fecha_programada IS NOT NULL THEN
                    e.fecha_programada
                WHEN e.fecha_inicio IS NULL AND e.id_repartidor IS NOT NULL AND e.tiempo_estimado_minutos IS NOT NULL THEN
                    e.fecha_asignada + (e.tiempo_estimado_minutos || ' minutes')::interval
            END AS hora_estimada_llegada,
            CASE
                WHEN e.fecha_inicio IS NULL AND e.id_repartidor IS NOT NULL
                     AND e.fecha_programada IS NOT NULL AND e.tiempo_estimado_minutos IS NOT NULL THEN
                    e.fecha_programada - (e.tiempo_estimado_minutos || ' minutes')::interval
            END AS hora_salida_recomendada,
            (se.nombre = 'EN_CAMINO' AND e.fecha_inicio IS NOT NULL AND e.tiempo_estimado_minutos IS NOT NULL
             AND NOW() > e.fecha_inicio + (e.tiempo_estimado_minutos || ' minutes')::interval
            ) AS entrega_atrasada,
            (se.nombre = 'ASIGNADA' AND e.fecha_inicio IS NULL AND e.id_repartidor IS NOT NULL
             AND e.fecha_programada IS NOT NULL AND e.tiempo_estimado_minutos IS NOT NULL
             AND NOW() > e.fecha_programada - (e.tiempo_estimado_minutos || ' minutes')::interval
            ) AS salida_atrasada
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
