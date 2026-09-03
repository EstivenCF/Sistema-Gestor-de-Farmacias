<?php
/**
 * Tarea 5 - Proceso estratégico: Gestión Estratégica de Vencimientos de Medicamentos
 * MEJORA FINAL: motor de redistribución inteligente.
 *
 * Esta librería NO repite lógica de riesgo_vencimiento_lib.php: la reutiliza
 * (obtenerUmbralesVencimiento, calcularIRV, obtenerIntervalosAccion,
 * determinarAccionPorIntervalo, calcularDistanciaKm, calcularDemandaHistorica,
 * estimarProbabilidadVenta) y agrega, encima, dos cosas nuevas:
 *
 *   1) evaluarSucursalesDestino(): pondera y ordena TODAS las sucursales
 *      candidatas a recibir una transferencia. Cada sucursal tiene su propio
 *      perfil de pesos y de sentido (más/menos) por criterio; no se usa la
 *      misma ponderación para Hatuey que para Principal.
 *   2) generarRecomendacionEstrategica(): integra esa puntuación con los
 *      intervalos configurables de % de venta y el tiempo restante de
 *      vencimiento, para producir UNA recomendación final explicada
 *      ("Transferir a Sucursal X — 87% de conveniencia", o Promoción /
 *      Devolución / Mantener, según corresponda).
 *
 * Requiere que riesgo_vencimiento_lib.php ya esté incluido (y por lo tanto
 * $conexion / PDO ya exista).
 */
/**
 * Pesos por defecto de cada criterio de la puntuación ponderada de
 * sucursales destino. Deben sumar 100. Se usan solo si todavía no hay nada
 * configurado en configuracion_sistema (clave 'venc_pesos_transferencia') o
 * si lo guardado no pasa validarPesosTransferencia().
 */
function pesosTransferenciaPorDefecto(): array
{
    return [
        'rotacion_destino'              => 25, // IRV (% de venta) de la sucursal destino
        'demanda_historica'              => 15, // volumen de venta histórico en destino
        'cantidad_disponible_destino'    => 10, // entre menos stock ya tenga, más "necesidad"
        'tiempo_restante_vencimiento'    => 15, // margen real (días restantes - tiempo de tránsito)
        'distancia'                      => 15, // entre más cerca, mejor
        'costo_transporte'               => 10, // costo estimado del flete vs. valor en riesgo
        'probabilidad_venta_antes_vencer' => 10, // proyección de venta del lote completo en destino
    ];
}
/**
 * Sentido por defecto de cada criterio: "mayor" = más es mejor para ESA
 * sucursal, "menor" = menos es mejor. No es igual para todas: Hatuey puede
 * priorizar mucho inventario y poco IRV, y Principal priorizar IRV alto.
 */
function preferenciasCriterioPorDefecto(): array
{
    return [
        'rotacion_destino' => 'mayor',
        'demanda_historica' => 'mayor',
        'cantidad_disponible_destino' => 'menor',
        'tiempo_restante_vencimiento' => 'mayor',
        'distancia' => 'menor',
        'costo_transporte' => 'menor',
        'probabilidad_venta_antes_vencer' => 'mayor',
    ];
}
function etiquetasCriterioTransferencia(): array
{
    return [
        'rotacion_destino' => 'Rotación de venta (IRV)',
        'demanda_historica' => 'Demanda histórica',
        'cantidad_disponible_destino' => 'Inventario ya disponible',
        'tiempo_restante_vencimiento' => 'Margen de tiempo antes de vencer',
        'distancia' => 'Distancia entre sucursales',
        'costo_transporte' => 'Costo de transporte',
        'probabilidad_venta_antes_vencer' => 'Probabilidad de vender a tiempo',
    ];
}

function evaluarLogisticaDisponible(PDO $conexion, int $idSucursalOrigen, int $idSucursalDestino, int $cantidad): array
{
    // Obtener coordenadas (solo para calcular distancia)
    $stmt = $conexion->prepare("SELECT latitud, longitud FROM sucursales WHERE id_sucursal = :id");
    $stmt->execute([':id' => $idSucursalOrigen]);
    $origen = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->execute([':id' => $idSucursalDestino]);
    $destino = $stmt->fetch(PDO::FETCH_ASSOC);
    $distanciaKm = null;
    
    if ($origen && $destino && $origen['latitud'] !== null && $origen['longitud'] !== null
        && $destino['latitud'] !== null && $destino['longitud'] !== null) {
        $distanciaKm = calcularDistanciaKm(
            [(float)$origen['latitud'], (float)$origen['longitud']],
            [(float)$destino['latitud'], (float)$destino['longitud']]
        );
    }

    // Si no hay coordenadas, usar distancia por defecto
    if ($distanciaKm === null) {
        $distanciaKm = 10;
    }

    // Obtener configuración de costos por tipo de combustible
    $config = [];
    try {
        $configStmt = $conexion->query("
            SELECT clave, valor FROM configuracion_sistema
            WHERE clave IN (
                'costo_galon_gasolina_regular',
                'costo_galon_gasolina_premium',
                'costo_galon_diesel'
            )
        ");
        $config = $configStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        // Valores por defecto
    }

    $costoGasolinaRegular = (float)($config['costo_galon_gasolina_regular'] ?? 250);
    $costoGasolinaPremium = (float)($config['costo_galon_gasolina_premium'] ?? 280);
    $costoDiesel = (float)($config['costo_galon_diesel'] ?? 200);

    // ================================================================
    // CONSULTA CORREGIDA - Mostrar TODOS los vehículos disponibles
    // ================================================================
    $stmt = $conexion->prepare("
        SELECT 
            v.id_vehiculo,
            v.tipo,
            v.placa,
            v.estado,
            COALESCE(v.capacidad_carga, 0) AS capacidad_carga,
            COALESCE(v.tipo_combustible, 'GASOLINA_REGULAR') AS tipo_combustible,
            COALESCE(v.rendimiento_km_por_galon, 
                CASE 
                    WHEN v.tipo ILIKE '%moto%' OR v.tipo = 'Motocicleta' THEN 35
                    WHEN v.tipo ILIKE '%carro%' OR v.tipo = 'Carro' THEN 15
                    WHEN v.tipo ILIKE '%camion%' OR v.tipo = 'Camión' THEN 8
                    ELSE 20
                END
            ) AS rendimiento_km_por_galon,
            COALESCE(v.costo_galon_combustible,
                CASE 
                    WHEN v.tipo_combustible = 'DIESEL' THEN :costo_diesel::numeric
                    WHEN v.tipo_combustible = 'GASOLINA_PREMIUM' THEN :costo_premium::numeric
                    ELSE :costo_regular::numeric
                END
            ) AS costo_galon_combustible,
            CASE 
                WHEN v.tipo ILIKE '%moto%' OR v.tipo = 'Motocicleta' THEN 50
                WHEN v.tipo ILIKE '%carro%' OR v.tipo = 'Carro' THEN 40
                WHEN v.tipo ILIKE '%camion%' OR v.tipo = 'Camión' THEN 30
                ELSE 40
            END AS velocidad_promedio_kmh,
            r.id_repartidor,
            r.nombre AS repartidor_nombre,
            r.activo,
            r.estado_laboral
        FROM vehiculos v
        LEFT JOIN repartidores r ON r.id_repartidor = v.id_repartidor
        WHERE 
            v.estado = 'DISPONIBLE'
        ORDER BY 
            CASE 
                WHEN v.tipo ILIKE '%moto%' OR v.tipo = 'Motocicleta' THEN 1
                WHEN v.tipo ILIKE '%carro%' OR v.tipo = 'Carro' THEN 2
                WHEN v.tipo ILIKE '%camion%' OR v.tipo = 'Camión' THEN 3
                ELSE 4
            END
    ");
    
    $stmt->execute([
        ':costo_regular' => $costoGasolinaRegular,
        ':costo_premium' => $costoGasolinaPremium,
        ':costo_diesel' => $costoDiesel
    ]);
    
    $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear resultados
    $vehiculosFormateados = [];
    foreach ($vehiculos as $v) {
        $rendimiento = (float)($v['rendimiento_km_por_galon'] ?? 20);
        $combustible = round($distanciaKm / max(1, $rendimiento), 2);
        $costoGalon = (float)($v['costo_galon_combustible'] ?? 250);
        $costo = round($combustible * $costoGalon, 2);
        $velocidad = (float)($v['velocidad_promedio_kmh'] ?? 40);
        $tiempoMinutos = (int)round(($distanciaKm / max(1, $velocidad)) * 60);
        $capacidadCarga = (int)$v['capacidad_carga'];
        $capacidadSuficiente = $capacidadCarga === 0 || $capacidadCarga >= $cantidad;
        
        $etiquetaCombustible = [
            'DIESEL' => 'Diésel',
            'GASOLINA_PREMIUM' => 'Gasolina Premium',
            'GASOLINA_REGULAR' => 'Gasolina Regular'
        ][$v['tipo_combustible']] ?? $v['tipo_combustible'];

        $vehiculosFormateados[] = [
            'id_vehiculo' => (int)$v['id_vehiculo'],
            'id_repartidor' => $v['id_repartidor'] ? (int)$v['id_repartidor'] : null,
            'tipo' => $v['tipo'] ?? 'Sin tipo',
            'placa' => $v['placa'] ?? '',
            'repartidor_nombre' => $v['repartidor_nombre'] ?? 'Sin asignar',
            'capacidad_carga' => $capacidadCarga,
            'capacidad_suficiente' => $capacidadSuficiente,
            'tipo_combustible' => $v['tipo_combustible'] ?? 'GASOLINA_REGULAR',
            'etiqueta_combustible' => $etiquetaCombustible,
            'costo_galon_combustible' => $costoGalon,
            'combustible_estimado_gal' => $combustible,
            'costo_combustible_estimado' => $costo,
            'tiempo_estimado_minutos' => $tiempoMinutos,
            'velocidad_promedio_kmh' => $velocidad,
            'rendimiento_km_por_galon' => $rendimiento,
        ];
    }

    return [
        'hay_logistica_disponible' => !empty($vehiculosFormateados),
        'vehiculos' => $vehiculosFormateados,
        'distancia_km' => round($distanciaKm, 2),
        'tiempo_estimado_minutos' => !empty($vehiculosFormateados) ? $vehiculosFormateados[0]['tiempo_estimado_minutos'] : null,
    ];
}
/**
 * Valida que los pesos sean coherentes: las 7 claves esperadas, todas
 * numéricas, no negativas, y que sumen 100 (con 0.5 de tolerancia por
 * redondeos). Igual que validarIntervalosAccion(), devuelve el detalle de
 * errores para poder mostrarlo en Configuración, no solo rechazar en silencio.
 */
function validarPesosTransferencia(array $pesos): array
{
    $errores = [];
    $clavesEsperadas = array_keys(pesosTransferenciaPorDefecto());
    foreach ($clavesEsperadas as $clave) {
        if (!array_key_exists($clave, $pesos)) {
            $errores[] = "Falta el peso del criterio '$clave'.";
            continue;
        }
        if (!is_numeric($pesos[$clave]) || (float)$pesos[$clave] < 0) {
            $errores[] = "El peso de '$clave' debe ser un número mayor o igual a 0.";
        }
    }
    if (!empty($errores)) {
        return ['valido' => false, 'errores' => $errores];
    }
    $suma = array_sum(array_map('floatval', array_intersect_key($pesos, array_flip($clavesEsperadas))));
    if (abs($suma - 100.0) > 0.5) {
        $errores[] = "Los pesos deben sumar 100% (suman " . round($suma, 2) . "%).";
    }
    return ['valido' => empty($errores), 'errores' => $errores];
}
/**
 * Lee los pesos configurados (clave 'venc_pesos_transferencia', JSON). Si no
 * existen, no se pueden decodificar, o no pasan validarPesosTransferencia(),
 * se usa pesosTransferenciaPorDefecto().
 */
function obtenerPesosTransferencia(PDO $conexion): array
{
    try {
        $stmt = $conexion->prepare("SELECT valor FROM configuracion_sistema WHERE clave = 'venc_pesos_transferencia'");
        $stmt->execute();
        $valor = $stmt->fetchColumn();
        if ($valor) {
            $decodificado = json_decode($valor, true);
            if (is_array($decodificado)) {
                $validacion = validarPesosTransferencia($decodificado);
                if ($validacion['valido']) {
                    return $decodificado;
                }
            }
        }
    } catch (PDOException $e) {
        // Sigue con el valor por defecto.
    }
    return pesosTransferenciaPorDefecto();
}
/**
 * Perfiles de ponderación por sucursal (clave 'venc_pesos_por_sucursal').
 * Formato: { "id_sucursal": { "pesos": {...}, "preferencias": {...} } }
 * Si una sucursal no tiene perfil, se usan los pesos globales.
 */
function obtenerMapaCriteriosPorSucursal(PDO $conexion): array
{
    try {
        $stmt = $conexion->prepare("SELECT valor FROM configuracion_sistema WHERE clave = 'venc_pesos_por_sucursal'");
        $stmt->execute();
        $valor = $stmt->fetchColumn();
        if ($valor) {
            $decodificado = json_decode($valor, true);
            if (is_array($decodificado)) {
                return $decodificado;
            }
        }
    } catch (PDOException $e) {
        // Sigue con mapa vacío: todas usan el perfil global.
    }
    return [];
}
function criteriosEfectivosSucursal(int $idSucursal, array $mapaPorSucursal, array $pesosDefault): array
{
    $cfg = $mapaPorSucursal[(string)$idSucursal] ?? $mapaPorSucursal[$idSucursal] ?? [];
    $pesos = is_array($cfg['pesos'] ?? null) ? $cfg['pesos'] : $pesosDefault;
    $validacion = validarPesosTransferencia($pesos);
    if (!$validacion['valido']) {
        $pesos = $pesosDefault;
    }
    $preferencias = preferenciasCriterioPorDefecto();
    if (is_array($cfg['preferencias'] ?? null)) {
        foreach ($preferencias as $criterio => $sentidoDefault) {
            $sentido = $cfg['preferencias'][$criterio] ?? $sentidoDefault;
            $preferencias[$criterio] = ($sentido === 'menor') ? 'menor' : 'mayor';
        }
    }
    return [
        'pesos' => $pesos,
        'preferencias' => $preferencias,
        'personalizado' => !empty($cfg['pesos']),
    ];
}
/**
 * Normaliza un valor crudo a una puntuación 0-100 dentro de un rango
 * [$min, $max]. $invertir = true cuando "menos es mejor" (ej. distancia,
 * costo, cantidad ya disponible en destino).
 */
function normalizarPuntuacion(float $valor, float $min, float $max, bool $invertir = false): float
{
    if ($max <= $min) {
        return 50.0; // sin variación entre candidatos: no se favorece a nadie
    }
    $valorAcotado = max($min, min($max, $valor));
    $porcentaje = (($valorAcotado - $min) / ($max - $min)) * 100;
    return $invertir ? round(100 - $porcentaje, 2) : round($porcentaje, 2);
}
/**
 * Evalúa y pondera TODAS las sucursales candidatas a recibir una
 * transferencia del lote, usando 7 criterios con peso configurable. Cada
 * criterio se normaliza 0-100 RELATIVO a los demás candidatos evaluados en
 * esta misma corrida (así, "la mejor" siempre existe aunque los valores
 * absolutos sean modestos), excepto tiempo/costo que también se comparan
 * contra límites absolutos (umbrales) para detectar transferencias que no
 * tienen sentido aunque ganen el ranking relativo.
 *
 * $loteDetalle: resultado de evaluarLoteDetalle() (ya trae rotacion_por_sucursal).
 * Devuelve la lista de candidatos ordenada de mayor a menor puntuación.
 */
function evaluarSucursalesDestino(PDO $conexion, array $loteDetalle, int $idSucursalOrigen, int $cantidadATransferir, array $umbrales, array $pesos): array
{
    $idMedicamento = (int)$loteDetalle['id_medicamento'];
    $diasRestantes = (int)$loteDetalle['dias_restantes'];
    $periodoIrv = (int)$umbrales['venc_irv_periodo_dias'];
    $costoUnidadKm = (float)$umbrales['venc_costo_transporte_estimado_unidad'];
    $velocidadKmh = max(1.0, (float)$umbrales['venc_velocidad_promedio_kmh']);
    $mapaCriterios = obtenerMapaCriteriosPorSucursal($conexion);
    $coordOrigen = obtenerCoordenadasSucursal($conexion, $idSucursalOrigen);
    // 1) Recolectar los datos crudos de cada candidato (todas las sucursales
    //    activas, no solo las que ya tienen stock del medicamento: una
    //    sucursal sin stock actual puede seguir siendo un buen destino si
    //    tiene buena demanda histórica).
    $stmt = $conexion->prepare("SELECT id_sucursal, nombre FROM sucursales WHERE estado = true AND id_sucursal != :origen ORDER BY nombre");
    $stmt->execute([':origen' => $idSucursalOrigen]);
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $candidatos = [];
    foreach ($sucursales as $suc) {
        $idSuc = (int)$suc['id_sucursal'];
        $irv = calcularIRV($conexion, $idMedicamento, $idSuc, $periodoIrv);
        $demanda = calcularDemandaHistorica($conexion, $idMedicamento, $idSuc);
        $stmtStock = $conexion->prepare("SELECT COALESCE(SUM(cantidad), 0) FROM inventario i JOIN lotes l ON i.id_lote = l.id_lote WHERE l.id_medicamento = :med AND i.id_sucursal = :suc");
        $stmtStock->execute([':med' => $idMedicamento, ':suc' => $idSuc]);
        $stockActual = (float)$stmtStock->fetchColumn();
        $coordDestino = obtenerCoordenadasSucursal($conexion, $idSuc);
        $distanciaKm = ($coordOrigen && $coordDestino) ? calcularDistanciaKm($coordOrigen, $coordDestino) : null;
        $horasTransito = $distanciaKm !== null ? ($distanciaKm / $velocidadKmh) : null;
        $diasTransito = $horasTransito !== null ? $horasTransito / 24 : null;
        $margenDias = $diasTransito !== null ? ($diasRestantes - $diasTransito) : null;
        $costoTransporteEstimado = $distanciaKm !== null
            ? round($distanciaKm * $cantidadATransferir * $costoUnidadKm, 2)
            : null;
        $probabilidadVenta = estimarProbabilidadVenta($irv, $diasRestantes, $periodoIrv);
        $candidatos[] = [
            'id_sucursal' => $idSuc,
            'sucursal_nombre' => $suc['nombre'],
            'irv' => $irv,
            'demanda_historica' => $demanda,
            'stock_actual' => $stockActual,
            'distancia_km' => $distanciaKm,
            'dias_transito_estimado' => $diasTransito !== null ? round($diasTransito, 2) : null,
            'margen_dias' => $margenDias !== null ? round($margenDias, 2) : null,
            'costo_transporte_estimado' => $costoTransporteEstimado,
            'probabilidad_venta_antes_vencer' => $probabilidadVenta,
        ];
    }
    if (empty($candidatos)) {
        return [];
    }
    // 2) Normalizar cada criterio 0-100 RELATIVO al conjunto de candidatos.
    $irvValores = array_map(fn($c) => $c['irv'] ?? 0.0, $candidatos);
    $demandaValores = array_map(fn($c) => $c['demanda_historica'], $candidatos);
    $stockValores = array_map(fn($c) => $c['stock_actual'], $candidatos);
    $distanciaValores = array_filter(array_map(fn($c) => $c['distancia_km'], $candidatos), fn($v) => $v !== null);
    $costoValores = array_filter(array_map(fn($c) => $c['costo_transporte_estimado'], $candidatos), fn($v) => $v !== null);
    $minIrv = min($irvValores); $maxIrv = max($irvValores);
    $minDemanda = min($demandaValores); $maxDemanda = max($demandaValores);
    $minStock = min($stockValores); $maxStock = max($stockValores);
    $minDist = count($distanciaValores) ? min($distanciaValores) : 0;
    $maxDist = count($distanciaValores) ? max($distanciaValores) : 0;
    $minCosto = count($costoValores) ? min($costoValores) : 0;
    $maxCosto = count($costoValores) ? max($costoValores) : 0;
    $valorEnRiesgo = (float)($loteDetalle['valor_en_riesgo'] ?? 0);
    $topeCostoPct = (float)$umbrales['venc_max_costo_transporte_pct_valor'];
    foreach ($candidatos as &$c) {
        // Puntajes CRUDOS: más valor del indicador = más puntos. El sentido
        // (si esa sucursal prefiere más o menos) se aplica después, con el
        // perfil de esa sucursal, no con una regla única para todas.
        $puntajesCrudos = [];
        $puntajesCrudos['rotacion_destino'] = normalizarPuntuacion((float)($c['irv'] ?? 0.0), $minIrv, $maxIrv);
        $puntajesCrudos['demanda_historica'] = normalizarPuntuacion($c['demanda_historica'], $minDemanda, $maxDemanda);
        $puntajesCrudos['cantidad_disponible_destino'] = normalizarPuntuacion($c['stock_actual'], $minStock, $maxStock);
        if ($c['margen_dias'] === null) {
            $puntajesCrudos['tiempo_restante_vencimiento'] = 50.0;
        } elseif ($c['margen_dias'] <= 0) {
            $puntajesCrudos['tiempo_restante_vencimiento'] = 0.0;
        } else {
            $umbralModerado = max(1, (int)$umbrales['venc_umbral_dias_moderado']);
            $puntajesCrudos['tiempo_restante_vencimiento'] = round(min(100, ($c['margen_dias'] / $umbralModerado) * 100), 2);
        }
        $puntajesCrudos['distancia'] = $c['distancia_km'] === null ? 50.0 : normalizarPuntuacion($c['distancia_km'], $minDist, $maxDist);
        $puntajesCrudos['costo_transporte'] = $c['costo_transporte_estimado'] === null ? 50.0 : normalizarPuntuacion($c['costo_transporte_estimado'], $minCosto, $maxCosto);
        $puntajesCrudos['probabilidad_venta_antes_vencer'] = (float)$c['probabilidad_venta_antes_vencer'];
        $perfil = criteriosEfectivosSucursal((int)$c['id_sucursal'], $mapaCriterios, $pesos);
        $pesosSucursal = $perfil['pesos'];
        $preferencias = $perfil['preferencias'];
        $puntajes = [];
        $contribuciones = [];
        $puntuacionFinal = 0.0;
        foreach ($pesosSucursal as $criterio => $peso) {
            $crudo = (float)($puntajesCrudos[$criterio] ?? 0);
            $ajustado = (($preferencias[$criterio] ?? 'mayor') === 'menor')
                ? round(100 - $crudo, 2)
                : $crudo;
            $puntajes[$criterio] = $ajustado;
            $contribucion = $ajustado * ((float)$peso / 100);
            $contribuciones[$criterio] = round($contribucion, 2);
            $puntuacionFinal += $contribucion;
        }
        $c['puntajes_crudos'] = $puntajesCrudos;
        $c['puntajes_por_criterio'] = $puntajes;
        $c['contribuciones_por_criterio'] = $contribuciones;
        $c['pesos_aplicados'] = $pesosSucursal;
        $c['preferencias_aplicadas'] = $preferencias;
        $c['perfil_personalizado'] = $perfil['personalizado'];
        $c['puntuacion'] = round($puntuacionFinal, 2);
        // 4) Viabilidad económica y de tiempo: independiente de qué tan bien
        //    puntúe relativo a los demás, se marca explícitamente si NO tiene
        //    sentido económico o de tiempo, para no recomendar algo absurdo
        //    solo porque ganó el ranking relativo.
        $c['viable_economicamente'] = true;
        $c['motivo_no_viable'] = null;
        if ($c['costo_transporte_estimado'] !== null && $valorEnRiesgo > 0) {
            $pctCosto = ($c['costo_transporte_estimado'] / $valorEnRiesgo) * 100;
            $c['costo_transporte_pct_valor'] = round($pctCosto, 2);
            if ($pctCosto > $topeCostoPct) {
                $c['viable_economicamente'] = false;
                $c['motivo_no_viable'] = "El flete estimado (RD$" . number_format($c['costo_transporte_estimado'], 2) . ") supera el {$topeCostoPct}% del valor en riesgo.";
            }
        } else {
            $c['costo_transporte_pct_valor'] = null;
        }
        if ($c['margen_dias'] !== null && $c['margen_dias'] <= 0) {
            $c['viable_economicamente'] = false; // reutiliza la misma bandera: "no conviene", sea por plata o por tiempo
            $c['motivo_no_viable'] = ($c['motivo_no_viable'] ? $c['motivo_no_viable'] . ' Además, ' : '') . 'el lote llegaría vencido o sin margen real de venta (tiempo de tránsito estimado mayor a los días restantes).';
        }
    }
    unset($c);
    usort($candidatos, fn($a, $b) => $b['puntuacion'] <=> $a['puntuacion']);
    return $candidatos;
}
/**
 * Construye una explicación breve y legible de por qué una sucursal quedó
 * en el primer lugar, señalando los 2 criterios que más pesaron a su favor.
 */
function explicarMejorSucursal(array $candidato, array $pesos): string
{
    $pesosAplicados = $candidato['pesos_aplicados'] ?? $pesos;
    $preferencias = $candidato['preferencias_aplicadas'] ?? preferenciasCriterioPorDefecto();
    $etiquetas = etiquetasCriterioTransferencia();
    $contribuciones = $candidato['contribuciones_por_criterio'] ?? [];
    if (!$contribuciones) {
        foreach ($pesosAplicados as $criterio => $peso) {
            $puntaje = $candidato['puntajes_por_criterio'][$criterio] ?? 0;
            $contribuciones[$criterio] = $puntaje * ((float)$peso / 100);
        }
    }
    arsort($contribuciones);
    $top = array_slice(array_keys($contribuciones), 0, 2);
    $motivos = [];
    foreach ($top as $criterio) {
        $nombre = $etiquetas[$criterio] ?? $criterio;
        $sentido = ($preferencias[$criterio] ?? 'mayor') === 'menor' ? 'prioriza menos' : 'prioriza más';
        $peso = (float)($pesosAplicados[$criterio] ?? 0);
        $motivos[] = "{$nombre} ({$sentido}, peso {$peso}%)";
    }
    $origenPerfil = !empty($candidato['perfil_personalizado'])
        ? 'según el perfil de criterios de esta sucursal'
        : 'según los pesos por defecto';
    return implode(' y ', $motivos) . ' — ' . $origenPerfil;
}
/**
 * DECISIÓN ESTRATÉGICA FINAL. Integra:
 *   - % de venta (IRV origen) + intervalos configurables  -> acción base
 *   - puntuación ponderada de sucursales destino            -> mejor candidata
 *   - tiempo restante de vencimiento                        -> ya incorporado
 *     tanto en el nivel de riesgo (CRITICO/MODERADO/BAJO) como en el
 *     criterio 'tiempo_restante_vencimiento' de la puntuación ponderada.
 *
 * Devuelve una recomendación única, explicada, con el ranking completo de
 * sucursales disponible para mostrar transparencia (no solo el resultado).
 */
function generarRecomendacionEstrategica(PDO $conexion, array $loteDetalle, int $idSucursalOrigen, array $umbrales): array
{
    $intervalos = obtenerIntervalosAccion($conexion);
    $pesos = obtenerPesosTransferencia($conexion);
    $tramo = determinarAccionPorIntervalo($loteDetalle['irv_origen'], $intervalos);
    $resultado = [
        'intervalo_aplicado' => $tramo,
        'accion_recomendada' => null,
        'etiqueta' => null,
        'conveniencia' => null,
        'motivos' => [],
        'advertencias' => [],
        'sucursal_recomendada' => null,
        'ranking_sucursales' => [],
        'pesos_utilizados' => $pesos,
        'pesos_por_sucursal' => obtenerMapaCriteriosPorSucursal($conexion),
    ];
    // Caso sin datos suficientes: no se inventa una recomendación, igual que
    // el resto del proceso ya hace con 'sin_datos_en_red'.
    if ($tramo === null) {
        $resultado['accion_recomendada'] = 'SIN_DATOS_SUFICIENTES';
        $resultado['etiqueta'] = 'Sin historial de ventas suficiente para recomendar automáticamente';
        $resultado['motivos'][] = 'Este medicamento no tiene ventas registradas en esta sucursal en el período configurado (' . $umbrales['venc_irv_periodo_dias'] . ' días), así que no se puede calcular su % de venta.';
        return $resultado;
    }
    $accionBase = $tramo['accion'] ?? 'PROMOCION';
    $minTramo = $tramo['min'] ?? 0;
    $maxTramo = $tramo['max'] ?? 100;
    $etiquetaTramo = $tramo['etiqueta'] ?? etiquetaAccion($accionBase);
    $resultado['motivos'][] = "% de venta (IRV) actual: {$loteDetalle['irv_origen']}%, dentro del tramo {$minTramo}%-{$maxTramo}% ({$etiquetaTramo}).";
    $resultado['motivos'][] = "Nivel de riesgo por vencimiento: {$loteDetalle['nivel_riesgo']} ({$loteDetalle['dias_restantes']} días restantes).";
    // La redistribución (venga como tramo base, o como respaldo si la
    // devolución/promoción no tuviera destino mejor) siempre se evalúa con
    // el motor ponderado, no solo cuando el tramo la sugiere directamente.
    $ranking = evaluarSucursalesDestino($conexion, $loteDetalle, $idSucursalOrigen, (int)$loteDetalle['cantidad'], $umbrales, $pesos);
    $resultado['ranking_sucursales'] = $ranking;
    $mejorViable = null;
    foreach ($ranking as $c) {
        if ($c['viable_economicamente']) {
            $mejorViable = $c;
            break;
        }
    }
    $umbralConveniencia = (float)$umbrales['venc_umbral_conveniencia_minima'];
    $hayDestinoConveniente = $mejorViable !== null && $mejorViable['puntuacion'] >= $umbralConveniencia;
    if ($accionBase === 'REDISTRIBUCION') {
        if ($hayDestinoConveniente) {
            $resultado['accion_recomendada'] = 'REDISTRIBUCION';
            $resultado['sucursal_recomendada'] = $mejorViable;
            $resultado['conveniencia'] = $mejorViable['puntuacion'];
            $resultado['etiqueta'] = "Transferir a {$mejorViable['sucursal_nombre']} — {$mejorViable['puntuacion']}% de conveniencia";
            $resultado['motivos'][] = 'Sucursal seleccionada por ' . explicarMejorSucursal($mejorViable, $pesos) . '.';
        } else {
            // Degrada a la siguiente acción disponible en los intervalos
            // (normalmente devolución al proveedor), en vez de forzar una
            // transferencia que ninguna sucursal justifica.
            $siguiente = obtenerAccionDegradada($intervalos, $accionBase);
            $resultado['accion_recomendada'] = $siguiente['accion'];
            $resultado['etiqueta'] = etiquetaAccion($siguiente['accion']) . ' (ninguna sucursal alcanza la conveniencia mínima configurada)';
            $resultado['advertencias'][] = $mejorViable
                ? "La mejor sucursal candidata ({$mejorViable['sucursal_nombre']}) solo alcanza {$mejorViable['puntuacion']}% de conveniencia, por debajo del mínimo configurado ({$umbralConveniencia}%)."
                : 'Ninguna sucursal candidata resultó económica o temporalmente viable para recibir esta transferencia.';
            if ($ranking && !$mejorViable) {
                $primerDescartado = $ranking[0];
                if (!empty($primerDescartado['motivo_no_viable'])) {
                    $resultado['advertencias'][] = "{$primerDescartado['sucursal_nombre']}: {$primerDescartado['motivo_no_viable']}";
                }
            }
        }
    } elseif ($accionBase === 'PROMOCION' || $accionBase === 'MANTENER') {
        $resultado['accion_recomendada'] = $accionBase;
        $resultado['etiqueta'] = etiquetaAccion($accionBase);
        // Informativo: si existiera una sucursal claramente mejor, se muestra
        // como alternativa aunque el tramo de % de venta no obligue a moverlo.
        if ($hayDestinoConveniente && $mejorViable['puntuacion'] >= $umbralConveniencia + 20) {
            $resultado['advertencias'][] = "Aunque el % de venta actual no exige transferir, {$mejorViable['sucursal_nombre']} muestra muy buena conveniencia ({$mejorViable['puntuacion']}%) por si se prefiere adelantar la redistribución.";
        }
    } else { // DEVOLUCION_PROVEEDOR
        $resultado['accion_recomendada'] = 'DEVOLUCION_PROVEEDOR';
        $resultado['etiqueta'] = etiquetaAccion('DEVOLUCION_PROVEEDOR');
        if ($hayDestinoConveniente) {
            $resultado['advertencias'][] = "Antes de devolver al proveedor, considere que {$mejorViable['sucursal_nombre']} tiene {$mejorViable['puntuacion']}% de conveniencia como destino alternativo.";
        }
    }
    if ($loteDetalle['nivel_riesgo'] === 'CRITICO') {
        $resultado['motivos'][] = 'Riesgo crítico: quedan pocos días antes del vencimiento, se recomienda ejecutar la acción cuanto antes.';
    }
    return $resultado;
}
/**
 * Si la sucursal destino no resultó suficientemente conveniente, se busca
 * en los intervalos configurados cuál es la "siguiente" acción disponible
 * (normalmente la del tramo inmediatamente inferior de % de venta, que
 * suele ser DEVOLUCION_PROVEEDOR). Si por configuración no hay una acción
 * distinta a REDISTRIBUCION por debajo, se cae a PROMOCION como opción
 * segura (nunca se deja la recomendación vacía).
 */
function obtenerAccionDegradada(array $intervalos, string $accionActual): array
{
    $ordenados = $intervalos;
    usort($ordenados, fn($a, $b) => $b['min'] <=> $a['min']); // de mayor a menor % de venta
    $encontrado = false;
    foreach ($ordenados as $tramo) {
        if ($encontrado && $tramo['accion'] !== $accionActual) {
            return $tramo;
        }
        if ($tramo['accion'] === $accionActual) {
            $encontrado = true;
        }
    }
    return ['accion' => 'PROMOCION', 'etiqueta' => 'Aplicar promoción'];
}
function etiquetaAccion(string $accion): string
{
    $etiquetas = [
        'MANTENER' => 'Mantener en la sucursal / venta normal',
        'PROMOCION' => 'Aplicar promoción',
        'REDISTRIBUCION' => 'Transferir a otra sucursal',
        'DEVOLUCION_PROVEEDOR' => 'Evaluar devolución al proveedor',
    ];
    return $etiquetas[$accion] ?? $accion;
}