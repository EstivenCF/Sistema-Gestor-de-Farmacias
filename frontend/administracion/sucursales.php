<?php
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

// Obtener empresas para el select
$empresas = [];
try {
    $stmt = $pdo->query("SELECT id_empresa, nombre FROM empresa ORDER BY nombre");
    $empresas = $stmt->fetchAll();
} catch (PDOException $e) {
    $empresas = [];
}

// Cambia la definición de $base_url por esta:
$base_url = "menuprincipal.php?mod=sucursales";

// ========================
// INSERT
// ========================
if (isset($_POST['insert'])) {
    $id_empresa = !empty($_POST['id_empresa']) ? intval($_POST['id_empresa']) : null;
    $nombre = trim($_POST['nombre']);
    $direccion = trim($_POST['direccion']);
    $telefono = trim($_POST['telefono']);
    $activo = isset($_POST['activo']) ? true : false;

    if (empty($nombre)) {
        $_SESSION['system_message'] = 'Error: El nombre de la sucursal es obligatorio.';
        $_SESSION['system_message_type'] = 'error';
    } else {
        try {
            $latitud = !empty($_POST['latitud']) ? (float)$_POST['latitud'] : null;
            $longitud = !empty($_POST['longitud']) ? (float)$_POST['longitud'] : null;

            $sql = "INSERT INTO sucursales (id_empresa, nombre, direccion, telefono, estado, latitud, longitud) 
                    VALUES (:id_empresa, :nombre, :direccion, :telefono, :estado, :latitud, :longitud)";
            $stmt = $pdo->prepare($sql);

            $stmt->bindValue(':id_empresa', $id_empresa, $id_empresa ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':nombre', $nombre);
            $stmt->bindValue(':direccion', $direccion);
            $stmt->bindValue(':telefono', $telefono);
            $stmt->bindValue(':estado', $activo, PDO::PARAM_BOOL);
            $stmt->bindValue(':latitud', $latitud, $latitud !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':longitud', $longitud, $longitud !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);

            $stmt->execute();

            $_SESSION['system_message'] = 'Sucursal creada con éxito';
            $_SESSION['system_message_type'] = 'success';
        } catch (PDOException $e) {
            if ($e->getCode() == '23505') {
                $_SESSION['system_message'] = 'Error: Ya existe una sucursal con ese nombre.';
            } else {
                $_SESSION['system_message'] = 'Error al crear la sucursal: ' . $e->getMessage();
            }
            $_SESSION['system_message_type'] = 'error';
        }
    }
    
    // Redirigir a la misma página (sucursales.php)
    echo "<script>window.location.href = '" . $base_url . "';</script>";
    exit();
}

// ========================
// UPDATE
// ========================
if (isset($_POST['actualizar'])) {
    $id_original = intval($_POST['id_original']);
    $id_empresa = !empty($_POST['empresa_update']) ? intval($_POST['empresa_update']) : null;
    $nombre_update = trim($_POST['nombre_update']);
    $direccion_update = trim($_POST['direccion_update']);
    $telefono_update = trim($_POST['telefono_update']);
    $activo_update = isset($_POST['activo_update']) ? true : false;

    if (empty($nombre_update)) {
        $_SESSION['system_message'] = 'Error: El nombre de la sucursal es obligatorio.';
        $_SESSION['system_message_type'] = 'error';
    } else {
        try {
            $latitud_update = !empty($_POST['latitud_update']) ? (float)$_POST['latitud_update'] : null;
            $longitud_update = !empty($_POST['longitud_update']) ? (float)$_POST['longitud_update'] : null;

            $sql = "UPDATE sucursales 
                    SET id_empresa = :id_empresa,
                        nombre = :nombre, 
                        direccion = :direccion, 
                        telefono = :telefono, 
                        estado = :estado,
                        latitud = :latitud,
                        longitud = :longitud
                    WHERE id_sucursal = :id";

            $stmt = $pdo->prepare($sql);

            $stmt->bindValue(':id_empresa', $id_empresa, $id_empresa ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':nombre', $nombre_update);
            $stmt->bindValue(':direccion', $direccion_update);
            $stmt->bindValue(':telefono', $telefono_update);
            $stmt->bindValue(':estado', $activo_update, PDO::PARAM_BOOL);
            $stmt->bindValue(':latitud', $latitud_update, $latitud_update !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':longitud', $longitud_update, $longitud_update !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':id', $id_original);

            $stmt->execute();

            $_SESSION['system_message'] = 'Sucursal actualizada con éxito';
            $_SESSION['system_message_type'] = 'success';
        } catch (PDOException $e) {
            if ($e->getCode() == '23505') {
                $_SESSION['system_message'] = 'Error: Ya existe una sucursal con ese nombre.';
            } else {
                $_SESSION['system_message'] = 'Error al actualizar: ' . $e->getMessage();
            }
            $_SESSION['system_message_type'] = 'error';
        }
    }
    
    // Redirigir a la misma página (sucursales.php)
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
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

    :root {
        --color-bg-light: #f9fafb;
        --color-card-bg: #ffffff;
        --color-text-dark: #222222;
        --color-text-secondary: #37383b;
        --color-primary: #1067b9;
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

    /* Contenedor Principal */
    .sucursal-container {
        max-width: 1400px;
        width: 100%;
        margin: 0 auto;
    }

    /* Header */
    .header-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        padding: 25px 30px;
        margin-bottom: 30px;
        color: white;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
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
        color: var(--color-success);
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
        border-color: var(--color-success);
        background: white;
        box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
    }

    select.form-control-modern {
        cursor: pointer;
    }

    textarea.form-control-modern {
        resize: vertical;
        min-height: 80px;
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
        accent-color: var(--color-success);
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
        background: linear-gradient(135deg, var(--color-success), #218838);
        color: white;
        width: 100%;
    }

    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
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
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

    /* Acciones en tabla - Solo botón Editar */
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
        background: var(--color-success);
    }

    .action-btn:hover {
        transform: translateY(-2px);
        filter: brightness(1.05);
    }

    /* ========== MODAL ========== */
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

    /* El modal del mapa es más alto que los demás (buscador + resultados +
       mapa + botones), así que el título y los botones de acción se quedan
       fijos arriba/abajo mientras el resto hace scroll adentro — así nunca
       se pierde de vista la X para cerrar ni el botón de confirmar. */
    #modalMapaSucursal .modal-content {
        display: flex;
        flex-direction: column;
        max-height: 90vh;
        padding: 20px 24px;
    }
    #modalMapaSucursal .mapa-suc-header {
        position: sticky;
        top: 0;
        background: #fff;
        z-index: 2;
        padding-bottom: .6rem;
        margin-bottom: .6rem;
        border-bottom: 1px solid #eee;
    }
    #modalMapaSucursal .mapa-suc-footer {
        position: sticky;
        bottom: 0;
        background: #fff;
        z-index: 2;
        padding-top: .75rem;
        margin-top: .5rem;
        border-top: 1px solid #eee;
    }
    @media (max-height: 700px) {
        #mapaSucLeaflet { height: 220px !important; }
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

    html,
    body {
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

    .modal-content input[type="text"],
    .modal-content textarea,
    .modal-content select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background-color: #f8fafc;
        font-size: 0.85rem;
        transition: all 0.2s ease;
    }

    .modal-content input:focus,
    .modal-content textarea:focus,
    .modal-content select:focus {
        border-color: #94a3b8;
        outline: none;
        box-shadow: 0 0 0 2px rgba(148, 163, 184, 0.1);
        background-color: #fff;
    }

    .modal-content textarea {
        resize: none;
        min-height: 60px;
    }

    .modal-content .checkbox-container {
        display: flex;
        align-items: center;
        margin-top: 15px;
        justify-content: flex-start;
    }

    .modal-content .checkbox-container input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
        accent-color: #28a745;
        margin-right: 8px;
    }

    .modal-content .checkbox-label {
        margin-top: 0 !important;
        text-transform: none;
        font-size: 0.8rem;
        color: #334155;
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

    input:checked+.slider {
        background: linear-gradient(135deg, #28a745, #218838);
    }

    input:checked+.slider:before {
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
        background: linear-gradient(135deg, #28a745, #218838);
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
        box-shadow: 0 6px 15px rgba(40, 167, 69, 0.3);
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
        color: var(--color-success);
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

    /* Responsive */
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
    }
</style>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Sucursales - Farmacia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>

<body>

    <div class="sucursal-container">
        <!-- Header -->
        <div class="header-card">
            <h1>
                <i class="fas fa-store"></i>
                Gestión de Sucursales
            </h1>
            <p>Administre las sucursales de la empresa, sus datos de contacto y estado operativo</p>
        </div>

        <!-- Estadísticas -->
        <?php
        try {
            $total_sucursales = $pdo->query("SELECT COUNT(*) FROM sucursales")->fetchColumn();
            $sucursales_activas = $pdo->query("SELECT COUNT(*) FROM sucursales WHERE estado = TRUE")->fetchColumn();
            $sucursales_inactivas = $total_sucursales - $sucursales_activas;
        } catch (PDOException $e) {
            $total_sucursales = 0;
            $sucursales_activas = 0;
            $sucursales_inactivas = 0;
        }
        ?>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-store"></i>
                <div class="number"><?= $total_sucursales ?></div>
                <div class="label">Total Sucursales</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <div class="number"><?= $sucursales_activas ?></div>
                <div class="label">Sucursales Activas</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-ban"></i>
                <div class="number"><?= $sucursales_inactivas ?></div>
                <div class="label">Sucursales Inactivas</div>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="content-grid">
            <!-- Sección del formulario (CREAR) -->
            <div class="form-section">
                <div class="card-modern">
                    <div class="card-header">
                        <i class="fas fa-plus-circle"></i>
                        <h3>Nueva Sucursal</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="formCrear">
                            <div class="form-group">
                                <label><i class="fas fa-building"></i> Empresa</label>
                                <select name="id_empresa" class="form-control-modern">
                                    <option value="">-- Seleccionar Empresa --</option>
                                    <?php foreach ($empresas as $empresa): ?>
                                        <option value="<?= $empresa['id_empresa'] ?>">
                                            <?= htmlspecialchars($empresa['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-tag"></i> Nombre de la Sucursal *</label>
                                <input type="text" name="nombre" id="nombre" class="form-control-modern"
                                    placeholder="Ej: Sucursal Principal, Sucursal Villa Olga" required autocomplete="off">
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-location-dot"></i> Dirección</label>
                                <textarea name="direccion" id="direccion" class="form-control-modern"
                                    placeholder="Dirección completa de la sucursal"></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fas fa-map-pin"></i> Latitud (opcional)</label>
                                        <input type="text" name="latitud" id="latitud" class="form-control-modern" placeholder="Ej: 19.4517">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fas fa-map-pin"></i> Longitud (opcional)</label>
                                        <input type="text" name="longitud" id="longitud" class="form-control-modern" placeholder="Ej: -70.6970">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="abrirMapaParaSucursal('crear')">
                                    <i class="fas fa-map-marked-alt"></i> Ubicar en mapa
                                </button>
                                <span class="small text-muted ms-2" id="estadoUbicacionCrear">Sin ubicar</span>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Teléfono</label>
                                <input type="text" name="telefono" id="telefono" class="form-control-modern"
                                    placeholder="Ej: 809-555-0001">
                            </div>

                            <div class="checkbox-wrapper">
                                <input type="checkbox" name="activo" id="activo" checked>
                                <label for="activo"><i class="fas fa-power-off"></i> Sucursal Activa</label>
                            </div>

                            <button type="submit" name="insert" class="btn btn-success">
                                <i class="fas fa-save"></i> Guardar Sucursal
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
                        <h3>Sucursales Registradas</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-wrapper">
                            <table class="data-table" id="tablaSucursales">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Empresa</th>
                                        <th>Nombre</th>
                                        <th>Dirección</th>
                                        <th>Teléfono</th>
                                        <th>Estado</th>
                                        <th style="text-align: center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaBody">
                                    <?php
                                    try {
                                        $sql = "SELECT s.id_sucursal, s.nombre, s.direccion, s.telefono, s.estado,
                                                   s.latitud, s.longitud,
                                                   e.nombre as empresa_nombre, e.id_empresa
                                            FROM sucursales s
                                            LEFT JOIN empresa e ON s.id_empresa = e.id_empresa
                                            ORDER BY s.id_sucursal";
                                        $stmt = $pdo->query($sql);

                                        if ($stmt->rowCount() > 0) {
                                            while ($fila = $stmt->fetch()) {
                                                $id = $fila['id_sucursal'];
                                                $empresa_nombre = $fila['empresa_nombre'] ?? 'Sin empresa';
                                                $nombre = $fila['nombre'];
                                                $direccion = $fila['direccion'] ?? '';
                                                $telefono = $fila['telefono'] ?? '';
                                                $estado = $fila['estado'];
                                                $estado_text = $estado ? 'Activo' : 'Inactivo';
                                                $estado_class = $estado ? 'badge-success' : 'badge-danger';
                                                $estado_icon = $estado ? 'fa-check-circle' : 'fa-times-circle';
                                    ?>
                                                <tr id="fila-<?= $id ?>" data-estado="<?= $estado ? '1' : '0' ?>">
                                                    <td><?= $id ?></td>
                                                    <td><?= htmlspecialchars($empresa_nombre) ?></td>
                                                    <td><strong><?= htmlspecialchars($nombre) ?></strong></td>
                                                    <td><?= htmlspecialchars($direccion) ?></td>
                                                    <td><?= htmlspecialchars($telefono) ?></td>
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
                                                                     <?= json_encode($direccion) ?>, 
                                                                     <?= json_encode($telefono) ?>, 
                                                                     <?= $estado ? 'true' : 'false' ?>,
                                                                     <?= $fila['id_empresa'] ?? 'null' ?>,
                                                                     <?= $fila['latitud'] ?? 'null' ?>,
                                                                     <?= $fila['longitud'] ?? 'null' ?>)'>
                                                                <i class="fas fa-edit"></i> Editar
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                    <?php
                                            }
                                        } else {
                                            echo '<tr><td colspan="7" style="text-align: center; padding: 40px;">
                                                <i class="fas fa-store-slash" style="font-size: 48px; color: #ccc;"></i>
                                                <p style="margin-top: 10px;">No hay sucursales registradas</p>
                                              </td></tr>';
                                        }
                                    } catch (PDOException $e) {
                                        echo '<tr><td colspan="7" style="color: red;">Error: ' . $e->getMessage() . 'NonNull Baselast';
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
            <h3>Editar Sucursal</h3>
            <form method="POST" action="" id="formEditar">
                <input type="hidden" name="id_original" id="id_original">

                <label for="empresa_update">EMPRESA</label>
                <select name="empresa_update" id="empresa_update">
                    <option value="">-- Seleccionar Empresa --</option>
                    <?php foreach ($empresas as $empresa): ?>
                        <option value="<?= $empresa['id_empresa'] ?>">
                            <?= htmlspecialchars($empresa['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="nombre_update">NOMBRE *</label>
                <input type="text" name="nombre_update" id="nombre_update" required maxlength="150">

                <label for="direccion_update">DIRECCIÓN</label>
                <textarea name="direccion_update" id="direccion_update" maxlength="200"></textarea>

                <label for="latitud_update">LATITUD (opcional)</label>
                <input type="text" name="latitud_update" id="latitud_update" placeholder="Ej: 19.4517">

                <label for="longitud_update">LONGITUD (opcional)</label>
                <input type="text" name="longitud_update" id="longitud_update" placeholder="Ej: -70.6970">

                <div class="mb-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="abrirMapaParaSucursal('editar')">
                        <i class="fas fa-map-marked-alt"></i> Ubicar en mapa
                    </button>
                    <span class="small text-muted ms-2" id="estadoUbicacionEditar">Sin ubicar</span>
                </div>

                <label for="telefono_update">TELÉFONO</label>
                <input type="text" name="telefono_update" id="telefono_update" maxlength="30">

                <div class="switch-container">
                    <label class="switch">
                        <input type="checkbox" name="activo_update" id="activo_update">
                        <span class="slider"></span>
                    </label>
                    <span class="switch-text">Sucursal Activa</span>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn-cancelar" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" name="actualizar" class="btn-actualizar">Actualizar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: ubicar sucursal en el mapa (OpenStreetMap, gratis) -->
    <div class="modal" id="modalMapaSucursal" style="z-index:10000;">
      <div class="modal-content" style="width:700px;max-width:95%;">
          <div class="d-flex justify-content-between align-items-center mapa-suc-header">
            <h6 class="mb-0"><i class="fas fa-map-marked-alt"></i> Ubicar sucursal en el mapa</h6>
            <span style="cursor:pointer;font-size:1.3rem;" onclick="cerrarModalMapaSuc()">&times;</span>
          </div>
          <div class="input-group mb-2" style="display:flex;gap:.4rem;">
              <input type="text" class="form-control-modern" id="mapaSucBuscarInput" placeholder="Busca la dirección (ej: Av. Independencia, Santiago)" style="flex:1;">
              <button class="btn-actualizar" type="button" onclick="buscarEnMapaSucursal()" style="white-space:nowrap;">
                <i class="fas fa-search"></i> Buscar
              </button>
          </div>
          <div id="mapaSucResultados" style="max-height:140px; overflow-y:auto;margin-bottom:.5rem;"></div>
          <p class="text-muted small mb-2">O haz clic directamente en el mapa para marcar el punto exacto.</p>
          <div id="mapaSucLeaflet" style="height:320px; border-radius:10px;"></div>
          <p class="small mt-2 mb-3" id="mapaSucCoordsTexto">Sin ubicación seleccionada todavía.</p>
          <div class="modal-buttons mapa-suc-footer">
            <button type="button" class="btn-cancelar" onclick="cerrarModalMapaSuc()">Cancelar</button>
            <button type="button" class="btn-actualizar" onclick="confirmarUbicacionMapaSucursal()">
              <i class="fas fa-check"></i> Usar esta ubicación
            </button>
          </div>
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
            pairs.push(`empresa:${document.getElementById('empresa_update').value}`);
            pairs.push(`nombre:${document.getElementById('nombre_update').value.trim()}`);
            pairs.push(`direccion:${document.getElementById('direccion_update').value.trim()}`);
            pairs.push(`telefono:${document.getElementById('telefono_update').value.trim()}`);
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

        function abrirModalEditar(id, nombre, direccion, telefono, activo, idEmpresa, latitud, longitud) {
            document.getElementById('id_original').value = id;
            document.getElementById('empresa_update').value = idEmpresa || '';
            document.getElementById('nombre_update').value = nombre;
            document.getElementById('direccion_update').value = direccion || '';
            document.getElementById('latitud_update').value = latitud || '';
            document.getElementById('longitud_update').value = longitud || '';
            document.getElementById('telefono_update').value = telefono || '';
            document.getElementById('activo_update').checked = activo === true || activo === 'true';

            const estadoEditar = document.getElementById('estadoUbicacionEditar');
            const tieneCoordenadas = latitud !== null && latitud !== undefined && latitud !== '' &&
                                       longitud !== null && longitud !== undefined && longitud !== '';
            estadoEditar.textContent = tieneCoordenadas ? '✓ Ubicada en el mapa' : 'Sin ubicar';
            estadoEditar.style.color = tieneCoordenadas ? '#198754' : '';

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

        // ══════════════ MAPA (OpenStreetMap + Nominatim, gratis) ══════════════

        let mapaSucLeaflet, marcadorMapaSuc, modoMapaSucursal = 'crear';
        let coordsSeleccionadasSuc = null;
        let resultadosBusquedaMapaSuc = [];
        const modalMapaSucEl = document.getElementById('modalMapaSucursal');

        document.addEventListener('DOMContentLoaded', () => {
            const inputBuscar = document.getElementById('mapaSucBuscarInput');
            if (inputBuscar) {
                let timeoutBusq;
                inputBuscar.addEventListener('keyup', (e) => {
                    if (e.key === 'Enter') { buscarEnMapaSucursal(); return; }
                    clearTimeout(timeoutBusq);
                    timeoutBusq = setTimeout(buscarEnMapaSucursal, 700);
                });
            }
        });

        function cerrarModalMapaSuc() {
            modalMapaSucEl.classList.remove('show');
        }

        function abrirMapaParaSucursal(modo) {
            modoMapaSucursal = modo;
            coordsSeleccionadasSuc = null;
            document.getElementById('mapaSucBuscarInput').value = '';
            document.getElementById('mapaSucResultados').innerHTML = '';
            document.getElementById('mapaSucCoordsTexto').textContent = 'Sin ubicación seleccionada todavía.';

            modalMapaSucEl.classList.add('show');

            // Esperar un instante a que el modal sea visible antes de iniciar
            // Leaflet (necesita medir el tamaño real del contenedor).
            setTimeout(() => {
                const idLat = modo === 'crear' ? 'latitud' : 'latitud_update';
                const idLng = modo === 'crear' ? 'longitud' : 'longitud_update';
                const latActual = parseFloat(document.getElementById(idLat).value);
                const lngActual = parseFloat(document.getElementById(idLng).value);
                const tieneUbicacionPrevia = !isNaN(latActual) && !isNaN(lngActual);
                const centroInicial = tieneUbicacionPrevia ? [latActual, lngActual] : [19.4517, -70.6970]; // Santiago, RD
                const zoomInicial = tieneUbicacionPrevia ? 16 : 13;

                if (mapaSucLeaflet) { mapaSucLeaflet.remove(); mapaSucLeaflet = null; }
                mapaSucLeaflet = L.map('mapaSucLeaflet').setView(centroInicial, zoomInicial);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                    maxZoom: 19
                }).addTo(mapaSucLeaflet);

                if (tieneUbicacionPrevia) {
                    colocarMarcadorSuc(latActual, lngActual);
                }

                mapaSucLeaflet.on('click', function(e) {
                    colocarMarcadorSuc(e.latlng.lat, e.latlng.lng);
                    geocodificarInversoSuc(e.latlng.lat, e.latlng.lng);
                });
            }, 150);
        }

        function colocarMarcadorSuc(lat, lng) {
            if (marcadorMapaSuc) mapaSucLeaflet.removeLayer(marcadorMapaSuc);
            marcadorMapaSuc = L.marker([lat, lng]).addTo(mapaSucLeaflet);
            coordsSeleccionadasSuc = { lat, lng, direccion: null };
            document.getElementById('mapaSucCoordsTexto').innerHTML =
                `<span style="color:#198754;"><strong>✓ Ubicación marcada:</strong> ${lat.toFixed(6)}, ${lng.toFixed(6)}</span>`;
        }

        function extraerDireccionCompletaSuc(addr, displayName) {
            if (!addr) return displayName || '';
            return [addr.house_number, addr.road].filter(Boolean).join(' ') || displayName || '';
        }

        function geocodificarInversoSuc(lat, lng) {
            const detalle = document.getElementById('mapaSucCoordsTexto');
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`)
                .then(r => r.json())
                .then(data => {
                    coordsSeleccionadasSuc.direccion = extraerDireccionCompletaSuc(data.address, data.display_name);
                    detalle.innerHTML =
                        `<span style="color:#198754;"><strong>✓ Ubicación marcada:</strong> ${lat.toFixed(6)}, ${lng.toFixed(6)}</span><br>
                         <span class="text-muted">${data.display_name || ''}</span>`;
                })
                .catch(() => { /* si falla, igual queda la coordenada marcada */ });
        }

        function buscarEnMapaSucursal() {
            const query = document.getElementById('mapaSucBuscarInput').value.trim();
            const resultados = document.getElementById('mapaSucResultados');
            if (query.length < 3) { resultados.innerHTML = ''; return; }

            resultados.innerHTML = '<div class="small text-muted p-2">Buscando...</div>';

            fetch(`https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&q=${encodeURIComponent(query)}&countrycodes=do&limit=5`)
                .then(r => r.json())
                .then(data => {
                    resultadosBusquedaMapaSuc = data;
                    if (!data.length) {
                        resultados.innerHTML = '<div class="small text-muted p-2">Sin resultados. Prueba con otro texto o marca el punto directo en el mapa.</div>';
                        return;
                    }
                    resultados.innerHTML = data.map((item, i) => `
                        <button type="button" onclick='seleccionarResultadoMapaSucursal(${i})'
                            style="display:block;width:100%;text-align:left;padding:.5rem .7rem;margin-bottom:2px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;font-size:.82rem;cursor:pointer;">
                            ${item.display_name}
                        </button>
                    `).join('');
                })
                .catch(() => {
                    resultados.innerHTML = '<div class="small text-danger p-2">Error al buscar. Intenta de nuevo o marca el punto directo en el mapa.</div>';
                });
        }

        function seleccionarResultadoMapaSucursal(indice) {
            const item = resultadosBusquedaMapaSuc[indice];
            if (!item) return;
            const lat = parseFloat(item.lat), lng = parseFloat(item.lon);
            mapaSucLeaflet.setView([lat, lng], 16);
            colocarMarcadorSuc(lat, lng);
            coordsSeleccionadasSuc.direccion = extraerDireccionCompletaSuc(item.address, item.display_name);
            document.getElementById('mapaSucCoordsTexto').innerHTML =
                `<span style="color:#198754;"><strong>✓ Ubicación marcada:</strong> ${lat.toFixed(6)}, ${lng.toFixed(6)}</span><br>
                 <span class="text-muted">${item.display_name}</span>`;
        }

        function confirmarUbicacionMapaSucursal() {
            if (!coordsSeleccionadasSuc) {
                Swal.fire('Falta ubicar', 'Busca la dirección o haz clic en el mapa para marcar el punto exacto.', 'warning');
                return;
            }
            const idLat = modoMapaSucursal === 'crear' ? 'latitud' : 'latitud_update';
            const idLng = modoMapaSucursal === 'crear' ? 'longitud' : 'longitud_update';
            const idDireccion = modoMapaSucursal === 'crear' ? 'direccion' : 'direccion_update';
            const idEstado = modoMapaSucursal === 'crear' ? 'estadoUbicacionCrear' : 'estadoUbicacionEditar';

            document.getElementById(idLat).value = coordsSeleccionadasSuc.lat;
            document.getElementById(idLng).value = coordsSeleccionadasSuc.lng;
            if (coordsSeleccionadasSuc.direccion) {
                document.getElementById(idDireccion).value = coordsSeleccionadasSuc.direccion;
            }
            const estadoTexto = document.getElementById(idEstado);
            estadoTexto.textContent = '✓ Ubicada en el mapa';
            estadoTexto.style.color = '#198754';

            cerrarModalMapaSuc();
        }
    </script>

</body>

</html>