<?php
// backend/delivery/validar_conciliacion.php
require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}
if (!in_array($_SESSION['rol'], ['Cajero', 'Administrador'])) {
    echo json_encode(['success' => false, 'message' => 'Solo cajeros pueden validar']); exit();
}

$data          = json_decode(file_get_contents('php://input'), true);
$id_entrega    = intval($data['id_entrega']  ?? 0);
$accion        = $data['accion']             ?? '';  // 'VALIDAR' | 'RECHAZAR'
$observaciones = trim($data['observaciones'] ?? '');

if (!$id_entrega || !in_array($accion, ['VALIDAR', 'RECHAZAR'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']); exit();
}

try {
    $conexion->beginTransaction();

    $nuevo_estado_concil = $accion === 'VALIDAR' ? 'VALIDADO' : 'RECHAZADO';

    $stmt = $conexion->prepare("
        UPDATE conciliacion_entrega
        SET estado                 = :estado,
            validado_por           = :validado_por,
            fecha_validacion       = NOW(),
            observaciones_sistema  = COALESCE(observaciones_sistema, '') || ' | Cajero: ' || :obs
        WHERE id_entrega = :id
        RETURNING id_conciliacion
    ");
    $stmt->execute([
        ':estado'       => $nuevo_estado_concil,
        ':validado_por' => $_SESSION['usuario_id'],
        ':obs'          => $observaciones ?: 'Sin observaciones adicionales',
        ':id'           => $id_entrega,
    ]);

    if ($accion === 'VALIDAR') {
        $stmt = $conexion->prepare("
            UPDATE entregas
            SET id_estado = (SELECT id_estado FROM estado_entrega WHERE nombre = 'CERRADO' LIMIT 1)
            WHERE id_entrega = :id
        ");
        $stmt->execute([':id' => $id_entrega]);

        $stmt = $conexion->prepare("
            INSERT INTO historial_entrega (id_entrega, id_estado, fecha, observacion)
            SELECT :id, id_estado, NOW(), 'Conciliación validada y pedido cerrado por cajero'
            FROM estado_entrega WHERE nombre = 'CERRADO' LIMIT 1
        ");
        $stmt->execute([':id' => $id_entrega]);
    }

    $conexion->commit();
    echo json_encode(['success' => true, 'accion' => $accion]);

} catch (PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
