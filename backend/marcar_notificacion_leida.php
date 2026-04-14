<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
$data = json_decode(file_get_contents('php://input'), true);
$id_notif = $data['id_notificacion'] ?? 0;
$id_usuario = $_SESSION['id_usuario'] ?? 0;
if ($id_notif && $id_usuario) {
    $stmt = $conexion->prepare("UPDATE notificaciones_sistema SET leida = TRUE, fecha_lectura = NOW() WHERE id_notificacion = ? AND id_usuario = ?");
    $stmt->execute([$id_notif, $id_usuario]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>