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

$id_usuario_sesion = $_SESSION['id_usuario'] ?? $_SESSION['usuario_id'] ?? 1;

$data = json_decode(file_get_contents('php://input'), true);

$id_devolucion = isset($data['id_devolucion']) && $data['id_devolucion'] !== '' && $data['id_devolucion'] !== null ? (int)$data['id_devolucion'] : null;
$id_tipo = isset($data['id_tipo']) ? (int)$data['id_tipo'] : 0;
$fecha = isset($data['fecha']) ? $data['fecha'] : '';
$id_sucursal = isset($data['id_sucursal']) ? (int)$data['id_sucursal'] : 0;
$id_estado = isset($data['id_estado']) ? (int)$data['id_estado'] : 1;
$motivo = trim($data['motivo'] ?? '');
$id_cliente = isset($data['id_cliente']) ? (int)$data['id_cliente'] : null;
$id_proveedor = isset($data['id_proveedor']) ? (int)$data['id_proveedor'] : null;
$monto_reembolso = isset($data['monto_reembolso']) ? (float)$data['monto_reembolso'] : 0;
$detalles = isset($data['detalles']) ? $data['detalles'] : [];

// Validaciones
if (!$id_tipo || !$fecha || !$id_sucursal || empty($motivo)) {
    echo json_encode(['success' => false, 'message' => 'Complete los campos requeridos']);
    exit();
}

if ($id_tipo == 1 && !$id_cliente) {
    echo json_encode(['success' => false, 'message' => 'Seleccione un cliente']);
    exit();
}

if ($id_tipo == 2 && !$id_proveedor) {
    echo json_encode(['success' => false, 'message' => 'Seleccione un proveedor']);
    exit();
}

if (empty($detalles)) {
    echo json_encode(['success' => false, 'message' => 'Agregue al menos un producto']);
    exit();
}

try {
    $conexion->beginTransaction();
    
    // Generar número de documento
    $numero_documento = '';
    $stmt = $conexion->query("SELECT MAX(id_devolucion) as max_id FROM devoluciones");
    $max_id = $stmt->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
    $numero_documento = 'DEV-' . str_pad(($max_id + 1), 6, '0', STR_PAD_LEFT);
    
    if ($id_devolucion) {
        // Actualizar devolución existente
        $sql = "UPDATE devoluciones SET 
                    id_tipo = :id_tipo,
                    fecha_solicitud = :fecha,
                    id_sucursal = :id_sucursal,
                    id_estado = :id_estado,
                    motivo = :motivo,
                    id_cliente = :id_cliente,
                    id_proveedor = :id_proveedor,
                    monto_reembolso = :monto_reembolso
                WHERE id_devolucion = :id_devolucion";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':id_tipo' => $id_tipo,
            ':fecha' => $fecha,
            ':id_sucursal' => $id_sucursal,
            ':id_estado' => $id_estado,
            ':motivo' => $motivo,
            ':id_cliente' => $id_cliente,
            ':id_proveedor' => $id_proveedor,
            ':monto_reembolso' => $monto_reembolso,
            ':id_devolucion' => $id_devolucion
        ]);
        
        // Eliminar detalles antiguos
        $stmt = $conexion->prepare("DELETE FROM detalle_devolucion WHERE id_devolucion = :id_devolucion");
        $stmt->execute([':id_devolucion' => $id_devolucion]);
        
    } else {
        // Insertar nueva devolución
        $sql = "INSERT INTO devoluciones (numero_documento, id_tipo, fecha_solicitud, id_sucursal, id_estado, motivo, id_cliente, id_proveedor, monto_reembolso, id_usuario) 
                VALUES (:numero_documento, :id_tipo, :fecha, :id_sucursal, :id_estado, :motivo, :id_cliente, :id_proveedor, :monto_reembolso, :id_usuario)
                RETURNING id_devolucion";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':numero_documento' => $numero_documento,
            ':id_tipo' => $id_tipo,
            ':fecha' => $fecha,
            ':id_sucursal' => $id_sucursal,
            ':id_estado' => $id_estado,
            ':motivo' => $motivo,
            ':id_cliente' => $id_cliente,
            ':id_proveedor' => $id_proveedor,
            ':monto_reembolso' => $monto_reembolso,
            ':id_usuario' => $id_usuario_sesion
        ]);
        
        $id_devolucion = $stmt->fetch(PDO::FETCH_ASSOC)['id_devolucion'];
    }
    
    // Insertar detalles
    foreach ($detalles as $detalle) {
        $stmt = $conexion->prepare("INSERT INTO detalle_devolucion (id_devolucion, id_lote, cantidad, precio_unitario) 
                                    VALUES (:id_devolucion, :id_lote, :cantidad, :precio_unitario)");
        $stmt->execute([
            ':id_devolucion' => $id_devolucion,
            ':id_lote' => $detalle['id_lote'],
            ':cantidad' => $detalle['cantidad'],
            ':precio_unitario' => $detalle['precio_unitario']
        ]);
        
        // Actualizar inventario (devolución de cliente = entrada, devolución a proveedor = salida)
        if ($id_tipo == 1) { // Devolución de cliente - entra al inventario
            // Obtener sucursal de la devolución
            $stmt = $conexion->prepare("SELECT id_sucursal FROM devoluciones WHERE id_devolucion = :id");
            $stmt->execute([':id' => $id_devolucion]);
            $suc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Sumar al inventario
            $stmt = $conexion->prepare("INSERT INTO inventario (id_lote, id_sucursal, cantidad) 
                                        VALUES (:id_lote, :id_sucursal, :cantidad)
                                        ON CONFLICT (id_lote, id_sucursal) 
                                        DO UPDATE SET cantidad = inventario.cantidad + :cantidad");
            $stmt->execute([
                ':id_lote' => $detalle['id_lote'],
                ':id_sucursal' => $suc['id_sucursal'],
                ':cantidad' => $detalle['cantidad']
            ]);
            
            // Registrar movimiento
            $stmt = $conexion->prepare("INSERT INTO movimiento_inventario (id_lote, id_sucursal, tipo, cantidad, motivo, referencia, id_usuario) 
                                        VALUES (:id_lote, :id_sucursal, 'ENTRADA', :cantidad, 'Devolución de cliente', :referencia, :id_usuario)");
            $stmt->execute([
                ':id_lote' => $detalle['id_lote'],
                ':id_sucursal' => $suc['id_sucursal'],
                ':cantidad' => $detalle['cantidad'],
                ':referencia' => $numero_documento,
                ':id_usuario' => $id_usuario_sesion
            ]);
        } elseif ($id_tipo == 2) { // Devolución a proveedor - sale del inventario
            // Obtener sucursal de la devolución
            $stmt = $conexion->prepare("SELECT id_sucursal FROM devoluciones WHERE id_devolucion = :id");
            $stmt->execute([':id' => $id_devolucion]);
            $suc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Restar del inventario
            $stmt = $conexion->prepare("UPDATE inventario SET cantidad = cantidad - :cantidad 
                                        WHERE id_lote = :id_lote AND id_sucursal = :id_sucursal AND cantidad >= :cantidad");
            $stmt->execute([
                ':id_lote' => $detalle['id_lote'],
                ':id_sucursal' => $suc['id_sucursal'],
                ':cantidad' => $detalle['cantidad']
            ]);
            
            // Registrar movimiento
            $stmt = $conexion->prepare("INSERT INTO movimiento_inventario (id_lote, id_sucursal, tipo, cantidad, motivo, referencia, id_usuario) 
                                        VALUES (:id_lote, :id_sucursal, 'SALIDA', :cantidad, 'Devolución a proveedor', :referencia, :id_usuario)");
            $stmt->execute([
                ':id_lote' => $detalle['id_lote'],
                ':id_sucursal' => $suc['id_sucursal'],
                ':cantidad' => $detalle['cantidad'],
                ':referencia' => $numero_documento,
                ':id_usuario' => $id_usuario_sesion
            ]);
        }
    }
    
    $conexion->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => $id_devolucion ? 'Devolución actualizada correctamente' : 'Devolución creada correctamente',
        'id_devolucion' => $id_devolucion,
        'numero_documento' => $numero_documento
    ]);
    
} catch(PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>