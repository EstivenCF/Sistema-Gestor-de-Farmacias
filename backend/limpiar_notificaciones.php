<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
$id_usuario = $_SESSION['id_usuario'] ?? 0;
if ($id_usuario) {
    $stmt = $conexion->prepare("UPDATE notificaciones_sistema SET leida = TRUE, fecha_lectura = NOW() WHERE id_usuario = ? AND leida = FALSE");
    $stmt->execute([$id_usuario]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>