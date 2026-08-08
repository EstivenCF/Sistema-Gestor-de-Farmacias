<?php
// backend/delivery/get_despacho_detalle.php
// NUEVO — para que el repartidor vea qué se despachó (P-2) antes de
// confirmar cuánto entregó de verdad.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$id_entrega = intval($_GET['id_entrega'] ?? 0);
$id_usuario = $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? 0);

if (!$id_entrega) {
    echo json_encode(['success' => false, 'message' => 'ID de entrega requerido']); exit();
}

try {
    // Solo puede ver el despacho de su propia entrega
    $stmt = $conexion->prepare("
        SELECT de.id_despacho
        FROM entregas e
        JOIN repartidores r ON r.id_repartidor = e.id_repartidor
        LEFT JOIN despacho_entrega de ON de.id_entrega = e.id_entrega
        WHERE e.id_entrega = :id AND r.id_usuario = :id_usuario
    ");
    $stmt->execute([':id' => $id_entrega, ':id_usuario' => $id_usuario]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Esta entrega no está asignada a ti']); exit();
    }
    if (!$row['id_despacho']) {
        echo json_encode(['success' => false, 'message' => 'Esta entrega todavía no tiene un despacho registrado']); exit();
    }

    $stmt = $conexion->prepare("
        SELECT
            dd.id_lote, dd.id_producto, dd.cantidad_despachada,
            l.numero_lote,
            COALESCE(m.nombre_completo, m.nombre, p.nombre) AS producto_nombre
        FROM detalle_despacho dd
        LEFT JOIN lotes l ON l.id_lote = dd.id_lote
        LEFT JOIN medicamentos m ON m.id_medicamento = l.id_medicamento
        LEFT JOIN productos p ON p.id_producto = dd.id_producto
        WHERE dd.id_despacho = :id_despacho
        ORDER BY dd.id_detalle
    ");
    $stmt->execute([':id_despacho' => $row['id_despacho']]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'productos' => $productos]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
