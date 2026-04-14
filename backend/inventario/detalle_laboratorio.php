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

$id_laboratorio = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_laboratorio) {
    echo json_encode(['success' => false, 'message' => 'ID de laboratorio inválido']);
    exit();
}

try {
    $sql = "SELECT l.*, 
                   COUNT(DISTINCT m.id_medicamento) as total_medicamentos,
                   COUNT(DISTINCT m.id_categoria) as total_categorias
            FROM laboratorios l
            LEFT JOIN medicamentos m ON m.id_laboratorio = l.id_laboratorio
            WHERE l.id_laboratorio = :id
            GROUP BY l.id_laboratorio";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id' => $id_laboratorio]);
    $laboratorio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($laboratorio) {
        // Obtener medicamentos de este laboratorio
        $stmt = $conexion->prepare("SELECT m.id_medicamento, m.nombre, m.concentracion, p.precio, cat.nombre as categoria
                                     FROM medicamentos m
                                     INNER JOIN productos p ON p.id_producto = m.id_producto
                                     LEFT JOIN categorias cat ON m.id_categoria = cat.id_categoria
                                     WHERE m.id_laboratorio = :id 
                                     ORDER BY m.nombre
                                     LIMIT 5");
        $stmt->execute([':id' => $id_laboratorio]);
        $laboratorio['medicamentos_recientes'] = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'laboratorio' => $laboratorio]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Laboratorio no encontrado']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la consulta: ' . $e->getMessage()]);
}
?>