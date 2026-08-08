<?php
require_once '../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion']) || !isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_compra = $input['id_compra'] ?? 0;
$items = $input['items'] ?? [];

if (!$id_compra || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$id_usuario = $_SESSION['id_usuario'];

try {
    $conexion->beginTransaction();

    // Obtener número de documento de la compra (para generar número de lote)
    $stmtCompra = $conexion->prepare("SELECT numero_documento FROM compras WHERE id_compra = ?");
    $stmtCompra->execute([$id_compra]);
    $compraData = $stmtCompra->fetch(PDO::FETCH_ASSOC);
    if (!$compraData) {
        throw new Exception("Compra no encontrada");
    }
    $numero_documento = $compraData['numero_documento'];

    $productos_completados = 0;

    foreach ($items as $item) {
        $id_detalle = (int)$item['id_detalle_compra'];
        $id_lote_original = isset($item['id_lote']) && !empty($item['id_lote']) ? (int)$item['id_lote'] : null;
        $cantidad_recibir = (int)$item['cantidad'];
        $distribuciones = $item['distribuciones'];

        // Obtener información del detalle de compra
        $stmt = $conexion->prepare("
            SELECT dc.cantidad, dc.cantidad_recibida, dc.id_medicamento, dc.precio_unitario
            FROM detalle_compra dc
            WHERE dc.id_detalle = ?
        ");
        $stmt->execute([$id_detalle]);
        $detalle = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$detalle) {
            throw new Exception("Detalle de compra no encontrado (ID: $id_detalle)");
        }

        $pendiente = $detalle['cantidad'] - $detalle['cantidad_recibida'];
        if ($cantidad_recibir > $pendiente) {
            throw new Exception("No se puede recibir más de lo pendiente para el producto ID $id_detalle. Pendiente: $pendiente, solicitado: $cantidad_recibir");
        }

        // Filtrar distribuciones válidas (sucursal > 0 y cantidad > 0)
        $distribuciones_validas = [];
        foreach ($distribuciones as $dist) {
            $id_sucursal = isset($dist['id_sucursal']) ? (int)$dist['id_sucursal'] : 0;
            $cantidad = isset($dist['cantidad']) ? (int)$dist['cantidad'] : 0;
            if ($id_sucursal > 0 && $cantidad > 0) {
                $distribuciones_validas[] = ['id_sucursal' => $id_sucursal, 'cantidad' => $cantidad];
            }
        }

        if (empty($distribuciones_validas)) {
            throw new Exception("Debe especificar al menos una sucursal válida para la recepción del producto.");
        }

        // Validar que la suma de cantidades distribuidas sea igual a la cantidad recibida
        $suma_dist = array_sum(array_column($distribuciones_validas, 'cantidad'));
        if ($suma_dist !== $cantidad_recibir) {
            throw new Exception("La suma de las cantidades distribuidas ($suma_dist) no coincide con la cantidad recibida ($cantidad_recibir).");
        }

        // Si no tiene lote, crear uno nuevo
        $id_lote = $id_lote_original;
        if (!$id_lote) {
            $numero_lote = 'LOTE-' . $id_compra . '-' . $id_detalle . '-' . time();
            $fecha_vencimiento = date('Y-m-d', strtotime('+2 years')); // por defecto 2 años

            $stmt = $conexion->prepare("
                INSERT INTO lotes (id_medicamento, numero_lote, fecha_vencimiento, cantidad_inicial, cantidad_actual, costo_unitario, estado, registro_por)
                VALUES (?, ?, ?, 0, 0, ?, 'ACTIVO', ?)
                RETURNING id_lote
            ");
            $stmt->execute([$detalle['id_medicamento'], $numero_lote, $fecha_vencimiento, $detalle['precio_unitario'], $id_usuario]);
            $id_lote = $stmt->fetchColumn();

            if (!$id_lote) {
                throw new Exception("No se pudo crear el lote para el medicamento ID {$detalle['id_medicamento']}.");
            }

            // Actualizar el detalle_compra con el nuevo lote
            $stmt = $conexion->prepare("UPDATE detalle_compra SET id_lote = ? WHERE id_detalle = ?");
            $stmt->execute([$id_lote, $id_detalle]);
        }

        // Actualizar cantidad recibida en detalle_compra
        $nueva_recibida = $detalle['cantidad_recibida'] + $cantidad_recibir;
        $stmt = $conexion->prepare("UPDATE detalle_compra SET cantidad_recibida = ? WHERE id_detalle = ?");
        $stmt->execute([$nueva_recibida, $id_detalle]);

        // Registrar inventario y movimientos en cada sucursal
        foreach ($distribuciones_validas as $dist) {
            $id_sucursal = $dist['id_sucursal'];
            $cantidad_sucursal = $dist['cantidad'];

            // Insertar o actualizar inventario
            $stmt = $conexion->prepare("
                INSERT INTO inventario (id_lote, id_sucursal, cantidad)
                VALUES (?, ?, ?)
                ON CONFLICT (id_lote, id_sucursal) DO UPDATE SET cantidad = inventario.cantidad + EXCLUDED.cantidad
            ");
            $stmt->execute([$id_lote, $id_sucursal, $cantidad_sucursal]);

            // Registrar movimiento de inventario (ENTRADA)
            $stmt = $conexion->prepare("
                INSERT INTO movimiento_inventario (id_lote, id_sucursal, tipo, cantidad, motivo, referencia, id_usuario)
                VALUES (?, ?, 'ENTRADA', ?, 'Recepción de compra', ?, ?)
            ");
            $stmt->execute([$id_lote, $id_sucursal, $cantidad_sucursal, $id_compra, $id_usuario]);

            // Actualizar cantidad_actual del lote
            $stmt = $conexion->prepare("UPDATE lotes SET cantidad_actual = cantidad_actual + ? WHERE id_lote = ?");
            $stmt->execute([$cantidad_sucursal, $id_lote]);
        }

        if ($nueva_recibida == $detalle['cantidad']) {
            $productos_completados++;
        }
    }

    // Actualizar estado de la compra según el progreso de recepción
    $stmt = $conexion->prepare("
        SELECT 
            COUNT(*) as total_productos,
            SUM(CASE WHEN cantidad_recibida >= cantidad THEN 1 ELSE 0 END) as completados
        FROM detalle_compra WHERE id_compra = ?
    ");
    $stmt->execute([$id_compra]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $nuevo_estado = null;
    if ($stats['completados'] == $stats['total_productos']) {
        $nuevo_estado = 4; // COMPLETADA (ajustar según tu tabla estado_compra)
    } elseif ($stats['completados'] > 0) {
        $nuevo_estado = 3; // PARCIAL
    }

    if ($nuevo_estado) {
        $stmt = $conexion->prepare("UPDATE compras SET id_estado = ? WHERE id_compra = ?");
        $stmt->execute([$nuevo_estado, $id_compra]);
    }

    $conexion->commit();
    echo json_encode(['success' => true, 'message' => 'Recepción registrada correctamente']);
} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>