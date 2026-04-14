<?php
session_start();
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json');

// Verificar que el usuario esté autenticado y sea administrador
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado. Debe iniciar sesión.']);
    exit();
}

if ($_SESSION['rol'] !== 'Administrador') {
    echo json_encode(['success' => false, 'message' => 'No autorizado. Solo administradores pueden desbloquear usuarios.']);
    exit();
}

$usuario = $_POST['usuario'] ?? '';

if (empty($usuario)) {
    echo json_encode(['success' => false, 'message' => 'Debe especificar un usuario.']);
    exit();
}

try {
    // Verificar si el usuario tiene intentos
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM intentos_login WHERE usuario_intentado = :usuario");
    $stmt->execute([':usuario' => $usuario]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($total == 0) {
        echo json_encode(['success' => true, 'message' => "El usuario '$usuario' no tiene bloqueos activos."]);
        exit();
    }
    
    // Eliminar todos los intentos del usuario
    $stmt = $conexion->prepare("DELETE FROM intentos_login WHERE usuario_intentado = :usuario");
    $stmt->execute([':usuario' => $usuario]);
    
    // Registrar en auditoría
    $admin_id = $_SESSION['usuario_id'] ?? 0;
    $admin_nombre = $_SESSION['usuario'] ?? 'Unknown';
    
    $audit_sql = "INSERT INTO auditoria_cambios (id_usuario, tabla_afectada, id_registro, accion, ip, fecha) 
                  VALUES (:id, 'intentos_login', 0, 'DESBLOQUEO_USUARIO', :ip, NOW())";
    $audit_stmt = $conexion->prepare($audit_sql);
    $audit_stmt->execute([
        ':id' => $admin_id,
        ':ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => "Usuario '$usuario' desbloqueado correctamente. Se eliminaron $total intentos fallidos.",
        'usuario' => $usuario,
        'total' => $total,
        'admin' => $admin_nombre
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>