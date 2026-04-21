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
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_repartidor = $_GET['repartidor'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Obtener tipos únicos de vehículos para el filtro (excluyendo Bicicleta y Moto)
$tipos = [];
try {
    $stmtTipos = $conexion->query("SELECT DISTINCT tipo FROM vehiculos WHERE tipo IS NOT NULL AND tipo != '' AND LOWER(tipo) NOT IN ('bicicleta', 'moto') ORDER BY tipo");
    $tipos = $stmtTipos->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {}

// Obtener repartidores activos para el filtro
$repartidores_opciones = [];
try {
    $stmtRep = $conexion->query("SELECT id_repartidor, nombre FROM repartidores WHERE activo = TRUE ORDER BY nombre");
    $repartidores_opciones = $stmtRep->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Consulta principal de vehículos
$query = "
    SELECT 
        v.id_vehiculo,
        v.tipo,
        v.placa,
        v.marca,
        v.modelo,
        v.color,
        v.seguro_empresa,
        v.fecha_vencimiento_seguro,
        v.activo,
        r.id_repartidor,
        r.nombre AS repartidor_nombre,
        (SELECT t.numero FROM telefonos t 
         JOIN repartidor_telefono rt ON t.id_telefono = rt.id_telefono 
         WHERE rt.id_repartidor = v.id_repartidor AND t.activo = TRUE LIMIT 1) AS repartidor_telefono
    FROM vehiculos v
    LEFT JOIN repartidores r ON v.id_repartidor = r.id_repartidor
    WHERE 1=1
";

$params = [];
if ($busqueda) {
    $query .= " AND (v.placa ILIKE :busqueda OR v.marca ILIKE :busqueda OR v.modelo ILIKE :busqueda OR v.color ILIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}
if ($filtro_tipo) {
    $query .= " AND v.tipo = :tipo";
    $params[':tipo'] = $filtro_tipo;
}
if ($filtro_repartidor) {
    $query .= " AND v.id_repartidor = :repartidor";
    $params[':repartidor'] = $filtro_repartidor;
}
if ($filtro_estado === 'activo') {
    $query .= " AND v.activo = TRUE";
} elseif ($filtro_estado === 'inactivo') {
    $query .= " AND v.activo = FALSE";
}
if ($fecha_desde && $fecha_hasta) {
    $query .= " AND v.fecha_vencimiento_seguro BETWEEN :fecha_desde AND :fecha_hasta";
    $params[':fecha_desde'] = $fecha_desde;
    $params[':fecha_hasta'] = $fecha_hasta;
} elseif ($fecha_desde) {
    $query .= " AND v.fecha_vencimiento_seguro >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
} elseif ($fecha_hasta) {
    $query .= " AND v.fecha_vencimiento_seguro <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

$query .= " ORDER BY v.id_vehiculo DESC";

$vehiculos = [];
$total_vehiculos = 0;
$total_activos = 0;
$total_inactivos = 0;

try {
    $stmt = $conexion->prepare($query);
    $stmt->execute($params);
    $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_vehiculos = count($vehiculos);
    foreach ($vehiculos as $v) {
        if ($v['activo'] == 't' || $v['activo'] === true || $v['activo'] === 1) {
            $total_activos++;
        } else {
            $total_inactivos++;
        }
    }
} catch(PDOException $e) {
    $vehiculos = [];
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
    /* Estilos para la paleta de colores */
    .color-swatch {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        cursor: pointer;
        border: 1px solid #ccc;
        transition: transform 0.1s ease, box-shadow 0.1s ease;
    }
    .color-swatch:hover {
        transform: scale(1.1);
        box-shadow: 0 0 0 2px #fff, 0 0 0 3px #28a745;
    }
    .form-control-color {
        width: 60px;
        height: 38px;
        padding: 2px;
    }
    /* Estilo para el círculo de color en la tabla */
    .color-preview {
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        transition: transform 0.1s ease;
        vertical-align: middle;
    }
    .color-preview:hover {
        transform: scale(1.15);
    }
</style>

<div class="dashboard-container">
    <div class="header-actions">
        <div>
            <h2 class="mb-0 text-success">
                <span class="material-symbols-rounded align-middle me-2">directions_car</span>
                Gestión de Vehículos
            </h2>
            <p class="text-muted mb-0">Administra los vehículos asignados a repartidores</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" onclick="abrirModalAgregarVehiculo()">
                <span class="material-symbols-rounded align-middle me-1">add</span>
                Agregar Vehículo
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
                <small>TOTAL VEHÍCULOS</small>
                <h3><?php echo $total_vehiculos; ?></h3>
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
                <input type="text" class="form-control" id="busquedaInput" placeholder="Placa, marca, modelo, color..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">TIPO</label>
                <select class="form-select" id="filtroTipo">
                    <option value="">Todos</option>
                    <?php foreach ($tipos as $tipo): ?>
                        <option value="<?php echo htmlspecialchars($tipo); ?>" <?php echo $filtro_tipo == $tipo ? 'selected' : ''; ?>><?php echo htmlspecialchars($tipo); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">REPARTIDOR</label>
                <select class="form-select" id="filtroRepartidor">
                    <option value="">Todos</option>
                    <?php foreach ($repartidores_opciones as $rep): ?>
                        <option value="<?php echo $rep['id_repartidor']; ?>" <?php echo $filtro_repartidor == $rep['id_repartidor'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($rep['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
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
                <label class="form-label fw-bold small text-muted">VENC. SEGURO (DESDE)</label>
                <input type="date" class="form-control" id="fechaDesde" value="<?php echo $fecha_desde; ?>">
            </div>
            <div class="col-md-1 text-end">
                <button type="button" class="btn btn-quitar-filtros" onclick="quitarFiltros()">Quitar filtros</button>
            </div>
        </div>
    </div>

    <!-- Tabla de vehículos -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaVehiculos">
                    <thead class="table-light">
                        <tr>
                            <th>Placa</th>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th>Tipo</th>
                            <th>Color</th>
                            <th>Repartidor</th>
                            <th>Seguro empresa</th>
                            <th>Venc. seguro</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaVehiculosBody">
                        <?php if (empty($vehiculos)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-5">
                                    <i class="fas fa-car d-block mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                                    No hay vehículos registrados con los filtros seleccionados
                                </td>
                            </tr>
                        <?php else: foreach ($vehiculos as $veh): 
                            $estado_texto = ($veh['activo'] == 't' || $veh['activo'] === true || $veh['activo'] === 1) ? 'Activo' : 'Inactivo';
                            $estado_class = ($estado_texto === 'Activo') ? 'badge-activo' : 'badge-inactivo';
                            $fecha_venc_seguro = $veh['fecha_vencimiento_seguro'] ? date('d/m/Y', strtotime($veh['fecha_vencimiento_seguro'])) : '—';
                            $repartidor_nombre = htmlspecialchars($veh['repartidor_nombre'] ?? 'No asignado');
                            $seguro_empresa = htmlspecialchars($veh['seguro_empresa'] ?? '—');
                        ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($veh['placa'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($veh['marca'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($veh['modelo'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($veh['tipo']); ?></td>
                                <td>
                                    <?php if ($veh['color']): ?>
                                        <span class="color-preview d-inline-block" style="background-color: <?php echo htmlspecialchars($veh['color']); ?>; width: 28px; height: 28px; border-radius: 50%; border: 1px solid #ddd; cursor: help;" title="<?php echo htmlspecialchars($veh['color']); ?>"></span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $repartidor_nombre; ?></td>
                                <td><?php echo $seguro_empresa; ?></td>
                                <td><?php echo $fecha_venc_seguro; ?></td>
                                <td><span class="badge-estado <?php echo $estado_class; ?>"><?php echo $estado_texto; ?></span></td>
                                <td class="text-center">
                                    <button class="btn btn-outline-info btn-sm me-1" onclick="verDetalleVehiculo(<?php echo $veh['id_vehiculo']; ?>)">
                                        <span class="material-symbols-rounded">visibility</span>
                                    </button>
                                    <button class="btn btn-outline-warning btn-sm me-1" onclick="editarVehiculo(<?php echo $veh['id_vehiculo']; ?>)">
                                        <span class="material-symbols-rounded">edit</span>
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="toggleEstadoVehiculo(<?php echo $veh['id_vehiculo']; ?>, <?php echo $estado_texto === 'Activo' ? 'false' : 'true'; ?>)">
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

<!-- MODAL DETALLE VEHÍCULO -->
<div class="modal fade" id="modalDetalleVehiculo" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Detalle del Vehículo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleVehiculoContenido">
                <div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando...</p></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL AGREGAR/EDITAR VEHÍCULO -->
<div class="modal fade" id="modalFormVehiculo" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="formVehiculoTitle">Nuevo Vehículo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formVehiculo">
                    <input type="hidden" id="vehiculo_id" value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tipo *</label>
                            <select class="form-select" id="vehiculo_tipo" required>
                                <option value="">Seleccione tipo</option>
                                <option value="Motocicleta">Motocicleta</option>
                                <option value="Automóvil">Automóvil</option>
                                <option value="Camioneta">Camioneta</option>
                                <?php 
                                // Tipos adicionales de la BD (excluyendo Bicicleta y Moto)
                                foreach ($tipos as $t): 
                                    if (!in_array($t, ['Motocicleta','Automóvil','Camioneta'])): ?>
                                        <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                                <?php 
                                    endif; 
                                endforeach; 
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Placa</label>
                            <input type="text" class="form-control" id="vehiculo_placa" placeholder="Ej: M001-ABC">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Marca *</label>
                            <select class="form-select" id="vehiculo_marca" required>
                                <option value="">Primero seleccione tipo</option>
                            </select>
                            <div id="marca_otro_container" style="display:none; margin-top:5px;">
                                <input type="text" class="form-control" id="vehiculo_marca_otro" placeholder="Escriba la marca">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Modelo *</label>
                            <select class="form-select" id="vehiculo_modelo" required>
                                <option value="">Primero seleccione marca</option>
                            </select>
                            <div id="modelo_otro_container" style="display:none; margin-top:5px;">
                                <input type="text" class="form-control" id="vehiculo_modelo_otro" placeholder="Escriba el modelo">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Color</label>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <input type="color" class="form-control form-control-color" id="vehiculo_color_picker" value="#000000" style="width: 60px; height: 38px;">
                                <input type="text" class="form-control" id="vehiculo_color" placeholder="Código o nombre del color" style="flex:1;">
                            </div>
                            <div class="color-palette d-flex gap-1 mt-2 flex-wrap">
                                <div class="color-swatch" style="background-color: #ff0000;" data-color="#ff0000" title="Rojo"></div>
                                <div class="color-swatch" style="background-color: #00ff00;" data-color="#00ff00" title="Verde"></div>
                                <div class="color-swatch" style="background-color: #0000ff;" data-color="#0000ff" title="Azul"></div>
                                <div class="color-swatch" style="background-color: #ffff00;" data-color="#ffff00" title="Amarillo"></div>
                                <div class="color-swatch" style="background-color: #ffa500;" data-color="#ffa500" title="Naranja"></div>
                                <div class="color-swatch" style="background-color: #800080;" data-color="#800080" title="Morado"></div>
                                <div class="color-swatch" style="background-color: #ffc0cb;" data-color="#ffc0cb" title="Rosa"></div>
                                <div class="color-swatch" style="background-color: #000000;" data-color="#000000" title="Negro"></div>
                                <div class="color-swatch" style="background-color: #ffffff;" data-color="#ffffff" title="Blanco" style="border:1px solid #ccc;"></div>
                                <div class="color-swatch" style="background-color: #808080;" data-color="#808080" title="Gris"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Repartidor asignado</label>
                            <select class="form-select" id="vehiculo_repartidor">
                                <option value="">Sin asignar</option>
                                <?php foreach ($repartidores_opciones as $rep): ?>
                                    <option value="<?php echo $rep['id_repartidor']; ?>"><?php echo htmlspecialchars($rep['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Seguro empresa</label>
                            <input type="text" class="form-control" id="vehiculo_seguro_empresa">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Fecha vencimiento seguro</label>
                            <input type="date" class="form-control" id="vehiculo_fecha_vencimiento_seguro">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Estado</label>
                            <select class="form-select" id="vehiculo_activo">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="guardarVehiculo()">Guardar Vehículo</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';
let editMode = false;

// ==================== DATOS DE MARCAS Y MODELOS POR TIPO ====================
const datosVehiculos = {
    "Motocicleta": {
        "Yamaha": ["FZ16", "NMAX", "MT-09", "YZF-R3", "Crypton"],
        "Honda": ["CBR 600", "CB 190", "XRE 300", "Wave", "Biz"],
        "Suzuki": ["GSX-R1000", "V-Strom", "GN 125", "AX100"],
        "Kawasaki": ["Ninja 400", "Z900", "Versys", "KLR650"],
        "KTM": ["Duke 390", "RC 390", "Adventure 790"]
    },
    "Automóvil": {
        "Toyota": ["Corolla", "Camry", "Yaris", "Hilux", "Rav4"],
        "Honda": ["Civic", "Accord", "CR-V", "HR-V"],
        "Nissan": ["Sentra", "Versa", "March", "X-Trail"],
        "Hyundai": ["Elantra", "Accent", "Tucson", "Santa Fe"],
        "Kia": ["Rio", "Soul", "Sportage", "Sorento"],
        "Chevrolet": ["Spark", "Onix", "Cruze", "Tracker"],
        "Mazda": ["Mazda 2", "Mazda 3", "Mazda 6", "CX-5"],
        "Ford": ["Fiesta", "Focus", "Escape", "Explorer"]
    },
    "Camioneta": {
        "Toyota": ["Hilux", "Tacoma", "Tundra", "Land Cruiser"],
        "Ford": ["Ranger", "F-150", "F-250", "Explorer"],
        "Nissan": ["Frontier", "Navara", "Patrol"],
        "Chevrolet": ["Silverado", "Colorado", "S-10"],
        "Mitsubishi": ["L200", "Montero"]
    }
};

// Función para cargar marcas según el tipo seleccionado
function cargarMarcas() {
    const tipo = document.getElementById('vehiculo_tipo').value;
    const marcaSelect = document.getElementById('vehiculo_marca');
    const marcaOtroContainer = document.getElementById('marca_otro_container');
    const marcaOtroInput = document.getElementById('vehiculo_marca_otro');
    
    // Limpiar selects
    marcaSelect.innerHTML = '<option value="">Seleccione marca</option>';
    document.getElementById('vehiculo_modelo').innerHTML = '<option value="">Primero seleccione marca</option>';
    document.getElementById('modelo_otro_container').style.display = 'none';
    marcaOtroContainer.style.display = 'none';
    
    if (!tipo || !datosVehiculos[tipo]) {
        marcaSelect.disabled = true;
        return;
    }
    
    marcaSelect.disabled = false;
    const marcas = Object.keys(datosVehiculos[tipo]);
    marcas.forEach(marca => {
        const option = document.createElement('option');
        option.value = marca;
        option.textContent = marca;
        marcaSelect.appendChild(option);
    });
    // Opción "Otro" (solo una vez)
    const optionOtro = document.createElement('option');
    optionOtro.value = "__otro__";
    optionOtro.textContent = "Otro (escribir)";
    marcaSelect.appendChild(optionOtro);
}

function cargarModelos() {
    const tipo = document.getElementById('vehiculo_tipo').value;
    const marca = document.getElementById('vehiculo_marca').value;
    const modeloSelect = document.getElementById('vehiculo_modelo');
    const modeloOtroContainer = document.getElementById('modelo_otro_container');
    const modeloOtroInput = document.getElementById('vehiculo_modelo_otro');
    
    modeloSelect.innerHTML = '<option value="">Seleccione modelo</option>';
    modeloOtroContainer.style.display = 'none';
    
    if (!tipo || !marca || !datosVehiculos[tipo]) {
        modeloSelect.disabled = true;
        return;
    }
    
    if (marca === "__otro__") {
        document.getElementById('marca_otro_container').style.display = 'block';
        modeloSelect.disabled = true;
        return;
    }
    
    document.getElementById('marca_otro_container').style.display = 'none';
    const modelos = datosVehiculos[tipo][marca] || [];
    modeloSelect.disabled = false;
    modelos.forEach(modelo => {
        const option = document.createElement('option');
        option.value = modelo;
        option.textContent = modelo;
        modeloSelect.appendChild(option);
    });
    const optionOtro = document.createElement('option');
    optionOtro.value = "__otro__";
    optionOtro.textContent = "Otro (escribir)";
    modeloSelect.appendChild(optionOtro);
}

function onMarcaChange() {
    const marcaSelect = document.getElementById('vehiculo_marca');
    if (marcaSelect.value === "__otro__") {
        document.getElementById('marca_otro_container').style.display = 'block';
        document.getElementById('vehiculo_modelo').disabled = true;
        document.getElementById('modelo_otro_container').style.display = 'none';
    } else {
        document.getElementById('marca_otro_container').style.display = 'none';
        cargarModelos();
    }
}

function onModeloChange() {
    const modeloSelect = document.getElementById('vehiculo_modelo');
    if (modeloSelect.value === "__otro__") {
        document.getElementById('modelo_otro_container').style.display = 'block';
    } else {
        document.getElementById('modelo_otro_container').style.display = 'none';
    }
}

// Obtener valor final de marca (del select o del input "otro")
function getMarcaValue() {
    const marcaSelect = document.getElementById('vehiculo_marca');
    if (marcaSelect.value === "__otro__") {
        return document.getElementById('vehiculo_marca_otro').value.trim();
    }
    return marcaSelect.value;
}

function getModeloValue() {
    const modeloSelect = document.getElementById('vehiculo_modelo');
    if (modeloSelect.value === "__otro__") {
        return document.getElementById('vehiculo_modelo_otro').value.trim();
    }
    return modeloSelect.value;
}

// Asignar valores iniciales en edición (cargar tipo -> marcas -> seleccionar marca -> cargar modelos -> seleccionar modelo)
async function setVehiculoDataForEdit(data) {
    // 1. Establecer tipo
    const tipoSelect = document.getElementById('vehiculo_tipo');
    tipoSelect.value = data.tipo || '';
    // 2. Cargar marcas según tipo
    cargarMarcas();
    // Esperar un poco a que se carguen las opciones
    setTimeout(() => {
        // 3. Seleccionar marca (si existe en la lista, si no usar "Otro")
        const marcaSelect = document.getElementById('vehiculo_marca');
        const marcaValue = data.marca || '';
        const optionExists = Array.from(marcaSelect.options).some(opt => opt.value === marcaValue);
        if (optionExists) {
            marcaSelect.value = marcaValue;
        } else if (marcaValue) {
            // Usar "Otro" y guardar en el input
            marcaSelect.value = "__otro__";
            document.getElementById('marca_otro_container').style.display = 'block';
            document.getElementById('vehiculo_marca_otro').value = marcaValue;
        }
        // 4. Disparar cambio de marca para cargar modelos
        onMarcaChange();
        setTimeout(() => {
            // 5. Seleccionar modelo
            const modeloSelect = document.getElementById('vehiculo_modelo');
            const modeloValue = data.modelo || '';
            const modeloOptionExists = Array.from(modeloSelect.options).some(opt => opt.value === modeloValue);
            if (modeloOptionExists) {
                modeloSelect.value = modeloValue;
            } else if (modeloValue) {
                modeloSelect.value = "__otro__";
                document.getElementById('modelo_otro_container').style.display = 'block';
                document.getElementById('vehiculo_modelo_otro').value = modeloValue;
            }
            onModeloChange();
        }, 100);
    }, 50);
    
    // 6. Establecer color (sincronizar color picker y campo de texto)
    const colorValue = data.color || '';
    document.getElementById('vehiculo_color').value = colorValue;
    // Si el color es un código hexadecimal válido (formato #RRGGBB), actualizar el picker
    if (/^#[0-9A-Fa-f]{6}$/.test(colorValue)) {
        document.getElementById('vehiculo_color_picker').value = colorValue;
    } else {
        // Si no es hex, dejar el picker por defecto (negro) pero mantener el texto
        document.getElementById('vehiculo_color_picker').value = '#000000';
    }
}

// ==================== FILTROS ====================
document.addEventListener('DOMContentLoaded', function() {
    const filtros = ['busquedaInput', 'filtroTipo', 'filtroRepartidor', 'filtroEstado', 'fechaDesde', 'fechaHasta'];
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
    
    // Eventos para cascada
    const tipoSelect = document.getElementById('vehiculo_tipo');
    if (tipoSelect) tipoSelect.addEventListener('change', function() {
        cargarMarcas();
        document.getElementById('vehiculo_modelo').innerHTML = '<option value="">Primero seleccione marca</option>';
    });
    const marcaSelect = document.getElementById('vehiculo_marca');
    if (marcaSelect) marcaSelect.addEventListener('change', onMarcaChange);
    const modeloSelect = document.getElementById('vehiculo_modelo');
    if (modeloSelect) modeloSelect.addEventListener('change', onModeloChange);
    
    // Sincronización del color: cuando el picker cambie, actualizar el campo de texto
    const colorPicker = document.getElementById('vehiculo_color_picker');
    const colorText = document.getElementById('vehiculo_color');
    if (colorPicker && colorText) {
        colorPicker.addEventListener('input', function() {
            colorText.value = this.value;
        });
        colorText.addEventListener('input', function() {
            // Si el texto ingresado es un hex válido, actualizar el picker
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                colorPicker.value = this.value;
            }
        });
    }
    
    // Eventos para los círculos de color (paleta)
    document.querySelectorAll('.color-swatch').forEach(swatch => {
        swatch.addEventListener('click', function() {
            const color = this.getAttribute('data-color');
            if (colorPicker && colorText) {
                colorPicker.value = color;
                colorText.value = color;
            }
        });
    });
});

function aplicarFiltros() {
    let url = BASE_URL + '/frontend/menuprincipal.php?mod=vehiculos';
    const busqueda = document.getElementById('busquedaInput').value;
    const tipo = document.getElementById('filtroTipo').value;
    const repartidor = document.getElementById('filtroRepartidor').value;
    const estado = document.getElementById('filtroEstado').value;
    const fd = document.getElementById('fechaDesde').value;
    const fh = document.getElementById('fechaHasta').value;
    if (busqueda) url += `&busqueda=${encodeURIComponent(busqueda)}`;
    if (tipo) url += `&tipo=${encodeURIComponent(tipo)}`;
    if (repartidor) url += `&repartidor=${repartidor}`;
    if (estado) url += `&estado=${estado}`;
    if (fd) url += `&fecha_desde=${fd}`;
    if (fh) url += `&fecha_hasta=${fh}`;
    window.location.href = url;
}

function quitarFiltros() {
    window.location.href = BASE_URL + '/frontend/menuprincipal.php?mod=vehiculos';
}

// ==================== DETALLE ====================
function verDetalleVehiculo(id) {
    const modalBody = document.getElementById('detalleVehiculoContenido');
    modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div><p>Cargando...</p></div>';
    const modal = new bootstrap.Modal(document.getElementById('modalDetalleVehiculo'), { backdrop: false });
    modal.show();
    
    fetch(BASE_URL + `/backend/delivery/get_detalle_vehiculo.php?id_vehiculo=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const html = `
                    <div class="row g-3">
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>TIPO</small><strong>${escapeHtml(data.tipo)}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>PLACA</small><strong>${escapeHtml(data.placa || '—')}</strong></div></div>
                        <div class="col-md-4"><div class="bg-light p-3 rounded"><small>MARCA</small><strong>${escapeHtml(data.marca || '—')}</strong></div></div>
                        <div class="col-md-4"><div class="bg-light p-3 rounded"><small>MODELO</small><strong>${escapeHtml(data.modelo || '—')}</strong></div></div>
                        <div class="col-md-4"><div class="bg-light p-3 rounded"><small>COLOR</small><strong>${escapeHtml(data.color || '—')}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>REPARTIDOR</small><strong>${escapeHtml(data.repartidor_nombre || 'No asignado')}</strong> ${data.repartidor_telefono ? '📞 ' + escapeHtml(data.repartidor_telefono) : ''}</div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>SEGURO EMPRESA</small><strong>${escapeHtml(data.seguro_empresa || '—')}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>VENCIMIENTO SEGURO</small><strong>${data.fecha_vencimiento_seguro || '—'}</strong></div></div>
                        <div class="col-md-6"><div class="bg-light p-3 rounded"><small>ESTADO</small><strong>${data.activo ? 'Activo' : 'Inactivo'}</strong></div></div>
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
function abrirModalAgregarVehiculo() {
    editMode = false;
    document.getElementById('formVehiculoTitle').innerText = 'Nuevo Vehículo';
    document.getElementById('formVehiculo').reset();
    document.getElementById('vehiculo_id').value = '';
    document.getElementById('vehiculo_activo').value = '1';
    // Limpiar selects dependientes
    document.getElementById('vehiculo_tipo').value = '';
    cargarMarcas();
    document.getElementById('vehiculo_modelo').innerHTML = '<option value="">Primero seleccione marca</option>';
    document.getElementById('marca_otro_container').style.display = 'none';
    document.getElementById('modelo_otro_container').style.display = 'none';
    // Resetear color
    document.getElementById('vehiculo_color').value = '';
    document.getElementById('vehiculo_color_picker').value = '#000000';
    new bootstrap.Modal(document.getElementById('modalFormVehiculo'), { backdrop: false }).show();
}

function editarVehiculo(id) {
    editMode = true;
    document.getElementById('formVehiculoTitle').innerText = 'Editar Vehículo';
    fetch(BASE_URL + `/backend/delivery/get_detalle_vehiculo.php?id_vehiculo=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('vehiculo_id').value = id;
                document.getElementById('vehiculo_placa').value = data.placa || '';
                document.getElementById('vehiculo_repartidor').value = data.id_repartidor || '';
                document.getElementById('vehiculo_seguro_empresa').value = data.seguro_empresa || '';
                document.getElementById('vehiculo_fecha_vencimiento_seguro').value = data.fecha_vencimiento_seguro_raw || '';
                document.getElementById('vehiculo_activo').value = data.activo ? '1' : '0';
                // Cargar dependencias (tipo, marca, modelo)
                setVehiculoDataForEdit(data);
                new bootstrap.Modal(document.getElementById('modalFormVehiculo'), { backdrop: false }).show();
            } else {
                Swal.fire('Error', 'No se pudo cargar el vehículo', 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
}

function guardarVehiculo() {
    const tipo = document.getElementById('vehiculo_tipo').value;
    if (!tipo) {
        Swal.fire('Error', 'El tipo de vehículo es obligatorio', 'error');
        return;
    }
    
    const marca = getMarcaValue();
    if (!marca) {
        Swal.fire('Error', 'La marca es obligatoria', 'error');
        return;
    }
    
    const modelo = getModeloValue();
    if (!modelo) {
        Swal.fire('Error', 'El modelo es obligatorio', 'error');
        return;
    }
    
    const color = document.getElementById('vehiculo_color').value.trim();
    
    const data = {
        id_vehiculo: document.getElementById('vehiculo_id').value || null,
        tipo: tipo,
        placa: document.getElementById('vehiculo_placa').value.trim(),
        marca: marca,
        modelo: modelo,
        color: color,
        id_repartidor: document.getElementById('vehiculo_repartidor').value || null,
        seguro_empresa: document.getElementById('vehiculo_seguro_empresa').value.trim(),
        fecha_vencimiento_seguro: document.getElementById('vehiculo_fecha_vencimiento_seguro').value || null,
        activo: document.getElementById('vehiculo_activo').value === '1'
    };
    
    const url = editMode ? BASE_URL + '/backend/delivery/actualizar_vehiculo.php' : BASE_URL + '/backend/delivery/agregar_vehiculo.php';
    
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(resp => {
        if (resp.success) {
            Swal.fire('Éxito', editMode ? 'Vehículo actualizado correctamente' : 'Vehículo agregado correctamente', 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', resp.message || 'No se pudo guardar', 'error');
        }
    })
    .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
}

// ==================== CAMBIAR ESTADO ====================
function toggleEstadoVehiculo(id, nuevoEstado) {
    const accion = nuevoEstado ? 'activar' : 'desactivar';
    Swal.fire({
        title: `¿${accion === 'activar' ? 'Activar' : 'Desactivar'} vehículo?`,
        text: `¿Estás seguro de ${accion === 'activar' ? 'activar' : 'desactivar'} este vehículo?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: nuevoEstado ? '#28a745' : '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Sí, ${accion}`,
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(BASE_URL + `/backend/delivery/cambiar_estado_vehiculo.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_vehiculo: id, activo: nuevoEstado })
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    Swal.fire('Éxito', `Vehículo ${accion}do correctamente`, 'success').then(() => location.reload());
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
    const tabla = document.getElementById('tablaVehiculos');
    if (!tabla || tabla.rows.length === 0) {
        Swal.fire('Error', 'No hay datos para exportar', 'error');
        return;
    }
    
    const busqueda = document.getElementById('busquedaInput').value || 'Sin búsqueda';
    const tipo = document.getElementById('filtroTipo').options[document.getElementById('filtroTipo').selectedIndex]?.text || 'Todos';
    const repartidor = document.getElementById('filtroRepartidor').options[document.getElementById('filtroRepartidor').selectedIndex]?.text || 'Todos';
    const estado = document.getElementById('filtroEstado').options[document.getElementById('filtroEstado').selectedIndex]?.text || 'Todos';
    const fd = document.getElementById('fechaDesde').value || '';
    const fh = document.getElementById('fechaHasta').value || '';
    let periodo = '';
    if (fd && fh) periodo = ` (${fd} al ${fh})`;
    else if (fd) periodo = ` (desde ${fd})`;
    else if (fh) periodo = ` (hasta ${fh})`;
    
    let htmlContent = `
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"><title>Reporte de Vehículos</title>
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
            <h1>Reporte de Vehículos</h1>
            <div class="filters">
                Búsqueda: ${escapeHtml(busqueda)} | Tipo: ${escapeHtml(tipo)} | Repartidor: ${escapeHtml(repartidor)} | Estado: ${escapeHtml(estado)} | Venc. seguro: ${periodo || 'Todos'}
            </div>
            <table>
                <thead><tr><th>Placa</th><th>Marca</th><th>Modelo</th><th>Tipo</th><th>Color</th><th>Repartidor</th><th>Seguro empresa</th><th>Venc. seguro</th><th>Estado</th></td></thead>
                <tbody>
    `;
    
    const tbody = document.getElementById('tablaVehiculosBody');
    const rows = tbody.querySelectorAll('tr');
    rows.forEach(row => {
        if (row.querySelector('.text-muted')) return;
        const cells = row.querySelectorAll('td');
        if (cells.length >= 10) {
            htmlContent += `<tr>
                <td>${escapeHtml(cells[0]?.innerText || '')}</td>
                <td>${escapeHtml(cells[1]?.innerText || '')}</td>
                <td>${escapeHtml(cells[2]?.innerText || '')}</td>
                <td>${escapeHtml(cells[3]?.innerText || '')}</td>
                <td>${escapeHtml(cells[4]?.innerText || '')}</td>
                <td>${escapeHtml(cells[5]?.innerText || '')}</td>
                <td>${escapeHtml(cells[6]?.innerText || '')}</td>
                <td>${escapeHtml(cells[7]?.innerText || '')}</td>
                <td>${escapeHtml(cells[8]?.innerText || '')}</td>
            </tr>`;
        }
    });
    
    htmlContent += `</tbody></table><div class="footer">Reporte generado el ${new Date().toLocaleString()}</div></body></html>`;
    
    const element = document.createElement('div');
    element.innerHTML = htmlContent;
    document.body.appendChild(element);
    
    const opt = {
        margin: [0.5, 0.5, 0.5, 0.5],
        filename: `vehiculos_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.pdf`,
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