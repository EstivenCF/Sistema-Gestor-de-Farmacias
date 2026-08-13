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
require_once __DIR__ . '/riesgo_vencimiento_lib.php';

$id_usuario_sesion = $_SESSION['id_usuario'] ?? $_SESSION['usuario_id'] ?? 1;

$data = json_decode(file_get_contents('php://input'), true);

$id_lote = isset($data['id_lote']) ? (int)$data['id_lote'] : 0;
$id_sucursal_origen = isset($data['id_sucursal_origen']) ? (int)$data['id_sucursal_origen'] : 0;
$id_sucursal_destino = isset($data['id_sucursal_destino']) ? (int)$data['id_sucursal_destino'] : 0;
$cantidad = isset($data['cantidad']) ? (int)$data['cantidad'] : 0;
$motivo = trim($data['motivo'] ?? 'Transferencia entre sucursales');

// NUEVO (Tarea 5 - proceso estratégico de vencimientos). Estos 3 campos son
// OPCIONALES y no afectan las transferencias manuales que ya hace stock.php
// (esa pantalla nunca los envía, así que su comportamiento no cambia en nada).
$id_accion = isset($data['id_accion']) ? (int)$data['id_accion'] : null;
$validar_rotacion = isset($data['validar_rotacion']) && $data['validar_rotacion'] === true;
$forzar = isset($data['forzar']) && $data['forzar'] === true;

if (!$id_lote || !$id_sucursal_origen || !$id_sucursal_destino || $cantidad <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

if ($id_sucursal_origen === $id_sucursal_destino) {
    echo json_encode(['success' => false, 'message' => 'La sucursal origen y destino no pueden ser la misma']);
    exit();
}

// NUEVO (Tarea 5): antes de mover nada, valida si la sucursal destino
// realmente vende bien ese medicamento. Si no cumple el umbral y el usuario
// no confirmó explícitamente ("forzar"), se detiene ANTES de tocar la BD y
// le devuelve al frontend los datos para que muestre la advertencia.
if ($validar_rotacion && !$forzar) {
    $stmtMed = $conexion->prepare("SELECT id_medicamento FROM lotes WHERE id_lote = :id_lote");
    $stmtMed->execute([':id_lote' => $id_lote]);
    $id_medicamento = $stmtMed->fetchColumn();

    if ($id_medicamento) {
        $umbrales = obtenerUmbralesVencimiento($conexion);
        $irvDestino = calcularIRV($conexion, (int)$id_medicamento, $id_sucursal_destino, (int)$umbrales['venc_irv_periodo_dias']);
        $umbralMinimo = (float)$umbrales['venc_irv_umbral_minimo'];

        if ($irvDestino === null || $irvDestino < $umbralMinimo) {
            echo json_encode([
                'success' => false,
                'requiere_confirmacion' => true,
                'irv_destino' => $irvDestino,
                'umbral_minimo' => $umbralMinimo,
                'message' => $irvDestino === null
                    ? 'La sucursal destino no tiene historial de ventas suficiente para este medicamento.'
                    : "La sucursal destino tampoco cumple el umbral mínimo de rotación ({$irvDestino}% vs {$umbralMinimo}% requerido). Transferir ahí probablemente no resuelva el problema."
            ]);
            exit();
        }
    }
}

try {
    $conexion->beginTransaction();
    
    // Verificar stock disponible en origen
    $stmt = $conexion->prepare("SELECT cantidad FROM inventario WHERE id_lote = :id_lote AND id_sucursal = :id_sucursal");
    $stmt->execute([':id_lote' => $id_lote, ':id_sucursal' => $id_sucursal_origen]);
    $stock_origen = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stock_origen || $stock_origen['cantidad'] < $cantidad) {
        $conexion->rollBack();
        echo json_encode(['success' => false, 'message' => 'Stock insuficiente en sucursal origen']);
        exit();
    }
    
    // Restar stock en origen
    $stmt = $conexion->prepare("UPDATE inventario SET cantidad = cantidad - :cantidad WHERE id_lote = :id_lote AND id_sucursal = :id_sucursal");
    $stmt->execute([':cantidad' => $cantidad, ':id_lote' => $id_lote, ':id_sucursal' => $id_sucursal_origen]);
    
    // Sumar stock en destino
    $stmt = $conexion->prepare("INSERT INTO inventario (id_lote, id_sucursal, cantidad) 
                                VALUES (:id_lote, :id_sucursal, :cantidad)
                                ON CONFLICT (id_lote, id_sucursal) 
                                DO UPDATE SET cantidad = inventario.cantidad + :cantidad");
    $stmt->execute([':id_lote' => $id_lote, ':id_sucursal' => $id_sucursal_destino, ':cantidad' => $cantidad]);
    
    // Registrar movimiento de salida en origen
    $stmt = $conexion->prepare("INSERT INTO movimiento_inventario (id_lote, id_sucursal, tipo, cantidad, motivo, referencia, id_usuario) 
                                VALUES (:id_lote, :id_sucursal, 'SALIDA', :cantidad, :motivo, 'TRANSFERENCIA', :id_usuario)
                                RETURNING id_movimiento");
    $stmt->execute([
        ':id_lote' => $id_lote,
        ':id_sucursal' => $id_sucursal_origen,
        ':cantidad' => $cantidad,
        ':motivo' => $motivo,
        ':id_usuario' => $id_usuario_sesion
    ]);
    $id_movimiento_salida = $stmt->fetchColumn(); // NUEVO (Tarea 5): se usa para enlazar con accion_recuperacion
    
    // Registrar movimiento de entrada en destino
    $stmt = $conexion->prepare("INSERT INTO movimiento_inventario (id_lote, id_sucursal, tipo, cantidad, motivo, referencia, id_usuario) 
                                VALUES (:id_lote, :id_sucursal, 'ENTRADA', :cantidad, :motivo, 'TRANSFERENCIA', :id_usuario)");
    $stmt->execute([
        ':id_lote' => $id_lote,
        ':id_sucursal' => $id_sucursal_destino,
        ':cantidad' => $cantidad,
        ':motivo' => $motivo,
        ':id_usuario' => $id_usuario_sesion
    ]);

    // NUEVO (Tarea 5): si esta transferencia viene de una acción de
    // recuperación (Pantalla #05), la marca como COMPLETADA y la enlaza
    // con el movimiento real, en la misma transacción que el traslado.
    if ($id_accion) {
        $stmt = $conexion->prepare("UPDATE accion_recuperacion
                                     SET id_sucursal_destino = :destino,
                                         id_movimiento = :mov,
                                         estado = 'COMPLETADA',
                                         fecha_ejecucion = CURRENT_TIMESTAMP,
                                         valor_recuperado_estimado = valor_en_riesgo
                                     WHERE id_accion = :id_accion");
        $stmt->execute([
            ':destino' => $id_sucursal_destino,
            ':mov' => $id_movimiento_salida,
            ':id_accion' => $id_accion
        ]);
    }
    
    $conexion->commit();
    echo json_encode([
        'success' => true,
        'message' => "$cantidad unidades transferidas correctamente",
        'id_movimiento' => (int)$id_movimiento_salida
    ]);
    
} catch(PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>