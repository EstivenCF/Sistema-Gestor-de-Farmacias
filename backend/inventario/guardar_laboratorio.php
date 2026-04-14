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

$id_laboratorio = isset($data['id_laboratorio']) && $data['id_laboratorio'] !== '' && $data['id_laboratorio'] !== null ? (int)$data['id_laboratorio'] : null;
$nombre = trim($data['nombre'] ?? '');
$pais = trim($data['pais'] ?? '');
$direccion = trim($data['direccion'] ?? '');
$telefono = trim($data['telefono'] ?? '');
$telefono2 = trim($data['telefono2'] ?? '');
$email = trim($data['email'] ?? '');
$email2 = trim($data['email2'] ?? '');
$contacto_nombre = trim($data['contacto_nombre'] ?? '');
$contacto_telefono = trim($data['contacto_telefono'] ?? '');
$website = trim($data['website'] ?? '');
$descripcion = trim($data['descripcion'] ?? '');
$activo = isset($data['activo']) ? (int)$data['activo'] : 1;

// ==================== VALIDACIONES OBLIGATORIAS ====================
if (empty($nombre)) {
    echo json_encode(['success' => false, 'message' => 'El nombre del laboratorio es requerido']);
    exit();
}

if (empty($pais)) {
    echo json_encode(['success' => false, 'message' => 'El país del laboratorio es requerido']);
    exit();
}

if (empty($direccion)) {
    echo json_encode(['success' => false, 'message' => 'La dirección del laboratorio es requerida']);
    exit();
}

if (empty($telefono) && empty($telefono2)) {
    echo json_encode(['success' => false, 'message' => 'Debe especificar al menos un número de teléfono']);
    exit();
}

try {
    if ($id_laboratorio) {
        // Actualizar
        $sql = "UPDATE laboratorios SET 
                    nombre = :nombre, 
                    pais = :pais, 
                    direccion = :direccion, 
                    telefono = :telefono,
                    telefono2 = :telefono2,
                    email = :email,
                    email2 = :email2,
                    contacto_nombre = :contacto_nombre,
                    contacto_telefono = :contacto_telefono,
                    website = :website,
                    descripcion = :descripcion,
                    activo = :activo
                WHERE id_laboratorio = :id";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre,
            ':pais' => $pais,
            ':direccion' => $direccion,
            ':telefono' => $telefono,
            ':telefono2' => $telefono2,
            ':email' => $email,
            ':email2' => $email2,
            ':contacto_nombre' => $contacto_nombre,
            ':contacto_telefono' => $contacto_telefono,
            ':website' => $website,
            ':descripcion' => $descripcion,
            ':activo' => $activo,
            ':id' => $id_laboratorio
        ]);
        echo json_encode(['success' => true, 'message' => 'Laboratorio actualizado correctamente']);
    } else {
        // Verificar si ya existe
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM laboratorios WHERE nombre ILIKE :nombre");
        $stmt->execute([':nombre' => $nombre]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Ya existe un laboratorio con ese nombre']);
            exit();
        }
        
        // Insertar nuevo
        $sql = "INSERT INTO laboratorios (nombre, pais, direccion, telefono, telefono2, email, email2, contacto_nombre, contacto_telefono, website, descripcion, activo, fecha_registro) 
                VALUES (:nombre, :pais, :direccion, :telefono, :telefono2, :email, :email2, :contacto_nombre, :contacto_telefono, :website, :descripcion, :activo, CURRENT_TIMESTAMP)";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre,
            ':pais' => $pais,
            ':direccion' => $direccion,
            ':telefono' => $telefono,
            ':telefono2' => $telefono2,
            ':email' => $email,
            ':email2' => $email2,
            ':contacto_nombre' => $contacto_nombre,
            ':contacto_telefono' => $contacto_telefono,
            ':website' => $website,
            ':descripcion' => $descripcion,
            ':activo' => $activo
        ]);
        echo json_encode(['success' => true, 'message' => 'Laboratorio creado correctamente']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>