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

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

$id_presentacion = isset($data['id_presentacion']) && $data['id_presentacion'] !== '' && $data['id_presentacion'] !== null ? (int)$data['id_presentacion'] : null;
$nombre = trim($data['nombre'] ?? '');
$descripcion = trim($data['descripcion'] ?? '');
$id_unidad = isset($data['id_unidad']) && $data['id_unidad'] !== '' && $data['id_unidad'] !== null ? (int)$data['id_unidad'] : null;
$activo = isset($data['activo']) ? (int)$data['activo'] : 1;
$categorias_asignadas = isset($data['categorias']) ? $data['categorias'] : []; // NUEVO

if (empty($nombre)) {
    echo json_encode(['success' => false, 'message' => 'El nombre de la presentación es requerido']);
    exit();
}

try {
    $conexion->beginTransaction();
    
    if ($id_presentacion) {
        // Actualizar presentación
        $sql = "UPDATE presentaciones SET nombre = :nombre, descripcion = :descripcion, id_unidad = :id_unidad, activo = :activo WHERE id_presentacion = :id";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre,
            ':descripcion' => $descripcion,
            ':id_unidad' => $id_unidad,
            ':activo' => $activo,
            ':id' => $id_presentacion
        ]);
        
        // Eliminar relaciones antiguas
        $stmt = $conexion->prepare("DELETE FROM categoria_presentacion WHERE id_presentacion = :id");
        $stmt->execute([':id' => $id_presentacion]);
        
    } else {
        // Verificar si ya existe
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM presentaciones WHERE nombre ILIKE :nombre");
        $stmt->execute([':nombre' => $nombre]);
        if ($stmt->fetchColumn() > 0) {
            $conexion->rollBack();
            echo json_encode(['success' => false, 'message' => 'Ya existe una presentación con ese nombre']);
            exit();
        }
        
        // Insertar nueva presentación
        $sql = "INSERT INTO presentaciones (nombre, descripcion, id_unidad, activo) VALUES (:nombre, :descripcion, :id_unidad, :activo) RETURNING id_presentacion";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre,
            ':descripcion' => $descripcion,
            ':id_unidad' => $id_unidad,
            ':activo' => $activo
        ]);
        $id_presentacion = $stmt->fetchColumn();
    }
    
    // Insertar nuevas relaciones con categorías
    if (!empty($categorias_asignadas)) {
        $stmt = $conexion->prepare("INSERT INTO categoria_presentacion (id_categoria, id_presentacion) VALUES (:id_categoria, :id_presentacion)");
        foreach ($categorias_asignadas as $id_categoria) {
            $stmt->execute([
                ':id_categoria' => (int)$id_categoria,
                ':id_presentacion' => $id_presentacion
            ]);
        }
    }
    
    $conexion->commit();
    echo json_encode(['success' => true, 'message' => $id_presentacion ? 'Presentación actualizada correctamente' : 'Presentación creada correctamente']);
    
} catch(PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>