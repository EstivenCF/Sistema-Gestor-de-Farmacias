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

$id_presentacion = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_presentacion) {
    echo json_encode(['success' => false, 'message' => 'ID de presentación inválido']);
    exit();
}

try {
    $sql = "SELECT p.*, 
                   u.nombre as unidad_nombre,
                   u.abreviatura as unidad_abrev,
                   COUNT(DISTINCT m.id_medicamento) as total_medicamentos
            FROM presentaciones p
            LEFT JOIN unidades_medida u ON u.id_unidad = p.id_unidad
            LEFT JOIN medicamentos m ON m.id_presentacion = p.id_presentacion
            WHERE p.id_presentacion = :id
            GROUP BY p.id_presentacion, u.id_unidad";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id' => $id_presentacion]);
    $presentacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($presentacion) {
        // Obtener medicamentos con esta presentación
        $stmt = $conexion->prepare("SELECT m.id_medicamento, m.nombre, m.concentracion, p.precio 
                                     FROM medicamentos m
                                     INNER JOIN productos p ON p.id_producto = m.id_producto
                                     WHERE m.id_presentacion = :id 
                                     ORDER BY m.nombre
                                     LIMIT 5");
        $stmt->execute([':id' => $id_presentacion]);
        $presentacion['medicamentos_recientes'] = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'presentacion' => $presentacion]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Presentación no encontrada']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la consulta: ' . $e->getMessage()]);
}
?>