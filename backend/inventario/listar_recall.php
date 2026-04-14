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

$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$riesgo = isset($_GET['riesgo']) ? $_GET['riesgo'] : '';
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';
$offset = ($pagina - 1) * $limite;

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
        $where .= " AND (a.numero_alerta ILIKE :busqueda OR a.entidad_emisora ILIKE :busqueda OR a.descripcion ILIKE :busqueda OR l.numero_lote ILIKE :busqueda OR m.nombre ILIKE :busqueda)";
        $params[':busqueda'] = "%$busqueda%";
    }
    
    // Contar total
    $countSql = "SELECT COUNT(DISTINCT a.id_alerta) as total 
                 FROM alertas_sanitarias a
                 LEFT JOIN alerta_lote al ON a.id_alerta = al.id_alerta
                 LEFT JOIN lotes l ON al.id_lote = l.id_lote
                 LEFT JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
                 $where";
    $stmt = $conexion->prepare($countSql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener datos
    $sql = "SELECT a.*, 
                   COUNT(DISTINCT al.id_lote) as total_lotes
            FROM alertas_sanitarias a
            LEFT JOIN alerta_lote al ON a.id_alerta = al.id_alerta
            $where
            GROUP BY a.id_alerta
            ORDER BY a.fecha_notificacion DESC, a.id_alerta DESC
            LIMIT $limite OFFSET $offset";
    
    $stmt = $conexion->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $alertas = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'alertas' => $alertas,
        'total' => (int)$total,
        'pagina' => $pagina,
        'limite' => $limite
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>