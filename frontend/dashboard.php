<?php
include '../backend/conexion.php';

// Asegurar que id_usuario esté en sesión
if (!isset($_SESSION['id_usuario']) && isset($_SESSION['usuario'])) {
    $stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE usuario = ?");
    $stmt->execute([$_SESSION['usuario']]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['id_usuario'] = $user['id_usuario'];
    }
}

// ================= FUNCIONES =================
function obtener_metrica_pdo($conexion, $query, $default_value = 0)
{
    try {
        $stmt = $conexion->query($query);
        $result = $stmt->fetchColumn();
        return $result !== false ? (is_numeric($result) ? $result : $default_value) : $default_value;
    } catch (PDOException $e) {
        return $default_value;
    }
}

function format_large_number($n)
{
    return number_format($n, 2, ',', '.');
}

function tiempo_transcurrido($fecha_db)
{
    $fecha_registro = new DateTime($fecha_db);
    $fecha_actual = new DateTime();
    $diferencia = $fecha_actual->diff($fecha_registro);
    if ($diferencia->y >= 1) return 'hace ' . $diferencia->y . ' año' . ($diferencia->y > 1 ? 's' : '');
    if ($diferencia->m >= 1) return 'hace ' . $diferencia->m . ' mes' . ($diferencia->m > 1 ? 'es' : '');
    if ($diferencia->d >= 1) return 'hace ' . $diferencia->d . ' día' . ($diferencia->d > 1 ? 's' : '');
    if ($diferencia->h >= 1) return 'hace ' . $diferencia->h . ' hora' . ($diferencia->h > 1 ? 's' : '');
    if ($diferencia->i >= 1) return 'hace ' . $diferencia->i . ' min';
    return 'hace unos segundos';
}

function abreviar_numero($n)
{
    if ($n >= 1000000) {
        return number_format($n / 1000000, 1, '.', '') . 'M';
    } else if ($n >= 1000) {
        return number_format($n / 1000, 1, '.', '') . 'K';
    }
    return number_format($n, 0, '', '');
}

// ================= MÉTRICAS =================

// Ventas del mes
$ventas_mes = obtener_metrica_pdo($conexion, "
    SELECT COALESCE(SUM(total),0)
    FROM ventas
    WHERE fecha >= CURRENT_DATE - INTERVAL '30 days'
");

// Inventario total
$inventario_total = obtener_metrica_pdo($conexion, "
    SELECT COALESCE(SUM(cantidad),0)
    FROM inventario
");

// Stock bajo
$stock_bajo = obtener_metrica_pdo($conexion, "
    SELECT COUNT(*)
    FROM inventario
    WHERE cantidad <= 10
");

// Próximos a vencer
$por_vencer = obtener_metrica_pdo($conexion, "
    SELECT COUNT(*)
    FROM lotes
    WHERE fecha_vencimiento <= CURRENT_DATE + INTERVAL '30 days'
    AND estado = 'ACTIVO'
");

// ================= GRÁFICO VENTAS =================
$labels = [];
$data = [];

$query = "
    SELECT TO_CHAR(fecha, 'Mon') mes, SUM(total) total
    FROM ventas
    WHERE fecha >= CURRENT_DATE - INTERVAL '6 months'
    GROUP BY mes
";

foreach ($conexion->query($query) as $row) {
    $labels[] = $row['mes'];
    $data[] = floatval($row['total']);
}

// ================= TOP MEDICAMENTOS =================
$top_labels = [];
$top_data = [];

$query_top = "
    SELECT m.nombre, SUM(dv.cantidad) total
    FROM detalle_venta dv
    JOIN lotes l ON dv.id_lote = l.id_lote
    JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
    GROUP BY m.nombre
    ORDER BY total DESC
    LIMIT 5
";

foreach ($conexion->query($query_top) as $row) {
    $top_labels[] = $row['nombre'];
    $top_data[] = intval($row['total']);
}

// ================= INICIOS DE SESIÓN =================
$inicios_sesion = [];
try {
    $query_sesiones = "SELECT s.fecha_inicio AS fecha, u.nombre, u.imagen_url 
                       FROM sesiones s 
                       JOIN usuarios u ON s.id_usuario = u.id_usuario 
                       ORDER BY s.fecha_inicio DESC 
                       LIMIT 5";

    $stmt_accesos = $conexion->prepare($query_sesiones);
    $stmt_accesos->execute();
    $inicios_sesion = $stmt_accesos->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $inicios_sesion = [];
}

// ================= ALERTAS DE INVENTARIO =================
$alertas = [];
try {
    $stmt = $conexion->query("SELECT * FROM generar_alertas_inventario()");
    $alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $alertas = [];
}

// ================= NOTIFICACIONES DE PAGOS =================
$notificaciones_pagos = [];
try {
    $stmt = $conexion->prepare("
        SELECT * FROM notificaciones_sistema 
        WHERE id_usuario = ? AND leida = false 
        ORDER BY fecha_creacion DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['id_usuario']]);
    $notificaciones_pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $notificaciones_pagos = [];
}

// Combinar alertas de inventario con notificaciones de pagos para mostrar en la tabla
$todas_las_alertas = [];

// Agregar alertas de inventario
foreach ($alertas as $a) {
    $todas_las_alertas[] = [
        'tipo' => 'inventario',
        'tipo_alerta' => $a['tipo_alerta'],
        'medicamento_nombre' => $a['medicamento_nombre'] ?? null,
        'numero_lote' => $a['numero_lote'] ?? null,
        'cantidad' => $a['cantidad'] ?? null,
        'stock_minimo' => $a['stock_minimo'] ?? null,
        'fecha_vencimiento' => $a['fecha_vencimiento'] ?? null,
        'dias_restantes' => $a['dias_restantes'] ?? null,
        'nivel_riesgo' => $a['nivel_riesgo'] ?? null,
        'entidad_emisora' => $a['entidad_emisora'] ?? null,
        'numero_alerta' => $a['numero_alerta'] ?? null,
        'detalle' => '',
        'info_adicional' => '',
        'fecha_creacion' => null,
        'url_destino' => $a['url_destino'] ?? '#'
    ];
}

// Agregar notificaciones de pagos
foreach ($notificaciones_pagos as $np) {
    $todas_las_alertas[] = [
        'tipo' => 'pago',
        'tipo_alerta' => 'PAGO_REGISTRADO',
        'medicamento_nombre' => null,
        'numero_lote' => null,
        'cantidad' => null,
        'stock_minimo' => null,
        'fecha_vencimiento' => null,
        'dias_restantes' => null,
        'nivel_riesgo' => null,
        'entidad_emisora' => null,
        'numero_alerta' => null,
        'detalle' => $np['titulo'],
        'info_adicional' => $np['mensaje'],
        'fecha_creacion' => $np['fecha_creacion'],
        'url_destino' => 'historial_ventas'
    ];
}

// Ordenar por fecha (las más recientes primero)
usort($todas_las_alertas, function($a, $b) {
    $fecha_a = $a['fecha_creacion'] ?? $a['fecha_vencimiento'] ?? '1970-01-01';
    $fecha_b = $b['fecha_creacion'] ?? $b['fecha_vencimiento'] ?? '1970-01-01';
    return strtotime($fecha_b) - strtotime($fecha_a);
});

// Limitar a 15 alertas para no sobrecargar la tabla
$todas_las_alertas = array_slice($todas_las_alertas, 0, 15);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>
    /* ================= CONTENEDOR ================= */
    .dashboard-container {
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 20px;
        font-family: 'Poppins', sans-serif;
        opacity: 0;
        transform: translateY(15px);
        animation: fadeSlideIn 0.8s ease-out forwards;
    }

    .dashboard-content-wrapper {
        display: flex;
        gap: 25px;
        align-items: flex-start;
    }

    .sidebar-panel {
        width: 320px;
        position: sticky;
        top: 20px;
    }

    .card-large {
        background: #fff;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .session-item {
        display: flex;
        align-items: center;
        padding: 20px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .user-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: #e0f2fe;
        color: #0369a1;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        margin-right: 15px;
    }

    @keyframes fadeSlideIn {
        0% {
            opacity: 0;
            transform: translateY(25px);
        }
        100% {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .cards-small {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .card-small {
        flex: 1;
        min-width: 250px;
        padding: 25px;
        border-radius: 10px;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border-left: 5px solid transparent;
        cursor: pointer;
        opacity: 0;
        animation: cardFadeIn 0.6s ease forwards;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .card-small:nth-child(1) { animation-delay: 0.1s; }
    .card-small:nth-child(2) { animation-delay: 0.2s; }
    .card-small:nth-child(3) { animation-delay: 0.3s; }
    .card-small:nth-child(4) { animation-delay: 0.4s; }

    @keyframes cardFadeIn {
        to { opacity: 1; }
    }

    .card-small:hover {
        transform: translateY(-8px) scale(1.03);
        box-shadow: 0 18px 28px rgba(0, 0, 0, 0.2);
    }

    .card-icon {
        font-size: 2.4em;
        margin-right: 15px;
    }

    .card-content {
        text-align: right;
        flex-grow: 1;
    }

    .card-content h3 {
        margin: 0;
        font-size: 0.9em;
        color: #6c757d;
    }

    .card-content p {
        font-size: 2.4em;
        font-weight: 600;
        margin: 0;
    }

    .card-blue { border-left-color: #007bff; }
    .card-blue .card-icon { color: #007bff; }
    .card-green { border-left-color: #28a745; }
    .card-green .card-icon { color: #28a745; }
    .card-orange { border-left-color: #fd7e14; }
    .card-orange .card-icon { color: #fd7e14; }
    .card-red { border-left-color: #dc3545; }
    .card-red .card-icon { color: #dc3545; }

    .cards-large {
        display: flex;
        gap: 20px;
    }

    .card-large {
        flex: 1;
        padding: 20px;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        opacity: 0;
        transform: scale(0.95);
        animation: chartFade 1s ease forwards;
    }

    .card-large:nth-child(2) { animation-delay: 0.2s; }

    @keyframes chartFade {
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    canvas {
        max-width: 100% !important;
        height: auto !important;
    }

    /* Estilos para filas clickeables */
    .alerta-fila {
        cursor: pointer;
        transition: background-color 0.2s ease;
    }
    .alerta-fila:hover {
        background-color: rgba(0, 0, 0, 0.02) !important;
    }
    .table-danger.alerta-fila:hover {
        background-color: #f5c6cb !important;
    }
    .table-warning.alerta-fila:hover {
        background-color: #ffe4b5 !important;
    }
    .table-pago.alerta-fila:hover {
        background-color: #d4edda !important;
    }

    .badge-pago {
        background-color: #28a745 !important;
        color: white !important;
    }

    .table-pago {
        background-color: #e8f5e9 !important;
    }
    .table-pago td {
        border-color: #c8e6c9;
    }
</style>

<section class="dashboard-container">

    <div class="mb-4">
        <h2 class="mb-0 text-success">
            <span class="material-symbols-rounded align-middle me-2">monitoring</span>
            Dashboard Operativo de Farmacia
        </h2>
        <p class="text-muted mb-0">Control y monitoreo en tiempo real del sistema farmacéutico</p>
    </div>

    <div style="display: flex; gap: 20px; width: 100%; margin-bottom: 20px;">

        <div style="flex: 1; display: flex; flex-direction: column; gap: 20px; min-width: 0;">

            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
                <div class="card-small card-blue">
                    <div class="card-icon"><i class="fas fa-dollar-sign"></i></div>
                    <div class="card-content">
                        <h3>Ventas del Mes</h3>
                        <p>$<?php echo abreviar_numero($ventas_mes); ?></p>
                    </div>
                </div>
                <div class="card-small card-green">
                    <div class="card-icon"><i class="fas fa-pills"></i></div>
                    <div class="card-content">
                        <h3>Inventario Total</h3>
                        <p><?php echo abreviar_numero($inventario_total); ?></p>
                    </div>
                </div>
                <div class="card-small card-orange">
                    <div class="card-icon"><i class="fas fa-exclamation-circle"></i></div>
                    <div class="card-content">
                        <h3>Stock Bajo</h3>
                        <p><?php echo abreviar_numero($stock_bajo); ?></p>
                    </div>
                </div>
                <div class="card-small card-red">
                    <div class="card-icon"><i class="fas fa-clock"></i></div>
                    <div class="card-content">
                        <h3>Proximos a Vencer</h3>
                        <p><?php echo abreviar_numero($por_vencer); ?></p>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="card-large">
                    <h6 class="fw-bold mb-3">Ventas últimos 6 Meses</h6>
                    <canvas id="ventasChart" style="max-height: 220px;"></canvas>
                </div>
                <div class="card-large">
                    <h6 class="fw-bold mb-3">Top Medicamentos Vendidos</h6>
                    <canvas id="topChart" style="max-height: 220px;"></canvas>
                </div>
            </div>
        </div>

        <div style="width: 280px; flex-shrink: 0;">
            <div class="card-large" style="height: 100%; display: flex; flex-direction: column; background: #fff; border-radius: 15px; padding: 20px; border: 1px solid #e2e8f0;">
                <h6 class="fw-bold mb-3" style="color: #1e293b;">
                    <i class="fas fa-user-clock text-primary me-2"></i>Accesos Recientes
                </h6>
                <div class="session-list" style="flex: 1; overflow-y: auto;">

                    <?php if (empty($inicios_sesion)): ?>
                        <div class="text-center py-4">
                            <p class="text-muted small">Sin actividad reciente</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($inicios_sesion as $s): ?>
                            <div class="session-item" style="display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f5f9;">

                                <div style="width: 40px; height: 40px; border-radius: 50px; margin-right: 12px; flex-shrink: 0; overflow: hidden;">
                                    <img src="<?php echo !empty($s['imagen_url']) ? $s['imagen_url'] : '../assets/img/usuarios/default.png'; ?>"
                                        alt="Perfil"
                                        style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                                <div style="overflow: hidden;">
                                    <div class="fw-bold" style="color: #334155; font-size: 0.85rem; white-space: nowrap; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($s['nombre']); ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #94a3b8;">
                                        <i class="far fa-clock me-1"></i>
                                        <?php echo tiempo_transcurrido($s['fecha']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- ALERTAS DEL SISTEMA (Inventario + Pagos) - Fila clickeable sin botón -->
    <div class="card-large alert-table shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
                <i class="fas fa-bell text-danger me-2"></i>Alertas del Sistema
            </h5>
            <?php if (count($notificaciones_pagos) > 0): ?>
                <span class="badge bg-success rounded-pill"><?php echo count($notificaciones_pagos); ?> pagos recientes</span>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Detalle</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($todas_las_alertas)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <i class="fas fa-check-circle text-success d-block mb-2" style="font-size: 2rem;"></i>
                                No hay alertas ni notificaciones en este momento.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($todas_las_alertas as $alerta):
                            $clase_fila = 'alerta-fila';
                            $badge_color = 'bg-secondary';
                            $icono = 'fa-bell';
                            $tipo_texto = '';
                            $descripcion = '';
                            $detalle_texto = '';
                            $fecha_mostrar = '';
                            $url_destino = '#';
                            
                            if ($alerta['tipo'] == 'inventario') {
                                if ($alerta['tipo_alerta'] == 'VENCIMIENTO') {
                                    $clase_fila .= ' table-danger';
                                    $badge_color = 'bg-danger';
                                    $icono = 'fa-calendar-times';
                                    $tipo_texto = 'Vencimiento';
                                    $descripcion = $alerta['medicamento_nombre'] ?? 'Medicamento';
                                    $detalle_texto = "Vence en " . ($alerta['dias_restantes'] ?? '?') . " días - Lote: " . ($alerta['numero_lote'] ?? 'N/A');
                                    $fecha_mostrar = date('d/m/Y', strtotime($alerta['fecha_vencimiento']));
                                    $url_destino = "menuprincipal.php?mod={$alerta['url_destino']}";
                                } 
                                elseif ($alerta['tipo_alerta'] == 'STOCK_CRITICO') {
                                    $clase_fila .= ' table-warning';
                                    $badge_color = 'bg-warning text-dark';
                                    $icono = 'fa-exclamation-triangle';
                                    $tipo_texto = 'Stock Crítico';
                                    $descripcion = $alerta['medicamento_nombre'] ?? 'Medicamento';
                                    $detalle_texto = "Stock: " . ($alerta['cantidad'] ?? '0') . " / " . ($alerta['stock_minimo'] ?? '0') . " und - Lote: " . ($alerta['numero_lote'] ?? 'N/A');
                                    $fecha_mostrar = 'Requiere atención urgente';
                                    $url_destino = "menuprincipal.php?mod={$alerta['url_destino']}";
                                } 
                                elseif ($alerta['tipo_alerta'] == 'STOCK_AGOTADO') {
                                    $clase_fila .= ' table-danger';
                                    $badge_color = 'bg-dark';
                                    $icono = 'fa-box-open';
                                    $tipo_texto = 'Stock Agotado';
                                    $descripcion = $alerta['medicamento_nombre'] ?? 'Medicamento';
                                    $detalle_texto = "Stock AGOTADO - Lote: " . ($alerta['numero_lote'] ?? 'N/A');
                                    $fecha_mostrar = 'Sin existencias';
                                    $url_destino = "menuprincipal.php?mod={$alerta['url_destino']}";
                                } 
                                elseif ($alerta['tipo_alerta'] == 'RECALL') {
                                    $clase_fila .= ' table-danger';
                                    $badge_color = 'bg-danger';
                                    $icono = 'fa-skull-crossbones';
                                    $tipo_texto = 'Recall Sanitario';
                                    $descripcion = $alerta['entidad_emisora'] ?? 'Entidad';
                                    $detalle_texto = "Alerta: " . ($alerta['numero_alerta'] ?? 'N/A') . " - Riesgo: " . ($alerta['nivel_riesgo'] ?? 'ALTO');
                                    $fecha_mostrar = 'Revisar inmediatamente';
                                    $url_destino = "menuprincipal.php?mod={$alerta['url_destino']}";
                                }
                            } else {
                                // Notificación de pago
                                $clase_fila .= ' table-pago';
                                $badge_color = 'badge-pago';
                                $icono = 'fa-money-bill-wave';
                                $tipo_texto = 'Pago Registrado';
                                $descripcion = $alerta['detalle'];
                                $detalle_texto = $alerta['info_adicional'];
                                $fecha_mostrar = date('d/m/Y H:i', strtotime($alerta['fecha_creacion']));
                                $url_destino = "menuprincipal.php?mod={$alerta['url_destino']}";
                            }
                        ?>
                            <tr class="<?php echo $clase_fila; ?>" onclick="window.location.href='<?php echo $url_destino; ?>'">
                                <td>
                                    <span class="badge <?php echo $badge_color; ?> shadow-sm px-3 py-2" style="border-radius: 30px;">
                                        <i class="fas <?php echo $icono; ?> me-1"></i>
                                        <?php echo $tipo_texto; ?>
                                    </span>
                                </th>
                                <td class="fw-bold"><?php echo htmlspecialchars($descripcion); ?></td>
                                <td><?php echo htmlspecialchars($detalle_texto); ?></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($fecha_mostrar); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    new Chart(document.getElementById('ventasChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: 'Ventas',
                data: <?php echo json_encode($data); ?>,
                borderColor: 'blue',
                fill: true
            }]
        }
    });

    new Chart(document.getElementById('topChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($top_labels); ?>,
            datasets: [{
                label: 'Cantidad',
                data: <?php echo json_encode($top_data); ?>,
                backgroundColor: 'green'
            }]
        }
    });
</script>