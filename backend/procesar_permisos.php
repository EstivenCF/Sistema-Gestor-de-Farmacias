<?php
session_start();
header('Content-Type: application/json');

require_once 'conexion.php';

// Verificar sesión
if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

// Obtener datos del POST (JSON)
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

$id_usuario = isset($input['id_usuario']) ? intval($input['id_usuario']) : 0;
$permisos = isset($input['permisos']) ? $input['permisos'] : [];
$accion = isset($input['accion']) ? $input['accion'] : '';

if ($accion !== 'guardar_permisos' || $id_usuario <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

try {
    $conexion->beginTransaction();
    
    // Obtener todos los permisos disponibles
    $stmt_permisos = $conexion->query("SELECT id_permiso, nombre FROM permisos");
    $todos_permisos = $stmt_permisos->fetchAll(PDO::FETCH_ASSOC);
    
    // Mapear nombres a IDs
    $mapa_permisos = [];
    foreach ($todos_permisos as $p) {
        $mapa_permisos[$p['nombre']] = $p['id_permiso'];
    }
    
    // Eliminar todos los permisos actuales del usuario
    $sql_delete = "DELETE FROM usuario_permiso WHERE id_usuario = ?";
    $stmt_delete = $conexion->prepare($sql_delete);
    $stmt_delete->execute([$id_usuario]);
    
    // Insertar solo los permisos que están en true
    $sql_insert = "INSERT INTO usuario_permiso (id_usuario, id_permiso, permitido) VALUES (?, ?, TRUE)";
    $stmt_insert = $conexion->prepare($sql_insert);
    
    $insertados = 0;
    foreach ($permisos as $nombre_permiso => $valor) {
        if ($valor === true && isset($mapa_permisos[$nombre_permiso])) {
            $stmt_insert->execute([$id_usuario, $mapa_permisos[$nombre_permiso]]);
            $insertados++;
        }
    }
    
    $conexion->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "$insertados permisos actualizados correctamente",
        'total' => $insertados
    ]);
    
} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar los permisos: ' . $e->getMessage()
    ]);
}
?>