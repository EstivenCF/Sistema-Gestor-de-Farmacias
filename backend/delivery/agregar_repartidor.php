<?php
// backend/delivery/agregar_repartidor.php
// REEMPLAZA el archivo existente.
//
// CAMBIO: el acceso al sistema ya NO es opcional. Todo repartidor
// nuevo usa la agenda para ver sus entregas, así que el usuario de
// login se crea SIEMPRE, en la misma transacción, ya enlazado.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['nombre'])) {
    echo json_encode(['success' => false, 'message' => 'Nombre del repartidor es requerido']);
    exit();
}

// ── Datos de acceso al sistema (siempre obligatorios) ──
$usuario_login = trim($data['usuario_login'] ?? '');
$password_login = (string)($data['password_login'] ?? '');
$id_sucursal_usuario = intval($data['id_sucursal_usuario'] ?? 0);

if ($usuario_login === '' || $password_login === '' || !$id_sucursal_usuario) {
    echo json_encode(['success' => false, 'message' => 'Todo repartidor necesita acceso al sistema: indica usuario, contraseña y sucursal']);
    exit();
}
if (strlen($password_login) < 4) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 4 caracteres']);
    exit();
}

try {
    $conexion->beginTransaction();

    $id_usuario = null;

    // Verificar que el nombre de usuario no esté ya tomado
    $stmtCheck = $conexion->prepare("SELECT 1 FROM usuarios WHERE usuario = :u");
    $stmtCheck->execute([':u' => $usuario_login]);
    if ($stmtCheck->fetchColumn()) {
        throw new Exception("El usuario '$usuario_login' ya existe, elige otro");
    }

    $stmtRol = $conexion->prepare("SELECT id_rol FROM roles WHERE nombre = 'Repartidor' LIMIT 1");
    $stmtRol->execute();
    $id_rol_repartidor = $stmtRol->fetchColumn();
    if (!$id_rol_repartidor) {
        throw new Exception("No existe el rol 'Repartidor' en la tabla roles");
    }

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
    $id_usuario = $stmtUsr->fetchColumn();

    // ── Crear el repartidor, ya enlazado ──
    // NOTA: ya no se guarda una licencia genérica aquí — cada tipo de
    // vehículo lleva su propia licencia en repartidor_habilidad (ver
    // más abajo), porque cada categoría es una licencia distinta.
    $stmt = $conexion->prepare("
        INSERT INTO repartidores (
            nombre, tipo_identificacion, numero_identificacion, direccion,
            fecha_ingreso, telefono_emergencia, foto_url, observaciones, activo, id_usuario
        ) VALUES (
            :nombre, :tipo_identificacion, :numero_identificacion, :direccion,
            :fecha_ingreso, :telefono_emergencia, :foto_url, :observaciones, true, :id_usuario
        )
        RETURNING id_repartidor
    ");
    $stmt->execute([
        ':nombre' => $data['nombre'],
        ':tipo_identificacion' => $data['tipo_identificacion'] ?? null,
        ':numero_identificacion' => $data['numero_identificacion'] ?? null,
        ':direccion' => $data['direccion'] ?? null,
        ':fecha_ingreso' => $data['fecha_ingreso'] ?? date('Y-m-d'),
        ':telefono_emergencia' => $data['telefono_emergencia'] ?? null,
        ':foto_url' => $data['foto_url'] ?? null,
        ':observaciones' => $data['observaciones'] ?? null,
        ':id_usuario' => $id_usuario,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $id_repartidor = $row['id_repartidor'];

    // Insertar teléfonos
    if (!empty($data['telefonos'])) {
        foreach ($data['telefonos'] as $tel) {
            if (empty($tel['numero'])) continue;
            $stmtTel = $conexion->prepare("
                INSERT INTO telefonos (numero, tipo, activo)
                VALUES (:numero, :tipo, true)
                RETURNING id_telefono
            ");
            $stmtTel->execute([
                ':numero' => $tel['numero'],
                ':tipo' => $tel['tipo'] ?? 'PRINCIPAL'
            ]);
            $id_telefono = $stmtTel->fetchColumn();

            $stmtRel = $conexion->prepare("
                INSERT INTO repartidor_telefono (id_repartidor, id_telefono)
                VALUES (:id_repartidor, :id_telefono)
            ");
            $stmtRel->execute([':id_repartidor' => $id_repartidor, ':id_telefono' => $id_telefono]);
        }
    }

    // Insertar correos
    if (!empty($data['correos'])) {
        foreach ($data['correos'] as $cor) {
            if (empty($cor['email'])) continue;
            $stmtCor = $conexion->prepare("
                INSERT INTO correos (email, tipo, activo, verificado)
                VALUES (:email, :tipo, true, false)
                RETURNING id_correo
            ");
            $stmtCor->execute([
                ':email' => $cor['email'],
                ':tipo' => $cor['tipo'] ?? 'PRINCIPAL'
            ]);
            $id_correo = $stmtCor->fetchColumn();

            $stmtRelCor = $conexion->prepare("
                INSERT INTO repartidor_correo (id_repartidor, id_correo)
                VALUES (:id_repartidor, :id_correo)
            ");
            $stmtRelCor->execute([':id_repartidor' => $id_repartidor, ':id_correo' => $id_correo]);
        }
    }

    // Habilidades: cada tipo de vehículo trae su propia licencia
    // (numero_licencia, fecha_vencimiento_licencia), porque una
    // licencia de motocicleta y una de carro son documentos distintos.
    if (!empty($data['habilidades'])) {
        $stmtHab = $conexion->prepare("
            INSERT INTO repartidor_habilidad (id_repartidor, tipo_vehiculo, nivel, numero_licencia, fecha_vencimiento_licencia)
            VALUES (:id_repartidor, :tipo, :nivel, :numero_licencia, :fecha_vencimiento)
            ON CONFLICT (id_repartidor, tipo_vehiculo) DO UPDATE
                SET numero_licencia = EXCLUDED.numero_licencia,
                    fecha_vencimiento_licencia = EXCLUDED.fecha_vencimiento_licencia
        ");
        foreach ($data['habilidades'] as $hab) {
            $tipo = is_array($hab) ? ($hab['tipo_vehiculo'] ?? '') : $hab;
            if (empty($tipo)) continue;
            $stmtHab->execute([
                ':id_repartidor'     => $id_repartidor,
                ':tipo'              => $tipo,
                ':nivel'             => (is_array($hab) ? ($hab['nivel'] ?? 'COMPETENTE') : 'COMPETENTE'),
                ':numero_licencia'   => is_array($hab) ? ($hab['numero_licencia'] ?? null) : null,
                ':fecha_vencimiento' => is_array($hab) ? ($hab['fecha_vencimiento_licencia'] ?? null) : null,
            ]);
        }
    }

    $conexion->commit();
    echo json_encode([
        'success' => true,
        'id_repartidor' => $id_repartidor,
        'id_usuario' => $id_usuario,
        'acceso_creado' => true,
    ]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
