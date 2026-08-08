<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require __DIR__ . '/conexion.php';

// ============================================
// CONFIGURACIÓN DE BLOQUEO ESTILO IPHONE (POR USUARIO)
// ============================================
define('MAX_INTENTOS_NORMAL', 5);      // 5 intentos → 1 minuto
define('MAX_INTENTOS_MEDIO', 6);       // 6 intentos → 5 minutos
define('MAX_INTENTOS_ALTO', 8);        // 8 intentos → 15 minutos
define('MAX_INTENTOS_TOTAL', 10);      // 10 intentos → bloqueo permanente

// Obtener IP del usuario (solo para registro)
function obtenerIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// ============================================
// FUNCIONES DE BLOQUEO POR USUARIO
// ============================================

// Verificar si el usuario es administrador
function esAdministrador($conexion, $usuario) {
    $stmt = $conexion->prepare("SELECT r.nombre FROM usuarios u JOIN roles r ON u.id_rol = r.id_rol WHERE u.usuario = :usuario");
    $stmt->execute([':usuario' => $usuario]);
    $rol = $stmt->fetch(PDO::FETCH_ASSOC);
    return $rol && ($rol['nombre'] === 'Administrador');
}

// Verificar bloqueo antes de cualquier intento (POR USUARIO)
function verificarBloqueo($conexion, $usuario) {
    // Si es administrador, NO aplicar bloqueo
    if (esAdministrador($conexion, $usuario)) {
        return ['bloqueado' => false, 'intentos' => 0];
    }
    
    // Contar intentos en la última hora para este usuario
    $stmt = $conexion->prepare("SELECT COUNT(*) as total, MAX(fecha_intento) as ultimo 
                                 FROM intentos_login 
                                 WHERE usuario_intentado = :usuario 
                                 AND fecha_intento > NOW() - INTERVAL '1 hour'");
    $stmt->execute([':usuario' => $usuario]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $intentos = (int)$resultado['total'];
    $ultimo_intento = $resultado['ultimo'];
    $tiempo_transcurrido = 0;
    
    if ($ultimo_intento) {
        $stmt = $conexion->prepare("SELECT EXTRACT(EPOCH FROM (NOW() - :ultimo)) as segundos");
        $stmt->execute([':ultimo' => $ultimo_intento]);
        $tiempo_transcurrido = (int)$stmt->fetch(PDO::FETCH_ASSOC)['segundos'];
    }
    
    // BLOQUEO PERMANENTE (10+ intentos) - SOLO PARA NO ADMINISTRADORES
    if ($intentos >= MAX_INTENTOS_TOTAL) {
        return ['bloqueado' => true, 'tipo' => 'permanente', 'mensaje' => 'Usuario bloqueado permanentemente. Contacte al administrador.'];
    }
    
    // BLOQUEO 15 MINUTOS (8-9 intentos)
    if ($intentos >= MAX_INTENTOS_ALTO) {
        if ($tiempo_transcurrido < 900) {
            $restantes = 900 - $tiempo_transcurrido;
            $minutos = ceil($restantes / 60);
            return ['bloqueado' => true, 'tipo' => 'largo', 'mensaje' => "Usuario desactivado por $minutos minutos - Quedan " . (MAX_INTENTOS_TOTAL - $intentos) . " intentos antes de bloqueo permanente"];
        }
    }
    
    // BLOQUEO 5 MINUTOS (6-7 intentos)
    if ($intentos >= MAX_INTENTOS_MEDIO) {
        if ($tiempo_transcurrido < 300) {
            $restantes = 300 - $tiempo_transcurrido;
            return ['bloqueado' => true, 'tipo' => 'medio', 'mensaje' => "Usuario desactivado por 5 minutos - Espere $restantes segundos. Quedan " . (MAX_INTENTOS_TOTAL - $intentos) . " intentos"];
        }
    }
    
    // BLOQUEO 1 MINUTO (5 intentos)
    if ($intentos >= MAX_INTENTOS_NORMAL) {
        if ($tiempo_transcurrido < 60) {
            $restantes = 60 - $tiempo_transcurrido;
            return ['bloqueado' => true, 'tipo' => 'corto', 'mensaje' => "Usuario desactivado por 1 minuto - Espere $restantes segundos. Quedan " . (MAX_INTENTOS_TOTAL - $intentos) . " intentos"];
        }
    }
    
    return ['bloqueado' => false, 'intentos' => $intentos];
}

// Función para obtener estado de bloqueo actual (para AJAX) - POR USUARIO
function obtenerEstadoBloqueo($conexion, $usuario) {
    if (empty($usuario)) {
        return ['bloqueado' => false, 'intentos' => 0, 'tipo' => 'NINGUNO'];
    }
    
    // Si es administrador, NO está bloqueado
    if (esAdministrador($conexion, $usuario)) {
        return ['bloqueado' => false, 'intentos' => 0, 'tipo' => 'NINGUNO'];
    }
    
    $stmt = $conexion->prepare("SELECT COUNT(*) as total, MAX(fecha_intento) as ultimo 
                                 FROM intentos_login 
                                 WHERE usuario_intentado = :usuario 
                                 AND fecha_intento > NOW() - INTERVAL '1 hour'");
    $stmt->execute([':usuario' => $usuario]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $intentos = (int)$resultado['total'];
    $ultimo_intento = $resultado['ultimo'];
    $tiempo_transcurrido = 0;
    
    if ($ultimo_intento) {
        $stmt = $conexion->prepare("SELECT EXTRACT(EPOCH FROM (NOW() - :ultimo)) as segundos");
        $stmt->execute([':ultimo' => $ultimo_intento]);
        $tiempo_transcurrido = (int)$stmt->fetch(PDO::FETCH_ASSOC)['segundos'];
    }
    
    // BLOQUEO PERMANENTE (10+ intentos)
    if ($intentos >= MAX_INTENTOS_TOTAL) {
        return ['bloqueado' => true, 'tipo' => 'permanente', 'tiempo_restante' => null, 'mensaje' => 'Usuario bloqueado permanentemente'];
    }
    
    // BLOQUEO 15 MINUTOS (8-9 intentos)
    if ($intentos >= MAX_INTENTOS_ALTO) {
        if ($tiempo_transcurrido < 900) {
            $tiempo_restante = 900 - $tiempo_transcurrido;
            return ['bloqueado' => true, 'tipo' => 'largo', 'tiempo_restante' => $tiempo_restante, 'mensaje' => 'Usuario desactivado'];
        }
    }
    
    // BLOQUEO 5 MINUTOS (6-7 intentos)
    if ($intentos >= MAX_INTENTOS_MEDIO) {
        if ($tiempo_transcurrido < 300) {
            $tiempo_restante = 300 - $tiempo_transcurrido;
            return ['bloqueado' => true, 'tipo' => 'medio', 'tiempo_restante' => $tiempo_restante, 'mensaje' => 'Usuario desactivado'];
        }
    }
    
    // BLOQUEO 1 MINUTO (5 intentos)
    if ($intentos >= MAX_INTENTOS_NORMAL) {
        if ($tiempo_transcurrido < 60) {
            $tiempo_restante = 60 - $tiempo_transcurrido;
            return ['bloqueado' => true, 'tipo' => 'corto', 'tiempo_restante' => $tiempo_restante, 'mensaje' => 'Usuario desactivado'];
        }
    }
    
    return ['bloqueado' => false, 'intentos' => $intentos, 'tipo' => 'NINGUNO'];
}

// ============================================
// MANEJAR PETICIONES AJAX
// ============================================

// Verificar estado de bloqueo (para AJAX) - POR USUARIO
if (isset($_POST['check_lockout'])) {
    header('Content-Type: application/json');
    $usuario = isset($_POST['user']) ? trim($_POST['user']) : '';
    $estado = obtenerEstadoBloqueo($conexion, $usuario);
    echo json_encode($estado);
    exit();
}

// Registrar intento fallido (PostgreSQL) - POR USUARIO
function registrarIntentoFallido($conexion, $ip, $usuario_intentado) {
    $stmt = $conexion->prepare("INSERT INTO intentos_login (ip, usuario_intentado, fecha_intento) VALUES (:ip, :usuario, NOW())");
    $stmt->execute([':ip' => $ip, ':usuario' => $usuario_intentado]);
    
    // Contar intentos actuales para este usuario
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM intentos_login WHERE usuario_intentado = :usuario AND fecha_intento > NOW() - INTERVAL '1 hour'");
    $stmt->execute([':usuario' => $usuario_intentado]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $intentos = (int)$resultado['total'];
    $restantes = MAX_INTENTOS_TOTAL - $intentos;
    
    if ($intentos >= MAX_INTENTOS_TOTAL) {
        return "Usuario bloqueado permanentemente. Contacte al administrador.";
    } elseif ($intentos >= MAX_INTENTOS_ALTO) {
        return "Usuario desactivado por 15 minutos - Le quedan $restantes intentos antes de bloqueo permanente";
    } elseif ($intentos >= MAX_INTENTOS_MEDIO) {
        return "Usuario desactivado por 5 minutos - Le quedan $restantes intentos";
    } elseif ($intentos >= MAX_INTENTOS_NORMAL) {
        return "Usuario desactivado por 1 minuto - Le quedan $restantes intentos";
    } else {
        return "Contraseña incorrecta. Le quedan $restantes intentos antes de que se desactive el acceso";
    }
}

// Limpiar intentos después de login exitoso - POR USUARIO
function limpiarIntentos($conexion, $usuario) {
    $stmt = $conexion->prepare("DELETE FROM intentos_login WHERE usuario_intentado = :usuario");
    $stmt->execute([':usuario' => $usuario]);
}

// ============================================
// PROCESO DE LOGIN
// ============================================

function redirectWithError($message)
{
    header("Location: ../frontend/index.php?error=" . urlencode($message));
    exit();
}

if (empty($_POST['user']) || empty($_POST['pass'])) {
    redirectWithError('Debe ingresar usuario y contraseña');
}

$usuario = trim($_POST['user']);
$clave   = trim($_POST['pass']);
$ip = obtenerIP();

try {
    // 🔎 Buscar usuario PRIMERO
    $sql = "SELECT u.*, r.nombre as rol_nombre 
            FROM usuarios u 
            JOIN roles r ON u.id_rol = r.id_rol 
            WHERE u.usuario = :usuario";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':usuario' => $usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // ⚠️ IMPORTANTE: El bloqueo se verifica DESPUÉS de buscar el usuario
    // Si el usuario no existe, igual registramos intento fallido con el nombre intentado
    if (!$user) {
        $mensaje = registrarIntentoFallido($conexion, $ip, $usuario);
        redirectWithError($mensaje);
    }

    // 🔒 VERIFICAR BLOQUEO POR USUARIO (solo si el usuario existe)
    // Los administradores tienen bypass automático
    $bloqueo = verificarBloqueo($conexion, $usuario);
    if ($bloqueo['bloqueado']) {
        redirectWithError($bloqueo['mensaje']);
    }

    // 🔴 Verificar estado del usuario
    if (!$user['estado']) {
        redirectWithError('Usuario inactivo. Contacte al administrador');
    }

    // 🔐 Validar contraseña
    $passwordValida = false;
    
    if (password_verify($clave, $user['contrasena'])) {
        $passwordValida = true;
    } elseif ($clave === trim($user['contrasena'])) {
        $passwordValida = true;
    }
    
    if (!$passwordValida) {
        $mensaje = registrarIntentoFallido($conexion, $ip, $usuario);
        redirectWithError($mensaje);
    }

    // ============================================
    // LOGIN EXITOSO - Limpiar intentos de este usuario
    // ============================================
    limpiarIntentos($conexion, $usuario);
    
    // 🔑 Generar token único
    $token = bin2hex(random_bytes(32));

    // Guardar en sesión
    $_SESSION['usuario_id'] = $user['id_usuario'];
    $_SESSION['usuario']    = $user['usuario'];
    $_SESSION['nombre']     = $user['nombre'];
    $_SESSION['rol']        = $user['rol_nombre'];
    $_SESSION['id_rol']     = $user['id_rol'];
    $_SESSION['token']      = $token;

    // 📦 Registrar sesión en BD
    try {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $fecha_expiracion = date('Y-m-d H:i:s', strtotime('+8 hours'));

        // Cerrar sesiones anteriores del mismo usuario
        $sqlUpdate = "UPDATE sesiones 
                      SET activa = FALSE, fecha_cierre = NOW() 
                      WHERE id_usuario = :id AND activa = TRUE";
        $stmtUpdate = $conexion->prepare($sqlUpdate);
        $stmtUpdate->execute([':id' => $user['id_usuario']]);

        // Insertar nueva sesión
        $sql_ins = "INSERT INTO sesiones 
                    (id_usuario, token, ip, user_agent, fecha_expiracion, activa) 
                    VALUES (:id, :tk, :ip, :ua, :exp, TRUE)";
        $stmt_ins = $conexion->prepare($sql_ins);
        $stmt_ins->execute([
            ':id' => $user['id_usuario'],
            ':tk' => $token,
            ':ip' => $ip,
            ':ua' => $user_agent,
            ':exp' => $fecha_expiracion
        ]);

        $_SESSION['id_sesion'] = $conexion->lastInsertId();
        
        // Registrar en auditoría
        try {
            $audit_sql = "INSERT INTO auditoria_cambios (id_usuario, tabla_afectada, id_registro, accion, ip, fecha) 
                          VALUES (:id, 'usuarios', :id_registro, 'LOGIN_EXITOSO', :ip, NOW())";
            $audit_stmt = $conexion->prepare($audit_sql);
            $audit_stmt->execute([
                ':id' => $user['id_usuario'],
                ':id_registro' => $user['id_usuario'],
                ':ip' => $ip
            ]);
        } catch (Exception $e) {
            error_log("Error en auditoría: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        error_log("Error guardando sesión: " . $e->getMessage());
    }

    header("Location: ../frontend/menuprincipal.php");
    exit();
    
} catch (PDOException $e) {
    error_log("Error en login: " . $e->getMessage());
    redirectWithError('Error en la base de datos. Contacte al administrador.');
}
?>