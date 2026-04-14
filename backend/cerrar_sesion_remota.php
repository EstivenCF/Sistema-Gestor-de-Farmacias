<?php
session_start();
include 'conexion.php';

$data = json_decode(file_get_contents('php://input'), true);
$id_sesion = $data['id_sesion'] ?? 0;

if (!$id_sesion) {
    echo json_encode(['success' => false, 'message' => 'ID de sesión no proporcionado']);
    exit();
}

// Verificar que el usuario actual tenga permisos (administrador)
if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 1) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Cerrar la sesión (actualizar estado)
$stmt = $conexion->prepare("UPDATE sesiones SET activa = FALSE, fecha_cierre = NOW() WHERE id_sesion = :id AND activa = TRUE");
$stmt->execute([':id' => $id_sesion]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Sesión no encontrada o ya cerrada']);
}
?>