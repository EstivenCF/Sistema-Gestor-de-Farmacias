<?php
require_once __DIR__ . '/../conexion.php';
header('Content-Type: application/json');
$id = $_GET['id'] ?? 0;
$stmt = $conexion->prepare("SELECT * FROM descuentos WHERE id_descuento = ?");
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode(['success' => (bool)$data] + ($data ?: []));
?>