<?php
require_once __DIR__ . '/../conexion.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['id_incidencia']) || empty($data['id_entrega']) || empty($data['id_tipo_incidencia']) || empty($data['descripcion'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    $stmt = $conexion->prepare("
        UPDATE incidencias_entrega SET
            id_entrega = :id_entrega,
            id_tipo_incidencia = :id_tipo,
            descripcion = :descripcion,
            foto_url = :foto_url,
            documento_url = :documento_url,
            reportado_por = :reportado_por,
            reportado_por_repartidor = :reportado_por_repartidor,
            afecta_calificacion = :afecta_calificacion,
            compensacion_cliente = :compensacion_cliente,
            compensacion_repartidor = :compensacion_repartidor,
            observaciones = :observaciones,
            resolucion = :resolucion,
            resuelto_por = :resuelto_por,
            fecha_resolucion = :fecha_resolucion
        WHERE id_incidencia = :id
    ");
    $stmt->execute([
        ':id' => $data['id_incidencia'],
        ':id_entrega' => $data['id_entrega'],
        ':id_tipo' => $data['id_tipo_incidencia'],
        ':descripcion' => $data['descripcion'],
        ':foto_url' => $data['foto_url'] ?? null,
        ':documento_url' => $data['documento_url'] ?? null,
        ':reportado_por' => $data['reportado_por'] ?? null,
        ':reportado_por_repartidor' => $data['reportado_por_repartidor'] ?? false,
        ':afecta_calificacion' => $data['afecta_calificacion'] ?? true,
        ':compensacion_cliente' => $data['compensacion_cliente'] ?? 0,
        ':compensacion_repartidor' => $data['compensacion_repartidor'] ?? 0,
        ':observaciones' => $data['observaciones'] ?? null,
        ':resolucion' => $data['resolucion'] ?? null,
        ':resuelto_por' => $data['resuelto_por'] ?? null,
        ':fecha_resolucion' => $data['fecha_resolucion'] ?? null
    ]);
    echo json_encode(['success' => true]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>