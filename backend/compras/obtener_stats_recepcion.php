<?php
require_once __DIR__ . '/../conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

try {
    $stmt = $conexion->query("SELECT COUNT(*) FROM compras WHERE id_estado IN (1, 3)");
    $pendientes = $stmt->fetchColumn();
    
    $stmt = $conexion->query("SELECT COUNT(*) FROM recepciones WHERE DATE(fecha_recepcion) = CURRENT_DATE");
    $hoy = $stmt->fetchColumn();
    
    $stmt = $conexion->query("SELECT COUNT(*) FROM compras WHERE id_estado = 3");
    $parciales = $stmt->fetchColumn();
    
    $stmt = $conexion->query("SELECT COUNT(*) FROM compras WHERE id_estado = 4 AND EXTRACT(MONTH FROM fecha) = EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM fecha) = EXTRACT(YEAR FROM CURRENT_DATE)");
    $completadas = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'total_pendientes' => (int)$pendientes,
        'recibidas_hoy' => (int)$hoy,
        'recepciones_parciales' => (int)$parciales,
        'completadas_mes' => (int)$completadas
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>