<?php
// backend/delivery/actualizar_vehiculo.php
// NUEVO — edita un vehículo completo (tipo, placa, marca, modelo,
// color, estado) en un solo paso. Antes solo se podía cambiar el
// estado; si algo se ponía mal al crear el vehículo (una placa mal
// escrita, por ejemplo) no había forma de corregirlo.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id_vehiculo = intval($data['id_vehiculo'] ?? 0);
$tipo        = trim($data['tipo'] ?? '');
$estado      = trim($data['estado'] ?? '');
$ESTADOS_VALIDOS = ['DISPONIBLE', 'DAÑADO', 'TALLER', 'INACTIVO']; // EN_USO es automático, no se puede poner a mano aquí

if (!$id_vehiculo || $tipo === '' || !in_array($estado, $ESTADOS_VALIDOS)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos o el estado no es válido']); exit();
}

try {
    // Si el vehículo está EN_USO ahora mismo, no permitir tocarlo desde aquí
    // (el mismo resguardo que ya tenía cambiar_estado_vehiculo.php).
    $stmtChk = $conexion->prepare("SELECT estado FROM vehiculos WHERE id_vehiculo = :id");
    $stmtChk->execute([':id' => $id_vehiculo]);
    $estadoActual = $stmtChk->fetchColumn();
    if ($estadoActual === false) {
        echo json_encode(['success' => false, 'message' => 'Vehículo no encontrado']); exit();
    }
    if ($estadoActual === 'EN_USO') {
        echo json_encode(['success' => false, 'message' => 'Este vehículo está en uso ahora mismo — no se puede editar hasta que termine su entrega actual']); exit();
    }

    $activo = ($estado !== 'INACTIVO') ? 't' : 'f';

    // id_repartidor, seguro_empresa y fecha_vencimiento_seguro no están en
    // este formulario (son de otro flujo) — si no llegan, se conservan tal
    // cual estaban, en vez de borrarlos.
    $stmt = $conexion->prepare("
        UPDATE vehiculos
        SET tipo = :tipo, placa = :placa, marca = :marca, modelo = :modelo, color = :color,
            estado = :estado, activo = :activo,
            id_repartidor = COALESCE(:id_repartidor, id_repartidor),
            seguro_empresa = COALESCE(:seguro_empresa, seguro_empresa),
            fecha_vencimiento_seguro = COALESCE(:fecha_vencimiento_seguro, fecha_vencimiento_seguro)
        WHERE id_vehiculo = :id
    ");
    $stmt->execute([
        ':tipo'   => $tipo,
        ':placa'  => trim($data['placa'] ?? '') ?: null,
        ':marca'  => trim($data['marca'] ?? '') ?: null,
        ':modelo' => trim($data['modelo'] ?? '') ?: null,
        ':color'  => trim($data['color'] ?? '') ?: null,
        ':estado' => $estado,
        ':activo' => $activo,
        ':id_repartidor' => $data['id_repartidor'] ?? null,
        ':seguro_empresa' => $data['seguro_empresa'] ?? null,
        ':fecha_vencimiento_seguro' => $data['fecha_vencimiento_seguro'] ?? null,
        ':id'     => $id_vehiculo,
    ]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
