<?php
/**
 * Redistribución inteligente — listado de viajes para la pantalla de
 * seguimiento (viajes_redistribucion.php). Por defecto trae los activos
 * (PLANIFICADO/EN_TRANSITO); con ?historial=1 trae también los cerrados
 * de los últimos 30 días.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

require_once __DIR__ . '/../conexion.php';

$incluirHistorial = !empty($_GET['historial']);

try {
    $where = $incluirHistorial
        ? "vr.fecha_creacion >= CURRENT_DATE - INTERVAL '30 days'"
        : "vr.estado IN ('PLANIFICADO', 'EN_TRANSITO')";

    $stmt = $conexion->query("
        SELECT
            vr.id_viaje, vr.estado, vr.cantidad, vr.distancia_km, vr.tiempo_estimado_minutos,
            vr.combustible_estimado_gal, vr.costo_estimado, vr.score_recomendacion, vr.semaforo,
            vr.fecha_creacion, vr.fecha_entrega,
            l.numero_lote, m.nombre_completo AS medicamento_nombre,
            vr.id_sucursal_origen, vr.id_sucursal_destino,
            so.nombre AS sucursal_origen, sd.nombre AS sucursal_destino,
            v.tipo AS vehiculo_tipo, v.placa AS vehiculo_placa,
            r.id_repartidor, r.nombre AS repartidor_nombre
        FROM viaje_redistribucion vr
        JOIN lotes l ON vr.id_lote = l.id_lote
        JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
        JOIN sucursales so ON vr.id_sucursal_origen = so.id_sucursal
        JOIN sucursales sd ON vr.id_sucursal_destino = sd.id_sucursal
        LEFT JOIN vehiculos v ON vr.id_vehiculo = v.id_vehiculo
        LEFT JOIN repartidores r ON vr.id_repartidor = r.id_repartidor
        WHERE $where
        ORDER BY vr.fecha_creacion DESC
    ");
    $viajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'viajes' => $viajes]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
