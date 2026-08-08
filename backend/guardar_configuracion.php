<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include 'conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 1) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$tipo = $_GET['tipo'] ?? '';
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos no recibidos']);
    exit();
}

try {
    switch ($tipo) {
        case 'empresa':
            $stmt = $conexion->prepare("UPDATE empresa SET nombre = :nombre, rnc = :rnc, direccion = :direccion, logo_url = :logo_url WHERE id_empresa = 1");
            $stmt->execute([
                ':nombre' => $data['nombre'],
                ':rnc' => $data['rnc'],
                ':direccion' => $data['direccion'],
                ':logo_url' => $data['logo_url']
            ]);
            echo json_encode(['success' => true, 'message' => 'Datos de empresa actualizados']);
            break;

        case 'config_general':
            foreach ($data as $clave => $valor) {
                $stmt = $conexion->prepare("INSERT INTO configuracion_sistema (clave, valor) VALUES (:clave, :valor) 
                                            ON CONFLICT (clave) DO UPDATE SET valor = EXCLUDED.valor");
                $stmt->execute([':clave' => $clave, ':valor' => $valor]);
            }
            echo json_encode(['success' => true, 'message' => 'Configuración general guardada']);
            break;

        case 'itbis':
            // Desactivar configuración anterior
            $conexion->exec("UPDATE config_itbis SET activo = FALSE");
            // Insertar nueva
            $stmt = $conexion->prepare("INSERT INTO config_itbis (porcentaje, aplica_medicamentos_sin_receta, aplica_medicamentos_con_receta, aplica_ropa, fecha_inicio, fecha_fin, activo, modificado_por) 
                                        VALUES (:porcentaje, :aplica_sin_receta, :aplica_con_receta, :aplica_ropa, :fecha_inicio, :fecha_fin, TRUE, :modificado_por)");
            $stmt->execute([
                ':porcentaje' => $data['porcentaje'],
                ':aplica_sin_receta' => isset($data['aplica_medicamentos_sin_receta']) ? 'true' : 'false',
                ':aplica_con_receta' => isset($data['aplica_medicamentos_con_receta']) ? 'true' : 'false',
                ':aplica_ropa' => isset($data['aplica_ropa']) ? 'true' : 'false',
                ':fecha_inicio' => $data['fecha_inicio'],
                ':fecha_fin' => empty($data['fecha_fin']) ? null : $data['fecha_fin'],
                ':modificado_por' => $_SESSION['id_usuario']
            ]);
            echo json_encode(['success' => true, 'message' => 'Configuración de ITBIS actualizada']);
            break;

        case 'credito':
            foreach ($data as $clave => $valor) {
                $valor = is_bool($valor) ? ($valor ? 'true' : 'false') : $valor;
                $stmt = $conexion->prepare("INSERT INTO configuracion_credito (clave, valor) VALUES (:clave, :valor) 
                                            ON CONFLICT (clave) DO UPDATE SET valor = EXCLUDED.valor, fecha_modificacion = NOW(), modificado_por = :modificado_por");
                $stmt->execute([':clave' => $clave, ':valor' => $valor, ':modificado_por' => $_SESSION['id_usuario']]);
            }
            echo json_encode(['success' => true, 'message' => 'Configuración de crédito guardada']);
            break;

        case 'backup':
            $stmt = $conexion->prepare("UPDATE configuracion_backup SET frecuencia = :frecuencia, hora_programada = :hora_programada, ruta_destino = :ruta_destino, activo = :activo");
            $stmt->execute([
                ':frecuencia' => $data['frecuencia'],
                ':hora_programada' => $data['hora_programada'],
                ':ruta_destino' => $data['ruta_destino'],
                ':activo' => isset($data['activo']) ? 'true' : 'false'
            ]);
            echo json_encode(['success' => true, 'message' => 'Configuración de backup guardada']);
            break;

        case 'notificaciones':
            $datos = [
                'email_notificaciones' => $data['email_notificaciones'] ?? '',
                'telefono_alertas' => $data['telefono_alertas'] ?? '',
                'notificar_stock_critico' => isset($data['notificar_stock_critico']) ? 'true' : 'false',
                'notificar_vencimientos' => isset($data['notificar_vencimientos']) ? 'true' : 'false'
            ];
            foreach ($datos as $clave => $valor) {
                $stmt = $conexion->prepare("INSERT INTO configuracion_sistema (clave, valor) VALUES (:clave, :valor) 
                                            ON CONFLICT (clave) DO UPDATE SET valor = EXCLUDED.valor");
                $stmt->execute([':clave' => $clave, ':valor' => $valor]);
            }
            echo json_encode(['success' => true, 'message' => 'Configuración de notificaciones guardada']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Tipo de configuración no válido']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>