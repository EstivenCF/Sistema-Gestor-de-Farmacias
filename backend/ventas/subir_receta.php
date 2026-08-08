<?php
// backend/ventas/subir_receta.php
// NUEVO — sube la foto de la receta y la adjunta a una venta ya creada.
// Se llama DESPUÉS de procesar_venta.php (recibe el id_venta ya generado).

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit();
}

$id_venta = intval($_POST['id_venta'] ?? 0);
if (!$id_venta) {
    echo json_encode(['success' => false, 'message' => 'ID de venta requerido']); exit();
}
if (empty($_FILES['receta']) || $_FILES['receta']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No se recibió el archivo de la receta']); exit();
}

$permitidas = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
$mime = mime_content_type($_FILES['receta']['tmp_name']);
if (!in_array($mime, $permitidas)) {
    echo json_encode(['success' => false, 'message' => 'Formato no permitido. Usa JPG, PNG, WEBP o PDF.']); exit();
}
if ($_FILES['receta']['size'] > 5 * 1024 * 1024) { // 5MB
    echo json_encode(['success' => false, 'message' => 'El archivo supera los 5MB permitidos.']); exit();
}

try {
    $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
    $ext = $extMap[$mime];
    $nombreArchivo = 'receta_venta_' . $id_venta . '_' . time() . '.' . $ext;

    $dirDestino = __DIR__ . '/../../assets/img/recetas/';
    if (!is_dir($dirDestino)) mkdir($dirDestino, 0755, true);

    $rutaAbsoluta = $dirDestino . $nombreArchivo;
    $rutaRelativa = '/assets/img/recetas/' . $nombreArchivo; // ruta guardada en BD

    if (!move_uploaded_file($_FILES['receta']['tmp_name'], $rutaAbsoluta)) {
        echo json_encode(['success' => false, 'message' => 'No se pudo guardar el archivo']); exit();
    }

    $stmt = $conexion->prepare("UPDATE ventas SET receta_imagen_path = :ruta, con_receta = TRUE WHERE id_venta = :id");
    $stmt->execute([':ruta' => $rutaRelativa, ':id' => $id_venta]);

    echo json_encode(['success' => true, 'ruta' => $rutaRelativa]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
