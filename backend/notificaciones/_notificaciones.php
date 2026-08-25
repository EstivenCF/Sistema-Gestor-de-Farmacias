<?php
// backend/notificaciones/_notificaciones.php
// NUEVO — helper compartido para generar notificaciones del portal del
// cliente (tabla notificaciones_cliente, ver PATCH 31/31 en Farmacia.sql).
//
// Es la pieza "garantizada" de las notificaciones proactivas: no depende
// de ningún servicio externo, así que SIEMPRE se genera, sin importar si
// más adelante también se manda por email o WhatsApp (ver
// backend/notificaciones/_envio_externo.php cuando exista) — esos son un
// extra encima de esto, nunca lo reemplazan.
//
// Se usa desde:
//   - backend/ventas/procesar_venta.php       (asignación / cola al facturar)
//   - backend/delivery/_auto_asignar.php      (asignación al salir de la cola)
//   - backend/delivery/actualizar_estado_entrega.php (en camino, entregada,
//                                               fallida, reprogramada, etc.)
//   - backend/delivery/confirmar_entrega_delivery.php (mismo tipo de eventos,
//                                               desde el flujo de confirmación)
//   - backend/portal_cliente/listar_notificaciones.php (detecta atrasos)

function crearNotificacionCliente(
    PDO $conexion, int $idCliente, ?int $idEntrega, string $tipo, string $titulo, string $mensaje
): void {
    if (!$idCliente) return; // ventas "Consumidor Final" sin cliente registrado no tienen a quién notificar
    $stmt = $conexion->prepare("
        INSERT INTO notificaciones_cliente (id_cliente, id_entrega, tipo, titulo, mensaje)
        VALUES (:id_cliente, :id_entrega, :tipo, :titulo, :mensaje)
    ");
    $stmt->execute([
        ':id_cliente' => $idCliente,
        ':id_entrega' => $idEntrega,
        ':tipo'       => $tipo,
        ':titulo'     => $titulo,
        ':mensaje'    => $mensaje,
    ]);
}

// Evita duplicar la misma notificación para la misma entrega+tipo (útil
// sobre todo para "ATRASADA", que se detecta de forma perezosa cada vez
// que el cliente abre el portal — sin esto, le saldría una notificación
// nueva cada vez que recarga la página mientras sigue atrasada).
function yaExisteNotificacion(PDO $conexion, int $idEntrega, string $tipo): bool {
    $stmt = $conexion->prepare("SELECT 1 FROM notificaciones_cliente WHERE id_entrega = :id AND tipo = :tipo LIMIT 1");
    $stmt->execute([':id' => $idEntrega, ':tipo' => $tipo]);
    return (bool) $stmt->fetchColumn();
}

function crearNotificacionClienteSiNoExiste(
    PDO $conexion, int $idCliente, int $idEntrega, string $tipo, string $titulo, string $mensaje
): void {
    if (yaExisteNotificacion($conexion, $idEntrega, $tipo)) return;
    crearNotificacionCliente($conexion, $idCliente, $idEntrega, $tipo, $titulo, $mensaje);
}
