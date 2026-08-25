<?php
// backend/delivery/actualizar_estado_entrega.php
// NUEVO — cambia el estado de una entrega (en curso, entregada, interrumpida,
// parcial, etc.) y libera el vehículo automáticamente cuando corresponde.

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../notificaciones/_notificaciones.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id_entrega     = intval($data['id_entrega'] ?? 0);
$nuevo_estado   = trim($data['estado'] ?? ''); // EN_CAMINO, ENTREGADA, INTERRUMPIDA, PARCIAL, CANCELADA, FALLIDA
$motivo         = trim($data['motivo'] ?? '');        // para INTERRUMPIDA
$detalle_parcial= trim($data['detalle_parcial'] ?? ''); // para PARCIAL
$id_motivo_fallida = intval($data['id_motivo_fallida'] ?? 0); // para FALLIDA
$detalle_fallida   = trim($data['detalle_fallida'] ?? '');    // para FALLIDA (opcional)

$ESTADOS_VALIDOS = ['ASIGNADA','EN_CAMINO','ENTREGADA','INTERRUMPIDA','PARCIAL','CANCELADA','REPROGRAMADA','FALLIDA'];

if (!$id_entrega || !in_array($nuevo_estado, $ESTADOS_VALIDOS)) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']); exit();
}

// El repartidor solo puede tocar SUS PROPIAS entregas, y solo con los
// estados que le corresponden a él ejecutar (cancelar o reprogramar
// son decisiones administrativas, no del repartidor en la calle).
if (($_SESSION['rol'] ?? '') === 'Repartidor') {
    $ESTADOS_PERMITIDOS_REPARTIDOR = ['EN_CAMINO','ENTREGADA','INTERRUMPIDA','PARCIAL','FALLIDA'];
    if (!in_array($nuevo_estado, $ESTADOS_PERMITIDOS_REPARTIDOR)) {
        echo json_encode(['success' => false, 'message' => 'Ese cambio de estado no está permitido desde tu cuenta']); exit();
    }
    $stmtProp = $conexion->prepare("
        SELECT 1 FROM entregas e
        JOIN repartidores r ON r.id_repartidor = e.id_repartidor
        WHERE e.id_entrega = :id AND r.id_usuario = :id_usuario
    ");
    $stmtProp->execute([
        ':id' => $id_entrega,
        ':id_usuario' => $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? 0),
    ]);
    if (!$stmtProp->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Esta entrega no está asignada a ti']); exit();
    }
}
if ($nuevo_estado === 'INTERRUMPIDA' && $motivo === '') {
    echo json_encode(['success' => false, 'message' => 'Debes indicar el motivo de la interrupción']); exit();
}
if ($nuevo_estado === 'PARCIAL' && $detalle_parcial === '') {
    echo json_encode(['success' => false, 'message' => 'Debes indicar el detalle de qué se entregó y qué no']); exit();
}
if ($nuevo_estado === 'FALLIDA' && !$id_motivo_fallida) {
    echo json_encode(['success' => false, 'message' => 'Debes seleccionar el motivo por el cual la entrega no se pudo completar']); exit();
}

// NUEVO — no dejar salir antes de tiempo cuando el cliente pidió una
// hora específica. Si la entrega tiene fecha_programada (hora acordada)
// y todavía falta bastante para la hora recomendada de salida
// (fecha_programada − tiempo_estimado_minutos, ver
// backend/delivery/_asignacion_automatica.php), no tiene sentido que el
// repartidor salga ya — llegaría mucho antes de lo acordado y estaría
// dando vueltas con el pedido esperando. Se deja un margen de 5 minutos
// para no bloquear por una diferencia mínima.
if ($nuevo_estado === 'EN_CAMINO') {
    $stmtChk = $conexion->prepare("
        SELECT
            (fecha_programada IS NOT NULL AND tiempo_estimado_minutos IS NOT NULL
             AND NOW() < fecha_programada - (tiempo_estimado_minutos || ' minutes')::interval - INTERVAL '5 minutes'
            ) AS muy_temprano,
            to_char(fecha_programada - (tiempo_estimado_minutos || ' minutes')::interval, 'HH24:MI') AS hora_salida_txt,
            to_char(fecha_programada, 'HH24:MI') AS hora_acordada_txt
        FROM entregas
        WHERE id_entrega = :id
    ");
    $stmtChk->execute([':id' => $id_entrega]);
    $chk = $stmtChk->fetch(PDO::FETCH_ASSOC);

    if ($chk && filter_var($chk['muy_temprano'], FILTER_VALIDATE_BOOLEAN)) {
        echo json_encode([
            'success' => false,
            'message' => "Todavía es muy temprano para salir — el cliente pidió la entrega para las {$chk['hora_acordada_txt']}. "
                . "Sal aproximadamente a las {$chk['hora_salida_txt']} para no llegar mucho antes de lo acordado.",
        ]);
        exit();
    }

    // NUEVO — el repartidor no puede salir con el pedido si el cajero
    // todavía no registró el despacho de esta ronda (Gestión de Entregas >
    // Despachar, ver backend/delivery/registrar_despacho.php). Antes esto
    // se dejaba pasar y se generaba un despacho de respaldo asumiendo que
    // el repartidor salió con todo lo vendido — pero eso permitía que el
    // repartidor marcara "en camino" sin que nadie de la farmacia hubiera
    // verificado ni entregado físicamente el medicamento. Ahora se exige
    // el despacho real primero.
    $stmtDespReq = $conexion->prepare("
        SELECT 1 FROM despacho_entrega de
        JOIN entregas e2 ON e2.id_entrega = de.id_entrega
        WHERE de.id_entrega = :id AND de.id_ronda = e2.ronda_actual
        LIMIT 1
    ");
    $stmtDespReq->execute([':id' => $id_entrega]);
    if (!$stmtDespReq->fetchColumn()) {
        echo json_encode([
            'success' => false,
            'message' => 'Todavía no puedes salir — el cajero no ha registrado el despacho de este pedido. Pídele que lo despache en Gestión de Entregas antes de marcar la salida.',
        ]);
        exit();
    }
}

// Estados que liberan al repartidor y su vehículo (ya no está "en camino")
$ESTADOS_FINALES = ['ENTREGADA','INTERRUMPIDA','PARCIAL','CANCELADA','FALLIDA'];

try {
    $conexion->beginTransaction();

    $stmt = $conexion->prepare("SELECT id_estado FROM estado_entrega WHERE nombre = :n");
    $stmt->execute([':n' => $nuevo_estado]);
    $id_estado = $stmt->fetchColumn();
    if (!$id_estado) throw new Exception("Estado '$nuevo_estado' no existe en estado_entrega");

    $nombre_motivo_fallida = null;
    if ($nuevo_estado === 'FALLIDA') {
        $stmt = $conexion->prepare("SELECT nombre FROM motivo_fallida WHERE id_motivo = :id AND activo = true");
        $stmt->execute([':id' => $id_motivo_fallida]);
        $nombre_motivo_fallida = $stmt->fetchColumn();
        if (!$nombre_motivo_fallida) throw new Exception('El motivo seleccionado no es válido');
    }

    // Obtener vehículo asignado para liberarlo si aplica, y datos del
    // cliente para las notificaciones del portal (PATCH 31/31).
    $stmt = $conexion->prepare("SELECT id_vehiculo, id_cliente, numero_seguimiento FROM entregas WHERE id_entrega = :id");
    $stmt->execute([':id' => $id_entrega]);
    $entregaInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    $id_vehiculo = $entregaInfo['id_vehiculo'] ?? null;
    $id_cliente_notif = (int) ($entregaInfo['id_cliente'] ?? 0);
    $numero_seguimiento_notif = $entregaInfo['numero_seguimiento'] ?? '';

    $campos = "id_estado = :id_estado, fecha_modificacion = NOW()";
    $params = [':id_estado' => $id_estado, ':id' => $id_entrega];

    if ($nuevo_estado === 'ENTREGADA') {
        $campos .= ", fecha_entrega_real = NOW(), fecha_entrega = NOW()";
    }
    if ($nuevo_estado === 'INTERRUMPIDA') {
        $campos .= ", motivo_interrupcion = :motivo";
        $params[':motivo'] = $motivo;
    }
    if ($nuevo_estado === 'PARCIAL') {
        $campos .= ", es_entrega_parcial = TRUE, detalle_parcial = :detalle";
        $params[':detalle'] = $detalle_parcial;
    }
    if ($nuevo_estado === 'FALLIDA') {
        $campos .= ", id_motivo_fallida = :id_motivo_fallida, observaciones = :obs_fallida";
        $params[':id_motivo_fallida'] = $id_motivo_fallida;
        $params[':obs_fallida'] = $nombre_motivo_fallida . ($detalle_fallida !== '' ? ' — ' . $detalle_fallida : '');
    }
    if ($nuevo_estado === 'EN_CAMINO') {
        $campos .= ", fecha_inicio = NOW()";
    }

    $stmt = $conexion->prepare("UPDATE entregas SET $campos WHERE id_entrega = :id");
    $stmt->execute($params);

    // Ya NO se genera aquí un "despacho de respaldo": antes, si el
    // repartidor marcaba EN_CAMINO sin que el cajero hubiera pasado por
    // "Despachar" (registrar_despacho.php), este archivo le inventaba un
    // despacho asumiendo que salió con el pedido completo — eso permitía
    // que un repartidor se reportara "en camino" sin que nadie en la
    // farmacia hubiera verificado ni entregado físicamente el medicamento.
    // Ahora ese caso ni siquiera llega hasta aquí: el bloqueo de más
    // arriba ("Todavía no puedes salir...") rechaza la transición a
    // EN_CAMINO si no existe ya un despacho_entrega real para
    // entregas.ronda_actual, así que en este punto siempre existe.

    // Historial
    $stmt = $conexion->prepare("
        INSERT INTO historial_entrega (id_entrega, id_estado, fecha, observacion, id_usuario)
        VALUES (:id_entrega, :id_estado, NOW(), :obs, :id_usuario)
    ");
    $obs = $nuevo_estado === 'INTERRUMPIDA' ? $motivo
         : ($nuevo_estado === 'PARCIAL' ? $detalle_parcial
         : ($nuevo_estado === 'FALLIDA' ? $nombre_motivo_fallida
         : "Cambio de estado a $nuevo_estado"));
    $stmt->execute([
        ':id_entrega' => $id_entrega,
        ':id_estado'  => $id_estado,
        ':obs'        => $obs,
        ':id_usuario' => $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? null),
    ]);

    // Liberar vehículo si la entrega llegó a un estado final
    if ($id_vehiculo && in_array($nuevo_estado, $ESTADOS_FINALES)) {
        $stmt = $conexion->prepare("UPDATE vehiculos SET estado = 'DISPONIBLE' WHERE id_vehiculo = :id AND estado = 'EN_USO'");
        $stmt->execute([':id' => $id_vehiculo]);
    }

    // Notificaciones al cliente (PATCH 31/31)
    if ($nuevo_estado === 'EN_CAMINO') {
        crearNotificacionCliente(
            $conexion, $id_cliente_notif, $id_entrega, 'EN_CAMINO',
            '¡Tu repartidor va en camino!',
            "El repartidor ya salió con tu pedido {$numero_seguimiento_notif}."
        );
    } elseif ($nuevo_estado === 'ENTREGADA') {
        crearNotificacionCliente(
            $conexion, $id_cliente_notif, $id_entrega, 'ENTREGADA',
            'Tu pedido fue entregado',
            "Tu pedido {$numero_seguimiento_notif} fue marcado como entregado. Confírmalo en el portal para poder calificar la entrega."
        );
    } elseif (in_array($nuevo_estado, ['INTERRUMPIDA', 'CANCELADA'])) {
        crearNotificacionCliente(
            $conexion, $id_cliente_notif, $id_entrega, $nuevo_estado,
            $nuevo_estado === 'CANCELADA' ? 'Tu pedido fue cancelado' : 'Tu entrega se interrumpió',
            "Tu pedido {$numero_seguimiento_notif} " . ($nuevo_estado === 'CANCELADA' ? 'fue cancelado' : 'se interrumpió') . ". Contáctanos si tienes dudas."
        );
    }

    // Reprogramación automática (PATCH 29/29): si el motivo de la falla es
    // de los que NO necesitan que un humano decida nada (ej. "Cliente
    // ausente", "No contesta el teléfono"), la entrega se regresa sola a
    // REPROGRAMADA para mañana a la misma hora, sin repartidor ni vehículo
    // — así entra de nuevo en la asignación automática como cualquier
    // entrega nueva. Motivos donde SÍ hace falta que alguien decida
    // (dirección incorrecta, producto dañado, vehículo averiado, etc.) se
    // quedan en FALLIDA tal cual, a propósito. También hay un tope de
    // intentos (delivery_max_intentos_reprogramacion) para no reintentar
    // para siempre una entrega que nunca se puede completar.
    $seReprogramoSola = false;
    if ($nuevo_estado === 'FALLIDA') {
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

        // Notificación al cliente — distinta según si se reprogramó sola
        // o si de verdad se quedó fallida esperando revisión.
        if ($seReprogramoSola) {
            crearNotificacionCliente(
                $conexion, $id_cliente_notif, $id_entrega, 'REPROGRAMADA',
                'Tu pedido se reprogramó para mañana',
                "No pudimos completar tu pedido {$numero_seguimiento_notif} ({$nombre_motivo_fallida}) — lo reprogramamos automáticamente para mañana."
            );
        } else {
            crearNotificacionCliente(
                $conexion, $id_cliente_notif, $id_entrega, 'FALLIDA',
                'No pudimos completar tu pedido',
                "Tu pedido {$numero_seguimiento_notif} no se pudo entregar ({$nombre_motivo_fallida}). Nos pondremos en contacto contigo."
            );
        }
    }

    $conexion->commit();
    echo json_encode(['success' => true, 'estado' => $seReprogramoSola ? 'REPROGRAMADA' : $nuevo_estado, 'reprogramada_automaticamente' => $seReprogramoSola]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
