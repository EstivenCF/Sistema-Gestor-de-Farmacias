<?php
/**
 * Tarea 5 - Proceso estratégico: Gestión Estratégica de Vencimientos de Medicamentos
 * Guarda una acción de recuperación (Pantalla #04: Promoción / Redistribución /
 * Devolución a proveedor). Las acciones de escalamiento (Combo, Donación,
 * Provisión de pérdida) se guardan desde la Pantalla #08, con este mismo endpoint.
 */
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

$camposObligatorios = ['id_lote', 'id_sucursal_origen', 'tipo_accion', 'cantidad_afectada', 'valor_en_riesgo'];
foreach ($camposObligatorios as $campo) {
    if (empty($data[$campo]) && $data[$campo] !== 0) {
        echo json_encode(['success' => false, 'message' => "Falta el campo obligatorio: $campo"]);
        exit();
    }
}

$marcarCompletada = isset($data['marcar_completada']) && $data['marcar_completada'] === true;

try {
    $sql = "INSERT INTO accion_recuperacion (
                id_lote, id_sucursal_origen, id_sucursal_destino,
                tipo_accion, estado, prioridad,
                cantidad_afectada, valor_en_riesgo, valor_recuperado_estimado,
                nivel_riesgo_al_generar, irv_origen_al_generar, causa_raiz,
                responsable, creado_por, fecha_limite, observaciones,
                entidad_receptora, id_accion_previa, fecha_ejecucion
            ) VALUES (
                :id_lote, :id_sucursal_origen, :id_sucursal_destino,
                :tipo_accion, :estado, :prioridad,
                :cantidad_afectada, :valor_en_riesgo, :valor_recuperado_estimado,
                :nivel_riesgo_al_generar, :irv_origen_al_generar, :causa_raiz,
                :responsable, :creado_por, :fecha_limite, :observaciones,
                :entidad_receptora, :id_accion_previa, :fecha_ejecucion
            ) RETURNING id_accion";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        ':id_lote' => $data['id_lote'],
        ':id_sucursal_origen' => $data['id_sucursal_origen'],
        ':id_sucursal_destino' => $data['id_sucursal_destino'] ?? null,
        ':tipo_accion' => $data['tipo_accion'],
        ':estado' => $marcarCompletada ? 'COMPLETADA' : 'PENDIENTE',
        ':prioridad' => $data['prioridad'] ?? 'MEDIA',
        ':cantidad_afectada' => $data['cantidad_afectada'],
        ':valor_en_riesgo' => $data['valor_en_riesgo'],
        ':valor_recuperado_estimado' => $data['valor_recuperado_estimado'] ?? null,
        ':nivel_riesgo_al_generar' => $data['nivel_riesgo_al_generar'] ?? null,
        ':irv_origen_al_generar' => $data['irv_origen_al_generar'] ?? null,
        ':causa_raiz' => $data['causa_raiz'] ?? null,
        ':responsable' => $data['responsable'] ?? null,
        ':creado_por' => $_SESSION['id_usuario'] ?? null,
        ':fecha_limite' => $data['fecha_limite'] ?? null,
        ':observaciones' => $data['observaciones'] ?? null,
        ':entidad_receptora' => $data['entidad_receptora'] ?? null,
        ':id_accion_previa' => $data['id_accion_previa'] ?? null,
        ':fecha_ejecucion' => $marcarCompletada ? date('Y-m-d H:i:s') : null,
    ]);

    $id_accion = $stmt->fetchColumn();

    echo json_encode(['success' => true, 'id_accion' => (int)$id_accion, 'message' => 'Acción de recuperación registrada correctamente']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
