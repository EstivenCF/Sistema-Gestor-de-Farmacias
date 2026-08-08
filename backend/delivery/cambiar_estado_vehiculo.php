<?php
// backend/delivery/cambiar_estado_vehiculo.php
// REEMPLAZA el archivo existente.
// Antes: solo togglaba 'activo' true/false.
// Ahora: maneja el estado granular (DISPONIBLE, EN_USO, DAÑADO, TALLER, INACTIVO).

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id_vehiculo = intval($data['id_vehiculo'] ?? 0);
$estado      = trim($data['estado'] ?? '');
$ESTADOS_VALIDOS = ['DISPONIBLE','EN_USO','DAÑADO','TALLER','INACTIVO'];

if (!$id_vehiculo || !in_array($estado, $ESTADOS_VALIDOS)) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']); exit();
}

// No permitir marcar EN_USO manualmente (eso lo hace el sistema al asignar una entrega)
if ($estado === 'EN_USO') {
    echo json_encode(['success' => false, 'message' => 'El estado EN_USO se asigna automáticamente al asignar una entrega']); exit();
}

try {
    $activo = ($estado !== 'INACTIVO') ? 't' : 'f';
    $stmt = $conexion->prepare("UPDATE vehiculos SET estado = :estado, activo = :activo WHERE id_vehiculo = :id");
    $stmt->execute([':estado' => $estado, ':activo' => $activo, ':id' => $id_vehiculo]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
