<?php
// backend/delivery/guardar_habilidades_repartidor.php
// NUEVO — guarda qué tipos de vehículo puede manejar un repartidor.
// Reemplaza el set completo de habilidades (borra y vuelve a insertar).

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id_repartidor = intval($data['id_repartidor'] ?? 0);
$habilidades   = $data['habilidades'] ?? []; // [{tipo_vehiculo, nivel}]

if (!$id_repartidor) {
    echo json_encode(['success' => false, 'message' => 'ID de repartidor requerido']); exit();
}

try {
    $conexion->beginTransaction();

    $conexion->prepare("DELETE FROM repartidor_habilidad WHERE id_repartidor = :id")
        ->execute([':id' => $id_repartidor]);

    $stmt = $conexion->prepare("
        INSERT INTO repartidor_habilidad (id_repartidor, tipo_vehiculo, nivel)
        VALUES (:id_repartidor, :tipo, :nivel)
        ON CONFLICT (id_repartidor, tipo_vehiculo) DO UPDATE SET nivel = EXCLUDED.nivel
    ");
    foreach ($habilidades as $h) {
        if (empty($h['tipo_vehiculo'])) continue;
        $stmt->execute([
            ':id_repartidor' => $id_repartidor,
            ':tipo'          => $h['tipo_vehiculo'],
            ':nivel'         => $h['nivel'] ?? 'COMPETENTE',
        ]);
    }

    $conexion->commit();
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
