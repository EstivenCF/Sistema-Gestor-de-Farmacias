<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['nombre'])) {
    echo json_encode(['success' => false, 'message' => 'Nombre del cliente es requerido']);
    exit();
}

try {
    $conexion->beginTransaction();
    
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
        ':permite_credito' => isset($data['permite_credito']) ? ($data['permite_credito'] ? 't' : 'f') : 'f'
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $id_cliente = $row['id_cliente'];
    
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
            $stmtRel->execute([':id_cliente' => $id_cliente, ':id_telefono' => $id_telefono]);
        }
    }
    
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
            $stmtRelCor->execute([':id_cliente' => $id_cliente, ':id_correo' => $id_correo]);
        }
    }
    
    $conexion->commit();
    echo json_encode(['success' => true, 'id_cliente' => $id_cliente]);
    
} catch (PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>