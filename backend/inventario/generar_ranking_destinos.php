<?php

/**
 * Redistribución inteligente — Ranking de destinos
 *
 * Este endpoint:
 * - No mueve inventario.
 * - No modifica stock.
 * - Solamente calcula y devuelve el ranking de sucursales.
 * - SIEMPRE responde JSON válido.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * IMPORTANTE:
 * Iniciamos buffer antes de cargar cualquier archivo externo.
 * Así evitamos que warnings, espacios o BOM rompan el JSON.
 */
ob_start();

header('Content-Type: application/json; charset=utf-8');

/**
 * Envía siempre una respuesta JSON limpia.
 */
function responderJson(array $respuesta, int $codigoHttp = 200): void
{
    http_response_code($codigoHttp);

    /*
     * Elimina cualquier salida accidental generada por los
     * archivos incluidos: warnings, espacios, BOM, etc.
     */
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $respuesta,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit();
}

/**
 * Convierte warnings/notices de PHP en excepciones para que
 * podamos devolverlos correctamente como JSON.
 */
set_error_handler(function (
    int $severity,
    string $message,
    string $file,
    int $line
): bool {

    /*
     * Ignoramos errores que PHP marque como no reportables.
     */
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException(
        $message,
        0,
        $severity,
        $file,
        $line
    );
});


try {

    /*
     * ------------------------------------------------------------
     * SESIÓN
     * ------------------------------------------------------------
     */

    if (!isset($_SESSION['usuario'])) {
        responderJson([
            'success' => false,
            'message' => 'Sesión no iniciada.'
        ], 401);
    }


    /*
     * ------------------------------------------------------------
     * ARCHIVOS NECESARIOS
     * ------------------------------------------------------------
     */

    $conexionFile = __DIR__ . '/../conexion.php';
    $riesgoFile = __DIR__ . '/riesgo_vencimiento_lib.php';
    $redistribucionFile = __DIR__ . '/redistribucion_inteligente_lib.php';

    if (!file_exists($conexionFile)) {
        throw new RuntimeException(
            'No se encontró el archivo de conexión: ' . $conexionFile
        );
    }

    if (!file_exists($riesgoFile)) {
        throw new RuntimeException(
            'No se encontró riesgo_vencimiento_lib.php'
        );
    }

    if (!file_exists($redistribucionFile)) {
        throw new RuntimeException(
            'No se encontró redistribucion_inteligente_lib.php'
        );
    }

    require_once $conexionFile;
    require_once $riesgoFile;
    require_once $redistribucionFile;


    /*
     * ------------------------------------------------------------
     * VALIDAR CONEXIÓN
     * ------------------------------------------------------------
     */

    if (!isset($conexion) || !$conexion) {
        throw new RuntimeException(
            'No se pudo establecer la conexión con la base de datos.'
        );
    }


    /*
     * ------------------------------------------------------------
     * VALIDAR FUNCIONES NECESARIAS
     * ------------------------------------------------------------
     */

    $funcionesNecesarias = [
        'obtenerUmbralesVencimiento',
        'evaluarLoteDetalle',
        'obtenerPesosTransferencia',
        'evaluarSucursalesDestino',
        'etiquetasCriterioTransferencia'
    ];

    foreach ($funcionesNecesarias as $funcion) {

        if (!function_exists($funcion)) {
            throw new RuntimeException(
                "La función requerida no existe: {$funcion}()"
            );
        }
    }


    /*
     * ------------------------------------------------------------
     * PARÁMETROS
     * ------------------------------------------------------------
     */

    $id_lote = isset($_GET['id_lote'])
        ? (int) $_GET['id_lote']
        : 0;

    $id_sucursal_origen = isset($_GET['id_sucursal_origen'])
        ? (int) $_GET['id_sucursal_origen']
        : 0;


    if ($id_lote <= 0 || $id_sucursal_origen <= 0) {

        responderJson([
            'success' => false,
            'message' => 'Faltan id_lote / id_sucursal_origen.'
        ], 400);
    }


    /*
     * ------------------------------------------------------------
     * UMBRALES
     * ------------------------------------------------------------
     */

    $umbrales = obtenerUmbralesVencimiento($conexion);

    if (!is_array($umbrales)) {
        throw new RuntimeException(
            'obtenerUmbralesVencimiento() no devolvió un arreglo válido.'
        );
    }


    /*
     * ------------------------------------------------------------
     * OBTENER LOTE
     * ------------------------------------------------------------
     */

    $lote = evaluarLoteDetalle(
        $conexion,
        $id_lote,
        $id_sucursal_origen,
        $umbrales
    );

    if (!$lote) {

        responderJson([
            'success' => false,
            'message' => 'No se encontró stock de ese lote en esa sucursal.'
        ], 404);
    }


    /*
     * ------------------------------------------------------------
     * OBTENER PESOS
     * ------------------------------------------------------------
     */

    $pesos = obtenerPesosTransferencia($conexion);

    if (!is_array($pesos)) {
        throw new RuntimeException(
            'obtenerPesosTransferencia() no devolvió un arreglo válido.'
        );
    }


    /*
     * ------------------------------------------------------------
     * EVALUAR SUCURSALES
     * ------------------------------------------------------------
     */

    $rankingCalculado = evaluarSucursalesDestino(
        $conexion,
        $lote,
        $id_sucursal_origen,
        (int) $lote['cantidad'],
        $umbrales,
        $pesos
    );

    if (!is_array($rankingCalculado)) {
        throw new RuntimeException(
            'evaluarSucursalesDestino() no devolvió un arreglo válido.'
        );
    }


    /*
     * ------------------------------------------------------------
     * UMBRALES DEL RANKING
     * ------------------------------------------------------------
     */

    $umbralConveniencia = isset(
        $umbrales['venc_umbral_conveniencia_minima']
    )
        ? (float) $umbrales['venc_umbral_conveniencia_minima']
        : 0;

    $umbralRotacion = isset(
        $umbrales['venc_irv_umbral_minimo']
    )
        ? (float) $umbrales['venc_irv_umbral_minimo']
        : 0;


    /*
     * ------------------------------------------------------------
     * CONSTRUIR RANKING
     * ------------------------------------------------------------
     */

    $ranking = array_map(
        function (array $candidato) use (
            $umbralConveniencia,
            $umbralRotacion
        ): array {

            $viable = !empty(
                $candidato['viable_economicamente']
            );

            $score = (float) (
                $candidato['puntuacion'] ?? 0
            );

            /*
             * Determinar semáforo.
             */
            if (!$viable) {

                $semaforo = 'NO_RECOMENDADA';

            } elseif ($score >= $umbralConveniencia) {

                $semaforo = 'OPTIMA';

            } else {

                $semaforo = 'VIABLE';
            }


            /*
             * Datos de criterios.
             */
            $etiquetas = etiquetasCriterioTransferencia();

            $pesosAplicados =
                $candidato['pesos_aplicados'] ?? [];

            $preferencias =
                $candidato['preferencias_aplicadas'] ?? [];

            $contribuciones =
                $candidato['contribuciones_por_criterio'] ?? [];


            /*
             * Explicaciones.
             */
            $explicaciones = [];


            /*
             * Perfil personalizado.
             */
            $explicaciones[] = [
                'tipo' => !empty(
                    $candidato['perfil_personalizado']
                )
                    ? 'positivo'
                    : 'neutro',

                'texto' => !empty(
                    $candidato['perfil_personalizado']
                )
                    ? 'Se usó el perfil de criterios propio de esta sucursal (no el mismo peso para todas).'
                    : 'Se usaron los pesos por defecto: esta sucursal aún no tiene un perfil propio.'
            ];


            /*
             * Contribuciones.
             */
            if (is_array($contribuciones)) {

                arsort($contribuciones);

                foreach (
                    array_slice(
                        $contribuciones,
                        0,
                        3,
                        true
                    ) as $criterio => $aporte
                ) {

                    $peso = (float) (
                        $pesosAplicados[$criterio] ?? 0
                    );

                    if ($peso <= 0) {
                        continue;
                    }

                    $sentido =
                        ($preferencias[$criterio] ?? 'mayor')
                        === 'menor'
                            ? 'prefiere menos'
                            : 'prefiere más';

                    $explicaciones[] = [
                        'tipo' => 'positivo',

                        'texto' =>
                            ($etiquetas[$criterio] ?? $criterio)
                            . ": peso {$peso}% ({$sentido}) · aporte "
                            . round((float) $aporte, 1)
                            . " pts."
                    ];
                }
            }


            /*
             * IRV / inventario.
             */
            $irv = $candidato['irv'] ?? null;

            $stockActual =
                (float) ($candidato['stock_actual'] ?? 0);

            if ($irv !== null) {

                $explicaciones[] = [
                    'tipo' =>
                        $irv >= $umbralRotacion
                            ? 'positivo'
                            : 'advertencia',

                    'texto' =>
                        'IRV destino: '
                        . $irv
                        . '%. Inventario actual: '
                        . number_format($stockActual)
                        . ' u.'
                ];

            } else {

                $explicaciones[] = [
                    'tipo' => 'neutro',

                    'texto' =>
                        'Sin historial suficiente de ventas en esta sucursal. '
                        . 'Inventario actual: '
                        . number_format($stockActual)
                        . ' u.'
                ];
            }


            /*
             * Distancia.
             */
            $distancia =
                $candidato['distancia_km'] ?? null;

            if ($distancia !== null) {

                $explicaciones[] = [
                    'tipo' => 'positivo',

                    'texto' =>
                        'Distancia estimada: '
                        . $distancia
                        . ' km.'
                ];
            }


            /*
             * Motivo de no viabilidad.
             */
            if (!empty($candidato['motivo_no_viable'])) {

                $explicaciones[] = [
                    'tipo' => 'negativo',

                    'texto' =>
                        $candidato['motivo_no_viable']
                ];
            }


            /*
             * Tiempo estimado.
             */
            $diasTransito =
                $candidato['dias_transito_estimado'] ?? null;

            $tiempoEstimado = null;

            if ($diasTransito !== null) {

                $tiempoEstimado = round(
                    (float) $diasTransito * 24 * 60
                );
            }


            /*
             * Resultado final de la sucursal.
             */
            return [

                'id_sucursal' =>
                    (int) ($candidato['id_sucursal'] ?? 0),

                'sucursal_nombre' =>
                    $candidato['sucursal_nombre'] ?? 'Sucursal',

                'score' =>
                    $score,

                'semaforo' =>
                    $semaforo,

                'explicaciones' =>
                    $explicaciones,

                'pesos_aplicados' =>
                    $pesosAplicados,

                'preferencias_aplicadas' =>
                    $preferencias,

                'contribuciones_por_criterio' =>
                    $contribuciones,

                'perfil_personalizado' =>
                    !empty($candidato['perfil_personalizado']),

                'distancia_km' =>
                    $distancia,

                'tiempo_estimado_minutos' =>
                    $tiempoEstimado,

                /*
                 * Estos dos valores todavía son calculados
                 * posteriormente por el módulo de logística.
                 */
                'combustible_estimado_gal' =>
                    null,

                'costo_combustible_estimado' =>
                    $candidato['costo_transporte_estimado'] ?? null,

                'viable_economicamente' =>
                    $viable
            ];

        },
        $rankingCalculado
    );


    /*
     * ------------------------------------------------------------
     * RESPUESTA EXITOSA
     * ------------------------------------------------------------
     */

    responderJson([

        'success' => true,

        'lote' => [

            'medicamento_nombre' =>
                $lote['medicamento_nombre'] ?? '',

            'cantidad' =>
                (int) ($lote['cantidad'] ?? 0),

            'dias_restantes' =>
                $lote['dias_restantes'] ?? null,

            'nivel_riesgo' =>
                $lote['nivel_riesgo'] ?? null
        ],

        'ranking' =>
            $ranking

    ]);


} catch (Throwable $e) {

    /*
     * IMPORTANTE:
     * Ahora el error llegará al JavaScript como JSON válido,
     * en lugar de romper r.json().
     */

    error_log(
        '[generar_ranking_destinos.php] '
        . $e->getMessage()
        . ' en '
        . $e->getFile()
        . ':'
        . $e->getLine()
    );

    responderJson([

        'success' => false,

        'message' =>
            'Error al generar el ranking: '
            . $e->getMessage()

    ], 500);
}