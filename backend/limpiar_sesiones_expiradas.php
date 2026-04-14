<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 1) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Cerrar todas las sesiones cuya fecha_expiracion sea menor a NOW()
$stmt = $conexion->prepare("UPDATE sesiones SET activa = FALSE, fecha_cierre = NOW() WHERE fecha_expiracion < NOW() AND activa = TRUE");
$stmt->execute();
$count = $stmt->rowCount();

echo json_encode(['success' => true, 'cerradas' => $count]);
?>