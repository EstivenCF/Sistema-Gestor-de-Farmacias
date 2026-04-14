<?php
// reporte_ventas.php - Reporte de ventas del sistema (PostgreSQL)
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
$id_usuario = $_GET['usuario'] ?? '';
$id_cliente = $_GET['cliente'] ?? '';
$metodo_pago = $_GET['metodo_pago'] ?? '';
$tipo_venta = $_GET['tipo_venta'] ?? '';
$estado_pago = $_GET['estado_pago'] ?? '';

// Consulta principal (PostgreSQL)
$sql = "SELECT v.id_venta, v.numero_documento, v.fecha, v.subtotal, v.descuento_total, v.itbis_total, v.total,
               v.es_credito, v.estado_pago, v.abonos_acumulados,
               c.nombre as cliente_nombre, u.nombre as vendedor_nombre,
               (SELECT string_agg(DISTINCT mp.nombre, ', ') 
                FROM pagos p 
                JOIN metodos_pago mp ON p.id_metodo = mp.id_metodo 
                WHERE p.id_venta = v.id_venta) as metodos_pago
        FROM ventas v
        LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
        LEFT JOIN usuarios u ON v.id_usuario = u.id_usuario
        WHERE v.fecha BETWEEN :fecha_desde AND :fecha_hasta";

$params = [
    ':fecha_desde' => $fecha_desde . ' 00:00:00',
    ':fecha_hasta' => $fecha_hasta . ' 23:59:59'
];

if (!empty($id_usuario)) {
    $sql .= " AND v.id_usuario = :id_usuario";
    $params[':id_usuario'] = $id_usuario;
}
if (!empty($id_cliente)) {
    $sql .= " AND v.id_cliente = :id_cliente";
    $params[':id_cliente'] = $id_cliente;
}
if (!empty($tipo_venta)) {
    if ($tipo_venta == 'contado') $sql .= " AND v.es_credito = FALSE";
    else $sql .= " AND v.es_credito = TRUE";
}
if (!empty($estado_pago)) {
    $sql .= " AND v.estado_pago = :estado_pago";
    $params[':estado_pago'] = $estado_pago;
}
if (!empty($metodo_pago)) {
    $sql .= " AND EXISTS (SELECT 1 FROM pagos p WHERE p.id_venta = v.id_venta AND p.id_metodo = :id_metodo)";
    $params[':id_metodo'] = $metodo_pago;
}

$sql .= " ORDER BY v.fecha DESC";
$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totales
$total_ventas = 0;
$total_itbis = 0;
$cantidad_ventas = count($ventas);
foreach ($ventas as $v) {
    $total_ventas += $v['total'];
    $total_itbis += $v['itbis_total'];
}
$ticket_promedio = $cantidad_ventas > 0 ? $total_ventas / $cantidad_ventas : 0;

// Listas para filtros
$usuarios = $conexion->query("SELECT id_usuario, nombre FROM usuarios WHERE estado = TRUE ORDER BY nombre")->fetchAll();
$clientes = $conexion->query("SELECT id_cliente, nombre FROM clientes ORDER BY nombre")->fetchAll();
$metodos = $conexion->query("SELECT id_metodo, nombre FROM metodos_pago ORDER BY nombre")->fetchAll();

// Datos para gráfico
$stmt_chart = $conexion->prepare("
    SELECT DATE(fecha) as dia, SUM(total) as total_dia
    FROM ventas
    WHERE fecha BETWEEN :fecha_desde AND :fecha_hasta
    GROUP BY DATE(fecha)
    ORDER BY dia ASC
");
$stmt_chart->execute($params);
$chart_data = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);
$dias = array_column($chart_data, 'dia');
$totales_dia = array_column($chart_data, 'total_dia');
?>

<div class="reporte-ventas-container">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
        <h2 class="h3">
            <span class="material-symbols-rounded align-middle">bar_chart</span>
            Reporte de Ventas
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
                    <span class="material-symbols-rounded text-primary">payments</span>
                    <h5 class="mt-2 mb-0">RD$ <?php echo number_format($total_ventas, 2); ?></h5>
                    <small class="text-muted">Total ventas</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <span class="material-symbols-rounded text-success">receipt</span>
                    <h5 class="mt-2 mb-0"><?php echo $cantidad_ventas; ?></h5>
                    <small class="text-muted">Transacciones</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <span class="material-symbols-rounded text-warning">trending_up</span>
                    <h5 class="mt-2 mb-0">RD$ <?php echo number_format($ticket_promedio, 2); ?></h5>
                    <small class="text-muted">Ticket promedio</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <span class="material-symbols-rounded text-danger">account_balance</span>
                    <h5 class="mt-2 mb-0">RD$ <?php echo number_format($total_itbis, 2); ?></h5>
                    <small class="text-muted">ITBIS total</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="mod" value="reporte_ventas">
                <div class="col-md-2">
                    <label class="form-label small">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Vendedor</label>
                    <select name="usuario" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?php echo $u['id_usuario']; ?>" <?php echo ($id_usuario == $u['id_usuario']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Cliente</label>
                    <select name="cliente" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?php echo $c['id_cliente']; ?>" <?php echo ($id_cliente == $c['id_cliente']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Método pago</label>
                    <select name="metodo_pago" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($metodos as $m): ?>
                            <option value="<?php echo $m['id_metodo']; ?>" <?php echo ($metodo_pago == $m['id_metodo']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Tipo venta</label>
                    <select name="tipo_venta" class="form-select">
                        <option value="">Todos</option>
                        <option value="contado" <?php echo $tipo_venta == 'contado' ? 'selected' : ''; ?>>Contado</option>
                        <option value="credito" <?php echo $tipo_venta == 'credito' ? 'selected' : ''; ?>>Crédito</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Estado pago</label>
                    <select name="estado_pago" class="form-select">
                        <option value="">Todos</option>
                        <option value="PENDIENTE" <?php echo $estado_pago == 'PENDIENTE' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="PARCIAL" <?php echo $estado_pago == 'PARCIAL' ? 'selected' : ''; ?>>Parcial</option>
                        <option value="PAGADO" <?php echo $estado_pago == 'PAGADO' ? 'selected' : ''; ?>>Pagado</option>
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="?mod=reporte_ventas" class="btn btn-link">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Gráfico -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body">
            <h5 class="card-title">Ventas diarias</h5>
            <div id="chartVentasDiarias" style="height: 300px;"></div>
        </div>
    </div>

    <!-- Tabla de ventas -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaVentas">
                    <thead class="table-light">
                        <tr>
                            <th>N° Documento</th><th>Fecha</th><th>Cliente</th><th>Vendedor</th>
                            <th>Subtotal</th><th>Descuento</th><th>ITBIS</th><th>Total</th>
                            <th>Método pago</th><th>Estado</th><th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ventas)): ?>
                            <tr><td colspan="11" class="text-center py-4">No hay ventas en el período.</td></tr>
                        <?php else: ?>
                            <?php foreach ($ventas as $v): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($v['numero_documento']); ?></td>
                                    <td nowrap><?php echo date('d/m/Y H:i', strtotime($v['fecha'])); ?></td>
                                    <td><?php echo htmlspecialchars($v['cliente_nombre'] ?? 'Consumidor final'); ?></td>
                                    <td><?php echo htmlspecialchars($v['vendedor_nombre']); ?></td>
                                    <td>RD$ <?php echo number_format($v['subtotal'], 2); ?></td>
                                    <td>RD$ <?php echo number_format($v['descuento_total'], 2); ?></td>
                                    <td>RD$ <?php echo number_format($v['itbis_total'], 2); ?></td>
                                    <td><strong>RD$ <?php echo number_format($v['total'], 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars($v['metodos_pago'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($v['es_credito']): ?>
                                            <span class="badge bg-warning text-dark">Crédito</span>
                                            <?php
                                            $estado_badge = match($v['estado_pago']) {
                                                'PAGADO' => 'success',
                                                'PARCIAL' => 'info',
                                                default => 'danger'
                                            };
                                            ?>
                                            <span class="badge bg-<?php echo $estado_badge; ?> ms-1"><?php echo $v['estado_pago']; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Contado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info ver-detalle" data-id="<?php echo $v['id_venta']; ?>">
                                            <span class="material-symbols-rounded fs-6">visibility</span>
                                        </button>
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

<!-- Modal detalle venta -->
<div class="modal fade" id="modalDetalleVenta" tabindex="-1" data-bs-backdrop="false" data-bs-keyboard="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de venta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detalleVentaBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p>Cargando detalles...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    // Gráfico
    var options = {
        series: [{ name: 'Ventas (RD$)', data: <?php echo json_encode($totales_dia); ?> }],
        chart: { type: 'line', height: 300, toolbar: { show: false }, zoom: { enabled: false } },
        xaxis: { categories: <?php echo json_encode($dias); ?>, title: { text: 'Fecha' } },
        yaxis: { title: { text: 'Monto (RD$)' }, labels: { formatter: (val) => 'RD$ ' + val.toFixed(2) } },
        stroke: { curve: 'smooth', width: 3 },
        colors: ['#0d6efd'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 0.5, opacityFrom: 0.7, opacityTo: 0.3 } }
    };
    new ApexCharts(document.querySelector("#chartVentasDiarias"), options).render();

    // Función para abrir modal con auto scroll
    async function abrirDetalleVenta(idVenta) {
        const modalElement = document.getElementById('modalDetalleVenta');
        const modalBody = document.getElementById('detalleVentaBody');
        modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p>Cargando detalles...</p></div>';
        
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        
        // Auto scroll al modal después de mostrarlo
        setTimeout(() => {
            modalElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 200); // pequeño retraso para asegurar que el modal esté visible
        
        try {
            const response = await fetch(`../backend/obtener_detalle_venta.php?id_venta=${idVenta}`);
            const data = await response.json();
            if (data.success) {
                let html = `
                    <div class="mb-3">
                        <strong>N° Documento:</strong> ${data.venta.numero_documento}<br>
                        <strong>Fecha:</strong> ${data.venta.fecha}<br>
                        <strong>Cliente:</strong> ${data.venta.cliente || 'Consumidor final'}<br>
                        <strong>Vendedor:</strong> ${data.venta.vendedor}<br>
                        <strong>Condición:</strong> ${data.venta.es_credito ? 'Crédito' : 'Contado'} ${data.venta.estado_pago ? ' - ' + data.venta.estado_pago : ''}<br>
                        ${data.venta.abonos_acumulados ? `<strong>Abonos acumulados:</strong> RD$ ${data.venta.abonos_acumulados}<br>` : ''}
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light"><tr><th>Producto</th><th>Cantidad</th><th>Precio unit.</th><th>Descuento</th><th>Subtotal</th></tr></thead>
                            <tbody>
                `;
                data.detalles.forEach(d => {
                    html += `<tr>
                        <td>${d.producto_nombre}</td>
                        <td>${d.cantidad}</td>
                        <td>RD$ ${parseFloat(d.precio_unitario).toFixed(2)}</td>
                        <td>RD$ ${parseFloat(d.descuento_unitario).toFixed(2)}</td>
                        <td>RD$ ${parseFloat(d.subtotal).toFixed(2)}</td>
                    </tr>`;
                });
                html += `
                            </tbody>
                            <tfoot>
                                <tr><th colspan="4" class="text-end">Subtotal:</th><td>RD$ ${parseFloat(data.venta.subtotal).toFixed(2)}</td></tr>
                                <tr><th colspan="4" class="text-end">Descuento:</th><td>RD$ ${parseFloat(data.venta.descuento_total).toFixed(2)}</td></tr>
                                <tr><th colspan="4" class="text-end">ITBIS:</th><td>RD$ ${parseFloat(data.venta.itbis_total).toFixed(2)}</td></tr>
                                <tr><th colspan="4" class="text-end">Total:</th><td><strong>RD$ ${parseFloat(data.venta.total).toFixed(2)}</strong></td></tr>
                            </tfoot>
                        </table>
                    </div>
                `;
                modalBody.innerHTML = html;
            } else {
                modalBody.innerHTML = `<div class="alert alert-danger">Error: ${data.message}</div>`;
            }
        } catch (err) {
            modalBody.innerHTML = `<div class="alert alert-danger">Error de conexión</div>`;
        }
    }

    // Asignar eventos a botones "ver detalle"
    document.querySelectorAll('.ver-detalle').forEach(btn => {
        btn.addEventListener('click', () => abrirDetalleVenta(btn.dataset.id));
    });

    // Exportar CSV
    document.getElementById('exportarCSV').addEventListener('click', () => {
        const filas = document.querySelectorAll('#tablaVentas tr');
        let csv = [];
        for (let fila of filas) {
            let celdas = fila.querySelectorAll('th, td');
            let filaTexto = Array.from(celdas).map(celda => '"' + celda.innerText.replace(/,/g, ';') + '"').join(',');
            csv.push(filaTexto);
        }
        const blob = new Blob(["\uFEFF" + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'reporte_ventas.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    });

    // Exportar Excel (XLS)
    document.getElementById('exportarExcel').addEventListener('click', () => {
        const tabla = document.getElementById('tablaVentas');
        const blob = new Blob([tabla.outerHTML], { type: 'application/vnd.ms-excel' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'reporte_ventas.xls';
        link.click();
        URL.revokeObjectURL(link.href);
    });
</script>