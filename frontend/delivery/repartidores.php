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
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Consulta principal de repartidores
$query = "
    SELECT 
        r.id_repartidor,
        r.nombre,
        r.tipo_identificacion,
        r.numero_identificacion,
        r.licencia_conducir,
        r.fecha_ingreso,
        r.activo,
        (
            SELECT t.numero 
            FROM repartidor_telefono rt 
            JOIN telefonos t ON rt.id_telefono = t.id_telefono 
            WHERE rt.id_repartidor = r.id_repartidor AND t.activo = TRUE 
            LIMIT 1
        ) AS telefono_principal,
        (
            SELECT cor.email 
            FROM repartidor_correo rc 
            JOIN correos cor ON rc.id_correo = cor.id_correo 
            WHERE rc.id_repartidor = r.id_repartidor AND cor.activo = TRUE 
            LIMIT 1
        ) AS email_principal
    FROM repartidores r
    WHERE 1=1
";

$params = [];
if ($busqueda) {
    $query .= " AND (r.nombre ILIKE :busqueda OR r.numero_identificacion ILIKE :busqueda OR r.licencia_conducir ILIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}
if ($filtro_estado === 'activo') {
    $query .= " AND r.activo = TRUE";
} elseif ($filtro_estado === 'inactivo') {
    $query .= " AND r.activo = FALSE";
}
if ($fecha_desde && $fecha_hasta) {
    $query .= " AND r.fecha_ingreso BETWEEN :fecha_desde AND :fecha_hasta";
    $params[':fecha_desde'] = $fecha_desde;
    $params[':fecha_hasta'] = $fecha_hasta;
} elseif ($fecha_desde) {
    $query .= " AND r.fecha_ingreso >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
} elseif ($fecha_hasta) {
    $query .= " AND r.fecha_ingreso <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

$query .= " ORDER BY r.fecha_ingreso DESC";

$repartidores = [];
$total_repartidores = 0;
$total_activos = 0;
$total_inactivos = 0;

try {
    $stmt = $conexion->prepare($query);
    $stmt->execute($params);
    $repartidores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_repartidores = count($repartidores);
    foreach ($repartidores as $r) {
        if ($r['activo'] == 't' || $r['activo'] === true || $r['activo'] === 1) {
            $total_activos++;
        } else {
            $total_inactivos++;
        }
    }
} catch(PDOException $e) {
    $repartidores = [];
}

$tipos_identificacion = ['CEDULA', 'PASAPORTE', 'RNC', 'OTRO'];
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
    .badge-activo { background-color: #28a745; color: #fff; }
    .badge-inactivo { background-color: #6c757d; color: #fff; }
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
                <span class="material-symbols-rounded align-middle me-2">motorcycle</span>
                Gestión de Repartidores
            </h2>
            <p class="text-muted mb-0">Administra los repartidores y su información de contacto</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" onclick="abrirModalAgregarRepartidor()">
                <span class="material-symbols-rounded align-middle me-1">person_add</span>
                Agregar Repartidor
            </button>
            <button type="button" class="btn btn-export-pdf" onclick="exportarPDF()">
                <span class="material-symbols-rounded align-middle me-1">picture_as_pdf</span>
                Exportar a PDF
            </button>
        </div>
    </div>

    <!-- Tarjetas de totales -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card-total text-center">
                <small>TOTAL REPARTIDORES</small>
                <h3><?php echo $total_repartidores; ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-total text-center">
                <small>ACTIVOS</small>
                <h3><?php echo $total_activos; ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-total text-center">
                <small>INACTIVOS</small>
                <h3><?php echo $total_inactivos; ?></h3>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="hv-filtros-bar">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted">BUSCAR</label>
                <input type="text" class="form-control" id="busquedaInput" placeholder="Nombre, identificación, licencia..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">ESTADO</label>
                <select class="form-select" id="filtroEstado">
                    <option value="">Todos</option>
                    <option value="activo" <?php echo $filtro_estado === 'activo' ? 'selected' : ''; ?>>Activos</option>
                    <option value="inactivo" <?php echo $filtro_estado === 'inactivo' ? 'selected' : ''; ?>>Inactivos</option>
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
            <div class="col-md-3 text-end">
                <button type="button" class="btn btn-quitar-filtros" onclick="quitarFiltros()">Quitar filtros</button>
            </div>
        </div>
    </div>

    <!-- Tabla de repartidores -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaRepartidores">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Identificación</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Licencia</th>
                            <th>Fecha ingreso</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaRepartidoresBody">
                        <?php if (empty($repartidores)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="fas fa-users d-block mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                                    No hay repartidores registrados con los filtros seleccionados
                                </td>
                            </tr>
                        <?php else: foreach ($repartidores as $rep): 
                            $estado_texto = ($rep['activo'] == 't' || $rep['activo'] === true || $rep['activo'] === 1) ? 'Activo' : 'Inactivo';
                            $estado_class = ($estado_texto === 'Activo') ? 'badge-activo' : 'badge-inactivo';
                            $fecha_ingreso = date('d/m/Y', strtotime($rep['fecha_ingreso']));
                            $telefono = htmlspecialchars($rep['telefono_principal'] ?: '—');
                            $email = htmlspecialchars($rep['email_principal'] ?: '—');
                            $identificacion = htmlspecialchars($rep['tipo_identificacion'] . ': ' . $rep['numero_identificacion']);
                        ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($rep['nombre']); ?></td>
                                <td><?php echo $identificacion; ?></td>
                                <td><?php echo $telefono; ?></td>
                                <td><?php echo $email; ?></td>
                                <td><?php echo htmlspecialchars($rep['licencia_conducir'] ?: '—'); ?></td>
                                <td><?php echo $fecha_ingreso; ?></td>
                                <td><span class="badge-estado <?php echo $estado_class; ?>"><?php echo $estado_texto; ?></span></td>
                                <td class="text-center">
                                    <button class="btn btn-outline-info btn-sm me-1" onclick="verDetalleRepartidor(<?php echo $rep['id_repartidor']; ?>)">
                                        <span class="material-symbols-rounded">visibility</span>
                                    </button>
                                    <button class="btn btn-outline-warning btn-sm me-1" onclick="editarRepartidor(<?php echo $rep['id_repartidor']; ?>)">
                                        <span class="material-symbols-rounded">edit</span>
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="toggleEstadoRepartidor(<?php echo $rep['id_repartidor']; ?>, <?php echo $estado_texto === 'Activo' ? 'false' : 'true'; ?>)">
                                        <span class="material-symbols-rounded"><?php echo $estado_texto === 'Activo' ? 'block' : 'check_circle'; ?></span>
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

<!-- MODAL DETALLE REPARTIDOR -->
<div class="modal fade" id="modalDetalleRepartidor" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Detalle del Repartidor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleRepartidorContenido">
                <div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando...</p></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL AGREGAR/EDITAR REPARTIDOR -->
<div class="modal fade" id="modalFormRepartidor" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="formRepartidorTitle">Nuevo Repartidor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formRepartidor">
                    <input type="hidden" id="repartidor_id" value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nombre completo *</label>
                            <input type="text" class="form-control" id="repartidor_nombre" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Tipo identificación</label>
                            <select class="form-select" id="repartidor_tipo_identificacion">
                                <option value="CEDULA">Cédula</option>
                                <option value="PASAPORTE">Pasaporte</option>
                                <option value="RNC">RNC</option>
                                <option value="OTRO">Otro</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Número identificación</label>
                            <input type="text" class="form-control" id="repartidor_numero_identificacion">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Dirección</label>
                            <input type="text" class="form-control" id="repartidor_direccion">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Fecha ingreso</label>
                            <input type="date" class="form-control" id="repartidor_fecha_ingreso" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Teléfono emergencia</label>
                            <input type="text" class="form-control" id="repartidor_telefono_emergencia">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Licencia conducir</label>
                            <input type="text" class="form-control" id="repartidor_licencia">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Vencimiento licencia</label>
                            <input type="date" class="form-control" id="repartidor_fecha_vencimiento_licencia">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Foto URL</label>
                            <input type="text" class="form-control" id="repartidor_foto_url" placeholder="https://...">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Observaciones</label>
                            <textarea class="form-control" rows="2" id="repartidor_observaciones"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Teléfonos</label>
                            <div id="telefonosContainer">
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" placeholder="Número de teléfono" name="telefonos[]">
                                    <select class="form-select w-auto" name="tipos_telefono[]">
                                        <option value="PRINCIPAL">Principal</option>
                                        <option value="TRABAJO">Trabajo</option>
                                        <option value="PERSONAL">Personal</option>
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
                <button type="button" class="btn btn-success" onclick="guardarRepartidor()">Guardar Repartidor</button>
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
    const filtros = ['busquedaInput', 'filtroEstado', 'fechaDesde', 'fechaHasta'];
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
    let url = BASE_URL + '/frontend/menuprincipal.php?mod=repartidores';
    const busqueda = document.getElementById('busquedaInput').value;
    const estado = document.getElementById('filtroEstado').value;
    const fd = document.getElementById('fechaDesde').value;
    const fh = document.getElementById('fechaHasta').value;
    if (busqueda) url += `&busqueda=${encodeURIComponent(busqueda)}`;
    if (estado) url += `&estado=${estado}`;
    if (fd) url += `&fecha_desde=${fd}`;
    if (fh) url += `&fecha_hasta=${fh}`;
    window.location.href = url;
}

function quitarFiltros() {
    window.location.href = BASE_URL + '/frontend/menuprincipal.php?mod=repartidores';
}

// ==================== DETALLE ====================
function verDetalleRepartidor(id) {
    const modalBody = document.getElementById('detalleRepartidorContenido');
    modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando...</p></div>';
    const modal = new bootstrap.Modal(document.getElementById('modalDetalleRepartidor'), { backdrop: false });
    modal.show();
    
    fetch(BASE_URL + `/backend/delivery/get_detalle_repartidor.php?id_repartidor=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                let telefonosHtml = '';
                if (data.telefonos && data.telefonos.length) {
                    data.telefonos.forEach(t => {
                        telefonosHtml += `<div>${escapeHtml(t.numero)} ${t.tipo ? '('+t.tipo+')' : ''}</div>`;
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
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>IDENTIFICACIÓN</small><strong>${escapeHtml(data.tipo_identificacion)}: ${escapeHtml(data.numero_identificacion)}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>DIRECCIÓN</small><p>${escapeHtml(data.direccion) || 'No registrada'}</p></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>FECHA INGRESO</small><strong>${data.fecha_ingreso}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>LICENCIA</small><strong>${escapeHtml(data.licencia_conducir || '—')}</strong> ${data.fecha_vencimiento_licencia ? '(Vence: '+data.fecha_vencimiento_licencia+')' : ''}</div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>TELÉFONO EMERGENCIA</small><strong>${escapeHtml(data.telefono_emergencia || '—')}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>ESTADO</small><strong>${data.activo ? 'Activo' : 'Inactivo'}</strong></div></div>
                        ${data.foto_url ? `<div class="col-md-6"><div class="bg-light p-3 rounded"><small>FOTO</small><br><img src="${escapeHtml(data.foto_url)}" style="max-width: 100px; border-radius: 10px;"></div></div>` : ''}
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>TELÉFONOS</small>${telefonosHtml}</div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>CORREOS</small>${correosHtml}</div></div>
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
function agregarCampoTelefono() {
    const container = document.getElementById('telefonosContainer');
    const div = document.createElement('div');
    div.className = 'input-group mb-2';
    div.innerHTML = `
        <input type="text" class="form-control" placeholder="Número de teléfono" name="telefonos[]">
        <select class="form-select w-auto" name="tipos_telefono[]">
            <option value="PRINCIPAL">Principal</option>
            <option value="TRABAJO">Trabajo</option>
            <option value="PERSONAL">Personal</option>
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

function abrirModalAgregarRepartidor() {
    editMode = false;
    document.getElementById('formRepartidorTitle').innerText = 'Nuevo Repartidor';
    document.getElementById('formRepartidor').reset();
    document.getElementById('repartidor_id').value = '';
    // Fecha ingreso readonly con fecha actual
    const hoy = new Date().toISOString().slice(0,10);
    document.getElementById('repartidor_fecha_ingreso').value = hoy;
    // Limpiar campos dinámicos
    const telContainer = document.getElementById('telefonosContainer');
    telContainer.innerHTML = '';
    agregarCampoTelefono();
    const corContainer = document.getElementById('correosContainer');
    corContainer.innerHTML = '';
    agregarCampoCorreo();
    new bootstrap.Modal(document.getElementById('modalFormRepartidor'), { backdrop: false }).show();
}

function editarRepartidor(id) {
    editMode = true;
    document.getElementById('formRepartidorTitle').innerText = 'Editar Repartidor';
    fetch(BASE_URL + `/backend/delivery/get_detalle_repartidor.php?id_repartidor=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('repartidor_id').value = id;
                document.getElementById('repartidor_nombre').value = data.nombre;
                document.getElementById('repartidor_tipo_identificacion').value = data.tipo_identificacion || 'CEDULA';
                document.getElementById('repartidor_numero_identificacion').value = data.numero_identificacion || '';
                document.getElementById('repartidor_direccion').value = data.direccion || '';
                document.getElementById('repartidor_fecha_ingreso').value = data.fecha_ingreso_raw || '';
                document.getElementById('repartidor_telefono_emergencia').value = data.telefono_emergencia || '';
                document.getElementById('repartidor_licencia').value = data.licencia_conducir || '';
                document.getElementById('repartidor_fecha_vencimiento_licencia').value = data.fecha_vencimiento_licencia_raw || '';
                document.getElementById('repartidor_foto_url').value = data.foto_url || '';
                document.getElementById('repartidor_observaciones').value = data.observaciones || '';
                // Cargar teléfonos
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
                                <option value="PERSONAL" ${t.tipo === 'PERSONAL' ? 'selected' : ''}>Personal</option>
                            </select>
                            <button type="button" class="btn btn-outline-danger" onclick="removerCampoTelefono(this)">-</button>
                        `;
                        telContainer.appendChild(div);
                    });
                } else {
                    agregarCampoTelefono();
                }
                // Cargar correos
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
                new bootstrap.Modal(document.getElementById('modalFormRepartidor'), { backdrop: false }).show();
            } else {
                Swal.fire('Error', 'No se pudo cargar el repartidor', 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
}

function guardarRepartidor() {
    const nombre = document.getElementById('repartidor_nombre').value.trim();
    if (!nombre) {
        Swal.fire('Error', 'El nombre es obligatorio', 'error');
        return;
    }
    
    // Recoger teléfonos
    const telefonos = [];
    const inputsTel = document.querySelectorAll('#telefonosContainer input[name="telefonos[]"]');
    const selectsTel = document.querySelectorAll('#telefonosContainer select[name="tipos_telefono[]"]');
    for (let i = 0; i < inputsTel.length; i++) {
        let numero = inputsTel[i].value.trim();
        if (numero) {
            telefonos.push({
                numero: numero,
                tipo: selectsTel[i].value
            });
        }
    }
    if (telefonos.length === 0) {
        Swal.fire('Error', 'Debe ingresar al menos un teléfono', 'error');
        return;
    }
    
    // Recoger correos
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
        id_repartidor: document.getElementById('repartidor_id').value || null,
        nombre: nombre,
        tipo_identificacion: document.getElementById('repartidor_tipo_identificacion').value,
        numero_identificacion: document.getElementById('repartidor_numero_identificacion').value.trim(),
        direccion: document.getElementById('repartidor_direccion').value.trim(),
        fecha_ingreso: document.getElementById('repartidor_fecha_ingreso').value,
        telefono_emergencia: document.getElementById('repartidor_telefono_emergencia').value.trim(),
        licencia_conducir: document.getElementById('repartidor_licencia').value.trim(),
        fecha_vencimiento_licencia: document.getElementById('repartidor_fecha_vencimiento_licencia').value,
        foto_url: document.getElementById('repartidor_foto_url').value.trim(),
        observaciones: document.getElementById('repartidor_observaciones').value.trim(),
        telefonos: telefonos,
        correos: correos
    };
    
    const url = editMode ? BASE_URL + '/backend/delivery/actualizar_repartidor.php' : BASE_URL + '/backend/delivery/agregar_repartidor.php';
    
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(resp => {
        if (resp.success) {
            Swal.fire('Éxito', editMode ? 'Repartidor actualizado correctamente' : 'Repartidor agregado correctamente', 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', resp.message || 'No se pudo guardar', 'error');
        }
    })
    .catch(err => {
        Swal.fire('Error', 'Error de conexión', 'error');
    });
}

// ==================== CAMBIAR ESTADO ====================
function toggleEstadoRepartidor(id, nuevoEstado) {
    const accion = nuevoEstado ? 'activar' : 'desactivar';
    Swal.fire({
        title: `¿${accion === 'activar' ? 'Activar' : 'Desactivar'} repartidor?`,
        text: `¿Estás seguro de ${accion === 'activar' ? 'activar' : 'desactivar'} este repartidor?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: nuevoEstado ? '#28a745' : '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Sí, ${accion}`,
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(BASE_URL + `/backend/delivery/cambiar_estado_repartidor.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_repartidor: id, activo: nuevoEstado })
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    Swal.fire('Éxito', `Repartidor ${accion}do correctamente`, 'success').then(() => location.reload());
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
    const tabla = document.getElementById('tablaRepartidores');
    if (!tabla || tabla.rows.length === 0) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    const busqueda = document.getElementById('busquedaInput').value || 'Sin búsqueda';
    const estado = document.getElementById('filtroEstado').options[document.getElementById('filtroEstado').selectedIndex]?.text || 'Todos';
    const fechaDesde = document.getElementById('fechaDesde').value || '';
    const fechaHasta = document.getElementById('fechaHasta').value || '';
    let periodo = '';
    if (fechaDesde && fechaHasta) periodo = ` (${fechaDesde} al ${fechaHasta})`;
    else if (fechaDesde) periodo = ` (desde ${fechaDesde})`;
    else if (fechaHasta) periodo = ` (hasta ${fechaHasta})`;
    
    let htmlContent = `
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"><title>Reporte de Repartidores</title>
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
            <h1>Reporte de Repartidores</h1>
            <div class="filters">
                Búsqueda: ${escapeHtml(busqueda)} | Estado: ${escapeHtml(estado)} | Fecha ingreso: ${periodo || 'Todos'}
            </div>
            <table>
                <thead><tr><th>Nombre</th><th>Identificación</th><th>Teléfono</th><th>Email</th><th>Licencia</th><th>Fecha ingreso</th><th>Estado</th></tr></thead>
                <tbody>
    `;
    
    const tbody = document.getElementById('tablaRepartidoresBody');
    const rows = tbody.querySelectorAll('tr');
    rows.forEach(row => {
        if (row.querySelector('.text-muted')) return;
        const cells = row.querySelectorAll('td');
        if (cells.length >= 8) {
            htmlContent += `<tr>
                <td>${escapeHtml(cells[0]?.innerText || '')}</td>
                <td>${escapeHtml(cells[1]?.innerText || '')}</td>
                <td>${escapeHtml(cells[2]?.innerText || '')}</td>
                <td>${escapeHtml(cells[3]?.innerText || '')}</td>
                <td>${escapeHtml(cells[4]?.innerText || '')}</td>
                <td>${escapeHtml(cells[5]?.innerText || '')}</td>
                <td>${escapeHtml(cells[6]?.innerText || '')}</td>
            </tr>`;
        }
    });
    
    htmlContent += `</tbody></table><div class="footer">Reporte generado el ${new Date().toLocaleString()}</div></body></html>`;
    
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    
    const opt = {
        margin: [0.5, 0.5, 0.5, 0.5],
        filename: `repartidores_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.pdf`,
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