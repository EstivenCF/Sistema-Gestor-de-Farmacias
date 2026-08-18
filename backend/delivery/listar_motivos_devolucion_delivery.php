<?php
// backend/delivery/listar_motivos_devolucion_delivery.php
// Catálogo de razones para el combobox de "Registrar devolución" que ve
// el repartidor en el detalle de una entrega.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

try {
    $stmt = $conexion->query("
        SELECT id_motivo, nombre
        FROM motivo_devolucion_delivery
        WHERE activo = true
        ORDER BY orden ASC, nombre ASC
    ");
    $motivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'motivos' => $motivos]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
