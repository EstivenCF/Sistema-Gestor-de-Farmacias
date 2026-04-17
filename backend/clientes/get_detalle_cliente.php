<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$id_cliente = isset($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : 0;
if (!$id_cliente) {
    echo json_encode(['success' => false, 'message' => 'Cliente no especificado']);
    exit();
}

try {
    // Datos del cliente
    $stmt = $conexion->prepare("SELECT id_cliente, nombre, direccion, barrio, ciudad, permite_credito, saldo_pendiente, fecha_registro FROM clientes WHERE id_cliente = :id");
    $stmt->execute([':id' => $id_cliente]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cliente) {
        echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
        exit();
    }
    $cliente['fecha_registro'] = date('d/m/Y', strtotime($cliente['fecha_registro']));

    // Teléfonos
    $stmt = $conexion->prepare("
        SELECT t.numero, t.tipo, t.whatsapp
        FROM cliente_telefono ct
        JOIN telefonos t ON ct.id_telefono = t.id_telefono
        WHERE ct.id_cliente = :id AND t.activo = true
    ");
    $stmt->execute([':id' => $id_cliente]);
    $cliente['telefonos'] = $stmt->fetchAll();

    // Correos
    $stmt = $conexion->prepare("
        SELECT c.email, c.tipo
        FROM cliente_correo cc
        JOIN correos c ON cc.id_correo = c.id_correo
        WHERE cc.id_cliente = :id AND c.activo = true
    ");
    $stmt->execute([':id' => $id_cliente]);
    $cliente['correos'] = $stmt->fetchAll();

    // Direcciones
    $stmt = $conexion->prepare("
        SELECT d.id_direccion, d.direccion, d.barrio, d.ciudad, d.referencia, cd.predeterminada
        FROM cliente_direccion cd
        JOIN direcciones d ON cd.id_direccion = d.id_direccion
        WHERE cd.id_cliente = :id AND d.activo = true
        ORDER BY cd.predeterminada DESC
    ");
    $stmt->execute([':id' => $id_cliente]);
    $cliente['direcciones'] = $stmt->fetchAll();

    echo json_encode(['success' => true, 'cliente' => $cliente]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>