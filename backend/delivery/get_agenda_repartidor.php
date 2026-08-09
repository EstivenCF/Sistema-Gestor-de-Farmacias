<?php
// backend/delivery/get_agenda_repartidor.php
// REEMPLAZA el archivo existente.
//
// ANTES: leía de agenda_delivery / agenda_delivery_entrega, tablas que
// nunca se llenan porque procesar_venta.php (el flujo real de asignación
// de delivery) escribe directamente en la tabla "entregas". Por eso la
// agenda del repartidor siempre salía vacía.
//
// AHORA: lee "entregas" directamente, igual que ya hace la pantalla
// Delivery > Entrega del cajero/administrador. Misma fuente de verdad
// para todos.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}
if (!in_array($_SESSION['rol'] ?? '', ['Repartidor', 'Administrador'])) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']); exit();
}

try {
    $rol = $_SESSION['rol'] ?? '';
    $repartidores_disponibles = null;

    if ($rol === 'Administrador') {
        // El administrador puede revisar la agenda de cualquier
        // repartidor a través del filtro — nunca resuelve "su propio"
        // perfil porque un administrador no tiene por qué tener uno.
        $stmt = $conexion->prepare("
            SELECT id_repartidor, nombre FROM repartidores
            WHERE activo = true ORDER BY nombre ASC
        ");
        $stmt->execute();
        $repartidores_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $id_repartidor_solicitado = intval($_GET['id_repartidor'] ?? 0);
        if (!$id_repartidor_solicitado) {
            // Todavía no eligió a nadie en el filtro — no es un error,
            // solo no hay nada que mostrar aún.
            echo json_encode([
                'success'                  => true,
                'requiere_seleccion'       => true,
                'repartidor'               => null,
                'entregas'                 => [],
                'stats'                    => ['total'=>0,'completadas'=>0,'en_camino'=>0,'pendientes'=>0,'incidencias'=>0],
                'repartidores_disponibles' => $repartidores_disponibles,
                'fecha_hoy'                => date('d/m/Y'),
            ]);
            exit();
        }

        $stmt = $conexion->prepare("SELECT id_repartidor, nombre FROM repartidores WHERE id_repartidor = :id AND activo = true");
        $stmt->execute([':id' => $id_repartidor_solicitado]);
        $repartidor = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$repartidor) {
            echo json_encode(['success' => false, 'message' => 'Ese repartidor no existe o está inactivo']); exit();
        }
    } else {
        // Repartidor: SIEMPRE su propio perfil — nunca se le hace caso
        // a un id_repartidor que venga en la URL, para que uno no pueda
        // ver la agenda de otro cambiando el parámetro.
        $id_usuario = $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? null);

        $stmt = $conexion->prepare("
            SELECT r.id_repartidor, r.nombre
            FROM repartidores r
            WHERE r.id_usuario = :id_usuario AND r.activo = true
            LIMIT 1
        ");
        $stmt->execute([':id_usuario' => $id_usuario]);
        $repartidor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$repartidor) {
            echo json_encode(['success' => false, 'message' => 'Sin perfil de repartidor asignado a este usuario']); exit();
        }
    }
    $id_repartidor = $repartidor['id_repartidor'];

    // Entregas activas asignadas a este repartidor (no importa el día en que
    // se crearon; lo relevante es que sigan sin cerrar).
    $stmt = $conexion->prepare("
        SELECT
            e.id_entrega, e.numero_seguimiento, e.direccion_entrega,
            e.barrio_entrega, e.ciudad_entrega, e.costo_entrega,
            e.fecha_pedido, e.fecha_asignada,
            se.nombre AS estado_nombre,
            c.nombre AS cliente_nombre,
            veh.tipo AS vehiculo_tipo, veh.placa AS vehiculo_placa
        FROM entregas e
        JOIN estado_entrega se ON se.id_estado = e.id_estado
        JOIN clientes c        ON c.id_cliente = e.id_cliente
        LEFT JOIN vehiculos veh ON veh.id_vehiculo = e.id_vehiculo
        WHERE e.id_repartidor = :id_repartidor
          AND se.nombre IN ('PENDIENTE','ASIGNADA','EN_CAMINO','REPROGRAMADA','INTERRUMPIDA','PARCIAL')
        ORDER BY e.fecha_asignada ASC NULLS LAST, e.fecha_pedido ASC
    ");
    $stmt->execute([':id_repartidor' => $id_repartidor]);
    $entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Numerar el orden de visita (más antigua primero)
    foreach ($entregas as $i => &$e) { $e['orden_visita'] = $i + 1; }
    unset($e);

    // Completadas HOY (para el KPI; las activas ya no incluyen las cerradas)
    $stmt = $conexion->prepare("
        SELECT COUNT(*) FROM entregas e
        JOIN estado_entrega se ON se.id_estado = e.id_estado
        WHERE e.id_repartidor = :id_repartidor
          AND se.nombre = 'ENTREGADA'
          AND e.fecha_entrega_real::date = CURRENT_DATE
    ");
    $stmt->execute([':id_repartidor' => $id_repartidor]);
    $completadas_hoy = (int) $stmt->fetchColumn();

    $total       = count($entregas);
    $en_camino   = count(array_filter($entregas, fn($e) => $e['estado_nombre'] === 'EN_CAMINO'));
    $pendientes  = count(array_filter($entregas, fn($e) => in_array($e['estado_nombre'], ['PENDIENTE','ASIGNADA','REPROGRAMADA'])));
    $incidencias = count(array_filter($entregas, fn($e) => in_array($e['estado_nombre'], ['INTERRUMPIDA','PARCIAL'])));

    echo json_encode([
        'success'                  => true,
        'repartidor'               => $repartidor,
        'entregas'                 => $entregas,
        'stats'                    => [
            'total'       => $total,
            'completadas' => $completadas_hoy,
            'en_camino'   => $en_camino,
            'pendientes'  => $pendientes,
            'incidencias' => $incidencias,
        ],
        'repartidores_disponibles' => $repartidores_disponibles,
        'fecha_hoy'                => date('d/m/Y'),
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
