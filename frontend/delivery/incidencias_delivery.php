<?php
require_once __DIR__ . '/../../backend/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_sesion'])) {
    header("Location: ../index.php");
    exit();
}

// Obtener parámetros de filtro
$busqueda = $_GET['busqueda'] ?? '';
$filtro_tipo = $_GET['tipo_incidencia'] ?? '';
$filtro_resuelto = $_GET['resuelto'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Obtener tipos de incidencia
$tipos_incidencia = [];
try {
    $stmtTipos = $conexion->query("SELECT id_tipo_incidencia, nombre FROM tipo_incidencia_delivery WHERE activo = TRUE ORDER BY nombre");
    $tipos_incidencia = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Consulta principal de incidencias
$query = "
    SELECT 
        i.id_incidencia,
        i.id_entrega,
        i.id_tipo_incidencia,
        ti.nombre AS tipo_nombre,
        i.fecha_incidencia,
        i.descripcion,
        i.foto_url,
        i.documento_url,
        i.reportado_por,
        i.reportado_por_repartidor,
        i.resuelto,
        i.fecha_resolucion,
        i.resolucion,
        i.resuelto_por,
        i.afecta_calificacion,
        i.compensacion_cliente,
        i.compensacion_repartidor,
        i.observaciones,
        e.id_entrega,
        e.numero_seguimiento,
        e.cliente_nombre,
        r.id_repartidor,
        r.nombre AS repartidor_nombre,
        (SELECT t.numero FROM telefonos t 
         JOIN repartidor_telefono rt ON t.id_telefono = rt.id_telefono 
         WHERE rt.id_repartidor = r.id_repartidor AND t.activo = TRUE LIMIT 1) AS repartidor_telefono
    FROM incidencias_entrega i
    LEFT JOIN tipo_incidencia_delivery ti ON i.id_tipo_incidencia = ti.id_tipo_incidencia
    LEFT JOIN entregas e ON i.id_entrega = e.id_entrega
    LEFT JOIN repartidores r ON e.id_repartidor = r.id_repartidor
    WHERE 1=1
";

$params = [];
if ($busqueda) {
    $query .= " AND (i.descripcion ILIKE :busqueda OR e.cliente_nombre ILIKE :busqueda OR r.nombre ILIKE :busqueda OR e.numero_seguimiento ILIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}
if ($filtro_tipo) {
    $query .= " AND i.id_tipo_incidencia = :tipo";
    $params[':tipo'] = $filtro_tipo;
}
if ($filtro_resuelto !== '') {
    $query .= " AND i.resuelto = :resuelto";
    $params[':resuelto'] = $filtro_resuelto === '1';
}
if ($fecha_desde && $fecha_hasta) {
    $query .= " AND i.fecha_incidencia BETWEEN :fecha_desde AND :fecha_hasta";
    $params[':fecha_desde'] = $fecha_desde;
    $params[':fecha_hasta'] = $fecha_hasta;
} elseif ($fecha_desde) {
    $query .= " AND i.fecha_incidencia >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
} elseif ($fecha_hasta) {
    $query .= " AND i.fecha_incidencia <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

$query .= " ORDER BY i.fecha_incidencia DESC";

$incidencias = [];
$total_incidencias = 0;
$total_resueltas = 0;
$total_pendientes = 0;
$total_con_compensacion = 0;

try {
    $stmt = $conexion->prepare($query);
    $stmt->execute($params);
    $incidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_incidencias = count($incidencias);
    foreach ($incidencias as $inc) {
        if ($inc['resuelto'] == 't' || $inc['resuelto'] === true || $inc['resuelto'] === 1) {
            $total_resueltas++;
        } else {
            $total_pendientes++;
        }
        if (($inc['compensacion_cliente'] > 0) || ($inc['compensacion_repartidor'] > 0)) {
            $total_con_compensacion++;
        }
    }
} catch(PDOException $e) {
    $incidencias = [];
}

$base_url = '/sistema-gestor-de-farmacias';
?>

<style>
    .hv-filtros-bar {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    .badge-estado {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
    }
    .badge-resuelto { background-color: #28a745; color: #fff; }
    .badge-pendiente { background-color: #dc3545; color: #fff; }
    .card-total {
        background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        color: white;
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .card-total h3 {
        font-size: 1.8rem;
        margin: 0;
        font-weight: 700;
    }
    .btn-quitar-filtros {
        background-color: #f1f3f5;
        color: #495057;
        border: 1.5px solid #dee2e6;
        border-radius: 10px;
        padding: 8px 20px;
    }
    .dashboard-container {
        padding: 20px;
        animation: fadeSlideIn 0.5s ease-out;
    }
    @keyframes fadeSlideIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .table-hover tbody tr:hover {
        background-color: rgba(40,167,69,0.05);
        cursor: pointer;
    }
    .modal-content {
        border-radius: 20px;
        overflow: hidden;
    }
    .btn-export-pdf {
        background-color: #dc3545;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 500;
        transition: all 0.2s ease;
        margin-left: 15px;
    }
    .btn-export-pdf:hover {
        background-color: #c82333;
        transform: translateY(-1px);
    }
    .header-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .modal-backdrop {
        display: none !important;
    }
    body.modal-open {
        overflow: auto !important;
        padding-right: 0 !important;
    }
    @media print {
        .no-print, .btn-export-pdf, .btn-quitar-filtros, .modal {
            display: none !important;
        }
    }
</style>

<div class="dashboard-container">
    <div class="header-actions">
        <div>
            <h2 class="mb-0 text-success">
                <span class="material-symbols-rounded align-middle me-2">report_problem</span>
                Incidencias del Delivery
            </h2>
            <p class="text-muted mb-0">Registro y gestión de incidencias en entregas</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" onclick="abrirModalAgregarIncidencia()">
                <span class="material-symbols-rounded align-middle me-1">add_alert</span>
                Nueva Incidencia
            </button>
            <button type="button" class="btn btn-export-pdf" onclick="exportarPDF()">
                <span class="material-symbols-rounded align-middle me-1">picture_as_pdf</span>
                Exportar a PDF
            </button>
        </div>
    </div>

    <!-- Tarjetas de totales -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card-total text-center"><small>TOTAL INCIDENCIAS</small><h3><?php echo $total_incidencias; ?></h3></div>
        </div>
        <div class="col-md-3">
            <div class="card-total text-center" style="background: linear-gradient(135deg, #28a745, #1e7e34);"><small>RESUELTAS</small><h3><?php echo $total_resueltas; ?></h3></div>
        </div>
        <div class="col-md-3">
            <div class="card-total text-center" style="background: linear-gradient(135deg, #dc3545, #bd2130);"><small>PENDIENTES</small><h3><?php echo $total_pendientes; ?></h3></div>
        </div>
        <div class="col-md-3">
            <div class="card-total text-center" style="background: linear-gradient(135deg, #ffc107, #e0a800); color: #000;"><small>CON COMPENSACIÓN</small><h3><?php echo $total_con_compensacion; ?></h3></div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="hv-filtros-bar">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted">BUSCAR</label>
                <input type="text" class="form-control" id="busquedaInput" placeholder="Descripción, cliente, repartidor, seguimiento..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">TIPO INCIDENCIA</label>
                <select class="form-select" id="filtroTipo">
                    <option value="">Todos</option>
                    <?php foreach ($tipos_incidencia as $tipo): ?>
                        <option value="<?php echo $tipo['id_tipo_incidencia']; ?>" <?php echo $filtro_tipo == $tipo['id_tipo_incidencia'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($tipo['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">ESTADO</label>
                <select class="form-select" id="filtroResuelto">
                    <option value="">Todos</option>
                    <option value="1" <?php echo $filtro_resuelto === '1' ? 'selected' : ''; ?>>Resueltas</option>
                    <option value="0" <?php echo $filtro_resuelto === '0' ? 'selected' : ''; ?>>Pendientes</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">FECHA DESDE</label>
                <input type="date" class="form-control" id="fechaDesde" value="<?php echo $fecha_desde; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">FECHA HASTA</label>
                <input type="date" class="form-control" id="fechaHasta" value="<?php echo $fecha_hasta; ?>">
            </div>
            <div class="col-md-1 text-end">
                <button type="button" class="btn btn-quitar-filtros" onclick="quitarFiltros()">Quitar filtros</button>
            </div>
        </div>
    </div>

    <!-- Tabla de incidencias -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaIncidencias">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Entrega</th>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th>Repartidor</th>
                            <th>Fecha incidencia</th>
                            <th>Resuelto</th>
                            <th>Compensación</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaIncidenciasBody">
                        <?php if (empty($incidencias)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="fas fa-exclamation-triangle d-block mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                                    No hay incidencias registradas con los filtros seleccionados
                                </td>
                            </tr>
                        <?php else: foreach ($incidencias as $inc): 
                            $resuelto_texto = ($inc['resuelto'] == 't' || $inc['resuelto'] === true || $inc['resuelto'] === 1) ? 'Sí' : 'No';
                            $resuelto_class = ($resuelto_texto === 'Sí') ? 'badge-resuelto' : 'badge-pendiente';
                            $fecha_inc = date('d/m/Y H:i', strtotime($inc['fecha_incidencia']));
                            $compensacion = ($inc['compensacion_cliente'] > 0 ? "Cliente: RD$ ".number_format($inc['compensacion_cliente'],2) : "") . 
                                            ($inc['compensacion_repartidor'] > 0 ? " Repartidor: RD$ ".number_format($inc['compensacion_repartidor'],2) : "");
                            if (empty($compensacion)) $compensacion = '—';
                            $repartidor_nombre = htmlspecialchars($inc['repartidor_nombre'] ?? 'No asignado');
                        ?>
                            <tr>
                                <td><?php echo $inc['id_incidencia']; ?></td>
                                <td>
                                    <?php if ($inc['id_entrega']): ?>
                                        <a href="javascript:void(0)" onclick="verEntrega(<?php echo $inc['id_entrega']; ?>)" class="text-decoration-none">
                                            <?php echo htmlspecialchars($inc['numero_seguimiento'] ?? 'N/D'); ?>
                                        </a>
                                    <?php else: echo '—'; endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($inc['tipo_nombre'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars(substr($inc['descripcion'], 0, 60)) . (strlen($inc['descripcion'])>60 ? '...' : ''); ?></td>
                                <td><?php echo $repartidor_nombre; ?></td>
                                <td><?php echo $fecha_inc; ?></td>
                                <td><span class="badge-estado <?php echo $resuelto_class; ?>"><?php echo $resuelto_texto; ?></span></td>
                                <td><?php echo $compensacion; ?></td>
                                <td class="text-center">
                                    <button class="btn btn-outline-info btn-sm me-1" onclick="verDetalleIncidencia(<?php echo $inc['id_incidencia']; ?>)">
                                        <span class="material-symbols-rounded">visibility</span>
                                    </button>
                                    <button class="btn btn-outline-warning btn-sm me-1" onclick="editarIncidencia(<?php echo $inc['id_incidencia']; ?>)">
                                        <span class="material-symbols-rounded">edit</span>
                                    </button>
                                    <?php if ($resuelto_texto === 'No'): ?>
                                        <button class="btn btn-outline-success btn-sm" onclick="resolverIncidencia(<?php echo $inc['id_incidencia']; ?>)">
                                            <span class="material-symbols-rounded">check_circle</span>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DETALLE INCIDENCIA -->
<div class="modal fade" id="modalDetalleIncidencia" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Detalle de Incidencia</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleIncidenciaContenido">
                <div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando...</p></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL AGREGAR/EDITAR INCIDENCIA -->
<div class="modal fade" id="modalFormIncidencia" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="formIncidenciaTitle">Nueva Incidencia</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formIncidencia">
                    <input type="hidden" id="incidencia_id" value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Entrega *</label>
                            <select class="form-select" id="incidencia_entrega" required>
                                <option value="">Seleccionar entrega</option>
                                <?php
                                // Obtener entregas recientes para el select
                                try {
                                    $stmtEnt = $conexion->query("
                                        SELECT id_entrega, numero_seguimiento, cliente_nombre 
                                        FROM entregas 
                                        ORDER BY fecha_pedido DESC LIMIT 100
                                    ");
                                    $entregas_list = $stmtEnt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($entregas_list as $ent) {
                                        echo '<option value="'.$ent['id_entrega'].'">'.htmlspecialchars($ent['numero_seguimiento'].' - '.$ent['cliente_nombre']).'</option>';
                                    }
                                } catch(PDOException $e) {}
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tipo Incidencia *</label>
                            <select class="form-select" id="incidencia_tipo" required>
                                <option value="">Seleccionar tipo</option>
                                <?php foreach ($tipos_incidencia as $tipo): ?>
                                    <option value="<?php echo $tipo['id_tipo_incidencia']; ?>"><?php echo htmlspecialchars($tipo['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Descripción *</label>
                            <textarea class="form-control" rows="3" id="incidencia_descripcion" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Foto URL</label>
                            <input type="text" class="form-control" id="incidencia_foto_url" placeholder="https://...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Documento URL</label>
                            <input type="text" class="form-control" id="incidencia_documento_url" placeholder="https://...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Reportado por</label>
                            <select class="form-select" id="incidencia_reportado_por">
                                <option value="">Seleccionar usuario</option>
                                <?php
                                try {
                                    $stmtUsu = $conexion->query("SELECT id_usuario, nombre FROM usuarios ORDER BY nombre");
                                    $usuarios = $stmtUsu->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($usuarios as $usr) {
                                        echo '<option value="'.$usr['id_usuario'].'">'.htmlspecialchars($usr['nombre']).'</option>';
                                    }
                                } catch(PDOException $e) {}
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="incidencia_reportado_por_repartidor">
                                <label class="form-check-label fw-bold">Reportado por repartidor</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="incidencia_afecta_calificacion">
                                <label class="form-check-label fw-bold">Afecta calificación</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Compensación cliente (RD$)</label>
                            <input type="number" step="0.01" class="form-control" id="incidencia_compensacion_cliente" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Compensación repartidor (RD$)</label>
                            <input type="number" step="0.01" class="form-control" id="incidencia_compensacion_repartidor" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Observaciones</label>
                            <textarea class="form-control" rows="2" id="incidencia_observaciones"></textarea>
                        </div>
                        <!-- Campos de resolución (solo se muestran al editar o resolver) -->
                        <div class="col-12" id="resolucionFields" style="display:none;">
                            <hr>
                            <h6 class="text-success">Resolución</h6>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold">Resolución</label>
                                    <textarea class="form-control" rows="3" id="incidencia_resolucion"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Resuelto por</label>
                                    <select class="form-select" id="incidencia_resuelto_por">
                                        <option value="">Seleccionar usuario</option>
                                        <?php foreach ($usuarios as $usr): ?>
                                            <option value="<?php echo $usr['id_usuario']; ?>"><?php echo htmlspecialchars($usr['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Fecha resolución</label>
                                    <input type="datetime-local" class="form-control" id="incidencia_fecha_resolucion">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="guardarIncidencia()">Guardar Incidencia</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';
let editMode = false;

// ==================== FILTROS ====================
document.addEventListener('DOMContentLoaded', function() {
    const filtros = ['busquedaInput', 'filtroTipo', 'filtroResuelto', 'fechaDesde', 'fechaHasta'];
    filtros.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', aplicarFiltros);
            if (id === 'busquedaInput') {
                let timeout;
                el.addEventListener('input', () => {
                    clearTimeout(timeout);
                    timeout = setTimeout(aplicarFiltros, 500);
                });
            }
        }
    });
});

function aplicarFiltros() {
    let url = BASE_URL + '/frontend/menuprincipal.php?mod=incidencias_delivery';
    const busqueda = document.getElementById('busquedaInput').value;
    const tipo = document.getElementById('filtroTipo').value;
    const resuelto = document.getElementById('filtroResuelto').value;
    const fd = document.getElementById('fechaDesde').value;
    const fh = document.getElementById('fechaHasta').value;
    if (busqueda) url += `&busqueda=${encodeURIComponent(busqueda)}`;
    if (tipo) url += `&tipo_incidencia=${tipo}`;
    if (resuelto !== '') url += `&resuelto=${resuelto}`;
    if (fd) url += `&fecha_desde=${fd}`;
    if (fh) url += `&fecha_hasta=${fh}`;
    window.location.href = url;
}

function quitarFiltros() {
    window.location.href = BASE_URL + '/frontend/menuprincipal.php?mod=incidencias_delivery';
}

// ==================== DETALLE ====================
function verDetalleIncidencia(id) {
    const modalBody = document.getElementById('detalleIncidenciaContenido');
    modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando...</p></div>';
    const modal = new bootstrap.Modal(document.getElementById('modalDetalleIncidencia'), { backdrop: false });
    modal.show();
    
    fetch(BASE_URL + `/backend/delivery/get_detalle_incidencia.php?id_incidencia=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                let html = `
                    <div class="row g-3">
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>ENTREGA</small><strong>${escapeHtml(data.numero_seguimiento || 'N/D')}</strong> (${escapeHtml(data.cliente_nombre || 'Sin cliente')})</div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>TIPO INCIDENCIA</small><strong>${escapeHtml(data.tipo_nombre)}</strong></div></div>
                        <div class="col-12"><div class="bg-light p-3 rounded"><small>DESCRIPCIÓN</small><p>${escapeHtml(data.descripcion)}</p></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>FECHA INCIDENCIA</small><strong>${data.fecha_incidencia_formateada}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>REPARTIDOR</small><strong>${escapeHtml(data.repartidor_nombre || 'No asignado')}</strong> ${data.repartidor_telefono ? '📞 '+escapeHtml(data.repartidor_telefono) : ''}</div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>REPORTADO POR</small><strong>${escapeHtml(data.reportado_por_nombre || 'N/D')}</strong> ${data.reportado_por_repartidor ? '(Repartidor)' : ''}</div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>AFECTA CALIFICACIÓN</small><strong>${data.afecta_calificacion ? 'Sí' : 'No'}</strong></div></div>
                `;
                if (data.foto_url) html += `<div class="col-md-6"><div class="bg-light p-3 rounded"><small>FOTO</small><br><img src="${escapeHtml(data.foto_url)}" style="max-width:100px; border-radius:10px;"></div></div>`;
                if (data.documento_url) html += `<div class="col-md-6"><div class="bg-light p-3 rounded"><small>DOCUMENTO</small><br><a href="${escapeHtml(data.documento_url)}" target="_blank">Ver documento</a></div></div>`;
                html += `
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>COMPENSACIÓN CLIENTE</small><strong>RD$ ${parseFloat(data.compensacion_cliente || 0).toLocaleString()}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>COMPENSACIÓN REPARTIDOR</small><strong>RD$ ${parseFloat(data.compensacion_repartidor || 0).toLocaleString()}</strong></div></div>
                        ${data.observaciones ? `<div class="col-12"><div class="bg-light p-3 rounded"><small>OBSERVACIONES</small><p>${escapeHtml(data.observaciones)}</p></div></div>` : ''}
                `;
                if (data.resuelto) {
                    html += `
                        <div class="col-12"><hr><h6 class="text-success">Resolución</h6></div>
                        <div class="col-12"><div class="bg-light p-3 rounded"><small>RESOLUCIÓN</small><p>${escapeHtml(data.resolucion || 'Sin resolución')}</p></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>RESUELTO POR</small><strong>${escapeHtml(data.resuelto_por_nombre || 'N/D')}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>FECHA RESOLUCIÓN</small><strong>${data.fecha_resolucion_formateada || '—'}</strong></div></div>
                    `;
                } else {
                    html += `<div class="col-12"><div class="alert alert-warning">Esta incidencia aún no ha sido resuelta.</div></div>`;
                }
                html += `</div>`;
                modalBody.innerHTML = html;
            } else {
                modalBody.innerHTML = '<div class="text-center py-5 text-danger">Error al cargar los datos</div>';
            }
        })
        .catch(() => modalBody.innerHTML = '<div class="text-center py-5 text-danger">Error de conexión</div>');
}

// ==================== AGREGAR / EDITAR ====================
function abrirModalAgregarIncidencia() {
    editMode = false;
    document.getElementById('formIncidenciaTitle').innerText = 'Nueva Incidencia';
    document.getElementById('formIncidencia').reset();
    document.getElementById('incidencia_id').value = '';
    document.getElementById('incidencia_fecha_resolucion').value = '';
    document.getElementById('resolucionFields').style.display = 'none';
    new bootstrap.Modal(document.getElementById('modalFormIncidencia'), { backdrop: false }).show();
}

function editarIncidencia(id) {
    editMode = true;
    document.getElementById('formIncidenciaTitle').innerText = 'Editar Incidencia';
    fetch(BASE_URL + `/backend/delivery/get_detalle_incidencia.php?id_incidencia=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('incidencia_id').value = id;
                document.getElementById('incidencia_entrega').value = data.id_entrega || '';
                document.getElementById('incidencia_tipo').value = data.id_tipo_incidencia || '';
                document.getElementById('incidencia_descripcion').value = data.descripcion || '';
                document.getElementById('incidencia_foto_url').value = data.foto_url || '';
                document.getElementById('incidencia_documento_url').value = data.documento_url || '';
                document.getElementById('incidencia_reportado_por').value = data.reportado_por || '';
                document.getElementById('incidencia_reportado_por_repartidor').checked = data.reportado_por_repartidor === 't' || data.reportado_por_repartidor === true || data.reportado_por_repartidor === 1;
                document.getElementById('incidencia_afecta_calificacion').checked = data.afecta_calificacion === 't' || data.afecta_calificacion === true || data.afecta_calificacion === 1;
                document.getElementById('incidencia_compensacion_cliente').value = data.compensacion_cliente || 0;
                document.getElementById('incidencia_compensacion_repartidor').value = data.compensacion_repartidor || 0;
                document.getElementById('incidencia_observaciones').value = data.observaciones || '';
                if (data.resuelto) {
                    document.getElementById('incidencia_resolucion').value = data.resolucion || '';
                    document.getElementById('incidencia_resuelto_por').value = data.resuelto_por || '';
                    const fechaRes = data.fecha_resolucion_raw ? data.fecha_resolucion_raw.slice(0,16) : '';
                    document.getElementById('incidencia_fecha_resolucion').value = fechaRes;
                    document.getElementById('resolucionFields').style.display = 'block';
                } else {
                    document.getElementById('resolucionFields').style.display = 'none';
                }
                new bootstrap.Modal(document.getElementById('modalFormIncidencia'), { backdrop: false }).show();
            } else {
                Swal.fire('Error', 'No se pudo cargar la incidencia', 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
}

function guardarIncidencia() {
    const idEntrega = document.getElementById('incidencia_entrega').value;
    const idTipo = document.getElementById('incidencia_tipo').value;
    const descripcion = document.getElementById('incidencia_descripcion').value.trim();
    if (!idEntrega || !idTipo || !descripcion) {
        Swal.fire('Error', 'Entrega, tipo y descripción son obligatorios', 'error');
        return;
    }
    
    const data = {
        id_incidencia: document.getElementById('incidencia_id').value || null,
        id_entrega: idEntrega,
        id_tipo_incidencia: idTipo,
        descripcion: descripcion,
        foto_url: document.getElementById('incidencia_foto_url').value.trim(),
        documento_url: document.getElementById('incidencia_documento_url').value.trim(),
        reportado_por: document.getElementById('incidencia_reportado_por').value || null,
        reportado_por_repartidor: document.getElementById('incidencia_reportado_por_repartidor').checked,
        afecta_calificacion: document.getElementById('incidencia_afecta_calificacion').checked,
        compensacion_cliente: parseFloat(document.getElementById('incidencia_compensacion_cliente').value) || 0,
        compensacion_repartidor: parseFloat(document.getElementById('incidencia_compensacion_repartidor').value) || 0,
        observaciones: document.getElementById('incidencia_observaciones').value.trim(),
        // Si se están enviando campos de resolución (en caso de edición)
        resolucion: document.getElementById('incidencia_resolucion').value.trim(),
        resuelto_por: document.getElementById('incidencia_resuelto_por').value || null,
        fecha_resolucion: document.getElementById('incidencia_fecha_resolucion').value || null
    };
    
    const url = editMode ? BASE_URL + '/backend/delivery/actualizar_incidencia.php' : BASE_URL + '/backend/delivery/agregar_incidencia.php';
    
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(resp => {
        if (resp.success) {
            Swal.fire('Éxito', editMode ? 'Incidencia actualizada' : 'Incidencia creada', 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', resp.message || 'No se pudo guardar', 'error');
        }
    })
    .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
}

// ==================== RESOLVER INCIDENCIA ====================
function resolverIncidencia(id) {
    Swal.fire({
        title: 'Resolver incidencia',
        html: `
            <textarea id="resolucion_text" class="swal2-textarea" placeholder="Escribe la resolución..."></textarea>
            <select id="resuelto_por_select" class="swal2-select mt-2">
                <option value="">Seleccionar usuario que resuelve</option>
                <?php foreach ($usuarios as $usr): ?>
                    <option value="<?php echo $usr['id_usuario']; ?>"><?php echo htmlspecialchars($usr['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        `,
        showCancelButton: true,
        confirmButtonText: 'Resolver',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const resolucion = document.getElementById('resolucion_text').value;
            const resueltoPor = document.getElementById('resuelto_por_select').value;
            if (!resolucion) {
                Swal.showValidationMessage('Debes escribir una resolución');
                return false;
            }
            if (!resueltoPor) {
                Swal.showValidationMessage('Debes seleccionar quién resuelve');
                return false;
            }
            return { resolucion, resuelto_por: resueltoPor };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(BASE_URL + `/backend/delivery/resolver_incidencia.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_incidencia: id,
                    resolucion: result.value.resolucion,
                    resuelto_por: result.value.resuelto_por
                })
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    Swal.fire('Éxito', 'Incidencia resuelta correctamente', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', resp.message || 'Error al resolver', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
        }
    });
}

function verEntrega(idEntrega) {
    window.location.href = BASE_URL + '/frontend/menuprincipal.php?mod=entregas&id_entrega=' + idEntrega;
}

// ==================== EXPORTAR PDF ====================
function exportarPDF() {
    const tabla = document.getElementById('tablaIncidencias');
    if (!tabla || tabla.rows.length === 0) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    const busqueda = document.getElementById('busquedaInput').value || 'Sin búsqueda';
    const tipo = document.getElementById('filtroTipo').options[document.getElementById('filtroTipo').selectedIndex]?.text || 'Todos';
    const resuelto = document.getElementById('filtroResuelto').options[document.getElementById('filtroResuelto').selectedIndex]?.text || 'Todos';
    const fd = document.getElementById('fechaDesde').value || '';
    const fh = document.getElementById('fechaHasta').value || '';
    let periodo = '';
    if (fd && fh) periodo = ` (${fd} al ${fh})`;
    else if (fd) periodo = ` (desde ${fd})`;
    else if (fh) periodo = ` (hasta ${fh})`;
    
    let htmlContent = `
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"><title>Reporte de Incidencias Delivery</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }
            h1 { color: #28a745; text-align: center; }
            .filters { text-align: center; margin-bottom: 20px; font-size: 10px; color: #555; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
            th { background-color: #28a745; color: white; }
            .footer { margin-top: 20px; text-align: right; font-size: 9px; color: #888; }
        </style>
        </head>
        <body>
            <h1>Reporte de Incidencias del Delivery</h1>
            <div class="filters">
                Búsqueda: ${escapeHtml(busqueda)} | Tipo: ${escapeHtml(tipo)} | Estado: ${escapeHtml(resuelto)} | Fecha: ${periodo || 'Todos'}
            </div>
            <table>
                <thead><tr><th>ID</th><th>Entrega</th><th>Tipo</th><th>Descripción</th><th>Repartidor</th><th>Fecha</th><th>Resuelto</th><th>Compensación</th></tr></thead>
                <tbody>
    `;
    
    const tbody = document.getElementById('tablaIncidenciasBody');
    const rows = tbody.querySelectorAll('tr');
    rows.forEach(row => {
        if (row.querySelector('.text-muted')) return;
        const cells = row.querySelectorAll('td');
        if (cells.length >= 9) {
            htmlContent += `<tr>
                <td>${escapeHtml(cells[0]?.innerText || '')}</td>
                <td>${escapeHtml(cells[1]?.innerText || '')}</td>
                <td>${escapeHtml(cells[2]?.innerText || '')}</td>
                <td>${escapeHtml(cells[3]?.innerText || '')}</td>
                <td>${escapeHtml(cells[4]?.innerText || '')}</td>
                <td>${escapeHtml(cells[5]?.innerText || '')}</td>
                <td>${escapeHtml(cells[6]?.innerText || '')}</td>
                <td>${escapeHtml(cells[7]?.innerText || '')}</td>
            </tr>`;
        }
    });
    
    htmlContent += `</tbody></table><div class="footer">Reporte generado el ${new Date().toLocaleString()}</div></body></html>`;
    
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    
    const opt = {
        margin: [0.5, 0.5, 0.5, 0.5],
        filename: `incidencias_delivery_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    
    html2pdf().set(opt).from(element).save().then(() => {
        document.body.removeChild(element);
        Swal.fire({ icon: 'success', title: 'Exportado', text: 'Reporte generado correctamente', timer: 2000, showConfirmButton: false });
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