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

// Resolver id_usuario compatible con cualquier estructura de sesión
$id_usuario_sesion = $_SESSION['id_usuario']
    ?? $_SESSION['usuario_id']
    ?? (is_array($_SESSION['usuario'] ?? null) ? ($_SESSION['usuario']['id_usuario'] ?? null) : null)
    ?? 1;

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

$id_lote = isset($data['id_lote']) && $data['id_lote'] !== '' && $data['id_lote'] !== null ? (int)$data['id_lote'] : null;
$id_medicamento = isset($data['id_medicamento']) ? (int)$data['id_medicamento'] : 0;
$numero_lote = trim($data['numero_lote'] ?? '');
$fecha_vencimiento = trim($data['fecha_vencimiento'] ?? '');
$cantidad_inicial = isset($data['cantidad_inicial']) && $data['cantidad_inicial'] !== '' ? (int)$data['cantidad_inicial'] : 0;
$costo_lote = isset($data['costo_lote']) && $data['costo_lote'] !== '' ? (float)$data['costo_lote'] : null;
$codigo_barras = trim($data['codigo_barras'] ?? '');
$ubicacion = trim($data['ubicacion'] ?? '');
$observaciones = trim($data['observaciones'] ?? '');
$estado = trim($data['estado'] ?? 'ACTIVO');
$motivo_cambio = trim($data['motivo_cambio'] ?? '');
$id_sucursal = isset($data['id_sucursal']) ? (int)$data['id_sucursal'] : 0;

// ==================== VALIDACIONES BÁSICAS ====================
if (!$id_medicamento) {
    echo json_encode(['success' => false, 'message' => 'Debe seleccionar un medicamento']);
    exit();
}
if (empty($numero_lote)) {
    echo json_encode(['success' => false, 'message' => 'El número de lote es requerido']);
    exit();
}
if (empty($fecha_vencimiento)) {
    echo json_encode(['success' => false, 'message' => 'La fecha de vencimiento es requerida']);
    exit();
}
if ($cantidad_inicial <= 0 && !$id_lote) {
    echo json_encode(['success' => false, 'message' => 'La cantidad inicial debe ser mayor a 0']);
    exit();
}
if ($costo_lote <= 0 && !$id_lote) {
    echo json_encode(['success' => false, 'message' => 'El costo del lote debe ser mayor a 0']);
    exit();
}
if (!$id_sucursal && !$id_lote) {
    echo json_encode(['success' => false, 'message' => 'Debe seleccionar una sucursal destino']);
    exit();
}

try {
    $conexion->beginTransaction();

    // ==================== SI VIENE ID, VALIDAR QUE EXISTA ====================
    if ($id_lote !== null) {
        $stmt = $conexion->prepare("SELECT id_lote FROM lotes WHERE id_lote = :id");
        $stmt->execute([':id' => $id_lote]);
        if (!$stmt->fetch()) {
            // El ID enviado no existe, lo tratamos como nuevo lote
            $id_lote = null;
        }
    }

    if ($id_lote) {
        // ==================== ACTUALIZAR LOTE EXISTENTE ====================
        $stmt = $conexion->prepare("SELECT estado FROM lotes WHERE id_lote = :id");
        $stmt->execute([':id' => $id_lote]);
        $estado_anterior = $stmt->fetchColumn();
        
        if ($estado_anterior !== $estado && empty($motivo_cambio)) {
            $conexion->rollBack();
            echo json_encode(['success' => false, 'message' => 'Debe especificar un motivo para cambiar el estado del lote']);
            exit();
        }
        
        $sql = "UPDATE lotes SET 
                    id_medicamento = :id_medicamento,
                    numero_lote = :numero_lote,
                    fecha_vencimiento = :fecha_vencimiento,
                    codigo_barras = :codigo_barras,
                    ubicacion = :ubicacion,
                    observaciones = :observaciones,
                    estado = :estado
                WHERE id_lote = :id";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':id_medicamento' => $id_medicamento,
            ':numero_lote' => $numero_lote,
            ':fecha_vencimiento' => $fecha_vencimiento,
            ':codigo_barras' => $codigo_barras,
            ':ubicacion' => $ubicacion,
            ':observaciones' => $observaciones,
            ':estado' => $estado,
            ':id' => $id_lote
        ]);
        
        if ($estado_anterior !== $estado) {
            $sqlHist = "INSERT INTO historial_estado_lote (id_lote, estado_anterior, estado_nuevo, motivo, id_usuario, observaciones) 
                        VALUES (:id_lote, :estado_anterior, :estado_nuevo, :motivo, :id_usuario, :observaciones)";
            $stmtHist = $conexion->prepare($sqlHist);
            $stmtHist->execute([
                ':id_lote'         => $id_lote,
                ':estado_anterior' => $estado_anterior,
                ':estado_nuevo'    => $estado,
                ':motivo'          => $motivo_cambio,
                ':id_usuario'      => $id_usuario_sesion,
                ':observaciones'   => $observaciones
            ]);
        }
        
        $conexion->commit();
        echo json_encode(['success' => true, 'message' => 'Lote actualizado correctamente']);
        
    } else {
        // ==================== CREAR NUEVO LOTE ====================
        // Verificar si ya existe el número de lote
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM lotes WHERE numero_lote = :numero_lote");
        $stmt->execute([':numero_lote' => $numero_lote]);
        if ($stmt->fetchColumn() > 0) {
            $conexion->rollBack();
            echo json_encode(['success' => false, 'message' => 'Ya existe un lote con ese número']);
            exit();
        }
        
        // Insertar lote con RETURNING para obtener el ID real
        $sql = "INSERT INTO lotes (id_medicamento, numero_lote, fecha_vencimiento, cantidad_inicial, cantidad_actual, costo_lote, codigo_barras, ubicacion, observaciones, estado, fecha_registro, registro_por) 
                VALUES (:id_medicamento, :numero_lote, :fecha_vencimiento, :cantidad_inicial, :cantidad_inicial, :costo_lote, :codigo_barras, :ubicacion, :observaciones, :estado, CURRENT_TIMESTAMP, :registro_por)
                RETURNING id_lote";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':id_medicamento' => $id_medicamento,
            ':numero_lote' => $numero_lote,
            ':fecha_vencimiento' => $fecha_vencimiento,
            ':cantidad_inicial' => $cantidad_inicial,
            ':costo_lote' => $costo_lote,
            ':codigo_barras' => $codigo_barras,
            ':ubicacion' => $ubicacion,
            ':observaciones' => $observaciones,
            ':estado' => $estado,
            ':registro_por' => $id_usuario_sesion
        ]);
        
        $id_lote_nuevo = $stmt->fetchColumn(); // ID real, solo si el INSERT tuvo éxito
        
        if (!$id_lote_nuevo) {
            throw new Exception("No se pudo obtener el ID del lote recién insertado");
        }
        
        // Insertar inventario
        $sqlInv = "INSERT INTO inventario (id_lote, id_sucursal, cantidad) 
                   VALUES (:id_lote, :id_sucursal, :cantidad)";
        $stmtInv = $conexion->prepare($sqlInv);
        $stmtInv->execute([
            ':id_lote' => $id_lote_nuevo,
            ':id_sucursal' => $id_sucursal,
            ':cantidad' => $cantidad_inicial
        ]);
        
        // Registrar movimiento de entrada
        $sqlMov = "INSERT INTO movimiento_inventario (id_lote, id_sucursal, tipo, cantidad, motivo, referencia, id_usuario, fecha) 
                   VALUES (:id_lote, :id_sucursal, 'ENTRADA', :cantidad, 'Creación de lote', 'Nuevo lote', :id_usuario, CURRENT_TIMESTAMP)";
        $stmtMov = $conexion->prepare($sqlMov);
        $stmtMov->execute([
            ':id_lote' => $id_lote_nuevo,
            ':id_sucursal' => $id_sucursal,
            ':cantidad' => $cantidad_inicial,
            ':id_usuario' => $id_usuario_sesion
        ]);
        
        $conexion->commit();
        echo json_encode(['success' => true, 'message' => 'Lote creado correctamente con ' . $cantidad_inicial . ' unidades en la sucursal seleccionada']);
    }
    
} catch(PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error en BD: ' . $e->getMessage()]);
} catch(Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>