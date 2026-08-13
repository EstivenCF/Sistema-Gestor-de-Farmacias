<?php
// reporte_vencimientos.php - Reporte de lotes por vencimiento
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../backend/conexion.php';

if (!isset($_SESSION['id_sesion']) || !isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

// Obtener filtros
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$estado_lote = $_GET['estado_lote'] ?? '';
$id_medicamento = $_GET['medicamento'] ?? '';
$id_laboratorio = $_GET['laboratorio'] ?? '';
$id_categoria = $_GET['categoria'] ?? '';

// Construir consulta principal
$sql = "SELECT l.id_lote, l.numero_lote, l.fecha_vencimiento, l.cantidad_actual, l.estado, l.ubicacion,
               m.id_medicamento, m.nombre_completo as medicamento, m.nombre, m.concentracion,
               cat.nombre as categoria, lab.nombre as laboratorio,
               pr.nombre as presentacion,
               CASE 
                   WHEN l.fecha_vencimiento < CURRENT_DATE THEN 'VENCIDO'
                   WHEN l.fecha_vencimiento <= CURRENT_DATE + INTERVAL '30 days' THEN 'PROXIMO A VENCER'
                   ELSE 'VIGENTE'
               END as alerta_vencimiento,
               (l.fecha_vencimiento - CURRENT_DATE) as dias_restantes
        FROM lotes l
        JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
        LEFT JOIN categorias cat ON m.id_categoria = cat.id_categoria
        LEFT JOIN laboratorios lab ON m.id_laboratorio = lab.id_laboratorio
        LEFT JOIN presentaciones pr ON m.id_presentacion = pr.id_presentacion
        WHERE 1=1";

$params = [];

if (!empty($fecha_desde)) {
    $sql .= " AND l.fecha_vencimiento >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}
if (!empty($fecha_hasta)) {
    $sql .= " AND l.fecha_vencimiento <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}
if (!empty($estado_lote)) {
    $sql .= " AND l.estado = :estado_lote";
    $params[':estado_lote'] = $estado_lote;
}
if (!empty($id_medicamento)) {
    $sql .= " AND m.id_medicamento = :id_medicamento";
    $params[':id_medicamento'] = $id_medicamento;
}
if (!empty($id_laboratorio)) {
    $sql .= " AND m.id_laboratorio = :id_laboratorio";
    $params[':id_laboratorio'] = $id_laboratorio;
}
if (!empty($id_categoria)) {
    $sql .= " AND m.id_categoria = :id_categoria";
    $params[':id_categoria'] = $id_categoria;
}

$sql .= " ORDER BY l.fecha_vencimiento ASC, alerta_vencimiento DESC";
$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estadísticas
$total_lotes = count($lotes);
$lotes_vencidos = 0;
$lotes_proximos = 0;
$lotes_vigentes = 0;
$unidades_vencidas = 0;
$unidades_proximas = 0;

foreach ($lotes as $l) {
    if ($l['alerta_vencimiento'] == 'VENCIDO') {
        $lotes_vencidos++;
        $unidades_vencidas += $l['cantidad_actual'];
    } elseif ($l['alerta_vencimiento'] == 'PROXIMO A VENCER') {
        $lotes_proximos++;
        $unidades_proximas += $l['cantidad_actual'];
    } else {
        $lotes_vigentes++;
    }
}

// Datos para gráfico de vencimientos por mes
$stmt_mes = $conexion->query("
    SELECT TO_CHAR(fecha_vencimiento, 'YYYY-MM') as mes, COUNT(*) as cantidad_lotes, SUM(cantidad_actual) as total_unidades
    FROM lotes
    WHERE fecha_vencimiento IS NOT NULL
    GROUP BY TO_CHAR(fecha_vencimiento, 'YYYY-MM')
    ORDER BY mes ASC
");
$vencimientos_mes = $stmt_mes->fetchAll(PDO::FETCH_ASSOC);
$meses = array_column($vencimientos_mes, 'mes');
$lotes_por_mes = array_column($vencimientos_mes, 'cantidad_lotes');
$unidades_por_mes = array_column($vencimientos_mes, 'total_unidades');

// Listas para filtros
$medicamentos = $conexion->query("SELECT id_medicamento, nombre_completo FROM medicamentos ORDER BY nombre_completo")->fetchAll();
$laboratorios = $conexion->query("SELECT id_laboratorio, nombre FROM laboratorios WHERE activo = TRUE ORDER BY nombre")->fetchAll();
$categorias = $conexion->query("SELECT id_categoria, nombre FROM categorias WHERE activo = TRUE ORDER BY nombre")->fetchAll();
$estados_lote = ['ACTIVO', 'VENCIDO', 'RETIRADO', 'MERMA', 'DAÑADO'];

// =============================================================================
// NUEVO (Tarea 5) - Indicadores gerenciales del proceso estratégico (Pantalla #07)
// Reutiliza la librería del proceso para el valor en riesgo ACTUAL (mismo
// cálculo que usan las Pantallas #02/#03), y consulta accion_recuperacion
// para medir qué tan efectivas fueron las acciones ya ejecutadas.
// =============================================================================
require_once __DIR__ . '/../../backend/inventario/riesgo_vencimiento_lib.php';
$umbralesProceso = obtenerUmbralesVencimiento($conexion);
$lotesEnRiesgoActual = listarLotesEnRiesgo($conexion, $umbralesProceso);
$valorTotalEnRiesgoActual = array_sum(array_column($lotesEnRiesgoActual, 'valor_en_riesgo'));

// Valor recuperado: solo acciones ya COMPLETADAS (promociones "en curso" no
// cuentan todavía, porque su resultado real aún no se conoce)
$valorRecuperado = (float)$conexion->query("
    SELECT COALESCE(SUM(valor_recuperado_estimado), 0) FROM accion_recuperacion WHERE estado = 'COMPLETADA'
")->fetchColumn();

// Valor en acciones que ya se están ejecutando pero aún no cierran (promociones activas)
$valorEnCurso = (float)$conexion->query("
    SELECT COALESCE(SUM(valor_en_riesgo), 0) FROM accion_recuperacion WHERE estado = 'EN_EJECUCION'
")->fetchColumn();

// Pérdida confirmada: acciones marcadas explícitamente SIN_EFECTO, más lotes
// que ya vencieron sin ninguna acción COMPLETADA que los haya cubierto.
$valorPerdidaConfirmada = (float)$conexion->query("
    SELECT COALESCE(SUM(ar.valor_en_riesgo), 0)
    FROM accion_recuperacion ar
    WHERE ar.estado = 'SIN_EFECTO' OR ar.tipo_accion = 'PROVISION_PERDIDA'
")->fetchColumn();

$valorLotesVencidosSinAccion = (float)$conexion->query("
    SELECT COALESCE(SUM(l.cantidad_actual * l.costo_unitario), 0)
    FROM lotes l
    WHERE l.estado = 'VENCIDO'
      AND NOT EXISTS (
          SELECT 1 FROM accion_recuperacion ar
          WHERE ar.id_lote = l.id_lote AND ar.estado = 'COMPLETADA'
      )
")->fetchColumn();
$valorPerdidaConfirmada += $valorLotesVencidosSinAccion;

// Efectividad: de lo que YA se cerró (recuperado + perdido), qué % se salvó.
// No incluye lo que sigue PENDIENTE/EN_EJECUCION porque su resultado aún no se conoce.
$totalCerrado = $valorRecuperado + $valorPerdidaConfirmada;
$efectividadRecuperacion = $totalCerrado > 0 ? round(($valorRecuperado / $totalCerrado) * 100, 1) : 0;

// Acciones de recuperación por tipo (para el gráfico de barras horizontal)
$accionesPorTipo = $conexion->query("
    SELECT tipo_accion, COUNT(*) AS total
    FROM accion_recuperacion
    GROUP BY tipo_accion
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Categorías con mayor pérdida (mismo criterio que la pérdida confirmada de arriba)
$categoriasConMayorPerdida = $conexion->query("
    SELECT cat.nombre AS categoria, SUM(valor_perdido) AS valor_perdido
    FROM (
        SELECT m.id_categoria, ar.valor_en_riesgo AS valor_perdido
        FROM accion_recuperacion ar
        JOIN lotes l ON ar.id_lote = l.id_lote
        JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
        WHERE ar.estado = 'SIN_EFECTO'
        UNION ALL
        SELECT m.id_categoria, (l.cantidad_actual * l.costo_unitario) AS valor_perdido
        FROM lotes l
        JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
        WHERE l.estado = 'VENCIDO'
          AND NOT EXISTS (SELECT 1 FROM accion_recuperacion ar2 WHERE ar2.id_lote = l.id_lote AND ar2.estado = 'COMPLETADA')
    ) perdidas
    JOIN categorias cat ON perdidas.id_categoria = cat.id_categoria
    GROUP BY cat.nombre
    ORDER BY valor_perdido DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
$valorPerdidaTotalCategorias = array_sum(array_column($categoriasConMayorPerdida, 'valor_perdido')) ?: 1;

$etiquetasTipoAccion = [
    'PROMOCION' => 'Promociones', 'REDISTRIBUCION' => 'Redistribuciones', 'COMBO' => 'Combos',
    'DEVOLUCION_PROVEEDOR' => 'Devoluciones a proveedor', 'DONACION' => 'Donaciones',
    'PROVISION_PERDIDA' => 'Provisión de pérdida', 'DESTRUCCION' => 'Destrucciones',
];
$maxAcciones = !empty($accionesPorTipo) ? max(array_column($accionesPorTipo, 'total')) : 1;
?>

<div class="reporte-vencimientos-container">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
        <h2 class="h3">
            <span class="material-symbols-rounded align-middle">event_busy</span>
            Reporte de Vencimientos
        </h2>
        <div>
            <button class="btn btn-outline-success btn-sm" id="exportarCSV">
                <span class="material-symbols-rounded fs-6">file_download</span> Exportar CSV
            </button>
            <button class="btn btn-outline-primary btn-sm ms-2" id="exportarExcel">
                <span class="material-symbols-rounded fs-6">table_chart</span> Exportar Excel
            </button>
        </div>
    </div>

    <!-- Tarjetas resumen -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <span class="material-symbols-rounded text-danger">warning</span>
                    <h5 class="mt-2 mb-0"><?php echo $lotes_vencidos; ?></h5>
                    <small class="text-muted">Lotes vencidos</small>
                    <div class="small text-muted"><?php echo number_format($unidades_vencidas); ?> unidades</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <span class="material-symbols-rounded text-warning">schedule</span>
                    <h5 class="mt-2 mb-0"><?php echo $lotes_proximos; ?></h5>
                    <small class="text-muted">Próximos a vencer (30 días)</small>
                    <div class="small text-muted"><?php echo number_format($unidades_proximas); ?> unidades</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <span class="material-symbols-rounded text-success">check_circle</span>
                    <h5 class="mt-2 mb-0"><?php echo $lotes_vigentes; ?></h5>
                    <small class="text-muted">Lotes vigentes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <span class="material-symbols-rounded text-primary">inventory</span>
                    <h5 class="mt-2 mb-0"><?php echo $total_lotes; ?></h5>
                    <small class="text-muted">Total lotes registrados</small>
                </div>
            </div>
        </div>
    </div>

    <!-- NUEVO (Tarea 5): Indicadores gerenciales del proceso estratégico -->
    <div class="mb-4">
        <h5 class="text-muted text-uppercase small fw-bold mb-3">
            <span class="material-symbols-rounded align-middle me-1" style="font-size:18px;">insights</span>
            Indicadores gerenciales - Proceso estratégico de vencimientos
        </h5>

        <div class="row g-3 mb-3">
            <div class="col-md-3 col-sm-6">
                <div class="card shadow-sm border-0 rounded-4 h-100" style="border-left:4px solid #dc3545 !important;">
                    <div class="card-body">
                        <small class="text-muted d-block">Valor total en riesgo (actual)</small>
                        <h4 class="mb-0 text-danger">RD$ <?php echo number_format($valorTotalEnRiesgoActual, 2); ?></h4>
                        <small class="text-muted">lotes activos con stock, hoy</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card shadow-sm border-0 rounded-4 h-100" style="border-left:4px solid #198754 !important;">
                    <div class="card-body">
                        <small class="text-muted d-block">Valor recuperado</small>
                        <h4 class="mb-0 text-success">RD$ <?php echo number_format($valorRecuperado, 2); ?></h4>
                        <small class="text-muted">acciones completadas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card shadow-sm border-0 rounded-4 h-100" style="border-left:4px solid #0d6efd !important;">
                    <div class="card-body">
                        <small class="text-muted d-block">Efectividad de recuperación</small>
                        <h4 class="mb-0 text-primary"><?php echo $efectividadRecuperacion; ?>%</h4>
                        <small class="text-muted">de lo ya cerrado (recuperado vs. perdido)</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card shadow-sm border-0 rounded-4 h-100" style="border-left:4px solid #6c757d !important;">
                    <div class="card-body">
                        <small class="text-muted d-block">Pérdida confirmada</small>
                        <h4 class="mb-0">RD$ <?php echo number_format($valorPerdidaConfirmada, 2); ?></h4>
                        <small class="text-muted">sin efecto o vencidos sin acción</small>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($valorEnCurso > 0): ?>
            <div class="alert alert-info small py-2 mb-3">
                <span class="material-symbols-rounded align-middle me-1" style="font-size:16px;">hourglass_top</span>
                Hay <strong>RD$ <?php echo number_format($valorEnCurso, 2); ?></strong> en acciones actualmente EN EJECUCIÓN (promociones activas cuyo resultado aún no se puede medir).
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-body">
                        <h6 class="card-title">Acciones de recuperación por tipo</h6>
                        <?php if (empty($accionesPorTipo)): ?>
                            <p class="text-muted small mb-0">Todavía no se ha registrado ninguna acción de recuperación.</p>
                        <?php else: ?>
                            <?php foreach ($accionesPorTipo as $a): ?>
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between small">
                                        <span><?php echo $etiquetasTipoAccion[$a['tipo_accion']] ?? $a['tipo_accion']; ?></span>
                                        <strong><?php echo $a['total']; ?></strong>
                                    </div>
                                    <div class="progress" style="height:8px;">
                                        <div class="progress-bar bg-primary" style="width: <?php echo round(($a['total'] / $maxAcciones) * 100); ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-body">
                        <h6 class="card-title">Categorías con mayor pérdida</h6>
                        <?php if (empty($categoriasConMayorPerdida)): ?>
                            <p class="text-muted small mb-0">No hay pérdidas confirmadas registradas todavía.</p>
                        <?php else: ?>
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Categoría</th><th class="text-end">Valor perdido</th><th class="text-end">Participación</th></tr></thead>
                                <tbody>
                                    <?php foreach ($categoriasConMayorPerdida as $c): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($c['categoria']); ?></td>
                                            <td class="text-end">RD$ <?php echo number_format($c['valor_perdido'], 2); ?></td>
                                            <td class="text-end">
                                                <span class="badge bg-danger bg-opacity-75"><?php echo round(($c['valor_perdido'] / $valorPerdidaTotalCategorias) * 100); ?>%</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="mod" value="reporte_vencimientos">
                <div class="col-md-2">
                    <label class="form-label small">Desde (vencimiento)</label>
                    <input type="date" name="fecha_desde" class="form-control" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Hasta (vencimiento)</label>
                    <input type="date" name="fecha_hasta" class="form-control" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Estado lote</label>
                    <select name="estado_lote" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($estados_lote as $est): ?>
                            <option value="<?php echo $est; ?>" <?php echo $estado_lote == $est ? 'selected' : ''; ?>><?php echo $est; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Medicamento</label>
                    <select name="medicamento" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($medicamentos as $m): ?>
                            <option value="<?php echo $m['id_medicamento']; ?>" <?php echo $id_medicamento == $m['id_medicamento'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['nombre_completo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Laboratorio</label>
                    <select name="laboratorio" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($laboratorios as $lab): ?>
                            <option value="<?php echo $lab['id_laboratorio']; ?>" <?php echo $id_laboratorio == $lab['id_laboratorio'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($lab['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Categoría</label>
                    <select name="categoria" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['id_categoria']; ?>" <?php echo $id_categoria == $cat['id_categoria'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="?mod=reporte_vencimientos" class="btn btn-link">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Gráfico de vencimientos por mes -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body">
            <h5 class="card-title">Lotes por mes de vencimiento</h5>
            <div id="chartVencimientos" style="height: 300px;"></div>
        </div>
    </div>

    <!-- Tabla de lotes -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaVencimientos">
                    <thead class="table-light">
                        <tr>
                            <th>Medicamento</th><th>Lote</th><th>Vencimiento</th><th>Días rest.</th>
                            <th>Cantidad</th><th>Estado</th><th>Alerta</th><th>Ubicación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lotes)): ?>
                            <tr><td colspan="8" class="text-center py-4">No hay lotes con los filtros seleccionados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lotes as $l): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($l['medicamento']); ?></td>
                                    <td><?php echo htmlspecialchars($l['numero_lote']); ?></td>
                                    <td nowrap><?php echo date('d/m/Y', strtotime($l['fecha_vencimiento'])); ?></td>
                                    <td>
                                        <?php
                                        $dias = $l['dias_restantes'];
                                        $clase = $dias < 0 ? 'text-danger' : ($dias <= 30 ? 'text-warning' : 'text-success');
                                        ?>
                                        <span class="<?php echo $clase; ?>"><?php echo $dias; ?></span>
                                     </td>
                                    <td><?php echo $l['cantidad_actual']; ?></td>
                                    <td><?php echo $l['estado']; ?></td>
                                    <td>
                                        <?php
                                        $badge = '';
                                        if ($l['alerta_vencimiento'] == 'VENCIDO') $badge = 'danger';
                                        elseif ($l['alerta_vencimiento'] == 'PROXIMO A VENCER') $badge = 'warning';
                                        else $badge = 'success';
                                        ?>
                                        <span class="badge bg-<?php echo $badge; ?>"><?php echo $l['alerta_vencimiento']; ?></span>
                                     </td>
                                    <td><?php echo htmlspecialchars($l['ubicacion'] ?? 'N/A'); ?></td>
                                 </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    // Gráfico de lotes por mes (barras)
    var options = {
        series: [
            { name: 'Cantidad de lotes', data: <?php echo json_encode($lotes_por_mes); ?> },
            { name: 'Unidades', data: <?php echo json_encode($unidades_por_mes); ?> }
        ],
        chart: { type: 'bar', height: 300, toolbar: { show: false } },
        xaxis: { categories: <?php echo json_encode($meses); ?>, title: { text: 'Mes de vencimiento' } },
        yaxis: { title: { text: 'Cantidad' } },
        colors: ['#dc3545', '#ffc107'],
        plotOptions: { bar: { borderRadius: 6, horizontal: false, columnWidth: '50%' } },
        legend: { position: 'top' }
    };
    new ApexCharts(document.querySelector("#chartVencimientos"), options).render();

    // Exportar CSV
    document.getElementById('exportarCSV').addEventListener('click', () => {
        const filas = document.querySelectorAll('#tablaVencimientos tr');
        let csv = [];
        for (let fila of filas) {
            let celdas = fila.querySelectorAll('th, td');
            let filaTexto = Array.from(celdas).map(celda => '"' + celda.innerText.replace(/,/g, ';') + '"').join(',');
            csv.push(filaTexto);
        }
        const blob = new Blob(["\uFEFF" + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'reporte_vencimientos.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    });

    // Exportar Excel
    document.getElementById('exportarExcel').addEventListener('click', () => {
        const tabla = document.getElementById('tablaVencimientos');
        const blob = new Blob([tabla.outerHTML], { type: 'application/vnd.ms-excel' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'reporte_vencimientos.xls';
        link.click();
        URL.revokeObjectURL(link.href);
    });
</script>