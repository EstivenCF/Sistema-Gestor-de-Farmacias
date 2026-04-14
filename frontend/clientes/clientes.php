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

// Obtener lista de barrios únicos para el filtro
$barrios = [];
try {
    $stmtBarrios = $conexion->query("SELECT DISTINCT barrio FROM clientes WHERE barrio IS NOT NULL AND barrio != '' ORDER BY barrio");
    $barrios = $stmtBarrios->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {}

// Consulta principal de clientes con teléfono y email principal
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
        (
            SELECT t.numero 
            FROM cliente_telefono ct 
            JOIN telefonos t ON ct.id_telefono = t.id_telefono 
            WHERE ct.id_cliente = c.id_cliente AND t.activo = TRUE 
            LIMIT 1
        ) AS telefono,
        (
            SELECT cor.email 
            FROM cliente_correo cc 
            JOIN correos cor ON cc.id_correo = cor.id_correo 
            WHERE cc.id_cliente = c.id_cliente AND cor.activo = TRUE 
            LIMIT 1
        ) AS email
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
    .hv-filtros-bar {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
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

    <!-- Tarjeta de totales -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card-total text-center">
                <small>TOTAL CLIENTES</small>
                <h3><?php echo $total_clientes; ?></h3>
            </div>
        </div>
    </div>

    <!-- Filtros -->
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

    <!-- Tabla de clientes -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaClientes">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre completo</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Dirección</th>
                            <th>Barrio</th>
                            <th>Fecha registro</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaClientesBody">
                        <?php if (empty($clientes)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="fas fa-users d-block mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                                    No hay clientes registrados con los filtros seleccionados
                                </td>
                            </tr>
                        <?php else: foreach ($clientes as $cliente): 
                            $fecha_reg = date('d/m/Y', strtotime($cliente['fecha_registro']));
                            $direccion = htmlspecialchars($cliente['direccion'] ?: '—');
                            $barrio = htmlspecialchars($cliente['barrio'] ?: '—');
                            $telefono = htmlspecialchars($cliente['telefono'] ?: '—');
                            $email = htmlspecialchars($cliente['email'] ?: '—');
                        ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                                <td><?php echo $telefono; ?></td>
                                <td><?php echo $email; ?></td>
                                <td><?php echo $direccion; ?></td>
                                <td><?php echo $barrio; ?></td>
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

<!-- MODAL DETALLE CLIENTE -->
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

<!-- MODAL AGREGAR/EDITAR CLIENTE -->
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
                            <label class="form-label fw-bold">Dirección</label>
                            <input type="text" class="form-control" id="cliente_direccion">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Barrio</label>
                            <input list="barriosList" class="form-control" id="cliente_barrio">
                            <datalist id="barriosList">
                                <?php foreach ($barrios as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b); ?>">
                                <?php endforeach; ?>
                            </datalist>
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
                        <div class="col-12">
                            <label class="form-label fw-bold">Teléfonos</label>
                            <div id="telefonosContainer">
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" placeholder="Número de teléfono" name="telefonos[]">
                                    <select class="form-select w-auto" name="tipos_telefono[]">
                                        <option value="PRINCIPAL">Principal</option>
                                        <option value="TRABAJO">Trabajo</option>
                                        <option value="CASA">Casa</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-danger" onclick="removerCampoTelefono(this)">-</button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success mt-1" onclick="agregarCampoTelefono()">+ Agregar teléfono</button>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Correos electrónicos</label>
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
                            <button type="button" class="btn btn-sm btn-outline-success mt-1" onclick="agregarCampoCorreo()">+ Agregar correo</button>
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

// Detalle cliente
function verDetalleCliente(id) {
    const modalBody = document.getElementById('detalleClienteContenido');
    modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando...</p></div>';
    const modal = new bootstrap.Modal(document.getElementById('modalDetalleCliente'), { backdrop: false });
    modal.show();
    
    fetch(BASE_URL + `/backend/clientes/get_detalle_cliente.php?id_cliente=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                let telefonosHtml = '';
                if (data.telefonos && data.telefonos.length) {
                    data.telefonos.forEach(t => {
                        telefonosHtml += `<div>${escapeHtml(t.numero)} ${t.tipo ? '('+t.tipo+')' : ''} ${t.whatsapp ? '📱 WhatsApp' : ''}</div>`;
                    });
                } else {
                    telefonosHtml = '<div>—</div>';
                }
                let correosHtml = '';
                if (data.correos && data.correos.length) {
                    data.correos.forEach(c => {
                        correosHtml += `<div>${escapeHtml(c.email)} ${c.tipo ? '('+c.tipo+')' : ''}</div>`;
                    });
                } else {
                    correosHtml = '<div>—</div>';
                }
                const html = `
                    <div class="row g-3">
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>NOMBRE</small><strong>${escapeHtml(data.nombre)}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>FECHA REGISTRO</small><strong>${data.fecha_registro}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>DIRECCIÓN</small><p>${escapeHtml(data.direccion) || 'No registrada'}</p></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>BARRIO / CIUDAD</small><strong>${escapeHtml(data.barrio) || '—'} / ${escapeHtml(data.ciudad) || '—'}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>¿PERMITE CRÉDITO?</small><strong>${data.permite_credito ? 'Sí' : 'No'}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>SALDO PENDIENTE</small><strong>RD$ ${parseFloat(data.saldo_pendiente || 0).toLocaleString()}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>TELÉFONOS</small>${telefonosHtml}</div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>CORREOS</small>${correosHtml}</div></div>
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
        <input type="text" class="form-control" placeholder="Número de teléfono" name="telefonos[]">
        <select class="form-select w-auto" name="tipos_telefono[]">
            <option value="PRINCIPAL">Principal</option>
            <option value="TRABAJO">Trabajo</option>
            <option value="CASA">Casa</option>
        </select>
        <button type="button" class="btn btn-outline-danger" onclick="removerCampoTelefono(this)">-</button>
    `;
    container.appendChild(div);
}

function removerCampoTelefono(btn) {
    if (document.querySelectorAll('#telefonosContainer .input-group').length > 1) {
        btn.closest('.input-group').remove();
    } else {
        Swal.fire('Aviso', 'Debe haber al menos un teléfono', 'info');
    }
}

function agregarCampoCorreo() {
    const container = document.getElementById('correosContainer');
    const div = document.createElement('div');
    div.className = 'input-group mb-2';
    div.innerHTML = `
        <input type="email" class="form-control" placeholder="Email" name="correos[]">
        <select class="form-select w-auto" name="tipos_correo[]">
            <option value="PRINCIPAL">Principal</option>
            <option value="TRABAJO">Trabajo</option>
            <option value="PERSONAL">Personal</option>
        </select>
        <button type="button" class="btn btn-outline-danger" onclick="removerCampoCorreo(this)">-</button>
    `;
    container.appendChild(div);
}

function removerCampoCorreo(btn) {
    if (document.querySelectorAll('#correosContainer .input-group').length > 1) {
        btn.closest('.input-group').remove();
    } else {
        Swal.fire('Aviso', 'Debe haber al menos un correo', 'info');
    }
}

// Agregar / Editar cliente
function abrirModalAgregarCliente() {
    editMode = false;
    document.getElementById('formClienteTitle').innerText = 'Nuevo Cliente';
    document.getElementById('formCliente').reset();
    document.getElementById('cliente_id').value = '';
    document.getElementById('cliente_ciudad').value = 'Santiago';
    const telContainer = document.getElementById('telefonosContainer');
    telContainer.innerHTML = '';
    agregarCampoTelefono();
    const corContainer = document.getElementById('correosContainer');
    corContainer.innerHTML = '';
    agregarCampoCorreo();
    new bootstrap.Modal(document.getElementById('modalFormCliente'), { backdrop: false }).show();
}

function editarCliente(id) {
    editMode = true;
    document.getElementById('formClienteTitle').innerText = 'Editar Cliente';
    fetch(BASE_URL + `/backend/clientes/get_detalle_cliente.php?id_cliente=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('cliente_id').value = id;
                document.getElementById('cliente_nombre').value = data.nombre;
                document.getElementById('cliente_direccion').value = data.direccion || '';
                document.getElementById('cliente_barrio').value = data.barrio || '';
                document.getElementById('cliente_ciudad').value = data.ciudad || 'Santiago';
                document.getElementById('cliente_permite_credito').checked = data.permite_credito;
                // Teléfonos
                const telContainer = document.getElementById('telefonosContainer');
                telContainer.innerHTML = '';
                if (data.telefonos && data.telefonos.length) {
                    data.telefonos.forEach(t => {
                        const div = document.createElement('div');
                        div.className = 'input-group mb-2';
                        div.innerHTML = `
                            <input type="text" class="form-control" name="telefonos[]" value="${escapeHtml(t.numero)}">
                            <select class="form-select w-auto" name="tipos_telefono[]">
                                <option value="PRINCIPAL" ${t.tipo === 'PRINCIPAL' ? 'selected' : ''}>Principal</option>
                                <option value="TRABAJO" ${t.tipo === 'TRABAJO' ? 'selected' : ''}>Trabajo</option>
                                <option value="CASA" ${t.tipo === 'CASA' ? 'selected' : ''}>Casa</option>
                            </select>
                            <button type="button" class="btn btn-outline-danger" onclick="removerCampoTelefono(this)">-</button>
                        `;
                        telContainer.appendChild(div);
                    });
                } else {
                    agregarCampoTelefono();
                }
                // Correos
                const corContainer = document.getElementById('correosContainer');
                corContainer.innerHTML = '';
                if (data.correos && data.correos.length) {
                    data.correos.forEach(c => {
                        const div = document.createElement('div');
                        div.className = 'input-group mb-2';
                        div.innerHTML = `
                            <input type="email" class="form-control" name="correos[]" value="${escapeHtml(c.email)}">
                            <select class="form-select w-auto" name="tipos_correo[]">
                                <option value="PRINCIPAL" ${c.tipo === 'PRINCIPAL' ? 'selected' : ''}>Principal</option>
                                <option value="TRABAJO" ${c.tipo === 'TRABAJO' ? 'selected' : ''}>Trabajo</option>
                                <option value="PERSONAL" ${c.tipo === 'PERSONAL' ? 'selected' : ''}>Personal</option>
                            </select>
                            <button type="button" class="btn btn-outline-danger" onclick="removerCampoCorreo(this)">-</button>
                        `;
                        corContainer.appendChild(div);
                    });
                } else {
                    agregarCampoCorreo();
                }
                new bootstrap.Modal(document.getElementById('modalFormCliente'), { backdrop: false }).show();
            } else {
                Swal.fire('Error', 'No se pudo cargar el cliente', 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
}

function guardarCliente() {
    const nombre = document.getElementById('cliente_nombre').value.trim();
    if (!nombre) {
        Swal.fire('Error', 'El nombre es obligatorio', 'error');
        return;
    }
    
    // Teléfonos
    const telefonos = [];
    const inputsTel = document.querySelectorAll('#telefonosContainer input[name="telefonos[]"]');
    const selectsTel = document.querySelectorAll('#telefonosContainer select[name="tipos_telefono[]"]');
    for (let i = 0; i < inputsTel.length; i++) {
        let numero = inputsTel[i].value.trim();
        if (numero) {
            telefonos.push({
                numero: numero,
                tipo: selectsTel[i].value,
                whatsapp: false
            });
        }
    }
    if (telefonos.length === 0) {
        Swal.fire('Error', 'Debe ingresar al menos un teléfono', 'error');
        return;
    }
    
    // Correos
    const correos = [];
    const inputsCor = document.querySelectorAll('#correosContainer input[name="correos[]"]');
    const selectsCor = document.querySelectorAll('#correosContainer select[name="tipos_correo[]"]');
    for (let i = 0; i < inputsCor.length; i++) {
        let email = inputsCor[i].value.trim();
        if (email) {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                Swal.fire('Error', `Correo inválido: ${email}`, 'warning');
                return;
            }
            correos.push({
                email: email,
                tipo: selectsCor[i].value
            });
        }
    }
    
    const data = {
        id_cliente: document.getElementById('cliente_id').value || null,
        nombre: nombre,
        direccion: document.getElementById('cliente_direccion').value.trim(),
        barrio: document.getElementById('cliente_barrio').value.trim(),
        ciudad: document.getElementById('cliente_ciudad').value.trim() || 'Santiago',
        permite_credito: document.getElementById('cliente_permite_credito').checked,
        telefonos: telefonos,
        correos: correos
    };
    
    const url = editMode ? BASE_URL + '/backend/clientes/actualizar_cliente.php' : BASE_URL + '/backend/clientes/agregar_cliente.php';
    
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(resp => {
        if (resp.success) {
            Swal.fire('Éxito', editMode ? 'Cliente actualizado correctamente' : 'Cliente agregado correctamente', 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', resp.message || 'No se pudo guardar', 'error');
        }
    })
    .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
}

// Exportar PDF
function exportarPDF() {
    const tabla = document.getElementById('tablaClientes');
    if (!tabla || tabla.rows.length === 0) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    const busqueda = document.getElementById('busquedaInput').value || 'Sin búsqueda';
    const barrio = document.getElementById('filtroBarrio').options[document.getElementById('filtroBarrio').selectedIndex]?.text || 'Todos';
    const fd = document.getElementById('fechaDesde').value || '';
    const fh = document.getElementById('fechaHasta').value || '';
    let periodo = '';
    if (fd && fh) periodo = ` (${fd} al ${fh})`;
    else if (fd) periodo = ` (desde ${fd})`;
    else if (fh) periodo = ` (hasta ${fh})`;
    
    let htmlContent = `
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"><title>Reporte de Clientes</title>
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
            <h1>Reporte de Clientes</h1>
            <div class="filters">Nombre: ${escapeHtml(busqueda)} | Barrio: ${escapeHtml(barrio)} | Fecha registro: ${periodo || 'Todos'}</div>
            <table>
                <thead><tr><th>Nombre</th><th>Teléfono</th><th>Email</th><th>Dirección</th><th>Barrio</th><th>Fecha registro</th></tr></thead>
                <tbody>
    `;
    
    const tbody = document.getElementById('tablaClientesBody');
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
        filename: `clientes_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.pdf`,
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