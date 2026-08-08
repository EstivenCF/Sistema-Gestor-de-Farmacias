<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json');

// Verificar que sea administrador
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    // Obtener usuarios con intentos fallidos en la última hora
    $stmt = $conexion->prepare("
        SELECT 
            usuario_intentado as usuario,
            COUNT(*) as intentos,
            MAX(fecha_intento) as ultimo_intento,
            EXTRACT(EPOCH FROM (NOW() - MAX(fecha_intento))) as segundos_transcurridos
        FROM intentos_login 
        WHERE fecha_intento > NOW() - INTERVAL '1 hour'
        GROUP BY usuario_intentado
        HAVING COUNT(*) >= 5
        ORDER BY intentos DESC
    ");
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $usuarios = [];
    foreach ($resultados as $row) {
        $intentos = (int)$row['intentos'];
        $tiempo_transcurrido = (int)$row['segundos_transcurridos'];
        $bloqueo_permanente = false;
        $tiempo_restante = 0;
        
        if ($intentos >= 10) {
            $bloqueo_permanente = true;
        } elseif ($intentos >= 8) {
            $tiempo_restante = max(0, 900 - $tiempo_transcurrido);
        } elseif ($intentos >= 6) {
            $tiempo_restante = max(0, 300 - $tiempo_transcurrido);
        } elseif ($intentos >= 5) {
            $tiempo_restante = max(0, 60 - $tiempo_transcurrido);
        }
        
        $usuarios[] = [
            'usuario' => $row['usuario'],
            'intentos' => $intentos,
            'bloqueo_permanente' => $bloqueo_permanente,
            'tiempo_restante' => $tiempo_restante
        ];
    }
    
    echo json_encode(['success' => true, 'usuarios' => $usuarios]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>