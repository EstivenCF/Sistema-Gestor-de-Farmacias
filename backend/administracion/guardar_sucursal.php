<?php
require_once __DIR__ . '/../conexion.php';
session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

$id_sucursal = $data['id_sucursal'] ?? null;
$nombre = trim($data['nombre'] ?? '');
$direccion = trim($data['direccion'] ?? '');
$telefono = trim($data['telefono'] ?? '');
$id_empresa = $data['id_empresa'] ?? 1;
$estado = isset($data['estado']) ? ($data['estado'] ? 'true' : 'false') : 'true';

if (empty($nombre)) {
    echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
    exit();
}

try {
    if ($id_sucursal) {
        $stmt = $conexion->prepare("
            UPDATE sucursales 
            SET nombre = ?, direccion = ?, telefono = ?, id_empresa = ?, estado = ?::boolean
            WHERE id_sucursal = ?
        ");
        $stmt->execute([$nombre, $direccion, $telefono, $id_empresa, $estado, $id_sucursal]);
        echo json_encode(['success' => true]);
    } else {
        $stmt = $conexion->prepare("
            INSERT INTO sucursales (id_empresa, nombre, direccion, telefono, estado)
            VALUES (?, ?, ?, ?, ?::boolean)
            RETURNING id_sucursal
        ");
        $stmt->execute([$id_empresa, $nombre, $direccion, $telefono, $estado]);
        $id = $stmt->fetchColumn();
        echo json_encode(['success' => true, 'id_sucursal' => $id]);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>