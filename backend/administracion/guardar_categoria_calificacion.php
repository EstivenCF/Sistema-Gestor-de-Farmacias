<?php
// backend/administracion/guardar_categoria_calificacion.php
// NUEVO — crea, renombra, reordena o activa/desactiva una sección
// (categoría) del formulario de calificación.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}
if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    echo json_encode(['success' => false, 'message' => 'Solo administradores']); exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id_categoria = intval($data['id_categoria'] ?? 0);
$nombre       = trim($data['nombre'] ?? '');
$orden        = intval($data['orden'] ?? 0);
$activo       = filter_var($data['activo'] ?? true, FILTER_VALIDATE_BOOLEAN);

if ($nombre === '') {
    echo json_encode(['success' => false, 'message' => 'La sección necesita un nombre']); exit();
}

try {
    if ($id_categoria) {
        $stmt = $conexion->prepare("
            UPDATE categorias_calificacion
            SET nombre = :nombre, orden = :orden, activo = :activo
            WHERE id_categoria = :id
        ");
        $stmt->execute([
            ':nombre' => $nombre, ':orden' => $orden, ':activo' => $activo ? 't' : 'f', ':id' => $id_categoria,
        ]);

        // Mantener sincronizado el texto de categoría en las preguntas que
        // ya la usan (algunas pantallas viejas todavía leen ese texto).
        $conexion->prepare("UPDATE preguntas_calificacion SET categoria = :nombre WHERE id_categoria = :id")
            ->execute([':nombre' => $nombre, ':id' => $id_categoria]);
    } else {
        $stmt = $conexion->prepare("
            INSERT INTO categorias_calificacion (nombre, orden, activo)
            VALUES (:nombre, :orden, :activo)
            RETURNING id_categoria
        ");
        $stmt->execute([':nombre' => $nombre, ':orden' => $orden, ':activo' => $activo ? 't' : 'f']);
        $id_categoria = $stmt->fetchColumn();
    }

    echo json_encode(['success' => true, 'id_categoria' => $id_categoria]);
} catch (PDOException $e) {
    if ($e->getCode() === '23505') {
        echo json_encode(['success' => false, 'message' => 'Ya existe una sección con ese nombre']);
    } else {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
