<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

require_once __DIR__ . '/../conexion.php';

$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$riesgo = isset($_GET['riesgo']) ? $_GET['riesgo'] : '';
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';

try {
    $where = "WHERE 1=1";
    $params = [];
    
    if (!empty($fecha_desde)) {
        $where .= " AND a.fecha_notificacion >= :fecha_desde";
        $params[':fecha_desde'] = $fecha_desde;
    }
    
    if (!empty($fecha_hasta)) {
        $where .= " AND a.fecha_notificacion <= :fecha_hasta";
        $params[':fecha_hasta'] = $fecha_hasta;
    }
    
    if (!empty($riesgo)) {
        $where .= " AND a.nivel_riesgo = :riesgo";
        $params[':riesgo'] = $riesgo;
    }
    
    if (!empty($estado)) {
        $where .= " AND a.estado = :estado";
        $params[':estado'] = $estado;
    }
    
    if (!empty($busqueda)) {
        $where .= " AND (a.numero_alerta ILIKE :busqueda OR a.entidad_emisora ILIKE :busqueda OR a.descripcion ILIKE :busqueda)";
        $params[':busqueda'] = "%$busqueda%";
    }
    
    // Alertas activas
    $whereActivas = $where . " AND a.estado = 'ACTIVA'";
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM alertas_sanitarias a $whereActivas");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $activas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Alertas de alto riesgo
    $whereAlto = $where . " AND a.nivel_riesgo = 'ALTO'";
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM alertas_sanitarias a $whereAlto");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $alto_riesgo = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Alertas resueltas
    $whereResueltas = $where . " AND a.estado = 'RESUELTA'";
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM alertas_sanitarias a $whereResueltas");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $resueltas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de lotes afectados
    $sql = "SELECT COUNT(DISTINCT al.id_lote) as total 
            FROM alerta_lote al
            JOIN alertas_sanitarias a ON al.id_alerta = a.id_alerta
            $where";
    $stmt = $conexion->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $lotes_afectados = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'activas' => (int)$activas,
        'alto_riesgo' => (int)$alto_riesgo,
        'resueltas' => (int)$resueltas,
        'lotes_afectados' => (int)$lotes_afectados
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'activas' => 0,
        'alto_riesgo' => 0,
        'resueltas' => 0,
        'lotes_afectados' => 0
    ]);
}
?>