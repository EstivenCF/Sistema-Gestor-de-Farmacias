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
$filtro_estado = $_GET['estado'] ?? '';
$filtro_repartidor = $_GET['repartidor'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Obtener lista de repartidores activos para el filtro
$repartidores_opciones = [];
try {
    $stmtRep = $conexion->query("SELECT id_repartidor, nombre FROM repartidores WHERE activo = TRUE ORDER BY nombre");
    $repartidores_opciones = $stmtRep->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Consulta principal de entregas
$query = "
    SELECT 
        e.id_entrega,
        e.cliente_nombre,
        e.direccion_entrega,
        e.fecha_asignacion,
        e.fecha_entrega_real,
        e.estado,
        e.observaciones,
        r.id_repartidor,
        r.nombre AS repartidor_nombre,
        (SELECT t.numero FROM telefonos t 
         JOIN repartidor_telefono rt ON t.id_telefono = rt.id_telefono 
         WHERE rt.id_repartidor = r.id_repartidor AND t.activo = TRUE LIMIT 1) AS repartidor_telefono
    FROM entregas e
    LEFT JOIN repartidores r ON e.id_repartidor = r.id_repartidor
    WHERE 1=1
";

$params = [];
if ($busqueda) {
    $query .= " AND (e.cliente_nombre ILIKE :busqueda OR e.direccion_entrega ILIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}
if ($filtro_estado) {
    $query .= " AND e.estado = :estado";
    $params[':estado'] = $filtro_estado;
}
if ($filtro_repartidor) {
    $query .= " AND e.id_repartidor = :repartidor";
    $params[':repartidor'] = $filtro_repartidor;
}
if ($fecha_desde && $fecha_hasta) {
    $query .= " AND e.fecha_asignacion BETWEEN :fecha_desde AND :fecha_hasta";
    $params[':fecha_desde'] = $fecha_desde;
    $params[':fecha_hasta'] = $fecha_hasta;
} elseif ($fecha_desde) {
    $query .= " AND e.fecha_asignacion >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
} elseif ($fecha_hasta) {
    $query .= " AND e.fecha_asignacion <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

$query .= " ORDER BY e.fecha_asignacion DESC";

$entregas = [];
$total_entregas = 0;
$total_pendientes = 0;
$total_en_camino = 0;
$total_entregadas = 0;
$total_canceladas = 0;

try {
    $stmt = $conexion->prepare($query);
    $stmt->execute($params);
    $entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_entregas = count($entregas);
    foreach ($entregas as $e) {
        switch ($e['estado']) {
            case 'pendiente': $total_pendientes++; break;
            case 'en_camino': $total_en_camino++; break;
            case 'entregado': $total_entregadas++; break;
            case 'cancelado': $total_canceladas++; break;
        }
    }
} catch(PDOException $e) {
    $entregas = [];
}

$estados = ['pendiente', 'en_camino', 'entregado', 'cancelado'];
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
    .badge-pendiente { background-color: #ffc107; color: #000; }
    .badge-en_camino { background-color: #17a2b8; color: #fff; }
    .badge-entregado { background-color: #28a745; color: #fff; }
    .badge-cancelado { background-color: #dc3545; color: #fff; }
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
                <!-- ✅ Ícono CORREGIDO: local_shipping en lugar de delivery_truck -->
                <span class="material-symbols-rounded align-middle me-2">local_shipping</span>
                Gestión de Entregas
            </h2>
            <p class="text-muted mb-0">Administra las entregas a domicilio</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" onclick="abrirModalAgregarEntrega()">
                <span class="material-symbols-rounded align-middle me-1">add_location_alt</span>
                Nueva Entrega
            </button>
            <button type="button" class="btn btn-export-pdf" onclick="exportarPDF()">
                <span class="material-symbols-rounded align-middle me-1">picture_as_pdf</span>
                Exportar a PDF
            </button>
        </div>
    </div>

    <!-- Tarjetas de totales -->
    <div class="row mb-4">
        <div class="col-md-2 col-sm-6">
            <div class="card-total text-center"><small>TOTAL</small><h3><?php echo $total_entregas; ?></h3></div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="card-total text-center" style="background: linear-gradient(135deg, #ffc107, #e0a800);"><small>PENDIENTES</small><h3><?php echo $total_pendientes; ?></h3></div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="card-total text-center" style="background: linear-gradient(135deg, #17a2b8, #117a8b);"><small>EN CAMINO</small><h3><?php echo $total_en_camino; ?></h3></div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="card-total text-center" style="background: linear-gradient(135deg, #28a745, #1e7e34);"><small>ENTREGADAS</small><h3><?php echo $total_entregadas; ?></h3></div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="card-total text-center" style="background: linear-gradient(135deg, #dc3545, #bd2130);"><small>CANCELADAS</small><h3><?php echo $total_canceladas; ?></h3></div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="hv-filtros-bar">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted">CLIENTE / DIRECCIÓN</label>
                <input type="text" class="form-control" id="busquedaInput" placeholder="Nombre o dirección" value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">ESTADO</label>
                <select class="form-select" id="filtroEstado">
                    <option value="">Todos</option>
                    <option value="pendiente" <?php echo $filtro_estado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                    <option value="en_camino" <?php echo $filtro_estado === 'en_camino' ? 'selected' : ''; ?>>En camino</option>
                    <option value="entregado" <?php echo $filtro_estado === 'entregado' ? 'selected' : ''; ?>>Entregado</option>
                    <option value="cancelado" <?php echo $filtro_estado === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">REPARTIDOR</label>
                <select class="form-select" id="filtroRepartidor">
                    <option value="">Todos</option>
                    <?php foreach ($repartidores_opciones as $rep): ?>
                        <option value="<?php echo $rep['id_repartidor']; ?>" <?php echo $filtro_repartidor == $rep['id_repartidor'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($rep['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
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

    <!-- Tabla de entregas -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaEntregas">
                    <thead class="table-light">
                        <tr>
                            <th>Cliente</th>
                            <th>Dirección</th>
                            <th>Repartidor</th>
                            <th>Fecha asignación</th>
                            <th>Fecha entrega real</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaEntregasBody">
                        <?php if (empty($entregas)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="fas fa-truck d-block mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                                    No hay entregas registradas con los filtros seleccionados
                                </td>
                            </tr>
                        <?php else: foreach ($entregas as $entrega): 
                            $estado_texto = ucfirst(str_replace('_', ' ', $entrega['estado']));
                            $badge_class = '';
                            switch($entrega['estado']) {
                                case 'pendiente': $badge_class = 'badge-pendiente'; break;
                                case 'en_camino': $badge_class = 'badge-en_camino'; break;
                                case 'entregado': $badge_class = 'badge-entregado'; break;
                                case 'cancelado': $badge_class = 'badge-cancelado'; break;
                            }
                            $fecha_asignacion = date('d/m/Y H:i', strtotime($entrega['fecha_asignacion']));
                            $fecha_entrega_real = $entrega['fecha_entrega_real'] ? date('d/m/Y H:i', strtotime($entrega['fecha_entrega_real'])) : '—';
                        ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($entrega['cliente_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($entrega['direccion_entrega']); ?></td>
                                <td><?php echo htmlspecialchars($entrega['repartidor_nombre'] ?? 'No asignado'); ?></td>
                                <td><?php echo $fecha_asignacion; ?></td>
                                <td><?php echo $fecha_entrega_real; ?></td>
                                <td><span class="badge-estado <?php echo $badge_class; ?>"><?php echo $estado_texto; ?></span></td>
                                <td class="text-center">
                                    <button class="btn btn-outline-info btn-sm me-1" onclick="verDetalleEntrega(<?php echo $entrega['id_entrega']; ?>)">
                                        <span class="material-symbols-rounded">visibility</span>
                                    </button>
                                    <button class="btn btn-outline-warning btn-sm me-1" onclick="editarEntrega(<?php echo $entrega['id_entrega']; ?>)">
                                        <span class="material-symbols-rounded">edit</span>
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="cambiarEstadoEntrega(<?php echo $entrega['id_entrega']; ?>, '<?php echo $entrega['estado']; ?>')">
                                        <span class="material-symbols-rounded">sync</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DETALLE ENTREGA -->
<div class="modal fade" id="modalDetalleEntrega" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Detalle de la Entrega</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleEntregaContenido">
                <div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando...</p></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL AGREGAR/EDITAR ENTREGA -->
<div class="modal fade" id="modalFormEntrega" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="formEntregaTitle">Nueva Entrega</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formEntrega">
                    <input type="hidden" id="entrega_id" value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Cliente *</label>
                            <input type="text" class="form-control" id="entrega_cliente_nombre" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Repartidor</label>
                            <select class="form-select" id="entrega_repartidor">
                                <option value="">Seleccionar repartidor</option>
                                <?php foreach ($repartidores_opciones as $rep): ?>
                                    <option value="<?php echo $rep['id_repartidor']; ?>"><?php echo htmlspecialchars($rep['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Dirección de entrega *</label>
                            <textarea class="form-control" rows="2" id="entrega_direccion" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Fecha asignación</label>
                            <input type="datetime-local" class="form-control" id="entrega_fecha_asignacion" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Fecha entrega real</label>
                            <input type="datetime-local" class="form-control" id="entrega_fecha_entrega_real">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Estado</label>
                            <select class="form-select" id="entrega_estado">
                                <option value="pendiente">Pendiente</option>
                                <option value="en_camino">En camino</option>
                                <option value="entregado">Entregado</option>
                                <option value="cancelado">Cancelado</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Observaciones</label>
                            <textarea class="form-control" rows="2" id="entrega_observaciones"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="guardarEntrega()">Guardar Entrega</button>
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
    const filtros = ['busquedaInput', 'filtroEstado', 'filtroRepartidor', 'fechaDesde', 'fechaHasta'];
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
    let url = BASE_URL + '/frontend/menuprincipal.php?mod=entregas';
    const busqueda = document.getElementById('busquedaInput').value;
    const estado = document.getElementById('filtroEstado').value;
    const repartidor = document.getElementById('filtroRepartidor').value;
    const fd = document.getElementById('fechaDesde').value;
    const fh = document.getElementById('fechaHasta').value;
    if (busqueda) url += `&busqueda=${encodeURIComponent(busqueda)}`;
    if (estado) url += `&estado=${estado}`;
    if (repartidor) url += `&repartidor=${repartidor}`;
    if (fd) url += `&fecha_desde=${fd}`;
    if (fh) url += `&fecha_hasta=${fh}`;
    window.location.href = url;
}

function quitarFiltros() {
    window.location.href = BASE_URL + '/frontend/menuprincipal.php?mod=entregas';
}

// ==================== DETALLE ====================
function verDetalleEntrega(id) {
    const modalBody = document.getElementById('detalleEntregaContenido');
    modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando...</p></div>';
    const modal = new bootstrap.Modal(document.getElementById('modalDetalleEntrega'), { backdrop: false });
    modal.show();
    
    fetch(BASE_URL + `/backend/delivery/get_detalle_entrega.php?id_entrega=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const html = `
                    <div class="row g-3">
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>CLIENTE</small><strong>${escapeHtml(data.cliente_nombre)}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>REPARTIDOR</small><strong>${escapeHtml(data.repartidor_nombre || 'No asignado')}</strong> ${data.repartidor_telefono ? `📞 ${escapeHtml(data.repartidor_telefono)}` : ''}</div></div>
                        <div class="col-12"><div class="bg-light p-3 rounded"><small>DIRECCIÓN</small><p>${escapeHtml(data.direccion_entrega)}</p></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>FECHA ASIGNACIÓN</small><strong>${data.fecha_asignacion}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>FECHA ENTREGA REAL</small><strong>${data.fecha_entrega_real || '—'}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>ESTADO</small><strong>${data.estado_texto}</strong></div></div>
                        ${data.observaciones ? `<div class="col-12"><div class="bg-light p-3 rounded"><small>OBSERVACIONES</small><p>${escapeHtml(data.observaciones)}</p></div></div>` : ''}
                    </div>
                `;
                modalBody.innerHTML = html;
            } else {
                modalBody.innerHTML = '<div class="text-center py-5 text-danger">Error al cargar los datos</div>';
            }
        })
        .catch(() => modalBody.innerHTML = '<div class="text-center py-5 text-danger">Error de conexión</div>');
}

// ==================== AGREGAR / EDITAR ====================
function abrirModalAgregarEntrega() {
    editMode = false;
    document.getElementById('formEntregaTitle').innerText = 'Nueva Entrega';
    document.getElementById('formEntrega').reset();
    document.getElementById('entrega_id').value = '';
    const ahora = new Date().toISOString().slice(0,16);
    document.getElementById('entrega_fecha_asignacion').value = ahora;
    document.getElementById('entrega_estado').value = 'pendiente';
    document.getElementById('entrega_fecha_entrega_real').value = '';
    new bootstrap.Modal(document.getElementById('modalFormEntrega'), { backdrop: false }).show();
}

function editarEntrega(id) {
    editMode = true;
    document.getElementById('formEntregaTitle').innerText = 'Editar Entrega';
    fetch(BASE_URL + `/backend/delivery/get_detalle_entrega.php?id_entrega=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('entrega_id').value = id;
                document.getElementById('entrega_cliente_nombre').value = data.cliente_nombre;
                document.getElementById('entrega_repartidor').value = data.id_repartidor || '';
                document.getElementById('entrega_direccion').value = data.direccion_entrega;
                document.getElementById('entrega_fecha_asignacion').value = data.fecha_asignacion_raw?.slice(0,16) || '';
                document.getElementById('entrega_fecha_entrega_real').value = data.fecha_entrega_real_raw?.slice(0,16) || '';
                document.getElementById('entrega_estado').value = data.estado;
                document.getElementById('entrega_observaciones').value = data.observaciones || '';
                new bootstrap.Modal(document.getElementById('modalFormEntrega'), { backdrop: false }).show();
            } else {
                Swal.fire('Error', 'No se pudo cargar la entrega', 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
}

function guardarEntrega() {
    const cliente = document.getElementById('entrega_cliente_nombre').value.trim();
    const direccion = document.getElementById('entrega_direccion').value.trim();
    if (!cliente || !direccion) {
        Swal.fire('Error', 'Cliente y dirección son obligatorios', 'error');
        return;
    }
    
    const data = {
        id_entrega: document.getElementById('entrega_id').value || null,
        cliente_nombre: cliente,
        direccion_entrega: direccion,
        id_repartidor: document.getElementById('entrega_repartidor').value || null,
        fecha_asignacion: document.getElementById('entrega_fecha_asignacion').value,
        fecha_entrega_real: document.getElementById('entrega_fecha_entrega_real').value || null,
        estado: document.getElementById('entrega_estado').value,
        observaciones: document.getElementById('entrega_observaciones').value.trim()
    };
    
    const url = editMode ? BASE_URL + '/backend/delivery/actualizar_entrega.php' : BASE_URL + '/backend/delivery/agregar_entrega.php';
    
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(resp => {
        if (resp.success) {
            Swal.fire('Éxito', editMode ? 'Entrega actualizada correctamente' : 'Entrega creada correctamente', 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', resp.message || 'No se pudo guardar', 'error');
        }
    })
    .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
}

// ==================== CAMBIAR ESTADO ====================
function cambiarEstadoEntrega(id, estadoActual) {
    let opciones = [];
    if (estadoActual === 'pendiente') opciones = ['en_camino', 'cancelado'];
    else if (estadoActual === 'en_camino') opciones = ['entregado', 'cancelado'];
    else if (estadoActual === 'entregado') opciones = [];
    else if (estadoActual === 'cancelado') opciones = [];
    
    if (opciones.length === 0) {
        Swal.fire('Información', 'Este estado no permite cambios', 'info');
        return;
    }
    
    Swal.fire({
        title: 'Cambiar estado',
        text: `Selecciona el nuevo estado para la entrega`,
        input: 'select',
        inputOptions: {
            'en_camino': 'En camino',
            'entregado': 'Entregado',
            'cancelado': 'Cancelado'
        },
        inputValue: opciones[0],
        showCancelButton: true,
        inputValidator: (value) => {
            if (!value) return 'Debes seleccionar un estado';
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const nuevoEstado = result.value;
            fetch(BASE_URL + `/backend/delivery/cambiar_estado_entrega.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_entrega: id, estado: nuevoEstado })
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    Swal.fire('Éxito', 'Estado actualizado correctamente', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', resp.message || 'Error al cambiar estado', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
        }
    });
}

// ==================== EXPORTAR PDF ====================
function exportarPDF() {
    const tabla = document.getElementById('tablaEntregas');
    if (!tabla || tabla.rows.length === 0) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    const busqueda = document.getElementById('busquedaInput').value || 'Sin búsqueda';
    const estado = document.getElementById('filtroEstado').options[document.getElementById('filtroEstado').selectedIndex]?.text || 'Todos';
    const repartidor = document.getElementById('filtroRepartidor').options[document.getElementById('filtroRepartidor').selectedIndex]?.text || 'Todos';
    const fd = document.getElementById('fechaDesde').value || '';
    const fh = document.getElementById('fechaHasta').value || '';
    let periodo = '';
    if (fd && fh) periodo = ` (${fd} al ${fh})`;
    else if (fd) periodo = ` (desde ${fd})`;
    else if (fh) periodo = ` (hasta ${fh})`;
    
    let htmlContent = `
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"><title>Reporte de Entregas</title>
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
            <h1>Reporte de Entregas</h1>
            <div class="filters">
                Búsqueda: ${escapeHtml(busqueda)} | Estado: ${escapeHtml(estado)} | Repartidor: ${escapeHtml(repartidor)} | Fecha asignación: ${periodo || 'Todos'}
            </div>
            <table>
                <thead><tr><th>Cliente</th><th>Dirección</th><th>Repartidor</th><th>Fecha asignación</th><th>Fecha entrega real</th><th>Estado</th></tr></thead>
                <tbody>
    `;
    
    const tbody = document.getElementById('tablaEntregasBody');
    const rows = tbody.querySelectorAll('tr');
    rows.forEach(row => {
        if (row.querySelector('.text-muted')) return;
        const cells = row.querySelectorAll('td');
        if (cells.length >= 7) {
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
        filename: `entregas_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.pdf`,
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