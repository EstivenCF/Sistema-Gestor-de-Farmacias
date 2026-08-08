<?php
// backend/delivery/listar_vehiculos_detalle.php
// NUEVO — para la pantalla "Vehículos": estado, repartidor dueño y,
// si está EN_USO, qué entrega/repartidor lo tiene en este momento.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

try {
    $stmt = $conexion->query("
        SELECT
            v.id_vehiculo, v.tipo, v.placa, v.marca, v.modelo, v.color,
            v.estado, v.activo, v.fecha_vencimiento_seguro,
            r.id_repartidor, r.nombre AS repartidor_nombre,
            (
                SELECT e.numero_seguimiento FROM entregas e
                JOIN estado_entrega se ON se.id_estado = e.id_estado
                WHERE e.id_vehiculo = v.id_vehiculo
                  AND se.nombre IN ('PENDIENTE','ASIGNADA','EN_CAMINO')
                ORDER BY e.fecha_pedido DESC LIMIT 1
            ) AS entrega_actual,
            (
                SELECT r2.nombre FROM entregas e
                JOIN estado_entrega se ON se.id_estado = e.id_estado
                JOIN repartidores r2 ON r2.id_repartidor = e.id_repartidor
                WHERE e.id_vehiculo = v.id_vehiculo
                  AND se.nombre IN ('PENDIENTE','ASIGNADA','EN_CAMINO')
                ORDER BY e.fecha_pedido DESC LIMIT 1
            ) AS repartidor_actual
        FROM vehiculos v
        LEFT JOIN repartidores r ON r.id_repartidor = v.id_repartidor
        ORDER BY v.estado, v.tipo, v.placa
    ");
    $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats = [
        'total'       => count($vehiculos),
        'disponible'  => count(array_filter($vehiculos, fn($v) => $v['estado'] === 'DISPONIBLE')),
        'en_uso'      => count(array_filter($vehiculos, fn($v) => $v['estado'] === 'EN_USO')),
        'danado'      => count(array_filter($vehiculos, fn($v) => $v['estado'] === 'DAÑADO')),
        'taller'      => count(array_filter($vehiculos, fn($v) => $v['estado'] === 'TALLER')),
    ];

    echo json_encode(['success' => true, 'vehiculos' => $vehiculos, 'stats' => $stats]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
