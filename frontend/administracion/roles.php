<?php
include(__DIR__ . "/../../backend/conexion.php");

// Configuración de la conexión PDO
try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<script>confirm('Error de Conexión: " . $e->getMessage() . "');</script>";
    exit();
}

$system_message = '';

// ========================
// INSERT
// ========================
if (isset($_POST['insert'])) {

    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $activo = isset($_POST['activo']) ? true : false;

    try {
        // 🔥 ADAPTADO
        $sql = "INSERT INTO roles (nombre, descripcion, estado) VALUES (:nombre, :descripcion, :estado)";
        $stmt = $pdo->prepare($sql);

        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':descripcion', $descripcion);
        $stmt->bindValue(':estado', $activo, PDO::PARAM_BOOL);

        $stmt->execute();

        $system_message = 'Rol Grabado con Éxito';
    } catch (PDOException $e) {
        if ($e->getCode() == '23505') {
            $system_message = 'Error al grabar: El Nombre del Rol ya existe.';
        } else {
            $system_message = 'Error al grabar: ' . $e->getMessage();
        }
    }
}

// ========================
// UPDATE
// ========================
if (isset($_POST['actualizar'])) {

    $id_original = $_POST['id_original'];
    $actualizar_nombre = trim($_POST['nombre_update']);
    $actualizar_descripcion = trim($_POST['descripcion_update']);
    $actualizar_activo = isset($_POST['activo_update']) ? true : false;

    try {
        // 🔥 ADAPTADO
        $sql = "UPDATE roles 
                SET nombre = :nombre, descripcion = :descripcion, estado = :estado 
                WHERE id_rol = :id";

        $stmt = $pdo->prepare($sql);

        $stmt->bindValue(':nombre', $actualizar_nombre);
        $stmt->bindValue(':descripcion', $actualizar_descripcion);
        $stmt->bindValue(':estado', $actualizar_activo, PDO::PARAM_BOOL);
        $stmt->bindValue(':id', $id_original);

        $stmt->execute();

        $system_message = 'Datos modificados con éxito';
    } catch (PDOException $e) {
        if ($e->getCode() == '23505') {
            $system_message = 'Error al modificar: El Nombre del Rol ya existe.';
        } else {
            $system_message = 'Error al modificar datos: ' . $e->getMessage();
        }
    }
}
?>

<style>
    /* Importación de Fuente */
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

    :root {
        --color-bg-light: #f9fafb;
        /* Fondo muy claro, casi blanco */
        --color-card-bg: #ffffff;
        /* Fondo de contenedores blanco puro */
        --color-text-dark: #222222;
        /* Texto principal oscuro */
        --color-text-secondary: #37383b;
        /* Texto secundario gris */
        --color-primary: #1067b9;
        /* Verde/Azul primario para acción (más fresco) */
        --color-success: #16a34a;
        /* Verde para éxito */
        --color-danger: #dc2626;
        /* Rojo para error */
        --color-border: #e5e7eb;
        --color-input-bg: #ffffff;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: "Poppins", sans-serif;
    }

    /* Estilos de la página */
    body {
        color: #333;
        /* Centrado horizontal y alineado arriba (para permitir desplazamiento) */
        display: flex;
        justify-content: center;
        align-items: flex-start;
        min-height: 100vh;
        padding-top: 0 !important;
        /* Eliminamos el espacio de 40px que tenías */
    }

    h2 {
        text-align: left !important;
        margin-bottom: 5px !important;
        font-size: 24px;
        font-weight: 700;
    }

    /* --- Estilo Principal del Contenedor (Envoltorio Blanco) --- */
    .roles-form-centrado {
        color: black;
        background-color: #ffffff;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        margin: 20px auto;
        margin-top: 0 !important;
        /* Sube el contenedor al borde superior */
        padding-top: 20px;
        width: 1200px;
        max-width: 95%;
        /* Asegura responsividad */
        height: auto;
        transition: all 0.3s ease-in-out;
    }

    .roles-form-centrado:hover {
        box-shadow: 0 12px 25px rgba(0, 0, 0, 0.2);
    }

    /* --- ESTILOS PARA EL FORMULARIO DE CREACIÓN (SUPERIOR) --- */

    /* Forzar que la fila no rompa línea y alinee al fondo (donde terminan los inputs) */
    .card-body form.row {
        align-items: flex-end !important;
    }

    /* Hacer que el input y el textarea tengan bordes redondeados y misma altura inicial */
    .card-body .form-control {
        border-radius: 50px;
        /* Bordes circulares */
        padding: 10px 20px;
        border: 1px solid #dee2e6;
        background-color: #f8fafc;
    }



    /* Ajuste específico para el Textarea de descripción */
    .card-body textarea.form-control {
        min-height: 45px;
        /* Altura idéntica a los inputs de Bootstrap */
        height: 45px;
        border-radius: 20px;
        /* Un poco menos circular para que el texto no se pegue */
        resize: none;
        overflow: hidden;
        padding-top: 10px;
        transition: all 0.3s ease;
    }

    /* Estilo específico del Botón Guardar (Igual al del Modal) */
    /* Botón Guardar del formulario superior */
    .card-body .btn-success {
        height: 42px;
        border-radius: 50px;
        padding: 0 25px;
        background-color: #37383b; /* Gris oscuro igual al modal */
        border: none;
        color: white;
        font-weight: 600;
        font-family: 'Poppins', sans-serif;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        
        /* PREPARACIÓN PARA EL EFECTO */
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        box-shadow: 0 2px 5px rgba(0,0,0,0.15);
        cursor: pointer;
    }

    /* EFECTO HOVER: SE LEVANTA */
    .card-body .btn-success:hover {
        background-color: #222222; /* Un poco más oscuro */
        transform: translateY(-4px); /* Se levanta 4 píxeles */
        box-shadow: 0 8px 15px rgba(0,0,0,0.3); /* Sombra más profunda para dar realismo */
    }

    /* EFECTO CLICK: SE HUNDE (Opcional para feedback) */
    .card-body .btn-success:active {
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(0,0,0,0.2);
    }

    /* Checkbox Verde (Bootstrap usa accent-color automáticamente en versiones nuevas, pero aseguramos) */
    .form-check-input:checked {
        background-color: #198754;
        border-color: #198754;
    }

    /* --- MODIFICACIÓN CLAVE: Layout Lado a Lado (Flexbox) --- */
    .content-container {
        display: flex;
        gap: 40px;
        /* Espacio entre el formulario y la tabla */
        width: 100%;
        /* Por defecto, apilamos verticalmente para móviles */
        flex-direction: column;
    }

    .form-wrapper {
        flex: 0 0 350px;
        /* Definimos el ancho deseado para el formulario */
        width: 100%;
        /* Ocupa todo el ancho en modo columna */
    }

    .table-wrapper {
        flex-grow: 1;
        /* La tabla toma el espacio restante */
        overflow-x: auto;
        /* Permite scroll horizontal para tablas anchas */
    }

    /* Media query para activar el layout lado a lado en escritorio */
    @media (min-width: 900px) {
        .content-container {
            flex-direction: row;
        }

        .form-wrapper {
            width: 350px;
        }

        .table-wrapper {
            min-width: 600px;
            /* Mínimo para que la tabla no se comprima demasiado */
        }
    }


    /* --- Estilos de Etiquetas (Labels) --- */
    label {
        font-weight: bold;
        font-size: 14px;
    }

    /* Aplicamos display: block y margin-top a los labels del formulario */
    .form-wrapper label:not(.checkbox-label) {
        display: block;
        margin-top: 25px;
    }

    /* --- Estilos de Inputs de Texto/Número --- */
    input[type="text"] {
        width: 100%;
        /* Ocupan todo el ancho del form-wrapper */
        margin-top: 8px;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 50px;
        background-color: #f9f9f9;
        font-size: 14px;
        transition: border 0.3s ease, box-shadow 0.3s ease;
    }

    input:focus {
        border-color: #000000;
        outline: none;
        box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
    }

    textarea[name="descripcion"] {
        width: 100%;
        margin-top: 8px;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 20px;
        background-color: #f9f9f9;
        font-size: 14px;
        resize: vertical;
        min-height: 80px;
        transition: border 0.3s ease, box-shadow 0.3s ease;
    }

    /* --- Estilos de Botones de Submit/Guardar --- */
    input[type="submit"][name="insert"],
    button[type="submit"] {
        width: 100%;
        /* Ocupan todo el ancho del form-wrapper */
        padding: 12px;
        margin-top: 35px;
        background-color: var(--color-success);
        color: white;
        border: none;
        border-radius: 50px;
        font-size: 16px;
        cursor: pointer;
        transition: background-color 0.3s ease, transform 0.1s;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    input[type="submit"][name="insert"]:hover,
    button[type="submit"]:hover {
        background-color: var(--color-success);
        transform: translateY(-2px);
        box-shadow: 0 6px 10px rgba(0, 0, 0, 0.3);
    }

    /* --- Estilos del Checkbox (Mejorados para el diseño) --- */
    .form-check-input:checked {
        background-color: #16a34a !important;
        border-color: #16a34a !important;
    }

    .form-check-input:focus {
        box-shadow: 0 0 0 0.2rem rgba(22, 163, 74, 0.25) !important;
    }

    .checkbox-container {
        display: flex;
        align-items: center;
        margin-top: 25px;
        margin-left: 5px;
    }

    .checkbox-container input[type="checkbox"] {
        margin-top: 0;
        margin-right: 8px;
        appearance: none;
        width: 20px;
        height: 20px;
        border: 2px solid var(--color-success);
        border-radius: 4px;
        background-color: #fff;
        cursor: pointer;
        position: relative;
        transition: all 0.2s;
    }

    .checkbox-container input[type="checkbox"]:checked {
        /* Fondo verde al estar seleccionado */
        background-color: var(--color-success);
        border-color: var(--color-success);
    }

    .checkbox-container input[type="checkbox"]:checked::after {
        content: '\f00c';
        /* Icono de Font Awesome check */
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        color: white;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 12px;
    }

    .checkbox-label {
        margin-top: 0 !important;
    }


    /* --- ESTILOS DE LA TABLA (READ) --- */
    .table-wrapper h3 {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 15px;
        color: #000000;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 0;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }

    th,
    td {
        border: none;
        border-bottom: 1px solid #eee;
        padding: 12px;
        text-align: left;
        font-size: 14px;
    }

    th {
        background-color: #000000;
        color: white;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
        border-bottom: none;
    }

    tr:nth-child(even) {
        background-color: #f7f7f7;
    }

    tr:last-child td {
        border-bottom: none;
    }

    /* --- ESTILOS DE BOTÓN DE EDICIÓN Y BORRAR EN LA TABLA --- */
    .btn-editar {
        /* Se mantiene el estilo de botón negro para que coincida con el botón Guardar */
        background-color: #000000;
        color: white;
        padding: 8px 12px;
        border: none;
        border-radius: 50px;
        cursor: pointer;
        margin: 0;
        font-size: 13px;
        transition: background-color 0.2s, transform 0.1s;
    }

    .btn-editar:hover {
        background-color: #333333;
        transform: scale(1.05);
    }

    td a {
        color: #e74c3c;
        text-decoration: none;
        font-size: 13px;
        margin-left: 10px;
        transition: color 0.2s;
    }

    td a:hover {
        text-decoration: underline;
        color: #c0392b;
    }

    /* ---------------------------------------------------- */
    /* MODAL DE EDICIÓN (MODERNO Y LIMPIO) */
    /* ---------------------------------------------------- */

    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        /* Fondo oscuro sutil */
        background: rgba(0, 0, 0, 0.4);
        justify-content: center;
        align-items: center;
        animation: fadeIn 0.3s ease-out;
    }

    .modal-content {
        background: #ffffff;
        /* Fondo blanco puro */
        border-radius: 12px;
        /* Bordes redondeados modernos */
        padding: 30px;
        width: 400px;
        max-width: 90%;
        color: #334155;
        /* Texto oscuro suave */
        position: relative;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    /* Título alineado a la izquierda */
    .modal-content h3 {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 25px;
        color: #1e293b;
        text-align: center;
    }

    /* Estilo de los Labels */
    .modal-content label {
        display: block;
        margin-top: 15px;
        margin-bottom: 5px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #64748b;
    }

    /* Inputs y Textarea Modernos */
    .modal-content input[type="text"],
    .modal-content textarea {
        width: 100%;
        padding: 10px 15px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        /* Bordes redondeados */
        background-color: #f8fafc;
        /* Fondo gris muy claro */
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .modal-content input:focus,
    .modal-content textarea:focus {
        border-color: #94a3b8;
        outline: none;
        box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.1);
        background-color: #fff;
    }

    .modal-content textarea {
        resize: none;
        min-height: 80px;
    }

    /* Checkbox en color Verde */
    .checkbox-container {
        display: flex;
        align-items: center;
        margin-top: 20px;
        justify-content: flex-start;
    }

    .checkbox-container input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        /* EL CAMBIO CLAVE A VERDE Nativo */
        accent-color: #28a745;
        margin-right: 10px;
    }

    .checkbox-label {
        margin-top: 0 !important;
        font-size: 0.9rem;
    }

    /* Botón Actualizar Verde y Largo */
    .modal-content .btn-guardar {
        width: 50%;
        padding: 12px;
        margin-top: 30px;
        /* EL CAMBIO CLAVE A VERDE */
        background-color: #28a745;
        color: white;
        border: none;
        border-radius: 50px;
        /* Estilo circular */
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.3s ease, transform 0.1s;
        box-shadow: 0 4px 10px rgba(40, 167, 69, 0.2);
    }

    .modal-content .btn-guardar:hover {
        background-color: #218838;
        transform: translateY(-1px);
    }

    /* Botón Cerrar (X) */
    .close {
        position: absolute;
        top: 15px;
        right: 25px;
        font-size: 24px;
        color: #94a3b8;
        cursor: pointer;
        transition: color 0.2s;
    }

    .close:hover {
        color: #1e293b;
    }

    /* Animación */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .form-actions {
        margin-top: -10px;
        text-align: center;
    }

    .btn-guardar {
        background-color: var(--color-text-secondary);
        color: white;
        border: none;
        padding: 12px 30px;
        font-size: 1rem;
        border-radius: 50px;
        cursor: pointer;
        width: 100%;
        font-weight: 600;
        transition: background 0.3s ease, transform 0.1s;
        box-shadow: 0 2px 6px rgba(22, 22, 22, 0.3);
    }

    .btn-guardar:hover {
        background-color: var(--color-text-dark);
        transform: translateY(-2px);
    }

    /* --- ESTILOS PARA MENSAJES DEL SISTEMA --- */
    .message-box {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        border-radius: 8px;
        font-weight: 600;
        z-index: 1001;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        animation: slideIn 0.5s ease-out;
    }

    .message-box.success {
        background-color: var(--color-success);
        color: white;
    }

    .message-box.error {
        background-color: var(--color-danger);
        color: white;
    }

    @keyframes slideIn {
        from {
            right: -300px;
            opacity: 0;
        }

        to {
            right: 20px;
            opacity: 1;
        }
    }
</style>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Gestión de Roles</title>
</head>

<body>

    <?php if (!empty($system_message)) : ?>
        <div id="system-message" class="alert alert-success position-fixed end-0 m-4" style="top: 80px; z-index: 2000;">
            <?= htmlspecialchars($system_message) ?>
        </div>

        <script>
            setTimeout(() => {
                const msg = document.getElementById('system-message');
                if (msg) {
                    msg.style.transition = "opacity 0.5s";
                    msg.style.opacity = "0";
                    setTimeout(() => msg.remove(), 500);
                }
            }, 3000);
        </script>
    <?php endif; ?>

    <div class="container-fluid py-4">

        <div class="d-flex align-items-center mb-4">
            <div>
                <h2 class="mb-0 text-success">
                    <span class="material-symbols-rounded align-middle me-2">badge</span>
                    Gestión de Roles
                </h2>
                <p class="text-muted mb-0">
                    Administra los roles del sistema, sus permisos y niveles de acceso
                </p>
            </div>

        </div>

        <!-- CONTENIDO -->
        <div class="card shadow-sm border-0">

            <div class="card-body">

                <!-- FORMULARIO ARRIBA -->
                <div class="mb-4">

                    <h5 class="mb-3 text-success">
                        <span class="material-symbols-rounded align-middle me-1">edit</span>
                        Crear Rol
                    </h5>

                    <form method="POST" action="" class="row g-3">

                        <div class="col-md-3">
                            <label class="form-label">Nombre del Rol</label>
                            <input type="text" name="nombre" id="nombre" placeholder="Ej: Administrador, Vendedor" required maxlength="255" class="form-control" autocomplete="off">
                        </div>

                        <div class="col-md-5">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" id="descripcion" placeholder="Detalle las funciones del rol (opcional)" maxlength="255" class="form-control"></textarea>
                        </div>

                        <div class="col-md-2 d-flex align-items-end justify-content-center">
                            <div class="form-check mb-2"> <input type="checkbox" class="form-check-input" name="activo" checked>
                                <input type="checkbox" class="form-check-input" name="activo" checked>
                                <label class="form-check-label">Activo</label>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <button type="submit" name="insert" class="btn btn-success" style="background-color: #28a745; border-color: #28a745;">
                                Guardar Rol
                            </button>
                        </div>
                    </form>

                </div>

                <!-- TABLA ABAJO -->
                <div>

                    <h5 class="mb-3 text-success">
                        <span class="material-symbols-rounded align-middle me-1">list</span>
                        Roles Registrados
                    </h5>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">

                            <thead class="table-success">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th>Activo</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php
                                try {
                                    $sql = "SELECT id_rol, nombre, descripcion, estado FROM roles";
                                    $stmt = $pdo->query($sql);

                                    if ($stmt->rowCount() > 0) {
                                        while ($fila = $stmt->fetch()) {

                                            $id = $fila['id_rol'];
                                            $nombre = $fila['nombre'];
                                            $descripcion = $fila['descripcion'];

                                            $activo_js = $fila['estado'] ? 'true' : 'false';
                                            $activo_text = $fila['estado'] ? 'Sí' : 'No';
                                ?>
                                            <tr>
                                                <td><?= $id ?></td>
                                                <td><?= $nombre ?></td>
                                                <td><?= $descripcion ?></td>
                                                <td>
                                                    <span class="badge <?= $fila['estado'] ? 'bg-success' : 'bg-danger' ?>">
                                                        <?= $activo_text ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-outline-success"
                                                        onclick="abrirModalEditar(<?= $id ?>, '<?= $nombre ?>', '<?= $descripcion ?>', <?= $activo_js ?>)">
                                                        Editar
                                                    </button>
                                                </td>
                                            </tr>
                                <?php
                                        }
                                    } else {
                                        echo '<tr><td colspan="5" class="text-center text-muted">No hay roles registrados</td></tr>';
                                    }
                                } catch (PDOException $e) {
                                    echo '<tr><td colspan="5">Error: ' . $e->getMessage() . '</td></tr>';
                                }
                                ?>
                            </tbody>

                        </table>
                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- MODAL (NO SE TOCA LÓGICA) -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <span class="close" onclick="manejarCierreRol()">
                <i class="fas fa-times"></i>
            </span>

            <h3>Editar Rol</h3>

            <form method="POST" action="" id="formEditar">
                <input type="hidden" name="id_original" id="id_original">

                <label for="nombre_update">Nombre del Rol:</label>
                <input type="text" name="nombre_update" id="nombre_update" required maxlength="60">

                <label for="descripcion_update">Descripción:</label>
                <textarea name="descripcion_update" id="descripcion_update" maxlength="255"></textarea>

                <div class="checkbox-container">
                    <input type="checkbox" name="activo_update" id="activo_update">
                    <label for="activo_update" class="checkbox-label">Activo</label>
                </div>

                <div class="form-actions">
                    <button type="submit" name="actualizar" class="btn-guardar" id="btn-actualizar">
                        Actualizar Rol
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modalEditar = document.getElementById('modalEditar');
        const formEditar = document.getElementById('formEditar');

        // Estado inicial del formulario en edición (para roles)
        let initialFormDataRol = null;

        // ------------------------------
        // Obtener estado actual del formulario de rol
        // ------------------------------
        function getFormStateRol() {
            const form = document.getElementById('formEditar');
            if (!form) return '';

            const pairs = [];

            // *** IDs esperados en el HTML del modal de edición de roles ***
            const idInput = document.getElementById('id_original'); // Asume que el ID oculto es 'id_original'
            const nombreInput = document.getElementById('nombre_update');
            const descripcionInput = document.getElementById('descripcion_update'); // Nuevo campo de rol
            const activoCheckbox = document.getElementById('activo_update');

            // Construir la cadena de estado
            pairs.push(`id:${idInput.value.trim()}`);
            pairs.push(`nombre:${nombreInput.value.trim()}`);
            pairs.push(`descripcion:${descripcionInput.value.trim()}`); // Incluir descripción
            pairs.push(`activo:${activoCheckbox.checked ? '1' : '0'}`);

            return pairs.sort().join('|');
        }

        // ------------------------------
        // Validar cambios del formulario de rol
        // ------------------------------
        function hasChangesRol() {
            if (!initialFormDataRol) {
                return true;
            }

            return getFormStateRol() !== initialFormDataRol;
        }

        // Función para el crecimiento automático del textarea superior
        const descInputInsert = document.getElementById('descripcion');

        if (descInputInsert) {
            descInputInsert.addEventListener("input", function() {
                this.style.height = '45px'; // Vuelve al tamaño de un input
                this.style.height = (this.scrollHeight) + "px"; // Crece según contenido
            });
        }

        // Opcional: También para el textarea del modal (para que ambos funcionen igual)
        const descInputUpdate = document.getElementById('descripcion_update');
        if (descInputUpdate) {
            descInputUpdate.addEventListener("input", function() {
                this.style.height = '45px';
                this.style.height = (this.scrollHeight) + "px";
            });
        }


        // ------------------------------
        // Cerrar modal de rol
        // ------------------------------
        function cerrarModalRol() {
            modalEditar.style.display = 'none';
            document.body.style.overflow = "auto"; // 🔥 evita que se quede bloqueado
            initialFormDataRol = null;
        }

        // ------------------------------
        // Manejar cierre del modal (con validación de rol)
        // ------------------------------
        function manejarCierreRol() {
            if (!hasChangesRol()) {
                cerrarModalRol();
                return;
            }

            if (confirm("Hay cambios sin guardar en el rol. ¿Seguro que quieres salir?")) {
                cerrarModalRol();
            }
        }


        // ------------------------------
        // Función para abrir el modal de edición y cargar los datos del rol
        // Se adapta para recibir 'descripcion' en lugar de 'codigo'
        // ------------------------------
        function abrirModalEditar(id, nombre, descripcion, activo) {
            // *** Carga de valores específicos para ROLES ***
            document.getElementById('id_original').value = id;
            document.getElementById('nombre_update').value = nombre;
            document.getElementById('descripcion_update').value = descripcion; // Nuevo: Cargar descripción

            const activoCheckbox = document.getElementById('activo_update');
            // El PHP pasa 'true' o 'false' como valor literal, pero JavaScript lo trata como string.
            activoCheckbox.checked = activo === true || activo === 'true';

            modalEditar.style.display = 'flex';
            document.body.style.overflow = "hidden";

            // Guardar estado inicial del rol
            initialFormDataRol = getFormStateRol();
        }


        // ------------------------------
        // SUBMIT FORMULARIO (ROLES)
        // ------------------------------
        if (formEditar) {
            formEditar.addEventListener('submit', function(event) {

                if (!hasChangesRol()) {
                    event.preventDefault();
                    alert("Para actualizar el registro del rol debes hacer un cambio.");
                    return;
                }

            });
        }


        // ------------------------------
        // CIERRE MODAL – clic fuera
        // ------------------------------
        window.onclick = function(event) {
            // Si la X de cerrar se usa en el modal:
            if (event.target.classList.contains('close')) {
                manejarCierreRol();
                return;
            }

            if (event.target == modalEditar) {
                manejarCierreRol();
            }
        }

        // ------------------------------
        // CIERRE MODAL – Tecla ESC
        // ------------------------------
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modalEditar.style.display === 'flex') {
                manejarCierreRol();
            }
        });
    </script>

</body>

</html>