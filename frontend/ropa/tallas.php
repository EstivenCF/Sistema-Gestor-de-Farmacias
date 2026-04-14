<?php
// tallas.php - Gestión de Tallas de Ropa

// Iniciar sesión solo si no está activa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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
$system_message_type = '';

// Cambia la definición de $base_url
$base_url = "menuprincipal.php?mod=tallas";

// ========================
// INSERT
// ========================
if (isset($_POST['insert'])) {
    $nombre = trim($_POST['nombre']);
    $activo = isset($_POST['activo']) ? true : false;

    if (empty($nombre)) {
        $_SESSION['system_message'] = 'Error: El nombre de la talla es obligatorio.';
        $_SESSION['system_message_type'] = 'error';
    } else {
        try {
            $sql = "INSERT INTO tallas (nombre, estado) VALUES (:nombre, :estado)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':nombre', $nombre);
            $stmt->bindValue(':estado', $activo, PDO::PARAM_BOOL);
            $stmt->execute();

            $_SESSION['system_message'] = 'Talla creada con éxito';
            $_SESSION['system_message_type'] = 'success';
        } catch (PDOException $e) {
            if ($e->getCode() == '23505') {
                $_SESSION['system_message'] = 'Error: Ya existe una talla con ese nombre.';
            } else {
                $_SESSION['system_message'] = 'Error al crear: ' . $e->getMessage();
            }
            $_SESSION['system_message_type'] = 'error';
        }
    }
    
    echo "<script>window.location.href = '" . $base_url . "';</script>";
    exit();
}

// ========================
// UPDATE
// ========================
if (isset($_POST['actualizar'])) {
    $id_original = intval($_POST['id_original']);
    $nombre_update = trim($_POST['nombre_update']);
    $activo_update = isset($_POST['activo_update']) ? true : false;

    if (empty($nombre_update)) {
        $_SESSION['system_message'] = 'Error: El nombre de la talla es obligatorio.';
        $_SESSION['system_message_type'] = 'error';
    } else {
        try {
            $sql = "UPDATE tallas SET nombre = :nombre, estado = :estado WHERE id_talla = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':nombre', $nombre_update);
            $stmt->bindValue(':estado', $activo_update, PDO::PARAM_BOOL);
            $stmt->bindValue(':id', $id_original);
            $stmt->execute();

            $_SESSION['system_message'] = 'Talla actualizada con éxito';
            $_SESSION['system_message_type'] = 'success';
        } catch (PDOException $e) {
            if ($e->getCode() == '23505') {
                $_SESSION['system_message'] = 'Error: Ya existe una talla con ese nombre.';
            } else {
                $_SESSION['system_message'] = 'Error al actualizar: ' . $e->getMessage();
            }
            $_SESSION['system_message_type'] = 'error';
        }
    }
    
    echo "<script>window.location.href = '" . $base_url . "';</script>";
    exit();
}

// Recuperar mensajes de sesión
if (isset($_SESSION['system_message'])) {
    $system_message = $_SESSION['system_message'];
    $system_message_type = $_SESSION['system_message_type'];
    unset($_SESSION['system_message']);
    unset($_SESSION['system_message_type']);
}

// Agregar columna estado si no existe (por si acaso)
try {
    $pdo->exec("ALTER TABLE tallas ADD COLUMN IF NOT EXISTS estado BOOLEAN DEFAULT TRUE");
} catch (PDOException $e) {
    // La columna ya existe o hay otro error, ignorar
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

    :root {
        --color-bg-light: #f9fafb;
        --color-card-bg: #ffffff;
        --color-text-dark: #222222;
        --color-text-secondary: #37383b;
        --color-primary: #e11d48;
        --color-primary-dark: #be123c;
        --color-success: #28a745;
        --color-warning: #ffc107;
        --color-danger: #dc2626;
        --color-info: #17a2b8;
        --color-border: #e5e7eb;
        --color-input-bg: #ffffff;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: "Poppins", sans-serif;
    }

    body {
        min-height: 100vh;
        padding: 20px;
    }

    .talla-container {
        max-width: 1400px;
        width: 100%;
        margin: 0 auto;
    }

    /* Header con color Rosa/Coral */
    .header-card {
        background: linear-gradient(135deg, #e11d48 0%, #be123c 100%);
        border-radius: 20px;
        padding: 25px 30px;
        margin-bottom: 30px;
        color: white;
        box-shadow: 0 10px 30px rgba(225, 29, 72, 0.3);
    }

    .header-card h1 {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .header-card p {
        font-size: 14px;
        opacity: 0.9;
    }

    /* Cards */
    .card-modern {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .card-modern:hover {
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    }

    .card-header {
        padding: 20px 25px;
        border-bottom: 2px solid #f0f0f0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .card-header i {
        font-size: 24px;
        color: var(--color-primary);
    }

    .card-header h3 {
        font-size: 18px;
        font-weight: 600;
        color: var(--color-text-dark);
        margin: 0;
    }

    .card-body {
        padding: 25px;
    }

    /* Layout lado a lado */
    .content-grid {
        display: flex;
        gap: 30px;
        flex-direction: column;
    }

    @media (min-width: 992px) {
        .content-grid {
            flex-direction: row;
        }
        .form-section {
            flex: 0 0 380px;
        }
        .table-section {
            flex: 1;
        }
    }

    /* Formulario */
    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        font-size: 13px;
        margin-bottom: 8px;
        color: var(--color-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-control-modern {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        font-size: 14px;
        transition: all 0.3s;
        background: #f8f9fa;
    }

    .form-control-modern:focus {
        outline: none;
        border-color: var(--color-primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.1);
    }

    /* Checkbox personalizado */
    .checkbox-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 20px 0;
        padding: 10px 0;
    }

    .checkbox-wrapper input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
        accent-color: var(--color-primary);
    }

    .checkbox-wrapper label {
        margin: 0;
        cursor: pointer;
        font-weight: 500;
        color: var(--color-text-dark);
    }

    /* Botones */
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        justify-content: center;
    }

    .btn-success {
        background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
        color: white;
        width: 100%;
    }

    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(225, 29, 72, 0.3);
    }

    /* Tabla */
    .table-wrapper {
        overflow-x: auto;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table thead tr {
        background: linear-gradient(135deg, #e11d48 0%, #be123c 100%);
        color: white;
    }

    .data-table th {
        padding: 15px;
        text-align: left;
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .data-table td {
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 14px;
    }

    .data-table tbody tr:hover {
        background: #f8f9fa;
    }

    /* Badges */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 12px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 600;
        gap: 5px;
    }

    .badge-success {
        background: #d4edda;
        color: #155724;
    }

    .badge-danger {
        background: #f8d7da;
        color: #721c24;
    }

    /* Badge especial para tallas */
    .talla-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 45px;
        padding: 6px 14px;
        border-radius: 30px;
        font-weight: 700;
        font-size: 13px;
        background: linear-gradient(135deg, #e11d48, #be123c);
        color: white;
        box-shadow: 0 2px 5px rgba(225, 29, 72, 0.2);
    }

    /* Acciones en tabla */
    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .action-btn {
        padding: 8px 20px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: 0.3s;
        cursor: pointer;
        border: none;
        color: white;
        background: var(--color-primary);
    }

    .action-btn:hover {
        transform: translateY(-2px);
        filter: brightness(1.05);
    }

    /* Modal */
    .modal {
        position: fixed;
        inset: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(4px);
        z-index: 9999;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: 0.3s ease-out;
    }

    .modal.show {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }

    .modal-content {
        background: #ffffff;
        border-radius: 16px;
        padding: 25px 30px;
        width: 380px;
        max-width: 90%;
        max-height: 85vh;
        overflow-y: auto;
        position: relative;
        box-shadow: 0 20px 35px rgba(0, 0, 0, 0.2);
        transform: scale(0.95);
        transition: transform 0.25s ease-out;
    }

    .modal.show .modal-content {
        transform: scale(1);
    }

    @media (max-height: 600px) {
        .modal {
            align-items: flex-start;
            padding: 20px;
        }
    }

    html, body {
        height: 100%;
    }

    body.modal-open {
        overflow: hidden;
        height: 100vh;
    }

    .modal-content h3 {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 20px;
        color: #1e293b;
        text-align: center;
        padding-right: 20px;
    }

    .modal-content label {
        display: block;
        margin-top: 12px;
        margin-bottom: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .modal-content input[type="text"] {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background-color: #f8fafc;
        font-size: 0.85rem;
        transition: all 0.2s ease;
    }

    .modal-content input:focus {
        border-color: var(--color-primary);
        outline: none;
        box-shadow: 0 0 0 2px rgba(225, 29, 72, 0.1);
        background-color: #fff;
    }

    .close {
        position: absolute;
        top: 12px;
        right: 18px;
        font-size: 22px;
        color: #94a3b8;
        cursor: pointer;
        transition: color 0.2s;
        line-height: 1;
    }

    .close:hover {
        color: #1e293b;
    }

    .switch-container {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 15px;
    }

    .switch {
        position: relative;
        display: inline-block;
        width: 45px;
        height: 24px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        inset: 0;
        background-color: #ccc;
        border-radius: 50px;
        transition: 0.3s;
    }

    .slider:before {
        content: "";
        position: absolute;
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        border-radius: 50%;
        transition: 0.3s;
    }

    input:checked + .slider {
        background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
    }

    input:checked + .slider:before {
        transform: translateX(21px);
    }

    .switch-text {
        font-size: 0.85rem;
        font-weight: 500;
        color: #334155;
    }

    .modal-buttons {
        display: flex;
        gap: 12px;
        margin-top: 25px;
        justify-content: flex-end;
    }

    .btn-cancelar {
        flex: 1;
        padding: 10px;
        background: transparent;
        border: 1.5px solid #e2e8f0;
        border-radius: 40px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        color: #64748b;
    }

    .btn-cancelar:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .btn-actualizar {
        flex: 1;
        padding: 10px;
        background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
        color: white;
        border: none;
        border-radius: 40px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-actualizar:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(225, 29, 72, 0.3);
    }

    /* Estadísticas */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        transition: transform 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-card i {
        font-size: 32px;
        color: var(--color-primary);
        margin-bottom: 10px;
    }

    .stat-card .number {
        font-size: 28px;
        font-weight: 700;
        color: var(--color-text-dark);
    }

    .stat-card .label {
        font-size: 13px;
        color: var(--color-text-secondary);
        margin-top: 5px;
    }

    /* Scrollbar personalizado para modal */
    .modal-content::-webkit-scrollbar {
        width: 6px;
    }

    .modal-content::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .modal-content::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 10px;
    }

    .modal-content::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    @media (max-width: 768px) {
        body {
            padding: 10px;
        }
        .header-card h1 {
            font-size: 22px;
        }
        .card-body {
            padding: 15px;
        }
        .data-table th,
        .data-table td {
            padding: 10px;
        }
        .modal-content {
            width: 90%;
            padding: 20px;
        }
        .talla-badge {
            min-width: 35px;
            padding: 4px 10px;
            font-size: 11px;
        }
    }
</style>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tallas - Farmacia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <div class="talla-container">
        <!-- Header con color Rosa -->
        <div class="header-card">
            <h1>
                <i class="fas fa-ruler-combined"></i>
                Gestión de Tallas
            </h1>
            <p>Administre las tallas disponibles para los productos de ropa y conveniencia</p>
        </div>

        <!-- Estadísticas -->
        <?php
        try {
            $total_tallas = $pdo->query("SELECT COUNT(*) FROM tallas")->fetchColumn();
            $tallas_activas = $pdo->query("SELECT COUNT(*) FROM tallas WHERE estado = TRUE")->fetchColumn();
            $tallas_inactivas = $total_tallas - $tallas_activas;
        } catch (PDOException $e) {
            $total_tallas = 0;
            $tallas_activas = 0;
            $tallas_inactivas = 0;
        }
        ?>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-ruler-combined"></i>
                <div class="number"><?= $total_tallas ?></div>
                <div class="label">Total Tallas</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <div class="number"><?= $tallas_activas ?></div>
                <div class="label">Tallas Activas</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-ban"></i>
                <div class="number"><?= $tallas_inactivas ?></div>
                <div class="label">Tallas Inactivas</div>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="content-grid">
            <!-- Sección del formulario (CREAR) -->
            <div class="form-section">
                <div class="card-modern">
                    <div class="card-header">
                        <i class="fas fa-plus-circle"></i>
                        <h3>Nueva Talla</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="formCrear">
                            <div class="form-group">
                                <label><i class="fas fa-tag"></i> Nombre de la Talla *</label>
                                <input type="text" name="nombre" id="nombre" class="form-control-modern"
                                    placeholder="Ej: XS, S, M, L, XL, XXL, Única, N/A..." required autocomplete="off">
                            </div>

                            <div class="checkbox-wrapper">
                                <input type="checkbox" name="activo" id="activo" checked>
                                <label for="activo"><i class="fas fa-power-off"></i> Talla Activa</label>
                            </div>

                            <button type="submit" name="insert" class="btn btn-success">
                                <i class="fas fa-save"></i> Guardar Talla
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sección de la tabla (LISTAR) -->
            <div class="table-section">
                <div class="card-modern">
                    <div class="card-header">
                        <i class="fas fa-list"></i>
                        <h3>Tallas Registradas</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-wrapper">
                            <table class="data-table" id="tablaTallas">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Talla</th>
                                        <th>Estado</th>
                                        <th style="text-align: center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaBody">
                                    <?php
                                    try {
                                        $sql = "SELECT id_talla, nombre, estado FROM tallas ORDER BY 
                                                CASE 
                                                    WHEN nombre = 'XS' THEN 1
                                                    WHEN nombre = 'S' THEN 2
                                                    WHEN nombre = 'M' THEN 3
                                                    WHEN nombre = 'L' THEN 4
                                                    WHEN nombre = 'XL' THEN 5
                                                    WHEN nombre = 'XXL' THEN 6
                                                    ELSE 99
                                                END, nombre";
                                        $stmt = $pdo->query($sql);

                                        if ($stmt->rowCount() > 0) {
                                            while ($fila = $stmt->fetch()) {
                                                $id = $fila['id_talla'];
                                                $nombre = $fila['nombre'];
                                                $estado = $fila['estado'];
                                                $estado_text = $estado ? 'Activo' : 'Inactivo';
                                                $estado_class = $estado ? 'badge-success' : 'badge-danger';
                                                $estado_icon = $estado ? 'fa-check-circle' : 'fa-times-circle';
                                    ?>
                                                <tr id="fila-<?= $id ?>" data-estado="<?= $estado ? '1' : '0' ?>">
                                                    <td><?= $id ?></td>
                                                    <td>
                                                        <span class="talla-badge"><?= htmlspecialchars($nombre) ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?= $estado_class ?>" id="estado-badge-<?= $id ?>">
                                                            <i class="fas <?= $estado_icon ?>"></i>
                                                            <?= $estado_text ?>
                                                        </span>
                                                    </td>
                                                    <td style="text-align: center">
                                                        <div class="action-buttons">
                                                            <button class="action-btn edit"
                                                                onclick='abrirModalEditar(<?= $id ?>, 
                                                                     <?= json_encode($nombre) ?>, 
                                                                     <?= $estado ? 'true' : 'false' ?>)'>
                                                                <i class="fas fa-edit"></i> Editar
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                    <?php
                                            }
                                        } else {
                                            echo '<tr><td colspan="4" style="text-align: center; padding: 40px;">
                                                <i class="fas fa-ruler-combined" style="font-size: 48px; color: #ccc;"></i>
                                                <p style="margin-top: 10px;">No hay tallas registradas</p>
                                                <p style="font-size: 12px;">Haz clic en "Guardar Talla" para comenzar</p>
                                                </td></tr>';
                                        }
                                    } catch (PDOException $e) {
                                        echo '<tr><td colspan="4" style="color: red;">Error: ' . $e->getMessage() . 'NonNull Baselast';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE EDICIÓN -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal()">&times;</span>
            <h3>Editar Talla</h3>
            <form method="POST" action="" id="formEditar">
                <input type="hidden" name="id_original" id="id_original">

                <label for="nombre_update">NOMBRE DE LA TALLA *</label>
                <input type="text" name="nombre_update" id="nombre_update" required maxlength="10" placeholder="Ej: M, L, XL, Única">

                <div class="switch-container">
                    <label class="switch">
                        <input type="checkbox" name="activo_update" id="activo_update">
                        <span class="slider"></span>
                    </label>
                    <span class="switch-text">Talla Activa</span>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn-cancelar" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" name="actualizar" class="btn-actualizar">Actualizar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        const modal = document.getElementById('modalEditar');
        let initialFormData = null;

        <?php if (!empty($system_message)): ?>
            Swal.fire({
                icon: '<?= $system_message_type === 'success' ? 'success' : 'error' ?>',
                title: '<?= htmlspecialchars($system_message) ?>',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        <?php endif; ?>

        function getFormState() {
            const pairs = [];
            pairs.push(`id:${document.getElementById('id_original').value}`);
            pairs.push(`nombre:${document.getElementById('nombre_update').value.trim()}`);
            pairs.push(`activo:${document.getElementById('activo_update').checked ? '1' : '0'}`);
            return pairs.sort().join('|');
        }

        function hasChanges() {
            if (!initialFormData) return true;
            return getFormState() !== initialFormData;
        }

        function cerrarModal() {
            if (!hasChanges()) {
                modal.classList.remove('show');
                document.body.classList.remove('modal-open');
                initialFormData = null;
                return;
            }

            Swal.fire({
                title: '¿Seguro que quieres salir?',
                text: "Hay cambios sin guardar.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#37383b',
                cancelButtonColor: '#dc2626',
                confirmButtonText: 'Sí, salir',
                cancelButtonText: 'No, seguir'
            }).then((result) => {
                if (result.isConfirmed) {
                    modal.classList.remove('show');
                    document.body.classList.remove('modal-open');
                    initialFormData = null;
                }
            });
        }

        function abrirModalEditar(id, nombre, activo) {
            document.getElementById('id_original').value = id;
            document.getElementById('nombre_update').value = nombre;
            document.getElementById('activo_update').checked = activo === true || activo === 'true';

            modal.classList.add('show');
            document.body.classList.add('modal-open');
            initialFormData = getFormState();
        }

        document.getElementById('formEditar')?.addEventListener('submit', function(e) {
            if (!hasChanges()) {
                e.preventDefault();
                Swal.fire({
                    icon: 'info',
                    title: 'Sin cambios',
                    text: 'Debes realizar al menos un cambio para actualizar.',
                    confirmButtonColor: '#37383b'
                });
            }
        });

        window.onclick = function(event) {
            if (event.target === modal) {
                cerrarModal();
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modal.classList.contains('show')) {
                cerrarModal();
            }
        });
    </script>

</body>

</html>