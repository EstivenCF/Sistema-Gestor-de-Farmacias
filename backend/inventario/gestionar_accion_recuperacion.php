<?php
/**
 * Tarea 5 - Proceso estratégico: Gestión Estratégica de Vencimientos de Medicamentos
 * Guarda una acción de recuperación (Pantalla #04: Promoción / Redistribución /
 * Devolución a proveedor). Las acciones de escalamiento (Combo, Donación,
 * Provisión de pérdida) se guardan desde la Pantalla #08, con este mismo endpoint.
 *
 * NUEVO: cuando tipo_accion = 'DEVOLUCION_PROVEEDOR', este endpoint ya NO
 * cierra la devolución de una vez. Genera la SOLICITUD formal (devoluciones,
 * estado SOLICITADA) contra el proveedor real que surtió el lote, validando
 * en el servidor -no solo en el formulario- que ese proveedor tenga pactado
 * el motivo indicado (proveedor_motivo_devolucion). La acción queda en
 * estado ESPERANDO_PROVEEDOR hasta que se registre la respuesta (Pantalla
 * de detalle de lote -> registrar_respuesta_devolucion_proveedor.php).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

// CORRECCIÓN: login.php guarda $_SESSION['usuario_id'], no 'id_usuario'.
// Antes esto quedaba en null siempre y el "responsable" real dependía por
// completo de lo que mandara el frontend (inseguro: cualquiera podía
// asignar la acción a otro usuario). Ahora el responsable SIEMPRE es el
// usuario autenticado — no se acepta 'responsable' del cliente.
$id_usuario = $_SESSION['usuario_id'] ?? ($_SESSION['id_usuario'] ?? null);
if (!$id_usuario) {
    echo json_encode(['success' => false, 'message' => 'No se pudo determinar el usuario autenticado.']);
    exit();
}

require_once __DIR__ . '/../conexion.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

$camposObligatorios = ['id_lote', 'id_sucursal_origen', 'tipo_accion', 'cantidad_afectada', 'valor_en_riesgo'];
foreach ($camposObligatorios as $campo) {
    if (empty($data[$campo]) && $data[$campo] !== 0) {
        echo json_encode(['success' => false, 'message' => "Falta el campo obligatorio: $campo"]);
        exit();
    }
}

$marcarCompletada = isset($data['marcar_completada']) && $data['marcar_completada'] === true;
$esDevolucionProveedor = $data['tipo_accion'] === 'DEVOLUCION_PROVEEDOR';

try {
    $conexion->beginTransaction();

    $id_devolucion = null;
    $estadoInicial = $marcarCompletada ? 'COMPLETADA' : 'PENDIENTE';

    if ($esDevolucionProveedor) {
        $id_proveedor = isset($data['id_proveedor']) ? (int)$data['id_proveedor'] : 0;
        $id_motivo = isset($data['id_motivo']) ? (int)$data['id_motivo'] : 0;

        if (!$id_proveedor || !$id_motivo) {
            throw new Exception('Debe indicar el proveedor y el motivo de la devolución.');
        }

        // 1) El proveedor debe ser un proveedor REAL de este lote (no
        //    cualquiera de la tabla proveedores) — se verifica contra la
        //    compra de origen, igual que hace obtener_proveedor_devolucion.php.
        $stmt = $conexion->prepare("
            SELECT COUNT(*) FROM detalle_compra dc
            JOIN compras c ON dc.id_compra = c.id_compra
            WHERE dc.id_lote = :id_lote AND c.id_proveedor = :id_proveedor
        ");
        $stmt->execute([':id_lote' => $data['id_lote'], ':id_proveedor' => $id_proveedor]);
        if ((int)$stmt->fetchColumn() === 0) {
            throw new Exception('El proveedor indicado no corresponde a la compra de origen de este lote.');
        }

        // 2) El motivo debe estar dentro de lo pactado con ese proveedor.
        $stmt = $conexion->prepare("
            SELECT COUNT(*) FROM proveedor_motivo_devolucion
            WHERE id_proveedor = :id_proveedor AND id_motivo = :id_motivo
        ");
        $stmt->execute([':id_proveedor' => $id_proveedor, ':id_motivo' => $id_motivo]);
        if ((int)$stmt->fetchColumn() === 0) {
            throw new Exception('Este proveedor no tiene pactado ese motivo de devolución. Verifique las condiciones acordadas en su ficha.');
        }

        // 3) Se crea la SOLICITUD (no la devolución completada). No se
        //    toca inventario todavía: eso solo ocurre si el proveedor
        //    aprueba (ver registrar_respuesta_devolucion_proveedor.php).
        // numero_documento es VARCHAR(20): se sigue el mismo formato corto
        // que ya usa registrar_devolucion.php (DEV + fecha corta + azar).
        $numero_doc = 'DEVP' . date('ymdHis') . rand(100, 999); // 4 + 12 + 3 = 19 caracteres
        $stmt = $conexion->prepare("
            INSERT INTO devoluciones (numero_documento, id_proveedor, id_sucursal, motivo, id_motivo, id_usuario, id_tipo, id_estado)
            VALUES (:num, :prov, :suc, :mot, :id_motivo,  :usuario,
                    (SELECT id_tipo FROM tipo_devolucion WHERE nombre = 'PROVEEDOR'),
                    (SELECT id_estado FROM estado_devolucion WHERE nombre = 'SOLICITADA'))
            RETURNING id_devolucion
        ");
        $stmt->execute([
            ':num' => $numero_doc,
            ':prov' => $id_proveedor,
            ':suc' => $data['id_sucursal_origen'],
            ':mot' => $data['observaciones'] ?? 'Solicitud de devolución generada desde el proceso de gestión de vencimientos',
            ':id_motivo' => $id_motivo,
            ':usuario' => $id_usuario,
        ]);
        $id_devolucion = $stmt->fetchColumn();

        if (!$id_devolucion) {
            throw new Exception('No se pudo generar la solicitud de devolución.');
        }

        $stmt = $conexion->prepare("INSERT INTO detalle_devolucion (id_devolucion, id_lote, cantidad, precio_unitario)
                                    SELECT :id_devolucion, :id_lote, :cantidad, COALESCE(
                                        l.costo_unitario,
                                        (SELECT dc.precio_unitario FROM detalle_compra dc WHERE dc.id_lote = l.id_lote LIMIT 1),
                                        0
                                    )
                                    FROM lotes l WHERE l.id_lote = :id_lote_precio");
        $stmt->execute([
            ':id_devolucion' => $id_devolucion,
            ':id_lote' => $data['id_lote'],
            ':cantidad' => $data['cantidad_afectada'],
            ':id_lote_precio' => $data['id_lote'],
        ]);

        // La acción no puede marcarse "completada" al vuelo: queda a la
        // espera de que el proveedor responda.
        $estadoInicial = 'ESPERANDO_PROVEEDOR';
        $marcarCompletada = false;
    }

    $sql = "INSERT INTO accion_recuperacion (
                id_lote, id_sucursal_origen, id_sucursal_destino,
                tipo_accion, estado, prioridad,
                cantidad_afectada, valor_en_riesgo, valor_recuperado_estimado,
                nivel_riesgo_al_generar, irv_origen_al_generar, causa_raiz,
                responsable, creado_por, fecha_limite, observaciones,
                entidad_receptora, id_accion_previa, id_devolucion, fecha_ejecucion
            ) VALUES (
                :id_lote, :id_sucursal_origen, :id_sucursal_destino,
                :tipo_accion, :estado, :prioridad,
                :cantidad_afectada, :valor_en_riesgo, :valor_recuperado_estimado,
                :nivel_riesgo_al_generar, :irv_origen_al_generar, :causa_raiz,
                :responsable, :creado_por, :fecha_limite, :observaciones,
                :entidad_receptora, :id_accion_previa, :id_devolucion, :fecha_ejecucion
            ) RETURNING id_accion";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        ':id_lote' => $data['id_lote'],
        ':id_sucursal_origen' => $data['id_sucursal_origen'],
        ':id_sucursal_destino' => $data['id_sucursal_destino'] ?? null,
        ':tipo_accion' => $data['tipo_accion'],
        ':estado' => $estadoInicial,
        ':prioridad' => $data['prioridad'] ?? 'MEDIA',
        ':cantidad_afectada' => $data['cantidad_afectada'],
        ':valor_en_riesgo' => $data['valor_en_riesgo'],
        ':valor_recuperado_estimado' => $data['valor_recuperado_estimado'] ?? null,
        ':nivel_riesgo_al_generar' => $data['nivel_riesgo_al_generar'] ?? null,
        ':irv_origen_al_generar' => $data['irv_origen_al_generar'] ?? null,
        ':causa_raiz' => $data['causa_raiz'] ?? null,
        ':responsable' => $id_usuario, // Ya no se acepta del cliente: siempre el usuario en sesión (punto 1 del análisis)
        ':creado_por' => $id_usuario,
        ':fecha_limite' => $data['fecha_limite'] ?? null,
        ':observaciones' => $data['observaciones'] ?? null,
        ':entidad_receptora' => $data['entidad_receptora'] ?? null,
        ':id_accion_previa' => $data['id_accion_previa'] ?? null,
        ':id_devolucion' => $id_devolucion,
        ':fecha_ejecucion' => $marcarCompletada ? date('Y-m-d H:i:s') : null,
    ]);

    $id_accion = $stmt->fetchColumn();

    $conexion->commit();

    $mensaje = $esDevolucionProveedor
        ? "Solicitud de devolución #$id_devolucion registrada y enviada al proveedor. La acción queda en espera de su respuesta."
        : 'Acción de recuperación registrada correctamente';

    echo json_encode(['success' => true, 'id_accion' => (int)$id_accion, 'id_devolucion' => $id_devolucion ? (int)$id_devolucion : null, 'message' => $mensaje]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
