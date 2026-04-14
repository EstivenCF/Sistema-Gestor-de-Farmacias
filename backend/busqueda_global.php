<?php
session_start();
require_once __DIR__ . '/conexion.php';

$q = $_GET['q'] ?? '';

$response = [
    'success' => true,
    'medicamentos' => [],
    'clientes' => [],
    'ventas' => []
];

if ($q !== '') {

    // MEDICAMENTOS
    $stmt = $conexion->prepare("
        SELECT nombre 
        FROM medicamentos 
        WHERE nombre ILIKE ? 
        ORDER BY 
            CASE 
                WHEN nombre ILIKE ? THEN 1
                ELSE 2
            END,
            nombre
        LIMIT 5
    ");
    $stmt->execute(["%$q%", "$q%"]);
    $response['medicamentos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // CLIENTES
    $stmt = $conexion->prepare("
        SELECT nombre 
        FROM clientes 
        WHERE nombre ILIKE ? 
        LIMIT 5
    ");
    $stmt->execute(["%$q%"]);
    $response['clientes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode($response);
