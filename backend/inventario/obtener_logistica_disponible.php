<?php
/**
 * Redistribución inteligente — Paso 3 del wizard.
 * Devuelve los vehículos DISPONIBLES 
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

$id_sucursal_origen = isset($_GET['id_sucursal_origen']) ? (int)$_GET['id_sucursal_origen'] : 0;
$id_sucursal_destino = isset($_GET['id_sucursal_destino']) ? (int)$_GET['id_sucursal_destino'] : 0;
$cantidad = isset($_GET['cantidad']) ? (int)$_GET['cantidad'] : 1;

if (!$id_sucursal_origen || !$id_sucursal_destino) {
    echo json_encode(['success' => false, 'message' => 'Faltan id_sucursal_origen / id_sucursal_destino']);
    exit();
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

try {
    // ================================================================
    // CONSULTA SIMPLE - SIN NINGUNA RESTRICCIÓN COMPLEJA
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
            r.nombre AS repartidor_nombre
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

    // Si no hay vehículos, devolver un array vacío pero con éxito
    if (empty($vehiculos)) {
        echo json_encode([
            'success' => true,
            'hay_logistica_disponible' => false,
            'vehiculos' => [],
            'distancia_km' => 0,
            'tiempo_estimado_minutos' => null
        ]);
        exit();
    }

    // Calcular distancia (simulada si no hay coordenadas)
    $distanciaKm = 10;
    
    $vehiculosFormateados = [];
    foreach ($vehiculos as $v) {
        $capacidadCarga = (int)$v['capacidad_carga'];
        $capacidadSuficiente = $capacidadCarga === 0 || $capacidadCarga >= $cantidad;
        
        $rendimiento = (float)($v['rendimiento_km_por_galon'] ?? 20);
        $combustible = round($distanciaKm / max(1, $rendimiento), 2);
        $costoGalon = (float)($v['costo_galon_combustible'] ?? 250);
        $costo = round($combustible * $costoGalon, 2);
        $velocidad = (float)($v['velocidad_promedio_kmh'] ?? 40);
        $tiempoMinutos = (int)round(($distanciaKm / max(1, $velocidad)) * 60);
        
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

    // Calcular tiempo global
    $tiempoGlobal = null;
    if (!empty($vehiculosFormateados)) {
        $tiempoGlobal = $vehiculosFormateados[0]['tiempo_estimado_minutos'];
        foreach ($vehiculosFormateados as $v) {
            if ($v['tiempo_estimado_minutos'] < $tiempoGlobal) {
                $tiempoGlobal = $v['tiempo_estimado_minutos'];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'hay_logistica_disponible' => !empty($vehiculosFormateados),
        'vehiculos' => $vehiculosFormateados,
        'distancia_km' => $distanciaKm,
        'tiempo_estimado_minutos' => $tiempoGlobal,
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error en la consulta: ' . $e->getMessage()
    ]);
}
?>