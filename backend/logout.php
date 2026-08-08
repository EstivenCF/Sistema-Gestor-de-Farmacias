<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'conexion.php';

if (isset($_SESSION['id_sesion'])) {

    $id = $_SESSION['id_sesion'];

    $sql = "UPDATE sesiones 
            SET activa = FALSE, fecha_cierre = NOW() 
            WHERE id_sesion = :id";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        error_log("No se actualizó la sesión ID: " . $id);
    }
}

// destruir sesión
session_destroy();

header("Location: index.php");
exit();