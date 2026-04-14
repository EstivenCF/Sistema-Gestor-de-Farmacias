<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['nombre']) || empty($data['id_repartidor'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    $conexion->beginTransaction();
    
    // Actualizar repartidor (sin fecha_ingreso)
    $stmt = $conexion->prepare("
        UPDATE repartidores SET
            nombre = :nombre,
            tipo_identificacion = :tipo_identificacion,
            numero_identificacion = :numero_identificacion,
            direccion = :direccion,
            telefono_emergencia = :telefono_emergencia,
            licencia_conducir = :licencia_conducir,
            fecha_vencimiento_licencia = :fecha_vencimiento_licencia,
            foto_url = :foto_url,
            observaciones = :observaciones
        WHERE id_repartidor = :id_repartidor
    ");
    $stmt->execute([
        ':id_repartidor' => $data['id_repartidor'],
        ':nombre' => $data['nombre'],
        ':tipo_identificacion' => $data['tipo_identificacion'] ?? null,
        ':numero_identificacion' => $data['numero_identificacion'] ?? null,
        ':direccion' => $data['direccion'] ?? null,
        ':telefono_emergencia' => $data['telefono_emergencia'] ?? null,
        ':licencia_conducir' => $data['licencia_conducir'] ?? null,
        ':fecha_vencimiento_licencia' => $data['fecha_vencimiento_licencia'] ?? null,
        ':foto_url' => $data['foto_url'] ?? null,
        ':observaciones' => $data['observaciones'] ?? null
    ]);
    
    // Eliminar relaciones antiguas
    $stmtDelTel = $conexion->prepare("DELETE FROM repartidor_telefono WHERE id_repartidor = :id_repartidor");
    $stmtDelTel->execute([':id_repartidor' => $data['id_repartidor']]);
    $stmtDelCor = $conexion->prepare("DELETE FROM repartidor_correo WHERE id_repartidor = :id_repartidor");
    $stmtDelCor->execute([':id_repartidor' => $data['id_repartidor']]);
    
    // Insertar nuevos teléfonos
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
            $stmtRel->execute([':id_repartidor' => $data['id_repartidor'], ':id_telefono' => $id_telefono]);
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
                INSERT INTO repartidor_correo (id_repartidor, id_correo)
                VALUES (:id_repartidor, :id_correo)
            ");
            $stmtRelCor->execute([':id_repartidor' => $data['id_repartidor'], ':id_correo' => $id_correo]);
        }
    }
    
    $conexion->commit();
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>