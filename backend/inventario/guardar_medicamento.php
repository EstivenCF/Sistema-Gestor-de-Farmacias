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

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

try {
    $conexion->beginTransaction();
    
    $id_medicamento = $data['id_medicamento'] ?? null;
    $es_nuevo = empty($id_medicamento);
    
    // Construir nombre para el producto
    $nombre_producto = trim($data['nombre']);
    if (!empty($data['concentracion'])) {
        $nombre_producto .= ' ' . $data['concentracion'];
    }
    if (!empty($data['id_unidad'])) {
        $stmt = $conexion->prepare("SELECT abreviatura FROM unidades_medida WHERE id_unidad = ?");
        $stmt->execute([$data['id_unidad']]);
        $unidad = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($unidad) {
            $nombre_producto .= $unidad['abreviatura'];
        }
    }
    if (!empty($data['id_presentacion'])) {
        $stmt = $conexion->prepare("SELECT nombre FROM presentaciones WHERE id_presentacion = ?");
        $stmt->execute([$data['id_presentacion']]);
        $presentacion = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($presentacion) {
            $nombre_producto .= ' ' . $presentacion['nombre'];
        }
    }
    
    if ($es_nuevo) {
        $stmt = $conexion->prepare("
            INSERT INTO productos (nombre, tipo_producto, precio, exento_itbis, estado)
            VALUES (?, 'MEDICAMENTO', ?, ?, true)
            RETURNING id_producto
        ");
        $stmt->execute([$nombre_producto, $data['precio'], $data['exento_itbis']]);
        $id_producto = $stmt->fetch(PDO::FETCH_ASSOC)['id_producto'];
    } else {
        $id_producto = $data['id_producto'];
        if ($id_producto) {
            $stmt = $conexion->prepare("
                UPDATE productos 
                SET nombre = ?, precio = ?, exento_itbis = ?
                WHERE id_producto = ?
            ");
            $stmt->execute([$nombre_producto, $data['precio'], $data['exento_itbis'], $id_producto]);
        } else {
            $stmt = $conexion->prepare("
                INSERT INTO productos (nombre, tipo_producto, precio, exento_itbis, estado)
                VALUES (?, 'MEDICAMENTO', ?, ?, true)
                RETURNING id_producto
            ");
            $stmt->execute([$nombre_producto, $data['precio'], $data['exento_itbis']]);
            $id_producto = $stmt->fetch(PDO::FETCH_ASSOC)['id_producto'];
        }
    }
    
    if ($es_nuevo) {
        $stmt = $conexion->prepare("
            INSERT INTO medicamentos (
                nombre, concentracion, descripcion, id_categoria, requiere_receta,
                stock_minimo, stock_maximo, punto_reorden, proveedor_preferido,
                id_unidad, id_laboratorio, exento_itbis, id_producto, id_presentacion, fecha_registro
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            RETURNING id_medicamento
        ");
        $stmt->execute([
            $data['nombre'],
            $data['concentracion'] ?? null,
            $data['descripcion'] ?? null,
            $data['id_categoria'],
            $data['requiere_receta'],
            $data['stock_minimo'] ?? 5,
            $data['stock_maximo'] ?? null,
            $data['punto_reorden'] ?? 10,
            $data['proveedor_preferido'] ?? null,
            $data['id_unidad'] ?? null,
            $data['id_laboratorio'] ?? null,
            $data['exento_itbis'],
            $id_producto,
            $data['id_presentacion']
        ]);
        $id_medicamento = $stmt->fetch(PDO::FETCH_ASSOC)['id_medicamento'];
    } else {
        $stmt = $conexion->prepare("
            UPDATE medicamentos SET
                nombre = ?,
                concentracion = ?,
                descripcion = ?,
                id_categoria = ?,
                requiere_receta = ?,
                stock_minimo = ?,
                stock_maximo = ?,
                punto_reorden = ?,
                proveedor_preferido = ?,
                id_unidad = ?,
                id_laboratorio = ?,
                exento_itbis = ?,
                id_producto = ?,
                id_presentacion = ?
            WHERE id_medicamento = ?
        ");
        $stmt->execute([
            $data['nombre'],
            $data['concentracion'] ?? null,
            $data['descripcion'] ?? null,
            $data['id_categoria'],
            $data['requiere_receta'],
            $data['stock_minimo'] ?? 5,
            $data['stock_maximo'] ?? null,
            $data['punto_reorden'] ?? 10,
            $data['proveedor_preferido'] ?? null,
            $data['id_unidad'] ?? null,
            $data['id_laboratorio'] ?? null,
            $data['exento_itbis'],
            $id_producto,
            $data['id_presentacion'],
            $id_medicamento
        ]);
    }
    
    $conexion->commit();
    
    echo json_encode([
        'success' => true,
        'id_medicamento' => $id_medicamento,
        'message' => $es_nuevo ? 'Medicamento creado correctamente' : 'Medicamento actualizado correctamente'
    ]);
    
} catch(PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>