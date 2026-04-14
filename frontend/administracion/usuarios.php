<?php

include(__DIR__ . "/../../backend/conexion.php");

// ========================
// SUCURSALES
// ========================
$querySucursales = $conexion->query("SELECT id_sucursal, nombre FROM sucursales WHERE estado = TRUE");
$sucursales = $querySucursales->fetchAll(PDO::FETCH_ASSOC);

// ========================
// USUARIOS
// ========================
$query = $conexion->query("
    SELECT u.*, r.nombre AS rol_nombre
    FROM usuarios u
    LEFT JOIN roles r ON u.id_rol = r.id_rol
    ORDER BY u.id_usuario ASC
");
$usuarios = $query->fetchAll(PDO::FETCH_ASSOC);

// ========================
// ROLES
// ========================
$queryRoles = $conexion->query("SELECT id_rol, nombre FROM roles");
$roles = $queryRoles->fetchAll(PDO::FETCH_ASSOC);

// ========================
// PROCESAR FORMULARIO
// ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'];
    $id = $_POST['id_usuario'] ?? null;
    $nombre = $_POST['nombre'];
    $user_name = $_POST['user_name'];
    $clave = $_POST['clave'];
    $id_rol = $_POST['id_rol'];
    $id_sucursal = $_POST['id_sucursal'];
    $estado = $_POST['estado'];

    // IMAGEN
    $url_default = "/sistema-gestor-de-farmacias/assets/img/usuarios/default.png";
    $imagen_actual = $_POST['imagen_actual'] ?? null;

    // Si es crear y no hay imagen nueva, usamos el default
    // Si es editar y no hay imagen nueva, mantenemos la actual
    $imagen_final = ($accion === 'crear') ? $url_default : $imagen_actual;

    if (!empty($_FILES['imagen']['name'])) {
        $dir = __DIR__ . "/../../assets/img/usuarios/";
        $url_base = "/sistema-gestor-de-farmacias/assets/img/usuarios/";

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $nombre_img = uniqid() . "_" . $_FILES['imagen']['name'];
        $ruta = $dir . $nombre_img;

        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta)) {
            $imagen_final = $url_base . $nombre_img;
        }
    }

    $clave_hash = null;
    if (!empty($clave)) {
        $clave_hash = password_hash($clave, PASSWORD_DEFAULT);
    }

    try {
        if ($accion === 'crear') {
            // 1. Verificar si el usuario ya existe antes de intentar insertar
            $checkUser = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = ?");
            $checkUser->execute([$user_name]);

            if ($checkUser->fetchColumn() > 0) {
                echo "<script>alert('Error: El nombre de usuario [@$user_name] ya está en uso. Elige otro.'); window.history.back();</script>";
                exit;
            }

            // 2. Si no existe, procedemos con UN SOLO insert
            $sql = "INSERT INTO usuarios (nombre, usuario, contrasena, id_rol, id_sucursal, estado, imagen_url) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([$nombre, $user_name, $clave_hash, $id_rol, $id_sucursal, $estado, $imagen_final]);
        }

        if ($accion === 'editar') {
            $params = [$nombre, $user_name, $id_rol, $id_sucursal, $estado, $imagen_final];
            $sql = "UPDATE usuarios SET nombre=?, usuario=?, id_rol=?, id_sucursal=?, estado=?, imagen_url=?";

            if ($clave_hash) {
                $sql .= ", contrasena=?";
                $params[] = $clave_hash;
            }

            $sql .= " WHERE id_usuario=?";
            $params[] = $id;

            $stmt = $conexion->prepare($sql);
            $stmt->execute($params);
        }
    } catch (PDOException $e) {
        // Capturamos el error de duplicado (23505) por si acaso fallara la validación manual
        if ($e->getCode() == 23505) {
            echo "<script>alert('Error: El usuario ya existe en la base de datos.'); window.history.back();</script>";
        } else {
            echo "<script>alert('Error técnico: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        }
        exit;
    }

    // Recargar datos para que se vea el cambio inmediatamente
    $query = $conexion->query("SELECT u.*, r.nombre AS rol_nombre FROM usuarios u LEFT JOIN roles r ON u.id_rol = r.id_rol ORDER BY u.id_usuario ASC");
    $usuarios = $query->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <link rel="stylesheet" href="/sistema-gestor-de-farmacias/frontend/administracion/styleusuarios.css">
</head>

<body>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0 text-success">
                    <span class="material-symbols-rounded align-middle me-2">group</span>
                    Gestión de Usuarios
                </h2>
                <p class="text-muted mb-0">Administra los usuarios del sistema, sus roles y permisos de acceso</p>
            </div>
            <div class="d-flex gap-3">
    <button type="button" class="btn btn-success" onclick="abrirModal('crear')">
        <span class="material-symbols-rounded align-middle me-1">add</span>
        Nuevo Usuario
    </button>
                
    <button type="button" class="btn btn-outline-consultar" onclick="window.location.href='menuprincipal.php?mod=consulta_usuarios'">
        <span class="material-symbols-rounded align-middle me-1">search</span>
        Consultar
    </button>
</div>
        </div>

        <div class="usuarios-wrapper">
            <div class="usuarios-grid">

                <?php if (empty($usuarios)): ?>
                    <p class="no-usuarios" style="grid-column: 1 / -1; text-align: center;">No hay Usuarios registrados.</p>
                <?php endif; ?>

                <?php foreach ($usuarios as $u): ?>
                    <div class="usuario-card"
                        data-usuario='<?php echo json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'
                        onclick="mostrarUsuario(JSON.parse(this.dataset.usuario))">

                        <div class="card-imagen">
                            <?php
                            // Usamos la ruta de la imagen del usuario o una por defecto
                            $imagen_final = $u['imagen_url'] ?? '/sistema-gestor-de-farmacias/assets/img/usuarios/default.png';
                            ?>
                            <img src="<?php echo htmlspecialchars($imagen_final); ?>" alt="Imagen de Usuario">
                        </div>

                        <div class="card-cuerpo">
                            <span class="card-fecha">
                                Rol: <?php echo htmlspecialchars($u['rol_nombre'] ?? 'N/A'); ?>
                            </span>
                            <h2 class="nombre-usuario"><?php echo htmlspecialchars($u['nombre']); ?></h2>
                            <p class="card-descripcion">
                                Usuario: <?php echo htmlspecialchars($u['usuario']); ?>
                            </p>
                        </div>

                        <div class="card-metricas">
                            <div class="metrica metrica-doc" style="flex-basis: 33.33%;">
                                <span class="metrica-valor"><?php echo htmlspecialchars($u['id_usuario']); ?></span>
                                <span class="metrica-etiqueta">ID</span>
                            </div>
                            <div class="metrica metrica-base" style="flex-basis: 33.33%;">
                                <span class="metrica-valor"><?php echo htmlspecialchars($u['id_rol']); ?></span>
                                <span class="metrica-etiqueta">ROL ID</span>
                            </div>
                            <div class="metrica <?php echo $u['estado'] ? 'metrica-success' : 'metrica-danger'; ?>" style="flex-basis: 33.33%;">
                                <span class="metrica-valor"><?php echo $u['estado'] ? 'ACTIVO' : 'INACTIVO'; ?></span>
                                <span class="metrica-etiqueta">ESTADO</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="modal" class="modal">
            <div class="modal-contenido">
                <span class="cerrar" onclick="manejarCierre()">&times;</span>
                <h2 id="modal-titulo">Gestión de Usuario</h2>

                <form method="POST" class="formulario-gestion" id="formulario-gestion" enctype="multipart/form-data">

                    <!-- IDs CORREGIDOS -->
                    <input type="hidden" name="accion" id="accion">
                    <input type="hidden" name="id_usuario" id="id_usuario">

                    <div class="grid-inputs">

                        <label>Nombre Completo:</label>
                        <input type="text" name="nombre" id="nombre" placeholder="Nombre completo" required autocomplete="off">

                        <label>Usuario:</label>
                        <input type="text" name="user_name" id="user_name" placeholder="Nombre de usuario" required autocomplete="off">

                        <label>Clave:</label>
                        <input type="password" name="clave" id="clave" placeholder="Dejar vacío para no cambiar" autocomplete="new-password">

                        <label>Rol:</label>
                        <select name="id_rol" id="id_rol" required>
                            <option value="">Seleccione Rol</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['id_rol']; ?>">
                                    <?php echo htmlspecialchars($r['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- NUEVO CAMPO (porque existe en tu tabla) -->
                        <label>Sucursal:</label>
                        <select name="id_sucursal" id="id_sucursal" required>
                            <option value="">Seleccione Sucursal</option>
                            <?php foreach ($sucursales as $s): ?>
                                <option value="<?php echo $s['id_sucursal']; ?>">
                                    <?php echo htmlspecialchars($s['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- IMAGEN CORREGIDA -->
                        <input type="hidden" name="imagen_actual" id="imagen_actual">

                        <label>Imagen:</label>
                        <input type="file" name="imagen" id="imagen" accept="image/*">

                        <!-- ESTADO CORREGIDO -->
                        <label>Estado:</label>
                        <select name="estado" id="estado">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>

                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-guardar" id="btn-guardar">Guardar</button>
                    </div>

                </form>
            </div>
        </div>

        <script src="/sistema-gestor-de-farmacias/frontend/administracion/scriptusuarios.js"></script>

</body>

</html>