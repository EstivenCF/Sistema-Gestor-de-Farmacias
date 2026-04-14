<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    // Total proveedores
    $stmt = $conexion->query("SELECT COUNT(*) FROM proveedores");
    $total = $stmt->fetchColumn();

    // Proveedores con al menos un teléfono
    $stmt = $conexion->query("SELECT COUNT(DISTINCT p.id_proveedor) FROM proveedores p JOIN proveedor_telefono pt ON p.id_proveedor = pt.id_proveedor");
    $con_telefono = $stmt->fetchColumn();

    // Proveedores con al menos un correo
    $stmt = $conexion->query("SELECT COUNT(DISTINCT p.id_proveedor) FROM proveedores p JOIN proveedor_correo pc ON p.id_proveedor = pc.id_proveedor");
    $con_correo = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'total' => intval($total),
        'con_telefono' => intval($con_telefono),
        'con_correo' => intval($con_correo)
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>