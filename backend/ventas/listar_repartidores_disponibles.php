<?php
// backend/ventas/listar_repartidores_disponibles.php
// REEMPLAZA el archivo existente.
//
// CAMBIO DE ARQUITECTURA: antes esta consulta exigía que el vehículo
// tuviera vehiculos.id_repartidor = r.id_repartidor (dueño fijo). Eso
// significaba que si un repartidor estaba de vacaciones, su vehículo
// quedaba parqueado sin poder usarse con nadie más.
//
// AHORA: este endpoint solo devuelve REPARTIDORES disponibles (activos,
// sin entrega en curso) con sus habilidades por tipo de vehículo. Ya NO
// selecciona vehículo aquí — eso se hace en un segundo paso con
// listar_vehiculos_para_repartidor.php, una vez que el cajero elige
// a la persona. Así cualquier vehículo DISPONIBLE de un tipo que el
// repartidor sepa manejar queda disponible para él, sin importar de
// quién sea "normalmente".

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    // Repartidor "disponible" = activo, sin entrega en curso, y al menos
    // un vehículo DISPONIBLE que sepa manejar (si no hay ningún vehículo
    // que pueda usar en este momento, no tiene sentido ofrecerlo).
    $stmt = $conexion->query("
        SELECT
            r.id_repartidor,
            r.nombre,
            r.licencia_conducir,
            t.numero AS telefono
        FROM repartidores r
        LEFT JOIN repartidor_telefono rt ON rt.id_repartidor = r.id_repartidor
        LEFT JOIN telefonos t ON t.id_telefono = rt.id_telefono AND t.activo = TRUE
        WHERE r.activo = TRUE
          AND r.estado_laboral = 'ACTIVO'
          AND r.fecha_salida IS NULL
          AND NOT EXISTS (
              SELECT 1 FROM entregas e
              JOIN estado_entrega se ON se.id_estado = e.id_estado
              WHERE e.id_repartidor = r.id_repartidor
                AND se.nombre IN ('PENDIENTE','ASIGNADA','EN_CAMINO')
          )
          AND EXISTS (
              SELECT 1 FROM repartidor_habilidad rh
              JOIN vehiculos v ON LOWER(TRIM(v.tipo)) = LOWER(TRIM(rh.tipo_vehiculo))
              WHERE rh.id_repartidor = r.id_repartidor
                AND v.activo = TRUE AND v.estado = 'DISPONIBLE'
          )
        ORDER BY r.nombre
    ");
    $repartidores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($repartidores)) {
        $ids = implode(',', array_map(fn($r) => (int)$r['id_repartidor'], $repartidores));
        $stmtH = $conexion->query("
            SELECT id_repartidor, tipo_vehiculo, nivel
            FROM repartidor_habilidad
            WHERE id_repartidor IN ($ids)
        ");
        $habilidadesPorRep = [];
        foreach ($stmtH->fetchAll(PDO::FETCH_ASSOC) as $h) {
            $habilidadesPorRep[$h['id_repartidor']][] = ['tipo_vehiculo' => $h['tipo_vehiculo'], 'nivel' => $h['nivel']];
        }
        foreach ($repartidores as &$r) {
            $r['habilidades'] = $habilidadesPorRep[$r['id_repartidor']] ?? [];
        }
        unset($r);
    }

    echo json_encode([
        'success'      => true,
        'repartidores' => $repartidores,
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
