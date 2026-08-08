<?php
require_once __DIR__ . '/../conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_cliente = isset($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : 0;
if (!$id_cliente) {
    echo json_encode(['success' => false, 'message' => 'ID de cliente inválido']);
    exit;
}

try {
    $sql = "SELECT 
                d.id_direccion,
                d.direccion,
                d.barrio,
                d.ciudad,
                d.referencia,
                d.latitud,
                d.longitud,
                COALESCE(d.direccion || ', ' || d.barrio || ', ' || d.ciudad, d.direccion) AS direccion_completa,
                cd.predeterminada
            FROM direcciones d
            INNER JOIN cliente_direccion cd ON d.id_direccion = cd.id_direccion
            WHERE cd.id_cliente = :id_cliente
              AND d.activo = true
            ORDER BY cd.predeterminada DESC, d.direccion ASC";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id_cliente' => $id_cliente]);
    $direcciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'direcciones' => $direcciones
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
}
?>