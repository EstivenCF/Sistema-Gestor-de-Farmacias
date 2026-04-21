<?php
require_once '../conexion.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$tipo = $_GET['tipo'] ?? '';
$estado = $_GET['estado'] ?? '';
$sucursal = $_GET['sucursal'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

try {
    $sql = "SELECT COUNT(*) as total FROM devoluciones d WHERE 1=1";
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
    if ($sucursal) {
        $sql .= " AND d.id_sucursal = :sucursal";
        $params[':sucursal'] = $sucursal;
    }
    if ($busqueda) {
        $sql .= " AND (d.numero_documento ILIKE :busqueda)";
        $params[':busqueda'] = "%$busqueda%";
    }

    // Total
    $stmt = $conexion->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $total = $stmt->fetchColumn();

    // Pendientes (estado SOLICITADA = 1)
    $sqlPend = $sql . " AND d.id_estado = 1";
    $stmt = $conexion->prepare($sqlPend);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $pendientes = $stmt->fetchColumn();

    // Completadas (estado COMPLETADA = 4)
    $sqlCom = $sql . " AND d.id_estado = 4";
    $stmt = $conexion->prepare($sqlCom);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $completadas = $stmt->fetchColumn();

    // Rechazadas (estado RECHAZADA = 3)
    $sqlRech = $sql . " AND d.id_estado = 3";
    $stmt = $conexion->prepare($sqlRech);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $rechazadas = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'total' => (int)$total,
        'pendientes' => (int)$pendientes,
        'completadas' => (int)$completadas,
        'rechazadas' => (int)$rechazadas
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en BD: ' . $e->getMessage()]);
}
?>