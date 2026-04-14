<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID requerido']);
    exit();
}

try {
    $stmt = $conexion->prepare("
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
        WHERE p.id_proveedor = ?
    ");
    $stmt->execute([$id]);
    $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proveedor) {
        echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
        exit();
    }

    $proveedor['telefonos'] = json_decode($proveedor['telefonos'], true);
    $proveedor['correos'] = json_decode($proveedor['correos'], true);

    echo json_encode(['success' => true, 'proveedor' => $proveedor]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>