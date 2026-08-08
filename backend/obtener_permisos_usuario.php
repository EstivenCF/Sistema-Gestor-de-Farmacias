<?php
// backend/obtener_permisos_usuario.php
// REEMPLAZA el archivo existente.
//
// Antes: solo leía usuario_permiso, con una lista incompleta y vieja
// de permisos (le faltaban 'dashboard', 'delivery' y sus 5
// submódulos, entre otros) — por eso la pantalla nunca reflejaba el
// estado real.
//
// Ahora: trae TODOS los permisos del catálogo real, y para cada uno
// aplica la misma jerarquía que ya usa el sistema para decidir
// acceso: si la persona tiene un permiso propio guardado
// (usuario_permiso), ese manda. Si no, se usa el valor por defecto
// de su rol (rol_permiso).

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

require_once 'conexion.php';

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

$id_usuario = isset($_GET['id_usuario']) ? intval($_GET['id_usuario']) : 0;
if ($id_usuario <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario inválido']);
    exit();
}

try {
    $stmtRol = $conexion->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = :id");
    $stmtRol->execute([':id' => $id_usuario]);
    $id_rol = $stmtRol->fetchColumn();
    if (!$id_rol) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit();
    }

    // Todos los permisos del catálogo
    $todos = $conexion->query("SELECT id_permiso, nombre FROM permisos")->fetchAll(PDO::FETCH_ASSOC);

    // Overrides propios de este usuario
    $stmtU = $conexion->prepare("SELECT id_permiso, permitido FROM usuario_permiso WHERE id_usuario = :id");
    $stmtU->execute([':id' => $id_usuario]);
    $overrides = [];
    foreach ($stmtU->fetchAll(PDO::FETCH_ASSOC) as $o) {
        $overrides[$o['id_permiso']] = filter_var($o['permitido'], FILTER_VALIDATE_BOOLEAN);
    }

    // Permisos por defecto de su rol
    $stmtR = $conexion->prepare("SELECT id_permiso FROM rol_permiso WHERE id_rol = :id");
    $stmtR->execute([':id' => $id_rol]);
    $delRol = array_flip($stmtR->fetchAll(PDO::FETCH_COLUMN));

    $permisos_completos = [];
    $tiene_override = [];
    foreach ($todos as $p) {
        if (isset($overrides[$p['id_permiso']])) {
            $permisos_completos[$p['nombre']] = $overrides[$p['id_permiso']];
            $tiene_override[$p['nombre']] = true;
        } else {
            $permisos_completos[$p['nombre']] = isset($delRol[$p['id_permiso']]);
            $tiene_override[$p['nombre']] = false;
        }
    }

    echo json_encode([
        'success' => true,
        'permisos' => $permisos_completos,
        'personalizado' => $tiene_override, // qué permisos tiene esta persona sobreescritos manualmente vs. heredados de su rol
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
