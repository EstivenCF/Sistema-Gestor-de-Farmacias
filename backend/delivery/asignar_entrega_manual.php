<?php
// backend/delivery/asignar_entrega_manual.php
// REEMPLAZA el archivo existente.
//
// CAMBIO: ya no exige que la entrega esté "en cola" (sin repartidor).
// Ahora también sirve para REASIGNAR una entrega que ya tenía
// repartidor a otro distinto (por ejemplo, si el repartidor original
// se enfermó a mitad de ruta). Si había un vehículo asignado antes,
// se libera automáticamente al reasignar.
//
// Se usa desde Delivery > Entrega, tanto para las que están "En cola"
// (botón "Asignar") como para las que ya tienen repartidor (botón
// "Reasignar"). Por ahora lo puede usar Cajero o Administrador.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}
if (!in_array($_SESSION['rol'] ?? '', ['Cajero','Administrador'])) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos para asignar entregas']); exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id_entrega    = intval($data['id_entrega'] ?? 0);
$id_repartidor = intval($data['id_repartidor'] ?? 0);
$id_vehiculo   = intval($data['id_vehiculo'] ?? 0);
$motivo_reasignacion = trim($data['motivo_reasignacion'] ?? '');

if (!$id_entrega || !$id_repartidor || !$id_vehiculo) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']); exit();
}

try {
    $conexion->beginTransaction();

    // La entrega debe seguir en un estado donde tenga sentido asignar/reasignar
    $stmt = $conexion->prepare("
        SELECT e.id_entrega, e.id_repartidor AS repartidor_anterior, e.id_vehiculo AS vehiculo_anterior, se.nombre AS estado_actual
        FROM entregas e
        JOIN estado_entrega se ON se.id_estado = e.id_estado
        WHERE e.id_entrega = :id
        FOR UPDATE OF e
    ");
    $stmt->execute([':id' => $id_entrega]);
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$entrega) { throw new Exception('Entrega no encontrada'); }

    $ESTADOS_ASIGNABLES = ['PENDIENTE','ASIGNADA','EN_CAMINO','REPROGRAMADA'];
    if (!in_array($entrega['estado_actual'], $ESTADOS_ASIGNABLES)) {
        throw new Exception('Esta entrega ya está en un estado final (' . $entrega['estado_actual'] . ') y no se puede reasignar');
    }

    $esReasignacion = !empty($entrega['repartidor_anterior']);
    if ($esReasignacion && (int)$entrega['repartidor_anterior'] === $id_repartidor) {
        throw new Exception('Esa entrega ya está asignada a este mismo repartidor');
    }
    if ($esReasignacion && $motivo_reasignacion === '') {
        throw new Exception('Debes indicar el motivo de la reasignación');
    }

    // El repartidor nuevo debe estar realmente disponible ahora mismo
    $stmt = $conexion->prepare("
        SELECT 1 FROM repartidores r
        WHERE r.id_repartidor = :id AND r.activo = TRUE AND r.estado_laboral = 'ACTIVO'
          AND NOT EXISTS (
              SELECT 1 FROM entregas e2
              JOIN estado_entrega se2 ON se2.id_estado = e2.id_estado
              WHERE e2.id_repartidor = r.id_repartidor AND se2.nombre IN ('PENDIENTE','ASIGNADA','EN_CAMINO')
                AND e2.id_entrega != :id_entrega
          )
    ");
    $stmt->execute([':id' => $id_repartidor, ':id_entrega' => $id_entrega]);
    if (!$stmt->fetch()) {
        throw new Exception('Ese repartidor ya no está disponible, actualiza la lista');
    }

    // El vehículo debe estar disponible y ser un tipo que el repartidor sepa manejar
    $stmt = $conexion->prepare("
        SELECT 1 FROM vehiculos v
        JOIN repartidor_habilidad rh
            ON LOWER(TRIM(rh.tipo_vehiculo)) = LOWER(TRIM(v.tipo)) AND rh.id_repartidor = :id_rep
        WHERE v.id_vehiculo = :id_veh AND v.activo = TRUE AND v.estado = 'DISPONIBLE'
    ");
    $stmt->execute([':id_rep' => $id_repartidor, ':id_veh' => $id_vehiculo]);
    if (!$stmt->fetch()) {
        throw new Exception('Ese vehículo ya no está disponible o no coincide con la licencia del repartidor');
    }

    // Asignar/reasignar. Si la entrega todavía no había sido despachada
    // (estaba PENDIENTE o REPROGRAMADA), al ponerle repartidor+vehículo
    // debe pasar a ASIGNADA — si no, se queda "atascada" y el cajero no
    // puede registrar el despacho (registrar_despacho.php exige ASIGNADA).
    // Si ya estaba ASIGNADA o EN_CAMINO (reasignación de repartidor a
    // mitad de camino), se deja el estado como está.
    $ESTADOS_QUE_AVANZAN_A_ASIGNADA = ['PENDIENTE', 'REPROGRAMADA'];
    $debeMoverAAsignada = in_array($entrega['estado_actual'], $ESTADOS_QUE_AVANZAN_A_ASIGNADA);

    $sql = "
        UPDATE entregas
        SET id_repartidor = :id_rep, id_vehiculo = :id_veh, fecha_asignada = NOW(),
            modificado_por = :id_usuario, fecha_modificacion = NOW()";
    if ($debeMoverAAsignada) {
        $sql .= ", id_estado = (SELECT id_estado FROM estado_entrega WHERE nombre = 'ASIGNADA')";
    }
    $sql .= " WHERE id_entrega = :id_entrega";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        ':id_rep'     => $id_repartidor,
        ':id_veh'     => $id_vehiculo,
        ':id_usuario' => $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? null),
        ':id_entrega' => $id_entrega,
    ]);

    // Liberar el vehículo anterior, si había uno
    if (!empty($entrega['vehiculo_anterior'])) {
        $stmt = $conexion->prepare("UPDATE vehiculos SET estado = 'DISPONIBLE' WHERE id_vehiculo = :id AND estado = 'EN_USO'");
        $stmt->execute([':id' => $entrega['vehiculo_anterior']]);
    }

    // Marcar el vehículo nuevo como en uso
    $stmt = $conexion->prepare("UPDATE vehiculos SET estado = 'EN_USO' WHERE id_vehiculo = :id");
    $stmt->execute([':id' => $id_vehiculo]);

    $obs = $esReasignacion
        ? 'Reasignada a otro repartidor' . ($motivo_reasignacion !== '' ? ': ' . $motivo_reasignacion : '')
        : 'Asignación manual desde la cola de espera';

    $stmt = $conexion->prepare("
        INSERT INTO historial_entrega (id_entrega, id_estado, fecha, observacion, id_usuario)
        SELECT :id_entrega, e.id_estado, NOW(), :obs, :id_usuario
        FROM entregas e WHERE e.id_entrega = :id_entrega2
    ");
    $stmt->execute([
        ':id_entrega'  => $id_entrega,
        ':id_entrega2' => $id_entrega,
        ':obs'         => $obs,
        ':id_usuario'  => $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? null),
    ]);

    $conexion->commit();
    echo json_encode(['success' => true, 'fue_reasignacion' => $esReasignacion]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
