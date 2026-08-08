<?php
// backend/portal_cliente/listar_motivos_cancelacion.php
// NUEVO — catálogo para el combobox de motivos al cancelar.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_cliente_portal'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']); exit();
}

try {
    $stmt = $conexion->query("SELECT id_motivo, nombre FROM motivo_cancelacion_cliente WHERE activo = TRUE ORDER BY orden");
    echo json_encode(['success' => true, 'motivos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
