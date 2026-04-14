<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id_cliente']) || !isset($data['productos'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

$id_cliente = $data['id_cliente'];
$productos = $data['productos'];

try {
    $cobertura_total = 0;
    $paga_paciente_total = 0;
    $subtotal_total = 0;
    $requiere_autorizacion = false;
    $autorizaciones_faltantes = [];
    
    foreach ($productos as $producto) {
        $precio = $producto['precio_unitario'];
        $cantidad = $producto['cantidad'];
        $id_medicamento = $producto['id_medicamento'];
        $monto_total = $precio * $cantidad;
        $subtotal_total += $monto_total;
        
        $stmt = $conexion->prepare("
            SELECT * FROM calcular_cobertura_seguro(
                :id_cliente, 
                :id_medicamento, 
                :precio, 
                :cantidad
            )
        ");
        $stmt->execute([
            ':id_cliente' => $id_cliente,
            ':id_medicamento' => $id_medicamento,
            ':precio' => $precio,
            ':cantidad' => $cantidad
        ]);
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado) {
            $cobertura_total += floatval($resultado['cubre_seguro']);
            $paga_paciente_total += floatval($resultado['paga_paciente']);
            
            if ($resultado['requiere_autorizacion'] && !$resultado['id_autorizacion_necesaria']) {
                $requiere_autorizacion = true;
                $autorizaciones_faltantes[] = $producto['nombre'];
            }
        }
    }
    
    $itbis_total = 0;
    foreach ($productos as $producto) {
        if ($producto['aplica_itbis']) {
            $itbis_total += ($producto['precio_unitario'] * $producto['cantidad']) * 0.18;
        }
    }
    
    $total_factura = $subtotal_total + $itbis_total;
    $monto_final_paciente = $paga_paciente_total + $itbis_total;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'subtotal' => $subtotal_total,
            'itbis_total' => $itbis_total,
            'total_factura' => $total_factura,
            'monto_cubre_seguro' => $cobertura_total,
            'monto_paga_paciente' => $monto_final_paciente,
            'requiere_autorizacion' => $requiere_autorizacion,
            'autorizaciones_faltantes' => $autorizaciones_faltantes
        ]
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en BD: ' . $e->getMessage()]);
}
?>