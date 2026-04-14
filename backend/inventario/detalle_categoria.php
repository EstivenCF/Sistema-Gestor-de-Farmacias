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

$id_categoria = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_categoria) {
    echo json_encode(['success' => false, 'message' => 'ID de categoría inválido']);
    exit();
}

try {
    $sql = "SELECT c.*, 
                   COUNT(DISTINCT m.id_medicamento) as total_medicamentos,
                   COUNT(DISTINCT l.id_laboratorio) as total_laboratorios
            FROM categorias c
            LEFT JOIN medicamentos m ON m.id_categoria = c.id_categoria
            LEFT JOIN laboratorios l ON m.id_laboratorio = l.id_laboratorio
            WHERE c.id_categoria = :id
            GROUP BY c.id_categoria";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id' => $id_categoria]);
    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($categoria) {
        // Obtener medicamentos de esta categoría con su precio desde productos
        $stmt = $conexion->prepare("SELECT m.id_medicamento, m.nombre, m.concentracion, p.precio 
                                     FROM medicamentos m
                                     INNER JOIN productos p ON p.id_producto = m.id_producto
                                     WHERE m.id_categoria = :id 
                                     ORDER BY m.nombre
                                     LIMIT 5");
        $stmt->execute([':id' => $id_categoria]);
        $categoria['medicamentos_recientes'] = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'categoria' => $categoria]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Categoría no encontrada']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la consulta: ' . $e->getMessage()]);
}
?>