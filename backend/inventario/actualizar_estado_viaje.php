<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

require_once __DIR__ . '/../conexion.php';

$data = json_decode(file_get_contents('php://input'), true);
$id_viaje = isset($data['id_viaje']) ? (int) $data['id_viaje'] : 0;
$nuevo_estado = strtoupper(trim($data['nuevo_estado'] ?? ''));

if (!$id_viaje || !in_array($nuevo_estado, ['ENTREGADO', 'CANCELADO'], true)) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

try {
    $conexion->beginTransaction();

    // Obtener información completa del viaje
    $stmt = $conexion->prepare('
        SELECT 
            v.id_viaje,
            v.id_sucursal_origen,
            v.id_sucursal_destino,
            v.id_lote,
            v.cantidad,
            v.id_vehiculo,
            v.id_repartidor,
            v.estado
        FROM viaje_redistribucion v
        WHERE v.id_viaje = :id
        FOR UPDATE
    ');
    $stmt->execute([':id' => $id_viaje]);
    $viaje = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$viaje) {
        throw new Exception('Viaje no encontrado');
    }

    if (!in_array($viaje['estado'], ['PLANIFICADO', 'EN_TRANSITO'], true)) {
        throw new Exception('Este viaje ya está cerrado.');
    }

    // Si se CANCELA, devolver el inventario a la sucursal de origen
    if ($nuevo_estado === 'CANCELADO') {
        // Verificar si existe el inventario en origen (usando SOLO id_lote)
        $stmt = $conexion->prepare('
            SELECT cantidad 
            FROM inventario 
            WHERE id_sucursal = :id_sucursal 
                AND id_lote = :id_lote
            FOR UPDATE
        ');
        $stmt->execute([
            ':id_sucursal' => $viaje['id_sucursal_origen'],
            ':id_lote' => $viaje['id_lote']
        ]);
        $inventarioOrigen = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($inventarioOrigen) {
            // Actualizar cantidad en origen (sumar)
            $stmt = $conexion->prepare('
                UPDATE inventario 
                SET cantidad = cantidad + :cantidad
                WHERE id_sucursal = :id_sucursal 
                    AND id_lote = :id_lote
            ');
            $stmt->execute([
                ':cantidad' => $viaje['cantidad'],
                ':id_sucursal' => $viaje['id_sucursal_origen'],
                ':id_lote' => $viaje['id_lote']
            ]);
        } else {
            // Si no existe registro en origen, crearlo
            $stmt = $conexion->prepare('
                INSERT INTO inventario (id_sucursal, id_lote, cantidad)
                VALUES (:id_sucursal, :id_lote, :cantidad)
            ');
            $stmt->execute([
                ':id_sucursal' => $viaje['id_sucursal_origen'],
                ':id_lote' => $viaje['id_lote'],
                ':cantidad' => $viaje['cantidad']
            ]);
        }

        // Restar del inventario de destino (si existe)
        $stmt = $conexion->prepare('
            SELECT cantidad 
            FROM inventario 
            WHERE id_sucursal = :id_sucursal 
                AND id_lote = :id_lote
            FOR UPDATE
        ');
        $stmt->execute([
            ':id_sucursal' => $viaje['id_sucursal_destino'],
            ':id_lote' => $viaje['id_lote']
        ]);
        $inventarioDestino = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($inventarioDestino) {
            // Restar del inventario de destino
            $stmt = $conexion->prepare('
                UPDATE inventario 
                SET cantidad = cantidad - :cantidad
                WHERE id_sucursal = :id_sucursal 
                    AND id_lote = :id_lote
            ');
            $stmt->execute([
                ':cantidad' => $viaje['cantidad'],
                ':id_sucursal' => $viaje['id_sucursal_destino'],
                ':id_lote' => $viaje['id_lote']
            ]);
        }
    }

    // Actualizar estado del viaje
    $stmt = $conexion->prepare('
        UPDATE viaje_redistribucion
        SET estado = :estado
        WHERE id_viaje = :id
    ');
    $stmt->execute([
        ':estado' => $nuevo_estado,
        ':id' => $id_viaje,
    ]);

    // Liberar el vehículo (estado a DISPONIBLE)
    if ($viaje['id_vehiculo']) {
        $stmt = $conexion->prepare('
            UPDATE vehiculos
            SET estado = \'DISPONIBLE\'
            WHERE id_vehiculo = :id AND estado = \'EN_USO\'
        ');
        $stmt->execute([':id' => $viaje['id_vehiculo']]);
    }

    // Liberar el repartidor (activo = TRUE)
    if ($viaje['id_repartidor']) {
        $stmt = $conexion->prepare('
            UPDATE repartidores
            SET activo = TRUE
            WHERE id_repartidor = :id
        ');
        $stmt->execute([':id' => $viaje['id_repartidor']]);
    }

    $conexion->commit();

    $mensaje = $nuevo_estado === 'ENTREGADO'
        ? 'Viaje marcado como entregado, vehículo y repartidor liberados.'
        : 'Viaje cancelado, inventario devuelto a sucursal de origen, vehículo y repartidor liberados.';

    echo json_encode([
        'success' => true,
        'message' => $mensaje,
    ]);

} catch (Exception $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>