<?php
// reporte_inventario.php - Reporte de inventario actual
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../backend/conexion.php';

if (!isset($_SESSION['id_sesion']) || !isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

// Obtener filtros
$categoria = $_GET['categoria'] ?? '';
$laboratorio = $_GET['laboratorio'] ?? '';
$estado_lote = $_GET['estado_lote'] ?? '';
$alerta_stock = $_GET['alerta_stock'] ?? ''; // CRITICO, BAJO, NORMAL
$alerta_vencimiento = $_GET['alerta_vencimiento'] ?? ''; // VENCIDO, PROXIMO, OK

// Construir consulta principal usando la vista vista_inventario_actual
$sql = "SELECT * FROM vista_inventario_actual WHERE 1=1";
$params = [];

if (!empty($categoria)) {
    $sql .= " AND categoria = :categoria";
    $params[':categoria'] = $categoria;
}
if (!empty($laboratorio)) {
    $sql .= " AND laboratorio = :laboratorio";
    $params[':laboratorio'] = $laboratorio;
}
if (!empty($estado_lote)) {
    $sql .= " AND estado = :estado_lote";
    $params[':estado_lote'] = $estado_lote;
}
if (!empty($alerta_stock)) {
    $sql .= " AND alerta_stock = :alerta_stock";
    $params[':alerta_stock'] = $alerta_stock;
}
if (!empty($alerta_vencimiento)) {
    $sql .= " AND alerta_vencimiento = :alerta_vencimiento";
    $params[':alerta_vencimiento'] = $alerta_vencimiento;
}

$sql .= " ORDER BY alerta_stock DESC, alerta_vencimiento DESC, medicamento ASC";
$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$total_unidades = 0;
$total_valor_inventario = 0;
$total_productos_distintos = 0;
$lotes_vencidos = 0;
$lotes_proximos = 0;
$stock_critico = 0;
$stock_bajo = 0;

$medicamentos_vistos = [];
foreach ($inventario as $item) {
    $total_unidades += $item['cantidad'];
    // Para valor, necesitaríamos costo del lote (no está en la vista). Opcional: podríamos calcular con precio de venta aprox.
    // Por simplicidad, omitimos valor económico o lo calculamos después si se requiere.
    if (!in_array($item['medicamento'], $medicamentos_vistos)) {
        $medicamentos_vistos[] = $item['medicamento'];
        $total_productos_distintos++;
    }
    if ($item['alerta_vencimiento'] == 'VENCIDO') $lotes_vencidos++;
    if ($item['alerta_vencimiento'] == 'PROXIMO A VENCER') $lotes_proximos++;
    if ($item['alerta_stock'] == 'CRITICO') $stock_critico++;
    if ($item['alerta_stock'] == 'BAJO') $stock_bajo++;
}

// Obtener listas para filtros (categorías, laboratorios, estados)
$categorias = $conexion->query("SELECT nombre FROM categorias WHERE activo = TRUE ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$laboratorios = $conexion->query("SELECT nombre FROM laboratorios WHERE activo = TRUE ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$estados_lote = ['ACTIVO', 'VENCIDO', 'RETIRADO', 'MERMA', 'DAÑADO'];

// Datos para gráfico top 10 productos con más stock
$stmt_top = $conexion->query("
    SELECT medicamento, SUM(cantidad) as total_stock
    FROM vista_inventario_actual
    GROUP BY medicamento
    ORDER BY total_stock DESC
    LIMIT 10
");
$top_productos = $stmt_top->fetchAll(PDO::FETCH_ASSOC);
$top_nombres = array_column($top_productos, 'medicamento');
$top_stocks = array_column($top_productos, 'total_stock');

// Datos para gráfico de stock por categoría
$stmt_cat = $conexion->query("
    SELECT categoria, SUM(cantidad) as total_stock
    FROM vista_inventario_actual
    WHERE categoria IS NOT NULL
    GROUP BY categoria
    ORDER BY total_stock DESC
");
$stock_categoria = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
$cat_nombres = array_column($stock_categoria, 'categoria');
$cat_stocks = array_column($stock_categoria, 'total_stock');
?>

<div class="reporte-inventario-container">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
        <h2 class="h3">
            <span class="material-symbols-rounded align-middle">inventory</span>
            Reporte de Inventario
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
                    <span class="material-symbols-rounded text-primary">inventory_2</span>
                    <h5 class="mt-2 mb-0"><?php echo number_format($total_unidades); ?></h5>
                    <small class="text-muted">Unidades en stock</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <span class="material-symbols-rounded text-success">medication</span>
                    <h5 class="mt-2 mb-0"><?php echo $total_productos_distintos; ?></h5>
                    <small class="text-muted">Productos diferentes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <span class="material-symbols-rounded text-warning">event_busy</span>
                    <h5 class="mt-2 mb-0"><?php echo $lotes_proximos; ?></h5>
                    <small class="text-muted">Próximos a vencer (30 días)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <span class="material-symbols-rounded text-danger">warning</span>
                    <h5 class="mt-2 mb-0"><?php echo $stock_critico + $stock_bajo; ?></h5>
                    <small class="text-muted">Alertas de stock (bajo/crítico)</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="mod" value="reporte_inventario">
                <div class="col-md-2">
                    <label class="form-label small">Categoría</label>
                    <select name="categoria" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categoria == $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Laboratorio</label>
                    <select name="laboratorio" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($laboratorios as $lab): ?>
                            <option value="<?php echo htmlspecialchars($lab); ?>" <?php echo $laboratorio == $lab ? 'selected' : ''; ?>><?php echo htmlspecialchars($lab); ?></option>
                        <?php endforeach; ?>
                    </select>
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
                    <label class="form-label small">Alerta stock</label>
                    <select name="alerta_stock" class="form-select">
                        <option value="">Todas</option>
                        <option value="CRITICO" <?php echo $alerta_stock == 'CRITICO' ? 'selected' : ''; ?>>Crítico (≤5)</option>
                        <option value="BAJO" <?php echo $alerta_stock == 'BAJO' ? 'selected' : ''; ?>>Bajo (≤10)</option>
                        <option value="NORMAL" <?php echo $alerta_stock == 'NORMAL' ? 'selected' : ''; ?>>Normal</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Alerta vencimiento</label>
                    <select name="alerta_vencimiento" class="form-select">
                        <option value="">Todas</option>
                        <option value="VENCIDO" <?php echo $alerta_vencimiento == 'VENCIDO' ? 'selected' : ''; ?>>Vencido</option>
                        <option value="PROXIMO A VENCER" <?php echo $alerta_vencimiento == 'PROXIMO A VENCER' ? 'selected' : ''; ?>>Próximo a vencer</option>
                        <option value="OK" <?php echo $alerta_vencimiento == 'OK' ? 'selected' : ''; ?>>Vigente</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="?mod=reporte_inventario" class="btn btn-link">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-body">
                    <h5 class="card-title">Top 10 productos con más stock</h5>
                    <div id="chartTopStock" style="height: 300px;"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-body">
                    <h5 class="card-title">Stock por categoría</h5>
                    <div id="chartStockCategoria" style="height: 300px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de inventario -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaInventario">
                    <thead class="table-light">
                        <tr>
                            <th>Medicamento</th><th>Lote</th><th>Vencimiento</th><th>Cantidad</th>
                            <th>Presentación</th><th>Categoría</th><th>Alerta stock</th><th>Alerta venc.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inventario)): ?>
                            <tr><td colspan="8" class="text-center py-4">No hay datos de inventario con los filtros seleccionados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($inventario as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['medicamento']); ?></td>
                                    <td><?php echo htmlspecialchars($item['numero_lote']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($item['fecha_vencimiento'])); ?></td>
                                    <td><?php echo $item['cantidad']; ?></td>
                                    <td><?php echo htmlspecialchars($item['presentacion'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($item['categoria'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                        $badge = '';
                                        if ($item['alerta_stock'] == 'CRITICO') $badge = 'danger';
                                        elseif ($item['alerta_stock'] == 'BAJO') $badge = 'warning';
                                        else $badge = 'success';
                                        ?>
                                        <span class="badge bg-<?php echo $badge; ?>"><?php echo $item['alerta_stock']; ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $badge2 = '';
                                        if ($item['alerta_vencimiento'] == 'VENCIDO') $badge2 = 'danger';
                                        elseif ($item['alerta_vencimiento'] == 'PROXIMO A VENCER') $badge2 = 'warning';
                                        else $badge2 = 'success';
                                        ?>
                                        <span class="badge bg-<?php echo $badge2; ?>"><?php echo $item['alerta_vencimiento']; ?></span>
                                    </td>
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
    // Gráfico top 10 productos
    var topOptions = {
        series: [{ name: 'Unidades', data: <?php echo json_encode($top_stocks); ?> }],
        chart: { type: 'bar', height: 300, toolbar: { show: false } },
        xaxis: { categories: <?php echo json_encode($top_nombres); ?>, labels: { rotate: -45, trim: true } },
        yaxis: { title: { text: 'Cantidad' } },
        colors: ['#0d6efd'],
        plotOptions: { bar: { borderRadius: 6, horizontal: false } }
    };
    new ApexCharts(document.querySelector("#chartTopStock"), topOptions).render();

    // Gráfico stock por categoría
    var catOptions = {
        series: <?php echo json_encode($cat_stocks); ?>,
        chart: { type: 'donut', height: 300 },
        labels: <?php echo json_encode($cat_nombres); ?>,
        colors: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14'],
        legend: { position: 'bottom' }
    };
    new ApexCharts(document.querySelector("#chartStockCategoria"), catOptions).render();

    // Exportar CSV
    document.getElementById('exportarCSV').addEventListener('click', () => {
        const filas = document.querySelectorAll('#tablaInventario tr');
        let csv = [];
        for (let fila of filas) {
            let celdas = fila.querySelectorAll('th, td');
            let filaTexto = Array.from(celdas).map(celda => '"' + celda.innerText.replace(/,/g, ';') + '"').join(',');
            csv.push(filaTexto);
        }
        const blob = new Blob(["\uFEFF" + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'reporte_inventario.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    });

    // Exportar Excel
    document.getElementById('exportarExcel').addEventListener('click', () => {
        const tabla = document.getElementById('tablaInventario');
        const blob = new Blob([tabla.outerHTML], { type: 'application/vnd.ms-excel' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'reporte_inventario.xls';
        link.click();
        URL.revokeObjectURL(link.href);
    });
</script>