<?php
require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

$id_proveedor = $data['id_proveedor'] ?? null;
$nombre = trim($data['nombre'] ?? '');
$rnc = !empty($data['rnc']) ? trim($data['rnc']) : null;
$direccion = !empty($data['direccion']) ? trim($data['direccion']) : null;
$telefonos = $data['telefonos'] ?? [];
$correos = $data['correos'] ?? [];

if (empty($nombre)) {
    echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
    exit();
}

try {
    $conexion->beginTransaction();

    // Insertar o actualizar proveedor
    if ($id_proveedor) {
        $stmt = $conexion->prepare("UPDATE proveedores SET nombre = ?, rnc = ?, direccion = ? WHERE id_proveedor = ?");
        $stmt->execute([$nombre, $rnc, $direccion, $id_proveedor]);
    } else {
        $stmt = $conexion->prepare("INSERT INTO proveedores (nombre, rnc, direccion) VALUES (?, ?, ?) RETURNING id_proveedor");
        $stmt->execute([$nombre, $rnc, $direccion]);
        $id_proveedor = $stmt->fetchColumn();
    }

    // Gestionar teléfonos (borrar viejos, insertar nuevos)
    $stmt = $conexion->prepare("DELETE FROM proveedor_telefono WHERE id_proveedor = ?");
    $stmt->execute([$id_proveedor]);

    foreach ($telefonos as $tel) {
        if (empty($tel['numero'])) continue;
        // Insertar teléfono (si no existe, usar ON CONFLICT o buscar)
        $stmt = $conexion->prepare("
            INSERT INTO telefonos (numero, tipo, whatsapp, activo) 
            VALUES (?, ?, ?, true)
            ON CONFLICT (numero) DO UPDATE SET tipo = EXCLUDED.tipo, whatsapp = EXCLUDED.whatsapp, activo = true
            RETURNING id_telefono
        ");
        $stmt->execute([$tel['numero'], $tel['tipo'], $tel['whatsapp'] ?? false]);
        $id_telefono = $stmt->fetchColumn();
        
        $stmt = $conexion->prepare("INSERT INTO proveedor_telefono (id_proveedor, id_telefono) VALUES (?, ?)");
        $stmt->execute([$id_proveedor, $id_telefono]);
    }

    // Gestionar correos
    $stmt = $conexion->prepare("DELETE FROM proveedor_correo WHERE id_proveedor = ?");
    $stmt->execute([$id_proveedor]);

    foreach ($correos as $cor) {
        if (empty($cor['email'])) continue;
        $stmt = $conexion->prepare("
            INSERT INTO correos (email, tipo, activo, verificado) 
            VALUES (?, ?, true, false)
            ON CONFLICT (email) DO UPDATE SET tipo = EXCLUDED.tipo, activo = true
            RETURNING id_correo
        ");
        $stmt->execute([$cor['email'], $cor['tipo']]);
        $id_correo = $stmt->fetchColumn();
        
        $stmt = $conexion->prepare("INSERT INTO proveedor_correo (id_proveedor, id_correo) VALUES (?, ?)");
        $stmt->execute([$id_proveedor, $id_correo]);
    }

    $conexion->commit();
    echo json_encode(['success' => true, 'id_proveedor' => $id_proveedor]);

} catch (PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error de BD: ' . $e->getMessage()]);
} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>