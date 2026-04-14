<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

require_once __DIR__ . '/../conexion.php';

$pagina      = (int)($_GET['pagina']      ?? 1);
$limite      = (int)($_GET['limite']      ?? 10);
$busqueda    = $_GET['busqueda']    ?? '';
$categoria   = $_GET['categoria']   ?? '';
$receta      = $_GET['receta']      ?? '';
$presentacion = $_GET['presentacion'] ?? '';
$laboratorio = $_GET['laboratorio'] ?? '';
$proveedor   = $_GET['proveedor']   ?? '';

$offset = ($pagina - 1) * $limite;

try {
    $where  = [];
    $params = [];

    if (!empty($busqueda)) {
        $where[]  = "(m.nombre ILIKE ? OR m.descripcion ILIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }

    if (!empty($categoria)) {
        $where[]  = "m.id_categoria = ?";
        $params[] = (int)$categoria;
    }

    // ── FIX: PostgreSQL no acepta PHP false/true como boolean via PDO bind.
    //    Se inserta el literal TRUE/FALSE directamente en el SQL (seguro porque
    //    solo puede ser '1' o '0', nunca input libre del usuario).
    if ($receta === '1' || $receta === '0') {
        $boolLiteral = ($receta === '1') ? 'TRUE' : 'FALSE';
        $where[] = "m.requiere_receta = $boolLiteral";
        // NO se agrega nada a $params para esta condición
    }

    if (!empty($presentacion)) {
        $where[]  = "m.id_presentacion = ?";
        $params[] = (int)$presentacion;
    }

    if (!empty($laboratorio)) {
        $where[]  = "m.id_laboratorio = ?";
        $params[] = (int)$laboratorio;
    }

    // ── Filtro por proveedor: la columna es proveedor_preferido
    if (!empty($proveedor)) {
        $where[]  = "m.proveedor_preferido = ?";
        $params[] = (int)$proveedor;
    }

    $whereClause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);

    // ── Consulta principal
    $sql = "
        SELECT
            m.id_medicamento,
            m.nombre,
            m.concentracion,
            m.descripcion,
            m.requiere_receta,
            m.exento_itbis,
            m.stock_minimo,
            m.stock_maximo,
            m.punto_reorden,
            m.fecha_registro,
            c.id_categoria,
            COALESCE(c.nombre,    '')  AS categoria_nombre,
            l.id_laboratorio,
            COALESCE(l.nombre,    '')  AS laboratorio_nombre,
            p.id_presentacion,
            COALESCE(p.nombre,    '')  AS presentacion,
            u.id_unidad,
            COALESCE(u.nombre,    '')  AS unidad_nombre,
            COALESCE(u.abreviatura,'') AS unidad_abrev,
            prov.id_proveedor,
            COALESCE(prov.nombre, '')  AS proveedor_nombre,
            COALESCE(prod.precio, 0)   AS precio,
            prod.id_producto
        FROM medicamentos m
        LEFT JOIN categorias       c    ON m.id_categoria       = c.id_categoria
        LEFT JOIN laboratorios     l    ON m.id_laboratorio     = l.id_laboratorio
        LEFT JOIN presentaciones   p    ON m.id_presentacion    = p.id_presentacion
        LEFT JOIN unidades_medida  u    ON m.id_unidad          = u.id_unidad
        LEFT JOIN proveedores      prov ON m.proveedor_preferido = prov.id_proveedor
        LEFT JOIN productos        prod ON m.id_producto         = prod.id_producto
        $whereClause
        ORDER BY m.nombre ASC, m.concentracion ASC
        LIMIT ? OFFSET ?
    ";

    $paramsConPaginacion = array_merge($params, [$limite, $offset]);

    $stmt = $conexion->prepare($sql);
    $stmt->execute($paramsConPaginacion);
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Consulta de conteo (mismos parámetros sin paginación)
    $countSql = "SELECT COUNT(*) AS total FROM medicamentos m
        LEFT JOIN proveedores prov ON m.proveedor_preferido = prov.id_proveedor
        $whereClause";
    $stmtCount = $conexion->prepare($countSql);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode([
        'success'      => true,
        'medicamentos' => $medicamentos,
        'total'        => $total,
        'pagina'       => $pagina,
        'limite'       => $limite
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>