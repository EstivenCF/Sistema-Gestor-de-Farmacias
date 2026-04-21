<?php
require_once '../conexion.php';
session_start();

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['id_sesion']) || !isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_venta = $input['id_venta'] ?? 0;
$motivo = trim($input['motivo'] ?? '');
$id_tipo = $input['id_tipo'] ?? 3; // Por defecto "Dañado"
$items = $input['items'] ?? [];

if (!$id_venta || empty($motivo) || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Limitar motivo a 200 caracteres (ajusta según tu esquema)
$motivo = substr($motivo, 0, 200);

$id_usuario = (int)$_SESSION['id_usuario'];

try {
    // Verificar que el usuario exista
    $stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    if (!$stmt->fetch()) {
        throw new Exception("Usuario inválido o no encontrado");
    }

    $conexion->beginTransaction();

    // Generar número de documento más corto (máx 20 caracteres)
    // Formato: DEV + año (2 dígitos) + mes (2) + día (2) + hora (2) + minuto (2) + segundo (2) + random (3)
    // Ejemplo: DEV250421123412345 -> 4 + 14 = 18 caracteres
    $numero_documento = 'DEV' . date('ymdHis') . rand(100, 999); // 4 + 12 + 3 = 19 caracteres

    // Insertar la devolución (cabecera)
    $stmt = $conexion->prepare("
        INSERT INTO devoluciones (
            numero_documento, id_venta, fecha_solicitud, motivo, 
            id_usuario, id_tipo, id_estado
        ) VALUES (?, ?, NOW(), ?, ?, ?, 1)
        RETURNING id_devolucion
    ");
    $stmt->execute([$numero_documento, $id_venta, $motivo, $id_usuario, $id_tipo]);
    $id_devolucion = $stmt->fetchColumn();

    if (!$id_devolucion) {
        throw new Exception("No se pudo registrar la devolución");
    }

    // Procesar cada producto
    foreach ($items as $item) {
        $id_detalle_venta = (int)$item['id_detalle_venta'];
        $cantidad = (int)$item['cantidad'];

        if ($cantidad <= 0) continue;

        // Obtener el lote y precio del detalle de venta
        $stmt = $conexion->prepare("
            SELECT id_lote, precio_unitario 
            FROM detalle_venta 
            WHERE id_detalle = ?
        ");
        $stmt->execute([$id_detalle_venta]);
        $detalle = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$detalle) {
            throw new Exception("Detalle de venta no encontrado");
        }

        $id_lote = $detalle['id_lote'];
        $precio_unitario = $detalle['precio_unitario'];

        // Insertar detalle de devolución
        $stmt = $conexion->prepare("
            INSERT INTO detalle_devolucion (id_devolucion, id_lote, cantidad, precio_unitario)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$id_devolucion, $id_lote, $cantidad, $precio_unitario]);

    }

    $conexion->commit();
    echo json_encode(['success' => true, 'message' => 'Solicitud de devolución registrada correctamente']);
} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>