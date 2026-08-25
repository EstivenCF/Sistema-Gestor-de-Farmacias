<?php
// backend/ventas/procesar_venta.php
// REEMPLAZA el archivo existente.
// Cambios sobre el original:
//   1. Guarda tipo_despacho ('RETIRO_PERSONAL' | 'DELIVERY') y con_receta en ventas.
//   2. Al asignar delivery, ahora también recibe id_vehiculo y lo guarda en
//      entregas.id_vehiculo, y marca ese vehículo como EN_USO.
//   3. El resto de la lógica (detalle_venta, inventario, pagos) queda IGUAL
//      al archivo original — no se tocó nada de eso.

require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_sesion'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

try {
    $conexion->beginTransaction();

    // Validar y convertir tipos
    $id_condicion = isset($data['id_condicion']) ? (int)$data['id_condicion'] : 1;
    if ($id_condicion < 1 || $id_condicion > 3) $id_condicion = 1;
    $es_credito = ($id_condicion == 2 || $id_condicion == 3);
    $usa_seguro = isset($data['usa_seguro']) ? filter_var($data['usa_seguro'], FILTER_VALIDATE_BOOLEAN) : false;
    $monto_seguro = isset($data['monto_cubre_seguro']) ? (float)$data['monto_cubre_seguro'] : 0.0;
    $monto_paciente = isset($data['monto_paga_paciente']) ? (float)$data['monto_paga_paciente'] : (float)$data['total'];

    // ── NUEVO: tipo de despacho y receta ──
    $delivery_activo = !empty($data['delivery_activo']) && filter_var($data['delivery_activo'], FILTER_VALIDATE_BOOLEAN);
    $tipo_despacho = $delivery_activo ? 'DELIVERY' : ($data['tipo_despacho'] ?? 'RETIRO_PERSONAL');
    if (!in_array($tipo_despacho, ['RETIRO_PERSONAL', 'DELIVERY'])) $tipo_despacho = 'RETIRO_PERSONAL';
    $con_receta = !empty($data['con_receta']) && filter_var($data['con_receta'], FILTER_VALIDATE_BOOLEAN);

    // Insertar venta
    $sql = "INSERT INTO ventas 
        (numero_documento, id_usuario, id_cliente, id_sucursal, id_condicion, subtotal, descuento_total, itbis_total, total, es_credito, usa_seguro, monto_cubre_seguro, monto_paga_paciente, tipo_despacho, con_receta)
        VALUES (:num_doc, :id_user, :id_cliente, :id_sucursal, :id_condicion, :subtotal, :descuento, :itbis, :total, :credito, :seguro, :monto_seguro, :monto_paciente, :tipo_despacho, :con_receta)
        RETURNING id_venta";
    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':num_doc', $data['numero_documento'], PDO::PARAM_STR);
    $stmt->bindValue(':id_user', $data['id_usuario'], PDO::PARAM_INT);
    $stmt->bindValue(':id_cliente', $data['id_cliente'] ?? null, PDO::PARAM_INT);
    $stmt->bindValue(':id_sucursal', $data['id_sucursal'], PDO::PARAM_INT);
    $stmt->bindValue(':id_condicion', $id_condicion, PDO::PARAM_INT);
    $stmt->bindValue(':subtotal', (float)$data['subtotal'], PDO::PARAM_STR);
    $stmt->bindValue(':descuento', (float)($data['monto_descuento'] ?? 0), PDO::PARAM_STR);
    $stmt->bindValue(':itbis', (float)$data['itbis_total'], PDO::PARAM_STR);
    $stmt->bindValue(':total', (float)$data['total'], PDO::PARAM_STR);
    $stmt->bindValue(':credito', $es_credito, PDO::PARAM_BOOL);
    $stmt->bindValue(':seguro', $usa_seguro, PDO::PARAM_BOOL);
    $stmt->bindValue(':monto_seguro', $monto_seguro, PDO::PARAM_STR);
    $stmt->bindValue(':monto_paciente', $monto_paciente, PDO::PARAM_STR);
    $stmt->bindValue(':tipo_despacho', $tipo_despacho, PDO::PARAM_STR);
    $stmt->bindValue(':con_receta', $con_receta, PDO::PARAM_BOOL);
    $stmt->execute();
    $id_venta = $stmt->fetchColumn();

    // ==================== RECORRER PRODUCTOS ====================
    foreach ($data['productos'] as $prod) {
        $itemSubtotal = (float)$prod['cantidad'] * (float)$prod['precio_unitario'];
        $itemItbis = ($prod['aplica_itbis'] ?? false) ? $itemSubtotal * 0.18 : 0;
        $tipo = $prod['tipo'] ?? 'MEDICAMENTO';

        $sqlDet = "INSERT INTO detalle_venta 
            (id_venta, id_lote, id_producto, id_talla, id_color, cantidad, precio_unitario, descuento_unitario, itbis_unitario, subtotal)
            VALUES 
            (:id_venta, :id_lote, :id_producto, :id_talla, :id_color, :cant, :precio, :dto, :itbis, :subtotal)";

        $stmtDet = $conexion->prepare($sqlDet);
        $stmtDet->bindValue(':id_venta', $id_venta, PDO::PARAM_INT);
        $stmtDet->bindValue(':id_lote', $prod['id_lote'] ?? null, PDO::PARAM_INT);
        $stmtDet->bindValue(':id_producto', $prod['id_producto'], PDO::PARAM_INT);
        $stmtDet->bindValue(':id_talla', $prod['id_talla'] ?? null, PDO::PARAM_INT);
        $stmtDet->bindValue(':id_color', $prod['id_color'] ?? null, PDO::PARAM_INT);
        $stmtDet->bindValue(':cant', $prod['cantidad'], PDO::PARAM_INT);
        $stmtDet->bindValue(':precio', $prod['precio_unitario'], PDO::PARAM_STR);
        $stmtDet->bindValue(':dto', 0, PDO::PARAM_STR);
        $stmtDet->bindValue(':itbis', $itemItbis, PDO::PARAM_STR);
        $stmtDet->bindValue(':subtotal', $itemSubtotal + $itemItbis, PDO::PARAM_STR);
        $stmtDet->execute();

        // ==================== ACTUALIZAR INVENTARIO ====================
        if ($tipo === 'ROPA') {
            $sqlInv = "UPDATE inventario_productos 
                    SET cantidad = cantidad - :cantidad
                    WHERE id_producto = :id_producto 
                        AND id_sucursal = :id_sucursal
                        AND (id_talla = :id_talla OR (id_talla IS NULL AND :id_talla IS NULL))
                        AND (id_color = :id_color OR (id_color IS NULL AND :id_color IS NULL))";
            $stmtInv = $conexion->prepare($sqlInv);
            $stmtInv->bindValue(':cantidad', $prod['cantidad'], PDO::PARAM_INT);
            $stmtInv->bindValue(':id_producto', $prod['id_producto'], PDO::PARAM_INT);
            $stmtInv->bindValue(':id_sucursal', $data['id_sucursal'], PDO::PARAM_INT);
            $stmtInv->bindValue(':id_talla', $prod['id_talla'] ?? null, PDO::PARAM_INT);
            $stmtInv->bindValue(':id_color', $prod['id_color'] ?? null, PDO::PARAM_INT);
            $stmtInv->execute();

            $sqlMov = "INSERT INTO movimiento_inventario_productos 
                (id_producto, id_sucursal, id_talla, id_color, tipo, cantidad, motivo, referencia, id_usuario)
                VALUES 
                (:id_producto, :id_sucursal, :id_talla, :id_color, 'SALIDA', :cantidad, 'Venta', :referencia, :id_usuario)";
            $stmtMov = $conexion->prepare($sqlMov);
            $stmtMov->bindValue(':id_producto', $prod['id_producto'], PDO::PARAM_INT);
            $stmtMov->bindValue(':id_sucursal', $data['id_sucursal'], PDO::PARAM_INT);
            $stmtMov->bindValue(':id_talla', $prod['id_talla'] ?? null, PDO::PARAM_INT);
            $stmtMov->bindValue(':id_color', $prod['id_color'] ?? null, PDO::PARAM_INT);
            $stmtMov->bindValue(':cantidad', $prod['cantidad'], PDO::PARAM_INT);
            $stmtMov->bindValue(':referencia', $data['numero_documento'], PDO::PARAM_STR);
            $stmtMov->bindValue(':id_usuario', $data['id_usuario'], PDO::PARAM_INT);
            $stmtMov->execute();

        } else {
            $sqlInv = "UPDATE inventario 
                    SET cantidad = cantidad - :cantidad
                    WHERE id_lote = :id_lote AND id_sucursal = :id_sucursal";
            $stmtInv = $conexion->prepare($sqlInv);
            $stmtInv->bindValue(':cantidad', $prod['cantidad'], PDO::PARAM_INT);
            $stmtInv->bindValue(':id_lote', $prod['id_lote'], PDO::PARAM_INT);
            $stmtInv->bindValue(':id_sucursal', $data['id_sucursal'], PDO::PARAM_INT);
            $stmtInv->execute();

            $sqlMov = "INSERT INTO movimiento_inventario 
                (id_lote, id_sucursal, tipo, cantidad, motivo, referencia, id_usuario)
                VALUES 
                (:id_lote, :id_sucursal, 'SALIDA', :cantidad, 'Venta', :referencia, :id_usuario)";
            $stmtMov = $conexion->prepare($sqlMov);
            $stmtMov->bindValue(':id_lote', $prod['id_lote'], PDO::PARAM_INT);
            $stmtMov->bindValue(':id_sucursal', $data['id_sucursal'], PDO::PARAM_INT);
            $stmtMov->bindValue(':cantidad', $prod['cantidad'], PDO::PARAM_INT);
            $stmtMov->bindValue(':referencia', $data['numero_documento'], PDO::PARAM_STR);
            $stmtMov->bindValue(':id_usuario', $data['id_usuario'], PDO::PARAM_INT);
            $stmtMov->execute();
        }
    }

    // Pago al contado
    if ($id_condicion == 1 && !empty($data['id_metodo_pago'])) {
        $sqlPago = "INSERT INTO pagos (id_venta, id_metodo, monto) VALUES (:id_venta, :id_metodo, :monto)";
        $stmtPago = $conexion->prepare($sqlPago);
        $stmtPago->bindValue(':id_venta', $id_venta, PDO::PARAM_INT);
        $stmtPago->bindValue(':id_metodo', $data['id_metodo_pago'], PDO::PARAM_INT);
        $stmtPago->bindValue(':monto', (float)$data['total'], PDO::PARAM_STR);
        $stmtPago->execute();
    }

    // ==================== ENTREGA (DELIVERY) ====================
    // ACTUALIZACIÓN — asignación automática: el cajero ya NO elige
    // repartidor ni vehículo (esos campos, si el frontend los llega a
    // mandar, se ignoran a propósito — nunca se confía en lo que decida
    // el navegador para esto). El backend calcula la distancia real
    // entre la sucursal y la dirección de entrega (Haversine, mismas
    // coordenadas que ya usaba calcular_costo_envio.php) y, con eso,
    // elige el repartidor+vehículo automáticamente según
    // backend/delivery/_asignacion_automatica.php: bicicleta para
    // distancias cortas, moto para medias, carro para largas — con
    // reserva a otro tipo si el ideal no tiene a nadie libre ahora
    // mismo. Si de plano no hay nadie disponible, la entrega se manda a
    // la cola igual que antes — el auto-asignador
    // (backend/delivery/_auto_asignar.php) la toma en cuanto alguien
    // compatible quede libre.
    if ($delivery_activo) {
        require_once __DIR__ . '/../delivery/_asignacion_automatica.php';

        // Distancia real sucursal -> dirección de entrega, y coordenadas
        // de la dirección (para guardarlas en la entrega: sin esto no
        // hay forma de recalcular la distancia más adelante).
        $km = null;
        $lat_entrega = null;
        $lon_entrega = null;
        $id_direccion = intval($data['id_direccion'] ?? 0);
        if ($id_direccion) {
            $stmtSuc = $conexion->prepare("SELECT latitud, longitud FROM sucursales WHERE id_sucursal = :id");
            $stmtSuc->execute([':id' => $data['id_sucursal']]);
            $sucursal = $stmtSuc->fetch(PDO::FETCH_ASSOC);

            $stmtDir = $conexion->prepare("SELECT latitud, longitud FROM direcciones WHERE id_direccion = :id");
            $stmtDir->execute([':id' => $id_direccion]);
            $direccion = $stmtDir->fetch(PDO::FETCH_ASSOC);

            if ($sucursal && $direccion && $sucursal['latitud'] !== null && $sucursal['longitud'] !== null
                && $direccion['latitud'] !== null && $direccion['longitud'] !== null) {
                $km = haversineKm(
                    (float) $sucursal['latitud'], (float) $sucursal['longitud'],
                    (float) $direccion['latitud'], (float) $direccion['longitud']
                );
                $lat_entrega = (float) $direccion['latitud'];
                $lon_entrega = (float) $direccion['longitud'];
            }
        }

        // Carga del pedido (PATCH 28/28): peso total si los productos lo
        // tienen registrado (productos.peso_kg, opcional), y cantidad
        // total de unidades como respaldo — para que el factor funcione
        // desde ya aunque nadie haya llenado peso_kg todavía. Nunca deja
        // asignar un vehículo más chico de lo que la carga exige, sin
        // importar qué tan corta sea la distancia.
        $pesoTotalKg = null;
        $totalUnidades = 0;
        foreach (($data['productos'] ?? []) as $prod) {
            $totalUnidades += (int) ($prod['cantidad'] ?? 0);
        }
        if (!empty($data['productos'])) {
            $idsProductos = array_unique(array_map(fn($p) => (int) $p['id_producto'], $data['productos']));
            if ($idsProductos) {
                $placeholders = implode(',', array_fill(0, count($idsProductos), '?'));
                $stmtPeso = $conexion->prepare("SELECT id_producto, peso_kg FROM productos WHERE id_producto IN ($placeholders)");
                $stmtPeso->execute(array_values($idsProductos));
                $pesoPorProducto = [];
                foreach ($stmtPeso->fetchAll(PDO::FETCH_ASSOC) as $p) {
                    $pesoPorProducto[$p['id_producto']] = $p['peso_kg'] !== null ? (float) $p['peso_kg'] : null;
                }
                $pesoTotalKg = 0.0;
                $pesoCompleto = true;
                foreach ($data['productos'] as $prod) {
                    $pesoUnit = $pesoPorProducto[(int) $prod['id_producto']] ?? null;
                    if ($pesoUnit === null) { $pesoCompleto = false; break; }
                    $pesoTotalKg += $pesoUnit * (int) ($prod['cantidad'] ?? 0);
                }
                // Si falta el peso de AL MENOS un producto del carrito, no se
                // puede confiar en un peso total parcial — se cae al respaldo
                // por cantidad de unidades (ver tipoVehiculoMinimoPorCarga).
                if (!$pesoCompleto) $pesoTotalKg = null;
            }
        }

        // Selección automática — con bloqueos de fila (FOR UPDATE SKIP
        // LOCKED dentro de la función), segura dentro de esta misma
        // transacción de venta. También intenta agrupar con una salida
        // cercana ya en curso antes de ocupar un vehículo nuevo (PATCH
        // 30/30) — por eso manda las coordenadas de esta entrega.
        $asignacion = seleccionarRepartidorYVehiculoAutomatico(
            $conexion, $km, true, $pesoTotalKg, $totalUnidades ?: null, $lat_entrega, $lon_entrega
        );

        $id_repartidor_inicial = $asignacion['id_repartidor'] ?? null;
        $id_vehiculo = $asignacion['id_vehiculo'] ?? null;
        $nombre_estado_inicial = $id_repartidor_inicial ? 'ASIGNADA' : 'PENDIENTE';
        $id_grupo_entrega = null;

        // Tiempo estimado: si se agrupó con una salida cercana, se usa el
        // tiempo ya calculado para esa parada extra (llegar a la primera +
        // el margen de "bajarse y entregar"); si no, con el vehículo real
        // que se asignó, o (si se fue a la cola) con el tipo ideal para la
        // distancia, como estimado informativo — _auto_asignar.php lo
        // recalcula con el vehículo real en cuanto de verdad se asigne.
        $tiempo_estimado = null;
        if (!empty($asignacion['agrupada'])) {
            $tiempo_estimado = $asignacion['tiempo_estimado_minutos_sugerido'];
            $id_grupo_entrega = $asignacion['id_grupo_entrega'];
        } elseif ($km !== null) {
            $config = obtenerConfigDelivery($conexion);
            $tipoParaEstimar = $asignacion['tipo_vehiculo'] ?? tiposVehiculoPorDistancia($km, $config)[0];
            $tiempo_estimado = calcularTiempoEstimadoMinutos($km, $tipoParaEstimar, $config);
        }

        $stmtEstado = $conexion->prepare("SELECT id_estado FROM estado_entrega WHERE nombre = :nombre");
        $stmtEstado->execute([':nombre' => $nombre_estado_inicial]);
        $id_estado_pendiente = $stmtEstado->fetchColumn();
        if (!$id_estado_pendiente) {
            throw new Exception("No se encontró el estado '$nombre_estado_inicial' en la tabla estado_entrega");
        }

        $numero_seguimiento = 'DEL-' . date('Ymd') . '-' . str_pad($id_venta, 6, '0', STR_PAD_LEFT);
        $cliente_nombre = '';
        if (!empty($data['id_cliente'])) {
            $stmtCli = $conexion->prepare("SELECT nombre FROM clientes WHERE id_cliente = :id");
            $stmtCli->execute([':id' => $data['id_cliente']]);
            $cliente_nombre = $stmtCli->fetchColumn();
        } else {
            $cliente_nombre = 'Consumidor Final';
        }

        // Hora acordada con el cliente (opcional) — llega como "14:30",
        // se combina con la fecha de hoy para formar fecha_programada
        // completa. Es la hora que el cliente pidió, independiente del
        // tiempo estimado de viaje calculado arriba.
        //
        // Si el cliente NO pidió una hora específica, se asume que el
        // repartidor sale de inmediato: fecha_programada pasa a ser
        // "ahora + tiempo estimado de viaje" (la hora a la que llegaría si
        // sale ya mismo). Esto no es solo informativo — con esto, esta
        // entrega también entra en el cálculo de "ida y vuelta" cuando el
        // sistema evalúe si este repartidor le puede caber OTRA entrega
        // (con hora acordada real) antes de tener que salir para esta.
        // Solo se calcula así cuando SÍ quedó un repartidor asignado ahora
        // mismo — si se fue a la cola, no hay "ahora" desde cuándo contar
        // todavía; eso lo resuelve _auto_asignar.php cuando de verdad se
        // le asigne alguien.
        $hora_acordada = trim($data['hora_acordada'] ?? '');
        if ($hora_acordada !== '') {
            $fecha_programada = date('Y-m-d') . ' ' . $hora_acordada . ':00';
        } elseif ($id_repartidor_inicial && $tiempo_estimado !== null) {
            $fecha_programada = (new DateTime())->modify("+{$tiempo_estimado} minutes")->format('Y-m-d H:i:s');
        } else {
            $fecha_programada = null;
        }

        $sqlEnt = "INSERT INTO entregas
            (id_venta, id_cliente, id_sucursal, id_repartidor, id_vehiculo, numero_seguimiento, direccion_entrega, costo_entrega, creado_por, cliente_nombre, id_estado, fecha_asignada, fecha_programada, distancia_km, tiempo_estimado_minutos, latitud_entrega, longitud_entrega)
            VALUES (:id_venta, :id_cliente, :id_sucursal, :id_repartidor, :id_vehiculo, :numero_seg, :direccion, :costo, :creado_por, :cliente_nombre, :id_estado, NOW(), :fecha_programada, :distancia_km, :tiempo_estimado, :lat_entrega, :lon_entrega)
            RETURNING id_entrega";
        $stmtEnt = $conexion->prepare($sqlEnt);
        $stmtEnt->bindValue(':id_venta', $id_venta, PDO::PARAM_INT);
        $stmtEnt->bindValue(':id_cliente', $data['id_cliente'] ?? null, PDO::PARAM_INT);
        $stmtEnt->bindValue(':id_sucursal', $data['id_sucursal'], PDO::PARAM_INT);
        $stmtEnt->bindValue(':id_repartidor', $id_repartidor_inicial, PDO::PARAM_INT);
        $stmtEnt->bindValue(':id_vehiculo', $id_vehiculo, PDO::PARAM_INT);
        $stmtEnt->bindValue(':numero_seg', $numero_seguimiento, PDO::PARAM_STR);
        $stmtEnt->bindValue(':direccion', $data['direccion_entrega'], PDO::PARAM_STR);
        $stmtEnt->bindValue(':costo', (float)($data['costo_envio'] ?? 0), PDO::PARAM_STR);
        $stmtEnt->bindValue(':creado_por', $data['id_usuario'], PDO::PARAM_INT);
        $stmtEnt->bindValue(':cliente_nombre', $cliente_nombre, PDO::PARAM_STR);
        $stmtEnt->bindValue(':id_estado', $id_estado_pendiente, PDO::PARAM_INT);
        $stmtEnt->bindValue(':fecha_programada', $fecha_programada, $fecha_programada ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmtEnt->bindValue(':distancia_km', $km, $km !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmtEnt->bindValue(':tiempo_estimado', $tiempo_estimado, $tiempo_estimado !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmtEnt->bindValue(':lat_entrega', $lat_entrega, $lat_entrega !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmtEnt->bindValue(':lon_entrega', $lon_entrega, $lon_entrega !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmtEnt->execute();
        $id_entrega_nueva = $stmtEnt->fetchColumn();

        // Si se agrupó con una salida cercana (PATCH 30/30): la entrega
        // nueva apunta al id de la primera (la "ancla" del grupo), y si la
        // ancla todavía no tenía grupo (esta es la primera vez que se le
        // suma una parada), también se le pone su propio id como grupo —
        // así "todas las entregas con el mismo id_grupo_entrega van juntas"
        // funciona sin importar cuál se agregó primero.
        if ($id_grupo_entrega) {
            $conexion->prepare("UPDATE entregas SET id_grupo_entrega = :grupo WHERE id_entrega = :id")
                ->execute([':grupo' => $id_grupo_entrega, ':id' => $id_entrega_nueva]);
            $conexion->prepare("UPDATE entregas SET id_grupo_entrega = :grupo WHERE id_entrega = :id AND id_grupo_entrega IS NULL")
                ->execute([':grupo' => $id_grupo_entrega, ':id' => $id_grupo_entrega]);
        }

        // Marcar el vehículo asignado como EN_USO (si se agrupó, ya estaba
        // EN_USO por la entrega ancla — este UPDATE es un no-op inofensivo)
        if ($id_vehiculo) {
            $stmtVeh = $conexion->prepare("UPDATE vehiculos SET estado = 'EN_USO' WHERE id_vehiculo = :id");
            $stmtVeh->execute([':id' => $id_vehiculo]);
        }

        // Notificación al cliente (PATCH 31/31) — le avisa desde ya si
        // quedó asignado (con quién y cuándo llegaría, aprox.) o si se fue
        // a la cola de espera.
        require_once __DIR__ . '/../notificaciones/_notificaciones.php';
        if ($id_repartidor_inicial) {
            $etaTxt = $fecha_programada ? (new DateTime($fecha_programada))->format('h:i a') : null;
            crearNotificacionCliente(
                $conexion, (int) ($data['id_cliente'] ?? 0), (int) $id_entrega_nueva, 'ASIGNADA',
                'Repartidor asignado a tu pedido',
                "Tu pedido {$numero_seguimiento} ya tiene repartidor asignado." . ($etaTxt ? " Llegaría aproximadamente a las {$etaTxt}." : "")
            );
        } else {
            crearNotificacionCliente(
                $conexion, (int) ($data['id_cliente'] ?? 0), (int) $id_entrega_nueva, 'EN_COLA',
                'Tu pedido está en cola',
                "Tu pedido {$numero_seguimiento} quedó en la cola de espera — te avisamos apenas se le asigne un repartidor."
            );
        }
    }

    $conexion->commit();
    echo json_encode([
        'success' => true,
        'id_venta' => $id_venta,
        'numero_documento' => $data['numero_documento']
    ]);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    $conexion->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error de BD: ' . $e->getMessage()]);
}
