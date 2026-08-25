<?php
// backend/portal_cliente/marcar_notificacion_leida.php
// NUEVO — marca una notificación puntual (o todas) del cliente como
// leída. Si mandan id_notificacion, marca solo esa; si mandan
// {"todas": true}, marca todas las suyas de un golpe (para el botón
// "Marcar todas como leídas").

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_cliente_portal'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']); exit();
}
$id_cliente = $_SESSION['id_cliente_portal'];

$data = json_decode(file_get_contents('php://input'), true);
$id_notificacion = intval($data['id_notificacion'] ?? 0);
$todas = !empty($data['todas']);

if (!$id_notificacion && !$todas) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']); exit();
}

try {
    if ($todas) {
        $stmt = $conexion->prepare("
            UPDATE notificaciones_cliente SET leida = TRUE, fecha_lectura = NOW()
            WHERE id_cliente = :id_cliente AND leida = FALSE
        ");
        $stmt->execute([':id_cliente' => $id_cliente]);
    } else {
        // Solo puede marcar SUS PROPIAS notificaciones — el id_cliente de
        // la sesión siempre entra en el WHERE, nunca se confía en lo que
        // mande el navegador para eso.
        $stmt = $conexion->prepare("
            UPDATE notificaciones_cliente SET leida = TRUE, fecha_lectura = NOW()
            WHERE id_notificacion = :id AND id_cliente = :id_cliente
        ");
        $stmt->execute([':id' => $id_notificacion, ':id_cliente' => $id_cliente]);
    }
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
