<?php
require_once __DIR__ . '/../conexion.php';
$data = json_decode(file_get_contents('php://input'), true);
$stmt = $conexion->prepare("UPDATE descuentos SET activo = ? WHERE id_descuento = ?");
$stmt->execute([$data['activo'], $data['id_descuento']]);
echo json_encode(['success'=>true]);
?>