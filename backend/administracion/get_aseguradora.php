<?php
require_once __DIR__ . '/../conexion.php';
header('Content-Type: application/json');
$id = $_GET['id'] ?? 0;
$stmt = $conexion->prepare("SELECT * FROM aseguradoras WHERE id_aseguradora = ?");
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode(['success' => (bool)$data] + ($data ?: []));
?>