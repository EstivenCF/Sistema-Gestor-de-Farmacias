<?php
// backend/delivery/_auto_asignar.php
// NUEVO — helper reutilizable. Se llama justo después de que un
// repartidor confirma una entrega y queda libre otra vez. Busca en la
// cola (entregas sin repartidor asignado) la más antigua y se la
// asigna, junto con un vehículo disponible que sepa manejar.
//
// Una entrega "en cola" es simplemente una fila en `entregas` con
// id_repartidor IS NULL — no hace falta ninguna tabla ni estado nuevo.
//
// Usa FOR UPDATE SKIP LOCKED para que, si dos repartidores confirman
// casi al mismo tiempo, no compitan por la misma entrega en cola.

function intentarAutoAsignarDesdeCola(PDO $conexion, int $id_repartidor): ?array {
    // 1. Confirmar que el repartidor está realmente libre ahora mismo
    $stmt = $conexion->prepare("
        SELECT 1 FROM repartidores r
        WHERE r.id_repartidor = :id AND r.activo = TRUE AND r.fecha_salida IS NULL
          AND NOT EXISTS (
              SELECT 1 FROM entregas e
              JOIN estado_entrega se ON se.id_estado = e.id_estado
              WHERE e.id_repartidor = r.id_repartidor
                AND se.nombre IN ('PENDIENTE','ASIGNADA','EN_CAMINO')
          )
    ");
    $stmt->execute([':id' => $id_repartidor]);
    if (!$stmt->fetchColumn()) return null;

    // 2. Tomar la entrega en cola más antigua (bloqueo suave anti-carrera)
    $stmt = $conexion->prepare("
        SELECT e.id_entrega
        FROM entregas e
        JOIN estado_entrega se ON se.id_estado = e.id_estado
        WHERE e.id_repartidor IS NULL AND se.nombre = 'PENDIENTE'
        ORDER BY e.fecha_pedido ASC
        LIMIT 1
        FOR UPDATE OF e SKIP LOCKED
    ");
    $stmt->execute();
    $id_entrega = $stmt->fetchColumn();
    if (!$id_entrega) return null; // nada en cola

    // 3. Buscar un vehículo disponible que el repartidor sepa manejar
    //    (se prioriza el que normalmente usa, sin ser obligatorio)
    $stmt = $conexion->prepare("
        SELECT v.id_vehiculo, v.tipo, v.placa
        FROM vehiculos v
        JOIN repartidor_habilidad rh ON rh.tipo_vehiculo = v.tipo AND rh.id_repartidor = :id
        WHERE v.activo = TRUE AND v.estado = 'DISPONIBLE'
        ORDER BY (v.id_repartidor = :id2) DESC
        LIMIT 1
    ");
    $stmt->execute([':id' => $id_repartidor, ':id2' => $id_repartidor]);
    $vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$vehiculo) return null; // hay cola pero no tiene vehículo disponible ahora

    // 4. Asignar
    $stmt = $conexion->prepare("
        UPDATE entregas
        SET id_repartidor = :id_repartidor, id_vehiculo = :id_vehiculo, fecha_asignada = NOW()
        WHERE id_entrega = :id_entrega
    ");
    $stmt->execute([
        ':id_repartidor' => $id_repartidor,
        ':id_vehiculo'   => $vehiculo['id_vehiculo'],
        ':id_entrega'    => $id_entrega,
    ]);

    $stmt = $conexion->prepare("UPDATE vehiculos SET estado = 'EN_USO' WHERE id_vehiculo = :id");
    $stmt->execute([':id' => $vehiculo['id_vehiculo']]);

    $stmt = $conexion->prepare("
        INSERT INTO historial_entrega (id_entrega, id_estado, fecha, observacion)
        SELECT :id_entrega, id_estado, NOW(), 'Asignación automática desde la cola de espera'
        FROM estado_entrega WHERE nombre = 'PENDIENTE'
    ");
    $stmt->execute([':id_entrega' => $id_entrega]);

    // Número de seguimiento, para el mensaje al repartidor
    $stmt = $conexion->prepare("SELECT numero_seguimiento FROM entregas WHERE id_entrega = :id");
    $stmt->execute([':id' => $id_entrega]);
    $numero_seguimiento = $stmt->fetchColumn();

    return [
        'id_entrega'         => $id_entrega,
        'numero_seguimiento' => $numero_seguimiento,
        'id_vehiculo'        => $vehiculo['id_vehiculo'],
        'vehiculo_texto'     => $vehiculo['tipo'] . ($vehiculo['placa'] ? ' (' . $vehiculo['placa'] . ')' : ''),
    ];
}
