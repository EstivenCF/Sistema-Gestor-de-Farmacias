<?php
// backend/administracion/listar_preguntas_calificacion.php
// NUEVO — trae el catálogo completo de preguntas del formulario de
// calificación (para la pantalla de administración). Incluye inactivas,
// para que el admin las pueda reactivar.

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
        SELECT pc.id_pregunta, pc.id_categoria, cc.nombre AS categoria, pc.texto,
               pc.tipo_respuesta, pc.obligatoria, pc.orden, pc.activo
        FROM preguntas_calificacion pc
        LEFT JOIN categorias_calificacion cc ON cc.id_categoria = pc.id_categoria
        ORDER BY COALESCE(cc.orden, 999) ASC, pc.orden ASC, pc.id_pregunta ASC
    ");
    $preguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'preguntas' => $preguntas]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
