<?php
/**
 * Tarea 5 - Proceso estratégico: Gestión Estratégica de Vencimientos de Medicamentos
 * Endpoint principal de evaluación: clasifica riesgo, calcula valor económico
 * en riesgo e IRV para todos los lotes activos con stock. Alimenta la
 * Pantalla #02 (Monitoreo y clasificación de vencimientos).
 *
 * Parámetros GET opcionales: sucursal, categoria, riesgo (CRITICO|MODERADO|BAJO), busqueda
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/riesgo_vencimiento_lib.php';

try {
    $umbrales = obtenerUmbralesVencimiento($conexion);
    // NUEVO (mejora final): se exponen los intervalos configurables de %
    // de venta junto con los lotes, para que la Pantalla #02 pueda mostrar
    // una sugerencia de acción por fila sin tener que llamar a otro
    // endpoint por cada lote.
    $intervalosAccion = obtenerIntervalosAccion($conexion);

    $filtros = [
        'id_sucursal' => isset($_GET['sucursal']) ? (int)$_GET['sucursal'] : null,
        'id_categoria' => isset($_GET['categoria']) ? (int)$_GET['categoria'] : null,
        'nivel_riesgo' => isset($_GET['riesgo']) ? strtoupper($_GET['riesgo']) : null,
        'busqueda' => isset($_GET['busqueda']) ? $_GET['busqueda'] : null,
    ];

    $lotes = listarLotesEnRiesgo($conexion, $umbrales, array_filter($filtros, fn($v) => $v !== null && $v !== ''));

    // Totales para las tarjetas KPI de la Pantalla #02
    $totales = ['CRITICO' => 0, 'MODERADO' => 0, 'BAJO' => 0];
    $valorTotalEnRiesgo = 0;
    foreach ($lotes as $l) {
        $totales[$l['nivel_riesgo']]++;
        $valorTotalEnRiesgo += $l['valor_en_riesgo'];
    }

    echo json_encode([
        'success' => true,
        'lotes' => $lotes,
        'totales_por_riesgo' => $totales,
        'valor_total_en_riesgo' => round($valorTotalEnRiesgo, 2),
        'umbrales' => $umbrales,
        'intervalos_accion' => $intervalosAccion,
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
