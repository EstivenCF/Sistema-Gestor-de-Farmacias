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

$id_categoria = isset($data['id_categoria']) && $data['id_categoria'] ? (int)$data['id_categoria'] : null;
$nombre = trim($data['nombre'] ?? '');
$descripcion = trim($data['descripcion'] ?? '');

if (empty($nombre)) {
    echo json_encode(['success' => false, 'message' => 'El nombre de la categoría es requerido']);
    exit();
}

try {
    if ($id_categoria) {
        // Actualizar
        $sql = "UPDATE categorias SET nombre = :nombre, descripcion = :descripcion WHERE id_categoria = :id";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre,
            ':descripcion' => $descripcion,
            ':id' => $id_categoria
        ]);
        echo json_encode(['success' => true, 'message' => 'Categoría actualizada correctamente']);
    } else {
        // Verificar si ya existe
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM categorias WHERE nombre ILIKE :nombre");
        $stmt->execute([':nombre' => $nombre]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Ya existe una categoría con ese nombre']);
            exit();
        }
        
        // Insertar nueva
        $sql = "INSERT INTO categorias (nombre, descripcion) VALUES (:nombre, :descripcion)";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre,
            ':descripcion' => $descripcion
        ]);
        echo json_encode(['success' => true, 'message' => 'Categoría creada correctamente']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>