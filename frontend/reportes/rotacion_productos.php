<?php
// rotacion_productos.php - Reporte de rotación de productos (ventas)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../backend/conexion.php';

if (!isset($_SESSION['id_sesion']) || !isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

// Obtener filtros
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$id_categoria = $_GET['categoria'] ?? '';
$id_laboratorio = $_GET['laboratorio'] ?? '';
$buscar = $_GET['buscar'] ?? '';

// Consulta principal: rotación por producto (medicamentos y productos generales)
$sql = "
    SELECT 
        COALESCE(m.id_medicamento, p.id_producto) as id_producto,
        COALESCE(m.nombre_completo, p.nombre) as producto_nombre,
        COALESCE(c.nombre, 'SIN CATEGORÍA') as categoria,
        COALESCE(lab.nombre, 'N/A') as laboratorio,
        SUM(dv.cantidad) as total_unidades,
        COUNT(DISTINCT dv.id_venta) as total_transacciones,
        SUM(dv.subtotal) as total_ingresos,
        ROUND(AVG(dv.cantidad), 2) as promedio_unidades_por_venta,
        MAX(v.fecha) as ultima_venta,
        EXTRACT(DAY FROM (CURRENT_DATE - MAX(v.fecha))) as dias_sin_venta
    FROM detalle_venta dv
    JOIN ventas v ON dv.id_venta = v.id_venta
    LEFT JOIN lotes l ON dv.id_lote = l.id_lote
    LEFT JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
    LEFT JOIN productos p ON dv.id_producto = p.id_producto
    LEFT JOIN categorias c ON (m.id_categoria = c.id_categoria)
    LEFT JOIN laboratorios lab ON (m.id_laboratorio = lab.id_laboratorio)
    WHERE v.fecha BETWEEN :fecha_desde AND :fecha_hasta
      AND (v.estado_fiscal != 'ANULADA' OR v.estado_fiscal IS NULL)
";

$params = [
    ':fecha_desde' => $fecha_desde . ' 00:00:00',
    ':fecha_hasta' => $fecha_hasta . ' 23:59:59'
];

if (!empty($id_categoria)) {
    $sql .= " AND m.id_categoria = :id_categoria";
    $params[':id_categoria'] = $id_categoria;
}
if (!empty($id_laboratorio)) {
    $sql .= " AND m.id_laboratorio = :id_laboratorio";
    $params[':id_laboratorio'] = $id_laboratorio;
}
if (!empty($buscar)) {
    $sql .= " AND (m.nombre_completo ILIKE :buscar OR p.nombre ILIKE :buscar)";
    $params[':buscar'] = "%$buscar%";
}

$sql .= " GROUP BY COALESCE(m.id_medicamento, p.id_producto), producto_nombre, categoria, laboratorio
          ORDER BY total_unidades DESC, total_ingresos DESC
          LIMIT 500";

$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales globales
$total_unidades_general = array_sum(array_column($productos, 'total_unidades'));
$total_ingresos_general = array_sum(array_column($productos, 'total_ingresos'));
$total_transacciones_general = array_sum(array_column($productos, 'total_transacciones'));
$cantidad_productos = count($productos);

// Top 10
$top10 = array_slice($productos, 0, 10);
$top_nombres = array_column($top10, 'producto_nombre');
$top_unidades = array_column($top10, 'total_unidades');

// Listas para filtros
$categorias = $conexion->query("SELECT id_categoria, nombre FROM categorias WHERE activo = TRUE ORDER BY nombre")->fetchAll();
$laboratorios = $conexion->query("SELECT id_laboratorio, nombre FROM laboratorios WHERE activo = TRUE ORDER BY nombre")->fetchAll();
?>

<div class="rotacion-productos-container">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
        <h2 class="h3">
            <span class="material-symbols-rounded align-middle">published_with_changes</span>
            Rotación de Productos
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
                    <span class="material-symbols-rounded text-primary">sell</span>
                    <h5 class="mt-2 mb-0"><?php echo number_format($total_unidades_general); ?></h5>
                    <small class="text-muted">Unidades vendidas</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <span class="material-symbols-rounded text-success">payments</span>
                    <h5 class="mt-2 mb-0">RD$ <?php echo number_format($total_ingresos_general, 2); ?></h5>
                    <small class="text-muted">Ingresos totales</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <span class="material-symbols-rounded text-warning">receipt_long</span>
                    <h5 class="mt-2 mb-0"><?php echo number_format($total_transacciones_general); ?></h5>
                    <small class="text-muted">Transacciones</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <span class="material-symbols-rounded text-info">inventory</span>
                    <h5 class="mt-2 mb-0"><?php echo $cantidad_productos; ?></h5>
                    <small class="text-muted">Productos con ventas</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="mod" value="rotacion_productos">
                <div class="col-md-2">
                    <label class="form-label small">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
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
                    <label class="form-label small">Buscar producto</label>
                    <input type="text" name="buscar" class="form-control" placeholder="Nombre..." value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="?mod=rotacion_productos" class="btn btn-link">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Gráfico top 10 -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body">
            <h5 class="card-title">Top 10 productos más vendidos (unidades)</h5>
            <div id="chartTopProductos" style="height: 350px;"></div>
        </div>
    </div>

    <!-- Tabla de rotación -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaRotacion">
                    <thead class="table-light">
                        <tr>
                            <th>Producto</th><th>Categoría</th><th>Laboratorio</th>
                            <th>Unidades vendidas</th><th>Ingresos</th><th>Transacciones</th>
                            <th>Promedio x venta</th><th>Última venta</th><th>Días sin venta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos)): ?>
                            <tr><td colspan="9" class="text-center py-4">No hay ventas en el período seleccionado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($productos as $p): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($p['producto_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($p['categoria']); ?></td>
                                    <td><?php echo htmlspecialchars($p['laboratorio']); ?></td>
                                    <td><?php echo number_format($p['total_unidades']); ?></td>
                                    <td>RD$ <?php echo number_format($p['total_ingresos'], 2); ?></td>
                                    <td><?php echo $p['total_transacciones']; ?></td>
                                    <td><?php echo $p['promedio_unidades_por_venta']; ?></td>
                                    <td><?php echo $p['ultima_venta'] ? date('d/m/Y', strtotime($p['ultima_venta'])) : 'Nunca'; ?></td>
                                    <td>
                                        <?php 
                                        $dias = $p['dias_sin_venta'];
                                        if ($dias === null) $dias = '∞';
                                        $clase = (is_numeric($dias) && $dias > 30) ? 'text-danger' : ((is_numeric($dias) && $dias > 7) ? 'text-warning' : 'text-success');
                                        ?>
                                        <span class="<?php echo $clase; ?>"><?php echo $dias; ?></span>
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
    var topOptions = {
        series: [{ name: 'Unidades vendidas', data: <?php echo json_encode($top_unidades); ?> }],
        chart: { type: 'bar', height: 350, toolbar: { show: false } },
        xaxis: { categories: <?php echo json_encode($top_nombres); ?>, labels: { rotate: -45, trim: true, style: { fontSize: '11px' } } },
        yaxis: { title: { text: 'Unidades' } },
        colors: ['#0d6efd'],
        plotOptions: { bar: { borderRadius: 6, horizontal: false, dataLabels: { position: 'top' } } },
        dataLabels: { enabled: true, offsetY: -20, style: { fontSize: '12px' } }
    };
    new ApexCharts(document.querySelector("#chartTopProductos"), topOptions).render();

    document.getElementById('exportarCSV').addEventListener('click', () => {
        const filas = document.querySelectorAll('#tablaRotacion tr');
        let csv = [];
        for (let fila of filas) {
            let celdas = fila.querySelectorAll('th, td');
            let filaTexto = Array.from(celdas).map(celda => '"' + celda.innerText.replace(/,/g, ';') + '"').join(',');
            csv.push(filaTexto);
        }
        const blob = new Blob(["\uFEFF" + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'rotacion_productos.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    });

    document.getElementById('exportarExcel').addEventListener('click', () => {
        const tabla = document.getElementById('tablaRotacion');
        const blob = new Blob([tabla.outerHTML], { type: 'application/vnd.ms-excel' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'rotacion_productos.xls';
        link.click();
        URL.revokeObjectURL(link.href);
    });
</script>