<?php
/**
 * Tarea 5 - Proceso estratégico: Gestión Estratégica de Vencimientos de Medicamentos
 * Librería de funciones reutilizables. NO es un endpoint (no imprime JSON ni valida
 * sesión) - los archivos que sí son endpoints (evaluar_riesgo_vencimiento.php,
 * detalle_riesgo_lote.php, etc.) hacen require_once de este archivo y usan sus
 * funciones, para no repetir la misma lógica de cálculo en cada uno.
 *
 * Requiere que $conexion (PDO) ya exista, es decir, debe incluirse DESPUÉS de
 * backend/conexion.php.
 */

/**
 * Lee los umbrales del proceso desde configuracion_sistema (clave/valor).
 * Si alguna clave no existe todavía en la BD, usa un valor por defecto
 * razonable para que el sistema no se caiga por falta de configuración.
 */
function obtenerUmbralesVencimiento(PDO $conexion): array
{
    $defaults = [
        'venc_umbral_dias_critico' => 15,
        'venc_umbral_dias_moderado' => 30,
        'venc_irv_umbral_minimo' => 15,
        'venc_irv_periodo_dias' => 30,
        'venc_costo_transporte_estimado_unidad' => 0,
        'venc_velocidad_promedio_kmh' => 35,
        'venc_umbral_conveniencia_minima' => 50,
        'venc_max_costo_transporte_pct_valor' => 30,
    ];

    try {
        $stmt = $conexion->query("SELECT clave, valor FROM configuracion_sistema WHERE clave LIKE 'venc_%'");
        $filas = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($filas as $clave => $valor) {
            if (array_key_exists($clave, $defaults)) {
                $defaults[$clave] = is_numeric($valor) ? $valor + 0 : $valor;
            }
        }
    } catch (PDOException $e) {
        // Si configuracion_sistema no está disponible por alguna razón, se
        // sigue con los valores por defecto en vez de romper la pantalla.
    }

    return $defaults;
}

/**
 * Clasifica el nivel de riesgo de un lote según los días restantes para
 * vencer, comparado contra los umbrales configurados.
 * Devuelve 'CRITICO' | 'MODERADO' | 'BAJO'.
 */
function calcularNivelRiesgo(int $diasRestantes, array $umbrales): string
{
    if ($diasRestantes <= (int)$umbrales['venc_umbral_dias_critico']) {
        return 'CRITICO';
    }
    if ($diasRestantes <= (int)$umbrales['venc_umbral_dias_moderado']) {
        return 'MODERADO';
    }
    return 'BAJO';
}

/**
 * Calcula el Índice de Rotación de Venta (IRV) de un medicamento en una
 * sucursal específica, dentro de la ventana de días configurada.
 *
 *   IRV = (unidades vendidas en el período / stock actual en esa sucursal) * 100
 *
 * NOTA METODOLÓGICA: se usa el stock ACTUAL como aproximación del stock
 * promedio del período, porque el sistema no guarda una foto diaria del
 * inventario histórico. Es una simplificación deliberada y debe quedar
 * documentada como tal en la memoria de la Tarea 5.
 *
 * Devuelve null si no hay stock actual (no se puede calcular el índice,
 * división por cero evitada a propósito).
 */
function calcularIRV(PDO $conexion, int $idMedicamento, int $idSucursal, int $diasPeriodo): ?float
{
    $sqlVendidas = "SELECT COALESCE(SUM(dv.cantidad), 0) AS total
                     FROM detalle_venta dv
                     JOIN ventas v ON dv.id_venta = v.id_venta
                     JOIN lotes l ON dv.id_lote = l.id_lote
                     WHERE l.id_medicamento = :med
                       AND v.id_sucursal = :suc
                       AND v.fecha >= CURRENT_DATE - (:dias || ' days')::interval";
    $stmt = $conexion->prepare($sqlVendidas);
    $stmt->execute([':med' => $idMedicamento, ':suc' => $idSucursal, ':dias' => $diasPeriodo]);
    $unidadesVendidas = (float)$stmt->fetchColumn();

    $sqlStock = "SELECT COALESCE(SUM(i.cantidad), 0) AS total
                 FROM inventario i
                 JOIN lotes l ON i.id_lote = l.id_lote
                 WHERE l.id_medicamento = :med AND i.id_sucursal = :suc";
    $stmt = $conexion->prepare($sqlStock);
    $stmt->execute([':med' => $idMedicamento, ':suc' => $idSucursal]);
    $stockActual = (float)$stmt->fetchColumn();

    if ($stockActual <= 0) {
        return null;
    }

    return round(($unidadesVendidas / $stockActual) * 100, 2);
}

function obtenerIntervalosAccion(PDO $conexion): array
{
    $defecto = [
        ['min' => 0, 'max' => 5, 'accion' => 'REDISTRIBUCION'],
        ['min' => 5.01, 'max' => 15, 'accion' => 'PROMOCION'],
        ['min' => 15.01, 'max' => 100, 'accion' => 'MANTENER'],
    ];

    try {
        $stmt = $conexion->prepare("SELECT valor FROM configuracion_sistema WHERE clave = 'venc_intervalos_accion'");
        $stmt->execute();
        $valor = $stmt->fetchColumn();
        $intervalos = $valor ? json_decode($valor, true) : null;
        if (!is_array($intervalos) || !$intervalos) {
            return $defecto;
        }

        $validos = [];
        foreach ($intervalos as $intervalo) {
            if (!isset($intervalo['min'], $intervalo['max'], $intervalo['accion'])
                || !is_numeric($intervalo['min']) || !is_numeric($intervalo['max'])
                || (float)$intervalo['min'] > (float)$intervalo['max']) {
                continue;
            }
            $validos[] = [
                'min' => (float)$intervalo['min'],
                'max' => (float)$intervalo['max'],
                'accion' => (string)$intervalo['accion'],
            ];
        }
        return $validos ?: $defecto;
    } catch (PDOException $e) {
        return $defecto;
    }
}

function determinarAccionPorIntervalo(?float $irv, array $intervalos): ?array
{
    if ($irv === null || !$intervalos) {
        return null;
    }

    $irvAcotado = max(0, min(100, $irv));
    foreach ($intervalos as $intervalo) {
        if ($irvAcotado >= (float)$intervalo['min'] && $irvAcotado <= (float)$intervalo['max']) {
            return $intervalo;
        }
    }

    return null;
}

function calcularDemandaHistorica(PDO $conexion, int $idMedicamento, int $idSucursal, int $dias = 180): float
{
    $stmt = $conexion->prepare("SELECT COALESCE(SUM(dv.cantidad), 0)
        FROM detalle_venta dv
        JOIN ventas v ON dv.id_venta = v.id_venta
        JOIN lotes l ON dv.id_lote = l.id_lote
        WHERE l.id_medicamento = :med
          AND v.id_sucursal = :suc
          AND v.fecha >= CURRENT_DATE - (:dias || ' days')::interval");
    $stmt->execute([':med' => $idMedicamento, ':suc' => $idSucursal, ':dias' => $dias]);
    return (float)$stmt->fetchColumn();
}

function obtenerCoordenadasSucursal(PDO $conexion, int $idSucursal): ?array
{
    $stmt = $conexion->prepare("SELECT latitud, longitud FROM sucursales WHERE id_sucursal = :suc");
    $stmt->execute([':suc' => $idSucursal]);
    $coordenadas = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$coordenadas || $coordenadas['latitud'] === null || $coordenadas['longitud'] === null) {
        return null;
    }
    return [(float)$coordenadas['latitud'], (float)$coordenadas['longitud']];
}

function calcularDistanciaKm(array $origen, array $destino): float
{
    $radioTierraKm = 6371;
    $lat1 = deg2rad((float)$origen[0]);
    $lat2 = deg2rad((float)$destino[0]);
    $deltaLat = deg2rad((float)$destino[0] - (float)$origen[0]);
    $deltaLon = deg2rad((float)$destino[1] - (float)$origen[1]);
    $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;
    return round($radioTierraKm * 2 * asin(min(1, sqrt($a))), 2);
}

function estimarProbabilidadVenta(?float $irv, int $diasRestantes, int $periodoIrv): float
{
    if ($irv === null || $irv <= 0 || $diasRestantes <= 0 || $periodoIrv <= 0) {
        return 0.0;
    }
    return round(min(100, $irv * ($diasRestantes / $periodoIrv)), 2);
}

/**
 * Evalúa TODOS los lotes activos con stock > 0, devolviendo por cada
 * combinación lote+sucursal: días restantes, nivel de riesgo, valor
 * económico en riesgo, IRV de esa sucursal y si está en baja rotación.
 * Es la fuente de datos de la Pantalla #02 (Monitoreo y clasificación).
 *
 * $filtros admite: id_sucursal, id_categoria, nivel_riesgo, busqueda
 */
function listarLotesEnRiesgo(PDO $conexion, array $umbrales, array $filtros = []): array
{
    $where = "WHERE l.estado = 'ACTIVO' AND i.cantidad > 0";
    $params = [];

    if (!empty($filtros['id_sucursal'])) {
        $where .= " AND i.id_sucursal = :id_sucursal";
        $params[':id_sucursal'] = $filtros['id_sucursal'];
    }
    if (!empty($filtros['id_categoria'])) {
        $where .= " AND m.id_categoria = :id_categoria";
        $params[':id_categoria'] = $filtros['id_categoria'];
    }
    if (!empty($filtros['busqueda'])) {
        $where .= " AND (l.numero_lote ILIKE :busqueda OR m.nombre ILIKE :busqueda)";
        $params[':busqueda'] = '%' . $filtros['busqueda'] . '%';
    }

    $sql = "SELECT i.id_inventario, i.id_lote, i.id_sucursal, i.cantidad,
                   l.numero_lote, l.fecha_vencimiento, l.costo_unitario,
                   m.id_medicamento, m.nombre AS medicamento_nombre, m.concentracion, m.id_categoria,
                   s.nombre AS sucursal_nombre,
                   (l.fecha_vencimiento - CURRENT_DATE) AS dias_restantes
            FROM inventario i
            JOIN lotes l ON i.id_lote = l.id_lote
            JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
            JOIN sucursales s ON i.id_sucursal = s.id_sucursal
            $where
            ORDER BY l.fecha_vencimiento ASC";

    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resultado = [];
    foreach ($filas as $fila) {
        $diasRestantes = (int)$fila['dias_restantes'];
        $nivelRiesgo = calcularNivelRiesgo($diasRestantes, $umbrales);

        // Filtro de nivel de riesgo se aplica después de calcularlo, porque
        // no es una columna real de la tabla, es derivada.
        if (!empty($filtros['nivel_riesgo']) && $filtros['nivel_riesgo'] !== $nivelRiesgo) {
            continue;
        }

        $irv = calcularIRV($conexion, (int)$fila['id_medicamento'], (int)$fila['id_sucursal'], (int)$umbrales['venc_irv_periodo_dias']);
        $valorEnRiesgo = round($fila['cantidad'] * (float)$fila['costo_unitario'], 2);

        $resultado[] = [
            'id_inventario' => (int)$fila['id_inventario'],
            'id_lote' => (int)$fila['id_lote'],
            'numero_lote' => $fila['numero_lote'],
            'id_medicamento' => (int)$fila['id_medicamento'],
            'medicamento_nombre' => $fila['medicamento_nombre'],
            'concentracion' => $fila['concentracion'],
            'id_sucursal' => (int)$fila['id_sucursal'],
            'sucursal_nombre' => $fila['sucursal_nombre'],
            'cantidad' => (int)$fila['cantidad'],
            'costo_unitario' => (float)$fila['costo_unitario'],
            'fecha_vencimiento' => $fila['fecha_vencimiento'],
            'dias_restantes' => $diasRestantes,
            'nivel_riesgo' => $nivelRiesgo,
            'valor_en_riesgo' => $valorEnRiesgo,
            'irv' => $irv, // null = sin stock suficiente para calcularlo
            'estado_venta' => ($irv === null) ? 'SIN_DATOS' : (($irv < (float)$umbrales['venc_irv_umbral_minimo']) ? 'BAJA_ROTACION' : 'ROTACION_NORMAL'),
        ];
    }

    return $resultado;
}

/**
 * Diagnóstico automático de causa raíz (Pantalla #08). Combina señales que
 * YA se pueden calcular con los datos existentes del sistema:
 *  - SIN_ROTACION_RED: confirmado por evaluarLoteDetalle() (sin_demanda_en_red)
 *  - PROXIMO_VENCIMIENTO: el lote ya está en riesgo CRITICO
 *  - SUSTITUTO_MEJOR_ROTACION: otro medicamento de la misma categoría con
 *    mejor IRV en la misma sucursal
 *  - PRECIO_NO_COMPETITIVO: precio de venta promedio muy por encima del
 *    promedio de su categoría
 *  - ESTACIONALIDAD: NO se detecta automáticamente (requeriría histórico de
 *    varios años que el sistema no tiene todavía) - queda marcada como
 *    "no determinada", no se inventa un resultado.
 */
function diagnosticarCausaRaiz(PDO $conexion, array $loteDetalle, int $idSucursal, array $umbrales): array
{
    $causas = [];

    // 1) Sin rotación en ninguna sucursal (ya viene calculado)
    $causas['SIN_ROTACION_RED'] = [
        'detectada' => $loteDetalle['sin_demanda_en_red'],
        'detalle' => $loteDetalle['sin_demanda_en_red']
            ? 'IRV por debajo de ' . $umbrales['venc_irv_umbral_minimo'] . '% en todas las sucursales con datos.'
            : 'Al menos una sucursal cumple el umbral, o no hay datos suficientes para confirmarlo.',
    ];

    $bajaRotacionOrigen = $loteDetalle['irv_origen'] !== null
        && $loteDetalle['irv_origen'] < (float) $umbrales['venc_irv_umbral_minimo'];
    $causas['BAJA_ROTACION_SUCURSAL'] = [
        'detectada' => $bajaRotacionOrigen,
        'detalle' => $bajaRotacionOrigen
            ? 'IRV de ' . $loteDetalle['irv_origen'] . '% en la sucursal actual, por debajo del umbral de ' . $umbrales['venc_irv_umbral_minimo'] . '%.'
            : 'La rotación de esta sucursal cumple el umbral o no hay datos suficientes para calcularla.',
    ];
    $sinVentasOrigen = $loteDetalle['irv_origen'] !== null
        && (float) $loteDetalle['irv_origen'] === 0.0;
    $causas['SIN_VENTAS_SUCURSAL'] = [
        'detectada' => $sinVentasOrigen,
        'detalle' => $sinVentasOrigen
            ? 'No se registraron unidades vendidas en los últimos ' . $umbrales['venc_irv_periodo_dias'] . ' días en la sucursal actual.'
            : 'Se registraron ventas del medicamento en el período evaluado.',
    ];

    // 2) Próximo a vencer (riesgo crítico)
    $causas['PROXIMO_VENCIMIENTO'] = [
        'detectada' => $loteDetalle['nivel_riesgo'] === 'CRITICO',
        'detalle' => $loteDetalle['nivel_riesgo'] === 'CRITICO'
            ? 'Quedan ' . $loteDetalle['dias_restantes'] . ' días para el vencimiento.'
            : 'El lote todavía no está en el umbral de riesgo crítico.',
    ];

    // 3) Sustituto con mejor rotación en la misma categoría
    $sustitutoDetectado = false;
    $sustitutoNombre = null;
    $stmt = $conexion->prepare("
        SELECT m2.id_medicamento, m2.nombre
        FROM medicamentos m2
        JOIN medicamentos m1 ON m1.id_categoria = m2.id_categoria
        WHERE m1.id_medicamento = :med_categoria AND m2.id_medicamento != :med_excluir
    ");
    $stmt->execute([
        ':med_categoria' => $loteDetalle['id_medicamento'],
        ':med_excluir' => $loteDetalle['id_medicamento'],
    ]);
    $competidores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $mejorIrv = $loteDetalle['irv_origen'] ?? 0;
    foreach ($competidores as $c) {
        $irvComp = calcularIRV($conexion, (int)$c['id_medicamento'], $idSucursal, (int)$umbrales['venc_irv_periodo_dias']);
        if ($irvComp !== null && $irvComp >= (float)$umbrales['venc_irv_umbral_minimo'] && $irvComp > $mejorIrv) {
            $mejorIrv = $irvComp;
            $sustitutoDetectado = true;
            $sustitutoNombre = $c['nombre'];
        }
    }
    $causas['SUSTITUTO_MEJOR_ROTACION'] = [
        'detectada' => $sustitutoDetectado,
        'detalle' => $sustitutoDetectado
            ? "\"$sustitutoNombre\" (misma categoría) tiene mejor rotación en esta sucursal ($mejorIrv%)."
            : 'No se encontró otro medicamento de la misma categoría con mejor rotación.',
    ];

    // 4) Precio no competitivo (precio de venta promedio vs. promedio de su categoría)
    $stmt = $conexion->prepare("
        SELECT AVG(dv.precio_unitario)
        FROM detalle_venta dv JOIN lotes l ON dv.id_lote = l.id_lote
        WHERE l.id_medicamento = :med
    ");
    $stmt->execute([':med' => $loteDetalle['id_medicamento']]);
    $precioPropio = $stmt->fetchColumn();

    $stmt = $conexion->prepare("
        SELECT AVG(dv.precio_unitario)
        FROM detalle_venta dv
        JOIN lotes l ON dv.id_lote = l.id_lote
        JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
                WHERE m.id_categoria = (SELECT id_categoria FROM medicamentos WHERE id_medicamento = :med_categoria)
                    AND m.id_medicamento != :med_excluir
    ");
        $stmt->execute([
                ':med_categoria' => $loteDetalle['id_medicamento'],
                ':med_excluir' => $loteDetalle['id_medicamento'],
        ]);
    $precioPromedioCategoria = $stmt->fetchColumn();

    $precioNoCompetitivo = ($precioPropio && $precioPromedioCategoria && $precioPropio > $precioPromedioCategoria * 1.15);
    $causas['PRECIO_NO_COMPETITIVO'] = [
        'detectada' => (bool)$precioNoCompetitivo,
        'detalle' => ($precioPropio && $precioPromedioCategoria)
            ? ('Precio promedio propio RD$' . number_format($precioPropio, 2) . ' vs. RD$' . number_format($precioPromedioCategoria, 2) . ' promedio de su categoría.')
            : 'Sin ventas suficientes de este medicamento o de su categoría para comparar precios.',
    ];

    // 5) Estacionalidad: no se detecta automáticamente, a propósito
    $causas['ESTACIONALIDAD'] = [
        'detectada' => false,
        'detalle' => 'No determinable con los datos actuales (requiere histórico de varios años).',
        'no_determinable' => true,
    ];

    return $causas;
}
function evaluarLoteDetalle(PDO $conexion, int $idLote, int $idSucursal, array $umbrales): ?array
{
    $sql = "SELECT i.id_inventario, i.cantidad,
                   l.id_lote, l.numero_lote, l.fecha_vencimiento, l.costo_unitario, l.costo_lote,
                   m.id_medicamento, m.nombre AS medicamento_nombre, m.concentracion,
                   s.nombre AS sucursal_nombre,
                   (l.fecha_vencimiento - CURRENT_DATE) AS dias_restantes
            FROM inventario i
            JOIN lotes l ON i.id_lote = l.id_lote
            JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
            JOIN sucursales s ON i.id_sucursal = s.id_sucursal
            WHERE i.id_lote = :lote AND i.id_sucursal = :suc";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':lote' => $idLote, ':suc' => $idSucursal]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fila) {
        return null;
    }

    $diasRestantes = (int)$fila['dias_restantes'];
    $nivelRiesgo = calcularNivelRiesgo($diasRestantes, $umbrales);
    $irvOrigen = calcularIRV($conexion, (int)$fila['id_medicamento'], $idSucursal, (int)$umbrales['venc_irv_periodo_dias']);

    // Rotación del mismo medicamento en TODAS las sucursales donde hay stock,
    // para sustentar la decisión de a dónde redistribuir (Pantalla #05).
    $sqlSucursales = "SELECT DISTINCT s.id_sucursal, s.nombre
                       FROM inventario i2
                       JOIN sucursales s ON i2.id_sucursal = s.id_sucursal
                       WHERE i2.id_lote IN (SELECT id_lote FROM lotes WHERE id_medicamento = :med)
                         AND i2.cantidad > 0";
    $stmt2 = $conexion->prepare($sqlSucursales);
    $stmt2->execute([':med' => $fila['id_medicamento']]);
    $sucursales = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $rotacionPorSucursal = [];
    foreach ($sucursales as $s) {
        $irv = calcularIRV($conexion, (int)$fila['id_medicamento'], (int)$s['id_sucursal'], (int)$umbrales['venc_irv_periodo_dias']);
        $rotacionPorSucursal[] = [
            'id_sucursal' => (int)$s['id_sucursal'],
            'sucursal_nombre' => $s['nombre'],
            'irv' => $irv,
            'cumple_umbral' => ($irv !== null && $irv >= (float)$umbrales['venc_irv_umbral_minimo']),
        ];
    }

    $tieneAlgunDato = count(array_filter($rotacionPorSucursal, fn($s) => $s['irv'] !== null)) > 0;
    $algunaSucursalCumple = count(array_filter($rotacionPorSucursal, fn($s) => $s['cumple_umbral'])) > 0;

    return [
        'id_lote' => (int)$fila['id_lote'],
        'numero_lote' => $fila['numero_lote'],
        'id_medicamento' => (int)$fila['id_medicamento'],
        'medicamento_nombre' => $fila['medicamento_nombre'],
        'concentracion' => $fila['concentracion'],
        'id_sucursal' => $idSucursal,
        'sucursal_nombre' => $fila['sucursal_nombre'],
        'cantidad' => (int)$fila['cantidad'],
        'costo_unitario' => (float)$fila['costo_unitario'],
        'fecha_vencimiento' => $fila['fecha_vencimiento'],
        'dias_restantes' => $diasRestantes,
        'nivel_riesgo' => $nivelRiesgo,
        'valor_en_riesgo' => round($fila['cantidad'] * (float)$fila['costo_unitario'], 2),
        'irv_origen' => $irvOrigen,
        'rotacion_por_sucursal' => $rotacionPorSucursal,
        // NUEVO (Tarea 5, corrección): antes se trataba igual "confirmé que
        // no vende en ninguna sucursal" y "no tengo ventas registradas para
        // evaluarlo". Son cosas distintas y no deben bloquear el flujo de
        // la misma forma:
        //   sin_demanda_en_red = HAY datos de venta y NINGUNA sucursal cumple el umbral (bloqueo real)
        //   sin_datos_en_red   = NINGUNA sucursal tiene historial de ventas suficiente (no se puede concluir nada, no se bloquea)
        'sin_demanda_en_red' => $tieneAlgunDato && !$algunaSucursalCumple,
        'sin_datos_en_red' => !$tieneAlgunDato,
    ];
}
