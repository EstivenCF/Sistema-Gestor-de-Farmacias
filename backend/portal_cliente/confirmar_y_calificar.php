<?php
// backend/portal_cliente/confirmar_y_calificar.php
// REEMPLAZA el archivo existente.
//
// ACTUALIZACIÓN — Preguntas de calificación configurables: ya no recibe
// atencion/estado_producto/presentacion/puntualidad/puntuacion_general
// como campos fijos. Ahora recibe "respuestas": [{id_pregunta, valor}],
// una por cada pregunta ACTIVA del catálogo (preguntas_calificacion).
// El backend vuelve a leer el catálogo real de la base (nunca confía en
// lo que mande el navegador para saber qué pregunta es, de qué tipo, ni
// si es obligatoria) y guarda una "foto" de la pregunta junto a la
// respuesta, para que si el administrador la edita después, la respuesta
// vieja siga mostrando lo que en verdad se le preguntó al cliente.
//
// "Se entregó a la persona correcta" se mantiene como un campo fijo
// (no es una pregunta de satisfacción, es una verificación puntual).

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_cliente_portal'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']); exit();
}
$id_cliente = $_SESSION['id_cliente_portal'];

$data = json_decode(file_get_contents('php://input'), true);
$id_entrega = intval($data['id_entrega'] ?? 0);
$persona_correcta = filter_var($data['persona_correcta'] ?? true, FILTER_VALIDATE_BOOLEAN);
$respuestasInput = $data['respuestas'] ?? []; // [{id_pregunta, valor_estrellas?, valor_texto?}]

if (!$id_entrega) {
    echo json_encode(['success' => false, 'message' => 'Falta la entrega']); exit();
}

try {
    $conexion->beginTransaction();

    $stmt = $conexion->prepare("
        SELECT e.id_entrega, se.nombre AS estado_actual
        FROM entregas e
        JOIN estado_entrega se ON se.id_estado = e.id_estado
        WHERE e.id_entrega = :id AND e.id_cliente = :id_cliente
        FOR UPDATE OF e
    ");
    $stmt->execute([':id' => $id_entrega, ':id_cliente' => $id_cliente]);
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entrega) { throw new Exception('Entrega no encontrada'); }
    if ($entrega['estado_actual'] !== 'ENTREGADA') {
        throw new Exception('Esta entrega todavía no ha sido marcada como entregada por el repartidor');
    }

    $stmtChk = $conexion->prepare("SELECT confirmado_por_cliente FROM entregas WHERE id_entrega = :id");
    $stmtChk->execute([':id' => $id_entrega]);
    if (filter_var($stmtChk->fetchColumn(), FILTER_VALIDATE_BOOLEAN)) {
        throw new Exception('Ya confirmaste y calificaste esta entrega antes');
    }

    // Verdad del servidor: el catálogo real de preguntas activas, nunca
    // lo que mande el navegador.
    $stmt = $conexion->prepare("
        SELECT pc.id_pregunta, cc.nombre AS categoria, pc.texto, pc.tipo_respuesta, pc.obligatoria
        FROM preguntas_calificacion pc
        LEFT JOIN categorias_calificacion cc ON cc.id_categoria = pc.id_categoria
        WHERE pc.activo = TRUE
    ");
    $stmt->execute();
    $catalogo = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $catalogo[(int) $row['id_pregunta']] = $row;
    }

    $respuestasPorPregunta = [];
    foreach ($respuestasInput as $r) {
        $respuestasPorPregunta[intval($r['id_pregunta'] ?? 0)] = $r;
    }

    $respuestasAGuardar = [];
    foreach ($catalogo as $id_pregunta => $pregunta) {
        $r = $respuestasPorPregunta[$id_pregunta] ?? null;

        if ($pregunta['tipo_respuesta'] === 'ESTRELLAS') {
            $valor = $r ? intval($r['valor_estrellas'] ?? 0) : 0;
            if ($valor < 1 || $valor > 5) {
                if ($pregunta['obligatoria']) {
                    throw new Exception('Falta calificar: "' . $pregunta['texto'] . '"');
                }
                continue; // opcional y no respondida, se omite
            }
            $respuestasAGuardar[] = [
                'id_pregunta'     => $id_pregunta,
                'texto_pregunta'  => $pregunta['texto'],
                'categoria'       => $pregunta['categoria'],
                'tipo_respuesta'  => 'ESTRELLAS',
                'valor_estrellas' => $valor,
                'valor_texto'     => null,
            ];
        } else { // TEXTO
            $valor = $r ? trim($r['valor_texto'] ?? '') : '';
            if ($valor === '') {
                if ($pregunta['obligatoria']) {
                    throw new Exception('Falta responder: "' . $pregunta['texto'] . '"');
                }
                continue;
            }
            $respuestasAGuardar[] = [
                'id_pregunta'     => $id_pregunta,
                'texto_pregunta'  => $pregunta['texto'],
                'categoria'       => $pregunta['categoria'],
                'tipo_respuesta'  => 'TEXTO',
                'valor_estrellas' => null,
                'valor_texto'     => $valor,
            ];
        }
    }

    $conexion->prepare("
        UPDATE entregas
        SET confirmado_por_cliente = TRUE, fecha_confirmacion_cliente = NOW()
        WHERE id_entrega = :id
    ")->execute([':id' => $id_entrega]);

    $stmt = $conexion->prepare("
        INSERT INTO calificaciones_entrega (id_entrega, id_cliente, persona_correcta)
        VALUES (:id_entrega, :id_cliente, :persona_correcta)
        RETURNING id_calificacion
    ");
    $stmt->execute([
        ':id_entrega'       => $id_entrega,
        ':id_cliente'       => $id_cliente,
        ':persona_correcta' => $persona_correcta ? 't' : 'f',
    ]);
    $id_calificacion = $stmt->fetchColumn();

    $stmtResp = $conexion->prepare("
        INSERT INTO respuestas_calificacion
            (id_calificacion, id_pregunta, texto_pregunta, categoria, tipo_respuesta, valor_estrellas, valor_texto)
        VALUES
            (:id_calificacion, :id_pregunta, :texto_pregunta, :categoria, :tipo_respuesta, :valor_estrellas, :valor_texto)
    ");
    foreach ($respuestasAGuardar as $r) {
        $stmtResp->execute([
            ':id_calificacion' => $id_calificacion,
            ':id_pregunta'     => $r['id_pregunta'],
            ':texto_pregunta'  => $r['texto_pregunta'],
            ':categoria'       => $r['categoria'],
            ':tipo_respuesta'  => $r['tipo_respuesta'],
            ':valor_estrellas' => $r['valor_estrellas'],
            ':valor_texto'     => $r['valor_texto'],
        ]);
    }

    $conexion->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
