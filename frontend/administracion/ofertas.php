<?php
require_once __DIR__ . '/../../backend/conexion.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['id_sesion'])) { header("Location: ../index.php"); exit(); }

// Obtener parámetros de filtro
$busqueda = $_GET['busqueda'] ?? '';
$filtro_activo = $_GET['activo'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// NUEVO (Tarea 5): si se llega desde la Pantalla #04 (Generar acción de
// recuperación → tipo Promoción), trae el contexto del lote/medicamento
// para precargar el modal en vez de que el usuario tenga que llenarlo a mano.
$id_accion = isset($_GET['id_accion']) ? (int)$_GET['id_accion'] : null;
$contextoAccion = null;
if ($id_accion) {
    try {
        $stmtCtx = $conexion->prepare("
            SELECT ar.id_accion, ar.id_lote, ar.id_sucursal_origen, ar.cantidad_afectada, ar.valor_en_riesgo, ar.observaciones,
                   l.numero_lote, l.fecha_vencimiento, l.id_medicamento,
                   m.nombre AS medicamento_nombre
            FROM accion_recuperacion ar
            JOIN lotes l ON ar.id_lote = l.id_lote
            JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
            WHERE ar.id_accion = :id_accion
        ");
        $stmtCtx->execute([':id_accion' => $id_accion]);
        $contextoAccion = $stmtCtx->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

$query = "SELECT * FROM descuentos WHERE 1=1";
$params = [];
if ($busqueda) {
    $query .= " AND (nombre ILIKE :busqueda OR codigo ILIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}
if ($filtro_activo !== '') {
    $query .= " AND activo = :activo";
    $params[':activo'] = $filtro_activo === '1';
}
if ($fecha_desde && $fecha_hasta) {
    $query .= " AND fecha_inicio >= :fecha_desde AND fecha_fin <= :fecha_hasta";
    $params[':fecha_desde'] = $fecha_desde;
    $params[':fecha_hasta'] = $fecha_hasta;
}
$query .= " ORDER BY fecha_creacion DESC";

$ofertas = [];
try {
    $stmt = $conexion->prepare($query);
    $stmt->execute($params);
    $ofertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

$tipos_descuento = [];
try {
    $stmtTipos = $conexion->query("SELECT id_tipo, nombre FROM tipo_descuento");
    $tipos_descuento = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

$base_url = '/sistema-gestor-de-farmacias';
?>
<div class="dashboard-container">
    <?php if ($contextoAccion): ?>
        <div class="alert alert-primary d-flex justify-content-between align-items-center mb-3">
            <div>
                <span class="material-symbols-rounded align-middle me-1">bolt</span>
                Creando promoción para la <strong>acción de recuperación #<?php echo $contextoAccion['id_accion']; ?></strong> —
                Lote <code><?php echo htmlspecialchars($contextoAccion['numero_lote']); ?></code> ·
                <?php echo htmlspecialchars($contextoAccion['medicamento_nombre']); ?> ·
                Valor en riesgo RD$ <?php echo number_format($contextoAccion['valor_en_riesgo'], 2); ?>
            </div>
            <a href="menuprincipal.php?mod=detalle_riesgo_lote&id_lote=<?php echo $contextoAccion['id_lote']; ?>&id_sucursal=<?php echo $contextoAccion['id_sucursal_origen']; ?>" class="btn btn-sm btn-outline-primary">Volver al lote</a>
        </div>
    <?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="text-primary"><span class="material-symbols-rounded align-middle me-2">local_offer</span>Gestión de Ofertas</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalOferta" onclick="resetForm()">+ Nueva Oferta</button>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-3"><input type="text" class="form-control" id="busqueda" placeholder="Buscar por nombre/código" value="<?php echo htmlspecialchars($busqueda); ?>"></div>
                <div class="col-md-2">
                    <select class="form-select" id="filtroActivo">
                        <option value="">Todos</option>
                        <option value="1" <?php echo $filtro_activo === '1' ? 'selected' : ''; ?>>Activos</option>
                        <option value="0" <?php echo $filtro_activo === '0' ? 'selected' : ''; ?>>Inactivos</option>
                    </select>
                </div>
                <div class="col-md-2"><input type="date" class="form-control" id="fecha_desde" placeholder="Fecha desde" value="<?php echo $fecha_desde; ?>"></div>
                <div class="col-md-2"><input type="date" class="form-control" id="fecha_hasta" placeholder="Fecha hasta" value="<?php echo $fecha_hasta; ?>"></div>
                <div class="col-md-3"><button class="btn btn-primary" onclick="aplicarFiltros()">Filtrar</button> <button class="btn btn-secondary" onclick="limpiarFiltros()">Limpiar</button></div>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>Código</th><th>Nombre</th><th>Tipo</th><th>Valor</th><th>Vigencia</th><th>Activo</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php foreach ($ofertas as $o): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($o['codigo']); ?></td>
                        <td><?php echo htmlspecialchars($o['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($o['es_porcentaje'] ? 'Porcentaje' : 'Monto fijo'); ?></td>
                        <td><?php echo $o['es_porcentaje'] ? $o['valor_descuento'].'%' : 'RD$ '.number_format($o['valor_descuento'],2); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($o['fecha_inicio'])).' - '.date('d/m/Y', strtotime($o['fecha_fin'])); ?></td>
                        <td><?php echo $o['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>'; ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editarOferta(<?php echo $o['id_descuento']; ?>)">Editar</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="toggleEstado(<?php echo $o['id_descuento']; ?>, <?php echo $o['activo'] ? 'false' : 'true'; ?>)"><?php echo $o['activo'] ? 'Desactivar' : 'Activar'; ?></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para agregar/editar -->
<div class="modal fade" id="modalOferta" tabindex="-1" data-bs-backdrop="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Oferta</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="formOferta">
                    <input type="hidden" id="id_descuento">
                    <input type="hidden" id="id_medicamento">
                    <input type="hidden" id="id_accion">
                    <?php if ($contextoAccion): ?>
                        <div class="alert alert-light border small mb-2">Medicamento: <strong><?php echo htmlspecialchars($contextoAccion['medicamento_nombre']); ?></strong> (se aplica automáticamente, no editable aquí)</div>
                    <?php endif; ?>
                    <div class="row g-2">
                        <div class="col-md-6"><label>Código *</label><input type="text" class="form-control" id="codigo" required></div>
                        <div class="col-md-6"><label>Nombre *</label><input type="text" class="form-control" id="nombre" required></div>
                        <div class="col-md-6"><label>Tipo descuento</label><select class="form-select" id="id_tipo_descuento"><?php foreach($tipos_descuento as $t): ?><option value="<?php echo $t['id_tipo']; ?>"><?php echo $t['nombre']; ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3"><label>Valor *</label><input type="number" step="0.01" class="form-control" id="valor_descuento" required></div>
                        <div class="col-md-3"><label>¿Porcentaje?</label><select class="form-select" id="es_porcentaje"><option value="1">Sí</option><option value="0">No (monto fijo)</option></select></div>
                        <div class="col-md-3"><label>Fecha inicio *</label><input type="date" class="form-control" id="fecha_inicio" required></div>
                        <div class="col-md-3"><label>Fecha fin *</label><input type="date" class="form-control" id="fecha_fin" required></div>
                        <div class="col-md-12"><label>Descripción</label><textarea class="form-control" id="descripcion" rows="2"></textarea></div>
                        <div class="col-md-6"><label>Monto mínimo compra</label><input type="number" step="0.01" class="form-control" id="monto_minimo_compra"></div>
                        <div class="col-md-6"><label>Activo</label><select class="form-select" id="activo"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" onclick="guardarOferta()">Guardar</button></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';

// NUEVO (Tarea 5): contexto de la acción de recuperación, si se llegó desde
// la Pantalla #04. Si es null, ofertas.php se comporta exactamente igual
// que antes (módulo genérico de administración).
const contextoAccion = <?php echo $contextoAccion ? json_encode([
    'id_accion' => $contextoAccion['id_accion'],
    'id_lote' => $contextoAccion['id_lote'],
    'id_sucursal' => $contextoAccion['id_sucursal_origen'],
    'id_medicamento' => $contextoAccion['id_medicamento'],
    'medicamento_nombre' => $contextoAccion['medicamento_nombre'],
    'numero_lote' => $contextoAccion['numero_lote'],
    'fecha_vencimiento' => $contextoAccion['fecha_vencimiento'],
    'observaciones' => $contextoAccion['observaciones'],
]) : 'null'; ?>;

document.addEventListener('DOMContentLoaded', function() {
    if (contextoAccion) {
        precargarPromocionDesdeAccion();
        new bootstrap.Modal(document.getElementById('modalOferta')).show();
    }
});

// NUEVO (Tarea 5): sugiere valores razonables en vez de dejar el formulario
// en blanco - el usuario los puede editar antes de guardar.
function precargarPromocionDesdeAccion() {
    resetForm();
    document.getElementById('id_medicamento').value = contextoAccion.id_medicamento;
    document.getElementById('id_accion').value = contextoAccion.id_accion;
    document.getElementById('codigo').value = 'VENC-' + contextoAccion.numero_lote;
    document.getElementById('nombre').value = 'Descuento por vencimiento próximo - ' + contextoAccion.medicamento_nombre;
    document.getElementById('valor_descuento').value = 25;
    document.getElementById('es_porcentaje').value = '1';
    document.getElementById('fecha_inicio').value = new Date().toISOString().split('T')[0];
    // Fecha fin sugerida: la de vencimiento del lote (no tendría sentido que la promoción siga después)
    document.getElementById('fecha_fin').value = contextoAccion.fecha_vencimiento;
    document.getElementById('descripcion').value = contextoAccion.observaciones || ('Promoción generada por el proceso estratégico de vencimientos para el lote ' + contextoAccion.numero_lote + '.');
    document.getElementById('activo').value = '1';
}

function aplicarFiltros() { let url = BASE_URL+'/frontend/menuprincipal.php?mod=ofertas&busqueda='+encodeURIComponent(document.getElementById('busqueda').value)+'&activo='+document.getElementById('filtroActivo').value+'&fecha_desde='+document.getElementById('fecha_desde').value+'&fecha_hasta='+document.getElementById('fecha_hasta').value; window.location.href=url; }
function limpiarFiltros() { window.location.href = BASE_URL+'/frontend/menuprincipal.php?mod=ofertas'; }
function resetForm() { document.getElementById('formOferta').reset(); document.getElementById('id_descuento').value=''; document.getElementById('id_medicamento').value=''; document.getElementById('id_accion').value=''; }
function editarOferta(id) { fetch(BASE_URL+'/backend/administracion/get_oferta.php?id='+id).then(r=>r.json()).then(data=>{ if(data.success){ document.getElementById('id_descuento').value=data.id_descuento; document.getElementById('codigo').value=data.codigo; document.getElementById('nombre').value=data.nombre; document.getElementById('id_tipo_descuento').value=data.id_tipo_descuento; document.getElementById('valor_descuento').value=data.valor_descuento; document.getElementById('es_porcentaje').value=data.es_porcentaje?'1':'0'; document.getElementById('fecha_inicio').value=data.fecha_inicio; document.getElementById('fecha_fin').value=data.fecha_fin; document.getElementById('descripcion').value=data.descripcion; document.getElementById('monto_minimo_compra').value=data.monto_minimo_compra; document.getElementById('activo').value=data.activo?'1':'0'; new bootstrap.Modal(document.getElementById('modalOferta')).show(); } else Swal.fire('Error','No se pudo cargar','error'); }).catch(()=>Swal.fire('Error','Error de conexión','error')); }
function guardarOferta() {
    let data = { id_descuento: document.getElementById('id_descuento').value, codigo: document.getElementById('codigo').value, nombre: document.getElementById('nombre').value, id_tipo_descuento: document.getElementById('id_tipo_descuento').value, valor_descuento: parseFloat(document.getElementById('valor_descuento').value), es_porcentaje: document.getElementById('es_porcentaje').value === '1', fecha_inicio: document.getElementById('fecha_inicio').value, fecha_fin: document.getElementById('fecha_fin').value, descripcion: document.getElementById('descripcion').value, monto_minimo_compra: parseFloat(document.getElementById('monto_minimo_compra').value)||null, activo: document.getElementById('activo').value === '1',
        id_medicamento: document.getElementById('id_medicamento').value || null,
        id_accion: document.getElementById('id_accion').value || null
    };
    fetch(BASE_URL+'/backend/administracion/guardar_oferta.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)}).then(r=>r.json()).then(res=>{
        if(res.success){
            if (data.id_accion) {
                // Viene del proceso estratégico: vuelve al lote en vez de quedarse en Ofertas
                Swal.fire('¡Promoción creada!', 'Se enlazó a la acción de recuperación y quedó en estado EN_EJECUCION.', 'success')
                    .then(() => { window.location.href = BASE_URL + '/frontend/menuprincipal.php?mod=detalle_riesgo_lote&id_lote=' + contextoAccion.id_lote + '&id_sucursal=' + contextoAccion.id_sucursal; });
            } else {
                Swal.fire('Éxito','Oferta guardada','success').then(()=>location.reload());
            }
        } else Swal.fire('Error',res.message,'error');
    }).catch(()=>Swal.fire('Error','Error de conexión','error'));
}
function toggleEstado(id, activar) { Swal.fire({title:'Confirmar',text:`¿${activar?'Activar':'Desactivar'} oferta?`,icon:'question',showCancelButton:true}).then(result=>{ if(result.isConfirmed){ fetch(BASE_URL+'/backend/administracion/cambiar_estado_oferta.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id_descuento:id,activo:activar})}).then(r=>r.json()).then(res=>{ if(res.success) Swal.fire('Éxito','Estado cambiado','success').then(()=>location.reload()); else Swal.fire('Error',res.message,'error'); }); } }); }
</script>