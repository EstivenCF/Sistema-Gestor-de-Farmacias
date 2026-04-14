<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['nombre']) || empty($data['id_cliente'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    $conexion->beginTransaction();
    
    // Actualizar cliente (sin fecha_registro)
    $stmt = $conexion->prepare("
        UPDATE clientes SET
            nombre = :nombre,
            direccion = :direccion,
            barrio = :barrio,
            ciudad = :ciudad,
            permite_credito = :permite_credito
        WHERE id_cliente = :id_cliente
    ");
    $stmt->execute([
        ':id_cliente' => $data['id_cliente'],
        ':nombre' => $data['nombre'],
        ':direccion' => $data['direccion'] ?? null,
        ':barrio' => $data['barrio'] ?? null,
        ':ciudad' => $data['ciudad'] ?? 'Santiago',
        ':permite_credito' => isset($data['permite_credito']) ? ($data['permite_credito'] ? 't' : 'f') : 'f'
    ]);
    
    // Eliminar relaciones antiguas
    $stmtDelTel = $conexion->prepare("DELETE FROM cliente_telefono WHERE id_cliente = :id_cliente");
    $stmtDelTel->execute([':id_cliente' => $data['id_cliente']]);
    $stmtDelCor = $conexion->prepare("DELETE FROM cliente_correo WHERE id_cliente = :id_cliente");
    $stmtDelCor->execute([':id_cliente' => $data['id_cliente']]);
    
    // Insertar nuevos teléfonos
    if (!empty($data['telefonos'])) {
        foreach ($data['telefonos'] as $tel) {
            if (empty($tel['numero'])) continue;
            $stmtTel = $conexion->prepare("
                INSERT INTO telefonos (numero, tipo, whatsapp, activo)
                VALUES (:numero, :tipo, :whatsapp, true)
                RETURNING id_telefono
            ");
            $stmtTel->execute([
                ':numero' => $tel['numero'],
                ':tipo' => $tel['tipo'] ?? 'PRINCIPAL',
                ':whatsapp' => isset($tel['whatsapp']) ? ($tel['whatsapp'] ? 't' : 'f') : 'f'
            ]);
            $id_telefono = $stmtTel->fetchColumn();
            
            $stmtRel = $conexion->prepare("
                INSERT INTO cliente_telefono (id_cliente, id_telefono)
                VALUES (:id_cliente, :id_telefono)
            ");
            $stmtRel->execute([':id_cliente' => $data['id_cliente'], ':id_telefono' => $id_telefono]);
        }
    }
    
    // Insertar nuevos correos
    if (!empty($data['correos'])) {
        foreach ($data['correos'] as $cor) {
            if (empty($cor['email'])) continue;
            $stmtCor = $conexion->prepare("
                INSERT INTO correos (email, tipo, activo, verificado)
                VALUES (:email, :tipo, true, false)
                RETURNING id_correo
            ");
            $stmtCor->execute([
                ':email' => $cor['email'],
                ':tipo' => $cor['tipo'] ?? 'PRINCIPAL'
            ]);
            $id_correo = $stmtCor->fetchColumn();
            
            $stmtRelCor = $conexion->prepare("
                INSERT INTO cliente_correo (id_cliente, id_correo)
                VALUES (:id_cliente, :id_correo)
            ");
            $stmtRelCor->execute([':id_cliente' => $data['id_cliente'], ':id_correo' => $id_correo]);
        }
    }
    
    $conexion->commit();
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>