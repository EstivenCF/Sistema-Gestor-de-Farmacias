<?php
// backend/portal_cliente/listar_preguntas_calificacion.php
// NUEVO — trae las preguntas ACTIVAS del formulario de calificación, en
// el orden configurado por el administrador, para que el cliente las
// vea al calificar su entrega.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_cliente_portal'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']); exit();
}

try {
    $stmt = $conexion->query("
        SELECT pc.id_pregunta, cc.nombre AS categoria, pc.texto, pc.tipo_respuesta, pc.obligatoria
        FROM preguntas_calificacion pc
        LEFT JOIN categorias_calificacion cc ON cc.id_categoria = pc.id_categoria
        WHERE pc.activo = TRUE
        ORDER BY COALESCE(cc.orden, 999) ASC, pc.orden ASC, pc.id_pregunta ASC
    ");
    $preguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'preguntas' => $preguntas]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
