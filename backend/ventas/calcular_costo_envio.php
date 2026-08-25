<?php
// backend/ventas/calcular_costo_envio.php
// Calcula el costo del envío según la distancia real entre la sucursal
// y la dirección de entrega, usando el costo por km configurado en
// Configuración > Delivery.
//
// Si a la sucursal o a la dirección le faltan coordenadas (algo muy
// probable al principio, porque nada las capturaba hasta ahora), cae
// de vuelta al "Costo de Envío por Defecto" configurado, para que la
// venta nunca se quede sin poder calcular un costo.
//
// ACTUALIZACIÓN — vista previa de asignación automática: además del
// costo, ahora también devuelve quién SE ASIGNARÍA automáticamente si
// se factura ahora mismo (repartidor, vehículo, tiempo estimado y hora
// aproximada de llegada), para que el cajero lo vea antes de confirmar.
// Es solo informativo — la asignación real y definitiva se vuelve a
// calcular en backend/ventas/procesar_venta.php al momento de facturar
// (nunca se confía en lo que el navegador haya mostrado aquí, por si
// algo cambió entre que se mostró el preview y se factura).

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../delivery/_asignacion_automatica.php';
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

// Carga del pedido (PATCH 28/28), opcional — el frontend manda el
// carrito como JSON en ?productos=[{"id_producto":1,"cantidad":2},...]
// para que el preview ya muestre el mismo vehículo que procesar_venta.php
// terminaría asignando de verdad. Si no se manda (o viene mal formado),
// simplemente no se considera la carga en este preview — no rompe nada.
$pesoTotalKg = null;
$totalUnidades = null;
$productosRaw = json_decode($_GET['productos'] ?? '', true);
if (is_array($productosRaw) && $productosRaw) {
    $totalUnidades = 0;
    foreach ($productosRaw as $p) { $totalUnidades += (int) ($p['cantidad'] ?? 0); }

    $idsProductos = array_unique(array_map(fn($p) => (int) ($p['id_producto'] ?? 0), $productosRaw));
    $idsProductos = array_filter($idsProductos);
    if ($idsProductos) {
        // Envuelto en try/catch aparte (no en el try/catch general de más
        // abajo, que todavía no había arrancado en este punto del script):
        // si la columna productos.peso_kg no existe todavía porque no se
        // ha vuelto a correr Farmacia.sql (PATCH 28/28), antes esto tumbaba
        // el script entero con un error crudo (no-JSON) y el navegador
        // mostraba "Error de conexión". Ahora simplemente se ignora la
        // carga en este preview, tal como ya decía el comentario de arriba.
        try {
            $placeholders = implode(',', array_fill(0, count($idsProductos), '?'));
            $stmtPeso = $conexion->prepare("SELECT id_producto, peso_kg FROM productos WHERE id_producto IN ($placeholders)");
            $stmtPeso->execute(array_values($idsProductos));
            $pesoPorProducto = [];
            foreach ($stmtPeso->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $pesoPorProducto[$row['id_producto']] = $row['peso_kg'] !== null ? (float) $row['peso_kg'] : null;
            }
            $pesoTotalKg = 0.0;
            $pesoCompleto = true;
            foreach ($productosRaw as $p) {
                $pesoUnit = $pesoPorProducto[(int) ($p['id_producto'] ?? 0)] ?? null;
                if ($pesoUnit === null) { $pesoCompleto = false; break; }
                $pesoTotalKg += $pesoUnit * (int) ($p['cantidad'] ?? 0);
            }
            if (!$pesoCompleto) $pesoTotalKg = null;
        } catch (PDOException $e) {
            $pesoTotalKg = null; // sin peso_kg todavía — se sigue solo con totalUnidades
        }
    }
}

// Arma el bloque "asignacion" de la respuesta: quién se asignaría
// automáticamente (incluyendo su calificación de atención al cliente,
// que es uno de los factores que ya usa la selección — ver
// backend/delivery/_asignacion_automatica.php), con qué vehículo, y
// cuánto tardaría — o null si ahora mismo no hay nadie disponible (se
// iría a la cola de espera).
function previsualizarAsignacion(
    PDO $conexion, ?float $km, ?float $pesoTotalKg, ?int $totalUnidades, ?float $latEntrega, ?float $lonEntrega
): ?array {
    $candidato = seleccionarRepartidorYVehiculoAutomatico(
        $conexion, $km, false, $pesoTotalKg, $totalUnidades, $latEntrega, $lonEntrega
    );
    if (!$candidato) return null;

    $tiempo_estimado_minutos = $candidato['tiempo_estimado_minutos_sugerido'] ?? null;
    if ($tiempo_estimado_minutos === null && $km !== null) {
        $config = obtenerConfigDelivery($conexion);
        $tiempo_estimado_minutos = calcularTiempoEstimadoMinutos($km, $candidato['tipo_vehiculo'], $config);
    }
    $hora_estimada_llegada = $tiempo_estimado_minutos !== null
        ? (new DateTime())->modify("+{$tiempo_estimado_minutos} minutes")->format('Y-m-d H:i:s')
        : null;

    return [
        'repartidor_nombre'       => $candidato['nombre'],
        'vehiculo_tipo'           => $candidato['tipo_vehiculo'],
        'vehiculo_placa'          => $candidato['placa'],
        'calificacion_promedio'   => $candidato['calificacion_promedio'],
        'tiempo_estimado_minutos' => $tiempo_estimado_minutos,
        'hora_estimada_llegada'   => $hora_estimada_llegada,
        'agrupada'                => !empty($candidato['agrupada']),
    ];
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
        $km = haversineKm(
            (float)$sucursal['latitud'], (float)$sucursal['longitud'],
            (float)$direccion['latitud'], (float)$direccion['longitud']
        );
        $costo = round($km * $costoPorKm, 2);

        $asignacion = previsualizarAsignacion(
            $conexion, $km, $pesoTotalKg, $totalUnidades, (float) $direccion['latitud'], (float) $direccion['longitud']
        );
        echo json_encode([
            'success'              => true,
            'calculado'            => true,
            'km'                   => round($km, 2),
            'costo_por_km'         => $costoPorKm,
            'costo_envio'          => $costo,
            'detalle'              => round($km, 2) . " km × RD$" . number_format($costoPorKm, 2) . "/km",
            'asignacion'           => $asignacion,
            // Si nadie está disponible ahora, informativo: quién se
            // liberaría primero y aprox. cuándo (para que el cajero sepa
            // si conviene esperar un momento en vez de mandar a la cola).
            'proxima_disponibilidad' => $asignacion ? null : estimarProximaDisponibilidad($conexion, $km, $pesoTotalKg, $totalUnidades),
        ]);
    } else {
        // Sin coordenadas suficientes: usar el costo fijo por defecto.
        // Tampoco se puede calcular el tiempo estimado ni elegir el tipo
        // de vehículo ideal sin distancia — igual se intenta buscar quién
        // quedaría libre, con el orden más versátil posible.
        $asignacion = previsualizarAsignacion($conexion, null, $pesoTotalKg, $totalUnidades, null, null);
        echo json_encode([
            'success'                => true,
            'calculado'              => false,
            'costo_envio'            => $costoDefecto,
            'detalle'                => "Costo fijo (no hay coordenadas registradas para calcular por distancia)",
            'asignacion'             => $asignacion,
            'proxima_disponibilidad' => $asignacion ? null : estimarProximaDisponibilidad($conexion, null, $pesoTotalKg, $totalUnidades),
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
