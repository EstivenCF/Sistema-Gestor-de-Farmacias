<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

$id_repartidor = $_GET['id_repartidor'] ?? null;
if (!$id_repartidor) {
    echo json_encode(['success' => false, 'message' => 'ID de repartidor requerido']);
    exit();
}

try {
    $stmt = $conexion->prepare("
        SELECT 
            id_repartidor,
            nombre,
            tipo_identificacion,
            numero_identificacion,
            direccion,
            TO_CHAR(fecha_ingreso, 'DD/MM/YYYY') as fecha_ingreso,
            fecha_ingreso as fecha_ingreso_raw,
            telefono_emergencia,
            licencia_conducir,
            TO_CHAR(fecha_vencimiento_licencia, 'DD/MM/YYYY') as fecha_vencimiento_licencia,
            fecha_vencimiento_licencia as fecha_vencimiento_licencia_raw,
            foto_url,
            observaciones,
            activo
        FROM repartidores
        WHERE id_repartidor = :id_repartidor
    ");
    $stmt->execute([':id_repartidor' => $id_repartidor]);
    $repartidor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$repartidor) {
        echo json_encode(['success' => false, 'message' => 'Repartidor no encontrado']);
        exit();
    }
    
    // Teléfonos
    $stmtTel = $conexion->prepare("
        SELECT t.numero, t.tipo
        FROM telefonos t
        JOIN repartidor_telefono rt ON t.id_telefono = rt.id_telefono
        WHERE rt.id_repartidor = :id_repartidor AND t.activo = TRUE
    ");
    $stmtTel->execute([':id_repartidor' => $id_repartidor]);
    $telefonos = $stmtTel->fetchAll(PDO::FETCH_ASSOC);
    
    // Correos
    $stmtCor = $conexion->prepare("
        SELECT c.email, c.tipo
        FROM correos c
        JOIN repartidor_correo rc ON c.id_correo = rc.id_correo
        WHERE rc.id_repartidor = :id_repartidor AND c.activo = TRUE
    ");
    $stmtCor->execute([':id_repartidor' => $id_repartidor]);
    $correos = $stmtCor->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'nombre' => $repartidor['nombre'],
        'tipo_identificacion' => $repartidor['tipo_identificacion'],
        'numero_identificacion' => $repartidor['numero_identificacion'],
        'direccion' => $repartidor['direccion'],
        'fecha_ingreso' => $repartidor['fecha_ingreso'],
        'fecha_ingreso_raw' => $repartidor['fecha_ingreso_raw'],
        'telefono_emergencia' => $repartidor['telefono_emergencia'],
        'licencia_conducir' => $repartidor['licencia_conducir'],
        'fecha_vencimiento_licencia' => $repartidor['fecha_vencimiento_licencia'],
        'fecha_vencimiento_licencia_raw' => $repartidor['fecha_vencimiento_licencia_raw'],
        'foto_url' => $repartidor['foto_url'],
        'observaciones' => $repartidor['observaciones'],
        'activo' => filter_var($repartidor['activo'], FILTER_VALIDATE_BOOLEAN),
        'telefonos' => $telefonos,
        'correos' => $correos
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>