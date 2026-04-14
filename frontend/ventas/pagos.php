<?php
require_once __DIR__ . '/../../backend/conexion.php';

// Verificar sesión
if (!isset($_SESSION['id_sesion'])) {
    header("Location: ../index.php");
    exit();
}

// Obtener parámetros de filtro
$filtro_fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$filtro_metodo = $_GET['metodo'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

// Obtener listas para filtros
$metodos_pago = [];

try {
    $stmt = $conexion->query("SELECT id_metodo, nombre FROM metodos_pago ORDER BY nombre");
    $metodos_pago = $stmt->fetchAll();
} catch(PDOException $e) {}

// Construir consulta base
$query = "
    SELECT 
        p.id_pago,
        p.monto,
        p.fecha_transaccion,
        p.referencia,
        COALESCE(p.estado, 'COMPLETADO') as estado,
        v.id_venta,
        v.numero_documento,
        v.total as total_venta,
        COALESCE(c.nombre, 'Consumidor Final') as cliente_nombre,
        u.nombre as usuario_nombre,
        mp.nombre as metodo_nombre,
        CASE 
            WHEN p.id_aseguradora IS NOT NULL THEN a.nombre
            ELSE NULL
        END as aseguradora_nombre,
        COALESCE(p.monto_seguro, 0) as monto_seguro,
        COALESCE(p.monto_paciente, p.monto) as monto_paciente,
        p.estado_seguro
    FROM pagos p
    JOIN ventas v ON p.id_venta = v.id_venta
    JOIN usuarios u ON v.id_usuario = u.id_usuario
    JOIN metodos_pago mp ON p.id_metodo = mp.id_metodo
    LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
    LEFT JOIN aseguradoras a ON p.id_aseguradora = a.id_aseguradora
    WHERE 1=1
";

$params = [];

if ($filtro_fecha_desde && $filtro_fecha_hasta) {
    $query .= " AND DATE(p.fecha_transaccion) BETWEEN :fecha_desde AND :fecha_hasta";
    $params[':fecha_desde'] = $filtro_fecha_desde;
    $params[':fecha_hasta'] = $filtro_fecha_hasta;
}

if ($filtro_metodo) {
    $query .= " AND p.id_metodo = :metodo";
    $params[':metodo'] = $filtro_metodo;
}

if ($filtro_estado) {
    $query .= " AND p.estado = :estado";
    $params[':estado'] = $filtro_estado;
}

if ($busqueda) {
    $query .= " AND (v.numero_documento ILIKE :busqueda OR c.nombre ILIKE :busqueda OR p.referencia ILIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}

$query .= " ORDER BY p.fecha_transaccion DESC LIMIT 500";

$pagos = [];
try {
    $stmt = $conexion->prepare($query);
    $stmt->execute($params);
    $pagos = $stmt->fetchAll();
} catch(PDOException $e) {
    $pagos = [];
}

// Calcular totales
$total_pagos = count($pagos);
$suma_monto = 0;
$suma_seguro = 0;
$suma_paciente = 0;
foreach ($pagos as $p) {
    $suma_monto += floatval($p['monto']);
    $suma_seguro += floatval($p['monto_seguro'] ?? 0);
    $suma_paciente += floatval($p['monto_paciente'] ?? 0);
}

$base_url = '/sistema-gestor-de-farmacias';
?>

<style>
    .modal-backdrop { display: none !important; }
    .modal { background-color: rgba(0, 0, 0, 0.5) !important; z-index: 1050; }
    .modal-dialog-centered { display: flex; align-items: center; min-height: calc(100% - 1rem); }
    .modal.show .modal-dialog { transform: none; margin: 1.75rem auto; }
    
    .filtros-bar {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 25px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    
    .badge-estado {
        padding: 6px 14px;
        border-radius: 30px;
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.3px;
    }
    .badge-completado { background-color: #28a745; color: #fff; }
    .badge-pendiente { background-color: #ffc107; color: #000; }
    .badge-rechazado { background-color: #dc3545; color: #fff; }
    .badge-anulado { background-color: #6c757d; color: #fff; }
    
    .card-total {
        background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        color: white;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 25px;
        transition: transform 0.2s ease;
    }
    
    .card-total:hover {
        transform: translateY(-3px);
    }
    
    .card-total h3 {
        font-size: 2rem;
        margin: 5px 0 0;
        font-weight: 700;
    }
    
    .card-total small {
        opacity: 0.85;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .detalle-item {
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .detalle-item:last-child {
        border-bottom: none;
    }
    
    .form-control, .form-select {
        border: 1.5px solid #e9ecef !important;
        border-radius: 12px;
        padding: 10px 14px;
        transition: all 0.2s ease;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #28a745 !important;
        box-shadow: 0 0 0 3px rgba(40,167,69,0.1) !important;
    }
    
    .dashboard-container {
        padding: 20px;
        animation: fadeSlideIn 0.4s ease-out;
    }
    
    @keyframes fadeSlideIn {
        from {
            opacity: 0;
            transform: translateY(15px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .table-pagos {
        border-radius: 16px;
        overflow: hidden;
    }
    
    .table-pagos thead th {
        background: #f8f9fa;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        font-weight: 700;
        color: #6c757d;
        padding: 15px 12px;
        border-bottom: 2px solid #e9ecef;
    }
    
    .table-pagos tbody td {
        padding: 14px 12px;
        vertical-align: middle;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .table-pagos tbody tr:hover {
        background-color: rgba(40,167,69,0.03);
        cursor: pointer;
    }
    
    .btn-quitar-filtros {
        background-color: #f1f3f5;
        color: #495057;
        border: 1.5px solid #e9ecef;
        border-radius: 12px;
        padding: 10px 20px;
        font-weight: 500;
        transition: all 0.2s ease;
    }
    
    .btn-quitar-filtros:hover {
        background-color: #e9ecef;
        border-color: #dee2e6;
        color: #212529;
        transform: translateY(-1px);
    }
    
    .btn-cancelar {
        background-color: #f1f3f5;
        color: #495057;
        border: 1.5px solid #e9ecef;
        border-radius: 12px;
        padding: 10px 25px;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    
    .btn-cancelar:hover {
        background-color: #e9ecef;
        border-color: #dee2e6;
        color: #212529;
        transform: translateY(-1px);
    }
    
    .btn-accion {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }
    
    .btn-accion:hover {
        transform: scale(1.08);
    }
    
    .modal-content {
        border-radius: 20px;
        overflow: hidden;
    }
    
    .modal-header {
        border-bottom: none;
        padding: 20px 25px;
    }
    
    .modal-body {
        padding: 20px 25px;
    }
    
    .modal-footer {
        border-top: none;
        padding: 15px 25px 25px;
    }
    
    hr {
        opacity: 0.5;
        margin: 15px 0;
    }
</style>

<div class="dashboard-container">
    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1 text-success d-flex align-items-center gap-2">
                <span class="material-symbols-rounded" style="font-size: 32px;">payments</span>
                Gestión de Pagos
            </h2>
            <p class="text-muted mb-0">Administre y consulte todas las transacciones de pago</p>
        </div>
        <div>
            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">
                <i class="fas fa-credit-card me-1"></i> <?php echo $total_pagos; ?> pagos registrados
            </span>
        </div>
    </div>

    <!-- Tarjetas de totales -->
    <div class="row mb-4 g-4">
        <div class="col-md-4">
            <div class="card-total text-center">
                <small><i class="fas fa-chart-line me-1"></i> TOTAL DE PAGOS</small>
                <h3><?php echo $total_pagos; ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-total text-center">
                <small><i class="fas fa-dollar-sign me-1"></i> MONTO TOTAL</small>
                <h3>RD$ <?php echo number_format($suma_monto, 2); ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-total text-center">
                <small><i class="fas fa-shield-alt me-1"></i> PAGOS POR SEGURO</small>
                <h3>RD$ <?php echo number_format($suma_seguro, 2); ?></h3>
            </div>
        </div>
    </div>

    <!-- Filtros mejorados -->
    <div class="filtros-bar">
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold small text-muted mb-1">
                    <i class="far fa-calendar-alt me-1"></i> DESDE
                </label>
                <input type="date" class="form-control" id="filtroFechaDesde" value="<?php echo $filtro_fecha_desde; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small text-muted mb-1">
                    <i class="far fa-calendar-alt me-1"></i> HASTA
                </label>
                <input type="date" class="form-control" id="filtroFechaHasta" value="<?php echo $filtro_fecha_hasta; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small text-muted mb-1">
                    <i class="fas fa-credit-card me-1"></i> MÉTODO
                </label>
                <select class="form-select" id="filtroMetodo">
                    <option value="">Todos</option>
                    <?php foreach ($metodos_pago as $met): ?>
                        <option value="<?php echo $met['id_metodo']; ?>" <?php echo $filtro_metodo == $met['id_metodo'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($met['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small text-muted mb-1">
                    <i class="fas fa-circle me-1"></i> ESTADO
                </label>
                <select class="form-select" id="filtroEstado">
                    <option value="">Todos</option>
                    <option value="COMPLETADO" <?php echo $filtro_estado == 'COMPLETADO' ? 'selected' : ''; ?>>Completado</option>
                    <option value="PENDIENTE" <?php echo $filtro_estado == 'PENDIENTE' ? 'selected' : ''; ?>>Pendiente</option>
                    <option value="RECHAZADO" <?php echo $filtro_estado == 'RECHAZADO' ? 'selected' : ''; ?>>Rechazado</option>
                    <option value="ANULADO" <?php echo $filtro_estado == 'ANULADO' ? 'selected' : ''; ?>>Anulado</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small text-muted mb-1">
                    <i class="fas fa-search me-1"></i> BUSCAR
                </label>
                <input type="text" class="form-control" id="busquedaInput" placeholder="Factura, cliente o referencia..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-quitar-filtros w-100" onclick="quitarFiltros()" title="Quitar filtros">
                    <span class="material-symbols-rounded">filter_list_off</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Tabla de pagos mejorada -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-pagos mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Factura</th>
                            <th>Cliente</th>
                            <th>Método</th>
                            <th>Monto</th>
                            <th>Seguro</th>
                            <th>Paciente</th>
                            <th>Referencia</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pagos)): ?>
                            <tr>
                                <td colspan="11" class="text-center text-muted py-5">
                                    <i class="fas fa-credit-card d-block mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                                    <p class="mb-0">No hay pagos registrados con los filtros seleccionados</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pagos as $pago): 
                                $estado_class = '';
                                $estado_icono = '';
                                
                                switch ($pago['estado']) {
                                    case 'COMPLETADO':
                                        $estado_class = 'badge-completado';
                                        $estado_icono = 'check_circle';
                                        break;
                                    case 'PENDIENTE':
                                        $estado_class = 'badge-pendiente';
                                        $estado_icono = 'pending';
                                        break;
                                    case 'RECHAZADO':
                                        $estado_class = 'badge-rechazado';
                                        $estado_icono = 'cancel';
                                        break;
                                    case 'ANULADO':
                                        $estado_class = 'badge-anulado';
                                        $estado_icono = 'block';
                                        break;
                                    default:
                                        $estado_class = 'badge-pendiente';
                                        $estado_icono = 'pending';
                                }
                            ?>
                                <tr onclick="verDetallePago(<?php echo $pago['id_pago']; ?>)" style="cursor: pointer;">
                                    <td class="fw-bold text-success">#<?php echo $pago['id_pago']; ?></td>
                                    <td class="text-nowrap"><?php echo date('d/m/Y H:i', strtotime($pago['fecha_transaccion'])); ?></td>
                                    <td>
                                        <code class="bg-light px-2 py-1 rounded"><?php echo htmlspecialchars($pago['numero_documento']); ?></code>
                                    </td>
                                    <td class="fw-medium"><?php echo htmlspecialchars($pago['cliente_nombre']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-1 rounded-pill">
                                            <i class="fas <?php 
                                                echo $pago['metodo_nombre'] == 'Efectivo' ? 'fa-money-bill' : 
                                                    ($pago['metodo_nombre'] == 'Tarjeta' ? 'fa-credit-card' : 'fa-exchange-alt'); 
                                            ?> me-1"></i>
                                            <?php echo htmlspecialchars($pago['metodo_nombre']); ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-success">RD$ <?php echo number_format($pago['monto'], 2); ?></td>
                                    <td>
                                        <?php if ($pago['monto_seguro'] > 0): ?>
                                            <span class="text-info fw-semibold">RD$ <?php echo number_format($pago['monto_seguro'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($pago['monto_paciente'] > 0): ?>
                                            <span class="text-warning fw-semibold">RD$ <?php echo number_format($pago['monto_paciente'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($pago['referencia']): ?>
                                            <small class="text-muted"><code><?php echo htmlspecialchars(substr($pago['referencia'], 0, 15)); ?></code></small>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-estado <?php echo $estado_class; ?> d-inline-flex align-items-center gap-1">
                                            <span class="material-symbols-rounded" style="font-size: 14px;"><?php echo $estado_icono; ?></span>
                                            <?php echo $pago['estado']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-1 justify-content-center" onclick="event.stopPropagation();">
                                            <button class="btn btn-outline-primary btn-accion" onclick="verDetallePago(<?php echo $pago['id_pago']; ?>)" title="Ver detalle">
                                                <span class="material-symbols-rounded" style="font-size: 18px;">visibility</span>
                                            </button>
                                            <?php if ($pago['estado'] == 'COMPLETADO'): ?>
                                                <button class="btn btn-outline-secondary btn-accion" onclick="imprimirReciboPago(<?php echo $pago['id_pago']; ?>)" title="Imprimir recibo">
                                                    <span class="material-symbols-rounded" style="font-size: 18px;">print</span>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($pago['estado'] == 'PENDIENTE'): ?>
                                                <button class="btn btn-outline-success btn-accion" onclick="confirmarPago(<?php echo $pago['id_pago']; ?>)" title="Confirmar pago">
                                                    <span class="material-symbols-rounded" style="font-size: 18px;">check_circle</span>
                                                </button>
                                                <button class="btn btn-outline-danger btn-accion" onclick="anularPago(<?php echo $pago['id_pago']; ?>)" title="Anular pago">
                                                    <span class="material-symbols-rounded" style="font-size: 18px;">cancel</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
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

<!-- MODAL PARA VER DETALLE DE PAGO -->
<div class="modal fade" id="modalDetallePago" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <span class="material-symbols-rounded">receipt</span>
                    Detalle del Pago
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detallePagoContenido">
                <div class="text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                    <p class="mt-2">Cargando detalles...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-secondary" id="btnImprimirPagoModal" style="display: none;" onclick="imprimirReciboPagoDesdeModal()">
                    <span class="material-symbols-rounded align-middle me-1">print</span>
                    Imprimir Recibo
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';
let pagoActualId = null;

// ==================== FILTROS AUTOMÁTICOS ====================
document.addEventListener('DOMContentLoaded', function() {
    const filtros = ['filtroFechaDesde', 'filtroFechaHasta', 'filtroMetodo', 'filtroEstado', 'busquedaInput'];
    
    filtros.forEach(id => {
        const elemento = document.getElementById(id);
        if (elemento) {
            elemento.addEventListener('change', aplicarFiltros);
            if (id === 'busquedaInput') {
                let timeout;
                elemento.addEventListener('input', function() {
                    clearTimeout(timeout);
                    timeout = setTimeout(aplicarFiltros, 500);
                });
            }
        }
    });
});

function aplicarFiltros() {
    const fechaDesde = document.getElementById('filtroFechaDesde').value;
    const fechaHasta = document.getElementById('filtroFechaHasta').value;
    const metodo = document.getElementById('filtroMetodo').value;
    const estado = document.getElementById('filtroEstado').value;
    const busqueda = document.getElementById('busquedaInput').value;
    
    let url = BASE_URL + '/frontend/menuprincipal.php?mod=pagos';
    
    if (fechaDesde) url += `&fecha_desde=${fechaDesde}`;
    if (fechaHasta) url += `&fecha_hasta=${fechaHasta}`;
    if (metodo) url += `&metodo=${metodo}`;
    if (estado) url += `&estado=${estado}`;
    if (busqueda) url += `&busqueda=${encodeURIComponent(busqueda)}`;
    
    window.location.href = url;
}

function quitarFiltros() {
    window.location.href = BASE_URL + '/frontend/menuprincipal.php?mod=pagos';
}

// ==================== FUNCIONES DE PAGOS ====================
function verDetallePago(idPago) {
    pagoActualId = idPago;
    const modalBody = document.getElementById('detallePagoContenido');
    modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success" role="status"></div><p class="mt-2">Cargando detalles...</p></div>';
    
    const modal = new bootstrap.Modal(document.getElementById('modalDetallePago'), {
        backdrop: false,
        keyboard: true
    });
    modal.show();
    
    fetch(BASE_URL + `/backend/ventas/get_detalle_pago.php?id_pago=${idPago}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const estadoClass = data.estado === 'COMPLETADO' ? 'bg-success' : (data.estado === 'PENDIENTE' ? 'bg-warning' : 'bg-danger');
                const estadoIcono = data.estado === 'COMPLETADO' ? 'check_circle' : (data.estado === 'PENDIENTE' ? 'pending' : 'cancel');
                
                let seguroHtml = '';
                if (data.monto_seguro > 0) {
                    seguroHtml = `
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <div class="bg-light p-3 rounded-3">
                                    <small class="text-muted d-block mb-1">🏥 ASEGURADORA</small>
                                    <strong>${escapeHtml(data.aseguradora_nombre || 'N/A')}</strong>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="bg-light p-3 rounded-3">
                                    <small class="text-muted d-block mb-1">💰 MONTO SEGURO</small>
                                    <strong class="text-info">RD$ ${parseFloat(data.monto_seguro).toLocaleString()}</strong>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="bg-light p-3 rounded-3">
                                    <small class="text-muted d-block mb-1">👤 MONTO PACIENTE</small>
                                    <strong class="text-warning">RD$ ${parseFloat(data.monto_paciente).toLocaleString()}</strong>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="bg-light p-3 rounded-3">
                                    <small class="text-muted d-block mb-1">📋 ESTADO DEL SEGURO</small>
                                    <span class="badge ${data.estado_seguro === 'APROBADO' ? 'bg-success' : 'bg-warning'}">${data.estado_seguro || 'PENDIENTE'}</span>
                                </div>
                            </div>
                        </div>
                    `;
                }
                
                const html = `
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="bg-light p-3 rounded-3">
                                <small class="text-muted d-block mb-1">🔢 ID PAGO</small>
                                <strong class="fs-4">#${data.id_pago}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="bg-light p-3 rounded-3">
                                <small class="text-muted d-block mb-1">📅 FECHA</small>
                                <strong>${data.fecha}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="bg-light p-3 rounded-3">
                                <small class="text-muted d-block mb-1">🧾 FACTURA</small>
                                <strong><code>${escapeHtml(data.numero_documento)}</code></strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="bg-light p-3 rounded-3">
                                <small class="text-muted d-block mb-1">👤 CLIENTE</small>
                                <strong>${escapeHtml(data.cliente_nombre)}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="bg-light p-3 rounded-3">
                                <small class="text-muted d-block mb-1">💳 MÉTODO DE PAGO</small>
                                <strong>${escapeHtml(data.metodo_nombre)}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="bg-light p-3 rounded-3">
                                <small class="text-muted d-block mb-1">📌 ESTADO</small>
                                <span class="badge ${estadoClass} d-inline-flex align-items-center gap-1 px-3 py-2">
                                    <span class="material-symbols-rounded" style="font-size: 14px;">${estadoIcono}</span>
                                    ${data.estado}
                                </span>
                            </div>
                        </div>
                        ${data.referencia ? `
                        <div class="col-12">
                            <div class="bg-light p-3 rounded-3">
                                <small class="text-muted d-block mb-1">🔖 REFERENCIA</small>
                                <code>${escapeHtml(data.referencia)}</code>
                            </div>
                        </div>
                        ` : ''}
                        ${seguroHtml}
                        <div class="col-12">
                            <div class="bg-success bg-opacity-10 p-4 rounded-3 text-end">
                                <small class="text-muted">Total de la venta: RD$ ${parseFloat(data.total_venta).toLocaleString()}</small><br>
                                <strong class="fs-2 text-success">MONTO PAGADO: RD$ ${parseFloat(data.monto).toLocaleString()}</strong>
                            </div>
                        </div>
                    </div>
                `;
                modalBody.innerHTML = html;
                
                const btnImprimir = document.getElementById('btnImprimirPagoModal');
                if (data.estado === 'COMPLETADO') {
                    btnImprimir.style.display = 'inline-flex';
                } else {
                    btnImprimir.style.display = 'none';
                }
            } else {
                modalBody.innerHTML = '<div class="text-center py-5 text-danger">❌ Error al cargar los detalles</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalBody.innerHTML = '<div class="text-center py-5 text-danger">❌ Error de conexión</div>';
        });
}

function confirmarPago(idPago) {
    Swal.fire({
        title: '¿Confirmar este pago?',
        text: 'Una vez confirmado, no podrá modificarse',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        confirmButtonText: '✓ Sí, confirmar',
        cancelButtonText: '✗ Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            
            fetch(BASE_URL + '/backend/ventas/confirmar_pago.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_pago: idPago })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire('¡Éxito!', 'Pago confirmado correctamente', 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message || 'Error al confirmar pago', 'error');
                }
            });
        }
    });
}

function anularPago(idPago) {
    Swal.fire({
        title: '¿Anular este pago?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: '✓ Sí, anular',
        cancelButtonText: '✗ Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            
            fetch(BASE_URL + '/backend/ventas/anular_pago.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_pago: idPago })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire('Anulado', 'Pago anulado correctamente', 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message || 'Error al anular pago', 'error');
                }
            });
        }
    });
}

function imprimirReciboPago(idPago) {
    window.open(BASE_URL + `/backend/ventas/imprimir_recibo_pago.php?id_pago=${idPago}`, '_blank');
}

function imprimirReciboPagoDesdeModal() {
    if (pagoActualId) {
        imprimirReciboPago(pagoActualId);
    }
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