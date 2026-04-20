<?php
require_once __DIR__ . '/../conexion.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['id_compra']) || empty($data['productos'])) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

$id_compra = (int)$data['id_compra'];
$productos = $data['productos'];
$id_usuario = $_SESSION['id_usuario'] ?? 1;

try {
    $conexion->beginTransaction();

    $stmt = $conexion->prepare("SELECT id_sucursal FROM compras WHERE id_compra = :id");
    $stmt->execute([':id' => $id_compra]);
    $id_sucursal = $stmt->fetchColumn();
    if (!$id_sucursal) throw new Exception("Compra no encontrada");

    $todo_recibido = true;
    $parcial = false;

    foreach ($productos as $prod) {
        $id_detalle = $prod['id_detalle'];
        $tipo = $prod['tipo'];
        $cantidad_a_recibir = (int)$prod['cantidad_recibida'];
        
        if ($cantidad_a_recibir <= 0) continue;

        // Validar pendiente
        $stmt = $conexion->prepare("SELECT cantidad, COALESCE(cantidad_recibida, 0) as recibida FROM detalle_compra WHERE id_detalle = :det");
        $stmt->execute([':det' => $id_detalle]);
        $det = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$det) throw new Exception("Detalle no encontrado");
        
        $cantidad_total = $det['cantidad'];
        $recibido_hasta_ahora = $det['recibida'];
        $pendiente = $cantidad_total - $recibido_hasta_ahora;
        
        if ($cantidad_a_recibir > $pendiente) {
            throw new Exception("No puede recibir más de lo pendiente (máximo $pendiente)");
        }
        
        if ($tipo === 'MEDICAMENTO') {
            $id_medicamento = $prod['id_medicamento'];
            $numero_lote = $prod['numero_lote'];
            $fecha_vencimiento = $prod['fecha_vencimiento'];
            $costo_unitario = $prod['costo_unitario'];

            $stmt = $conexion->prepare("SELECT id_lote FROM lotes WHERE numero_lote = :lote");
            $stmt->execute([':lote' => $numero_lote]);
            $id_lote = $stmt->fetchColumn();

            if (!$id_lote) {
                $stmt = $conexion->prepare("INSERT INTO lotes (id_medicamento, numero_lote, fecha_vencimiento, cantidad_inicial, cantidad_actual, costo_unitario, estado, fecha_registro, registro_por)
                                            VALUES (:med, :lote, :venc, :cant, :cant, :costo, 'ACTIVO', NOW(), :user) RETURNING id_lote");
                $stmt->execute([
                    ':med' => $id_medicamento,
                    ':lote' => $numero_lote,
                    ':venc' => $fecha_vencimiento,
                    ':cant' => $cantidad_a_recibir,
                    ':costo' => $costo_unitario,
                    ':user' => $id_usuario
                ]);
                $id_lote = $stmt->fetchColumn();
            } else {
                $stmt = $conexion->prepare("UPDATE lotes SET cantidad_actual = cantidad_actual + :cant WHERE id_lote = :id");
                $stmt->execute([':cant' => $cantidad_a_recibir, ':id' => $id_lote]);
            }

            $stmt = $conexion->prepare("INSERT INTO inventario (id_lote, id_sucursal, cantidad) VALUES (:lote, :suc, :cant)
                                        ON CONFLICT (id_lote, id_sucursal) DO UPDATE SET cantidad = inventario.cantidad + :cant");
            $stmt->execute([':lote' => $id_lote, ':suc' => $id_sucursal, ':cant' => $cantidad_a_recibir]);

            $stmt = $conexion->prepare("INSERT INTO movimiento_inventario (id_lote, id_sucursal, tipo, cantidad, motivo, referencia, id_usuario, fecha)
                                        VALUES (:lote, :suc, 'ENTRADA', :cant, 'Recepción de compra', :ref, :user, NOW())");
            $stmt->execute([
                ':lote' => $id_lote,
                ':suc' => $id_sucursal,
                ':cant' => $cantidad_a_recibir,
                ':ref' => "COMPRA-$id_compra",
                ':user' => $id_usuario
            ]);

            $stmt = $conexion->prepare("UPDATE detalle_compra 
                                        SET cantidad_recibida = COALESCE(cantidad_recibida, 0) + :rec,
                                            id_lote = COALESCE(id_lote, :lote)
                                        WHERE id_detalle = :det");
            $stmt->execute([
                ':rec' => $cantidad_a_recibir,
                ':lote' => $id_lote,
                ':det' => $id_detalle
            ]);
            
        } else if ($tipo === 'ROPA') {
            $id_producto = $prod['id_producto'];
            $id_talla = !empty($prod['id_talla']) ? (int)$prod['id_talla'] : null;
            $id_color = !empty($prod['id_color']) ? (int)$prod['id_color'] : null;
            
            if (empty($id_talla) && empty($id_color)) {
                $stmtGet = $conexion->prepare("SELECT id_talla, id_color FROM detalle_compra WHERE id_detalle = :det");
                $stmtGet->execute([':det' => $id_detalle]);
                $detalleInfo = $stmtGet->fetch(PDO::FETCH_ASSOC);
                if ($detalleInfo) {
                    $id_talla = $detalleInfo['id_talla'];
                    $id_color = $detalleInfo['id_color'];
                }
            }
            
            $stmt = $conexion->prepare("
                SELECT id_inventario, cantidad 
                FROM inventario_productos 
                WHERE id_producto = :prod 
                AND id_sucursal = :suc 
                AND (id_talla = :talla OR (:talla IS NULL AND id_talla IS NULL))
                AND (id_color = :color OR (:color IS NULL AND id_color IS NULL))
            ");
            $stmt->execute([
                ':prod' => $id_producto,
                ':suc' => $id_sucursal,
                ':talla' => $id_talla,
                ':color' => $id_color
            ]);
            $inventarioExistente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($inventarioExistente) {
                $stmt = $conexion->prepare("UPDATE inventario_productos SET cantidad = cantidad + :cant WHERE id_inventario = :id");
                $stmt->execute([
                    ':cant' => $cantidad_a_recibir,
                    ':id' => $inventarioExistente['id_inventario']
                ]);
            } else {
                $stmt = $conexion->prepare("
                    INSERT INTO inventario_productos (id_producto, id_sucursal, id_talla, id_color, cantidad)
                    VALUES (:prod, :suc, :talla, :color, :cant)
                ");
                $stmt->execute([
                    ':prod' => $id_producto,
                    ':suc' => $id_sucursal,
                    ':talla' => $id_talla,
                    ':color' => $id_color,
                    ':cant' => $cantidad_a_recibir
                ]);
            }
            
            $stmt = $conexion->prepare("
                INSERT INTO movimiento_inventario_productos (id_producto, id_sucursal, id_talla, id_color, tipo, cantidad, motivo, referencia, id_usuario, fecha)
                VALUES (:prod, :suc, :talla, :color, 'ENTRADA', :cant, 'Recepción de compra', :ref, :user, NOW())
            ");
            $stmt->execute([
                ':prod' => $id_producto,
                ':suc' => $id_sucursal,
                ':talla' => $id_talla,
                ':color' => $id_color,
                ':cant' => $cantidad_a_recibir,
                ':ref' => "COMPRA-$id_compra",
                ':user' => $id_usuario
            ]);
            
            $stmt = $conexion->prepare("UPDATE detalle_compra SET cantidad_recibida = COALESCE(cantidad_recibida, 0) + :rec WHERE id_detalle = :det");
            $stmt->execute([
                ':rec' => $cantidad_a_recibir,
                ':det' => $id_detalle
            ]);
        }
        
        $nuevo_recibido = $recibido_hasta_ahora + $cantidad_a_recibir;
        if ($nuevo_recibido < $cantidad_total) {
            $parcial = true;
            $todo_recibido = false;
        }
    }

    if ($todo_recibido) {
        $nuevo_estado = 'COMPLETADA';
    } elseif ($parcial) {
        $nuevo_estado = 'PARCIAL';
    } else {
        $nuevo_estado = 'PENDIENTE';
    }

    $stmt = $conexion->prepare("UPDATE compras SET id_estado = (SELECT id_estado FROM estado_compra WHERE nombre = :estado) WHERE id_compra = :id");
    $stmt->execute([':estado' => $nuevo_estado, ':id' => $id_compra]);

    $conexion->commit();
    echo json_encode(['success' => true, 'message' => 'Recepción procesada correctamente']);

} catch (Exception $e) {
    if (isset($conexion)) $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    if (isset($conexion)) $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>