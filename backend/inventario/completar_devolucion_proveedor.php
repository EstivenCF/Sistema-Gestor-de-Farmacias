<?php
/**
 * OBSOLETO (Tarea 5) — Este endpoint completaba la devolución a proveedor
 * en un solo paso, sin pasar por la solicitud formal (SOLICITADA) ni
 * validar que el proveedor y el motivo fueran los realmente pactados.
 *
 * Sustituido por dos endpoints:
 *  - gestionar_accion_recuperacion.php  -> crea la SOLICITUD (valida
 *    proveedor real del lote + motivo pactado, no toca inventario)
 *  - registrar_respuesta_devolucion_proveedor.php -> registra si el
 *    proveedor aprobó o rechazó, y solo ahí se mueve inventario.
 *
 * Se deja este archivo como stub (en vez de borrarlo) para que cualquier
 * llamada residual falle de forma explícita en vez de ejecutar el bypass
 * silenciosamente.
 */
header('Content-Type: application/json');
http_response_code(410);
echo json_encode([
    'success' => false,
    'message' => 'Este endpoint fue reemplazado. Use gestionar_accion_recuperacion.php para generar la solicitud y registrar_respuesta_devolucion_proveedor.php para registrar la respuesta del proveedor.'
]);
