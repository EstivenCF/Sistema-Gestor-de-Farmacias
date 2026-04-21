<?php
require_once '../conexion.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$pagina = (int)($_GET['pagina'] ?? 1);
$limite = (int)($_GET['limite'] ?? 10);
$offset = ($pagina - 1) * $limite;

$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$tipo = $_GET['tipo'] ?? '';
$estado = $_GET['estado'] ?? '';
$sucursal = $_GET['sucursal'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

try {
    // Construcción de la consulta base
    $sql = "
        SELECT 
            d.id_devolucion,
            d.numero_documento,
            d.fecha_solicitud,
            d.motivo,
            d.monto_reembolso,
            td.nombre as tipo_nombre,
            ed.nombre as estado_nombre,
            s.nombre as sucursal_nombre,
            c.nombre as cliente_nombre,
            p.nombre as proveedor_nombre
        FROM devoluciones d
        LEFT JOIN tipo_devolucion td ON d.id_tipo = td.id_tipo
        LEFT JOIN estado_devolucion ed ON d.id_estado = ed.id_estado
        LEFT JOIN sucursales s ON d.id_sucursal = s.id_sucursal
        LEFT JOIN clientes c ON d.id_cliente = c.id_cliente
        LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor
        WHERE 1=1
    ";
    $params = [];

    if ($fecha_desde && $fecha_hasta) {
        $sql .= " AND DATE(d.fecha_solicitud) BETWEEN :fecha_desde AND :fecha_hasta";
        $params[':fecha_desde'] = $fecha_desde;
        $params[':fecha_hasta'] = $fecha_hasta;
    } elseif ($fecha_desde) {
        $sql .= " AND DATE(d.fecha_solicitud) >= :fecha_desde";
        $params[':fecha_desde'] = $fecha_desde;
    } elseif ($fecha_hasta) {
        $sql .= " AND DATE(d.fecha_solicitud) <= :fecha_hasta";
        $params[':fecha_hasta'] = $fecha_hasta;
    }

    if ($tipo) {
        $sql .= " AND d.id_tipo = :tipo";
        $params[':tipo'] = $tipo;
    }
    if ($estado) {
        $sql .= " AND d.id_estado = :estado";
        $params[':estado'] = $estado;
    }
    if ($sucursal) {
        $sql .= " AND d.id_sucursal = :sucursal";
        $params[':sucursal'] = $sucursal;
    }
    if ($busqueda) {
        $sql .= " AND (d.numero_documento ILIKE :busqueda OR c.nombre ILIKE :busqueda OR p.nombre ILIKE :busqueda)";
        $params[':busqueda'] = "%$busqueda%";
    }

    // Consulta para contar total (sin paginación)
    $sqlCount = "SELECT COUNT(*) as total FROM (" . $sql . ") as sub";
    $stmtCount = $conexion->prepare($sqlCount);
    foreach ($params as $key => $value) {
        $stmtCount->bindValue($key, $value);
    }
    $stmtCount->execute();
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

    // Agregar orden y paginación
    $sql .= " ORDER BY d.fecha_solicitud DESC LIMIT :limite OFFSET :offset";
    $params[':limite'] = $limite;
    $params[':offset'] = $offset;

    $stmt = $conexion->prepare($sql);
    foreach ($params as $key => $value) {
        if ($key == ':limite' || $key == ':offset') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();
    $devoluciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'devoluciones' => $devoluciones,
        'total' => (int)$total,
        'pagina' => $pagina,
        'limite' => $limite
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en BD: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>