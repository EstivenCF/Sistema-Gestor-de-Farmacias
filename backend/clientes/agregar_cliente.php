<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

try {
    $conexion->beginTransaction();

    // Insertar cliente
    $stmt = $conexion->prepare("
        INSERT INTO clientes (nombre, direccion, barrio, ciudad, permite_credito, fecha_registro)
        VALUES (:nombre, :direccion, :barrio, :ciudad, :permite_credito, CURRENT_DATE)
        RETURNING id_cliente
    ");
    $stmt->execute([
        ':nombre' => $data['nombre'],
        ':direccion' => $data['direccion'] ?? null,
        ':barrio' => $data['barrio'] ?? null,
        ':ciudad' => $data['ciudad'] ?? 'Santiago',
        ':permite_credito' => $data['permite_credito'] ?? false
    ]);
    $id_cliente = $stmt->fetchColumn();

    // Insertar teléfonos
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

    // Insertar correos
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

    // Insertar direcciones
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
    echo json_encode(['success' => true, 'id_cliente' => $id_cliente]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>