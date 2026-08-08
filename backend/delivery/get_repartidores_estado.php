<?php
// backend/delivery/get_repartidores_estado.php
require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) { echo json_encode(['success'=>false,'message'=>'No autorizado']); exit(); }
if (!in_array($_SESSION['rol'], ['Cajero','Administrador'])) { echo json_encode(['success'=>false,'message'=>'Sin permisos']); exit(); }

try {
    $stmt = $conexion->query("
        SELECT
            r.id_repartidor, r.nombre,
            v.tipo AS vehiculo_tipo, v.placa AS vehiculo_placa,
            ad.id_agenda, ad.total_entregas, ad.entregas_completadas, ad.entregas_fallidas,
            (
                SELECT COUNT(*) FROM agenda_delivery_entrega
                WHERE id_agenda = ad.id_agenda AND estado IN ('PENDIENTE','EN_CAMINO')
            ) AS entregas_activas,
            (
                SELECT string_agg(e2.numero_seguimiento || ' - ' || e2.direccion_entrega, ', ')
                FROM agenda_delivery_entrega ade2
                JOIN entregas e2 ON e2.id_entrega = ade2.id_entrega
                WHERE ade2.id_agenda = ad.id_agenda AND ade2.estado = 'EN_CAMINO'
                LIMIT 1
            ) AS entrega_actual
        FROM repartidores r
        LEFT JOIN vehiculos v      ON v.id_repartidor = r.id_repartidor AND v.activo = true
        LEFT JOIN agenda_delivery ad ON ad.id_repartidor = r.id_repartidor AND ad.fecha = CURRENT_DATE
        WHERE r.activo = true
        ORDER BY r.nombre
    ");
    $repartidores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Determinar estado dinámico de cada repartidor
    foreach ($repartidores as &$r) {
        if (!$r['id_agenda']) {
            $r['estado_actual'] = 'FUERA_TURNO';
        } elseif ($r['entregas_activas'] > 0 && $r['entrega_actual']) {
            $r['estado_actual'] = 'EN_CAMINO';
        } elseif ($r['total_entregas'] > 0 && $r['entregas_activas'] == 0) {
            $r['estado_actual'] = 'EN_DESCANSO'; // terminó todas sus entregas
        } else {
            $r['estado_actual'] = 'DISPONIBLE';
        }
    }

    $stats = [
        'total'        => count($repartidores),
        'disponibles'  => count(array_filter($repartidores, fn($r) => $r['estado_actual'] === 'DISPONIBLE')),
        'en_camino'    => count(array_filter($repartidores, fn($r) => $r['estado_actual'] === 'EN_CAMINO')),
        'en_descanso'  => count(array_filter($repartidores, fn($r) => $r['estado_actual'] === 'EN_DESCANSO')),
    ];

    echo json_encode(['success' => true, 'repartidores' => $repartidores, 'stats' => $stats]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
