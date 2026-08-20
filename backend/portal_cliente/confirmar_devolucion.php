<?php
// backend/portal_cliente/confirmar_devolucion.php
// NUEVO — El cliente, desde su Portal, confirma o niega una devolución que
// el repartidor registró en una de sus entregas. Mientras no responda, esa
// devolución no le llega al cajero/Inventario para aprobar o rechazar
// (ver el chequeo en backend/inventario/validar_devolucion.php).
//
// Si el cliente dice que NO es cierto, igual queda registrado su "No" y su
// nota — la devolución sigue llegando al cajero, pero marcada como
// disputa, para que la resuelva con esa información.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_cliente_portal'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']); exit();
}
$id_cliente = $_SESSION['id_cliente_portal'];

$data = json_decode(file_get_contents('php://input'), true);
$id_devolucion = intval($data['id_devolucion'] ?? 0);
$respuesta = $data['respuesta'] ?? ''; // 'SI' | 'NO'
$nota = trim($data['nota'] ?? '');

if (!$id_devolucion || !in_array($respuesta, ['SI', 'NO'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']); exit();
}
if ($respuesta === 'NO' && $nota === '') {
    echo json_encode(['success' => false, 'message' => 'Cuéntanos brevemente por qué no es correcto']); exit();
}

try {
    $conexion->beginTransaction();

    // Verdad del servidor: la devolución tiene que ser de una entrega que
    // de verdad es de este cliente, y todavía sin respuesta suya.
    $stmt = $conexion->prepare("
        SELECT d.id_devolucion, d.confirmado_por_cliente
        FROM devoluciones d
        JOIN entregas e ON e.id_entrega = d.id_entrega
        WHERE d.id_devolucion = :id AND e.id_cliente = :id_cliente
        FOR UPDATE OF d
    ");
    $stmt->execute([':id' => $id_devolucion, ':id_cliente' => $id_cliente]);
    $dev = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dev) { throw new Exception('Devolución no encontrada'); }
    if ($dev['confirmado_por_cliente'] !== null) {
        throw new Exception('Ya respondiste sobre esta devolución antes');
    }

    $conexion->prepare("
        UPDATE devoluciones
        SET confirmado_por_cliente = :confirmado,
            fecha_confirmacion_cliente = NOW(),
            nota_cliente = :nota
        WHERE id_devolucion = :id
    ")->execute([
        ':confirmado' => $respuesta === 'SI' ? 't' : 'f',
        ':nota'       => $nota !== '' ? $nota : null,
        ':id'         => $id_devolucion,
    ]);

    $conexion->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
