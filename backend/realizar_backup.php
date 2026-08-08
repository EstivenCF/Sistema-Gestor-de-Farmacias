<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include 'conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 1) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Obtener configuración de backup
$stmt = $conexion->query("SELECT ruta_destino FROM configuracion_backup LIMIT 1");
$backup_config = $stmt->fetch(PDO::FETCH_ASSOC);
$ruta_destino = $backup_config['ruta_destino'] ?? '../backups/';

// Crear directorio si no existe
if (!file_exists($ruta_destino)) {
    mkdir($ruta_destino, 0777, true);
}

// Nombre del archivo
$fecha = date('Y-m-d_H-i-s');
$archivo = $ruta_destino . "backup_{$fecha}.sql";

// Comando pg_dump (ajustar según configuración del servidor)
$db_host = 'localhost';
$db_name = 'farmacia_db'; // Cambiar por el nombre real
$db_user = 'postgres';
$db_pass = ''; // Si tiene contraseña, agregar

$command = "pg_dump --host={$db_host} --username={$db_user} --dbname={$db_name} --file={$archivo} 2>&1";
// Si hay contraseña, se puede usar PGPASSWORD
putenv("PGPASSWORD={$db_pass}");
exec($command, $output, $return_var);

if ($return_var === 0) {
    // Registrar en historial_backup
    $tamano = filesize($archivo);
    $stmt = $conexion->prepare("INSERT INTO historial_backup (fecha_backup, tipo, tamano_bytes, estado, realizado_por) VALUES (NOW(), 'MANUAL', :tamano, 'EXITOSO', :id_usuario)");
    $stmt->execute([':tamano' => $tamano, ':id_usuario' => $_SESSION['id_usuario']]);
    echo json_encode(['success' => true, 'archivo' => basename($archivo)]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al crear backup: ' . implode("\n", $output)]);
}
?>