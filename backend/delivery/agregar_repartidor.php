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
    echo json_encode(['success' => false, 'message' => 'Nombre del repartidor es requerido']);
    exit();
}

try {
    $conexion->beginTransaction();
    
    $stmt = $conexion->prepare("
        INSERT INTO repartidores (
            nombre, tipo_identificacion, numero_identificacion, direccion,
            fecha_ingreso, telefono_emergencia, licencia_conducir,
            fecha_vencimiento_licencia, foto_url, observaciones, activo
        ) VALUES (
            :nombre, :tipo_identificacion, :numero_identificacion, :direccion,
            :fecha_ingreso, :telefono_emergencia, :licencia_conducir,
            :fecha_vencimiento_licencia, :foto_url, :observaciones, true
        )
        RETURNING id_repartidor
    ");
    $stmt->execute([
        ':nombre' => $data['nombre'],
        ':tipo_identificacion' => $data['tipo_identificacion'] ?? null,
        ':numero_identificacion' => $data['numero_identificacion'] ?? null,
        ':direccion' => $data['direccion'] ?? null,
        ':fecha_ingreso' => $data['fecha_ingreso'] ?? date('Y-m-d'),
        ':telefono_emergencia' => $data['telefono_emergencia'] ?? null,
        ':licencia_conducir' => $data['licencia_conducir'] ?? null,
        ':fecha_vencimiento_licencia' => $data['fecha_vencimiento_licencia'] ?? null,
        ':foto_url' => $data['foto_url'] ?? null,
        ':observaciones' => $data['observaciones'] ?? null
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $id_repartidor = $row['id_repartidor'];
    
    // Insertar teléfonos
    if (!empty($data['telefonos'])) {
        foreach ($data['telefonos'] as $tel) {
            if (empty($tel['numero'])) continue;
            $stmtTel = $conexion->prepare("
                INSERT INTO telefonos (numero, tipo, activo)
                VALUES (:numero, :tipo, true)
                RETURNING id_telefono
            ");
            $stmtTel->execute([
                ':numero' => $tel['numero'],
                ':tipo' => $tel['tipo'] ?? 'PRINCIPAL'
            ]);
            $id_telefono = $stmtTel->fetchColumn();
            
            $stmtRel = $conexion->prepare("
                INSERT INTO repartidor_telefono (id_repartidor, id_telefono)
                VALUES (:id_repartidor, :id_telefono)
            ");
            $stmtRel->execute([':id_repartidor' => $id_repartidor, ':id_telefono' => $id_telefono]);
        }
    }
    
    // Insertar correos
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
                INSERT INTO repartidor_correo (id_repartidor, id_correo)
                VALUES (:id_repartidor, :id_correo)
            ");
            $stmtRelCor->execute([':id_repartidor' => $id_repartidor, ':id_correo' => $id_correo]);
        }
    }
    
    $conexion->commit();
    echo json_encode(['success' => true, 'id_repartidor' => $id_repartidor]);
    
} catch (PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>