<?php
require_once __DIR__ . '/../conexion.php';
session_start();
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) { echo json_encode(['success'=>false,'message'=>'Datos inválidos']); exit; }
if (empty($data['id_descuento'])) {
    $stmt = $conexion->prepare("INSERT INTO descuentos (codigo, nombre, id_tipo_descuento, valor_descuento, es_porcentaje, fecha_inicio, fecha_fin, descripcion, monto_minimo_compra, activo, creado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$data['codigo'], $data['nombre'], $data['id_tipo_descuento'], $data['valor_descuento'], $data['es_porcentaje'], $data['fecha_inicio'], $data['fecha_fin'], $data['descripcion'], $data['monto_minimo_compra'], $data['activo'], $_SESSION['id_usuario']]);
} else {
    $stmt = $conexion->prepare("UPDATE descuentos SET codigo=?, nombre=?, id_tipo_descuento=?, valor_descuento=?, es_porcentaje=?, fecha_inicio=?, fecha_fin=?, descripcion=?, monto_minimo_compra=?, activo=? WHERE id_descuento=?");
    $stmt->execute([$data['codigo'], $data['nombre'], $data['id_tipo_descuento'], $data['valor_descuento'], $data['es_porcentaje'], $data['fecha_inicio'], $data['fecha_fin'], $data['descripcion'], $data['monto_minimo_compra'], $data['activo'], $data['id_descuento']]);
}
echo json_encode(['success'=>true]);
?>