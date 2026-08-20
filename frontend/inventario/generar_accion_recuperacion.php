<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header("Location: " . dirname(__DIR__, 2) . "/frontend/index.php");
    exit();
}

date_default_timezone_set('America/Santo_Domingo');

$base_path = dirname(__DIR__, 2);
require_once $base_path . '/backend/queries/index.php';
require_once $base_path . '/backend/inventario/riesgo_vencimiento_lib.php';

$base_url = '/Sistema-Gestor-de-Farmacias';

$id_lote = isset($_GET['id_lote']) ? (int)$_GET['id_lote'] : 0;
$id_sucursal = isset($_GET['id_sucursal']) ? (int)$_GET['id_sucursal'] : 0;

$umbrales = obtenerUmbralesVencimiento($conexion);
$lote = null;
$error = null;

if ($id_lote > 0 && $id_sucursal > 0) {
    $lote = evaluarLoteDetalle($conexion, $id_lote, $id_sucursal, $umbrales);
    if (!$lote) {
        $error = 'No se encontró stock de ese lote en esa sucursal.';
    }
} else {
    $error = 'Falta indicar el lote y la sucursal (id_lote / id_sucursal).';
}

// Sucursales para el campo "Sucursal aplicable"
$sucursales = [];
try {
    $stmt = $conexion->query("SELECT id_sucursal, nombre FROM sucursales WHERE estado = true ORDER BY nombre");
    $sucursales = $stmt->fetchAll();
} catch (PDOException $e) {}

// Usuarios responsables (Administrador / Encargado Inventario)
$responsables = [];
try {
    $stmt = $conexion->query("
        SELECT u.id_usuario, u.nombre, r.nombre AS rol
        FROM usuarios u
        JOIN roles r ON u.id_rol = r.id_rol
        WHERE u.estado = true AND r.nombre IN ('Administrador', 'Encargado Inventario')
        ORDER BY u.nombre
    ");
    $responsables = $stmt->fetchAll();
} catch (PDOException $e) {}
?>

<div class="container-fluid">
    <div class="mb-4">
        <nav class="tarea5-breadcrumb" aria-label="breadcrumb">
            <a href="menuprincipal.php?mod=vencimientos" class="tarea5-breadcrumb-home" title="Vencimientos">
                <span class="material-symbols-rounded">home</span>
            </a>
            <?php if (!$error): ?>
                <span class="tarea5-breadcrumb-sep material-symbols-rounded">chevron_right</span>
                <a href="menuprincipal.php?mod=detalle_riesgo_lote&id_lote=<?php echo $id_lote; ?>&id_sucursal=<?php echo $id_sucursal; ?>" class="tarea5-breadcrumb-link">Detalle de lote</a>
            <?php endif; ?>
            <span class="tarea5-breadcrumb-sep material-symbols-rounded">chevron_right</span>
            <span class="tarea5-breadcrumb-actual">Generar acción de recuperación</span>
        </nav>
        <h2 class="mb-0">
            <span class="material-symbols-rounded align-middle me-2 text-primary">bolt</span>
            Generar acción de recuperación
        </h2>
        <?php if (!$error): ?>
            <p class="text-muted mb-0">
                Lote <code><?php echo htmlspecialchars($lote['numero_lote']); ?></code> ·
                <?php echo htmlspecialchars($lote['medicamento_nombre']); ?> ·
                Valor en riesgo <strong class="text-danger">RD$ <?php echo number_format($lote['valor_en_riesgo'], 2); ?></strong>
            </p>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><span class="material-symbols-rounded align-middle me-1">error</span> <?php echo htmlspecialchars($error); ?></div>
        <a href="menuprincipal.php?mod=vencimientos" class="btn btn-outline-secondary">Volver a Vencimientos</a>
    <?php else: ?>

    <form id="formAccionRecuperacion">
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body">
                <label class="form-label fw-bold text-secondary small">SELECCIONE EL TIPO DE ACCIÓN</label>
                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <div class="tile-accion" data-tipo="PROMOCION" onclick="seleccionarTipo('PROMOCION')">
                            <div class="tile-icon bg-primary bg-opacity-10 text-primary"><span class="material-symbols-rounded">sell</span></div>
                            <div class="fw-semibold mb-1">Promoción</div>
                            <small class="text-muted">Aplicar descuento comercial para acelerar la rotación en punto de venta.</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="tile-accion" data-tipo="REDISTRIBUCION" onclick="seleccionarTipo('REDISTRIBUCION')">
                            <div class="tile-icon bg-info bg-opacity-10 text-info"><span class="material-symbols-rounded">swap_horiz</span></div>
                            <div class="fw-semibold mb-1">Redistribución</div>
                            <small class="text-muted">Transferir el lote a una sucursal con mayor probabilidad de rotación.</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="tile-accion" data-tipo="DEVOLUCION_PROVEEDOR" onclick="seleccionarTipo('DEVOLUCION_PROVEEDOR')">
                            <div class="tile-icon bg-secondary bg-opacity-10 text-secondary"><span class="material-symbols-rounded">assignment_return</span></div>
                            <div class="fw-semibold mb-1">Devolución</div>
                            <small class="text-muted">Gestionar la devolución del lote directamente con el proveedor.</small>
                        </div>
                    </div>
                </div>
                <?php if ($lote['sin_demanda_en_red']): ?>
                    <div class="alert alert-warning mt-3 mb-0 small">
                        <span class="material-symbols-rounded align-middle me-1" style="font-size:18px;">warning</span>
                        Ninguna sucursal (incluida esta) cumple el umbral mínimo de rotación para este medicamento.
                        La opción "Redistribución" probablemente no resuelva el problema —
                        <a href="menuprincipal.php?mod=diagnostico_causa_raiz&id_lote=<?php echo $id_lote; ?>&id_sucursal=<?php echo $id_sucursal; ?>">ver diagnóstico de causa raíz</a>.
                    </div>
                <?php elseif ($lote['sin_datos_en_red']): ?>
                    <div class="alert alert-secondary mt-3 mb-0 small">
                        <span class="material-symbols-rounded align-middle me-1" style="font-size:18px;">info</span>
                        No hay historial de ventas suficiente para evaluar la rotación de este medicamento en ninguna sucursal todavía.
                    </div>
                <?php endif; ?>

                <!-- NUEVO: datos de la solicitud de devolución. Solo se muestra
                     al elegir "Devolución"; el proveedor se resuelve contra la
                     compra de origen real del lote (no se elige libremente) y
                     el motivo se limita a lo que ese proveedor tiene pactado. -->
                <div id="panelDevolucion" class="mt-3" style="display:none;">
                    <hr>
                    <label class="form-label fw-bold text-secondary small">DATOS DE LA SOLICITUD DE DEVOLUCIÓN</label>
                    <div id="panelDevolucionCargando" class="text-muted small">
                        <span class="spinner-border spinner-border-sm me-1"></span> Consultando proveedor de origen del lote...
                    </div>
                    <div id="panelDevolucionError" class="alert alert-danger small mb-0" style="display:none;"></div>
                    <div id="panelDevolucionContenido" class="row g-3" style="display:none;">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">PROVEEDOR DE ORIGEN</label>
                            <select class="form-select" id="selectProveedorDevolucion"></select>
                            <small class="text-muted">Derivado de la compra que originó este lote.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">MOTIVO DE LA DEVOLUCIÓN</label>
                            <select class="form-select" id="selectMotivoDevolucion" required>
                                <option value="">Seleccione...</option>
                            </select>
                            <small class="text-muted">Solo se listan los motivos pactados con ese proveedor.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label fw-bold text-secondary small">CANTIDAD A INCLUIR EN LA ACCIÓN</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="cantidadAfectada" min="1" max="<?php echo $lote['cantidad']; ?>" value="<?php echo $lote['cantidad']; ?>" required>
                            <span class="input-group-text">de <?php echo $lote['cantidad']; ?> u.</span>
                        </div>
                        <small class="text-muted" id="valorRiesgoParcial">Valor en riesgo para esta cantidad: RD$ <?php echo number_format($lote['valor_en_riesgo'], 2); ?></small>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label fw-bold text-secondary small">RESPONSABLE</label>
                        <select class="form-select" id="responsable" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($responsables as $r): ?>
                                <option value="<?php echo $r['id_usuario']; ?>"><?php echo htmlspecialchars($r['nombre']); ?> (<?php echo htmlspecialchars($r['rol']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label fw-bold text-secondary small">FECHA LÍMITE DE EJECUCIÓN</label>
                        <input type="date" class="form-control" id="fechaLimite" required>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label fw-bold text-secondary small">SUCURSAL APLICABLE</label>
                        <select class="form-select" id="sucursalAplicable">
                            <?php foreach ($sucursales as $s): ?>
                                <option value="<?php echo $s['id_sucursal']; ?>" <?php echo $s['id_sucursal'] == $id_sucursal ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label fw-bold text-secondary small">PRIORIDAD DE EJECUCIÓN</label>
                        <select class="form-select" id="prioridad">
                            <option value="MEDIA">Media</option>
                            <option value="ALTA" selected>Alta</option>
                            <option value="CRITICA">Crítica</option>
                            <option value="BAJA">Baja</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold text-secondary small">OBSERVACIONES</label>
                        <textarea class="form-control" id="observaciones" rows="2" placeholder="Ej. Lote con baja rotación en los últimos <?php echo $umbrales['venc_irv_periodo_dias']; ?> días; se recomienda aplicar descuento inmediato."></textarea>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary" id="btnGuardar">
                        <span class="material-symbols-rounded align-middle me-1" style="font-size:18px;">save</span>
                        Guardar acción
                    </button>
                    <a href="menuprincipal.php?mod=detalle_riesgo_lote&id_lote=<?php echo $id_lote; ?>&id_sucursal=<?php echo $id_sucursal; ?>" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </div>
        </div>
    </form>

    <?php endif; ?>
</div>

<style>
.tile-accion {
    border: 1.5px solid #dee2e6;
    border-radius: 14px;
    padding: 16px;
    cursor: pointer;
    height: 100%;
    transition: all 0.15s ease;
}
.tile-accion:hover { border-color: #86b7fe; }
.tile-accion.selected { border-color: #0d6efd; background: rgba(13,110,253,0.05); box-shadow: 0 0 0 1px #0d6efd; }
.tile-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';
const idLote = <?php echo $id_lote; ?>;
const idSucursal = <?php echo $id_sucursal; ?>;
<?php if (!$error): ?>
const loteInfo = {
    cantidad: <?php echo $lote['cantidad']; ?>,
    costo_unitario: <?php echo $lote['costo_unitario']; ?>,
    valor_en_riesgo: <?php echo $lote['valor_en_riesgo']; ?>,
    nivel_riesgo: '<?php echo $lote['nivel_riesgo']; ?>',
    irv_origen: <?php echo $lote['irv_origen'] === null ? 'null' : $lote['irv_origen']; ?>
};
<?php endif; ?>

let tipoAccionSeleccionado = 'PROMOCION'; // Promoción viene preseleccionada, igual que en el mockup aprobado
let datosDevolucion = { proveedores: [], cargado: false, valido: false };

function seleccionarTipo(tipo) {
    tipoAccionSeleccionado = tipo;
    document.querySelectorAll('.tile-accion').forEach(t => t.classList.remove('selected'));
    document.querySelector(`.tile-accion[data-tipo="${tipo}"]`).classList.add('selected');

    const panel = document.getElementById('panelDevolucion');
    if (tipo === 'DEVOLUCION_PROVEEDOR') {
        panel.style.display = 'block';
        if (!datosDevolucion.cargado) cargarProveedorDevolucion();
    } else {
        panel.style.display = 'none';
    }
}

function cargarProveedorDevolucion() {
    const elCargando = document.getElementById('panelDevolucionCargando');
    const elError = document.getElementById('panelDevolucionError');
    const elContenido = document.getElementById('panelDevolucionContenido');
    elCargando.style.display = 'block';
    elError.style.display = 'none';
    elContenido.style.display = 'none';

    fetch(`${BASE_URL}/backend/inventario/obtener_proveedor_devolucion.php?id_lote=${idLote}`)
        .then(r => r.json())
        .then(data => {
            elCargando.style.display = 'none';
            datosDevolucion.cargado = true;

            if (!data.success) {
                datosDevolucion.valido = false;
                elError.textContent = data.message;
                elError.style.display = 'block';
                return;
            }

            datosDevolucion.proveedores = data.proveedores;
            datosDevolucion.valido = true;

            const selectProv = document.getElementById('selectProveedorDevolucion');
            selectProv.innerHTML = data.proveedores.map(p => `<option value="${p.id_proveedor}">${p.nombre}</option>`).join('');
            selectProv.onchange = actualizarMotivosDevolucion;
            elContenido.style.display = 'flex';
            actualizarMotivosDevolucion();
        })
        .catch(() => {
            elCargando.style.display = 'none';
            datosDevolucion.valido = false;
            elError.textContent = 'No se pudo consultar el proveedor de origen del lote. Intente de nuevo.';
            elError.style.display = 'block';
        });
}

function actualizarMotivosDevolucion() {
    const idProv = parseInt(document.getElementById('selectProveedorDevolucion').value);
    const prov = datosDevolucion.proveedores.find(p => p.id_proveedor === idProv);
    const selectMotivo = document.getElementById('selectMotivoDevolucion');

    if (!prov || prov.motivos.length === 0) {
        selectMotivo.innerHTML = '<option value="">Este proveedor no tiene motivos de devolución pactados</option>';
        return;
    }
    selectMotivo.innerHTML = '<option value="">Seleccione...</option>' +
        prov.motivos.map(m => `<option value="${m.id_motivo}" title="${m.descripcion}">${m.nombre.replaceAll('_', ' ')}</option>`).join('');
}

document.addEventListener('DOMContentLoaded', function() {
    seleccionarTipo('PROMOCION');

    // Fecha límite por defecto: hoy + 5 días
    const fecha = new Date();
    fecha.setDate(fecha.getDate() + 5);
    document.getElementById('fechaLimite').value = fecha.toISOString().split('T')[0];

    // Recalcular el valor en riesgo en vivo según la cantidad que se decida incluir
    const inputCantidad = document.getElementById('cantidadAfectada');
    inputCantidad.addEventListener('input', actualizarValorRiesgoParcial);
    actualizarValorRiesgoParcial();
});

function actualizarValorRiesgoParcial() {
    const inputCantidad = document.getElementById('cantidadAfectada');
    let cantidad = parseInt(inputCantidad.value) || 0;

    if (cantidad > loteInfo.cantidad) {
        cantidad = loteInfo.cantidad;
        inputCantidad.value = cantidad;
    }

    const valorParcial = cantidad * loteInfo.costo_unitario;
    document.getElementById('valorRiesgoParcial').textContent =
        `Valor en riesgo para esta cantidad: RD$ ${valorParcial.toLocaleString('es-DO', { minimumFractionDigits: 2 })}`;
}

document.getElementById('formAccionRecuperacion')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const cantidadAfectada = parseInt(document.getElementById('cantidadAfectada').value) || 0;
    if (cantidadAfectada < 1 || cantidadAfectada > loteInfo.cantidad) {
        Swal.fire('Cantidad inválida', `Debe ser un número entre 1 y ${loteInfo.cantidad} (existencia disponible).`, 'warning');
        return;
    }

    let idProveedorDevolucion = null;
    let idMotivoDevolucion = null;

    if (tipoAccionSeleccionado === 'DEVOLUCION_PROVEEDOR') {
        if (!datosDevolucion.valido) {
            Swal.fire('No se puede continuar', 'Este lote no tiene un proveedor de origen válido para tramitar una devolución.', 'warning');
            return;
        }
        idProveedorDevolucion = parseInt(document.getElementById('selectProveedorDevolucion').value) || null;
        idMotivoDevolucion = parseInt(document.getElementById('selectMotivoDevolucion').value) || null;
        if (!idProveedorDevolucion || !idMotivoDevolucion) {
            Swal.fire('Falta información', 'Seleccione el proveedor y el motivo de la devolución.', 'warning');
            return;
        }
    }

    const btn = document.getElementById('btnGuardar');
    btn.disabled = true;

    const valorEnRiesgoParcial = Math.round(cantidadAfectada * loteInfo.costo_unitario * 100) / 100;

    const payload = {
        id_lote: idLote,
        id_sucursal_origen: idSucursal,
        id_sucursal_destino: tipoAccionSeleccionado === 'REDISTRIBUCION' ? null : null, // se define en la Pantalla #05
        tipo_accion: tipoAccionSeleccionado,
        cantidad_afectada: cantidadAfectada,
        valor_en_riesgo: valorEnRiesgoParcial,
        nivel_riesgo_al_generar: loteInfo.nivel_riesgo,
        irv_origen_al_generar: loteInfo.irv_origen,
        responsable: document.getElementById('responsable').value || null,
        fecha_limite: document.getElementById('fechaLimite').value || null,
        prioridad: document.getElementById('prioridad').value,
        observaciones: document.getElementById('observaciones').value || null,
        id_proveedor: idProveedorDevolucion,
        id_motivo: idMotivoDevolucion
    };

    fetch(`${BASE_URL}/backend/inventario/gestionar_accion_recuperacion.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            if (data.success) {
                if (tipoAccionSeleccionado === 'REDISTRIBUCION') {
                    // Va directo a elegir la sucursal destino (Pantalla #05),
                    // ya con la acción creada y lista para enlazarse.
                    window.location.href = `menuprincipal.php?mod=redistribuir_stock_riesgo&id_lote=${idLote}&id_sucursal=${idSucursal}&id_accion=${data.id_accion}`;
                    return;
                }

                if (tipoAccionSeleccionado === 'PROMOCION') {
                    // Va directo al módulo de Ofertas, precargado con el
                    // contexto del lote (Pantalla #06).
                    window.location.href = `menuprincipal.php?mod=ofertas&id_accion=${data.id_accion}`;
                    return;
                }

                if (tipoAccionSeleccionado === 'DEVOLUCION_PROVEEDOR') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Solicitud enviada al proveedor',
                        html: `${data.message}<br><small class="text-muted">La acción quedó como <strong>ESPERANDO_PROVEEDOR</strong>. El inventario no se descuenta hasta que se registre la respuesta.</small>`,
                    }).then(() => {
                        window.location.href = `menuprincipal.php?mod=detalle_riesgo_lote&id_lote=${idLote}&id_sucursal=${idSucursal}`;
                    });
                    return;
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Acción registrada',
                    html: `La acción de recuperación #${data.id_accion} quedó registrada como <strong>PENDIENTE</strong>.`,
                }).then(() => {
                    window.location.href = `menuprincipal.php?mod=detalle_riesgo_lote&id_lote=${idLote}&id_sucursal=${idSucursal}`;
                });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            Swal.fire('Error', 'Error de conexión con el servidor', 'error');
        });
});
</script>
