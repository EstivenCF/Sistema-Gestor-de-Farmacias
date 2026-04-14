<?php
require_once __DIR__ . '/../../backend/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_sesion'])) {
    header("Location: ../index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'] ?? 0;

// Obtener cajas del usuario (activas y cerradas)
$cajas_usuario = [];
try {
    $stmt = $conexion->prepare("
        SELECT c.id_caja, c.numero_caja, c.estado, s.nombre as sucursal_nombre
        FROM caja c
        JOIN sucursales s ON c.id_sucursal = s.id_sucursal
        WHERE c.id_usuario = :id_usuario
        ORDER BY c.fecha_apertura DESC
    ");
    $stmt->execute([':id_usuario' => $id_usuario]);
    $cajas_usuario = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Obtener parámetros de filtro
$filtro_caja = $_GET['caja'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Consulta de movimientos
$query = "
    SELECT 
        m.id_movimiento,
        m.tipo,
        m.monto,
        m.concepto,
        m.fecha,
        m.id_venta,
        c.id_caja,
        c.numero_caja,
        s.nombre as sucursal_nombre
    FROM movimiento_caja m
    JOIN caja c ON m.id_caja = c.id_caja
    JOIN sucursales s ON c.id_sucursal = s.id_sucursal
    WHERE c.id_usuario = :id_usuario
";

$params = [':id_usuario' => $id_usuario];

if ($filtro_caja) {
    $query .= " AND m.id_caja = :id_caja";
    $params[':id_caja'] = $filtro_caja;
}
if ($filtro_tipo) {
    $query .= " AND m.tipo = :tipo";
    $params[':tipo'] = $filtro_tipo;
}
if ($fecha_desde && $fecha_hasta) {
    $query .= " AND m.fecha BETWEEN :fecha_desde AND :fecha_hasta";
    $params[':fecha_desde'] = $fecha_desde;
    $params[':fecha_hasta'] = $fecha_hasta;
} elseif ($fecha_desde) {
    $query .= " AND m.fecha >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
} elseif ($fecha_hasta) {
    $query .= " AND m.fecha <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

$query .= " ORDER BY m.fecha DESC";

$movimientos = [];
$total_ingresos = 0;
$total_egresos = 0;
$saldo_actual = 0;

try {
    $stmt = $conexion->prepare($query);
    $stmt->execute($params);
    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($movimientos as $m) {
        if ($m['tipo'] == 'INGRESO') {
            $total_ingresos += $m['monto'];
        } else {
            $total_egresos += $m['monto'];
        }
    }
    $saldo_actual = $total_ingresos - $total_egresos;
} catch(PDOException $e) {
    // error
}

// Obtener cajas abiertas para poder registrar nuevos movimientos
$cajas_abiertas = [];
try {
    $stmtAbiertas = $conexion->prepare("
        SELECT c.id_caja, c.numero_caja, s.nombre as sucursal_nombre
        FROM caja c
        JOIN sucursales s ON c.id_sucursal = s.id_sucursal
        WHERE c.id_usuario = :id_usuario AND c.estado = 'ABIERTA'
    ");
    $stmtAbiertas->execute([':id_usuario' => $id_usuario]);
    $cajas_abiertas = $stmtAbiertas->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

$base_url = '/sistema-gestor-de-farmacias';
?>
<div class="dashboard-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0 text-primary">
                <span class="material-symbols-rounded align-middle me-2">sync_alt</span>
                Movimientos de Caja
            </h2>
            <p class="text-muted mb-0">Registro de ingresos y egresos por caja</p>
        </div>
        <div>
            <?php if (!empty($cajas_abiertas)): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalRegistroMovimiento">
                    <span class="material-symbols-rounded align-middle me-1">add</span>
                    Registrar Movimiento
                </button>
            <?php endif; ?>
            <button type="button" class="btn btn-danger" onclick="exportarPDF()">
                <span class="material-symbols-rounded align-middle me-1">picture_as_pdf</span>
                Exportar a PDF
            </button>
        </div>
    </div>

    <!-- Tarjetas de resumen -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h6 class="card-title">Total Ingresos</h6>
                    <h3>RD$ <?php echo number_format($total_ingresos, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-danger">
                <div class="card-body">
                    <h6 class="card-title">Total Egresos</h6>
                    <h3>RD$ <?php echo number_format($total_egresos, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h6 class="card-title">Saldo Actual</h6>
                    <h3>RD$ <?php echo number_format($saldo_actual, 2); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Caja</label>
                    <select class="form-select" id="filtroCaja">
                        <option value="">Todas</option>
                        <?php foreach ($cajas_usuario as $c): ?>
                            <option value="<?php echo $c['id_caja']; ?>" <?php echo $filtro_caja == $c['id_caja'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['numero_caja'] . ' - ' . $c['sucursal_nombre'] . ' (' . $c['estado'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Tipo</label>
                    <select class="form-select" id="filtroTipo">
                        <option value="">Todos</option>
                        <option value="INGRESO" <?php echo $filtro_tipo === 'INGRESO' ? 'selected' : ''; ?>>Ingresos</option>
                        <option value="EGRESO" <?php echo $filtro_tipo === 'EGRESO' ? 'selected' : ''; ?>>Egresos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Fecha desde</label>
                    <input type="date" class="form-control" id="fechaDesde" value="<?php echo $fecha_desde; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Fecha hasta</label>
                    <input type="date" class="form-control" id="fechaHasta" value="<?php echo $fecha_hasta; ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary me-2" onclick="aplicarFiltros()">Filtrar</button>
                    <button class="btn btn-secondary" onclick="limpiarFiltros()">Limpiar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de movimientos -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tablaMovimientos">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Caja</th>
                            <th>Tipo</th>
                            <th>Monto</th>
                            <th>Concepto</th>
                            <th>Venta relacionada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movimientos)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No hay movimientos registrados</td>
                            </tr>
                        <?php else: foreach ($movimientos as $m): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($m['fecha'])); ?></td>
                                <td><?php echo htmlspecialchars($m['numero_caja']); ?> (<?php echo htmlspecialchars($m['sucursal_nombre']); ?>)</td>
                                <td>
                                    <?php if ($m['tipo'] == 'INGRESO'): ?>
                                        <span class="badge bg-success">Ingreso</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Egreso</span>
                                    <?php endif; ?>
                                </td>
                                <td>RD$ <?php echo number_format($m['monto'], 2); ?></td>
                                <td><?php echo htmlspecialchars($m['concepto']); ?></td>
                                <td><?php echo $m['id_venta'] ? '<a href="menuprincipal.php?mod=ventas&id='.$m['id_venta'].'">Ver venta</a>' : '—'; ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal para registrar nuevo movimiento -->
<div class="modal fade" id="modalRegistroMovimiento" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Registrar Movimiento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formMovimiento">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Caja *</label>
                        <select class="form-select" id="movimiento_caja" required>
                            <option value="">Seleccionar caja</option>
                            <?php foreach ($cajas_abiertas as $c): ?>
                                <option value="<?php echo $c['id_caja']; ?>"><?php echo htmlspecialchars($c['numero_caja'] . ' - ' . $c['sucursal_nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Solo se muestran cajas abiertas.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tipo *</label>
                        <select class="form-select" id="movimiento_tipo" required>
                            <option value="">Seleccionar</option>
                            <option value="INGRESO">Ingreso (entrada de dinero)</option>
                            <option value="EGRESO">Egreso (salida de dinero)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Monto (RD$) *</label>
                        <input type="number" step="0.01" class="form-control" id="movimiento_monto" required placeholder="0.00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Concepto *</label>
                        <input type="text" class="form-control" id="movimiento_concepto" required placeholder="Ej: Retiro para cambio, Depósito de ventas...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ID de venta (opcional)</label>
                        <input type="number" class="form-control" id="movimiento_id_venta" placeholder="Número de venta si aplica">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarMovimiento()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';

function aplicarFiltros() {
    let url = BASE_URL + '/frontend/menuprincipal.php?mod=movimientos_caja';
    const caja = document.getElementById('filtroCaja').value;
    const tipo = document.getElementById('filtroTipo').value;
    const fd = document.getElementById('fechaDesde').value;
    const fh = document.getElementById('fechaHasta').value;
    if (caja) url += `&caja=${caja}`;
    if (tipo) url += `&tipo=${tipo}`;
    if (fd) url += `&fecha_desde=${fd}`;
    if (fh) url += `&fecha_hasta=${fh}`;
    window.location.href = url;
}

function limpiarFiltros() {
    window.location.href = BASE_URL + '/frontend/menuprincipal.php?mod=movimientos_caja';
}

function guardarMovimiento() {
    const id_caja = document.getElementById('movimiento_caja').value;
    const tipo = document.getElementById('movimiento_tipo').value;
    const monto = parseFloat(document.getElementById('movimiento_monto').value);
    const concepto = document.getElementById('movimiento_concepto').value.trim();
    const id_venta = document.getElementById('movimiento_id_venta').value || null;
    
    if (!id_caja || !tipo || isNaN(monto) || monto <= 0 || !concepto) {
        Swal.fire('Error', 'Todos los campos obligatorios deben estar llenos y monto mayor a 0', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Confirmar movimiento',
        text: `¿Registrar ${tipo === 'INGRESO' ? 'ingreso' : 'egreso'} de RD$ ${monto.toFixed(2)} por concepto: "${concepto}"?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: tipo === 'INGRESO' ? '#28a745' : '#dc3545',
        confirmButtonText: 'Confirmar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(BASE_URL + '/backend/caja/agregar_movimiento_caja.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_caja: id_caja,
                    tipo: tipo,
                    monto: monto,
                    concepto: concepto,
                    id_venta: id_venta
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Éxito', 'Movimiento registrado', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
        }
    });
}

function exportarPDF() {
    const tabla = document.getElementById('tablaMovimientos');
    if (!tabla || tabla.rows.length === 0) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    const caja = document.getElementById('filtroCaja').options[document.getElementById('filtroCaja').selectedIndex]?.text || 'Todas';
    const tipo = document.getElementById('filtroTipo').options[document.getElementById('filtroTipo').selectedIndex]?.text || 'Todos';
    const fd = document.getElementById('fechaDesde').value || '';
    const fh = document.getElementById('fechaHasta').value || '';
    let periodo = '';
    if (fd && fh) periodo = ` (${fd} al ${fh})`;
    else if (fd) periodo = ` (desde ${fd})`;
    else if (fh) periodo = ` (hasta ${fh})`;
    
    let htmlContent = `
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"><title>Reporte de Movimientos de Caja</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }
            h1 { color: #0d6efd; text-align: center; }
            .filters { text-align: center; margin-bottom: 20px; font-size: 10px; color: #555; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
            th { background-color: #0d6efd; color: white; }
            .footer { margin-top: 20px; text-align: right; font-size: 9px; color: #888; }
        </style>
        </head>
        <body>
            <h1>Reporte de Movimientos de Caja</h1>
            <div class="filters">
                Caja: ${escapeHtml(caja)} | Tipo: ${escapeHtml(tipo)} | Fecha: ${periodo || 'Todos'}
            </div>
            <table>
                <thead><tr><th>Fecha</th><th>Caja</th><th>Tipo</th><th>Monto</th><th>Concepto</th><th>Venta</th></tr></thead>
                <tbody>
    `;
    
    const tbody = document.querySelector('#tablaMovimientos tbody');
    const rows = tbody.querySelectorAll('tr');
    rows.forEach(row => {
        if (row.querySelector('.text-muted')) return;
        const cells = row.querySelectorAll('td');
        if (cells.length >= 6) {
            htmlContent += `<tr>
                <td>${escapeHtml(cells[0]?.innerText || '')}</td>
                <td>${escapeHtml(cells[1]?.innerText || '')}</td>
                <td>${escapeHtml(cells[2]?.innerText || '')}</td>
                <td>${escapeHtml(cells[3]?.innerText || '')}</td>
                <td>${escapeHtml(cells[4]?.innerText || '')}</td>
                <td>${escapeHtml(cells[5]?.innerText || '')}</td>
            </tr>`;
        }
    });
    
    htmlContent += `</tbody></table><div class="footer">Reporte generado el ${new Date().toLocaleString()}</div></body></html>`;
    
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    
    const opt = {
        margin: [0.5, 0.5, 0.5, 0.5],
        filename: `movimientos_caja_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    
    html2pdf().set(opt).from(element).save().then(() => {
        document.body.removeChild(element);
        Swal.fire({ icon: 'success', title: 'Exportado', text: 'Reporte generado', timer: 1500, showConfirmButton: false });
    }).catch(() => {
        document.body.removeChild(element);
        Swal.fire('Error', 'Error al generar PDF', 'error');
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        if (m === '"') return '&quot;';
        if (m === "'") return '&#39;';
        return m;
    });
}
</script>