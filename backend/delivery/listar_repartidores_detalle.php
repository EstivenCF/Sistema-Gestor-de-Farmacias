<?php
// backend/delivery/listar_repartidores_detalle.php
// REEMPLAZA el archivo existente.
//
// Cambios:
//   1. Acepta ?fecha_inicio=&fecha_fin= (por defecto: hoy en ambas) en
//      vez de estar fijo a "hoy". El conteo por filtro (Completadas,
//      Fallidas, Devoluciones, etc.) ahora respeta ese rango.
//   2. Acepta ?mostrar_inactivos=1 para ver también a los repartidores
//      que ya no están activos (para poder reactivarlos si hace falta).
//   3. Devuelve "stats" con los números para el dashboard de la pantalla.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$filtro = trim($_GET['filtro'] ?? 'ENTREGADA');
$FILTROS_VALIDOS = ['ENTREGADA','FALLIDA','PARCIAL','INTERRUMPIDA','CANCELADA','REPROGRAMADA','DEVOLUCIONES'];
if (!in_array($filtro, $FILTROS_VALIDOS)) $filtro = 'ENTREGADA';

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d');
$fecha_fin    = $_GET['fecha_fin']    ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) $fecha_inicio = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin))    $fecha_fin    = date('Y-m-d');
if ($fecha_inicio > $fecha_fin) { $tmp = $fecha_inicio; $fecha_inicio = $fecha_fin; $fecha_fin = $tmp; }

$mostrar_inactivos = !empty($_GET['mostrar_inactivos']);

try {
    $whereActivo = $mostrar_inactivos ? '' : 'WHERE r.activo = TRUE';
    $stmt = $conexion->query("
        SELECT
            r.id_repartidor, r.nombre, r.activo, r.estado_laboral,
            r.tipo_identificacion, r.numero_identificacion,
            r.telefono_emergencia, r.fecha_ingreso, r.foto_url,
            r.id_usuario,
            u.usuario AS usuario_login,
            (
                SELECT COUNT(*) FROM entregas e
                JOIN estado_entrega se ON se.id_estado = e.id_estado
                WHERE e.id_repartidor = r.id_repartidor
                  AND se.nombre IN ('PENDIENTE','ASIGNADA','EN_CAMINO')
            ) AS entregas_activas,
            (
                SELECT ROUND(AVG(ce.puntuacion_general)::numeric, 1)
                FROM calificaciones_entrega ce
                JOIN entregas e ON e.id_entrega = ce.id_entrega
                WHERE e.id_repartidor = r.id_repartidor
            ) AS calificacion_promedio,
            (
                SELECT COUNT(*)
                FROM calificaciones_entrega ce
                JOIN entregas e ON e.id_entrega = ce.id_entrega
                WHERE e.id_repartidor = r.id_repartidor
            ) AS total_calificaciones
        FROM repartidores r
        LEFT JOIN usuarios u ON u.id_usuario = r.id_usuario
        $whereActivo
        ORDER BY r.activo DESC, r.nombre
    ");
    $repartidores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($repartidores) {
        $ids = implode(',', array_map(fn($r) => (int)$r['id_repartidor'], $repartidores));

        $stmtV = $conexion->query("SELECT id_repartidor, id_vehiculo, tipo, placa, estado FROM vehiculos WHERE id_repartidor IN ($ids)");
        $vehiculosPorRep = [];
        foreach ($stmtV->fetchAll(PDO::FETCH_ASSOC) as $v) {
            $vehiculosPorRep[$v['id_repartidor']][] = $v;
        }

        $stmtH = $conexion->query("
            SELECT id_repartidor, tipo_vehiculo, nivel, numero_licencia, fecha_vencimiento_licencia
            FROM repartidor_habilidad
            WHERE id_repartidor IN ($ids)
            ORDER BY tipo_vehiculo
        ");
        $habilidadesPorRep = [];
        foreach ($stmtH->fetchAll(PDO::FETCH_ASSOC) as $h) {
            $habilidadesPorRep[$h['id_repartidor']][] = $h;
        }

        $entregasFiltroPorRep = array_fill_keys(array_map(fn($r) => (int)$r['id_repartidor'], $repartidores), 0);

        if ($filtro === 'DEVOLUCIONES') {
            $stmtF = $conexion->prepare("
                SELECT e.id_repartidor, COUNT(*) AS total
                FROM devoluciones d
                JOIN entregas e ON e.id_venta = d.id_venta
                WHERE e.id_repartidor IN ($ids)
                  AND d.fecha_solicitud::date BETWEEN :fi AND :ff
                GROUP BY e.id_repartidor
            ");
            $stmtF->execute([':fi' => $fecha_inicio, ':ff' => $fecha_fin]);
        } else {
            $stmtF = $conexion->prepare("
                SELECT e.id_repartidor, COUNT(*) AS total
                FROM entregas e
                JOIN estado_entrega se ON se.id_estado = e.id_estado
                WHERE e.id_repartidor IN ($ids)
                  AND se.nombre = :filtro
                  AND COALESCE(e.fecha_entrega_real, e.fecha_modificacion, e.fecha_pedido)::date BETWEEN :fi AND :ff
                GROUP BY e.id_repartidor
            ");
            $stmtF->execute([':filtro' => $filtro, ':fi' => $fecha_inicio, ':ff' => $fecha_fin]);
        }
        foreach ($stmtF->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $entregasFiltroPorRep[$f['id_repartidor']] = (int) $f['total'];
        }

        foreach ($repartidores as &$r) {
            $id = $r['id_repartidor'];
            $r['vehiculos']       = $vehiculosPorRep[$id]   ?? [];
            $r['habilidades']     = $habilidadesPorRep[$id] ?? [];
            $r['entregas_filtro'] = $entregasFiltroPorRep[$id] ?? 0;
            $r['tiene_acceso']    = !empty($r['id_usuario']);

            if (!$r['activo']) {
                $r['estado_actual'] = 'INACTIVO';
            } elseif ($r['estado_laboral'] !== 'ACTIVO') {
                $r['estado_actual'] = $r['estado_laboral'];
            } else {
                $r['estado_actual'] = $r['entregas_activas'] > 0 ? 'EN_CAMINO' : 'DISPONIBLE';
            }
        }
        unset($r);
    }

    $stats = [
        'total'          => count($repartidores),
        'disponibles'    => count(array_filter($repartidores, fn($r) => $r['estado_actual'] === 'DISPONIBLE')),
        'en_camino'      => count(array_filter($repartidores, fn($r) => $r['estado_actual'] === 'EN_CAMINO')),
        'no_disponibles' => count(array_filter($repartidores, fn($r) => !in_array($r['estado_actual'], ['DISPONIBLE','EN_CAMINO']) && $r['activo'])),
        'inactivos'      => count(array_filter($repartidores, fn($r) => !$r['activo'])),
        'total_filtro'   => array_sum(array_column($repartidores, 'entregas_filtro')),
        'sin_licencia'   => count(array_filter($repartidores, fn($r) => empty($r['habilidades']))),
    ];

    echo json_encode([
        'success' => true,
        'repartidores' => $repartidores,
        'filtro' => $filtro,
        'fecha_inicio' => $fecha_inicio,
        'fecha_fin' => $fecha_fin,
        'stats' => $stats,
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
