<?php
// backend/delivery/confirmar_entrega_detalle.php
// NUEVO — reemplaza la confirmación simple de "Entregada". Ahora el
// repartidor reporta, producto por producto, cuánto entregó de
// verdad (no siempre es igual a lo que se despachó — a veces el
// cliente rechaza algo, o no había suficiente cambio, etc.). Si algo
// no se entregó completo, se le pide el motivo y se registra
// automáticamente como una devolución de cliente — usando el MISMO
// sistema de Devoluciones que ya existe en Inventario, no uno aparte.
//
// De paso, ya deja armada la conciliación (con los datos reales que
// reportó el repartidor, no un valor adivinado) para que el cajero
// solo tenga que revisarla y validarla en Conciliación (P-4).

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id_entrega = intval($data['id_entrega'] ?? 0);
$lineas = $data['lineas'] ?? [];
$nombre_quien_recibe = trim($data['nombre_quien_recibe'] ?? '');
$id_usuario = $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? 0);

if (!$id_entrega || empty($lineas) || !$id_usuario) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos para confirmar la entrega']); exit();
}

try {
    $conexion->beginTransaction();

    // Solo puede confirmar su propia entrega, y solo si está EN_CAMINO
    $stmt = $conexion->prepare("
        SELECT e.id_entrega, e.id_venta, e.id_cliente, e.id_sucursal, se.nombre AS estado_actual, e.id_vehiculo
        FROM entregas e
        JOIN estado_entrega se ON se.id_estado = e.id_estado
        JOIN repartidores r ON r.id_repartidor = e.id_repartidor
        WHERE e.id_entrega = :id AND r.id_usuario = :id_usuario
        FOR UPDATE OF e
    ");
    $stmt->execute([':id' => $id_entrega, ':id_usuario' => $id_usuario]);
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entrega) { throw new Exception('Esta entrega no está asignada a ti'); }
    if ($entrega['estado_actual'] !== 'EN_CAMINO') {
        throw new Exception('Esta entrega no está EN_CAMINO (está ' . $entrega['estado_actual'] . ')');
    }

    $stmtDesp = $conexion->prepare("SELECT id_despacho FROM despacho_entrega WHERE id_entrega = :id");
    $stmtDesp->execute([':id' => $id_entrega]);
    $id_despacho = $stmtDesp->fetchColumn();

    $hay_faltante = false;
    foreach ($lineas as $l) {
        if (intval($l['cantidad_entregada'] ?? 0) < intval($l['cantidad_despachada'] ?? 0)) {
            $hay_faltante = true;
            break;
        }
    }

    // Estado final de la entrega: PARCIAL si algo quedó corto, ENTREGADA si no
    $nombre_estado_final = $hay_faltante ? 'PARCIAL' : 'ENTREGADA';
    $stmt = $conexion->prepare("SELECT id_estado FROM estado_entrega WHERE nombre = :n");
    $stmt->execute([':n' => $nombre_estado_final]);
    $id_estado_final = $stmt->fetchColumn();

    $camposExtra = $hay_faltante
        ? ", es_entrega_parcial = TRUE, detalle_parcial = :detalle_parcial"
        : "";
    $sql = "UPDATE entregas SET id_estado = :id_estado, fecha_entrega_real = NOW(), fecha_entrega = NOW(),
            nombre_quien_recibe = :nombre_recibe, fecha_modificacion = NOW() $camposExtra WHERE id_entrega = :id";
    $stmt = $conexion->prepare($sql);
    $params = [
        ':id_estado' => $id_estado_final,
        ':nombre_recibe' => $nombre_quien_recibe ?: null,
        ':id' => $id_entrega,
    ];
    if ($hay_faltante) {
        $params[':detalle_parcial'] = 'Entrega con diferencias — ver devolución registrada';
    }
    $stmt->execute($params);

    // Liberar el vehículo, ya volvió
    if ($entrega['id_vehiculo']) {
        $conexion->prepare("UPDATE vehiculos SET estado = 'DISPONIBLE' WHERE id_vehiculo = :id AND estado = 'EN_USO'")
            ->execute([':id' => $entrega['id_vehiculo']]);
    }

    // Si hubo faltantes, registrar la devolución (mismo sistema que ya
    // existe en Inventario > Devoluciones — tipo CLIENTE)
    $id_devolucion = null;
    if ($hay_faltante) {
        $lineasConFaltante = array_filter($lineas, fn($l) =>
            intval($l['cantidad_entregada'] ?? 0) < intval($l['cantidad_despachada'] ?? 0));

        $motivos = array_unique(array_filter(array_map(fn($l) => $l['motivo_devolucion'] ?? '', $lineasConFaltante)));
        $motivo_general = implode(' / ', $motivos) ?: 'Entrega parcial reportada por el repartidor';

        $numero_doc = 'DEV-' . date('Ymd') . '-' . str_pad($id_entrega, 4, '0', STR_PAD_LEFT);
        $stmtDev = $conexion->prepare("
            INSERT INTO devoluciones (numero_documento, id_venta, id_sucursal, id_cliente, motivo, id_usuario, id_tipo, id_estado)
            VALUES (:num, :id_venta, :id_sucursal, :id_cliente, :motivo, :id_usuario,
                    (SELECT id_tipo FROM tipo_devolucion WHERE nombre = 'CLIENTE'),
                    (SELECT id_estado FROM estado_devolucion WHERE nombre = 'SOLICITADA'))
            RETURNING id_devolucion
        ");
        $stmtDev->execute([
            ':num' => $numero_doc,
            ':id_venta' => $entrega['id_venta'],
            ':id_sucursal' => $entrega['id_sucursal'],
            ':id_cliente' => $entrega['id_cliente'],
            ':motivo' => $motivo_general,
            ':id_usuario' => $id_usuario,
        ]);
        $id_devolucion = $stmtDev->fetchColumn();

        $stmtDetDev = $conexion->prepare("
            INSERT INTO detalle_devolucion (id_devolucion, id_lote, cantidad)
            VALUES (:id_devolucion, :id_lote, :cantidad)
        ");
        foreach ($lineasConFaltante as $l) {
            $faltante = intval($l['cantidad_despachada']) - intval($l['cantidad_entregada']);
            if ($faltante <= 0 || empty($l['id_lote'])) continue;
            $stmtDetDev->execute([
                ':id_devolucion' => $id_devolucion,
                ':id_lote' => $l['id_lote'],
                ':cantidad' => $faltante,
            ]);
        }
    }

    // Dejar armada la conciliación con los datos reales que reportó el
    // repartidor — así el cajero, en Conciliación (P-4), revisa lo que
    // de verdad pasó en vez de un valor adivinado.
    if ($id_despacho) {
        $stmtConc = $conexion->prepare("
            INSERT INTO conciliacion_entrega (id_entrega, tiene_diferencia, observaciones_repartidor, estado)
            VALUES (:id_entrega, :dif, :obs, 'PENDIENTE')
            RETURNING id_conciliacion
        ");
        $stmtConc->execute([
            ':id_entrega' => $id_entrega,
            ':dif' => $hay_faltante ? 't' : 'f',
            ':obs' => $data['observaciones_repartidor'] ?? null,
        ]);
        $id_conciliacion = $stmtConc->fetchColumn();

        $stmtDetConc = $conexion->prepare("
            INSERT INTO detalle_conciliacion (id_conciliacion, id_lote, id_producto, cantidad_despachada, cantidad_entregada, motivo_diferencia)
            VALUES (:id_conciliacion, :id_lote, :id_producto, :desp, :entr, :motivo)
        ");
        foreach ($lineas as $l) {
            $desp = intval($l['cantidad_despachada'] ?? 0);
            $entr = intval($l['cantidad_entregada'] ?? 0);
            $stmtDetConc->execute([
                ':id_conciliacion' => $id_conciliacion,
                ':id_lote' => $l['id_lote'] ?? null,
                ':id_producto' => $l['id_producto'] ?? null,
                ':desp' => $desp,
                ':entr' => $entr,
                ':motivo' => ($desp !== $entr) ? ($l['motivo_devolucion'] ?? null) : null,
            ]);
        }
    }

    $conexion->prepare("
        INSERT INTO historial_entrega (id_entrega, id_estado, fecha, id_usuario, observacion)
        VALUES (:id_entrega, :id_estado, NOW(), :id_usuario, :obs)
    ")->execute([
        ':id_entrega' => $id_entrega,
        ':id_estado' => $id_estado_final,
        ':id_usuario' => $id_usuario,
        ':obs' => $hay_faltante ? 'Entrega parcial — devolución registrada' : 'Entrega completa confirmada',
    ]);

    $conexion->commit();
    echo json_encode([
        'success' => true,
        'estado' => $nombre_estado_final,
        'id_devolucion' => $id_devolucion,
    ]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
