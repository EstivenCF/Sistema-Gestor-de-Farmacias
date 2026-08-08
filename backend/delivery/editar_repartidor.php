<?php
// backend/delivery/editar_repartidor.php
// NUEVO — edita los datos de un repartidor ya existente: información
// básica, y reemplaza el set completo de habilidades/licencias por
// tipo de vehículo. Si el repartidor todavía no tenía acceso al
// sistema, también permite crearlo aquí mismo (mismo patrón seguro de
// contraseña que agregar_repartidor.php).

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id_repartidor = intval($data['id_repartidor'] ?? 0);
if (!$id_repartidor || empty($data['nombre'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']); exit();
}

$usuario_login = trim($data['usuario_login'] ?? '');
$password_login = (string)($data['password_login'] ?? '');
$id_sucursal_usuario = intval($data['id_sucursal_usuario'] ?? 0);

try {
    $conexion->beginTransaction();

    // ── Datos básicos ──
    $activo_val = isset($data['activo']) ? ($data['activo'] ? 't' : 'f') : 't';
    $ESTADOS_LABORALES = ['ACTIVO','VACACIONES','LICENCIA_MEDICA','HOSPITALIZADO','LUTO','OTRO'];
    $estado_laboral = $data['estado_laboral'] ?? 'ACTIVO';
    if (!in_array($estado_laboral, $ESTADOS_LABORALES)) $estado_laboral = 'ACTIVO';

    $stmt = $conexion->prepare("
        UPDATE repartidores
        SET nombre = :nombre,
            tipo_identificacion = :tipo_identificacion,
            numero_identificacion = :numero_identificacion,
            telefono_emergencia = :telefono_emergencia,
            fecha_ingreso = :fecha_ingreso,
            activo = :activo,
            estado_laboral = :estado_laboral
        WHERE id_repartidor = :id
    ");
    $stmt->execute([
        ':nombre' => $data['nombre'],
        ':tipo_identificacion' => $data['tipo_identificacion'] ?? null,
        ':numero_identificacion' => $data['numero_identificacion'] ?? null,
        ':telefono_emergencia' => $data['telefono_emergencia'] ?? null,
        ':fecha_ingreso' => $data['fecha_ingreso'] ?? null,
        ':activo' => $activo_val,
        ':estado_laboral' => $estado_laboral,
        ':id' => $id_repartidor,
    ]);

    // ── El acceso al sistema es obligatorio: si todavía no lo tiene,
    //    hay que completarlo ahora para poder guardar ──
    $stmtChk = $conexion->prepare("SELECT id_usuario FROM repartidores WHERE id_repartidor = :id");
    $stmtChk->execute([':id' => $id_repartidor]);
    $ya_tenia_usuario = $stmtChk->fetchColumn();

    if (!$ya_tenia_usuario) {
        if ($usuario_login === '' || $password_login === '' || !$id_sucursal_usuario) {
            throw new Exception('Este repartidor todavía no tiene acceso al sistema — indica usuario, contraseña y sucursal para poder guardar');
        }
        if (strlen($password_login) < 4) {
            throw new Exception('La contraseña debe tener al menos 4 caracteres');
        }
        $stmtCheckU = $conexion->prepare("SELECT 1 FROM usuarios WHERE usuario = :u");
        $stmtCheckU->execute([':u' => $usuario_login]);
        if ($stmtCheckU->fetchColumn()) {
            throw new Exception("El usuario '$usuario_login' ya existe, elige otro");
        }

        $stmtRol = $conexion->prepare("SELECT id_rol FROM roles WHERE nombre = 'Repartidor' LIMIT 1");
        $stmtRol->execute();
        $id_rol_repartidor = $stmtRol->fetchColumn();

        $stmtUsr = $conexion->prepare("
            INSERT INTO usuarios (nombre, usuario, contrasena, id_rol, id_sucursal, estado)
            VALUES (:nombre, :usuario, :contrasena, :id_rol, :id_sucursal, true)
            RETURNING id_usuario
        ");
        $stmtUsr->execute([
            ':nombre'     => $data['nombre'],
            ':usuario'    => $usuario_login,
            ':contrasena' => password_hash($password_login, PASSWORD_DEFAULT),
            ':id_rol'     => $id_rol_repartidor,
            ':id_sucursal'=> $id_sucursal_usuario,
        ]);
        $nuevo_id_usuario = $stmtUsr->fetchColumn();

        $stmtLink = $conexion->prepare("UPDATE repartidores SET id_usuario = :id_usuario WHERE id_repartidor = :id");
        $stmtLink->execute([':id_usuario' => $nuevo_id_usuario, ':id' => $id_repartidor]);
    }

    // ── Reemplazar el set completo de habilidades/licencias ──
    $conexion->prepare("DELETE FROM repartidor_habilidad WHERE id_repartidor = :id")
        ->execute([':id' => $id_repartidor]);

    if (!empty($data['habilidades'])) {
        $stmtHab = $conexion->prepare("
            INSERT INTO repartidor_habilidad (id_repartidor, tipo_vehiculo, nivel, numero_licencia, fecha_vencimiento_licencia)
            VALUES (:id_repartidor, :tipo, :nivel, :numero_licencia, :fecha_vencimiento)
        ");
        foreach ($data['habilidades'] as $hab) {
            $tipo = $hab['tipo_vehiculo'] ?? '';
            if ($tipo === '') continue;
            $stmtHab->execute([
                ':id_repartidor'     => $id_repartidor,
                ':tipo'              => $tipo,
                ':nivel'             => $hab['nivel'] ?? 'COMPETENTE',
                ':numero_licencia'   => $hab['numero_licencia'] ?? null,
                ':fecha_vencimiento' => $hab['fecha_vencimiento_licencia'] ?? null,
            ]);
        }
    }

    $conexion->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
