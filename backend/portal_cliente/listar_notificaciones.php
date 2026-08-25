<?php
// backend/portal_cliente/listar_notificaciones.php
// NUEVO — lista las notificaciones del cliente que inició sesión en el
// portal (más recientes primero), y de paso hace una pasada perezosa
// para detectar entregas EN_CAMINO que ya se atrasaron y todavía no
// tienen su notificación de "ATRASADA" creada. No hace falta un cron
// para esto: como el cliente abre el portal para ver sus pedidos, cada
// vez que lo hace es una oportunidad de detectar el atraso y avisarle.

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../notificaciones/_notificaciones.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_cliente_portal'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']); exit();
}
$id_cliente = $_SESSION['id_cliente_portal'];

try {
    // Detecta entregas EN_CAMINO y atrasadas (mismo cálculo que
    // backend/portal_cliente/listar_mis_entregas.php) que todavía no
    // tienen su notificación de atraso — y se la crea ahora mismo.
    $stmt = $conexion->prepare("
        SELECT e.id_entrega, e.numero_seguimiento,
            to_char(e.fecha_inicio + (e.tiempo_estimado_minutos || ' minutes')::interval, 'HH24:MI') AS hora_esperada_txt
        FROM entregas e
        JOIN estado_entrega se ON se.id_estado = e.id_estado
        WHERE e.id_cliente = :id_cliente
          AND se.nombre = 'EN_CAMINO'
          AND e.fecha_inicio IS NOT NULL AND e.tiempo_estimado_minutos IS NOT NULL
          AND NOW() > e.fecha_inicio + (e.tiempo_estimado_minutos || ' minutes')::interval
    ");
    $stmt->execute([':id_cliente' => $id_cliente]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $atrasada) {
        crearNotificacionClienteSiNoExiste(
            $conexion, $id_cliente, (int) $atrasada['id_entrega'], 'ATRASADA',
            'Tu pedido se está atrasando',
            "El pedido {$atrasada['numero_seguimiento']} debió haber llegado alrededor de las {$atrasada['hora_esperada_txt']} y todavía está en camino. Puedes llamar al repartidor desde el detalle del pedido."
        );
    }

    $stmt = $conexion->prepare("
        SELECT id_notificacion, id_entrega, tipo, titulo, mensaje, leida, fecha_creacion
        FROM notificaciones_cliente
        WHERE id_cliente = :id_cliente
        ORDER BY fecha_creacion DESC
        LIMIT 40
    ");
    $stmt->execute([':id_cliente' => $id_cliente]);
    $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conexion->prepare("SELECT COUNT(*) FROM notificaciones_cliente WHERE id_cliente = :id_cliente AND leida = FALSE");
    $stmt->execute([':id_cliente' => $id_cliente]);
    $noLeidas = (int) $stmt->fetchColumn();

    echo json_encode(['success' => true, 'notificaciones' => $notificaciones, 'no_leidas' => $noLeidas]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
