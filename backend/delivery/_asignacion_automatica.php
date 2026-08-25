<?php
// backend/delivery/_asignacion_automatica.php
// NUEVO — selección automática de repartidor y vehículo para una entrega,
// según la distancia real (sucursal -> dirección de entrega) en vez de
// dejar que el cajero elija a mano. También calcula el tiempo estimado de
// viaje, para poder mostrar una hora de llegada esperada y avisar cuando
// un delivery se está atrasando.
//
// Se usa desde:
//   - backend/ventas/procesar_venta.php    (al facturar una venta con delivery)
//   - backend/delivery/_auto_asignar.php   (cuando un repartidor queda libre
//                                            y hay entregas esperando en la cola)
//   - backend/ventas/calcular_costo_envio.php (para mostrarle al cajero, antes
//                                            de facturar, quién se asignaría)

// Distancia en línea recta entre dos coordenadas (fórmula de Haversine).
// Es una aproximación (no sigue calles ni tráfico), pero no necesita
// ningún servicio externo ni llave de API.
function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $radioTierra = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $radioTierra * $c;
}

// Config con valores por defecto (por si el PATCH 26/26 de Farmacia.sql
// todavía no se ha corrido en esta base — así nada se rompe de golpe).
function obtenerConfigDelivery(PDO $conexion): array {
    $config = [
        'delivery_umbral_km_moto'            => 15.0,
        'delivery_umbral_km_carro'           => 40.0,
        'delivery_velocidad_kmh_motocicleta' => 35.0,
        'delivery_velocidad_kmh_carro'       => 30.0,
        'delivery_velocidad_kmh_camion'      => 25.0,
        'delivery_velocidad_kmh_default'     => 25.0,
        'delivery_buffer_minutos'            => 10.0,
        'delivery_peso_umbral_moto_kg'       => 5.0,
        'delivery_peso_umbral_carro_kg'      => 25.0,
        'delivery_items_umbral_moto'         => 15.0,
        'delivery_items_umbral_carro'        => 60.0,
        'delivery_radio_agrupacion_km'       => 1.5,
        'delivery_minutos_por_parada_extra'  => 8.0,
    ];
    $stmt = $conexion->query("SELECT clave, valor FROM configuracion_sistema WHERE clave LIKE 'delivery\\_%' ESCAPE '\\'");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (array_key_exists($row['clave'], $config)) {
            $config[$row['clave']] = (float) $row['valor'];
        }
    }
    return $config;
}

// Horario general de envíos (Configuración > Envíos, claves ya existían
// desde antes pero nunca se aplicaban en ningún lado del backend). Se usa
// como respaldo para los repartidores que no tienen un turno propio
// (repartidores.hora_inicio_turno / hora_fin_turno — ver PATCH 27/27):
// si el repartidor no tiene turno, se asume que trabaja dentro de este
// horario general. No se mete en obtenerConfigDelivery() porque esa
// función castea todo a float y estos valores son horas ("08:00"), no
// números.
function obtenerHorarioGeneralDelivery(PDO $conexion): array {
    $horario = ['delivery_hora_inicio' => '08:00', 'delivery_hora_fin' => '20:00'];
    $stmt = $conexion->query("SELECT clave, valor FROM configuracion_sistema WHERE clave IN ('delivery_hora_inicio', 'delivery_hora_fin')");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $horario[$row['clave']] = $row['valor'];
    }
    return $horario;
}

// Los 3 tipos de vehículo válidos en este sistema (mismos que usa el
// combobox de licencias de repartidores/vehículos: "Motocicleta",
// "Carro", "Camión" — "Bicicleta" se retiró como categoría, ver PATCH
// 6/6 y 7/7 en Farmacia.sql).
const TIPOS_VEHICULO_VALIDOS = ['Motocicleta', 'Carro', 'Camión'];

// Orden de tipos de vehículo preferidos según la distancia — el primero
// es el ideal para ese trayecto; los siguientes son alternativas por si
// el ideal no tiene ningún repartidor+vehículo libre en este momento.
function tiposVehiculoPorDistancia(float $km, array $config): array {
    if ($km <= $config['delivery_umbral_km_moto']) {
        return ['Motocicleta', 'Carro', 'Camión'];
    } elseif ($km <= $config['delivery_umbral_km_carro']) {
        return ['Carro', 'Motocicleta', 'Camión'];
    } else {
        return ['Camión', 'Carro', 'Motocicleta'];
    }
}

// Quita acentos para poder armar la clave de configuración a partir del
// nombre del tipo de vehículo ("Camión" -> "camion") sin depender de
// cómo PHP maneje mayúsculas/minúsculas en UTF-8.
function quitarAcentos(string $texto): string {
    $texto = str_replace(
        ['á','é','í','ó','ú','Á','É','Í','Ó','Ú','ñ','Ñ'],
        ['a','e','i','o','u','A','E','I','O','U','n','N'],
        $texto
    );
    return strtolower(trim($texto));
}

// Rango de "tamaño" de cada tipo de vehículo, para poder comparar cuál es
// más grande que cuál (ver PATCH 28/28).
const RANGO_VEHICULO = ['Motocicleta' => 1, 'Carro' => 2, 'Camión' => 3];

// Tipo de vehículo MÍNIMO que exige la carga del pedido: si se conoce el
// peso total (productos.peso_kg, opcional por producto), se usa eso; si
// no, se cae a la cantidad total de unidades del pedido como respaldo —
// así el factor funciona desde ya aunque nadie haya llenado peso_kg
// todavía. Devuelve null si no se mandó ninguna carga que evaluar (en ese
// caso la distancia decide sola, como antes de este patch).
function tipoVehiculoMinimoPorCarga(?float $pesoKg, ?int $totalUnidades, array $config): ?string {
    if ($pesoKg !== null && $pesoKg > 0) {
        if ($pesoKg <= $config['delivery_peso_umbral_moto_kg']) return 'Motocicleta';
        if ($pesoKg <= $config['delivery_peso_umbral_carro_kg']) return 'Carro';
        return 'Camión';
    }
    if ($totalUnidades !== null && $totalUnidades > 0) {
        if ($totalUnidades <= $config['delivery_items_umbral_moto']) return 'Motocicleta';
        if ($totalUnidades <= $config['delivery_items_umbral_carro']) return 'Carro';
        return 'Camión';
    }
    return null;
}

// Combina el orden de preferencia por distancia con el mínimo que exige
// la carga: nunca se sugiere un vehículo más chico que ese mínimo, aunque
// la distancia sola hubiera bastado con algo menor.
function combinarPreferenciaConCarga(array $tiposPorDistancia, ?string $minimoPorCarga): array {
    if ($minimoPorCarga === null) return $tiposPorDistancia;
    $rangoMinimo = RANGO_VEHICULO[$minimoPorCarga] ?? 1;
    $filtrados = array_values(array_filter(
        $tiposPorDistancia,
        fn($t) => (RANGO_VEHICULO[$t] ?? 1) >= $rangoMinimo
    ));
    if (!$filtrados) {
        $filtrados = array_values(array_filter(
            TIPOS_VEHICULO_VALIDOS,
            fn($t) => (RANGO_VEHICULO[$t] ?? 1) >= $rangoMinimo
        ));
    }
    return $filtrados;
}

function velocidadKmhPorTipo(string $tipo, array $config): float {
    $clave = 'delivery_velocidad_kmh_' . quitarAcentos($tipo);
    $velocidad = $config[$clave] ?? $config['delivery_velocidad_kmh_default'];
    return $velocidad > 0 ? $velocidad : $config['delivery_velocidad_kmh_default'];
}

// Minutos estimados de viaje + margen de alistado, redondeado hacia
// arriba (mejor sobrestimar un poco que hacer esperar al cliente de
// menos de lo prometido).
function calcularTiempoEstimadoMinutos(float $km, string $tipo, array $config): int {
    $velocidad = velocidadKmhPorTipo($tipo, $config);
    $minutosViaje = ($km / $velocidad) * 60;
    return (int) ceil($minutosViaje + $config['delivery_buffer_minutos']);
}

// Subconsulta reutilizable: promedio de calificación del repartidor en
// atención al cliente / amabilidad. Combina las calificaciones viejas
// (columna fija calificaciones_entrega.atencion) con las nuevas
// (preguntas configurables por estrellas de categoría "Repartidor" —
// ver backend/delivery/listar_repartidores_detalle.php, de donde se
// copió este mismo cálculo para no tener dos criterios distintos de
// "buena calificación" en el sistema).
const SQL_CALIFICACION_PROMEDIO_REPARTIDOR = "
    (SELECT ROUND(AVG(valor)::numeric, 2) FROM (
        SELECT ce.atencion AS valor
        FROM calificaciones_entrega ce
        JOIN entregas e4 ON e4.id_entrega = ce.id_entrega
        WHERE e4.id_repartidor = r.id_repartidor AND ce.atencion IS NOT NULL
        UNION ALL
        SELECT rc.valor_estrellas AS valor
        FROM respuestas_calificacion rc
        JOIN calificaciones_entrega ce ON ce.id_calificacion = rc.id_calificacion
        JOIN entregas e4 ON e4.id_entrega = ce.id_entrega
        WHERE e4.id_repartidor = r.id_repartidor
          AND rc.categoria = 'Repartidor' AND rc.tipo_respuesta = 'ESTRELLAS'
    ) prom)
";

// Busca una entrega ASIGNADA (todavía sin salir, se detecta con
// fecha_inicio IS NULL) cuyo destino esté muy cerca del destino de esta
// entrega nueva — si la encuentra, esta entrega se puede sumar como una
// parada extra de esa misma salida, con el MISMO repartidor y vehículo,
// en vez de exigir un vehículo aparte (ver PATCH 30/30). Solo entra en
// juego si el vehículo que ya tiene esa salida es lo bastante grande para
// la carga de esta entrega nueva, y si le sigue dando tiempo dentro del
// turno del repartidor con el tiempo extra de la parada.
//
// Deliberadamente simple para el alcance de este proyecto: no reordena ni
// recalcula la ruta completa, solo asume que la parada nueva se hace justo
// después de llegar a la primera (tiempo_estimado_minutos de la existente
// + un margen fijo de "bajarse y entregar" configurable). Tampoco limita
// cuántas paradas puede acumular una misma salida.
function buscarAgrupacionCercana(PDO $conexion, ?float $latEntrega, ?float $lonEntrega, ?string $minimoPorCarga, array $config): ?array {
    if ($latEntrega === null || $lonEntrega === null) return null;

    $horarioGeneral = obtenerHorarioGeneralDelivery($conexion);
    $radioKm = $config['delivery_radio_agrupacion_km'];
    $minutosParadaExtra = (int) $config['delivery_minutos_por_parada_extra'];
    $rangoMinimo = $minimoPorCarga !== null ? (RANGO_VEHICULO[$minimoPorCarga] ?? 1) : 1;

    $stmt = $conexion->prepare("
        SELECT e.id_entrega, e.id_repartidor, e.id_vehiculo, v.tipo AS tipo_vehiculo, v.placa,
               e.latitud_entrega, e.longitud_entrega, e.tiempo_estimado_minutos,
               r.nombre, r.hora_inicio_turno, r.hora_fin_turno,
               " . SQL_CALIFICACION_PROMEDIO_REPARTIDOR . " AS calificacion_promedio
        FROM entregas e
        JOIN estado_entrega se ON se.id_estado = e.id_estado
        JOIN repartidores r ON r.id_repartidor = e.id_repartidor
        JOIN vehiculos v ON v.id_vehiculo = e.id_vehiculo
        WHERE se.nombre = 'ASIGNADA'
          AND e.fecha_inicio IS NULL
          AND e.latitud_entrega IS NOT NULL AND e.longitud_entrega IS NOT NULL
          AND e.tiempo_estimado_minutos IS NOT NULL
          AND r.activo = TRUE AND r.estado_laboral = 'ACTIVO'
        ORDER BY e.fecha_asignada ASC
    ");
    $stmt->execute();

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        // El vehículo que ya tiene esa salida debe alcanzar para la carga
        // de la entrega nueva (si no, hace falta un vehículo aparte).
        if ((RANGO_VEHICULO[$c['tipo_vehiculo']] ?? 1) < $rangoMinimo) continue;

        $distEntreDestinos = haversineKm(
            (float) $c['latitud_entrega'], (float) $c['longitud_entrega'],
            $latEntrega, $lonEntrega
        );
        if ($distEntreDestinos > $radioKm) continue;

        $tiempoConParadaExtra = ((int) $c['tiempo_estimado_minutos']) + $minutosParadaExtra;
        $horaInicioEfectiva = $c['hora_inicio_turno'] ?? $horarioGeneral['delivery_hora_inicio'];
        $horaFinEfectiva    = $c['hora_fin_turno'] ?? $horarioGeneral['delivery_hora_fin'];

        $stmtChk = $conexion->prepare("
            SELECT CURRENT_TIME >= :inicio::time
               AND CURRENT_TIME + (:tiempo || ' minutes')::interval <= :fin::time AS cabe
        ");
        $stmtChk->execute([
            ':inicio' => $horaInicioEfectiva,
            ':fin'    => $horaFinEfectiva,
            ':tiempo' => $tiempoConParadaExtra,
        ]);
        if (!filter_var($stmtChk->fetchColumn(), FILTER_VALIDATE_BOOLEAN)) continue;

        return [
            'id_repartidor'                     => (int) $c['id_repartidor'],
            'nombre'                             => $c['nombre'],
            'id_vehiculo'                        => (int) $c['id_vehiculo'],
            'tipo_vehiculo'                      => $c['tipo_vehiculo'],
            'placa'                              => $c['placa'],
            'calificacion_promedio'              => $c['calificacion_promedio'] !== null ? (float) $c['calificacion_promedio'] : null,
            'agrupada'                           => true,
            'id_grupo_entrega'                   => (int) $c['id_entrega'],
            'tiempo_estimado_minutos_sugerido'   => $tiempoConParadaExtra,
        ];
    }

    return null;
}

// Busca un repartidor+vehículo para esta entrega. "Disponible" no
// significa solo "sin nada asignado" — un repartidor con una entrega ya
// ASIGNADA pero con hora acordada LEJANA (ej. son las 9:00 y su próxima
// salida es a las 10:30) igual se puede considerar, siempre que le dé
// tiempo de ir y volver con esta entrega nueva antes de la hora en que
// debe salir para la ya agendada. Eso evita tener repartidores "on hold"
// una hora y media solo porque tienen algo pendiente más tarde.
//
// TODA entrega ASIGNADA tiene una fecha_programada, aunque el cliente no
// haya pedido una hora específica: si no la pidió, procesar_venta.php (y
// _auto_asignar.php cuando sale de la cola) asumen que el repartidor sale
// de inmediato y calculan fecha_programada = ahora + tiempo estimado de
// viaje. O sea, "sin hora acordada" = "hora acordada implícita: ya
// mismo" — no hace falta un caso especial para eso aquí; entra en el
// mismo cálculo de margen de ida y vuelta que una entrega con hora real.
//
// Lo que SÍ bloquea por completo a un repartidor:
//   - Tener una entrega EN_CAMINO (está físicamente en la calle, no
//     puede estar en dos sitios a la vez).
//   - Tener una entrega ASIGNADA sin margen suficiente: se calcula el
//     tiempo de ida y vuelta estimado de ESTA entrega nueva (2× el tiempo
//     de viaje, asumiendo que tiene que volver a la sucursal a buscar lo
//     del próximo pedido) y se exige que quepa completo antes de la hora
//     de salida recomendada del compromiso ya agendado. Si ese compromiso
//     es "de inmediato" (sin hora real pedida por el cliente), su salida
//     recomendada es prácticamente ahora mismo, así que en la práctica no
//     deja margen para meterle nada antes — como debe ser.
//   - Por seguridad, si una entrega ASIGNADA se quedó sin
//     fecha_programada o sin tiempo_estimado_minutos (dato viejo o algún
//     caso raro no contemplado), se trata igual que antes: bloqueo total,
//     mejor pecar de cauteloso que asignar de más.
//   - Estar fuera de turno (ver PATCH 27/27): cada repartidor puede tener
//     su propio hora_inicio_turno/hora_fin_turno; si no tiene uno propio,
//     se usa el horario general de envíos (Configuración > Envíos). No
//     basta con estar dentro de turno ahora mismo — también se exige que
//     le dé tiempo de LLEGAR con esta entrega antes de que se le acabe el
//     turno, para no mandarlo a mitad de camino cuando ya debería estar
//     saliendo.
//
// Antes de buscar un vehículo NUEVO, se intenta agrupar con una salida ya
// en curso que vaya muy cerca (ver buscarAgrupacionCercana / PATCH 30/30).
// También se puede pasar el peso o la cantidad de unidades del pedido
// (PATCH 28/28): eso nunca hace bajar de categoría de vehículo, aunque la
// distancia sola hubiera bastado con algo más chico.
//
// Entre varios candidatos igual de válidos, se prioriza primero por
// CALIFICACIÓN — atención al cliente / amabilidad, tomada de lo que los
// propios clientes calificaron en entregas anteriores — y como
// desempate, al que lleva más tiempo sin una entrega (reparto justo de
// la carga de trabajo). Un repartidor sin calificaciones todavía no
// queda descartado por eso: solo pierde el desempate frente a otro que
// sí tenga buena calificación.
//
// FOR UPDATE SKIP LOCKED evita que dos ventas con delivery procesadas
// casi al mismo tiempo se peleen por el mismo vehículo.
// $paraReservar = true: se usa para asignar de verdad (procesar_venta.php,
// _auto_asignar.php) — bloquea la fila del vehículo con FOR UPDATE SKIP
// LOCKED para que dos asignaciones simultáneas no elijan el mismo.
// $paraReservar = false: solo para mostrarle al cajero una vista previa
// (calcular_costo_envio.php) antes de facturar — sin bloqueo, porque no
// se va a asignar nada todavía y la petición ni siquiera corre dentro de
// una transacción explícita.
function seleccionarRepartidorYVehiculoAutomatico(
    PDO $conexion,
    ?float $km,
    bool $paraReservar = true,
    ?float $pesoKg = null,
    ?int $totalUnidades = null,
    ?float $latEntrega = null,
    ?float $lonEntrega = null
): ?array {
    $config = obtenerConfigDelivery($conexion);
    $horarioGeneral = obtenerHorarioGeneralDelivery($conexion);
    $minimoPorCarga = tipoVehiculoMinimoPorCarga($pesoKg, $totalUnidades, $config);

    // Primero se intenta agrupar con una salida ya en curso que vaya muy
    // cerca (ver PATCH 30/30) — reusar un viaje que ya va a salir es
    // mejor que ocupar un vehículo nuevo. Si no hay nada cerca o no cabe,
    // se sigue con la búsqueda normal de más abajo.
    $agrupacion = buscarAgrupacionCercana($conexion, $latEntrega, $lonEntrega, $minimoPorCarga, $config);
    if ($agrupacion) return $agrupacion;

    // Sin distancia conocida (direcciones sin coordenadas todavía) no se
    // puede decidir por tipo de vehículo — se prueba con el orden más
    // versátil posible en vez de dejar la entrega sin intentar nada.
    $tiposPreferidos = $km !== null ? tiposVehiculoPorDistancia($km, $config) : TIPOS_VEHICULO_VALIDOS;
    // La carga del pedido nunca deja bajar de categoría de vehículo,
    // aunque la distancia sola hubiera bastado con algo más chico.
    $tiposPreferidos = combinarPreferenciaConCarga($tiposPreferidos, $minimoPorCarga);

    foreach ($tiposPreferidos as $tipo) {
        // Cuánto tardaría ir y volver con ESTA entrega, para saber si le
        // cabe a un repartidor que ya tiene algo agendado más tarde.
        $tiempoEstimadoNuevo = $km !== null ? calcularTiempoEstimadoMinutos($km, $tipo, $config) : null;
        $margenIdaYVuelta = $tiempoEstimadoNuevo !== null ? ($tiempoEstimadoNuevo * 2) : 0;
        // Si no hay km, no se puede saber cuánto tardaría — se exige al
        // menos que esté dentro de turno AHORA MISMO, sin poder validar
        // que le dé tiempo de terminar antes de que se acabe su turno.
        $tiempoParaFinTurno = $tiempoEstimadoNuevo ?? 0;

        $stmt = $conexion->prepare("
            SELECT v.id_vehiculo, v.tipo, v.placa, r.id_repartidor, r.nombre,
                   (SELECT MAX(e3.fecha_asignada) FROM entregas e3
                    WHERE e3.id_repartidor = r.id_repartidor
                      AND e3.fecha_pedido > CURRENT_DATE - INTERVAL '1 day') AS ultima_entrega,
                   " . SQL_CALIFICACION_PROMEDIO_REPARTIDOR . " AS calificacion_promedio
            FROM vehiculos v
            JOIN repartidor_habilidad rh
                ON LOWER(TRIM(rh.tipo_vehiculo)) = LOWER(TRIM(v.tipo))
            JOIN repartidores r ON r.id_repartidor = rh.id_repartidor
            WHERE v.activo = TRUE AND v.estado = 'DISPONIBLE'
              AND LOWER(TRIM(v.tipo)) = LOWER(TRIM(:tipo))
              AND r.activo = TRUE AND r.estado_laboral = 'ACTIVO'
              -- Turno laboral: el propio del repartidor si lo tiene, si no
              -- el horario general de envíos. Tiene que estar dentro de
              -- turno ahora Y que le dé tiempo de LLEGAR con esta entrega
              -- antes de que se le acabe el turno — no soporta turnos que
              -- crucen la medianoche (ver comentario en
              -- obtenerHorarioGeneralDelivery).
              AND CURRENT_TIME >= COALESCE(r.hora_inicio_turno, :horario_inicio::time)
              AND CURRENT_TIME + (:tiempo_fin_turno || ' minutes')::interval
                  <= COALESCE(r.hora_fin_turno, :horario_fin::time)
              AND NOT EXISTS (
                  SELECT 1 FROM entregas e2
                  JOIN estado_entrega se2 ON se2.id_estado = e2.id_estado
                  WHERE e2.id_repartidor = r.id_repartidor
                    AND (
                        se2.nombre IN ('PENDIENTE','EN_CAMINO')
                        OR (
                            se2.nombre = 'ASIGNADA' AND (
                                e2.fecha_programada IS NULL
                                OR e2.tiempo_estimado_minutos IS NULL
                                OR NOW() + (:margen || ' minutes')::interval
                                   > e2.fecha_programada - (e2.tiempo_estimado_minutos || ' minutes')::interval
                            )
                        )
                    )
              )
            ORDER BY calificacion_promedio DESC NULLS LAST, ultima_entrega ASC NULLS FIRST, r.id_repartidor ASC
            LIMIT 1
            " . ($paraReservar ? "FOR UPDATE OF v SKIP LOCKED" : "") . "
        ");
        $stmt->execute([
            ':tipo' => $tipo,
            ':margen' => $margenIdaYVuelta,
            ':horario_inicio' => $horarioGeneral['delivery_hora_inicio'],
            ':horario_fin' => $horarioGeneral['delivery_hora_fin'],
            ':tiempo_fin_turno' => $tiempoParaFinTurno,
        ]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($match) {
            return [
                'id_repartidor'         => (int) $match['id_repartidor'],
                'nombre'                => $match['nombre'],
                'id_vehiculo'           => (int) $match['id_vehiculo'],
                'tipo_vehiculo'         => $match['tipo'],
                'placa'                 => $match['placa'],
                'calificacion_promedio' => $match['calificacion_promedio'] !== null ? (float) $match['calificacion_promedio'] : null,
                'agrupada'              => false,
            ];
        }
    }

    return null; // nadie disponible ahora mismo — la entrega se va a la cola
}

// Cuando NADIE está disponible ahora mismo, esto busca — entre los
// repartidores que SÍ tienen (o podrían tener) un vehículo del tipo que
// hace falta pero están ocupados con otra entrega — cuál quedaría libre
// primero, usando el mismo cálculo de tiempo estimado por distancia que
// ya se usa para la hora de llegada. Es solo informativo (para que el
// cajero sepa si conviene esperar un momento en vez de mandar a la cola
// a ciegas); no reserva ni asigna nada.
function estimarProximaDisponibilidad(PDO $conexion, ?float $km, ?float $pesoKg = null, ?int $totalUnidades = null): ?array {
    $config = obtenerConfigDelivery($conexion);
    $horarioGeneral = obtenerHorarioGeneralDelivery($conexion);
    $tiposPreferidos = $km !== null ? tiposVehiculoPorDistancia($km, $config) : TIPOS_VEHICULO_VALIDOS;
    $tiposPreferidos = combinarPreferenciaConCarga($tiposPreferidos, tipoVehiculoMinimoPorCarga($pesoKg, $totalUnidades, $config));
    $placeholders = implode(',', array_fill(0, count($tiposPreferidos), '?'));

    $stmt = $conexion->prepare("
        SELECT r.nombre,
            CASE
                WHEN e.fecha_inicio IS NOT NULL AND e.tiempo_estimado_minutos IS NOT NULL THEN
                    e.fecha_inicio + (e.tiempo_estimado_minutos || ' minutes')::interval
                WHEN e.fecha_inicio IS NULL AND e.fecha_programada IS NOT NULL THEN
                    e.fecha_programada
                WHEN e.fecha_inicio IS NULL AND e.tiempo_estimado_minutos IS NOT NULL THEN
                    e.fecha_asignada + (e.tiempo_estimado_minutos || ' minutes')::interval
            END AS hora_libre
        FROM repartidores r
        JOIN repartidor_habilidad rh ON rh.id_repartidor = r.id_repartidor AND rh.tipo_vehiculo IN ($placeholders)
        JOIN entregas e ON e.id_repartidor = r.id_repartidor
        JOIN estado_entrega se ON se.id_estado = e.id_estado AND se.nombre IN ('PENDIENTE','ASIGNADA','EN_CAMINO')
        WHERE r.activo = TRUE AND r.estado_laboral = 'ACTIVO'
          -- Si ya está fuera de turno, no tiene sentido ofrecerlo como
          -- 'próxima disponibilidad' — hoy ya no va a hacer más entregas.
          AND CURRENT_TIME >= COALESCE(r.hora_inicio_turno, ?::time)
          AND CURRENT_TIME <= COALESCE(r.hora_fin_turno, ?::time)
        ORDER BY hora_libre ASC NULLS LAST
        LIMIT 1
    ");
    $stmt->execute(array_merge($tiposPreferidos, [$horarioGeneral['delivery_hora_inicio'], $horarioGeneral['delivery_hora_fin']]));
    $match = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$match || !$match['hora_libre']) return null;

    return [
        'repartidor_nombre'          => $match['nombre'],
        'hora_estimada_disponible'   => $match['hora_libre'],
    ];
}
