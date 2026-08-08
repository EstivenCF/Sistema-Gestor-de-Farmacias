<?php
require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    // Usar la función de PostgreSQL obtener_repartidores_disponibles()
    $stmt = $conexion->query("SELECT id_repartidor, nombre, entregas_activas, telefono FROM obtener_repartidores_disponibles()");
    $repartidores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'repartidores' => $repartidores
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en la consulta: ' . $e->getMessage()
    ]);
}
?>