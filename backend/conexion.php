<?php
$host = "127.0.0.1";
$port = "5432";
$dbname = "Farmacia";
$user = "postgres";
$password = "2003";

date_default_timezone_set('America/Santo_Domingo');

try {
    $conexion = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    $conexion->exec("SET NAMES 'UTF8'");
    $conexion->exec("SET TIME ZONE 'America/Santo_Domingo'");
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch (Exception $e) {}

// ── Auditoría: dejar registrado QUIÉN hace cada cambio ──────────────
// set_audit_vars() ya existía en la base (Farmacia.sql), pero nada la
// llamaba desde PHP, así que auditoria_cambios siempre guardaba el
// usuario y la IP en blanco. Se llama una vez por conexión (que dura
// toda la petición), así el trigger fn_auditoria_generica() puede leer
// current_setting('myapp.id_usuario'/'myapp.ip_address') en cualquier
// INSERT/UPDATE/DELETE que hagamos después en esta misma petición.
// Si todavía no hay sesión iniciada (ej: la propia pantalla de login),
// queda en NULL, que es justo lo que se espera para acciones sin dueño.
try {
    $id_usuario_auditoria = $_SESSION['id_usuario'] ?? null;
    $ip_auditoria = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmtAudit = $conexion->prepare("SELECT set_audit_vars(:id_usuario, :ip)");
    $stmtAudit->execute([':id_usuario' => $id_usuario_auditoria, ':ip' => $ip_auditoria]);
} catch (Exception $e) {
    // No interrumpir la petición si esto falla por cualquier motivo
    // (ej: base de datos vieja que todavía no tiene set_audit_vars()).
}
