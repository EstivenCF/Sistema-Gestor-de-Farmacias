<?php
/**
 * Redistribución inteligente — reasignar transporte de un viaje ya en curso.
 * Caso de uso: el repartidor asignado se enferma / el vehículo se daña a
 * mitad de camino y hay que cambiar quién/con qué se termina la entrega.
 * Libera el vehículo anterior (vuelve a DISPONIBLE) y ocupa el nuevo — el
 * repartidor anterior queda libre automáticamente porque su disponibilidad
 * se calcula a partir de si TIENE un viaje/entrega activa, no de un campo
 * fijo (ver el parche en listar_repartidores_detalle.php /
 * get_repartidores_estado.php).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

$id_usuario = $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? null);
if (!$id_usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

require_once __DIR__ . '/../conexion.php';

$data = json_decode(file_get_contents('php://input'), true);
$id_viaje = isset($data['id_viaje']) ? (int)$data['id_viaje'] : 0;
$id_vehiculo_nuevo = isset($data['id_vehiculo_nuevo']) ? (int)$data['id_vehiculo_nuevo'] : 0;
$id_repartidor_nuevo = isset($data['id_repartidor_nuevo']) ? (int)$data['id_repartidor_nuevo'] : 0;
$motivo = trim($data['motivo'] ?? '');

if (!$id_viaje || !$id_vehiculo_nuevo || !$id_repartidor_nuevo || $motivo === '') {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos: se requiere id_viaje, id_vehiculo_nuevo, id_repartidor_nuevo y el motivo de la reasignación']);
    exit();
}

try {
    $conexion->beginTransaction();

    $stmt = $conexion->prepare("SELECT id_vehiculo, id_repartidor, estado, explicacion FROM viaje_redistribucion WHERE id_viaje = :id FOR UPDATE");
    $stmt->execute([':id' => $id_viaje]);
    $viaje = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$viaje) {
        throw new Exception('Viaje no encontrado');
    }
    if (!in_array($viaje['estado'], ['PLANIFICADO', 'EN_TRANSITO'], true)) {
        throw new Exception('Este viaje ya está cerrado, no se puede reasignar.');
    }

    // El vehículo nuevo debe estar realmente disponible en este momento.
    $stmt = $conexion->prepare("SELECT estado FROM vehiculos WHERE id_vehiculo = :id FOR UPDATE");
    $stmt->execute([':id' => $id_vehiculo_nuevo]);
    $estadoVehiculoNuevo = $stmt->fetchColumn();
    if ($estadoVehiculoNuevo !== 'DISPONIBLE') {
        throw new Exception('El vehículo elegido ya no está disponible (alguien más lo tomó). Vuelva a consultar la lista.');
    }

    // En reasignar_viaje.php, después de verificar el vehículo
    // Verificar que el vehículo tenga capacidad suficiente
    $stmt = $conexion->prepare("
        SELECT capacidad_carga 
        FROM vehiculos 
        WHERE id_vehiculo = :id
    ");
    $stmt->execute([':id' => $id_vehiculo_nuevo]);
    $capacidad = $stmt->fetchColumn();

    if ($capacidad > 0) {
        // Obtener la cantidad del viaje
        $stmt = $conexion->prepare("SELECT cantidad FROM viaje_redistribucion WHERE id_viaje = :id");
        $stmt->execute([':id' => $id_viaje]);
        $cantidadViaje = $stmt->fetchColumn();
        
        if ($capacidad < $cantidadViaje) {
            throw new Exception("El vehículo seleccionado tiene capacidad para $capacidad u. pero el viaje requiere $cantidadViaje u.");
        }
    }

    // Libera el vehículo anterior
    if ($viaje['id_vehiculo']) {
        $stmt = $conexion->prepare("UPDATE vehiculos SET estado = 'DISPONIBLE' WHERE id_vehiculo = :id AND estado = 'EN_USO'");
        $stmt->execute([':id' => $viaje['id_vehiculo']]);
    }

    // Ocupa el nuevo
    $stmt = $conexion->prepare("UPDATE vehiculos SET estado = 'EN_USO' WHERE id_vehiculo = :id");
    $stmt->execute([':id' => $id_vehiculo_nuevo]);

    $notaReasignacion = "\nReasignado " . date('Y-m-d H:i') . ": $motivo";
    $stmt = $conexion->prepare("
        UPDATE viaje_redistribucion
        SET id_vehiculo = :veh, id_repartidor = :rep,
            explicacion = COALESCE(explicacion, '') || :nota
        WHERE id_viaje = :id
    ");
    $stmt->execute([
        ':veh' => $id_vehiculo_nuevo,
        ':rep' => $id_repartidor_nuevo,
        ':nota' => $notaReasignacion,
        ':id' => $id_viaje,
    ]);

    $conexion->commit();
    echo json_encode(['success' => true, 'message' => 'Viaje reasignado. El vehículo y repartidor anteriores quedaron disponibles de nuevo.']);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
