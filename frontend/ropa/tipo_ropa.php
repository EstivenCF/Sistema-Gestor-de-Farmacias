<?php
// tipo_ropa.php - Gestión de Tipos de Ropa

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
$base_url = "menuprincipal.php?mod=tipo_ropa";

// ========================
// INSERT
// ========================
if (isset($_POST['insert'])) {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $activo = isset($_POST['activo']) ? true : false;

    if (empty($nombre)) {
        $_SESSION['system_message'] = 'Error: El nombre del tipo de ropa es obligatorio.';
        $_SESSION['system_message_type'] = 'error';
    } else {
        try {
            $sql = "INSERT INTO tipo_ropa (nombre, descripcion, estado) VALUES (:nombre, :descripcion, :estado)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':nombre', $nombre);
            $stmt->bindValue(':descripcion', $descripcion);
            $stmt->bindValue(':estado', $activo, PDO::PARAM_BOOL);
            $stmt->execute();

            $_SESSION['system_message'] = 'Tipo de ropa creado con éxito';
            $_SESSION['system_message_type'] = 'success';
        } catch (PDOException $e) {
            if ($e->getCode() == '23505') {
                $_SESSION['system_message'] = 'Error: Ya existe un tipo de ropa con ese nombre.';
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
    $descripcion_update = trim($_POST['descripcion_update']);
    $activo_update = isset($_POST['activo_update']) ? true : false;

    if (empty($nombre_update)) {
        $_SESSION['system_message'] = 'Error: El nombre del tipo de ropa es obligatorio.';
        $_SESSION['system_message_type'] = 'error';
    } else {
        try {
            $sql = "UPDATE tipo_ropa SET nombre = :nombre, descripcion = :descripcion, estado = :estado WHERE id_tipo = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':nombre', $nombre_update);
            $stmt->bindValue(':descripcion', $descripcion_update);
            $stmt->bindValue(':estado', $activo_update, PDO::PARAM_BOOL);
            $stmt->bindValue(':id', $id_original);
            $stmt->execute();

            $_SESSION['system_message'] = 'Tipo de ropa actualizado con éxito';
            $_SESSION['system_message_type'] = 'success';
        } catch (PDOException $e) {
            if ($e->getCode() == '23505') {
                $_SESSION['system_message'] = 'Error: Ya existe un tipo de ropa con ese nombre.';
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
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

    :root {
        --color-bg-light: #f9fafb;
        --color-card-bg: #ffffff;
        --color-text-dark: #222222;
        --color-text-secondary: #37383b;
        --color-primary: #8b5cf6;
        --color-primary-dark: #7c3aed;
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

    .tipo-container {
        max-width: 1400px;
        width: 100%;
        margin: 0 auto;
    }

    /* Header con color Morado */
    .header-card {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        border-radius: 20px;
        padding: 25px 30px;
        margin-bottom: 30px;
        color: white;
        box-shadow: 0 10px 30px rgba(139, 92, 246, 0.3);
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
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
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
        box-shadow: 0 5px 15px rgba(139, 92, 246, 0.3);
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
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
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

    /* Acciones en tabla */
    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .action-btn {
        padding: 8px 16px;
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
    }

    .action-btn.edit {
        background: var(--color-primary);
    }

    .action-btn.view {
        background: #0ea5e9;
    }

    .action-btn:hover {
        transform: translateY(-2px);
        filter: brightness(1.05);
    }

    /* Modal de Detalles - Amplio como medicamentos */
    .modal-detalle {
        position: fixed;
        inset: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(4px);
        z-index: 10000;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: 0.3s ease-out;
    }

    .modal-detalle.show {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }

    .modal-detalle .modal-content {
        background: #ffffff;
        border-radius: 20px;
        width: 550px;
        max-width: 90%;
        max-height: 85vh;
        overflow-y: auto;
        position: relative;
        box-shadow: 0 20px 35px rgba(0, 0, 0, 0.2);
        transform: scale(0.95);
        transition: transform 0.25s ease-out;
        padding: 0;
    }

    .modal-detalle.show .modal-content {
        transform: scale(1);
    }

    .modal-detalle .modal-header {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
        padding: 20px 25px;
        border-radius: 20px 20px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-detalle .modal-header h3 {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-detalle .modal-header .close-detalle {
        font-size: 28px;
        cursor: pointer;
        color: white;
        opacity: 0.8;
        transition: opacity 0.2s;
        line-height: 1;
    }

    .modal-detalle .modal-header .close-detalle:hover {
        opacity: 1;
    }

    .modal-detalle .modal-body {
        padding: 25px;
    }

    /* Grid de detalles - 2 columnas como medicamentos */
    .detalle-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    .detalle-item {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 15px;
        border-left: 4px solid var(--color-primary);
        transition: all 0.2s;
    }

    .detalle-item:hover {
        background: #f1f5f9;
        transform: translateX(2px);
    }

    .detalle-item label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }

    .detalle-item .detalle-valor {
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
        word-break: break-word;
    }

    .detalle-item .detalle-valor i {
        color: var(--color-primary);
        margin-right: 8px;
        width: 20px;
    }

    .detalle-full {
        grid-column: span 2;
    }

    /* Badge dentro del modal */
    .badge-modal {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 600;
        gap: 6px;
    }

    .badge-modal-active {
        background: #d4edda;
        color: #155724;
    }

    .badge-modal-inactive {
        background: #f8d7da;
        color: #721c24;
    }

    /* Productos asociados - tabla dentro del modal */
    .productos-asociados {
        margin-top: 20px;
    }

    .productos-asociados h4 {
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 2px solid #e2e8f0;
    }

    .productos-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    .productos-table th {
        text-align: left;
        padding: 8px;
        background: #f1f5f9;
        color: #475569;
        font-weight: 600;
    }

    .productos-table td {
        padding: 8px;
        border-bottom: 1px solid #e2e8f0;
    }

    .productos-table tr:hover {
        background: #f8fafc;
    }

    /* Modal de Edición */
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

    .modal .modal-content {
        background: #ffffff;
        border-radius: 16px;
        padding: 25px 30px;
        width: 480px;
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
        .modal, .modal-detalle {
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

    .modal .modal-content h3 {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 20px;
        color: #1e293b;
        text-align: center;
        padding-right: 20px;
    }

    .modal .modal-content label {
        display: block;
        margin-top: 12px;
        margin-bottom: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .modal .modal-content input[type="text"],
    .modal .modal-content textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background-color: #f8fafc;
        font-size: 0.85rem;
        transition: all 0.2s ease;
    }

    .modal .modal-content input:focus,
    .modal .modal-content textarea:focus {
        border-color: var(--color-primary);
        outline: none;
        box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.1);
        background-color: #fff;
    }

    .modal .modal-content textarea {
        resize: vertical;
        min-height: 80px;
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
        box-shadow: 0 6px 15px rgba(139, 92, 246, 0.3);
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

    /* Scrollbar personalizado */
    .modal-content::-webkit-scrollbar,
    .modal-detalle .modal-content::-webkit-scrollbar {
        width: 6px;
    }

    .modal-content::-webkit-scrollbar-track,
    .modal-detalle .modal-content::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .modal-content::-webkit-scrollbar-thumb,
    .modal-detalle .modal-content::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 10px;
    }

    .modal-content::-webkit-scrollbar-thumb:hover,
    .modal-detalle .modal-content::-webkit-scrollbar-thumb:hover {
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
        .modal .modal-content,
        .modal-detalle .modal-content {
            width: 95%;
            padding: 20px;
        }
        .detalle-grid {
            grid-template-columns: 1fr;
        }
        .action-btn {
            padding: 6px 12px;
            font-size: 11px;
        }
    }
</style>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tipos de Ropa - Farmacia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <div class="tipo-container">
        <!-- Header -->
        <div class="header-card">
            <h1>
                <i class="fas fa-tags"></i>
                Gestión de Tipos de Ropa
            </h1>
            <p>Administre las categorías de ropa (Uniformes Médicos, Maternidad y Bebé, Ortopedia, etc.)</p>
        </div>

        <!-- Estadísticas -->
        <?php
        try {
            $total_tipos = $pdo->query("SELECT COUNT(*) FROM tipo_ropa")->fetchColumn();
            $tipos_activos = $pdo->query("SELECT COUNT(*) FROM tipo_ropa WHERE estado = TRUE")->fetchColumn();
            $tipos_inactivos = $total_tipos - $tipos_activos;
            
            // Productos asociados por tipo
            $productos_por_tipo = [];
            $stmt = $pdo->query("
                SELECT t.id_tipo, 
                       COUNT(r.id_ropa) as total,
                       json_agg(json_build_object('id_producto', p.id_producto, 'nombre', p.nombre, 'precio', p.precio)) as productos
                FROM tipo_ropa t
                LEFT JOIN ropa_detalle r ON t.id_tipo = r.id_tipo
                LEFT JOIN productos p ON r.id_producto = p.id_producto
                GROUP BY t.id_tipo
            ");
            while ($row = $stmt->fetch()) {
                $productos_por_tipo[$row['id_tipo']] = [
                    'total' => $row['total'],
                    'productos' => json_decode($row['productos'], true) ?: []
                ];
            }
        } catch (PDOException $e) {
            $total_tipos = 0;
            $tipos_activos = 0;
            $tipos_inactivos = 0;
            $productos_por_tipo = [];
        }
        ?>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-tags"></i>
                <div class="number"><?= $total_tipos ?></div>
                <div class="label">Total Tipos</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <div class="number"><?= $tipos_activos ?></div>
                <div class="label">Tipos Activos</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-ban"></i>
                <div class="number"><?= $tipos_inactivos ?></div>
                <div class="label">Tipos Inactivos</div>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="content-grid">
            <!-- Sección del formulario (CREAR) -->
            <div class="form-section">
                <div class="card-modern">
                    <div class="card-header">
                        <i class="fas fa-plus-circle"></i>
                        <h3>Nuevo Tipo de Ropa</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="formCrear">
                            <div class="form-group">
                                <label><i class="fas fa-tag"></i> Nombre del Tipo *</label>
                                <input type="text" name="nombre" id="nombre" class="form-control-modern"
                                    placeholder="Ej: Uniformes Médicos, Maternidad y Bebé, Ortopedia..." required autocomplete="off">
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-align-left"></i> Descripción</label>
                                <textarea name="descripcion" id="descripcion" class="form-control-modern"
                                    placeholder="Descripción del tipo de ropa (opcional)"></textarea>
                            </div>

                            <div class="checkbox-wrapper">
                                <input type="checkbox" name="activo" id="activo" checked>
                                <label for="activo"><i class="fas fa-power-off"></i> Tipo Activo</label>
                            </div>

                            <button type="submit" name="insert" class="btn btn-success">
                                <i class="fas fa-save"></i> Guardar Tipo
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
                        <h3>Tipos de Ropa Registrados</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-wrapper">
                            <table class="data-table" id="tablaTipos">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Descripción</th>
                                        <th>Productos</th>
                                        <th>Estado</th>
                                        <th style="text-align: center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaBody">
                                    <?php
                                    try {
                                        $sql = "SELECT id_tipo, nombre, descripcion, estado FROM tipo_ropa ORDER BY id_tipo";
                                        $stmt = $pdo->query($sql);

                                        if ($stmt->rowCount() > 0) {
                                            while ($fila = $stmt->fetch()) {
                                                $id = $fila['id_tipo'];
                                                $nombre = $fila['nombre'];
                                                $descripcion = $fila['descripcion'] ?? '';
                                                $estado = $fila['estado'];
                                                $estado_text = $estado ? 'Activo' : 'Inactivo';
                                                $estado_class = $estado ? 'badge-success' : 'badge-danger';
                                                $estado_icon = $estado ? 'fa-check-circle' : 'fa-times-circle';
                                                $total_productos = $productos_por_tipo[$id]['total'] ?? 0;
                                                $descripcion_corta = strlen($descripcion) > 50 ? substr($descripcion, 0, 50) . '...' : $descripcion;
                                    ?>
                                                <tr id="fila-<?= $id ?>" data-estado="<?= $estado ? '1' : '0' ?>">
                                                    <td><?= $id ?></td>
                                                    <td><strong><?= htmlspecialchars($nombre) ?></strong></td>
                                                    <td><?= htmlspecialchars($descripcion_corta) ?: '—' ?></td>
                                                    <td>
                                                        <span class="badge" style="background: #e0f2fe; color: #0284c7;">
                                                            <i class="fas fa-tshirt"></i> <?= $total_productos ?> productos
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?= $estado_class ?>" id="estado-badge-<?= $id ?>">
                                                            <i class="fas <?= $estado_icon ?>"></i>
                                                            <?= $estado_text ?>
                                                        </span>
                                                    </td>
                                                    <td style="text-align: center">
                                                        <div class="action-buttons">
                                                            <button class="action-btn view" 
                                                                onclick='verDetalle(<?= $id ?>, 
                                                                    <?= json_encode($nombre) ?>, 
                                                                    <?= json_encode($descripcion) ?>, 
                                                                    <?= $total_productos ?>, 
                                                                    <?= $estado ? 'true' : 'false' ?>,
                                                                    <?= json_encode($productos_por_tipo[$id]['productos'] ?? []) ?>)'>
                                                                <i class="fas fa-eye"></i> Ver
                                                            </button>
                                                            <button class="action-btn edit"
                                                                onclick='abrirModalEditar(<?= $id ?>, 
                                                                    <?= json_encode($nombre) ?>, 
                                                                    <?= json_encode($descripcion) ?>, 
                                                                    <?= $estado ? 'true' : 'false' ?>)'>
                                                                <i class="fas fa-edit"></i> Editar
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                    <?php
                                            }
                                        } else {
                                            echo '<tr><td colspan="6" style="text-align: center; padding: 40px;">
                                                <i class="fas fa-tags" style="font-size: 48px; color: #ccc;"></i>
                                                <p style="margin-top: 10px;">No hay tipos de ropa registrados</p>
                                                <p style="font-size: 12px;">Haz clic en "Guardar Tipo" para comenzar</p>
                                                </td></tr>';
                                        }
                                    } catch (PDOException $e) {
                                        echo '<tr><td colspan="6" style="color: red;">Error: ' . $e->getMessage() . 'NonNull Baselast';
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

    <!-- MODAL DE DETALLES - AMPLIO COMO MEDICAMENTOS -->
    <div id="modalDetalle" class="modal-detalle">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-info-circle"></i>
                    Detalle del Tipo de Ropa
                </h3>
                <span class="close-detalle" onclick="cerrarDetalle()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="detalle-grid">
                    <div class="detalle-item">
                        <label><i class="fas fa-hashtag"></i> ID</label>
                        <div class="detalle-valor" id="detalle-id">-</div>
                    </div>
                    <div class="detalle-item">
                        <label><i class="fas fa-tag"></i> Nombre</label>
                        <div class="detalle-valor" id="detalle-nombre">-</div>
                    </div>
                    <div class="detalle-item detalle-full">
                        <label><i class="fas fa-align-left"></i> Descripción</label>
                        <div class="detalle-valor" id="detalle-descripcion">-</div>
                    </div>
                    <div class="detalle-item">
                        <label><i class="fas fa-tshirt"></i> Productos Asociados</label>
                        <div class="detalle-valor" id="detalle-productos">-</div>
                    </div>
                    <div class="detalle-item">
                        <label><i class="fas fa-power-off"></i> Estado</label>
                        <div class="detalle-valor" id="detalle-estado">-</div>
                    </div>
                    <div class="detalle-item">
                        <label><i class="fas fa-calendar-alt"></i> Fecha de Registro</label>
                        <div class="detalle-valor" id="detalle-fecha">-</div>
                    </div>
                </div>

                <!-- Tabla de productos asociados -->
                <div class="productos-asociados" id="productos-asociados-container" style="display: none;">
                    <h4><i class="fas fa-tshirt"></i> Productos de este tipo</h4>
                    <table class="productos-table" id="productos-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Producto</th>
                                <th>Precio</th>
                            </tr>
                        </thead>
                        <tbody id="productos-table-body">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE EDICIÓN -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal()">&times;</span>
            <h3>Editar Tipo de Ropa</h3>
            <form method="POST" action="" id="formEditar">
                <input type="hidden" name="id_original" id="id_original">

                <label for="nombre_update">NOMBRE *</label>
                <input type="text" name="nombre_update" id="nombre_update" required maxlength="100">

                <label for="descripcion_update">DESCRIPCIÓN</label>
                <textarea name="descripcion_update" id="descripcion_update" maxlength="500"></textarea>

                <div class="switch-container">
                    <label class="switch">
                        <input type="checkbox" name="activo_update" id="activo_update">
                        <span class="slider"></span>
                    </label>
                    <span class="switch-text">Tipo Activo</span>
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
        const modalDetalle = document.getElementById('modalDetalle');
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

        // ========================
        // FUNCIONES PARA EL MODAL DE DETALLES
        // ========================
        function verDetalle(id, nombre, descripcion, totalProductos, activo, productos) {
            document.getElementById('detalle-id').innerHTML = '<i class="fas fa-hashtag"></i> ' + id;
            document.getElementById('detalle-nombre').innerHTML = '<i class="fas fa-tag"></i> ' + nombre;
            document.getElementById('detalle-descripcion').innerHTML = '<i class="fas fa-align-left"></i> ' + (descripcion || '<span style="color: #94a3b8;">Sin descripción</span>');
            document.getElementById('detalle-productos').innerHTML = '<i class="fas fa-tshirt"></i> ' + totalProductos + ' producto(s) asociado(s)';
            
            const estadoText = activo ? 'Activo' : 'Inactivo';
            const estadoIcon = activo ? 'fa-check-circle' : 'fa-times-circle';
            const estadoColor = activo ? '#10b981' : '#ef4444';
            document.getElementById('detalle-estado').innerHTML = '<span class="badge-modal ' + (activo ? 'badge-modal-active' : 'badge-modal-inactive') + '"><i class="fas ' + estadoIcon + '"></i> ' + estadoText + '</span>';
            
            const fechaActual = new Date().toLocaleDateString('es-ES', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            document.getElementById('detalle-fecha').innerHTML = '<i class="fas fa-calendar-alt"></i> ' + fechaActual;

            // Mostrar productos asociados si hay
            const productosContainer = document.getElementById('productos-asociados-container');
            const productosTableBody = document.getElementById('productos-table-body');
            
            if (productos && productos.length > 0 && productos[0] !== null) {
                productosTableBody.innerHTML = '';
                productos.forEach(prod => {
                    if (prod && prod.id_producto) {
                        const row = productosTableBody.insertRow();
                        row.insertCell(0).innerHTML = prod.id_producto;
                        row.insertCell(1).innerHTML = '<strong>' + escapeHtml(prod.nombre) + '</strong>';
                        row.insertCell(2).innerHTML = 'RD$ ' + parseFloat(prod.precio).toFixed(2);
                    }
                });
                productosContainer.style.display = 'block';
            } else {
                productosContainer.style.display = 'none';
            }
            
            modalDetalle.classList.add('show');
            document.body.classList.add('modal-open');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function cerrarDetalle() {
            modalDetalle.classList.remove('show');
            document.body.classList.remove('modal-open');
        }

        // ========================
        // FUNCIONES PARA EL MODAL DE EDICIÓN
        // ========================
        function getFormState() {
            const pairs = [];
            pairs.push(`id:${document.getElementById('id_original').value}`);
            pairs.push(`nombre:${document.getElementById('nombre_update').value.trim()}`);
            pairs.push(`descripcion:${document.getElementById('descripcion_update').value.trim()}`);
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

        function abrirModalEditar(id, nombre, descripcion, activo) {
            document.getElementById('id_original').value = id;
            document.getElementById('nombre_update').value = nombre;
            document.getElementById('descripcion_update').value = descripcion || '';
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

        // Cerrar modales al hacer clic fuera
        window.onclick = function(event) {
            if (event.target === modal) {
                cerrarModal();
            }
            if (event.target === modalDetalle) {
                cerrarDetalle();
            }
        }

        // Cerrar con Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (modal.classList.contains('show')) {
                    cerrarModal();
                }
                if (modalDetalle.classList.contains('show')) {
                    cerrarDetalle();
                }
            }
        });
    </script>

</body>

</html>