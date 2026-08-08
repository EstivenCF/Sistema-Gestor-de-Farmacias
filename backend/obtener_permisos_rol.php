<?php
// backend/obtener_permisos_rol.php
// NUEVO — para el botón "Cargar permisos por defecto del rol". Antes
// ese botón tenía sus propios valores escritos directo en
// JavaScript, con roles que ni existen en la base de datos, y sin
// ninguna entrada para "Repartidor" (si lo usabas con uno, cargaba
// por error los permisos de Cajero). Ahora trae los valores reales
// de rol_permiso.

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

require_once 'conexion.php';

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

$id_rol = isset($_GET['id_rol']) ? intval($_GET['id_rol']) : 0;
if ($id_rol <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de rol inválido']);
    exit();
}

try {
    // Todos los permisos que existen
    $todos = $conexion->query("SELECT nombre FROM permisos")->fetchAll(PDO::FETCH_COLUMN);

    // Los que sí tiene este rol por defecto
    $stmt = $conexion->prepare("
        SELECT p.nombre
        FROM rol_permiso rp
        JOIN permisos p ON p.id_permiso = rp.id_permiso
        WHERE rp.id_rol = :id
    ");
    $stmt->execute([':id' => $id_rol]);
    $delRol = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

    $permisos = [];
    foreach ($todos as $nombre) {
        $permisos[$nombre] = isset($delRol[$nombre]);
    }

    echo json_encode(['success' => true, 'permisos' => $permisos]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
