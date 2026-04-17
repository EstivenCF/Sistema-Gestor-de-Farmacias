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
$filtro_barrio = $_GET['barrio'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Obtener lista de barrios únicos
$barrios = [];
try {
    $stmtBarrios = $conexion->query("SELECT DISTINCT barrio FROM clientes WHERE barrio IS NOT NULL AND barrio != '' ORDER BY barrio");
    $barrios = $stmtBarrios->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {}

// Consulta principal
$query = "
    SELECT 
        c.id_cliente,
        c.nombre,
        c.direccion,
        c.barrio,
        c.ciudad,
        c.fecha_registro,
        c.permite_credito,
        c.saldo_pendiente,
        (SELECT t.numero FROM cliente_telefono ct JOIN telefonos t ON ct.id_telefono = t.id_telefono WHERE ct.id_cliente = c.id_cliente AND t.activo = TRUE LIMIT 1) AS telefono,
        (SELECT cor.email FROM cliente_correo cc JOIN correos cor ON cc.id_correo = cor.id_correo WHERE cc.id_cliente = c.id_cliente AND cor.activo = TRUE LIMIT 1) AS email
    FROM clientes c
    WHERE 1=1
";
$params = [];
if ($busqueda) {
    $query .= " AND c.nombre ILIKE :busqueda";
    $params[':busqueda'] = "%$busqueda%";
}
if ($filtro_barrio) {
    $query .= " AND c.barrio = :barrio";
    $params[':barrio'] = $filtro_barrio;
}
if ($fecha_desde && $fecha_hasta) {
    $query .= " AND c.fecha_registro BETWEEN :fecha_desde AND :fecha_hasta";
    $params[':fecha_desde'] = $fecha_desde;
    $params[':fecha_hasta'] = $fecha_hasta;
} elseif ($fecha_desde) {
    $query .= " AND c.fecha_registro >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
} elseif ($fecha_hasta) {
    $query .= " AND c.fecha_registro <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}
$query .= " ORDER BY c.fecha_registro DESC";

$clientes = [];
$total_clientes = 0;
try {
    $stmt = $conexion->prepare($query);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_clientes = count($clientes);
} catch(PDOException $e) {
    $clientes = [];
}

$base_url = '/sistema-gestor-de-farmacias';
?>

<style>
    /* ... tus estilos ... (mantén los que ya tenías) */
    .hv-filtros-bar { background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
    .card-total { background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); color: white; border-radius: 12px; padding: 15px; margin-bottom: 20px; }
    .card-total h3 { font-size: 1.8rem; margin: 0; font-weight: 700; }
    .btn-quitar-filtros { background-color: #f1f3f5; color: #495057; border: 1.5px solid #dee2e6; border-radius: 10px; padding: 8px 20px; }
    .dashboard-container { padding: 20px; animation: fadeSlideIn 0.5s ease-out; }
    @keyframes fadeSlideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .table-hover tbody tr:hover { background-color: rgba(40,167,69,0.05); cursor: pointer; }
    .modal-content { border-radius: 20px; overflow: hidden; }
    .btn-export-pdf { background-color: #dc3545; color: white; border: none; padding: 8px 20px; border-radius: 10px; font-weight: 500; margin-left: 15px; }
    .btn-export-pdf:hover { background-color: #c82333; transform: translateY(-1px); }
    .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .modal-backdrop { display: none !important; }
    body.modal-open { overflow: auto !important; padding-right: 0 !important; }
    .direccion-item { background: #f8f9fa; padding: 8px 12px; border-radius: 8px; margin-bottom: 8px; border-left: 3px solid #28a745; }
    .predeterminada-badge { background-color: #28a745; color: white; font-size: 0.7rem; padding: 2px 8px; border-radius: 20px; margin-left: 8px; }
</style>

<div class="dashboard-container">
    <div class="header-actions">
        <div>
            <h2 class="mb-0 text-success">
                <span class="material-symbols-rounded align-middle me-2">group</span>
                Gestión de Clientes
            </h2>
            <p class="text-muted mb-0">Consulta y administra los clientes registrados</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" onclick="abrirModalAgregarCliente()">
                <span class="material-symbols-rounded align-middle me-1">person_add</span>
                Agregar Cliente
            </button>
            <button type="button" class="btn btn-export-pdf" onclick="exportarPDF()">
                <span class="material-symbols-rounded align-middle me-1">picture_as_pdf</span>
                Exportar a PDF
            </button>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card-total text-center">
                <small>TOTAL CLIENTES</small>
                <h3><?php echo $total_clientes; ?></h3>
            </div>
        </div>
    </div>

    <div class="hv-filtros-bar">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted">NOMBRE</label>
                <input type="text" class="form-control" id="busquedaInput" placeholder="Buscar por nombre" value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted">BARRIO</label>
                <select class="form-select" id="filtroBarrio">
                    <option value="">Todos</option>
                    <?php foreach ($barrios as $barrio): ?>
                        <option value="<?php echo htmlspecialchars($barrio); ?>" <?php echo $filtro_barrio == $barrio ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($barrio); ?>
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
            <div class="col-md-2 text-end">
                <button type="button" class="btn btn-quitar-filtros" onclick="quitarFiltros()">Quitar filtros</button>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaClientes">
                    <thead class="table-light">
                        <tr><th>Nombre completo</th><th>Teléfono</th><th>Email</th><th>Dirección</th><th>Barrio</th><th>Fecha registro</th><th class="text-center">Acciones</th></tr>
                    </thead>
                    <tbody id="tablaClientesBody">
                        <?php if (empty($clientes)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-5">No hay clientes registrados</td></tr>
                        <?php else: foreach ($clientes as $cliente): 
                            $fecha_reg = date('d/m/Y', strtotime($cliente['fecha_registro']));
                        ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['telefono'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($cliente['email'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($cliente['direccion'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($cliente['barrio'] ?: '—'); ?></td>
                                <td><?php echo $fecha_reg; ?></td>
                                <td class="text-center">
                                    <button class="btn btn-outline-primary btn-sm" onclick="verDetalleCliente(<?php echo $cliente['id_cliente']; ?>)">
                                        <span class="material-symbols-rounded">visibility</span>
                                    </button>
                                    <button class="btn btn-outline-warning btn-sm" onclick="editarCliente(<?php echo $cliente['id_cliente']; ?>)">
                                        <span class="material-symbols-rounded">edit</span>
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

<!-- MODALES (igual que antes, los dejo igual) -->
<!-- Modal Detalle Cliente -->
<div class="modal fade" id="modalDetalleCliente" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Detalle del Cliente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleClienteContenido">
                <div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando...</p></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Formulario Cliente (con direcciones) -->
<div class="modal fade" id="modalFormCliente" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="formClienteTitle">Nuevo Cliente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formCliente">
                    <input type="hidden" id="cliente_id" value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nombre completo *</label>
                            <input type="text" class="form-control" id="cliente_nombre" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Dirección principal</label>
                            <input type="text" class="form-control" id="cliente_direccion">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Barrio</label>
                            <input list="barriosList" class="form-control" id="cliente_barrio">
                            <datalist id="barriosList"><?php foreach ($barrios as $b) echo "<option value=\"".htmlspecialchars($b)."\">"; ?></datalist>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Ciudad</label>
                            <input type="text" class="form-control" id="cliente_ciudad" value="Santiago">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cliente_permite_credito">
                                <label class="form-check-label fw-bold">Permite crédito</label>
                            </div>
                        </div>
                        <!-- Teléfonos -->
                        <div class="col-12">
                            <label class="form-label fw-bold">Teléfonos</label>
                            <div id="telefonosContainer">
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" placeholder="Número" name="telefonos[]">
                                    <select class="form-select w-auto" name="tipos_telefono[]">
                                        <option value="PRINCIPAL">Principal</option>
                                        <option value="TRABAJO">Trabajo</option>
                                        <option value="CASA">Casa</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-danger" onclick="removerCampoTelefono(this)">-</button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="agregarCampoTelefono()">+ Agregar teléfono</button>
                        </div>
                        <!-- Correos -->
                        <div class="col-12">
                            <label class="form-label fw-bold">Correos</label>
                            <div id="correosContainer">
                                <div class="input-group mb-2">
                                    <input type="email" class="form-control" placeholder="Email" name="correos[]">
                                    <select class="form-select w-auto" name="tipos_correo[]">
                                        <option value="PRINCIPAL">Principal</option>
                                        <option value="TRABAJO">Trabajo</option>
                                        <option value="PERSONAL">Personal</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-danger" onclick="removerCampoCorreo(this)">-</button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="agregarCampoCorreo()">+ Agregar correo</button>
                        </div>
                        <!-- Direcciones -->
                        <div class="col-12">
                            <label class="form-label fw-bold">Direcciones</label>
                            <div id="direccionesContainer">
                                <div class="direccion-item mb-2 p-3 border rounded">
                                    <div class="row g-2">
                                        <div class="col-md-8"><input type="text" class="form-control" placeholder="Dirección" name="direcciones[]"></div>
                                        <div class="col-md-4"><input type="text" class="form-control" placeholder="Barrio" name="direcciones_barrio[]"></div>
                                        <div class="col-md-4"><input type="text" class="form-control" placeholder="Ciudad" name="direcciones_ciudad[]" value="Santiago"></div>
                                        <div class="col-md-6"><input type="text" class="form-control" placeholder="Referencia (opcional)" name="direcciones_referencia[]"></div>
                                        <div class="col-md-2 d-flex align-items-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="direcciones_predeterminada[]">
                                                <label class="form-check-label small">Predeterminada</label>
                                            </div>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removerCampoDireccion(this)">-</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="agregarCampoDireccion()">+ Agregar dirección</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="guardarCliente()">Guardar Cliente</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';
let editMode = false;

// Filtros
document.addEventListener('DOMContentLoaded', function() {
    const filtros = ['busquedaInput', 'filtroBarrio', 'fechaDesde', 'fechaHasta'];
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
    let url = BASE_URL + '/frontend/menuprincipal.php?mod=clientes';
    const busqueda = document.getElementById('busquedaInput').value;
    const barrio = document.getElementById('filtroBarrio').value;
    const fd = document.getElementById('fechaDesde').value;
    const fh = document.getElementById('fechaHasta').value;
    if (busqueda) url += `&busqueda=${encodeURIComponent(busqueda)}`;
    if (barrio) url += `&barrio=${encodeURIComponent(barrio)}`;
    if (fd) url += `&fecha_desde=${fd}`;
    if (fh) url += `&fecha_hasta=${fh}`;
    window.location.href = url;
}

function quitarFiltros() {
    window.location.href = BASE_URL + '/frontend/menuprincipal.php?mod=clientes';
}

// Ver detalle cliente
function verDetalleCliente(id) {
    console.log("Ver detalle cliente ID:", id);
    const modalBody = document.getElementById('detalleClienteContenido');
    modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando...</p></div>';
    const modal = new bootstrap.Modal(document.getElementById('modalDetalleCliente'), { backdrop: false });
    modal.show();
    
    fetch(BASE_URL + `/backend/clientes/get_detalle_cliente.php?id_cliente=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const c = data.cliente;
                let telefonosHtml = '', correosHtml = '', direccionesHtml = '';
                if (c.telefonos && c.telefonos.length) {
                    c.telefonos.forEach(t => telefonosHtml += `<div>${escapeHtml(t.numero)} ${t.tipo ? '('+t.tipo+')' : ''} ${t.whatsapp ? '📱 WhatsApp' : ''}</div>`);
                } else telefonosHtml = '<div>—</div>';
                if (c.correos && c.correos.length) {
                    c.correos.forEach(cor => correosHtml += `<div>${escapeHtml(cor.email)} ${cor.tipo ? '('+cor.tipo+')' : ''}</div>`);
                } else correosHtml = '<div>—</div>';
                if (c.direcciones && c.direcciones.length) {
                    c.direcciones.forEach(dir => {
                        direccionesHtml += `<div class="direccion-item"><strong>${escapeHtml(dir.direccion)}</strong>${dir.predeterminada ? ' <span class="predeterminada-badge">Predeterminada</span>' : ''}<br><small>${escapeHtml(dir.barrio || '')}, ${escapeHtml(dir.ciudad || '')}</small>${dir.referencia ? `<br><small class="text-muted">Ref: ${escapeHtml(dir.referencia)}</small>` : ''}</div>`;
                    });
                } else direccionesHtml = '<div>—</div>';
                const html = `
                    <div class="row g-3">
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>NOMBRE</small><strong>${escapeHtml(c.nombre)}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>FECHA REGISTRO</small><strong>${c.fecha_registro}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>DIRECCIÓN PRINCIPAL</small><p>${escapeHtml(c.direccion) || 'No registrada'}</p></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>BARRIO / CIUDAD</small><strong>${escapeHtml(c.barrio) || '—'} / ${escapeHtml(c.ciudad) || '—'}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>¿PERMITE CRÉDITO?</small><strong>${c.permite_credito ? 'Sí' : 'No'}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>SALDO PENDIENTE</small><strong>RD$ ${parseFloat(c.saldo_pendiente || 0).toLocaleString()}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>TELÉFONOS</small>${telefonosHtml}</div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>CORREOS</small>${correosHtml}</div></div>
                        <div class="col-12"><div class="bg-light p-3 rounded"><small>DIRECCIONES</small><div>${direccionesHtml}</div></div></div>
                    </div>
                `;
                modalBody.innerHTML = html;
            } else {
                modalBody.innerHTML = '<div class="text-center py-5 text-danger">Error al cargar los datos del cliente</div>';
            }
        })
        .catch(() => modalBody.innerHTML = '<div class="text-center py-5 text-danger">Error de conexión</div>');
}

// Campos dinámicos
function agregarCampoTelefono() {
    const container = document.getElementById('telefonosContainer');
    const div = document.createElement('div');
    div.className = 'input-group mb-2';
    div.innerHTML = `
        <input type="text" class="form-control" placeholder="Número" name="telefonos[]">
        <select class="form-select w-auto" name="tipos_telefono[]"><option value="PRINCIPAL">Principal</option><option value="TRABAJO">Trabajo</option><option value="CASA">Casa</option></select>
        <button type="button" class="btn btn-outline-danger" onclick="removerCampoTelefono(this)">-</button>
    `;
    container.appendChild(div);
}
function removerCampoTelefono(btn) { if (document.querySelectorAll('#telefonosContainer .input-group').length > 1) btn.closest('.input-group').remove(); else Swal.fire('Aviso', 'Debe haber al menos un teléfono', 'info'); }

function agregarCampoCorreo() {
    const container = document.getElementById('correosContainer');
    const div = document.createElement('div');
    div.className = 'input-group mb-2';
    div.innerHTML = `
        <input type="email" class="form-control" placeholder="Email" name="correos[]">
        <select class="form-select w-auto" name="tipos_correo[]"><option value="PRINCIPAL">Principal</option><option value="TRABAJO">Trabajo</option><option value="PERSONAL">Personal</option></select>
        <button type="button" class="btn btn-outline-danger" onclick="removerCampoCorreo(this)">-</button>
    `;
    container.appendChild(div);
}
function removerCampoCorreo(btn) { if (document.querySelectorAll('#correosContainer .input-group').length > 1) btn.closest('.input-group').remove(); else Swal.fire('Aviso', 'Debe haber al menos un correo', 'info'); }

function agregarCampoDireccion() {
    const container = document.getElementById('direccionesContainer');
    const div = document.createElement('div');
    div.className = 'direccion-item mb-2 p-3 border rounded';
    div.innerHTML = `
        <div class="row g-2">
            <div class="col-md-8"><input type="text" class="form-control" placeholder="Dirección" name="direcciones[]"></div>
            <div class="col-md-4"><input type="text" class="form-control" placeholder="Barrio" name="direcciones_barrio[]"></div>
            <div class="col-md-4"><input type="text" class="form-control" placeholder="Ciudad" name="direcciones_ciudad[]" value="Santiago"></div>
            <div class="col-md-6"><input type="text" class="form-control" placeholder="Referencia (opcional)" name="direcciones_referencia[]"></div>
            <div class="col-md-2 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="direcciones_predeterminada[]"><label class="form-check-label small">Predeterminada</label></div></div>
            <div class="col-md-2 text-end"><button type="button" class="btn btn-outline-danger btn-sm" onclick="removerCampoDireccion(this)">-</button></div>
        </div>
    `;
    container.appendChild(div);
}
function removerCampoDireccion(btn) { if (document.querySelectorAll('#direccionesContainer .direccion-item').length > 1) btn.closest('.direccion-item').remove(); else Swal.fire('Aviso', 'Debe haber al menos una dirección', 'info'); }

// Abrir modal agregar cliente
function abrirModalAgregarCliente() {
    editMode = false;
    document.getElementById('formClienteTitle').innerText = 'Nuevo Cliente';
    document.getElementById('formCliente').reset();
    document.getElementById('cliente_id').value = '';
    document.getElementById('cliente_ciudad').value = 'Santiago';
    document.getElementById('telefonosContainer').innerHTML = ''; agregarCampoTelefono();
    document.getElementById('correosContainer').innerHTML = ''; agregarCampoCorreo();
    document.getElementById('direccionesContainer').innerHTML = ''; agregarCampoDireccion();
    new bootstrap.Modal(document.getElementById('modalFormCliente'), { backdrop: false }).show();
}

// Editar cliente
function editarCliente(id) {
    editMode = true;
    document.getElementById('formClienteTitle').innerText = 'Editar Cliente';
    fetch(BASE_URL + `/backend/clientes/get_detalle_cliente.php?id_cliente=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const c = data.cliente;
                document.getElementById('cliente_id').value = id;
                document.getElementById('cliente_nombre').value = c.nombre;
                document.getElementById('cliente_direccion').value = c.direccion || '';
                document.getElementById('cliente_barrio').value = c.barrio || '';
                document.getElementById('cliente_ciudad').value = c.ciudad || 'Santiago';
                document.getElementById('cliente_permite_credito').checked = c.permite_credito;
                // Teléfonos
                const telContainer = document.getElementById('telefonosContainer');
                telContainer.innerHTML = '';
                if (c.telefonos && c.telefonos.length) {
                    c.telefonos.forEach(t => {
                        const div = document.createElement('div');
                        div.className = 'input-group mb-2';
                        div.innerHTML = `
                            <input type="text" class="form-control" name="telefonos[]" value="${escapeHtml(t.numero)}">
                            <select class="form-select w-auto" name="tipos_telefono[]"><option value="PRINCIPAL" ${t.tipo === 'PRINCIPAL' ? 'selected' : ''}>Principal</option><option value="TRABAJO" ${t.tipo === 'TRABAJO' ? 'selected' : ''}>Trabajo</option><option value="CASA" ${t.tipo === 'CASA' ? 'selected' : ''}>Casa</option></select>
                            <button type="button" class="btn btn-outline-danger" onclick="removerCampoTelefono(this)">-</button>
                        `;
                        telContainer.appendChild(div);
                    });
                } else agregarCampoTelefono();
                // Correos
                const corContainer = document.getElementById('correosContainer');
                corContainer.innerHTML = '';
                if (c.correos && c.correos.length) {
                    c.correos.forEach(cor => {
                        const div = document.createElement('div');
                        div.className = 'input-group mb-2';
                        div.innerHTML = `
                            <input type="email" class="form-control" name="correos[]" value="${escapeHtml(cor.email)}">
                            <select class="form-select w-auto" name="tipos_correo[]"><option value="PRINCIPAL" ${cor.tipo === 'PRINCIPAL' ? 'selected' : ''}>Principal</option><option value="TRABAJO" ${cor.tipo === 'TRABAJO' ? 'selected' : ''}>Trabajo</option><option value="PERSONAL" ${cor.tipo === 'PERSONAL' ? 'selected' : ''}>Personal</option></select>
                            <button type="button" class="btn btn-outline-danger" onclick="removerCampoCorreo(this)">-</button>
                        `;
                        corContainer.appendChild(div);
                    });
                } else agregarCampoCorreo();
                // Direcciones
                const dirContainer = document.getElementById('direccionesContainer');
                dirContainer.innerHTML = '';
                if (c.direcciones && c.direcciones.length) {
                    c.direcciones.forEach(dir => {
                        const div = document.createElement('div');
                        div.className = 'direccion-item mb-2 p-3 border rounded';
                        div.innerHTML = `
                            <div class="row g-2">
                                <div class="col-md-8"><input type="text" class="form-control" name="direcciones[]" value="${escapeHtml(dir.direccion)}"></div>
                                <div class="col-md-4"><input type="text" class="form-control" name="direcciones_barrio[]" value="${escapeHtml(dir.barrio || '')}"></div>
                                <div class="col-md-4"><input type="text" class="form-control" name="direcciones_ciudad[]" value="${escapeHtml(dir.ciudad || 'Santiago')}"></div>
                                <div class="col-md-6"><input type="text" class="form-control" name="direcciones_referencia[]" value="${escapeHtml(dir.referencia || '')}"></div>
                                <div class="col-md-2 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="direcciones_predeterminada[]" ${dir.predeterminada ? 'checked' : ''}><label class="form-check-label small">Predeterminada</label></div></div>
                                <div class="col-md-2 text-end"><button type="button" class="btn btn-outline-danger btn-sm" onclick="removerCampoDireccion(this)">-</button></div>
                            </div>
                        `;
                        dirContainer.appendChild(div);
                    });
                } else agregarCampoDireccion();
                new bootstrap.Modal(document.getElementById('modalFormCliente'), { backdrop: false }).show();
            } else Swal.fire('Error', 'No se pudo cargar el cliente', 'error');
        })
        .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
}

function guardarCliente() {
    const nombre = document.getElementById('cliente_nombre').value.trim();
    if (!nombre) return Swal.fire('Error', 'El nombre es obligatorio', 'error');
    // Recolectar teléfonos
    const telefonos = [];
    document.querySelectorAll('#telefonosContainer input[name="telefonos[]"]').forEach((inp, i) => {
        let num = inp.value.trim();
        if (num) telefonos.push({ numero: num, tipo: document.querySelectorAll('#telefonosContainer select[name="tipos_telefono[]"]')[i].value, whatsapp: false });
    });
    if (telefonos.length === 0) return Swal.fire('Error', 'Debe ingresar al menos un teléfono', 'error');
    // Correos
    const correos = [];
    document.querySelectorAll('#correosContainer input[name="correos[]"]').forEach((inp, i) => {
        let email = inp.value.trim();
        if (email) {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return Swal.fire('Error', `Correo inválido: ${email}`, 'warning');
            correos.push({ email: email, tipo: document.querySelectorAll('#correosContainer select[name="tipos_correo[]"]')[i].value });
        }
    });
    // Direcciones
    const direcciones = [];
    const dirInputs = document.querySelectorAll('#direccionesContainer input[name="direcciones[]"]');
    const barrioInputs = document.querySelectorAll('#direccionesContainer input[name="direcciones_barrio[]"]');
    const ciudadInputs = document.querySelectorAll('#direccionesContainer input[name="direcciones_ciudad[]"]');
    const refInputs = document.querySelectorAll('#direccionesContainer input[name="direcciones_referencia[]"]');
    const predCheck = document.querySelectorAll('#direccionesContainer input[name="direcciones_predeterminada[]"]');
    for (let i = 0; i < dirInputs.length; i++) {
        let dir = dirInputs[i].value.trim();
        if (dir) direcciones.push({
            direccion: dir,
            barrio: barrioInputs[i]?.value.trim() || '',
            ciudad: ciudadInputs[i]?.value.trim() || 'Santiago',
            referencia: refInputs[i]?.value.trim() || '',
            predeterminada: predCheck[i]?.checked || false
        });
    }
    const data = {
        id_cliente: document.getElementById('cliente_id').value || null,
        nombre: nombre,
        direccion: document.getElementById('cliente_direccion').value.trim(),
        barrio: document.getElementById('cliente_barrio').value.trim(),
        ciudad: document.getElementById('cliente_ciudad').value.trim() || 'Santiago',
        permite_credito: document.getElementById('cliente_permite_credito').checked,
        telefonos: telefonos,
        correos: correos,
        direcciones: direcciones
    };
    const url = editMode ? BASE_URL + '/backend/clientes/actualizar_cliente.php' : BASE_URL + '/backend/clientes/agregar_cliente.php';
    Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
        .then(res => res.json())
        .then(resp => { Swal.close(); if (resp.success) Swal.fire('Éxito', editMode ? 'Cliente actualizado' : 'Cliente agregado', 'success').then(() => location.reload()); else Swal.fire('Error', resp.message, 'error'); })
        .catch(() => { Swal.close(); Swal.fire('Error', 'Error de conexión', 'error'); });
}

function exportarPDF() { /* ... mismo código que antes ... */ }
function escapeHtml(str) { if (!str) return ''; return String(str).replace(/[&<>"']/g, m => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[m])); }
</script>