<?php
/**
 * Tarea 5 - Proceso estratégico: Gestión Estratégica de Vencimientos de Medicamentos
 * MEJORA FINAL. Endpoint que integra:
 *   - Intervalos configurables de % de venta (IRV)  -> obtenerIntervalosAccion()
 *   - Puntuación ponderada de sucursales destino    -> evaluarSucursalesDestino()
 *   - Tiempo restante de vencimiento
 * en UNA sola recomendación explicada, vía generarRecomendacionEstrategica().
 *
 * No reemplaza evaluar_riesgo_vencimiento.php (que sigue siendo la fuente de
 * la Pantalla #02) ni detalle_lote.php: se apoya en evaluarLoteDetalle(),
 * que ya calculaba todo lo necesario del lote.
 *
 * Parámetros GET: id_lote, id_sucursal (obligatorios)
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
require_once __DIR__ . '/redistribucion_inteligente_lib.php';

$id_lote = isset($_GET['id_lote']) ? (int)$_GET['id_lote'] : 0;
$id_sucursal = isset($_GET['id_sucursal']) ? (int)$_GET['id_sucursal'] : 0;

if (!$id_lote || !$id_sucursal) {
    echo json_encode(['success' => false, 'message' => 'Faltan id_lote / id_sucursal']);
    exit();
}

try {
    $umbrales = obtenerUmbralesVencimiento($conexion);
    $lote = evaluarLoteDetalle($conexion, $id_lote, $id_sucursal, $umbrales);

    if (!$lote) {
        echo json_encode(['success' => false, 'message' => 'No se encontró stock de ese lote en esa sucursal.']);
        exit();
    }

    $recomendacion = generarRecomendacionEstrategica($conexion, $lote, $id_sucursal, $umbrales);

    echo json_encode([
        'success' => true,
        'lote' => $lote,
        'recomendacion' => $recomendacion,
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
