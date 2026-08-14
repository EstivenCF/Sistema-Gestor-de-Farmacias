<?php
// backend/administracion/guardar_pregunta_calificacion.php
// REEMPLAZA el archivo existente.
//
// ACTUALIZACIÓN — la pregunta ya no lleva un texto libre de categoría:
// se asigna a una sección real (id_categoria, tabla categorias_calificacion).
// Se sigue guardando también el nombre en la columna de texto "categoria"
// (sincronizado desde la sección), para no romper pantallas viejas que
// todavía la leen directo.

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
$id_pregunta    = intval($data['id_pregunta'] ?? 0);
$id_categoria   = intval($data['id_categoria'] ?? 0);
$texto          = trim($data['texto'] ?? '');
$tipo_respuesta = $data['tipo_respuesta'] ?? 'ESTRELLAS';
$obligatoria    = filter_var($data['obligatoria'] ?? true, FILTER_VALIDATE_BOOLEAN);
$orden          = intval($data['orden'] ?? 0);
$activo         = filter_var($data['activo'] ?? true, FILTER_VALIDATE_BOOLEAN);

if ($texto === '') {
    echo json_encode(['success' => false, 'message' => 'La pregunta necesita un texto']); exit();
}
if (!in_array($tipo_respuesta, ['ESTRELLAS', 'TEXTO'])) {
    echo json_encode(['success' => false, 'message' => 'Tipo de respuesta inválido']); exit();
}
if (!$id_categoria) {
    echo json_encode(['success' => false, 'message' => 'Debes elegir una sección para esta pregunta']); exit();
}

try {
    $stmt = $conexion->prepare("SELECT nombre FROM categorias_calificacion WHERE id_categoria = :id");
    $stmt->execute([':id' => $id_categoria]);
    $nombre_categoria = $stmt->fetchColumn();
    if (!$nombre_categoria) {
        echo json_encode(['success' => false, 'message' => 'Esa sección no existe']); exit();
    }

    if ($id_pregunta) {
        $stmt = $conexion->prepare("
            UPDATE preguntas_calificacion
            SET id_categoria = :id_categoria, categoria = :categoria, texto = :texto, tipo_respuesta = :tipo,
                obligatoria = :obligatoria, orden = :orden, activo = :activo
            WHERE id_pregunta = :id
        ");
        $stmt->execute([
            ':id_categoria' => $id_categoria,
            ':categoria'    => $nombre_categoria,
            ':texto'        => $texto,
            ':tipo'         => $tipo_respuesta,
            ':obligatoria'  => $obligatoria ? 't' : 'f',
            ':orden'        => $orden,
            ':activo'       => $activo ? 't' : 'f',
            ':id'           => $id_pregunta,
        ]);
    } else {
        $stmt = $conexion->prepare("
            INSERT INTO preguntas_calificacion (id_categoria, categoria, texto, tipo_respuesta, obligatoria, orden, activo)
            VALUES (:id_categoria, :categoria, :texto, :tipo, :obligatoria, :orden, :activo)
            RETURNING id_pregunta
        ");
        $stmt->execute([
            ':id_categoria' => $id_categoria,
            ':categoria'    => $nombre_categoria,
            ':texto'        => $texto,
            ':tipo'         => $tipo_respuesta,
            ':obligatoria'  => $obligatoria ? 't' : 'f',
            ':orden'        => $orden,
            ':activo'       => $activo ? 't' : 'f',
        ]);
        $id_pregunta = $stmt->fetchColumn();
    }

    echo json_encode(['success' => true, 'id_pregunta' => $id_pregunta]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
