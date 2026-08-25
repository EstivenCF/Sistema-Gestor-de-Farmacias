<?php
// backend/delivery/_auto_asignar.php
// helper reutilizable. Se llama justo después de que un repartidor
// confirma una entrega y queda libre otra vez. Busca en la cola
// (entregas sin repartidor asignado) la más antigua y se la asigna,
// junto con un vehículo disponible que sepa manejar.
//
// Una entrega "en cola" es simplemente una fila en `entregas` con
// id_repartidor IS NULL — no hace falta ninguna tabla ni estado nuevo.
//
// Usa FOR UPDATE SKIP LOCKED para que, si dos repartidores confirman
// casi al mismo tiempo, no compitan por la misma entrega en cola.
//
// ACTUALIZACIÓN — automatización por distancia: antes se le daba a este
// repartidor el vehículo "que suele usar" sin más criterio. Ahora se usa
// la misma lógica de backend/delivery/_asignacion_automatica.php que
// usa procesar_venta.php al facturar: según la distancia_km ya guardada
// en la entrega, se prueba primero el tipo de vehículo ideal para ese
// trayecto y, si este repartidor no tiene uno de ese tipo libre, se cae
// a los tipos alternativos en orden. El tiempo_estimado_minutos también
// se recalcula aquí, ahora sí con el vehículo real que se va a usar (el
// que se guardó al facturar era solo un estimado con el tipo "ideal",
// por si la entrega se iba a la cola).

require_once __DIR__ . '/_asignacion_automatica.php';
require_once __DIR__ . '/../notificaciones/_notificaciones.php';

function intentarAutoAsignarDesdeCola(PDO $conexion, int $id_repartidor): ?array {
    // 1. Confirmar que el repartidor está realmente libre ahora mismo —
    //    incluye estar dentro de su turno laboral (propio o el horario
    //    general de envíos como respaldo, ver PATCH 27/27). Si ya se le
    //    acabó el turno, no se le sigue metiendo trabajo de la cola solo
    //    porque terminó su última entrega justo antes de salir.
    $horarioGeneral = obtenerHorarioGeneralDelivery($conexion);
    $stmt = $conexion->prepare("
        SELECT 1 FROM repartidores r
        WHERE r.id_repartidor = :id AND r.activo = TRUE AND r.fecha_salida IS NULL
          AND CURRENT_TIME >= COALESCE(r.hora_inicio_turno, :horario_inicio::time)
          AND CURRENT_TIME <= COALESCE(r.hora_fin_turno, :horario_fin::time)
          AND NOT EXISTS (
              SELECT 1 FROM entregas e
              JOIN estado_entrega se ON se.id_estado = e.id_estado
              WHERE e.id_repartidor = r.id_repartidor
                AND se.nombre IN ('PENDIENTE','ASIGNADA','EN_CAMINO')
          )
    ");
    $stmt->execute([
        ':id' => $id_repartidor,
        ':horario_inicio' => $horarioGeneral['delivery_hora_inicio'],
        ':horario_fin' => $horarioGeneral['delivery_hora_fin'],
    ]);
    if (!$stmt->fetchColumn()) return null;

    // 2. Tomar la entrega en cola más antigua (bloqueo suave anti-carrera)
    $stmt = $conexion->prepare("
        SELECT e.id_entrega, e.distancia_km, e.id_sucursal, e.latitud_entrega, e.longitud_entrega, e.id_cliente, e.numero_seguimiento
        FROM entregas e
        JOIN estado_entrega se ON se.id_estado = e.id_estado
        WHERE e.id_repartidor IS NULL AND se.nombre = 'PENDIENTE'
        ORDER BY e.fecha_pedido ASC
        LIMIT 1
        FOR UPDATE OF e SKIP LOCKED
    ");
    $stmt->execute();
    $entregaEnCola = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$entregaEnCola) return null; // nada en cola
    $id_entrega = $entregaEnCola['id_entrega'];

    // Si por lo que sea la entrega no tiene distancia_km guardada (base
    // vieja, o dirección sin coordenadas al facturar), se intenta
    // recalcular con las coordenadas de la sucursal — mismo cálculo que
    // procesar_venta.php.
    $km = $entregaEnCola['distancia_km'] !== null ? (float) $entregaEnCola['distancia_km'] : null;
    if ($km === null && $entregaEnCola['latitud_entrega'] !== null && $entregaEnCola['longitud_entrega'] !== null) {
        $stmtSuc = $conexion->prepare("SELECT latitud, longitud FROM sucursales WHERE id_sucursal = :id");
        $stmtSuc->execute([':id' => $entregaEnCola['id_sucursal']]);
        $sucursal = $stmtSuc->fetch(PDO::FETCH_ASSOC);
        if ($sucursal && $sucursal['latitud'] !== null && $sucursal['longitud'] !== null) {
            $km = haversineKm(
                (float) $sucursal['latitud'], (float) $sucursal['longitud'],
                (float) $entregaEnCola['latitud_entrega'], (float) $entregaEnCola['longitud_entrega']
            );
        }
    }

    // 3. Buscar, EN ORDEN DE PREFERENCIA según la distancia, un vehículo
    //    disponible que este repartidor sepa manejar.
    $config = obtenerConfigDelivery($conexion);
    $tiposPreferidos = $km !== null ? tiposVehiculoPorDistancia($km, $config) : TIPOS_VEHICULO_VALIDOS;

    $vehiculo = null;
    foreach ($tiposPreferidos as $tipo) {
        $stmt = $conexion->prepare("
            SELECT v.id_vehiculo, v.tipo, v.placa
            FROM vehiculos v
            JOIN repartidor_habilidad rh ON LOWER(TRIM(rh.tipo_vehiculo)) = LOWER(TRIM(v.tipo)) AND rh.id_repartidor = :id
            WHERE v.activo = TRUE AND v.estado = 'DISPONIBLE' AND LOWER(TRIM(v.tipo)) = LOWER(TRIM(:tipo))
            LIMIT 1
            FOR UPDATE OF v SKIP LOCKED
        ");
        $stmt->execute([':id' => $id_repartidor, ':tipo' => $tipo]);
        $vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($vehiculo) break;
    }
    if (!$vehiculo) return null; // hay cola pero este repartidor no tiene vehículo disponible ahora

    $tiempoEstimado = $km !== null ? calcularTiempoEstimadoMinutos($km, $vehiculo['tipo'], $config) : null;

    // 4. Asignar — y pasarla a ASIGNADA (venía de PENDIENTE en el query de
    // arriba). Sin esto se queda "atascada" en PENDIENTE con repartidor y
    // vehículo puestos, y nadie puede despacharla (mismo bug que se
    // arregló en asignar_entrega_manual.php). También se recalcula
    // tiempo_estimado_minutos con el vehículo real asignado.
    //
    // Si esta entrega venía de la cola SIN hora acordada (fecha_programada
    // seguía NULL desde que se facturó, porque en ese momento no había
    // nadie libre), ahora que por fin se asigna se asume lo mismo que en
    // procesar_venta.php: el repartidor sale de inmediato, así que
    // fecha_programada pasa a ser "ahora + tiempo estimado". Si la entrega
    // SÍ tenía una hora acordada real (se fue a la cola pero el cliente
    // había pedido una hora específica), esa fecha_programada ya existente
    // se respeta tal cual — el COALESCE no la toca.
    $sqlUpdate = "
        UPDATE entregas
        SET id_repartidor = :id_repartidor, id_vehiculo = :id_vehiculo, fecha_asignada = NOW(),
            id_estado = (SELECT id_estado FROM estado_entrega WHERE nombre = 'ASIGNADA')";
    $params = [
        ':id_repartidor' => $id_repartidor,
        ':id_vehiculo'   => $vehiculo['id_vehiculo'],
        ':id_entrega'    => $id_entrega,
    ];
    if ($tiempoEstimado !== null) {
        $sqlUpdate .= ", tiempo_estimado_minutos = :tiempo_estimado";
        $sqlUpdate .= ", fecha_programada = COALESCE(fecha_programada, NOW() + (:tiempo_estimado_fp || ' minutes')::interval)";
        $params[':tiempo_estimado'] = $tiempoEstimado;
        $params[':tiempo_estimado_fp'] = $tiempoEstimado;
    }
    if ($km !== null) {
        $sqlUpdate .= ", distancia_km = :distancia_km";
        $params[':distancia_km'] = $km;
    }
    $sqlUpdate .= " WHERE id_entrega = :id_entrega";
    $stmt = $conexion->prepare($sqlUpdate);
    $stmt->execute($params);

    $stmt = $conexion->prepare("UPDATE vehiculos SET estado = 'EN_USO' WHERE id_vehiculo = :id");
    $stmt->execute([':id' => $vehiculo['id_vehiculo']]);

    $stmt = $conexion->prepare("
        INSERT INTO historial_entrega (id_entrega, id_estado, fecha, observacion)
        SELECT :id_entrega, id_estado, NOW(), 'Asignación automática desde la cola de espera'
        FROM estado_entrega WHERE nombre = 'ASIGNADA'
    ");
    $stmt->execute([':id_entrega' => $id_entrega]);

    // Número de seguimiento, para el mensaje al repartidor
    $stmt = $conexion->prepare("SELECT numero_seguimiento FROM entregas WHERE id_entrega = :id");
    $stmt->execute([':id' => $id_entrega]);
    $numero_seguimiento = $stmt->fetchColumn();

    // Notificación al cliente (PATCH 31/31) — esta entrega venía esperando
    // en la cola, y recién ahora consiguió repartidor.
    if (!empty($entregaEnCola['id_cliente'])) {
        crearNotificacionCliente(
            $conexion, (int) $entregaEnCola['id_cliente'], (int) $id_entrega, 'ASIGNADA',
            'Repartidor asignado a tu pedido',
            "Tu pedido {$numero_seguimiento}, que estaba en cola de espera, ya tiene repartidor asignado."
        );
    }

    return [
        'id_entrega'         => $id_entrega,
        'numero_seguimiento' => $numero_seguimiento,
        'id_vehiculo'        => $vehiculo['id_vehiculo'],
        'vehiculo_texto'     => $vehiculo['tipo'] . ($vehiculo['placa'] ? ' (' . $vehiculo['placa'] . ')' : ''),
    ];
}
