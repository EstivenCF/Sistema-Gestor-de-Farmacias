<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['id_cliente'])) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

$id_cliente = (int)$data['id_cliente'];

try {
    $conexion->beginTransaction();

    // Actualizar datos básicos del cliente
    $stmt = $conexion->prepare("
        UPDATE clientes SET
            nombre = :nombre,
            direccion = :direccion,
            barrio = :barrio,
            ciudad = :ciudad,
            permite_credito = :permite_credito
        WHERE id_cliente = :id
    ");
    $stmt->execute([
        ':nombre' => $data['nombre'],
        ':direccion' => $data['direccion'] ?? null,
        ':barrio' => $data['barrio'] ?? null,
        ':ciudad' => $data['ciudad'] ?? 'Santiago',
        ':permite_credito' => $data['permite_credito'] ?? false,
        ':id' => $id_cliente
    ]);

    // Reemplazar teléfonos
    $stmtDelTel = $conexion->prepare("DELETE FROM cliente_telefono WHERE id_cliente = :id");
    $stmtDelTel->execute([':id' => $id_cliente]);
    if (!empty($data['telefonos'])) {
        $stmtTel = $conexion->prepare("INSERT INTO telefonos (numero, tipo, whatsapp, activo) VALUES (:numero, :tipo, :whatsapp, TRUE) RETURNING id_telefono");
        $stmtRel = $conexion->prepare("INSERT INTO cliente_telefono (id_cliente, id_telefono) VALUES (:id_cliente, :id_telefono)");
        foreach ($data['telefonos'] as $tel) {
            $stmtTel->execute([
                ':numero' => $tel['numero'],
                ':tipo' => $tel['tipo'],
                ':whatsapp' => $tel['whatsapp'] ?? false
            ]);
            $id_telefono = $stmtTel->fetchColumn();
            $stmtRel->execute([':id_cliente' => $id_cliente, ':id_telefono' => $id_telefono]);
        }
    }

    // Reemplazar correos
    $stmtDelCor = $conexion->prepare("DELETE FROM cliente_correo WHERE id_cliente = :id");
    $stmtDelCor->execute([':id' => $id_cliente]);
    if (!empty($data['correos'])) {
        $stmtCor = $conexion->prepare("INSERT INTO correos (email, tipo, activo) VALUES (:email, :tipo, TRUE) RETURNING id_correo");
        $stmtRelCor = $conexion->prepare("INSERT INTO cliente_correo (id_cliente, id_correo) VALUES (:id_cliente, :id_correo)");
        foreach ($data['correos'] as $cor) {
            $stmtCor->execute([
                ':email' => $cor['email'],
                ':tipo' => $cor['tipo']
            ]);
            $id_correo = $stmtCor->fetchColumn();
            $stmtRelCor->execute([':id_cliente' => $id_cliente, ':id_correo' => $id_correo]);
        }
    }

    // Reemplazar direcciones
    $stmtDelDir = $conexion->prepare("DELETE FROM cliente_direccion WHERE id_cliente = :id");
    $stmtDelDir->execute([':id' => $id_cliente]);
    if (!empty($data['direcciones'])) {
        $stmtDir = $conexion->prepare("INSERT INTO direcciones (direccion, barrio, ciudad, referencia, activo) VALUES (:direccion, :barrio, :ciudad, :referencia, TRUE) RETURNING id_direccion");
        $stmtRelDir = $conexion->prepare("INSERT INTO cliente_direccion (id_cliente, id_direccion, predeterminada) VALUES (:id_cliente, :id_direccion, :predeterminada)");
        foreach ($data['direcciones'] as $dir) {
            $stmtDir->execute([
                ':direccion' => $dir['direccion'],
                ':barrio' => $dir['barrio'] ?? null,
                ':ciudad' => $dir['ciudad'] ?? 'Santiago',
                ':referencia' => $dir['referencia'] ?? null
            ]);
            $id_direccion = $stmtDir->fetchColumn();
            $stmtRelDir->execute([
                ':id_cliente' => $id_cliente,
                ':id_direccion' => $id_direccion,
                ':predeterminada' => $dir['predeterminada'] ?? false
            ]);
        }
    }

    $conexion->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>