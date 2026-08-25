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
//   4. Redespacho tras PARCIAL: una entrega puede tener varias RONDAS de
//      despacho/confirmación. Esta ronda solo se evalúa contra lo que de
//      verdad se despachó en la ronda actual, y el estado final
//      (ENTREGADA vs PARCIAL) se decide contra el pedido completo menos
//      todo lo ya entregado en rondas anteriores — así se sabe si con
//      esta ronda el pedido quedó, por fin, completo.

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

    // Solo puede confirmar su propia entrega (si es Repartidor), y solo
    // si de verdad está EN_CAMINO — si no, cualquiera con el id_entrega
    // podría confirmar entregas ajenas o repetir una ya cerrada.
    $stmt = $conexion->prepare("
        SELECT e.id_vehiculo, e.id_repartidor, e.cedula_receptor_autorizado,
               e.id_venta, e.id_cliente, e.fecha_asignada, se.nombre AS estado_actual,
               e.numero_seguimiento
        FROM entregas e
        JOIN estado_entrega se ON se.id_estado = e.id_estado
        WHERE e.id_entrega = :id
        FOR UPDATE OF e
    ");
    $stmt->execute([':id' => $id_entrega]);
    $prev = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$prev) { throw new Exception('Entrega no encontrada'); }

    if (($_SESSION['rol'] ?? '') === 'Repartidor') {
        $stmtProp = $conexion->prepare("
            SELECT 1 FROM repartidores r
            WHERE r.id_repartidor = :id_rep AND r.id_usuario = :id_usuario
        ");
        $stmtProp->execute([
            ':id_rep'     => $prev['id_repartidor'],
            ':id_usuario' => $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? 0),
        ]);
        if (!$stmtProp->fetchColumn()) {
            throw new Exception('Esta entrega no está asignada a ti');
        }
    }

    if ($prev['estado_actual'] !== 'EN_CAMINO') {
        throw new Exception('Esta entrega no está EN_CAMINO (está ' . $prev['estado_actual'] . ') — no se puede confirmar');
    }

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

    // Ronda de despacho que se está confirmando ahora. entregas.ronda_actual
    // es la única fuente de verdad (ver PATCH 25/25 en Farmacia.sql) — antes
    // se calculaba aquí mismo con MAX(id_ronda) FROM despacho_entrega, y si
    // por lo que fuera esa tabla no tenía la ronda más reciente todavía
    // (ej. justo tras un redespacho), se repetía un número ya usado y
    // tronaba "llave duplicada" en conciliacion_entrega.
    $stmt = $conexion->prepare("SELECT ronda_actual FROM entregas WHERE id_entrega = :id");
    $stmt->execute([':id' => $id_entrega]);
    $id_ronda_actual = (int) $stmt->fetchColumn();

    if ($esExitosa) {
        if (!empty($prev['cedula_receptor_autorizado']) && trim($prev['cedula_receptor_autorizado']) !== $cedula_receptor) {
            $estado_recepcion = 'EN_DISPUTA';
        }

        // Verdad del servidor: lo realmente pedido (pedido COMPLETO de la venta)
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

        // Lo ya entregado en rondas ANTERIORES (no incluye la de ahora,
        // porque su conciliación todavía no existe)
        $stmt = $conexion->prepare("
            SELECT dc.id_lote, dc.id_producto, SUM(dc.cantidad_entregada) AS entregado
            FROM detalle_conciliacion dc
            JOIN conciliacion_entrega ce ON ce.id_conciliacion = dc.id_conciliacion
            WHERE ce.id_entrega = :id
              AND ce.fecha_conciliacion >= COALESCE(:fecha_asignada::timestamp, '-infinity')
            GROUP BY dc.id_lote, dc.id_producto
        ");
        $stmt->execute([':id' => $id_entrega, ':fecha_asignada' => $prev['fecha_asignada']]);
        $entregadoPrevioPorClave = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $clave = $row['id_lote'] !== null ? 'L' . $row['id_lote'] : 'P' . $row['id_producto'];
            $entregadoPrevioPorClave[$clave] = (int) $row['entregado'];
        }

        // Lo que el cliente ya devolvió en esta entrega (ver "Registrar
        // devolución") — tampoco se puede volver a contar como entregado.
        $stmt = $conexion->prepare("
            SELECT dd.id_lote, SUM(dd.cantidad) AS devuelto
            FROM detalle_devolucion dd
            JOIN devoluciones dv ON dv.id_devolucion = dd.id_devolucion
            WHERE dv.id_entrega = :id
              AND dv.fecha_solicitud >= COALESCE(:fecha_asignada::timestamp, '-infinity')
            GROUP BY dd.id_lote
        ");
        $stmt->execute([':id' => $id_entrega, ':fecha_asignada' => $prev['fecha_asignada']]);
        $devueltoPorClave = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['id_lote'] === null) continue;
            $devueltoPorClave['L' . $row['id_lote']] = (int) $row['devuelto'];
        }

        // Lo que de verdad se despachó en ESTA ronda (lo que el repartidor
        // tiene físicamente en sus manos ahora)
        $stmt = $conexion->prepare("
            SELECT dd.id_lote, dd.id_producto, SUM(dd.cantidad_despachada) AS despachado
            FROM detalle_despacho dd
            JOIN despacho_entrega de ON de.id_despacho = dd.id_despacho
            WHERE de.id_entrega = :id AND de.id_ronda = :ronda
            GROUP BY dd.id_lote, dd.id_producto
        ");
        $stmt->execute([':id' => $id_entrega, ':ronda' => $id_ronda_actual]);
        $despachadoRondaPorClave = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $clave = $row['id_lote'] !== null ? 'L' . $row['id_lote'] : 'P' . $row['id_producto'];
            $despachadoRondaPorClave[$clave] = (int) $row['despachado'];
        }

        $todoCompleto = true;
        $faltantes = [];

        foreach ($pedidoReal as $id_detalle => $real) {
            $clave = $real['id_lote'] !== null ? 'L' . $real['id_lote'] : 'P' . $real['id_producto'];
            $pedida_total = (int) $real['cantidad'];
            $entregado_previo = $entregadoPrevioPorClave[$clave] ?? 0;
            $devuelto_previo = $devueltoPorClave[$clave] ?? 0;
            $pendiente_antes = max(0, $pedida_total - $entregado_previo - $devuelto_previo);
            $despachado_este_round = $despachadoRondaPorClave[$clave] ?? 0;

            // Tope real: nunca más de lo pendiente NI más de lo que se
            // despachó en esta ronda — jamás lo que mande el navegador.
            $tope = min($pendiente_antes, $despachado_este_round);

            $inputLine = null;
            foreach ($productosInput as $p) {
                if (intval($p['id_detalle'] ?? 0) === (int) $id_detalle) { $inputLine = $p; break; }
            }
            $cant_entregada_round = $inputLine ? max(0, min($tope, intval($inputLine['cantidad_entregada'] ?? 0))) : 0;

            $pendiente_despues = $pendiente_antes - $cant_entregada_round;
            if ($pendiente_despues > 0) {
                $todoCompleto = false;
                $faltantes[] = "{$real['producto_nombre']}: " . ($pedida_total - $pendiente_despues) . " de $pedida_total";
            }

            // Solo se guarda en la conciliación de esta ronda lo que tuvo
            // movimiento en ella (se despachó y/o se reportó entregado ahora)
            if ($despachado_este_round > 0 || $cant_entregada_round > 0) {
                $itemsConciliacion[] = [
                    'id_lote'     => $real['id_lote'],
                    'id_producto' => $real['id_producto'],
                    'pedida'      => $despachado_este_round,
                    'entregada'   => $cant_entregada_round,
                ];
            }
        }

        if (empty($itemsConciliacion)) {
            throw new Exception('No se reconoció ningún producto válido de esta venta, o no había nada despachado en esta ronda');
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
        ':obs'       => $obsFinal ?: "Confirmado por repartidor: $resultado (ronda $id_ronda_actual)",
        ':id_usuario'=> $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? null),
    ]);

    // Trazabilidad por producto de ESTA ronda (solo si hubo entrega, completa o parcial)
    if ($esExitosa && !empty($itemsConciliacion)) {
        $stmt = $conexion->prepare("
            INSERT INTO conciliacion_entrega (id_entrega, id_ronda, tiene_diferencia, observaciones_repartidor, estado)
            VALUES (:id_entrega, :id_ronda, :tiene_diferencia, :obs, 'PENDIENTE')
            RETURNING id_conciliacion
        ");
        $stmt->execute([
            ':id_entrega'      => $id_entrega,
            ':id_ronda'        => $id_ronda_actual,
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

    // Liberar el vehículo — solo en estados verdaderamente finales. Si
    // quedó PARCIAL, el repartidor todavía tiene que volver por lo que
    // falta, así que el vehículo sigue reservado para esa segunda ronda.
    if (!empty($prev['id_vehiculo']) && $nuevo_estado_nombre !== 'PARCIAL') {
        $stmt = $conexion->prepare("UPDATE vehiculos SET estado = 'DISPONIBLE' WHERE id_vehiculo = :id AND estado = 'EN_USO'");
        $stmt->execute([':id' => $prev['id_vehiculo']]);
    }

    // Reprogramación automática (PATCH 29/29) — mismo criterio que
    // backend/delivery/actualizar_estado_entrega.php: si el motivo de la
    // falla es de los que no necesitan que un humano decida nada (ej.
    // "Cliente ausente", "No contesta el teléfono"), la entrega se
    // regresa sola a REPROGRAMADA para mañana a la misma hora, sin
    // repartidor ni vehículo, hasta un tope de intentos.
    $seReprogramoSola = false;
    if (!$esExitosa) {
        $stmt = $conexion->prepare("SELECT reprogramable_automatico FROM motivo_fallida WHERE id_motivo = :id");
        $stmt->execute([':id' => $id_motivo_fallida]);
        $esReprogramable = filter_var($stmt->fetchColumn(), FILTER_VALIDATE_BOOLEAN);

        if ($esReprogramable) {
            $stmt = $conexion->prepare("SELECT numero_intento FROM entregas WHERE id_entrega = :id");
            $stmt->execute([':id' => $id_entrega]);
            $intentoActual = (int) $stmt->fetchColumn();

            $stmt = $conexion->query("SELECT valor FROM configuracion_sistema WHERE clave = 'delivery_max_intentos_reprogramacion'");
            $maxIntentos = (int) ($stmt->fetchColumn() ?: 3);

            if ($intentoActual < $maxIntentos) {
                $idEstadoReprogramada = $conexion->query("SELECT id_estado FROM estado_entrega WHERE nombre = 'REPROGRAMADA'")->fetchColumn();
                if ($idEstadoReprogramada) {
                    $stmt = $conexion->prepare("
                        UPDATE entregas SET
                            id_estado = :id_estado,
                            id_repartidor = NULL,
                            id_vehiculo = NULL,
                            fecha_asignada = NULL,
                            fecha_inicio = NULL,
                            numero_intento = numero_intento + 1,
                            fecha_programada = COALESCE(fecha_programada, NOW()) + INTERVAL '1 day'
                        WHERE id_entrega = :id
                    ");
                    $stmt->execute([':id_estado' => $idEstadoReprogramada, ':id' => $id_entrega]);
                    $seReprogramoSola = true;

                    $stmt = $conexion->prepare("
                        INSERT INTO historial_entrega (id_entrega, id_estado, fecha, observacion, id_usuario)
                        VALUES (:id_entrega, :id_estado, NOW(), :obs, :id_usuario)
                    ");
                    $stmt->execute([
                        ':id_entrega' => $id_entrega,
                        ':id_estado'  => $idEstadoReprogramada,
                        ':obs'        => "Reprogramada automáticamente para mañana (intento " . ($intentoActual + 1) . " de $maxIntentos) — motivo del fallo: $nombre_motivo_fallida",
                        ':id_usuario' => $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? null),
                    ]);
                }
            }
        }

        if ($seReprogramoSola) {
            crearNotificacionCliente(
                $conexion, (int) $prev['id_cliente'], $id_entrega, 'REPROGRAMADA',
                'Tu pedido se reprogramó para mañana',
                "No pudimos completar tu pedido {$prev['numero_seguimiento']} ({$nombre_motivo_fallida}) — lo reprogramamos automáticamente para mañana."
            );
        } else {
            crearNotificacionCliente(
                $conexion, (int) $prev['id_cliente'], $id_entrega, 'FALLIDA',
                'No pudimos completar tu pedido',
                "Tu pedido {$prev['numero_seguimiento']} no se pudo entregar ({$nombre_motivo_fallida}). Nos pondremos en contacto contigo."
            );
        }
    } elseif ($nuevo_estado_nombre !== 'PARCIAL') {
        crearNotificacionCliente(
            $conexion, (int) $prev['id_cliente'], $id_entrega, 'ENTREGADA',
            'Tu pedido fue entregado',
            "Tu pedido {$prev['numero_seguimiento']} fue marcado como entregado. Confírmalo en el portal para poder calificar la entrega."
        );
    } else {
        crearNotificacionCliente(
            $conexion, (int) $prev['id_cliente'], $id_entrega, 'PARCIAL',
            'Tu pedido llegó incompleto',
            "Tu pedido {$prev['numero_seguimiento']} llegó parcial — el repartidor volverá con el resto. Revisa el detalle en el portal."
        );
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
    // de la confirmación que ya se guardó arriba. Si quedó PARCIAL, el
    // repartidor NO está libre todavía (debe volver por lo pendiente),
    // así que no se le asigna nada nuevo de la cola.
    $asignacionAutomatica = null;
    if ($nuevo_estado_nombre !== 'PARCIAL') {
        try {
            $conexion->beginTransaction();
            $asignacionAutomatica = intentarAutoAsignarDesdeCola($conexion, (int)$prev['id_repartidor']);
            $conexion->commit();
        } catch (Exception $eAuto) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            // No se propaga: la confirmación de arriba ya es válida.
        }
    }

    echo json_encode([
        'success'                       => true,
        'estado_final'                  => $seReprogramoSola ? 'REPROGRAMADA' : $nuevo_estado_nombre,
        'estado_recepcion'              => $estado_recepcion,
        'es_parcial'                    => $es_parcial,
        'detalle_parcial'               => $detalle_parcial_txt,
        'reprogramada_automaticamente'  => $seReprogramoSola,
        'auto_asignacion'               => $asignacionAutomatica,
    ]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
