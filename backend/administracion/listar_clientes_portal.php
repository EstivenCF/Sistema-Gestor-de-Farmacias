<?php
// backend/administracion/listar_clientes_portal.php
// NUEVO — lista todos los clientes que tienen acceso al portal de
// seguimiento, para poder gestionarlos desde una pantalla propia.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

try {
    $stmt = $conexion->query("
        SELECT
            c.id_cliente, c.nombre, c.usuario_portal, c.fecha_registro,
            t.numero AS telefono,
            (SELECT COUNT(*) FROM entregas e WHERE e.id_cliente = c.id_cliente) AS total_pedidos
        FROM clientes c
        LEFT JOIN cliente_telefono ct ON ct.id_cliente = c.id_cliente
        LEFT JOIN telefonos t ON t.id_telefono = ct.id_telefono AND t.tipo = 'PRINCIPAL'
        WHERE c.usuario_portal IS NOT NULL
        GROUP BY c.id_cliente, c.nombre, c.usuario_portal, c.fecha_registro, t.numero
        ORDER BY c.nombre
    ");
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'clientes' => $clientes, 'total' => count($clientes)]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
