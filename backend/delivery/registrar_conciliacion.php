<?php
// backend/delivery/registrar_conciliacion.php
// NUEVO — P-4: el cajero valida o rechaza la conciliación, comparando
// lo despachado contra lo entregado. Al validar, el pedido queda
// formalmente cerrado.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}
// No tenía restricción de rol — cualquier usuario autenticado podía llamar
// este endpoint directamente aunque el botón de la UI solo se muestre a
// Cajero/Administrador (mismo grupo que puede reabrir entregas devueltas).
$rol = $_SESSION['rol'] ?? '';
if (!in_array($rol, ['Cajero', 'Administrador'])) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos para conciliar entregas']); exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id_entrega = intval($data['id_entrega'] ?? 0);
$lineas = $data['lineas'] ?? [];
$accion = $data['accion'] ?? ''; // 'validar' | 'rechazar'
$observaciones = trim($data['observaciones'] ?? '');
$id_usuario = $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? 0);

if (!$id_entrega || empty($lineas) || !in_array($accion, ['validar', 'rechazar']) || !$id_usuario) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos para registrar la conciliación']); exit();
}

try {
    $conexion->beginTransaction();

    $stmtDesp = $conexion->prepare("SELECT id_despacho FROM despacho_entrega WHERE id_entrega = :id");
    $stmtDesp->execute([':id' => $id_entrega]);
    $id_despacho = $stmtDesp->fetchColumn();
    if (!$id_despacho) { throw new Exception('Esta entrega no tiene un despacho registrado'); }

    // No se puede conciliar/cerrar una entrega con una disputa abierta del
    // cliente ("no recibí nada") — hay que resolverla primero (llamando al
    // cliente y al repartidor) vía resolver_disputa.php.
    $stmtRecep = $conexion->prepare("SELECT estado_recepcion FROM entregas WHERE id_entrega = :id");
    $stmtRecep->execute([':id' => $id_entrega]);
    if ($stmtRecep->fetchColumn() === 'EN_DISPUTA') {
        throw new Exception('Esta entrega tiene una disputa abierta con el cliente — resuélvela primero');
    }

    // No se puede volver a conciliar una entrega cuya última ronda ya
    // quedó validada o rechazada — sin este chequeo, la UI ya no muestra
    // el botón "Conciliar" para esos casos, pero alguien podría llamar
    // este endpoint directo y sobrescribir una conciliación ya cerrada.
    $stmtEstadoConc = $conexion->prepare("
        SELECT estado FROM conciliacion_entrega WHERE id_entrega = :id ORDER BY id_ronda DESC LIMIT 1
    ");
    $stmtEstadoConc->execute([':id' => $id_entrega]);
    $estadoConcActual = $stmtEstadoConc->fetchColumn();
    if ($estadoConcActual && $estadoConcActual !== 'PENDIENTE') {
        throw new Exception('Esta entrega ya fue conciliada (' . $estadoConcActual . ')');
    }

    $tiene_diferencia = false;
    foreach ($lineas as $l) {
        if (intval($l['cantidad_despachada'] ?? 0) !== intval($l['cantidad_entregada'] ?? 0)) {
            $tiene_diferencia = true;
            break;
        }
    }

    $estado_conciliacion = $accion === 'validar'
        ? ($tiene_diferencia ? 'REQUERIDO_AJUSTE' : 'VALIDADO')
        : 'RECHAZADO';

    // Si hubo varias rondas (por una entrega Parcial redespachada), se
    // valida/cierra siempre la de la ÚLTIMA ronda — la que de verdad
    // terminó el pedido.
    $stmtChk = $conexion->prepare("SELECT id_conciliacion FROM conciliacion_entrega WHERE id_entrega = :id ORDER BY id_ronda DESC LIMIT 1");
    $stmtChk->execute([':id' => $id_entrega]);
    $id_conciliacion = $stmtChk->fetchColumn();

    if ($id_conciliacion) {
        $conexion->prepare("
            UPDATE conciliacion_entrega
            SET tiene_diferencia = :dif, observaciones_sistema = :obs, estado = :estado,
                validado_por = :val, fecha_validacion = NOW(), fecha_conciliacion = NOW()
            WHERE id_conciliacion = :id
        ")->execute([
            ':dif' => $tiene_diferencia ? 't' : 'f', ':obs' => $observaciones ?: null,
            ':estado' => $estado_conciliacion, ':val' => $id_usuario, ':id' => $id_conciliacion,
        ]);
        $conexion->prepare("DELETE FROM detalle_conciliacion WHERE id_conciliacion = :id")->execute([':id' => $id_conciliacion]);
    } else {
        $stmt = $conexion->prepare("
            INSERT INTO conciliacion_entrega (id_entrega, tiene_diferencia, observaciones_sistema, estado, validado_por, fecha_validacion)
            VALUES (:id_entrega, :dif, :obs, :estado, :val, NOW())
            RETURNING id_conciliacion
        ");
        $stmt->execute([
            ':id_entrega' => $id_entrega, ':dif' => $tiene_diferencia ? 't' : 'f',
            ':obs' => $observaciones ?: null, ':estado' => $estado_conciliacion, ':val' => $id_usuario,
        ]);
        $id_conciliacion = $stmt->fetchColumn();
    }

    $stmtDet = $conexion->prepare("
        INSERT INTO detalle_conciliacion (id_conciliacion, id_lote, id_producto, cantidad_despachada, cantidad_entregada, motivo_diferencia)
        VALUES (:id_conciliacion, :id_lote, :id_producto, :desp, :entr, :motivo)
    ");
    foreach ($lineas as $l) {
        $desp = intval($l['cantidad_despachada'] ?? 0);
        $entr = intval($l['cantidad_entregada'] ?? 0);
        $stmtDet->execute([
            ':id_conciliacion' => $id_conciliacion,
            ':id_lote'         => $l['id_lote'] ?? null,
            ':id_producto'     => $l['id_producto'] ?? null,
            ':desp'            => $desp,
            ':entr'            => $entr,
            ':motivo'          => ($desp !== $entr) ? ($l['motivo_diferencia'] ?? null) : null,
        ]);
    }

    $conexion->commit();
    echo json_encode(['success' => true, 'estado_conciliacion' => $estado_conciliacion]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
