<?php
// backend/ventas/listar_vehiculos_para_repartidor.php
// NUEVO — segundo paso del flujo de asignación. Dado un repartidor ya
// elegido, devuelve los vehículos DISPONIBLES cuyo tipo coincide con
// alguna de sus habilidades. No importa quién sea el "dueño" del
// vehículo (vehiculos.id_repartidor es solo informativo) — cualquier
// vehículo libre que la persona sepa manejar es una opción válida.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$id_repartidor = intval($_GET['id_repartidor'] ?? 0);
if (!$id_repartidor) {
    echo json_encode(['success' => false, 'message' => 'ID de repartidor requerido']); exit();
}

try {
    // Confirmar que el repartidor sigue disponible (pudo cambiar entre
    // que se listó y que se selecciona)
    $stmt = $conexion->prepare("
        SELECT 1 FROM repartidores r
        WHERE r.id_repartidor = :id AND r.activo = TRUE AND r.estado_laboral = 'ACTIVO' AND r.fecha_salida IS NULL
          AND NOT EXISTS (
              SELECT 1 FROM entregas e
              JOIN estado_entrega se ON se.id_estado = e.id_estado
              WHERE e.id_repartidor = r.id_repartidor
                AND se.nombre IN ('PENDIENTE','ASIGNADA','EN_CAMINO')
          )
    ");
    $stmt->execute([':id' => $id_repartidor]);
    if (!$stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Este repartidor ya no está disponible, actualiza la lista']); exit();
    }

    // Vehículos disponibles cuyo tipo coincide con alguna habilidad del repartidor.
    // Se ordena poniendo primero los que "normalmente" usa esa persona
    // (vehiculos.id_repartidor = mismo repartidor), solo como preferencia,
    // NUNCA como filtro obligatorio.
    $stmt = $conexion->prepare("
        SELECT
            v.id_vehiculo, v.tipo, v.placa, v.marca, v.modelo, v.color,
            v.id_repartidor AS repartidor_habitual,
            rh.nivel AS nivel_habilidad
        FROM vehiculos v
        JOIN repartidor_habilidad rh
            ON LOWER(TRIM(rh.tipo_vehiculo)) = LOWER(TRIM(v.tipo)) AND rh.id_repartidor = :id_repartidor
        WHERE v.activo = TRUE AND v.estado = 'DISPONIBLE'
        ORDER BY (v.id_repartidor = :id_repartidor2) DESC, v.tipo, v.placa
    ");
    $stmt->execute([':id_repartidor' => $id_repartidor, ':id_repartidor2' => $id_repartidor]);
    $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'vehiculos' => $vehiculos]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
