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

$id_categoria = isset($_GET['id_categoria']) ? (int)$_GET['id_categoria'] : 0;

if (!$id_categoria) {
    echo json_encode(['success' => false, 'message' => 'ID de categoría inválido']);
    exit();
}

try {
    // Obtener presentaciones asociadas a esta categoría
    $sql = "SELECT p.id_presentacion, p.nombre, p.descripcion, u.abreviatura as unidad_abrev
            FROM presentaciones p
            INNER JOIN categoria_presentacion cp ON cp.id_presentacion = p.id_presentacion
            LEFT JOIN unidades_medida u ON u.id_unidad = p.id_unidad
            WHERE cp.id_categoria = :id_categoria AND p.activo = true
            ORDER BY p.nombre";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id_categoria' => $id_categoria]);
    $presentaciones = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'presentaciones' => $presentaciones
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>