<?php
// backend/ventas/calcular_costo_envio.php
// NUEVO — calcula el costo del envío según la distancia real entre la
// sucursal y la dirección de entrega, usando el costo por km
// configurado en Configuración > Delivery.
//
// Si a la sucursal o a la dirección le faltan coordenadas (algo muy
// probable al principio, porque nada las capturaba hasta ahora), cae
// de vuelta al "Costo de Envío por Defecto" configurado, para que la
// venta nunca se quede sin poder calcular un costo.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$id_sucursal  = intval($_GET['id_sucursal'] ?? 0);
$id_direccion = intval($_GET['id_direccion'] ?? 0);

if (!$id_sucursal || !$id_direccion) {
    echo json_encode(['success' => false, 'message' => 'Sucursal y dirección son requeridas']); exit();
}

// Distancia en línea recta entre dos coordenadas (fórmula de Haversine).
// Es una aproximación (no sigue calles), pero no necesita ningún
// servicio externo ni llave de API.
function distanciaKm($lat1, $lon1, $lat2, $lon2) {
    $radioTierra = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $radioTierra * $c;
}

try {
    $stmt = $conexion->prepare("SELECT latitud, longitud FROM sucursales WHERE id_sucursal = :id");
    $stmt->execute([':id' => $id_sucursal]);
    $sucursal = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $conexion->prepare("SELECT latitud, longitud FROM direcciones WHERE id_direccion = :id");
    $stmt->execute([':id' => $id_direccion]);
    $direccion = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $conexion->query("SELECT clave, valor FROM configuracion_sistema WHERE clave IN ('costo_por_km', 'costo_envio_default')");
    $config = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) { $config[$c['clave']] = $c['valor']; }
    $costoPorKm = isset($config['costo_por_km']) ? (float)$config['costo_por_km'] : 15;
    $costoDefecto = isset($config['costo_envio_default']) ? (float)$config['costo_envio_default'] : 100;

    $tieneCoordenadas = $sucursal && $direccion
        && $sucursal['latitud'] !== null && $sucursal['longitud'] !== null
        && $direccion['latitud'] !== null && $direccion['longitud'] !== null;

    if ($tieneCoordenadas) {
        $km = distanciaKm(
            (float)$sucursal['latitud'], (float)$sucursal['longitud'],
            (float)$direccion['latitud'], (float)$direccion['longitud']
        );
        $costo = round($km * $costoPorKm, 2);

        echo json_encode([
            'success'        => true,
            'calculado'      => true,
            'km'             => round($km, 2),
            'costo_por_km'   => $costoPorKm,
            'costo_envio'    => $costo,
            'detalle'        => round($km, 2) . " km × RD$" . number_format($costoPorKm, 2) . "/km",
        ]);
    } else {
        // Sin coordenadas suficientes: usar el costo fijo por defecto
        echo json_encode([
            'success'     => true,
            'calculado'   => false,
            'costo_envio' => $costoDefecto,
            'detalle'     => "Costo fijo (no hay coordenadas registradas para calcular por distancia)",
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
