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

try {
    // Obtener lotes vencidos que aún están ACTIVOS
    $stmt = $conexion->prepare("
        SELECT id_lote, numero_lote 
        FROM lotes 
        WHERE estado = 'ACTIVO' AND fecha_vencimiento < CURRENT_DATE
    ");
    $stmt->execute();
    $lotes_vencidos = $stmt->fetchAll();
    
    if (empty($lotes_vencidos)) {
        echo json_encode(['success' => true, 'message' => 'No hay lotes vencidos para marcar']);
        exit();
    }
    
    $contador = 0;
    
    foreach ($lotes_vencidos as $lote) {
        // Actualizar estado del lote
        $stmt = $conexion->prepare("
            UPDATE lotes 
            SET estado = 'VENCIDO', fecha_retiro = CURRENT_DATE, motivo_retiro = 'Vencimiento automático'
            WHERE id_lote = :id_lote
        ");
        $stmt->execute([':id_lote' => $lote['id_lote']]);
        
        // Registrar en historial de cambios
        $stmt = $conexion->prepare("
            INSERT INTO historial_estado_lote (id_lote, estado_anterior, estado_nuevo, motivo, id_usuario, observaciones)
            VALUES (:id_lote, 'ACTIVO', 'VENCIDO', 'Vencimiento automático', :id_usuario, 'Marcado automáticamente por sistema')
        ");
        $stmt->execute([
            ':id_lote' => $lote['id_lote'],
            ':id_usuario' => $id_usuario_sesion
        ]);
        
        $contador++;
    }
    
    echo json_encode(['success' => true, 'message' => "$contador lotes marcados como vencidos"]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>