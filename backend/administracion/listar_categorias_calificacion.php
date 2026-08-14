<?php
// backend/administracion/listar_categorias_calificacion.php
// NUEVO — trae las secciones (categorías) del formulario de calificación,
// con cuántas preguntas tiene cada una (para que la pantalla de admin
// pueda avisar antes de dejar borrar una sección que todavía se usa).

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}
if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    echo json_encode(['success' => false, 'message' => 'Solo administradores']); exit();
}

try {
    $stmt = $conexion->query("
        SELECT cc.id_categoria, cc.nombre, cc.orden, cc.activo,
               (SELECT COUNT(*) FROM preguntas_calificacion pc WHERE pc.id_categoria = cc.id_categoria) AS total_preguntas
        FROM categorias_calificacion cc
        ORDER BY cc.orden ASC, cc.id_categoria ASC
    ");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'categorias' => $categorias]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
