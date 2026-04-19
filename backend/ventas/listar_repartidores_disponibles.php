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

try {
    // Obtener repartidores activos con información de entregas activas y teléfono
    $sql = "SELECT 
                r.id_repartidor,
                r.nombre,
                COUNT(CASE WHEN e.id_estado NOT IN (4,5,7) THEN 1 END) AS entregas_activas,
                MAX(e.fecha_asignada) AS ultima_entrega,
                t.numero AS telefono
            FROM repartidores r
            LEFT JOIN entregas e ON r.id_repartidor = e.id_repartidor 
                AND e.fecha_pedido > CURRENT_DATE - INTERVAL '1 day'
            LEFT JOIN repartidor_telefono rt ON r.id_repartidor = rt.id_repartidor
            LEFT JOIN telefonos t ON rt.id_telefono = t.id_telefono AND t.activo = true
            WHERE r.activo = true
            GROUP BY r.id_repartidor, r.nombre, t.numero
            ORDER BY entregas_activas ASC, ultima_entrega ASC NULLS FIRST";
    
    $stmt = $conexion->query($sql);
    $repartidores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'repartidores' => $repartidores
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
}
?>