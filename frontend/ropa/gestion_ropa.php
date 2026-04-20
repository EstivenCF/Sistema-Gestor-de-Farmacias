<?php
// gestion_ropa.php - Módulo completo de gestión de ropa y productos de conveniencia
// Conexión real a base de datos

// Iniciar sesión solo si no está activa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

ob_start();

include(__DIR__ . "/../../backend/conexion.php");

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("Errores activos");

// --- FUNCIÓN PARA COMPRIMIR NÚMEROS (K, M) ---
function comprimirNumero($n) {
    if ($n >= 1000000) {
        return round($n / 1000000, 2) . 'M';
    } elseif ($n >= 1000) {
        return round($n / 1000, 1) . 'K';
    }
    return number_format($n);
}

function comprimirMoneda($n) {
    if ($n >= 1000000) {
        return '$' . round($n / 1000000, 2) . 'M';
    } elseif ($n >= 1000) {
        return '$' . round($n / 1000, 1) . 'K';
    }
    return '$' . number_format($n, 2);
}

// ========================
// PROCESAR FORMULARIO (CRUD)
// ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id_producto = $_POST['id_producto'] ?? null;
    $id_ropa = $_POST['id_ropa'] ?? null;

    // Datos del producto base
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : null;
    $precio = isset($_POST['precio']) ? floatval($_POST['precio']) : 0;
    $exento_itbis = isset($_POST['exento_itbis']) ? (intval($_POST['exento_itbis']) === 1) : false;
    $estado = isset($_POST['estado']) ? (intval($_POST['estado']) === 1) : true;

    // Datos específicos de ropa
    $talla = isset($_POST['talla']) ? trim($_POST['talla']) : null;
    $color = isset($_POST['color']) ? trim($_POST['color']) : null;
    $marca = isset($_POST['marca']) ? trim($_POST['marca']) : null;
    $id_tipo = isset($_POST['id_tipo']) && $_POST['id_tipo'] !== '' ? intval($_POST['id_tipo']) : null;
    $id_marca = isset($_POST['id_marca']) && $_POST['id_marca'] !== '' ? intval($_POST['id_marca']) : null;
    $id_fabricante = isset($_POST['id_fabricante']) && $_POST['id_fabricante'] !== '' ? intval($_POST['id_fabricante']) : null;
    $id_color = isset($_POST['id_color']) && $_POST['id_color'] !== '' ? intval($_POST['id_color']) : null;
    $id_talla = isset($_POST['id_talla']) && $_POST['id_talla'] !== '' ? intval($_POST['id_talla']) : null;

    // Stock (se maneja en inventario_productos)
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $id_sucursal = isset($_POST['id_sucursal']) ? intval($_POST['id_sucursal']) : 1;

    // IMAGEN
    $url_default = "/sistema-gestor-de-farmacias/assets/img/ropa/default.png";
    $imagen_actual = $_POST['imagen_actual'] ?? null;
    $imagen_final = ($accion === 'crear') ? $url_default : $imagen_actual;

    if (!empty($_FILES['imagen']['name']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $dir = __DIR__ . "/../../assets/img/ropa/";
        $url_base = "/sistema-gestor-de-farmacias/assets/img/ropa/";

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
        // Iniciar transacción
        $conexion->beginTransaction();

        if ($accion === 'crear') {
            // Verificar si ya existe un producto con el mismo nombre
            $check = $conexion->prepare("SELECT COUNT(*) FROM productos WHERE nombre = ? AND tipo_producto = 'ROPA'");
            $check->execute([$nombre]);
            if ($check->fetchColumn() > 0) {
                $mensaje_error = 'Ya existe un producto de ropa con ese nombre.';
            } else {
                // 1. Insertar en productos
                $sql = "INSERT INTO productos (nombre, tipo_producto, precio, estado, exento_itbis, imagen_url) 
            VALUES (?, 'ROPA', ?, ?, ?, ?) RETURNING id_producto";

                $stmt = $conexion->prepare($sql);
                $stmt->execute([$nombre, $precio, $estado ? 't' : 'f', $exento_itbis ? 't' : 'f', $imagen_final]);

                $id_producto = $stmt->fetchColumn();

                // 2. Insertar en ropa_detalle
                $sql = "INSERT INTO ropa_detalle (id_producto, talla, color, marca, id_tipo, id_marca, id_fabricante, id_color, id_talla) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id_ropa";

                $stmt = $conexion->prepare($sql);
                $stmt->execute([$id_producto, $talla, $color, $marca, $id_tipo, $id_marca, $id_fabricante, $id_color, $id_talla]);

                $id_ropa = $stmt->fetchColumn();

                // 3. Insertar en inventario_productos (stock inicial)
                $sql = "INSERT INTO inventario_productos (id_producto, id_sucursal, cantidad) 
                        VALUES (?, ?, ?)";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([$id_producto, $id_sucursal, $stock]);

                $exito = true;
            }
        } elseif ($accion === 'editar') {
            // Verificar duplicado EXCLUYENDO el producto actual
            $check = $conexion->prepare("SELECT COUNT(*) FROM productos WHERE nombre = ? AND tipo_producto = 'ROPA' AND id_producto != ?");
            $check->execute([$nombre, $id_producto]);
            if ($check->fetchColumn() > 0) {
                $mensaje_error = 'Ya existe otro producto de ropa con ese nombre.';
            } else {
                // 1. Actualizar productos (mantener imagen actual si no se sube nueva)
                if (empty($imagen_final) || $imagen_final == $url_default) {
                    // Obtener la imagen actual de la base de datos
                    $stmtImg = $conexion->prepare("SELECT imagen_url FROM productos WHERE id_producto = ?");
                    $stmtImg->execute([$id_producto]);
                    $imagen_guardada = $stmtImg->fetchColumn();
                    $imagen_final = !empty($imagen_guardada) ? $imagen_guardada : $url_default;
                }

                $sql = "UPDATE productos SET nombre=?, precio=?, estado=?, exento_itbis=?, imagen_url=? WHERE id_producto=?";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([$nombre, $precio, $estado ? 't' : 'f', $exento_itbis ? 't' : 'f', $imagen_final, $id_producto]);

                // 2. Verificar si existe ropa_detalle
                $checkRopa = $conexion->prepare("SELECT COUNT(*) FROM ropa_detalle WHERE id_producto = ?");
                $checkRopa->execute([$id_producto]);
                
                if ($checkRopa->fetchColumn() > 0) {
                    // Actualizar ropa_detalle existente
                    $sql = "UPDATE ropa_detalle SET talla=?, color=?, marca=?, id_tipo=?, id_marca=?, id_fabricante=?, id_color=?, id_talla=? 
                            WHERE id_producto=?";
                    $stmt = $conexion->prepare($sql);
                    $stmt->execute([$talla, $color, $marca, $id_tipo, $id_marca, $id_fabricante, $id_color, $id_talla, $id_producto]);
                } else {
                    // Insertar nuevo ropa_detalle
                    $sql = "INSERT INTO ropa_detalle (id_producto, talla, color, marca, id_tipo, id_marca, id_fabricante, id_color, id_talla) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conexion->prepare($sql);
                    $stmt->execute([$id_producto, $talla, $color, $marca, $id_tipo, $id_marca, $id_fabricante, $id_color, $id_talla]);
                }

                // 3. Actualizar stock en inventario_productos
                $checkInv = $conexion->prepare("SELECT COUNT(*) FROM inventario_productos WHERE id_producto = ? AND id_sucursal = ?");
                $checkInv->execute([$id_producto, $id_sucursal]);

                if ($checkInv->fetchColumn() > 0) {
                    $sql = "UPDATE inventario_productos SET cantidad = ? WHERE id_producto = ? AND id_sucursal = ?";
                    $stmt = $conexion->prepare($sql);
                    $stmt->execute([$stock, $id_producto, $id_sucursal]);
                } else {
                    $sql = "INSERT INTO inventario_productos (id_producto, id_sucursal, cantidad) VALUES (?, ?, ?)";
                    $stmt = $conexion->prepare($sql);
                    $stmt->execute([$id_producto, $id_sucursal, $stock]);
                }

                $exito = true;
            }
        } else {
            $mensaje_error = 'Acción no válida.';
        }

        if ($exito) {
            $conexion->commit();
        } else {
            $conexion->rollBack();
        }
    } catch (PDOException $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        $mensaje_error = 'Error técnico: ' . $e->getMessage();
        error_log("ERROR PDO: " . $e->getMessage());
    } catch (Exception $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        $mensaje_error = 'Error: ' . $e->getMessage();
        error_log("ERROR General: " . $e->getMessage());
    }

    // Devolver respuesta JSON para SweetAlert
    if (ob_get_length()) ob_clean();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $exito,
        'message' => $exito ? 'Operación realizada con éxito.' : $mensaje_error
    ]);
    exit;
}

// ========================
// OBTENER DATOS PARA LISTADO Y FILTROS
// ========================

// Obtener parámetros de filtro
$buscar = isset($_GET['buscar']) ? $_GET['buscar'] : '';
$filtro_categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';
$filtro_talla = isset($_GET['talla']) ? $_GET['talla'] : '';

// Construir consulta SQL con filtros
$sql = "
    SELECT 
        p.id_producto,
        p.nombre,
        p.precio,
        p.estado,
        p.exento_itbis,
        p.imagen_url,
        COALESCE(SUM(ip.cantidad), 0) as stock_total,
        COUNT(DISTINCT r.id_ropa) as num_variantes,
        STRING_AGG(DISTINCT COALESCE(ta.nombre, r.talla), ', ' ORDER BY COALESCE(ta.nombre, r.talla)) as tallas_disponibles,
        STRING_AGG(DISTINCT COALESCE(c.nombre, r.color), ', ' ORDER BY COALESCE(c.nombre, r.color)) as colores_disponibles,
        STRING_AGG(DISTINCT COALESCE(m.nombre, r.marca), ', ' ORDER BY COALESCE(m.nombre, r.marca)) as marcas_disponibles,
        t.nombre as tipo_nombre
    FROM productos p
    LEFT JOIN ropa_detalle r ON p.id_producto = r.id_producto
    LEFT JOIN tipo_ropa t ON r.id_tipo = t.id_tipo
    LEFT JOIN marcas m ON r.id_marca = m.id_marca
    LEFT JOIN colores c ON r.id_color = c.id_color
    LEFT JOIN tallas ta ON r.id_talla = ta.id_talla
    LEFT JOIN inventario_productos ip ON p.id_producto = ip.id_producto
    WHERE p.tipo_producto = 'ROPA'
";

// Aplicar filtros
if (!empty($buscar)) {
    $sql .= " AND (p.nombre ILIKE :buscar OR r.marca ILIKE :buscar OR COALESCE(m.nombre, r.marca) ILIKE :buscar)";
}
if (!empty($filtro_categoria)) {
    $sql .= " AND t.nombre = :categoria";
}
if (!empty($filtro_talla)) {
    $sql .= " AND (COALESCE(ta.nombre, r.talla) = :talla)";
}

$sql .= " GROUP BY p.id_producto, p.nombre, p.precio, p.estado, p.exento_itbis, p.imagen_url, t.nombre
          ORDER BY p.id_producto DESC";

$stmt = $conexion->prepare($sql);

if (!empty($buscar)) {
    $stmt->bindValue(':buscar', '%' . $buscar . '%');
}
if (!empty($filtro_categoria)) {
    $stmt->bindValue(':categoria', $filtro_categoria);
}
if (!empty($filtro_talla)) {
    $stmt->bindValue(':talla', $filtro_talla);
}

$stmt->execute();
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener todas las variantes para cada producto
$variantes_por_producto = [];
foreach ($productos as $p) {
    $stmtVar = $conexion->prepare("
        SELECT 
            r.id_ropa,
            r.id_producto,
            COALESCE(ta.nombre, r.talla) as talla,
            COALESCE(c.nombre, r.color) as color,
            COALESCE(m.nombre, r.marca) as marca,
            COALESCE(SUM(ip.cantidad), 0) as stock,
            ip.id_sucursal
        FROM ropa_detalle r
        LEFT JOIN tallas ta ON r.id_talla = ta.id_talla
        LEFT JOIN colores c ON r.id_color = c.id_color
        LEFT JOIN marcas m ON r.id_marca = m.id_marca
        LEFT JOIN inventario_productos ip ON r.id_producto = ip.id_producto
        WHERE r.id_producto = ?
        GROUP BY r.id_ropa, r.id_producto, ta.nombre, r.talla, c.nombre, r.color, m.nombre, r.marca, ip.id_sucursal
    ");
    $stmtVar->execute([$p['id_producto']]);
    $variantes_por_producto[$p['id_producto']] = $stmtVar->fetchAll(PDO::FETCH_ASSOC);
}

// ========================
// OBTENER DATOS PARA SELECTORES (CATÁLOGOS) - SOLO REGISTROS ACTIVOS
// ========================

// Obtener tipos de ropa (solo activos)
$tipos_ropa = $conexion->query("SELECT id_tipo, nombre FROM tipo_ropa WHERE estado = true ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Obtener marcas (solo activas)
$marcas = $conexion->query("SELECT id_marca, nombre FROM marcas WHERE estado = true ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Obtener fabricantes (solo activos)
$fabricantes = $conexion->query("SELECT id_fabricante, nombre FROM fabricantes WHERE estado = true ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Obtener colores (solo activos)
$colores = $conexion->query("SELECT id_color, nombre FROM colores WHERE estado = true ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Obtener tallas (solo activas)
$tallas = $conexion->query("SELECT id_talla, nombre FROM tallas WHERE estado = true ORDER BY 
                            CASE 
                                WHEN nombre = 'XS' THEN 1
                                WHEN nombre = 'S' THEN 2
                                WHEN nombre = 'M' THEN 3
                                WHEN nombre = 'L' THEN 4
                                WHEN nombre = 'XL' THEN 5
                                WHEN nombre = 'XXL' THEN 6
                                ELSE 99
                            END, nombre")->fetchAll(PDO::FETCH_ASSOC);

// Obtener sucursales (solo activas)
$sucursales = $conexion->query("SELECT id_sucursal, nombre FROM sucursales WHERE estado = true ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total_productos = count($productos);
$total_stock = 0;
$total_con_stock_bajo = 0;
$valor_inventario = 0;

foreach ($productos as $p) {
    $total_stock += $p['stock_total'];
    if ($p['stock_total'] <= 5) {
        $total_con_stock_bajo++;
    }
    $valor_inventario += $p['precio'] * $p['stock_total'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ropa y Productos de Conveniencia</title>
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

        .btn-outline-primary {
            background: transparent;
            border: 2px solid #1067b9;
            color: #1067b9;
        }

        .btn-outline-primary:hover {
            background: #1067b9;
            color: white;
            transform: translateY(-2px);
        }

        /* Agregamos una mejora visual para los números comprimidos */
        .stat-info h3 {
            font-size: 24px; /* Un poco más pequeño para que no rompa el card */
            white-space: nowrap;
        }
        .tooltip-value {
            cursor: help;
            border-bottom: 1px dotted #ccc;
        }

        /* Filtros */
        .filtros-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }

        .filtro-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .filtro-group input,
        .filtro-group select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.2s;
        }

        .filtro-group input:focus,
        .filtro-group select:focus {
            outline: none;
            border-color: #1067b9;
            box-shadow: 0 0 0 3px rgba(16, 103, 185, 0.1);
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
        }

        .stat-info p {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }

        /* Grid de Productos */
        .productos-wrapper {
            background: white;
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .productos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        /* Card de Producto */
        .producto-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .producto-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 30px -12px rgba(0, 0, 0, 0.15);
            border-color: transparent;
        }

        .card-imagen {
            width: 100%;
            height: 200px;
            background-color: #e5e7eb;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .card-imagen img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .producto-card:hover .card-imagen img {
            transform: scale(1.05);
        }

        .badge-stock {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: white;
        }

        .badge-stock.critico {
            background: #ef4444;
        }

        .badge-stock.bajo {
            background: #f59e0b;
        }

        .badge-stock.normal {
            background: #10b981;
        }

        .card-cuerpo {
            padding: 16px;
            flex-grow: 1;
        }

        .card-categoria {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            margin-bottom: 10px;
        }

        .categoria-medico {
            background: #e0f2fe;
            color: #0284c7;
        }

        .categoria-bebe {
            background: #fce7f3;
            color: #db2777;
        }

        .categoria-ortopedia {
            background: #fef3c7;
            color: #d97706;
        }

        .nombre-producto {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1e293b;
            line-height: 1.3;
        }

        .card-detalles {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 12px;
            font-size: 0.75rem;
            color: #64748b;
        }

        .card-detalles span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .card-detalles .material-symbols-rounded {
            font-size: 14px;
        }

        .precio {
            font-size: 1.3rem;
            font-weight: 700;
            color: #28a745;
            margin-top: 12px;
        }

        .stock {
            font-size: 0.8rem;
            margin-top: 8px;
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

        .modal-show {
            display: flex;
        }

        .modal-contenido {
            background: white;
            border-radius: 16px;
            padding: 16px 16px 8px 16px;
            width: 900px;
            max-width: 95%;
            color: #222;
            position: relative;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25);
            animation: fadeIn 0.25s ease-out;
            max-height: 95vh;
            overflow-y: auto;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .cerrar {
            position: absolute;
            top: 15px;
            right: 25px;
            font-size: 30px;
            color: #37383b;
            cursor: pointer;
            line-height: 1;
            transition: color 0.2s;
        }

        .cerrar:hover {
            color: #222222;
        }

        .modal-contenido h2 {
            text-align: center;
            margin-bottom: 18px;
            font-weight: 700;
            font-size: 20px;
        }

        .formulario-gestion {
            padding: 0 20px 10px;
        }

        /* FORMULARIO EN 2 COLUMNAS */
        .grid-inputs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .grid-inputs-full {
            grid-column: span 3;
        }

        .grid-inputs label {
            font-weight: 600;
            font-size: 11px;
            color: #334155;
            margin-bottom: 4px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .grid-inputs input,
        .grid-inputs select,
        .grid-inputs textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1.5px solid #e2e8f0;
            border-radius: 50px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            background: #f8fafc;
        }

        .grid-inputs input:focus,
        .grid-inputs select:focus,
        .grid-inputs textarea:focus {
            outline: none;
            border-color: #28a745;
            background: white;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.1);
        }

        .preview-imagen {
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .preview-imagen img {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            object-fit: cover;
            background: #f0f0f0;
            border: 1.5px solid #e2e8f0;
        }

        .form-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
            padding-bottom: 10px;
        }

        .btn-guardar {
            width: auto;
            min-width: 180px;
            padding: 10px 24px;
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        .btn-guardar:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        input[type="file"] {
            font-size: 13px;
            padding: 6px;
        }

        @media (max-width: 900px) {
            .grid-inputs {
                grid-template-columns: repeat(2, 1fr);
            }
            .grid-inputs-full {
                grid-column: span 2;
            }
        }

        @media (max-width: 500px) {
            .grid-inputs {
                grid-template-columns: 1fr;
            }
            .grid-inputs-full {
                grid-column: span 1;
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
                    <span class="material-symbols-rounded">apparel</span>
                    Gestión de Ropa y Productos de Conveniencia
                </h2>
                <p>Administra uniformes médicos, productos de maternidad, ortopedia y más</p>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-success" onclick="abrirModalCrear()">
                    <span class="material-symbols-rounded">add</span>
                    Nuevo Producto
                </button>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-rounded">apparel</span>
                </div>
                <div class="stat-info">
                    <h3 title="<?php echo number_format($total_productos); ?>">
                        <?php echo comprimirNumero($total_productos); ?>
                    </h3>
                    <p>Total Productos</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-rounded">inventory</span>
                </div>
                <div class="stat-info">
                    <h3 title="<?php echo number_format($total_stock); ?>">
                        <?php echo comprimirNumero($total_stock); ?>
                    </h3>
                    <p>Unidades en Stock</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-rounded">warning</span>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_con_stock_bajo; ?></h3>
                    <p>Stock Bajo (≤5)</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-rounded">attach_money</span>
                </div>
                <div class="stat-info">
                    <h3 title="<?php echo number_format($valor_inventario, 2); ?>">
                        <?php echo comprimirMoneda($valor_inventario); ?>
                    </h3>
                    <p>Valor Inventario</p>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filtros-card">
            <form id="formFiltros" method="GET" action="">
                <input type="hidden" name="mod" value="gestion_ropa">
                <div class="filtros-grid">
                    <div class="filtro-group">
                        <label>BUSCAR</label>
                        <input type="text" name="buscar" placeholder="Nombre o marca..." value="<?php echo htmlspecialchars($buscar); ?>">
                    </div>

                    <div class="filtro-group">
                        <label>CATEGORÍA</label>
                        <select name="categoria">
                            <option value="">Todas</option>
                            <?php foreach ($tipos_ropa as $tipo): ?>
                                <option value="<?php echo htmlspecialchars($tipo['nombre']); ?>" <?php echo $filtro_categoria == $tipo['nombre'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filtro-group">
                        <label>TALLA</label>
                        <select name="talla">
                            <option value="">Todas</option>
                            <?php foreach ($tallas as $talla): ?>
                                <option value="<?php echo htmlspecialchars($talla['nombre']); ?>" <?php echo $filtro_talla == $talla['nombre'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($talla['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filtro-group">
                        <button type="submit" class="btn btn-outline-primary" style="width: 100%;">
                            Aplicar Filtros
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Grid de Productos -->
        <div class="productos-wrapper">
            <div class="productos-grid">
                <?php if (empty($productos)): ?>
                    <div style="text-align: center; padding: 60px;">
                        <span class="material-symbols-rounded" style="font-size: 64px; color: #cbd5e1;">shopping_bag_off</span>
                        <p style="margin-top: 16px; color: #64748b;">No hay productos registrados</p>
                        <p style="font-size: 12px; margin-top: 8px; color: #94a3b8;">Haz clic en "Nuevo Producto" para comenzar</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($productos as $p):
                    // Determinar clase de categoría para el badge
                    $categoria_clase = 'categoria-medico';
                    $categoria_texto = $p['tipo_nombre'] ?? 'General';
                    if (stripos($categoria_texto, 'bebé') !== false || stripos($categoria_texto, 'maternidad') !== false) {
                        $categoria_clase = 'categoria-bebe';
                    } elseif (stripos($categoria_texto, 'ortopedia') !== false || stripos($categoria_texto, 'soporte') !== false) {
                        $categoria_clase = 'categoria-ortopedia';
                    }

                    $stock_clase = 'normal';
                    $stock_texto = 'Stock OK';
                    if ($p['stock_total'] <= 0) {
                        $stock_clase = 'critico';
                        $stock_texto = 'AGOTADO';
                    } elseif ($p['stock_total'] <= 5) {
                        $stock_clase = 'critico';
                        $stock_texto = 'CRÍTICO';
                    } elseif ($p['stock_total'] <= 10) {
                        $stock_clase = 'bajo';
                        $stock_texto = 'BAJO';
                    }

                    $variantes = $variantes_por_producto[$p['id_producto']] ?? [];
                    $primera_variante = !empty($variantes) ? $variantes[0] : [];
                ?>
                    <div class="producto-card"
                        data-id_producto="<?php echo $p['id_producto']; ?>"
                        data-id_ropa="<?php echo $primera_variante['id_ropa'] ?? ''; ?>"
                        data-nombre="<?php echo htmlspecialchars($p['nombre']); ?>"
                        data-precio="<?php echo $p['precio']; ?>"
                        data-estado="<?php echo $p['estado']; ?>"
                        data-exento_itbis="<?php echo $p['exento_itbis']; ?>"
                        data-imagen_url="<?php echo !empty($p['imagen_url']) ? htmlspecialchars($p['imagen_url']) : ''; ?>"
                        data-stock="<?php echo $p['stock_total']; ?>"
                        data-talla="<?php echo htmlspecialchars($primera_variante['talla'] ?? ''); ?>"
                        data-color="<?php echo htmlspecialchars($primera_variante['color'] ?? ''); ?>"
                        data-marca="<?php echo htmlspecialchars($primera_variante['marca'] ?? ''); ?>"
                        data-id_tipo=""
                        data-id_marca=""
                        data-id_fabricante=""
                        data-id_color=""
                        data-id_talla=""
                        data-id_sucursal="<?php echo $primera_variante['id_sucursal'] ?? 1; ?>">

                        <div class="card-imagen">
                            <img src="<?php echo !empty($p['imagen_url']) ? htmlspecialchars($p['imagen_url']) : '/sistema-gestor-de-farmacias/assets/img/ropa/default.png'; ?>"
                                alt="<?php echo htmlspecialchars($p['nombre']); ?>"
                                onerror="this.src='/sistema-gestor-de-farmacias/assets/img/ropa/default.png'">
                            <span class="badge-stock <?php echo $stock_clase; ?>"><?php echo $stock_texto; ?></span>
                        </div>

                        <div class="card-cuerpo">
                            <span class="card-categoria <?php echo $categoria_clase; ?>"><?php echo htmlspecialchars($categoria_texto); ?></span>
                            <h3 class="nombre-producto"><?php echo htmlspecialchars($p['nombre']); ?></h3>

                            <div class="card-detalles">
                                <?php if (!empty($p['marcas_disponibles'])): ?>
                                    <span><span class="material-symbols-rounded">brand_awareness</span> <?php echo htmlspecialchars($p['marcas_disponibles']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($p['tallas_disponibles'])): ?>
                                    <span><span class="material-symbols-rounded">straighten</span> Tallas: <?php echo htmlspecialchars($p['tallas_disponibles']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($p['colores_disponibles'])): ?>
                                    <span><span class="material-symbols-rounded">palette</span> Colores: <?php echo htmlspecialchars($p['colores_disponibles']); ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($p['num_variantes'] > 0): ?>
                                <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #e9ecef; display: flex; flex-wrap: wrap; gap: 6px;">
                                    <span style="background: #f1f5f9; padding: 4px 10px; border-radius: 20px; font-size: 11px; color: #334155; display: inline-flex; align-items: center; gap: 4px;">
                                        <span class="material-symbols-rounded" style="font-size: 12px;">device_hub</span>
                                        <?php echo $p['num_variantes']; ?> variante(s)
                                    </span>
                                </div>
                            <?php endif; ?>

                            <div class="precio">$<?php echo number_format($p['precio'], 2); ?></div>
                            <div class="stock">
                                Stock total: 
                                <span title="<?php echo number_format($p['stock_total']); ?>">
                                    <?php echo comprimirNumero($p['stock_total']); ?>
                                </span> unidades
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Modal (fuera del container para que position:fixed funcione correctamente) -->
    <div id="modal" class="modal">
        <div class="modal-contenido">
            <span class="cerrar" onclick="cerrarModal()">&times;</span>
            <h2 id="modal-titulo">Nuevo Producto</h2>

            <form id="formulario-gestion" enctype="multipart/form-data" class="formulario-gestion">
                <input type="hidden" name="accion" id="accion">
                <input type="hidden" name="id_producto" id="id_producto">
                <input type="hidden" name="id_ropa" id="id_ropa">
                <input type="hidden" name="imagen_actual" id="imagen_actual">
                <input type="hidden" name="id_sucursal" id="id_sucursal_hidden" value="1">

                <div class="grid-inputs">
                    <div class="grid-inputs-full">
                        <label>Nombre del Producto *</label>
                        <input type="text" name="nombre" id="nombre" placeholder="Ej: Bata Médica Antifluidos" required autocomplete="off">
                    </div>

                    <div>
                        <label>Categoría / Tipo</label>
                        <select name="id_tipo" id="id_tipo">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($tipos_ropa as $tipo): ?>
                                <option value="<?php echo $tipo['id_tipo']; ?>"><?php echo htmlspecialchars($tipo['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Marca</label>
                        <select name="id_marca" id="id_marca">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($marcas as $marca): ?>
                                <option value="<?php echo $marca['id_marca']; ?>"><?php echo htmlspecialchars($marca['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Fabricante</label>
                        <select name="id_fabricante" id="id_fabricante">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($fabricantes as $fabricante): ?>
                                <option value="<?php echo $fabricante['id_fabricante']; ?>"><?php echo htmlspecialchars($fabricante['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Talla</label>
                        <select name="id_talla" id="id_talla">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($tallas as $talla): ?>
                                <option value="<?php echo $talla['id_talla']; ?>"><?php echo htmlspecialchars($talla['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Talla Texto Libre</label>
                        <input type="text" name="talla" id="talla" placeholder="Ej: M, L, XL, Única" autocomplete="off">
                    </div>

                    <div>
                        <label>Color</label>
                        <select name="id_color" id="id_color">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($colores as $color): ?>
                                <option value="<?php echo $color['id_color']; ?>"><?php echo htmlspecialchars($color['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Color Texto Libre</label>
                        <input type="text" name="color" id="color" placeholder="Ej: Blanco, Azul Marino" autocomplete="off">
                    </div>

                    <div>
                        <label>Marca Texto Libre</label>
                        <input type="text" name="marca" id="marca" placeholder="Ej: HealthStyle" autocomplete="off">
                    </div>

                    <div>
                        <label>Precio *</label>
                        <input type="number" step="0.01" name="precio" id="precio" placeholder="0.00" required>
                    </div>

                    <div>
                        <label>Stock Inicial</label>
                        <input type="number" name="stock" id="stock" value="0">
                    </div>

                    <div>
                        <label>Sucursal</label>
                        <select name="id_sucursal" id="id_sucursal">
                            <option value="">Seleccionar sucursal...</option>
                            <?php foreach ($sucursales as $sucursal): ?>
                                <option value="<?php echo $sucursal['id_sucursal']; ?>"><?php echo htmlspecialchars($sucursal['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Estado</label>
                        <select name="estado" id="estado">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>

                    <div>
                        <label>Exento de ITBIS</label>
                        <select name="exento_itbis" id="exento_itbis">
                            <option value="0">No (Aplica ITBIS)</option>
                            <option value="1">Sí (Exento)</option>
                        </select>
                    </div>

                    <div class="grid-inputs-full">
                        <label>Imagen del Producto</label>
                        <input type="file" name="imagen" id="imagen" accept="image/*">
                        <div id="previewLogo" class="preview-imagen" style="display: none;">
                            <img id="previewImg" src="" alt="Vista previa">
                            <span style="font-size: 12px; color: #64748b;">Vista previa</span>
                        </div>
                    </div>
                </div>

                <div class="form-actions" id="form-actions">
                    <button type="submit" class="btn-guardar" id="btn-guardar">Guardar Producto</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('modal');
        const form = document.getElementById('formulario-gestion');

        // Función para enviar formulario vía AJAX
        async function enviarFormulario(formData) {
            Swal.fire({
                title: 'Guardando...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const response = await fetch('/Sistema-Gestor-de-Farmacias/frontend/ropa/gestion_ropa.php', {
                method: 'POST',
                body: formData
            });

                const text = await response.text();
                console.log("RESPUESTA RAW:", text);

                const result = JSON.parse(text);

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
            document.getElementById('modal-titulo').innerHTML = 'Nuevo Producto';
            document.getElementById('id_producto').value = '';
            document.getElementById('id_ropa').value = '';
            document.getElementById('nombre').value = '';
            document.getElementById('precio').value = '';
            document.getElementById('stock').value = '0';
            document.getElementById('estado').value = '1';
            document.getElementById('exento_itbis').value = '0';
            document.getElementById('id_tipo').value = '';
            document.getElementById('id_marca').value = '';
            document.getElementById('id_fabricante').value = '';
            document.getElementById('id_color').value = '';
            document.getElementById('id_talla').value = '';
            document.getElementById('talla').value = '';
            document.getElementById('color').value = '';
            document.getElementById('marca').value = '';
            document.getElementById('id_sucursal').value = '1';
            document.getElementById('imagen_actual').value = '';
            document.getElementById('imagen').value = '';
            document.getElementById('previewLogo').style.display = 'none';

            const actionsDiv = document.getElementById('form-actions');
            actionsDiv.innerHTML = '<button type="submit" class="btn-guardar">Guardar Producto</button>';

            modal.classList.add('modal-show');
            document.body.style.overflow = 'auto';
        }

        function abrirModalEditar(producto) {
            document.getElementById('accion').value = 'editar';
            document.getElementById('modal-titulo').innerHTML = 'Editar Producto';
            document.getElementById('id_producto').value = producto.id_producto;
            document.getElementById('id_ropa').value = producto.id_ropa || '';
            document.getElementById('nombre').value = producto.nombre;
            document.getElementById('precio').value = producto.precio;
            document.getElementById('stock').value = producto.stock || 0;
            document.getElementById('estado').value = producto.estado ? '1' : '0';
            document.getElementById('exento_itbis').value = producto.exento_itbis ? '1' : '0';
            document.getElementById('id_tipo').value = producto.id_tipo || '';
            document.getElementById('id_marca').value = producto.id_marca || '';
            document.getElementById('id_fabricante').value = producto.id_fabricante || '';
            document.getElementById('id_color').value = producto.id_color || '';
            document.getElementById('id_talla').value = producto.id_talla || '';
            document.getElementById('talla').value = producto.talla || '';
            document.getElementById('color').value = producto.color || '';
            document.getElementById('marca').value = producto.marca || '';
            document.getElementById('id_sucursal').value = producto.id_sucursal || 1;
            document.getElementById('imagen_actual').value = producto.imagen_url || '';

            document.getElementById('previewLogo').style.display = 'none';

            const actionsDiv = document.getElementById('form-actions');
            actionsDiv.innerHTML = `
                <button type="submit" class="btn-guardar">Actualizar</button>
            `;

            modal.classList.add('modal-show');
            document.body.style.overflow = 'auto';
        }

        function cerrarModal() {
            modal.classList.remove('modal-show');
            document.body.style.overflow = 'auto';
            form.reset();
        }

        // Evento para las cards (click para editar)
        document.querySelectorAll('.producto-card').forEach(card => {
            card.addEventListener('click', function(e) {
                const producto = {
                    id_producto: this.dataset.id_producto,
                    id_ropa: this.dataset.id_ropa,
                    nombre: this.dataset.nombre,
                    precio: parseFloat(this.dataset.precio),
                    estado: this.dataset.estado === '1',
                    exento_itbis: this.dataset.exento_itbis === '1',
                    stock: parseInt(this.dataset.stock),
                    talla: this.dataset.talla,
                    color: this.dataset.color,
                    marca: this.dataset.marca,
                    id_tipo: this.dataset.id_tipo,
                    id_marca: this.dataset.id_marca,
                    id_fabricante: this.dataset.id_fabricante,
                    id_color: this.dataset.id_color,
                    id_talla: this.dataset.id_talla,
                    id_sucursal: this.dataset.id_sucursal,
                    imagen_url: this.dataset.imagen_url
                };
                abrirModalEditar(producto);
            });
        });

        // Vista previa de imagen
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
                previewDiv.style.display = 'none';
            }
        });

        // Envío del formulario
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(form);
            const nombre = formData.get('nombre');
            const precio = formData.get('precio');

            if (!nombre.trim()) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'El nombre del producto es obligatorio.',
                    confirmButtonColor: '#dc2626'
                });
                return;
            }

            if (!precio || parseFloat(precio) <= 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'El precio debe ser mayor a 0.',
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
            if (event.key === 'Escape' && modal.classList.contains('modal-show')) {
                cerrarModal();
            }
        });
    </script>
</body>

</html>
