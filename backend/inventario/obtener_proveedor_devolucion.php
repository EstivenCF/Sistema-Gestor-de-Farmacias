<?php
/**
 * Tarea 5 - Proceso estratégico: Gestión Estratégica de Vencimientos
 * Resuelve, para un lote dado, el/los proveedor(es) reales que lo
 * suministraron (lotes -> detalle_compra -> compras -> proveedores) y, para
 * cada uno, el catálogo de motivos de devolución que tiene pactados
 * (proveedor_motivo_devolucion).
 *
 * Se usa desde la Pantalla #04 al elegir el tipo de acción "Devolución":
 * el proveedor y el combo de motivos ya NO se escogen libremente de toda la
 * lista de proveedores del sistema, se derivan de la compra de origen real.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['usuario']) && !isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

require_once __DIR__ . '/../conexion.php';

$id_lote = isset($_GET['id_lote']) ? (int)$_GET['id_lote'] : 0;
if (!$id_lote) {
    echo json_encode(['success' => false, 'message' => 'Falta id_lote']);
    exit();
}

try {
    // Proveedores reales que aparecen en el historial de compra de este lote.
    // Un lote normalmente proviene de una sola compra/proveedor, pero se
    // contempla el caso de más de uno (ajustes, reposiciones registradas
    // bajo el mismo número de lote) sin asumir de más.
    $stmt = $conexion->prepare("
        SELECT DISTINCT p.id_proveedor, p.nombre
        FROM detalle_compra dc
        JOIN compras c ON dc.id_compra = c.id_compra
        JOIN proveedores p ON c.id_proveedor = p.id_proveedor
        WHERE dc.id_lote = :id_lote
        ORDER BY p.nombre
    ");
    $stmt->execute([':id_lote' => $id_lote]);
    $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($proveedores)) {
        echo json_encode([
            'success' => false,
            'message' => 'Este lote no tiene una compra de origen registrada; no se puede tramitar una devolución a proveedor.'
        ]);
        exit();
    }

    // Motivos aceptados por cada proveedor candidato
    $stmtMotivos = $conexion->prepare("
        SELECT md.id_motivo, md.nombre, md.descripcion
        FROM proveedor_motivo_devolucion pmd
        JOIN motivo_devolucion md ON pmd.id_motivo = md.id_motivo
        WHERE pmd.id_proveedor = :id_proveedor AND md.activo = true
        ORDER BY md.nombre
    ");

    foreach ($proveedores as &$prov) {
        $stmtMotivos->execute([':id_proveedor' => $prov['id_proveedor']]);
        $prov['motivos'] = $stmtMotivos->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($prov);

    echo json_encode(['success' => true, 'proveedores' => $proveedores]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
