<?php
require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) { echo json_encode(['success'=>false,'message'=>'Datos inválidos']); exit; }

$id_usuario_sesion = $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? null);
if (!$id_usuario_sesion) {
    echo json_encode(['success'=>false,'message'=>'No se pudo determinar el usuario autenticado']);
    exit;
}

try {
    if (empty($data['id_descuento'])) {
        $stmt = $conexion->prepare("INSERT INTO descuentos (codigo, nombre, id_tipo_descuento, valor_descuento, es_porcentaje, fecha_inicio, fecha_fin, descripcion, monto_minimo_compra, activo, creado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?) RETURNING id_descuento");
        $stmt->execute([$data['codigo'], $data['nombre'], $data['id_tipo_descuento'], $data['valor_descuento'], $data['es_porcentaje'], $data['fecha_inicio'], $data['fecha_fin'], $data['descripcion'], $data['monto_minimo_compra'], $data['activo'], $id_usuario_sesion]);
        $id_descuento = $stmt->fetchColumn();
    } else {
        $stmt = $conexion->prepare("UPDATE descuentos SET codigo=?, nombre=?, id_tipo_descuento=?, valor_descuento=?, es_porcentaje=?, fecha_inicio=?, fecha_fin=?, descripcion=?, monto_minimo_compra=?, activo=? WHERE id_descuento=?");
        $stmt->execute([$data['codigo'], $data['nombre'], $data['id_tipo_descuento'], $data['valor_descuento'], $data['es_porcentaje'], $data['fecha_inicio'], $data['fecha_fin'], $data['descripcion'], $data['monto_minimo_compra'], $data['activo'], $data['id_descuento']]);
        $id_descuento = $data['id_descuento'];
    }

    // NUEVO (Tarea 5): vincula el descuento al medicamento específico usando
    // descuento_medicamento, que ya existía en el esquema pero ningún
    // endpoint la usaba todavía (los descuentos quedaban genéricos, sin
    // scope a un producto). Solo se activa si viene id_medicamento.
    if (!empty($data['id_medicamento'])) {
        $stmt = $conexion->prepare("INSERT INTO descuento_medicamento (id_descuento, id_medicamento) VALUES (:desc, :med) ON CONFLICT DO NOTHING");
        $stmt->execute([':desc' => $id_descuento, ':med' => $data['id_medicamento']]);
    }

    // NUEVO (Tarea 5): si esta oferta viene de una acción de recuperación
    // (Pantalla #04 → #06), la enlaza y la pasa a EN_EJECUCION. No se marca
    // COMPLETADA aquí porque la promoción se evalúa con el tiempo (Pantalla
    // #07 mide si de verdad aceleró la rotación).
    if (!empty($data['id_accion'])) {
        $stmt = $conexion->prepare("UPDATE accion_recuperacion
                                     SET id_descuento = :desc, estado = 'EN_EJECUCION', fecha_ejecucion = CURRENT_TIMESTAMP
                                     WHERE id_accion = :id_accion");
        $stmt->execute([':desc' => $id_descuento, ':id_accion' => $data['id_accion']]);
    }

    echo json_encode(['success' => true, 'id_descuento' => (int)$id_descuento]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>