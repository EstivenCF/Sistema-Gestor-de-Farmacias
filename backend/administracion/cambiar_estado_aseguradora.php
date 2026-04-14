<?php
require_once __DIR__ . '/../conexion.php';
$data = json_decode(file_get_contents('php://input'), true);
$stmt = $conexion->prepare("UPDATE aseguradoras SET activo = ? WHERE id_aseguradora = ?");
$stmt->execute([$data['activo'], $data['id_aseguradora']]);
echo json_encode(['success'=>true]);
?>