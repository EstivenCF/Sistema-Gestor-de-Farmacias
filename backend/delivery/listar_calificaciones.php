<?php
// backend/delivery/listar_calificaciones.php
// NUEVO — lista todas las calificaciones que han hecho los clientes,
// con el desglose de respuestas por pregunta. Soporta calificaciones
// viejas (de antes de las preguntas configurables), que solo tenían las
// 5 columnas fijas — para esas se reconstruye un desglose equivalente
// al vuelo, así la pantalla se ve igual sin importar cuándo se calificó.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$id_repartidor = intval($_GET['id_repartidor'] ?? 0);
$fecha_inicio  = $_GET['fecha_inicio'] ?? '';
$fecha_fin     = $_GET['fecha_fin'] ?? '';
$q             = trim($_GET['q'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) $fecha_inicio = '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin))    $fecha_fin    = '';

try {
    $where = [];
    $params = [];
    if ($id_repartidor) { $where[] = 'e.id_repartidor = :id_repartidor'; $params[':id_repartidor'] = $id_repartidor; }
    if ($fecha_inicio)  { $where[] = 'ce.fecha_calificacion >= :fi'; $params[':fi'] = $fecha_inicio . ' 00:00:00'; }
    if ($fecha_fin)     { $where[] = 'ce.fecha_calificacion <= :ff'; $params[':ff'] = $fecha_fin . ' 23:59:59'; }
    if ($q !== '')      { $where[] = '(c.nombre ILIKE :q OR e.numero_seguimiento ILIKE :q)'; $params[':q'] = "%$q%"; }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $conexion->prepare("
        SELECT
            ce.id_calificacion, ce.fecha_calificacion, ce.persona_correcta,
            e.id_entrega, e.numero_seguimiento,
            c.nombre AS cliente_nombre,
            r.nombre AS repartidor_nombre, r.id_repartidor,
            -- Legacy: calificaciones de antes de las preguntas configurables
            ce.puntualidad, ce.atencion, ce.estado_producto, ce.presentacion,
            ce.puntuacion_general, ce.comentario
        FROM calificaciones_entrega ce
        JOIN entregas e   ON e.id_entrega = ce.id_entrega
        JOIN clientes c   ON c.id_cliente = ce.id_cliente
        LEFT JOIN repartidores r ON r.id_repartidor = e.id_repartidor
        $whereSql
        ORDER BY ce.fecha_calificacion DESC
        LIMIT 300
    ");
    $stmt->execute($params);
    $calificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($calificaciones) {
        $ids = array_column($calificaciones, 'id_calificacion');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmtR = $conexion->prepare("
            SELECT id_calificacion, categoria, texto_pregunta, tipo_respuesta, valor_estrellas, valor_texto
            FROM respuestas_calificacion
            WHERE id_calificacion IN ($in)
            ORDER BY id_respuesta ASC
        ");
        $stmtR->execute($ids);
        $respuestasPorCalificacion = [];
        foreach ($stmtR->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $respuestasPorCalificacion[$r['id_calificacion']][] = $r;
        }

        foreach ($calificaciones as &$cal) {
            $respuestas = $respuestasPorCalificacion[$cal['id_calificacion']] ?? [];

            // Si no hay respuestas dinámicas, es una calificación vieja —
            // se reconstruye el mismo desglose a partir de las columnas fijas.
            if (empty($respuestas)) {
                $legacy = [
                    ['categoria' => 'Repartidor',  'texto_pregunta' => 'Atención y trato',                    'tipo_respuesta' => 'ESTRELLAS', 'valor_estrellas' => $cal['atencion']],
                    ['categoria' => 'Medicamento', 'texto_pregunta' => 'Estado en que llegó el producto',     'tipo_respuesta' => 'ESTRELLAS', 'valor_estrellas' => $cal['estado_producto']],
                    ['categoria' => 'Medicamento', 'texto_pregunta' => 'Presentación / empaque',              'tipo_respuesta' => 'ESTRELLAS', 'valor_estrellas' => $cal['presentacion']],
                    ['categoria' => 'General',     'texto_pregunta' => '¿Llegó a tiempo?',                    'tipo_respuesta' => 'ESTRELLAS', 'valor_estrellas' => $cal['puntualidad']],
                    ['categoria' => 'General',     'texto_pregunta' => 'Calificación general del servicio',  'tipo_respuesta' => 'ESTRELLAS', 'valor_estrellas' => $cal['puntuacion_general']],
                ];
                $respuestas = array_values(array_filter($legacy, fn($r) => $r['valor_estrellas'] !== null));
                if (!empty($cal['comentario'])) {
                    $respuestas[] = ['categoria' => 'General', 'texto_pregunta' => 'Comentario', 'tipo_respuesta' => 'TEXTO', 'valor_texto' => $cal['comentario']];
                }
            }
            $cal['respuestas'] = $respuestas;
            unset($cal['puntualidad'], $cal['atencion'], $cal['estado_producto'], $cal['presentacion'], $cal['puntuacion_general'], $cal['comentario']);
        }
        unset($cal);
    }

    $stmtReps = $conexion->query("SELECT id_repartidor, nombre FROM repartidores WHERE activo = TRUE ORDER BY nombre ASC");
    $repartidores = $stmtReps->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'calificaciones' => $calificaciones, 'repartidores' => $repartidores]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
