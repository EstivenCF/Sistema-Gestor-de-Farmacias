<?php
// Iniciar sesión solo si no está activa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include(__DIR__ . "/../../backend/conexion.php");

error_reporting(0);
ini_set('display_errors', 0);

// ========================
// PROCESAR FORMULARIO
// ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id_empresa = $_POST['id_empresa'] ?? null;
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : null;
    $rnc = isset($_POST['rnc']) ? trim($_POST['rnc']) : null;
    $direccion = isset($_POST['direccion']) ? trim($_POST['direccion']) : null;

    // IMAGEN (logo_url)
    $url_default = "/sistema-gestor-de-farmacias/assets/img/empresa/default.png";
    $imagen_actual = $_POST['imagen_actual'] ?? null;

    // Si es crear y no hay imagen nueva, usamos el default
    // Si es editar y no hay imagen nueva, mantenemos la actual
    $imagen_final = ($accion === 'crear') ? $url_default : $imagen_actual;

    if (!empty($_FILES['imagen']['name']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $dir = __DIR__ . "/../../assets/img/empresa/";
        $url_base = "/sistema-gestor-de-farmacias/assets/img/empresa/";

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $nombre_img = uniqid() . "_" . time() . "." . $extension;
        $ruta = $dir . $nombre_img;

        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta)) {
            $imagen_final = $url_base . $nombre_img;
        }
    }

    $mensaje_error = '';
    $exito = false;

    try {
        if ($accion === 'crear') {
            // Verificar si el RNC ya existe (si se proporcionó)
            if (!empty($rnc)) {
                $checkRnc = $conexion->prepare("SELECT COUNT(*) FROM empresa WHERE rnc = ?");
                $checkRnc->execute([$rnc]);
                if ($checkRnc->fetchColumn() > 0) {
                    $mensaje_error = 'Ya existe una empresa con ese RNC.';
                }
            }

            if (empty($mensaje_error)) {
                $sql = "INSERT INTO empresa (nombre, rnc, direccion, logo_url, fecha_registro) VALUES (?, ?, ?, ?, NOW())";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([$nombre, $rnc ?: null, $direccion ?: null, $imagen_final]);
                $exito = true;
            }
        } elseif ($accion === 'editar') {
            $sql = "UPDATE empresa SET nombre=?, rnc=?, direccion=?, logo_url=? WHERE id_empresa=?";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([$nombre, $rnc ?: null, $direccion ?: null, $imagen_final, $id_empresa]);
            $exito = true;
        } elseif ($accion === 'eliminar') {
            // Verificar si tiene sucursales asociadas
            $check = $conexion->prepare("SELECT COUNT(*) FROM sucursales WHERE id_empresa = ?");
            $check->execute([$id_empresa]);
            $count = $check->fetchColumn();

            if ($count > 0) {
                $mensaje_error = "No se puede eliminar la empresa porque tiene $count sucursal(es) asociada(s).";
            } else {
                $sql = "DELETE FROM empresa WHERE id_empresa = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([$id_empresa]);
                $exito = true;
            }
        } else {
            $mensaje_error = 'Acción no válida.';
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23505) {
            $mensaje_error = 'Ya existe una empresa con ese RNC.';
        } else {
            $mensaje_error = 'Error técnico: ' . $e->getMessage();
        }
    }

    // Devolver respuesta JSON para SweetAlert
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'success' => $exito,
        'message' => $exito ? 'Operación realizada con éxito.' : $mensaje_error
    ]);

    exit;
}

// ========================
// OBTENER EMPRESAS
// ========================
$query = $conexion->query("
    SELECT e.*, 
           (SELECT COUNT(*) FROM sucursales WHERE id_empresa = e.id_empresa) as total_sucursales
    FROM empresa e
    ORDER BY e.id_empresa ASC
");
$empresas = $query->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total_empresas = count($empresas);
$total_con_rnc = 0;
$total_con_sucursales = 0;
foreach ($empresas as $e) {
    if (!empty($e['rnc'])) $total_con_rnc++;
    if ($e['total_sucursales'] > 0) $total_con_sucursales++;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Empresas</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0,1" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            padding: 24px;
        }

        .container-fluid {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .header-title h2 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #1e293b, #2d3a4e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-title .material-symbols-rounded {
            font-size: 32px;
            background: linear-gradient(135deg, #1067b9, #28a745);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-title p {
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
        }

        /* Botones */
        .btn-group {
            display: flex;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            font-family: 'Poppins', sans-serif;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }

        .btn-outline-consultar {
            background: transparent;
            border: 2px solid #1067b9;
            color: #1067b9;
        }

        .btn-outline-consultar:hover {
            background: #1067b9;
            color: white;
            transform: translateY(-2px);
        }

        /* Estadísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea20, #764ba220);
        }

        .stat-icon .material-symbols-rounded {
            font-size: 32px;
            color: #1067b9;
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            font-family: 'Poppins', sans-serif;
        }

        .stat-info p {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }

        /* Grid de Empresas (Cards) */
        .empresas-wrapper {
            background: white;
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .empresas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        /* Card de Empresa */
        .empresa-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .empresa-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 30px -12px rgba(0, 0, 0, 0.15);
            border-color: transparent;
        }

        .card-imagen {
            width: 100%;
            height: 250px;
            background-color: #e5e7eb;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .card-imagen img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .empresa-card:hover .card-imagen img {
            transform: scale(1.05);
        }

        .card-cuerpo {
            padding: 15px;
            flex-grow: 1;
            text-align: center;
            background-color: white;
        }

        .card-fecha {
            display: block;
            font-size: 0.8rem;
            font-weight: 500;
            color: #052996;
            background: #e8f5e9;
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 12px;
        }

        .nombre-empresa {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #222222;
        }

        .card-descripcion {
            font-size: 0.8rem;
            color: #37383b;
            line-height: 1.3;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            margin-top: 5px;
        }

        .card-descripcion .material-symbols-rounded {
            font-size: 16px;
        }

        .card-metricas {
            display: flex;
            width: 100%;
            height: 55px;
            border-top: 1px solid #e5e7eb;
            border-radius: 0 0 15px 15px;
            overflow: hidden;
        }

        .metrica {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            font-weight: 600;
            text-align: center;
            padding: 5px;
            transition: background-color 0.3s;
        }

        .metrica:not(:last-child) {
            border-right: 1px solid rgba(255, 255, 255, 0.2);
        }

        .metrica-valor {
            font-size: 0.95rem;
            line-height: 1.1;
        }

        .metrica-etiqueta {
            font-size: 0.6rem;
            opacity: 0.9;
        }

        .metrica-sucursales {
            background-color: #059669;
        }

        .metrica-rnc-success {
            background-color: #052996;
        }

        .metrica-rnc-danger {
            background-color: #ef4444;
        }

        .metrica-registro {
            background-color: #1067b9;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
        }

        .modal-contenido {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            max-height: 85vh;
            padding: 20px;
            overflow-y: auto;
            position: relative;
            animation: modalFadeIn 0.3s ease;
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .cerrar {
            position: absolute;
            right: 20px;
            top: 16px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #94a3b8;
            transition: color 0.2s;
            z-index: 10;
        }

        .cerrar:hover {
            color: #dc2626;
        }

        .modal-contenido h2 {
            font-size: 24px;
            font-weight: 700;
            padding: 24px 28px 16px;
            color: #1e293b;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 20px;
            font-family: 'Poppins', sans-serif;
        }

        .formulario-gestion {
            padding: 24px 32px 32px;
        }

        .grid-inputs {
            display: flex;
            flex-direction: column;
            gap: 18px;
            margin-bottom: 24px;
        }

        .grid-inputs input[type="file"] {
            padding: 10px;
            background: #f8fafc;
            border: 2px dashed #e2e8f0;
            cursor: pointer;
        }

        .grid-inputs textarea {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s;
            background: #f8fafc;
        }

        .grid-inputs label {
            font-weight: 600;
            font-size: 13px;
            color: #334155;
            margin-bottom: -8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'Poppins', sans-serif;
        }

        .grid-inputs input,
        .grid-inputs select {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 50px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s;
            background: #f8fafc;
        }

        .grid-inputs input:focus,
        .grid-inputs select:focus,
        .grid-inputs textarea:focus {
            outline: none;
            border-color: #28a745;
            background: white;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }

        .grid-inputs textarea {
            resize: vertical;
            min-height: 80px;
        }

        .preview-imagen {
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .preview-imagen img {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            object-fit: cover;
            background: #f0f0f0;
            border: 2px solid #e2e8f0;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }

        .btn-guardar {
            flex: 1;
            padding: 14px;
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
            border: none;
            border-radius: 40px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        .btn-guardar:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
        }

        .btn-eliminar-modal {
            flex: 1;
            padding: 14px;
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            border: none;
            border-radius: 40px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        .btn-eliminar-modal:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 38, 38, 0.3);
        }

        .no-empresas {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px;
            color: #94a3b8;
        }

        .no-empresas .material-symbols-rounded {
            font-size: 64px;
            margin-bottom: 16px;
        }

        @media (max-width: 768px) {
            body {
                padding: 16px;
            }

            .empresas-grid {
                grid-template-columns: 1fr;
            }

            .header-section {
                flex-direction: column;
            }

            .modal-contenido {
                width: 95%;
            }
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="header-section">
            <div class="header-title">
                <h2>
                    <span class="material-symbols-rounded">apartment</span>
                    Gestión de Empresa
                </h2>
                <p>Administra la empresa del sistema, sus datos fiscales e información corporativa</p>
            </div>
            <?php if ($total_empresas == 0): ?>
                <div class="btn-group">
                    <button type="button" class="btn btn-success" onclick="abrirModalCrear()">
                        <span class="material-symbols-rounded">add</span>
                        Nueva Empresa
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-rounded">apartment</span>
                </div>
                <div class="stat-info">
                    <h3><?= $total_empresas ?></h3>
                    <p>Total Empresas</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-rounded">description</span>
                </div>
                <div class="stat-info">
                    <h3><?= $total_con_rnc ?></h3>
                    <p>Con RNC Registrado</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-rounded">store</span>
                </div>
                <div class="stat-info">
                    <h3><?= $total_con_sucursales ?></h3>
                    <p>Con Sucursales</p>
                </div>
            </div>
        </div>

        <!-- Grid de Empresas (Cards) -->
        <div class="empresas-wrapper">
            <div class="empresas-grid">
                <?php if (empty($empresas)): ?>
                    <div class="no-empresas">
                        <span class="material-symbols-rounded">business_off</span>
                        <p>No hay empresas registradas</p>
                        <p style="font-size: 12px; margin-top: 8px;">Haz clic en "Nueva Empresa" para comenzar</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($empresas as $e):
                    $logo_url = !empty($e['logo_url']) ? $e['logo_url'] : '/sistema-gestor-de-farmacias/assets/img/empresa/default.png';
                    $clase_rnc = !empty($e['rnc']) ? 'metrica-rnc-success' : 'metrica-rnc-danger';
                    $texto_rnc = !empty($e['rnc']) ? '✓' : '✗';
                ?>
                    <div class="empresa-card"
                        data-id="<?= $e['id_empresa'] ?>"
                        data-nombre="<?= htmlspecialchars($e['nombre']) ?>"
                        data-rnc="<?= htmlspecialchars($e['rnc'] ?? '') ?>"
                        data-direccion="<?= htmlspecialchars($e['direccion'] ?? '') ?>"
                        data-logo="<?= htmlspecialchars($logo_url) ?>"
                        data-sucursales="<?= $e['total_sucursales'] ?>">
                        <div class="card-imagen">
                            <img src="<?= htmlspecialchars($logo_url) ?>"
                                alt="Logo de <?= htmlspecialchars($e['nombre']) ?>"
                                onerror="this.src='/sistema-gestor-de-farmacias/assets/img/empresa/default.png'">
                        </div>

                        <div class="card-cuerpo">
                            <span class="card-fecha">
                                ID: <?= $e['id_empresa'] ?>
                            </span>
                            <h2 class="nombre-empresa"><?= htmlspecialchars($e['nombre']) ?></h2>
                            <?php if (!empty($e['rnc'])): ?>
                                <p class="card-descripcion">
                                    <span class="material-symbols-rounded">receipt</span>
                                    RNC: <?= htmlspecialchars($e['rnc']) ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($e['direccion'])): ?>
                                <p class="card-descripcion">
                                    <span class="material-symbols-rounded">location_on</span>
                                    <?= htmlspecialchars(substr($e['direccion'], 0, 50)) . (strlen($e['direccion']) > 50 ? '...' : '') ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="card-metricas">
                            <div class="metrica metrica-sucursales">
                                <span class="metrica-valor"><?= $e['total_sucursales'] ?></span>
                                <span class="metrica-etiqueta">SUCURSALES</span>
                            </div>
                            <div class="metrica <?= $clase_rnc ?>">
                                <span class="metrica-valor"><?= $texto_rnc ?></span>
                                <span class="metrica-etiqueta">RNC</span>
                            </div>
                            <div class="metrica metrica-registro">
                                <span class="metrica-valor"><?= isset($e['fecha_registro']) && $e['fecha_registro'] ? date('d/m/Y', strtotime($e['fecha_registro'])) : '—' ?></span>
                                <span class="metrica-etiqueta">REGISTRO</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="modal" class="modal">
        <div class="modal-contenido">
            <span class="cerrar" onclick="cerrarModal()">&times;</span>
            <h2 id="modal-titulo">Gestión de Empresa</h2>

            <form id="formulario-gestion" enctype="multipart/form-data">
                <input type="hidden" name="accion" id="accion">
                <input type="hidden" name="id_empresa" id="id_empresa">
                <input type="hidden" name="imagen_actual" id="imagen_actual">

                <div class="grid-inputs">
                    <label>Nombre de la Empresa *</label>
                    <input type="text" name="nombre" id="nombre" placeholder="Ej: Farmacia Salud+" required autocomplete="off">

                    <label>RNC</label>
                    <input type="text" name="rnc" id="rnc" placeholder="Ej: 101-2345678-9" autocomplete="off">

                    <label>Dirección</label>
                    <textarea name="direccion" id="direccion" placeholder="Dirección completa de la empresa"></textarea>

                    <label>Logo de la Empresa</label>
                    <input type="file" name="imagen" id="imagen" accept="image/*">
                    <div id="previewLogo" class="preview-imagen" style="display: none;">
                        <img id="previewImg" src="" alt="Vista previa">
                        <span style="font-size: 12px; color: #64748b;">Vista previa</span>
                    </div>
                </div>

                <div class="form-actions" id="form-actions">
                    <button type="submit" class="btn-guardar" id="btn-guardar">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('modal');
        const form = document.getElementById('formulario-gestion');

        // Función para enviar formulario vía AJAX
        async function enviarFormulario(formData) {
            // Mostrar loading
            Swal.fire({
                title: 'Guardando...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const response = await fetch('/sistema-gestor-de-farmacias/frontend/administracion/empresa.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                Swal.close();

                if (result.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: result.message,
                        confirmButtonColor: '#28a745',
                        timer: 2000,
                        showConfirmButton: true
                    });
                    location.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.message,
                        confirmButtonColor: '#dc2626'
                    });
                }
            } catch (error) {
                Swal.close();
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Ocurrió un error al procesar la solicitud.',
                    confirmButtonColor: '#dc2626'
                });
            }
        }

        function abrirModalCrear() {
            document.getElementById('accion').value = 'crear';
            document.getElementById('modal-titulo').innerHTML = 'Nueva Empresa';
            document.getElementById('id_empresa').value = '';
            document.getElementById('nombre').value = '';
            document.getElementById('rnc').value = '';
            document.getElementById('direccion').value = '';
            document.getElementById('imagen_actual').value = '';
            document.getElementById('imagen').value = '';
            document.getElementById('previewLogo').style.display = 'none';

            const actionsDiv = document.getElementById('form-actions');
            actionsDiv.innerHTML = '<button type="submit" class="btn-guardar">Guardar Empresa</button>';

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function abrirModalEditar(empresa) {
            document.getElementById('accion').value = 'editar';
            document.getElementById('modal-titulo').innerHTML = 'Editar Empresa';
            document.getElementById('id_empresa').value = empresa.id_empresa;
            document.getElementById('nombre').value = empresa.nombre;
            document.getElementById('rnc').value = empresa.rnc || '';
            document.getElementById('direccion').value = empresa.direccion || '';
            document.getElementById('imagen_actual').value = empresa.logo_url || '';

            // Mostrar preview de la imagen actual
            if (empresa.logo_url && empresa.logo_url !== '' && !empresa.logo_url.includes('default.png')) {
                const previewDiv = document.getElementById('previewLogo');
                const previewImg = document.getElementById('previewImg');
                previewImg.src = empresa.logo_url;
                previewDiv.style.display = 'flex';
            } else {
                document.getElementById('previewLogo').style.display = 'none';
            }

            const actionsDiv = document.getElementById('form-actions');
            actionsDiv.innerHTML = `
            <button type="button" class="btn-eliminar-modal" onclick="eliminarEmpresa(${empresa.id_empresa}, '${empresa.nombre.replace(/'/g, "\\'")}')">Eliminar</button>
            <button type="submit" class="btn-guardar">Actualizar</button>
        `;

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function eliminarEmpresa(id, nombre) {
            Swal.fire({
                title: '¿Eliminar empresa?',
                html: `Estás a punto de eliminar <strong>${nombre}</strong>.<br><br>
                   <span style="color: #dc2626;">⚠️ Esta acción eliminará la empresa si no tiene sucursales asociadas.</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#37383b',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('accion', 'eliminar');
                    formData.append('id_empresa', id);

                    try {
                        const response = await fetch('/sistema-gestor-de-farmacias/frontend/administracion/empresa.php', {
                            method: 'POST',
                            body: formData
                        });
                        const resultData = await response.json();

                        if (resultData.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Eliminada!',
                                text: resultData.message,
                                confirmButtonColor: '#28a745',
                                timer: 2000,
                                showConfirmButton: true
                            }).then(() => {
                                setTimeout(() => {
                                    location.reload();
                                }, 500);
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: resultData.message,
                                confirmButtonColor: '#dc2626'
                            });
                        }
                    } catch (error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Ocurrió un error al eliminar.',
                            confirmButtonColor: '#dc2626'
                        });
                    }
                }
            });
        }

        function cerrarModal() {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            form.reset();
        }

        // Evento para las cards (click para editar) - Usando dataset
        document.querySelectorAll('.empresa-card').forEach(card => {
            card.addEventListener('click', function(e) {
                const id = this.dataset.id;
                const nombre = this.dataset.nombre;
                const rnc = this.dataset.rnc;
                const direccion = this.dataset.direccion;
                const logo = this.dataset.logo;

                abrirModalEditar({
                    id_empresa: id,
                    nombre: nombre,
                    rnc: rnc,
                    direccion: direccion,
                    logo_url: logo
                });
            });
        });

        // Vista previa de imagen al seleccionar archivo
        document.getElementById('imagen')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const previewDiv = document.getElementById('previewLogo');
            const previewImg = document.getElementById('previewImg');

            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    previewImg.src = event.target.result;
                    previewDiv.style.display = 'flex';
                };
                reader.readAsDataURL(file);
            } else {
                const imagenActual = document.getElementById('imagen_actual').value;
                if (imagenActual && imagenActual !== '' && !imagenActual.includes('default.png')) {
                    previewImg.src = imagenActual;
                    previewDiv.style.display = 'flex';
                } else {
                    previewDiv.style.display = 'none';
                }
            }
        });

        // Envío del formulario
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(form);

            const nombre = formData.get('nombre');
            if (!nombre.trim()) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'El nombre de la empresa es obligatorio.',
                    confirmButtonColor: '#dc2626'
                });
                return;
            }

            await enviarFormulario(formData);
        });

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            if (event.target === modal) {
                cerrarModal();
            }
        }

        // Cerrar con Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modal.style.display === 'flex') {
                cerrarModal();
            }
        });
    </script>

</body>

</html>