<?php
header("Content-Type: application/json");
include(__DIR__ . "/../../backend/conexion.php");

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
    exit();
}

$campo = $_GET['campo'] ?? 'nombre';
$busqueda = trim($_GET['busqueda'] ?? '');
$page = intval($_GET['page'] ?? 1);

$limit = 10;
$offset = ($page - 1) * $limit;

$campos_validos = ['id_usuario','nombre','usuario','id_rol','estado'];

if (!in_array($campo, $campos_validos)) {
    $campo = 'nombre';
}

$query = "SELECT id_usuario,nombre,usuario,id_rol,id_sucursal,estado FROM usuarios WHERE 1=1";
$params = [];

if ($busqueda !== "") {
    $query .= " AND {$campo}::text ILIKE ?";
    $params[] = "%$busqueda%";
}

$stmtTotal = $pdo->prepare($query);
$stmtTotal->execute($params);
$total_rows = $stmtTotal->rowCount();
$total_pages = ceil($total_rows / $limit);

$query .= " ORDER BY id_usuario ASC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);

$data = array_map(function($u){
    $u['estado_texto'] = $u['estado'] ? 'Si' : 'No';
    return $u;
}, $stmt->fetchAll(PDO::FETCH_ASSOC));

echo json_encode([
    "data"=>$data,
    "page"=>$page,
    "pages"=>$total_pages
]);
?>