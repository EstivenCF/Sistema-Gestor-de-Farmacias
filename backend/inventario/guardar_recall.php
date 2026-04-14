<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

require_once __DIR__ . '/../conexion.php';

$id_usuario_sesion = $_SESSION['id_usuario'] ?? $_SESSION['usuario_id'] ?? 1;

$data = json_decode(file_get_contents('php://input'), true);

$id_alerta = isset($data['id_alerta']) && $data['id_alerta'] !== '' && $data['id_alerta'] !== null ? (int)$data['id_alerta'] : null;
$numero_alerta = trim($data['numero_alerta'] ?? '');
$fecha_notificacion = isset($data['fecha_notificacion']) ? $data['fecha_notificacion'] : '';
$entidad_emisora = trim($data['entidad_emisora'] ?? '');
$nivel_riesgo = trim($data['nivel_riesgo'] ?? '');
$estado = trim($data['estado'] ?? 'ACTIVA');
$descripcion = trim($data['descripcion'] ?? '');
$documento_url = trim($data['documento_url'] ?? '');
$lotes = isset($data['lotes']) ? $data['lotes'] : [];

// Validaciones
if (!$numero_alerta || !$fecha_notificacion || !$entidad_emisora || !$nivel_riesgo || empty($descripcion)) {
    echo json_encode(['success' => false, 'message' => 'Complete los campos requeridos']);
    exit();
}

if (!in_array($nivel_riesgo, ['ALTO', 'MEDIO', 'BAJO'])) {
    echo json_encode(['success' => false, 'message' => 'Nivel de riesgo inválido']);
    exit();
}

if (!in_array($estado, ['ACTIVA', 'EN_INVESTIGACION', 'RESUELTA'])) {
    echo json_encode(['success' => false, 'message' => 'Estado inválido']);
    exit();
}

try {
    $conexion->beginTransaction();
    
    if ($id_alerta) {
        // Actualizar alerta existente
        $sql = "UPDATE alertas_sanitarias SET 
                    numero_alerta = :numero_alerta,
                    fecha_notificacion = :fecha_notificacion,
                    entidad_emisora = :entidad_emisora,
                    nivel_riesgo = :nivel_riesgo,
                    estado = :estado,
                    descripcion = :descripcion,
                    documento_url = :documento_url
                WHERE id_alerta = :id_alerta";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':numero_alerta' => $numero_alerta,
            ':fecha_notificacion' => $fecha_notificacion,
            ':entidad_emisora' => $entidad_emisora,
            ':nivel_riesgo' => $nivel_riesgo,
            ':estado' => $estado,
            ':descripcion' => $descripcion,
            ':documento_url' => $documento_url,
            ':id_alerta' => $id_alerta
        ]);
        
        // Eliminar lotes antiguos
        $stmt = $conexion->prepare("DELETE FROM alerta_lote WHERE id_alerta = :id_alerta");
        $stmt->execute([':id_alerta' => $id_alerta]);
        
    } else {
        // Insertar nueva alerta
        $sql = "INSERT INTO alertas_sanitarias (numero_alerta, fecha_notificacion, entidad_emisora, nivel_riesgo, estado, descripcion, documento_url, creado_por) 
                VALUES (:numero_alerta, :fecha_notificacion, :entidad_emisora, :nivel_riesgo, :estado, :descripcion, :documento_url, :creado_por)
                RETURNING id_alerta";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':numero_alerta' => $numero_alerta,
            ':fecha_notificacion' => $fecha_notificacion,
            ':entidad_emisora' => $entidad_emisora,
            ':nivel_riesgo' => $nivel_riesgo,
            ':estado' => $estado,
            ':descripcion' => $descripcion,
            ':documento_url' => $documento_url,
            ':creado_por' => $id_usuario_sesion
        ]);
        
        $id_alerta = $stmt->fetch(PDO::FETCH_ASSOC)['id_alerta'];
    }
    
    // Insertar lotes afectados
    foreach ($lotes as $lote) {
        if (!empty($lote['id_lote'])) {
            $stmt = $conexion->prepare("INSERT INTO alerta_lote (id_alerta, id_lote) VALUES (:id_alerta, :id_lote)");
            $stmt->execute([
                ':id_alerta' => $id_alerta,
                ':id_lote' => $lote['id_lote']
            ]);
        }
    }
    
    $conexion->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => $id_alerta ? 'Alerta actualizada correctamente' : 'Alerta creada correctamente',
        'id_alerta' => $id_alerta
    ]);
    
} catch(PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>