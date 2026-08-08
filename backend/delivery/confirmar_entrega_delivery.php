<?php
// backend/delivery/confirmar_entrega_delivery.php
// REEMPLAZA el archivo existente.
//
// Cambios sobre la versión anterior:
//   1. "Entrega Fallida" ahora EXIGE un motivo (se guarda en observaciones).
//   2. "Entrega Exitosa" ahora recibe la cantidad realmente entregada de
//      CADA producto. El backend compara contra lo pedido (detalle_venta,
//      nunca confía en lo que mande el cliente para "lo pedido") y decide
//      solo si el estado final es ENTREGADA (todo completo) o PARCIAL
//      (algo quedó corto) — el repartidor no elige "parcial" a mano,
//      el sistema lo determina.
//   3. Se guarda el desglose en conciliacion_entrega / detalle_conciliacion
//      (existían pero no se usaban desde el flujo real) para tener
//      trazabilidad de qué se confirmó por producto.

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/_auto_asignar.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}
if (!in_array($_SESSION['rol'] ?? '', ['Repartidor', 'Administrador'])) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']); exit();
}

$data              = json_decode(file_get_contents('php://input'), true);
$id_entrega        = intval($data['id_entrega'] ?? 0);
$resultado         = $data['resultado'] ?? '';       // 'EXITOSA' | 'FALLIDA'
$nombre_receptor   = trim($data['nombre_receptor'] ?? '');
$cedula_receptor   = trim($data['cedula_receptor'] ?? '');
$id_motivo_fallida = intval($data['id_motivo_fallida'] ?? 0);
$detalle_fallida   = trim($data['detalle_fallida'] ?? '');
$observaciones     = trim($data['observaciones'] ?? '');
$productosInput    = $data['productos'] ?? []; // [{id_detalle, cantidad_entregada}] — solo si EXITOSA

if (!$id_entrega || !in_array($resultado, ['EXITOSA', 'FALLIDA'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']); exit();
}
if ($resultado === 'FALLIDA' && !$id_motivo_fallida) {
    echo json_encode(['success' => false, 'message' => 'Debes seleccionar el motivo por el cual no se pudo completar la entrega']); exit();
}
if ($resultado === 'EXITOSA') {
    if ($nombre_receptor === '' || $cedula_receptor === '') {
        echo json_encode(['success' => false, 'message' => 'Debes indicar el nombre y la cédula de quien recibió el pedido']); exit();
    }
    if (empty($productosInput)) {
        echo json_encode(['success' => false, 'message' => 'Debes confirmar la cantidad entregada de cada producto']); exit();
    }
}

try {
    $conexion->beginTransaction();

    $stmt = $conexion->prepare("SELECT id_vehiculo, id_repartidor, cedula_receptor_autorizado, id_venta, id_cliente FROM entregas WHERE id_entrega = :id");
    $stmt->execute([':id' => $id_entrega]);
    $prev = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$prev) { throw new Exception('Entrega no encontrada'); }

    $estado_recepcion = 'CONFIRMADO';
    $esExitosa = ($resultado === 'EXITOSA');
    $nuevo_estado_nombre = 'FALLIDA';
    $detalle_parcial_txt = null;
    $es_parcial = false;
    $itemsConciliacion = [];
    $nombre_motivo_fallida = null;

    if (!$esExitosa) {
        // Validar que el motivo elegido exista realmente en el catálogo
        $stmt = $conexion->prepare("SELECT nombre FROM motivo_fallida WHERE id_motivo = :id AND activo = true");
        $stmt->execute([':id' => $id_motivo_fallida]);
        $nombre_motivo_fallida = $stmt->fetchColumn();
        if (!$nombre_motivo_fallida) {
            throw new Exception('El motivo seleccionado no es válido');
        }
    }

    if ($esExitosa) {
        if (!empty($prev['cedula_receptor_autorizado']) && trim($prev['cedula_receptor_autorizado']) !== $cedula_receptor) {
            $estado_recepcion = 'EN_DISPUTA';
        }

        // Verdad del servidor: lo realmente pedido, con nombre de producto para el resumen
        $stmt = $conexion->prepare("
            SELECT dv.id_detalle, dv.id_lote, dv.id_producto, dv.cantidad,
                   COALESCE(m.nombre_completo, m.nombre, p.nombre) AS producto_nombre
            FROM detalle_venta dv
            LEFT JOIN lotes l         ON l.id_lote        = dv.id_lote
            LEFT JOIN medicamentos m  ON m.id_medicamento  = l.id_medicamento
            LEFT JOIN productos p     ON p.id_producto     = dv.id_producto
            WHERE dv.id_venta = :id_venta
        ");
        $stmt->execute([':id_venta' => $prev['id_venta']]);
        $pedidoReal = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pedidoReal[$row['id_detalle']] = $row;
        }

        $todoCompleto = true;
        $faltantes = [];

        foreach ($productosInput as $p) {
            $id_detalle = intval($p['id_detalle'] ?? 0);
            if (!isset($pedidoReal[$id_detalle])) continue; // ignora líneas que no pertenecen a esta venta

            $real = $pedidoReal[$id_detalle];
            $cant_pedida = (int) $real['cantidad'];
            $cant_entregada = max(0, min($cant_pedida, intval($p['cantidad_entregada'] ?? 0)));

            if ($cant_entregada < $cant_pedida) {
                $todoCompleto = false;
                $faltantes[] = "{$real['producto_nombre']}: $cant_entregada de $cant_pedida";
            }

            $itemsConciliacion[] = [
                'id_lote'     => $real['id_lote'],
                'id_producto' => $real['id_producto'],
                'pedida'      => $cant_pedida,
                'entregada'   => $cant_entregada,
            ];
        }

        if (empty($itemsConciliacion)) {
            throw new Exception('No se reconoció ningún producto válido de esta venta');
        }

        // Si no se entregó nada de nada, esto NO es una entrega parcial —
        // es una entrega que no se pudo realizar. Se rechaza para que
        // el repartidor use "Entrega Fallida" con su motivo correspondiente.
        $totalEntregado = array_sum(array_column($itemsConciliacion, 'entregada'));
        if ($totalEntregado === 0) {
            throw new Exception('No se entregó ningún producto. Si no pudiste completar la entrega, selecciona "Entrega Fallida" e indica el motivo.');
        }

        $es_parcial = !$todoCompleto;
        $nuevo_estado_nombre = $es_parcial ? 'PARCIAL' : 'ENTREGADA';
        if ($es_parcial) {
            $detalle_parcial_txt = implode('; ', $faltantes);
        }
    }

    $stmt = $conexion->prepare("SELECT id_estado FROM estado_entrega WHERE nombre = :nombre LIMIT 1");
    $stmt->execute([':nombre' => $nuevo_estado_nombre]);
    $id_estado_nuevo = $stmt->fetchColumn();
    if (!$id_estado_nuevo) { throw new Exception("Estado '$nuevo_estado_nombre' no existe en estado_entrega"); }

    $camposFecha = $esExitosa ? "fecha_entrega_real = NOW(), fecha_entrega = NOW()," : "";
    $obsFinal = $esExitosa
        ? ($observaciones ?: null)
        : ($nombre_motivo_fallida . ($detalle_fallida !== '' ? ' — ' . $detalle_fallida : ''));

    $stmt = $conexion->prepare("
        UPDATE entregas
        SET id_estado           = :id_estado,
            $camposFecha
            nombre_quien_recibe = :receptor,
            identificacion_quien_recibe = :cedula,
            estado_recepcion    = :estado_recepcion,
            es_entrega_parcial  = :es_parcial,
            detalle_parcial     = :detalle_parcial,
            id_motivo_fallida   = :id_motivo_fallida,
            observaciones       = :obs,
            modificado_por      = :mod,
            fecha_modificacion  = NOW()
        WHERE id_entrega = :id
    ");
    $stmt->execute([
        ':id_estado'         => $id_estado_nuevo,
        ':receptor'          => $esExitosa ? ($nombre_receptor ?: null) : null,
        ':cedula'            => $esExitosa ? ($cedula_receptor ?: null) : null,
        ':estado_recepcion'  => $esExitosa ? $estado_recepcion : null,
        ':es_parcial'        => $es_parcial ? 't' : 'f',
        ':detalle_parcial'   => $detalle_parcial_txt,
        ':id_motivo_fallida' => $esExitosa ? null : $id_motivo_fallida,
        ':obs'               => $obsFinal ?: null,
        ':mod'               => $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? null),
        ':id'                => $id_entrega,
    ]);

    $stmt = $conexion->prepare("
        INSERT INTO historial_entrega (id_entrega, id_estado, fecha, observacion, id_usuario)
        VALUES (:id, :id_estado, NOW(), :obs, :id_usuario)
    ");
    $stmt->execute([
        ':id'        => $id_entrega,
        ':id_estado' => $id_estado_nuevo,
        ':obs'       => $obsFinal ?: "Confirmado por repartidor: $resultado",
        ':id_usuario'=> $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? null),
    ]);

    // Trazabilidad por producto (solo si hubo entrega, completa o parcial)
    if ($esExitosa && !empty($itemsConciliacion)) {
        $stmt = $conexion->prepare("
            INSERT INTO conciliacion_entrega (id_entrega, tiene_diferencia, observaciones_repartidor, estado)
            VALUES (:id_entrega, :tiene_diferencia, :obs, 'PENDIENTE')
            RETURNING id_conciliacion
        ");
        $stmt->execute([
            ':id_entrega'      => $id_entrega,
            ':tiene_diferencia'=> $es_parcial ? 't' : 'f',
            ':obs'             => $observaciones ?: null,
        ]);
        $id_conciliacion = $stmt->fetchColumn();

        $stmtDet = $conexion->prepare("
            INSERT INTO detalle_conciliacion (id_conciliacion, id_lote, id_producto, cantidad_despachada, cantidad_entregada)
            VALUES (:id_conciliacion, :id_lote, :id_producto, :pedida, :entregada)
        ");
        foreach ($itemsConciliacion as $item) {
            $stmtDet->execute([
                ':id_conciliacion' => $id_conciliacion,
                ':id_lote'         => $item['id_lote'],
                ':id_producto'     => $item['id_producto'],
                ':pedida'          => $item['pedida'],
                ':entregada'       => $item['entregada'],
            ]);
        }
    }

    // Liberar el vehículo
    if (!empty($prev['id_vehiculo'])) {
        $stmt = $conexion->prepare("UPDATE vehiculos SET estado = 'DISPONIBLE' WHERE id_vehiculo = :id AND estado = 'EN_USO'");
        $stmt->execute([':id' => $prev['id_vehiculo']]);
    }

    // Token de calificación (entrega completa o parcial, siempre que haya habido receptor)
    if ($esExitosa) {
        $stmt = $conexion->prepare("SELECT id_calificacion FROM calificaciones_entrega WHERE id_entrega = :id");
        $stmt->execute([':id' => $id_entrega]);
        if (!$stmt->fetchColumn()) {
            $token = bin2hex(random_bytes(16));
            $stmt = $conexion->prepare("
                INSERT INTO calificaciones_entrega (id_entrega, id_cliente, token, token_expira_en)
                VALUES (:id_entrega, :id_cliente, :token, NOW() + INTERVAL '48 hours')
            ");
            $stmt->execute([
                ':id_entrega' => $id_entrega,
                ':id_cliente' => $prev['id_cliente'],
                ':token'      => $token,
            ]);
        }
    }

    $conexion->commit();

    // El repartidor acaba de quedar libre: intentar tomar la entrega
    // más antigua de la cola de espera, si hay alguna. Esto va en su
    // propia transacción — si algo sale mal aquí, NO afecta el éxito
    // de la confirmación que ya se guardó arriba.
    $asignacionAutomatica = null;
    try {
        $conexion->beginTransaction();
        $asignacionAutomatica = intentarAutoAsignarDesdeCola($conexion, (int)$prev['id_repartidor']);
        $conexion->commit();
    } catch (Exception $eAuto) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        // No se propaga: la confirmación de arriba ya es válida.
    }

    echo json_encode([
        'success'               => true,
        'estado_final'          => $nuevo_estado_nombre,
        'estado_recepcion'      => $estado_recepcion,
        'es_parcial'            => $es_parcial,
        'detalle_parcial'       => $detalle_parcial_txt,
        'auto_asignacion'       => $asignacionAutomatica,
    ]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
