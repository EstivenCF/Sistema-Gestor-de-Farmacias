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

$id_alerta = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_alerta) {
    echo json_encode(['success' => false, 'message' => 'ID de alerta inválido']);
    exit();
}

try {
    // Información principal de la alerta
    $sql = "SELECT a.* 
            FROM alertas_sanitarias a
            WHERE a.id_alerta = :id";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id' => $id_alerta]);
    $alerta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($alerta) {
        // Obtener lotes afectados
        $sql = "SELECT al.id_lote, l.numero_lote, l.fecha_vencimiento, l.estado,
                       m.id_medicamento, m.nombre as medicamento_nombre, m.concentracion,
                       p.nombre as presentacion,
                       u.abreviatura as unidad_abrev
                FROM alerta_lote al
                JOIN lotes l ON al.id_lote = l.id_lote
                JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
                LEFT JOIN presentaciones p ON m.id_presentacion = p.id_presentacion
                LEFT JOIN unidades_medida u ON m.id_unidad = u.id_unidad
                WHERE al.id_alerta = :id
                ORDER BY m.nombre ASC";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([':id' => $id_alerta]);
        $alerta['lotes'] = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'alerta' => $alerta]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Alerta no encontrada']);
    }
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>