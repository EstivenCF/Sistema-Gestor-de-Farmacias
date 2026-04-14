<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

$id_cliente = $_GET['id_cliente'] ?? null;
if (!$id_cliente) {
    echo json_encode(['success' => false, 'message' => 'ID de cliente requerido']);
    exit();
}

try {
    $stmt = $conexion->prepare("
        SELECT 
            id_cliente,
            nombre,
            direccion,
            barrio,
            ciudad,
            TO_CHAR(fecha_registro, 'DD/MM/YYYY') as fecha_registro,
            permite_credito,
            saldo_pendiente
        FROM clientes
        WHERE id_cliente = :id_cliente
    ");
    $stmt->execute([':id_cliente' => $id_cliente]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
        exit();
    }
    
    $stmtTel = $conexion->prepare("
        SELECT t.numero, t.tipo, t.whatsapp
        FROM telefonos t
        JOIN cliente_telefono ct ON t.id_telefono = ct.id_telefono
        WHERE ct.id_cliente = :id_cliente AND t.activo = TRUE
        ORDER BY t.tipo
    ");
    $stmtTel->execute([':id_cliente' => $id_cliente]);
    $telefonos = $stmtTel->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtCor = $conexion->prepare("
        SELECT c.email, c.tipo
        FROM correos c
        JOIN cliente_correo cc ON c.id_correo = cc.id_correo
        WHERE cc.id_cliente = :id_cliente AND c.activo = TRUE
        ORDER BY c.tipo
    ");
    $stmtCor->execute([':id_cliente' => $id_cliente]);
    $correos = $stmtCor->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'nombre' => $cliente['nombre'],
        'direccion' => $cliente['direccion'],
        'barrio' => $cliente['barrio'],
        'ciudad' => $cliente['ciudad'],
        'fecha_registro' => $cliente['fecha_registro'],
        'permite_credito' => filter_var($cliente['permite_credito'], FILTER_VALIDATE_BOOLEAN),
        'saldo_pendiente' => floatval($cliente['saldo_pendiente']),
        'telefonos' => $telefonos,
        'correos' => $correos
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>