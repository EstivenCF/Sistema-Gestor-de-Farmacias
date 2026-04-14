<?php
session_start();
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json');

// Verificar que sea administrador
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    // Contar cuántos usuarios tienen intentos
    $stmt = $conexion->prepare("SELECT COUNT(DISTINCT usuario_intentado) as total FROM intentos_login");
    $stmt->execute();
    $totalUsuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($totalUsuarios == 0) {
        echo json_encode(['success' => true, 'message' => 'No hay usuarios bloqueados para desbloquear.']);
        exit();
    }
    
    // Eliminar todos los intentos
    $stmt = $conexion->prepare("DELETE FROM intentos_login");
    $stmt->execute();
    
    // Registrar en auditoría
    $admin_id = $_SESSION['usuario_id'] ?? 0;
    $audit_sql = "INSERT INTO auditoria_cambios (id_usuario, tabla_afectada, id_registro, accion, ip, fecha) 
                  VALUES (:id, 'intentos_login', 0, 'DESBLOQUEO_TODOS', :ip, NOW())";
    $audit_stmt = $conexion->prepare($audit_sql);
    $audit_stmt->execute([
        ':id' => $admin_id,
        ':ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => "Se desbloquearon todos los usuarios. Se eliminaron los registros de $totalUsuarios usuario(s)."
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>