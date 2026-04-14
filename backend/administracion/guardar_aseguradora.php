<?php
require_once __DIR__ . '/../conexion.php';
session_start();
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) { echo json_encode(['success'=>false,'message'=>'Datos inválidos']); exit; }
if (empty($data['id_aseguradora'])) {
    $stmt = $conexion->prepare("INSERT INTO aseguradoras (codigo, nombre, rnc, telefono, email, direccion, porcentaje_cobertura_default, activo, observaciones) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$data['codigo'], $data['nombre'], $data['rnc'], $data['telefono'], $data['email'], $data['direccion'], $data['porcentaje_cobertura_default'], $data['activo'], $data['observaciones']]);
} else {
    $stmt = $conexion->prepare("UPDATE aseguradoras SET codigo=?, nombre=?, rnc=?, telefono=?, email=?, direccion=?, porcentaje_cobertura_default=?, activo=?, observaciones=? WHERE id_aseguradora=?");
    $stmt->execute([$data['codigo'], $data['nombre'], $data['rnc'], $data['telefono'], $data['email'], $data['direccion'], $data['porcentaje_cobertura_default'], $data['activo'], $data['observaciones'], $data['id_aseguradora']]);
}
echo json_encode(['success'=>true]);
?>