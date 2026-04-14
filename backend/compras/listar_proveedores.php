<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$limite = isset($_GET['limite']) ? intval($_GET['limite']) : 10;
$offset = ($pagina - 1) * $limite;

$busqueda = $_GET['busqueda'] ?? '';
$rnc = $_GET['rnc'] ?? '';
$telefono = $_GET['telefono'] ?? '';
$email = $_GET['email'] ?? '';

try {
    // Consulta para contar total
    $countSql = "SELECT COUNT(DISTINCT p.id_proveedor) as total FROM proveedores p WHERE 1=1";
    $where = "";
    $params = [];

    if (!empty($busqueda)) {
        $where .= " AND (p.nombre ILIKE :busqueda OR p.rnc ILIKE :busqueda)";
        $params[':busqueda'] = "%$busqueda%";
    }
    if (!empty($rnc)) {
        $where .= " AND p.rnc ILIKE :rnc";
        $params[':rnc'] = "%$rnc%";
    }
    if (!empty($telefono)) {
        $where .= " AND EXISTS (SELECT 1 FROM proveedor_telefono pt JOIN telefonos t ON pt.id_telefono = t.id_telefono WHERE pt.id_proveedor = p.id_proveedor AND t.numero ILIKE :telefono)";
        $params[':telefono'] = "%$telefono%";
    }
    if (!empty($email)) {
        $where .= " AND EXISTS (SELECT 1 FROM proveedor_correo pc JOIN correos c ON pc.id_correo = c.id_correo WHERE pc.id_proveedor = p.id_proveedor AND c.email ILIKE :email)";
        $params[':email'] = "%$email%";
    }

    $stmt = $conexion->prepare($countSql . $where);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    // Consulta para datos
    $sql = "
        SELECT 
            p.id_proveedor,
            p.nombre,
            p.rnc,
            p.direccion,
            COALESCE(
                (SELECT json_agg(json_build_object('numero', t.numero, 'tipo', t.tipo, 'whatsapp', t.whatsapp))
                 FROM proveedor_telefono pt
                 JOIN telefonos t ON pt.id_telefono = t.id_telefono
                 WHERE pt.id_proveedor = p.id_proveedor AND t.activo = true), '[]'::json
            ) as telefonos,
            COALESCE(
                (SELECT json_agg(json_build_object('email', c.email, 'tipo', c.tipo))
                 FROM proveedor_correo pc
                 JOIN correos c ON pc.id_correo = c.id_correo
                 WHERE pc.id_proveedor = p.id_proveedor AND c.activo = true), '[]'::json
            ) as correos
        FROM proveedores p
        WHERE 1=1 $where
        ORDER BY p.nombre ASC
        LIMIT :limite OFFSET :offset
    ";

    $stmt = $conexion->prepare($sql);
    foreach ($params as $key => $val) {
        if ($key !== ':limite' && $key !== ':offset') {
            $stmt->bindValue($key, $val);
        }
    }
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decodificar JSON
    foreach ($proveedores as &$p) {
        $p['telefonos'] = json_decode($p['telefonos'], true);
        $p['correos'] = json_decode($p['correos'], true);
    }

    echo json_encode([
        'success' => true,
        'proveedores' => $proveedores,
        'total' => intval($total),
        'pagina' => $pagina,
        'limite' => $limite
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>