<?php
// backend/delivery/listar_sucursales.php
// NUEVO — para el combo de sucursal al crear el acceso de un repartidor.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

try {
    $stmt = $conexion->query("SELECT id_sucursal, nombre FROM sucursales WHERE estado = true ORDER BY nombre");
    echo json_encode(['success' => true, 'sucursales' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
