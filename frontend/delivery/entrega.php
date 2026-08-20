<?php
// frontend/delivery/entrega.php — se carga DENTRO de menuprincipal.php
require_once __DIR__ . '/../../backend/conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$base_url = '/sistema-gestor-de-farmacias';
?>
<style>
  .card-d{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);}
  .chip{border:1.5px solid #dee2e6;border-radius:20px;padding:.35rem .9rem;font-size:.78rem;font-weight:600;cursor:pointer;background:#fff;color:#555;white-space:nowrap;}
  .chip.active{background:#28a745;border-color:#28a745;color:#fff;}
  table thead th{background:#28a745;color:#fff;font-size:.78rem;font-weight:600;border:none;padding:.6rem .8rem;}
  table tbody td{font-size:.8rem;vertical-align:middle;padding:.55rem .8rem;}
  .badge-st{font-size:.68rem;padding:.32em .6em;border-radius:20px;font-weight:700;}
  .st-PENDIENTE{background:#EAF1F8;color:#1565C0;}
  .st-ASIGNADA{background:#E0E7FF;color:#4338CA;}
  .st-EN_CAMINO{background:#FFF3CD;color:#C77B00;}
  .st-ENTREGADA{background:#D4EDDA;color:#2E8B57;}
  .st-INTERRUMPIDA{background:#FDEAEA;color:#DC3545;}
  .st-PARCIAL{background:#FFE8CC;color:#B35C00;}
  .st-CANCELADA,.st-FALLIDA{background:#F5F5F5;color:#888;}
  .st-DEVUELTA{background:#F8D7DA;color:#842029;}
  .st-REPROGRAMADA{background:#E9D8FD;color:#6B21A8;}
  .stat-entrega{border-radius:14px;padding:1.1rem 1.3rem;border-left:5px solid;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;align-items:center;justify-content:space-between;}
  .stat-entrega .v{font-size:1.9rem;font-weight:700;line-height:1;}
  .stat-entrega .l{font-size:.82rem;color:#666;margin-top:.2rem;}
  .stat-entrega .material-symbols-rounded{font-size:2.1rem;opacity:.8;}
  .stat-pendientes{border-color:#1565C0;} .stat-pendientes .v,.stat-pendientes .material-symbols-rounded{color:#1565C0;}
  .stat-camino{border-color:#C77B00;} .stat-camino .v,.stat-camino .material-symbols-rounded{color:#C77B00;}
  .stat-entregadas{border-color:#2E8B57;} .stat-entregadas .v,.stat-entregadas .material-symbols-rounded{color:#2E8B57;}
  .stat-problemas{border-color:#DC3545;} .stat-problemas .v,.stat-problemas .material-symbols-rounded{color:#DC3545;}
</style>

<div class="container-fluid p-0">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
      <h2 class="mb-0 text-success"><span class="material-symbols-rounded align-middle me-2">local_shipping</span> Entrega</h2>
      <p class="text-muted mb-3">Asigna, reasigna y da seguimiento a las entregas a domicilio en curso</p>
    </div>
    <button type="button" class="btn btn-outline-success" onclick="cargar()" title="Refrescar">
      <span class="material-symbols-rounded align-middle">refresh</span> Refrescar
    </button>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
      <div class="stat-entrega stat-pendientes">
        <div><div class="v" id="statPendientes">—</div><div class="l">Pendientes</div></div>
        <span class="material-symbols-rounded">schedule</span>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-entrega stat-camino">
        <div><div class="v" id="statCamino">—</div><div class="l">En camino</div></div>
        <span class="material-symbols-rounded">two_wheeler</span>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-entrega stat-entregadas">
        <div><div class="v" id="statEntregadas">—</div><div class="l">Entregadas</div></div>
        <span class="material-symbols-rounded">task_alt</span>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-entrega stat-problemas">
        <div><div class="v" id="statProblemas">—</div><div class="l">Con problemas</div></div>
        <span class="material-symbols-rounded">report</span>
      </div>
    </div>
  </div>

  <div class="card-d p-3 mb-3">
    <label class="form-label small fw-bold text-muted mb-2">FILTRAR POR ESTADO</label>
    <div class="d-flex flex-wrap gap-2 mb-3" id="chipsContainer"></div>
    <div class="d-flex flex-column flex-md-row gap-2">
      <input type="text" id="buscador" class="form-control" placeholder="Buscar por documento, cliente o número de seguimiento..." oninput="cargar()">
      <select id="filtroMotivo" class="form-select" style="max-width:280px;" onchange="cargar()">
        <option value="">Todos los motivos (fallidas)</option>
      </select>
    </div>
  </div>

  <div class="card-d p-2">
    <div class="table-responsive" id="tabla"><div class="text-center py-4"><div class="spinner-border text-success"></div></div></div>
  </div>
</div>

<!-- MODAL: cambiar estado -->
<div class="modal fade" id="modalEstado" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:#28a745;color:#fff;">
        <h6 class="modal-title mb-0">Actualizar estado de entrega</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="meIdEntrega">
        <label class="form-label fw-semibold small">Nuevo estado</label>
        <select class="form-select mb-3" id="meEstado" onchange="toggleCamposEstado()">
          <option value="ASIGNADA">Asignada (revertir para poder registrar despacho)</option>
          <option value="EN_CAMINO">En camino</option>
          <option value="ENTREGADA">Entregada</option>
          <option value="PARCIAL">Parcial (se entregó solo una parte)</option>
          <option value="INTERRUMPIDA">Interrumpida (accidente, robo, etc.)</option>
          <option value="REPROGRAMADA">Reprogramada</option>
          <option value="CANCELADA">Cancelada</option>
          <option value="FALLIDA">Fallida</option>
        </select>
        <div id="campoMotivo" style="display:none;">
          <label class="form-label fw-semibold small">Motivo de la interrupción</label>
          <textarea class="form-control mb-3" id="meMotivo" rows="2" placeholder="Ej: Accidente de tránsito en el camino, repartidor ileso, pedido perdido."></textarea>
        </div>
        <div id="campoParcial" style="display:none;">
          <label class="form-label fw-semibold small">¿Qué se entregó y qué no?</label>
          <textarea class="form-control mb-3" id="meDetalleParcial" rows="2" placeholder="Ej: Se entregaron 2 de 3 productos. Faltó Jarabe Expectorante por rotura del frasco."></textarea>
        </div>
        <div id="campoFallida" style="display:none;">
          <label class="form-label fw-semibold small">Motivo</label>
          <select class="form-select mb-2" id="meMotivoFallida">
            <option value="">Cargando motivos...</option>
          </select>
          <label class="form-label fw-semibold small">Detalle adicional (opcional)</label>
          <textarea class="form-control mb-3" id="meDetalleFallida" rows="2" placeholder="Información adicional"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success" onclick="guardarEstado()">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: ver detalle -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:#28a745;color:#fff;">
        <h6 class="modal-title mb-0">Detalle de la entrega</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detalleBody">
        <div class="text-center py-4"><div class="spinner-border text-success"></div></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: asignar / reasignar repartidor -->
<div class="modal fade" id="modalAsignar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:#28a745;color:#fff;">
        <h6 class="modal-title mb-0"><span id="maTitulo">Asignar</span> — <span id="maNumero"></span></h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="maMotivoBloque" class="mb-3" style="display:none;">
          <label class="form-label fw-bold small text-muted">Motivo de la reasignación <span class="text-danger">*</span></label>
          <textarea id="maMotivo" class="form-control" rows="2" placeholder="Ej: El repartidor se reportó enfermo a mitad de ruta"></textarea>
        </div>
        <label class="form-label fw-bold text-muted small">PASO 1 — ELEGIR REPARTIDOR *</label>
        <div id="maListaRepartidores" class="d-flex flex-column gap-2 mb-3">
          <p class="text-muted small">Cargando...</p>
        </div>
        <div id="maPasoVehiculo" style="display:none;">
          <label class="form-label fw-bold text-muted small">PASO 2 — ELEGIR VEHÍCULO *</label>
          <div id="maListaVehiculos" class="d-flex flex-wrap"></div>
        </div>
        <input type="hidden" id="maIdEntrega">
        <input type="hidden" id="maIdRepartidor">
        <input type="hidden" id="maIdVehiculo">
        <input type="hidden" id="maEsCola">
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success" onclick="confirmarAsignacion()">Confirmar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: registrar despacho (P-2) -->
<div class="modal fade" id="modalDespacho" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:#28a745;color:#fff;">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">inventory</span> Registrar Despacho</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="despachoBody">
        <div class="text-center py-4"><div class="spinner-border text-success"></div></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal"><span class="material-symbols-rounded align-middle" style="font-size:1rem;">close</span> Cancelar</button>
        <button class="btn btn-success" onclick="confirmarDespacho()"><span class="material-symbols-rounded align-middle" style="font-size:1rem;">check</span> Confirmar Despacho</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: conciliación y validación (P-4) -->
<div class="modal fade" id="modalConciliacion" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:#28a745;color:#fff;">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">fact_check</span> Conciliación de Entrega</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="conciliacionBody">
        <div class="text-center py-4"><div class="spinner-border text-success"></div></div>
      </div>
      <div class="modal-footer" id="conciliacionFooter"></div>
    </div>
  </div>
</div>

<style>
  .tarjeta-repartidor{border:1.5px solid #dee2e6;border-radius:10px;padding:.7rem .9rem;cursor:pointer;transition:all .15s;}
  .tarjeta-repartidor:hover{border-color:#28a745;background:#f0fff4;}
  .tarjeta-repartidor.selected{border-color:#28a745;background:#eafaf0;box-shadow:0 0 0 2px rgba(40,167,69,.25);}
  .hab-mini{background:#EAF1F8;color:#1e7e34;border-radius:12px;padding:.15em .55em;font-size:.68rem;font-weight:600;margin-right:.25rem;}
  .tabla-despacho th{background:#28a745;color:#fff;font-size:.75rem;padding:.5rem .7rem;}
  .tabla-despacho td{font-size:.82rem;vertical-align:middle;padding:.5rem .7rem;}
  .badge-correcto{background:#D4EDDA;color:#2E8B57;border-radius:20px;padding:.3em .7em;font-size:.72rem;font-weight:700;}
  .badge-diferencia{background:#FDEAEA;color:#DC3545;border-radius:20px;padding:.3em .7em;font-size:.72rem;font-weight:700;}
</style>

<script>
const BASE_URL = '<?php echo $base_url; ?>';
let modalEstado, modalDetalle, modalAsignar, modalDespacho, modalConciliacion, filtroActual = '';
document.addEventListener('DOMContentLoaded', () => {
  ['modalEstado', 'modalDetalle', 'modalAsignar', 'modalDespacho', 'modalConciliacion'].forEach(id => {
    const el = document.getElementById(id);
    if (el && el.parentElement !== document.body) document.body.appendChild(el);
  });
  modalEstado = new bootstrap.Modal('#modalEstado');
  modalDetalle = new bootstrap.Modal('#modalDetalle');
  modalAsignar = new bootstrap.Modal('#modalAsignar');
  modalDespacho = new bootstrap.Modal('#modalDespacho');
  modalConciliacion = new bootstrap.Modal('#modalConciliacion');
  cargarFiltroMotivos();
  cargar();
});

function abrirModalAsignar(idEntrega, numeroSeg, esCola) {
  document.getElementById('maIdEntrega').value = idEntrega;
  document.getElementById('maIdRepartidor').value = '';
  document.getElementById('maIdVehiculo').value = '';
  document.getElementById('maEsCola').value = esCola ? '1' : '0';
  document.getElementById('maNumero').textContent = numeroSeg;
  document.getElementById('maTitulo').textContent = esCola ? 'Asignar' : 'Reasignar';
  document.getElementById('maMotivoBloque').style.display = esCola ? 'none' : 'block';
  document.getElementById('maMotivo').value = '';
  document.getElementById('maPasoVehiculo').style.display = 'none';
  document.getElementById('maListaVehiculos').innerHTML = '';
  document.getElementById('maListaRepartidores').innerHTML = '<p class="text-muted small">Cargando repartidores disponibles...</p>';
  modalAsignar.show();

  fetch(BASE_URL + '/backend/ventas/listar_repartidores_disponibles.php').then(r=>r.json()).then(data=>{
    const cont = document.getElementById('maListaRepartidores');
    if (!data.success || !data.repartidores.length) {
      cont.innerHTML = '<p class="text-danger small mb-0">No hay repartidores disponibles en este momento.</p>';
      return;
    }
    cont.innerHTML = data.repartidores.map(rep => {
      const habs = (rep.habilidades||[]).map(h => `<span class="hab-mini">${h.tipo_vehiculo}</span>`).join('') || '<span class="text-muted" style="font-size:.72rem;">Sin habilidades</span>';
      return `<div class="tarjeta-repartidor" id="maTarjeta-${rep.id_repartidor}" onclick="maElegirRepartidor(${rep.id_repartidor})">
        <strong>${rep.nombre}</strong> ${rep.telefono ? `<small class="text-muted">(${rep.telefono})</small>` : ''}
        <div class="mt-1">${habs}</div>
      </div>`;
    }).join('');
  });
}

function maElegirRepartidor(id) {
  document.querySelectorAll('.tarjeta-repartidor').forEach(t => t.classList.remove('selected'));
  document.getElementById('maTarjeta-'+id).classList.add('selected');
  document.getElementById('maIdRepartidor').value = id;
  document.getElementById('maIdVehiculo').value = '';

  const pasoVeh = document.getElementById('maPasoVehiculo');
  pasoVeh.style.display = 'block';
  document.getElementById('maListaVehiculos').innerHTML = '<p class="text-muted small">Cargando vehículos...</p>';

  fetch(BASE_URL + `/backend/ventas/listar_vehiculos_para_repartidor.php?id_repartidor=${id}`).then(r=>r.json()).then(data=>{
    if (!data.success || !data.vehiculos.length) {
      document.getElementById('maListaVehiculos').innerHTML = '<p class="text-danger small mb-0">Este repartidor no tiene ningún vehículo disponible ahora mismo.</p>';
      return;
    }
    document.getElementById('maListaVehiculos').innerHTML = data.vehiculos.map(v => `
      <button type="button" class="btn btn-sm btn-outline-secondary me-2 mb-2" onclick="maElegirVehiculo(event, ${v.id_vehiculo})">
        ${v.tipo}${v.placa ? ' ('+v.placa+')' : ''}
      </button>`).join('');
  });
}

function maElegirVehiculo(evt, idVehiculo) {
  document.querySelectorAll('#maListaVehiculos button').forEach(b => { b.classList.remove('btn-success'); b.classList.add('btn-outline-secondary'); });
  evt.currentTarget.classList.remove('btn-outline-secondary');
  evt.currentTarget.classList.add('btn-success');
  document.getElementById('maIdVehiculo').value = idVehiculo;
}

function confirmarAsignacion() {
  const id_entrega = document.getElementById('maIdEntrega').value;
  const id_repartidor = document.getElementById('maIdRepartidor').value;
  const id_vehiculo = document.getElementById('maIdVehiculo').value;
  const esCola = document.getElementById('maEsCola').value === '1';
  const motivo_reasignacion = document.getElementById('maMotivo').value.trim();

  if (!id_repartidor || !id_vehiculo) {
    Swal.fire('Falta información', 'Selecciona un repartidor y su vehículo.', 'warning');
    return;
  }
  if (!esCola && !motivo_reasignacion) {
    Swal.fire('Falta información', 'Indica el motivo de la reasignación.', 'warning');
    return;
  }

  fetch(BASE_URL + '/backend/delivery/asignar_entrega_manual.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({
      id_entrega: parseInt(id_entrega), id_repartidor: parseInt(id_repartidor),
      id_vehiculo: parseInt(id_vehiculo), motivo_reasignacion
    })
  }).then(r=>r.json()).then(data=>{
    if (data.success) {
      modalAsignar.hide();
      Swal.fire({title: data.fue_reasignacion ? '¡Reasignado!' : '¡Asignado!', icon:'success', timer:1500, showConfirmButton:false});
      cargar();
    } else {
      Swal.fire('Error', data.message, 'error');
    }
  }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
}
function cargarFiltroMotivos() {
  fetch(BASE_URL + '/backend/delivery/listar_motivos_fallida.php')
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      const sel = document.getElementById('filtroMotivo');
      sel.innerHTML = '<option value="">Todos los motivos (fallidas)</option>' +
        data.motivos.map(m => `<option value="${m.id_motivo}">${m.nombre}</option>`).join('');
    });
}

function escapeHtml(str) { if (!str) return ''; return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m])); }

const ESTADO_LABEL = {
  PENDIENTE:'Pendiente', ASIGNADA:'Asignada', EN_CAMINO:'En camino', ENTREGADA:'Entregada',
  INTERRUMPIDA:'Interrumpida', PARCIAL:'Parcial', CANCELADA:'Cancelada',
  REPROGRAMADA:'Reprogramada', FALLIDA:'Fallida', DEVUELTA:'Devolución',
};

function verDetalle(id) {
  document.getElementById('detalleBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success"></div></div>';
  modalDetalle.show();

  fetch(BASE_URL + `/backend/delivery/get_detalle_entrega.php?id_entrega=${id}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        document.getElementById('detalleBody').innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        return;
      }
      const e = data.entrega;
      const prods = data.productos || [];

      let extraHtml = '';
      if (e.estado_nombre === 'ENTREGADA') {
        extraHtml = `
          <div class="alert alert-success py-2 px-3 mb-3" style="font-size:.85rem;">
            <strong>Entregado a:</strong> ${e.nombre_quien_recibe || '—'}
            ${e.identificacion_quien_recibe ? ' (Cédula: ' + e.identificacion_quien_recibe + ')' : ''}
            <br><strong>Fecha de entrega:</strong> ${e.fecha_entrega_real ? new Date(e.fecha_entrega_real).toLocaleString('es-DO') : '—'}
            ${e.estado_recepcion === 'EN_DISPUTA' ? `<br><span class="text-danger"><strong>⚠ En disputa:</strong> ${e.comentario_cliente ? escapeHtml(e.comentario_cliente) : 'la cédula no coincide con el receptor autorizado, o el cliente reporta que no recibió el pedido'}</span>` : ''}
          </div>`;
      } else if (e.estado_nombre === 'INTERRUMPIDA') {
        extraHtml = `<div class="alert alert-danger py-2 px-3 mb-3" style="font-size:.85rem;"><strong>Motivo de interrupción:</strong> ${e.motivo_interrupcion || '—'}</div>`;
      } else if (e.estado_nombre === 'PARCIAL') {
        extraHtml = `<div class="alert alert-warning py-2 px-3 mb-3" style="font-size:.85rem;"><strong>Detalle de entrega parcial:</strong> ${e.detalle_parcial || '—'}</div>`;
      } else if (e.estado_nombre === 'FALLIDA') {
        extraHtml = `<div class="alert alert-secondary py-2 px-3 mb-3" style="font-size:.85rem;"><strong>Motivo:</strong> ${e.motivo_fallida_nombre || '—'}${e.observaciones ? '<br><strong>Detalle:</strong> ' + e.observaciones.replace(e.motivo_fallida_nombre + ' — ', '') : ''}</div>`;
      } else if (e.estado_nombre === 'DEVUELTA') {
        extraHtml = `<div class="alert alert-danger py-2 px-3 mb-3" style="font-size:.85rem;"><strong>Devolución del cliente:</strong> ${e.detalle_parcial || 'El cliente devolvió todo el pedido.'}<br><span class="text-muted">Revisa el detalle en Inventario &gt; Devoluciones.</span><br><button class="btn btn-sm btn-outline-danger mt-2" onclick="modalDetalle.hide(); reabrirEntregaDevuelta(${e.id_entrega})"><span class="material-symbols-rounded align-middle" style="font-size:1rem;">restart_alt</span> Reabrir para redespachar</button></div>`;
      }

      const prodsHtml = prods.length
        ? prods.map(p => `<li>${p.producto_nombre || 'Producto'} — ${p.cantidad} uds. ${p.numero_lote ? '(Lote ' + p.numero_lote + ')' : ''}</li>`).join('')
        : '<li class="text-muted">Sin productos registrados</li>';

      document.getElementById('detalleBody').innerHTML = `
        ${extraHtml}
        <div class="row g-2" style="font-size:.85rem;">
          <div class="col-6"><strong>Seguimiento:</strong> ${e.numero_seguimiento}</div>
          <div class="col-6"><strong>Estado:</strong> ${ESTADO_LABEL[e.estado_nombre] || e.estado_nombre}</div>
          <div class="col-6"><strong>Cliente:</strong> ${e.cliente_nombre}</div>
          <div class="col-6"><strong>Repartidor:</strong> ${e.repartidor_nombre || 'Sin asignar'}</div>
          <div class="col-6"><strong>Vehículo:</strong> ${e.vehiculo_tipo ? e.vehiculo_tipo + (e.vehiculo_placa ? ' ('+e.vehiculo_placa+')' : '') : '—'}</div>
          <div class="col-6"><strong>Costo envío:</strong> RD$ ${parseFloat(e.costo_entrega||0).toFixed(2)}</div>
          <div class="col-12"><strong>Dirección:</strong> ${e.direccion_entrega}${e.barrio_entrega ? ', ' + e.barrio_entrega : ''}${e.ciudad_entrega ? ', ' + e.ciudad_entrega : ''}</div>
          <div class="col-6"><strong>Fecha pedido:</strong> ${e.fecha_pedido ? new Date(e.fecha_pedido).toLocaleString('es-DO') : '—'}</div>
          <div class="col-6"><strong>Fecha asignada:</strong> ${e.fecha_asignada ? new Date(e.fecha_asignada).toLocaleString('es-DO') : '—'}</div>
        </div>
        <hr>
        <strong style="font-size:.85rem;">Productos:</strong>
        <ul style="font-size:.85rem;" class="mt-1">${prodsHtml}</ul>
      `;
    })
    .catch(() => {
      document.getElementById('detalleBody').innerHTML = '<div class="alert alert-danger">Error de conexión.</div>';
    });
}

const CHIPS = [
  ['', 'Todas'], ['EN_COLA','En cola'], ['PENDIENTE','Pendiente'], ['ASIGNADA','Asignada'], ['EN_CAMINO','En curso'],
  ['ENTREGADA','Entregada'], ['PARCIAL','Parcial'], ['DEVUELTA','Devolución'], ['INTERRUMPIDA','Interrumpida'],
  ['REPROGRAMADA','Reprogramada'], ['CANCELADA','Cancelada'], ['FALLIDA','Fallida'],
];

function renderChips(conteos) {
  const c = document.getElementById('chipsContainer');
  c.innerHTML = CHIPS.map(([val,lbl]) => {
    // 'EN_COLA' es un subconjunto de PENDIENTE, no se suma aparte en "Todas"
    const n = val === ''
      ? Object.entries(conteos||{}).filter(([k]) => k !== 'EN_COLA').reduce((a,[,v])=>a+v,0)
      : (conteos?.[val]||0);
    return `<span class="chip ${filtroActual===val?'active':''}" onclick="filtrar('${val}')">${lbl} (${n})</span>`;
  }).join('');
}
function filtrar(val) { filtroActual = val; cargar(); }

function renderStats(conteos) {
  const c = conteos || {};
  document.getElementById('statPendientes').textContent = (c.PENDIENTE||0) + (c.ASIGNADA||0);
  document.getElementById('statCamino').textContent = c.EN_CAMINO||0;
  document.getElementById('statEntregadas').textContent = c.ENTREGADA||0;
  document.getElementById('statProblemas').textContent = (c.INTERRUMPIDA||0) + (c.PARCIAL||0) + (c.FALLIDA||0) + (c.CANCELADA||0);
}

function cargar() {
  const q = document.getElementById('buscador').value;
  const motivo = document.getElementById('filtroMotivo')?.value || '';
  fetch(BASE_URL + `/backend/delivery/listar_entregas.php?estado=${encodeURIComponent(filtroActual)}&q=${encodeURIComponent(q)}&motivo=${encodeURIComponent(motivo)}`)
    .then(r => r.json()).then(data => {
      if (!data.success) { document.getElementById('tabla').innerHTML = '<div class="alert alert-danger m-3">'+data.message+'</div>'; return; }
      renderChips(data.conteos);
      renderStats(data.conteos);
      if (!data.entregas.length) {
        document.getElementById('tabla').innerHTML = '<div class="text-center text-muted py-5"><span class="material-symbols-rounded" style="font-size:3rem;">inbox</span><br>No hay entregas con este filtro.</div>';
        return;
      }
      const rows = data.entregas.map(e => {
        const puedeActualizar = !['ENTREGADA','CANCELADA','FALLIDA','DEVUELTA'].includes(e.estado_nombre);
        const enCola = e.en_cola === true || e.en_cola === 't' || e.en_cola === 1;
        const confirmadoCliente = e.confirmado_por_cliente === true || e.confirmado_por_cliente === 't' || e.confirmado_por_cliente === 1;
        return `<tr ${enCola ? 'style="background:#FFF9E6;"' : ''}>
          <td class="fw-semibold text-success">${e.numero_seguimiento}</td>
          <td>${e.numero_documento}</td>
          <td>${e.cliente_nombre}</td>
          <td>${e.repartidor_nombre || (enCola ? '<span class="text-warning fw-semibold"><span class="material-symbols-rounded" style="font-size:.9rem;">schedule</span> En cola</span>' : '<span class="text-muted">Sin asignar</span>')}</td>
          <td>${e.vehiculo_tipo ? e.vehiculo_tipo + (e.vehiculo_placa ? ' ('+e.vehiculo_placa+')' : '') : '—'}</td>
          <td class="text-center">
            <span class="badge-st st-${e.estado_nombre}">${enCola ? 'EN COLA' : e.estado_nombre.replace('_',' ')}</span>
            ${e.estado_nombre === 'FALLIDA' && e.motivo_fallida_nombre ? `<br><small class="text-muted">${e.motivo_fallida_nombre}</small>` : ''}
            ${e.estado_recepcion === 'EN_DISPUTA' ? `<br><span class="badge bg-danger mt-1" title="${escapeHtml(e.comentario_cliente || '')}">⚠ En disputa</span>` : ''}
            ${e.estado_nombre === 'ENTREGADA' && e.conciliacion_estado && e.conciliacion_estado !== 'PENDIENTE' ? `<br><span class="badge ${e.conciliacion_estado === 'RECHAZADO' ? 'bg-danger' : 'bg-success'} mt-1" title="Conciliación: ${e.conciliacion_estado}">✓ Conciliada</span>` : ''}
            ${e.estado_nombre === 'ENTREGADA' && e.estado_recepcion !== 'EN_DISPUTA' && (!e.conciliacion_estado || e.conciliacion_estado === 'PENDIENTE') && !confirmadoCliente ? `<br><span class="badge bg-warning text-dark mt-1" title="El cliente todavía no ha confirmado que recibió el pedido">⏳ Esperando confirmación del cliente</span>` : ''}
          </td>
          <td>${new Date(e.fecha_pedido).toLocaleDateString('es-DO')}</td>
          <td class="text-center">
                <button class="btn btn-sm btn-outline-secondary me-1" onclick="verDetalle(${e.id_entrega})" title="Ver detalle">
                    <span class="material-symbols-rounded" style="font-size:1rem;">visibility</span>
                </button>
                ${puedeActualizar ? `<button class="btn btn-sm btn-outline-warning me-1" onclick='abrirModalAsignar(${e.id_entrega}, "${e.numero_seguimiento}", ${enCola})' title="${enCola ? 'Asignar repartidor' : 'Reasignar a otro repartidor'}"><span class="material-symbols-rounded" style="font-size:1rem;">${enCola ? 'person_add' : 'sync_alt'}</span></button>` : ''}
                ${puedeActualizar ? `<button class="btn btn-sm btn-outline-primary me-1" onclick="abrirModalEstado(${e.id_entrega})" title="Actualizar estado"><span class="material-symbols-rounded" style="font-size:1rem;">edit</span></button>` : ''}
                ${['ASIGNADA','PARCIAL'].includes(e.estado_nombre) ? `<button class="btn btn-sm btn-outline-success me-1" onclick="abrirModalDespacho(${e.id_entrega})" title="${e.estado_nombre === 'PARCIAL' ? 'Despachar lo pendiente' : 'Registrar despacho'}"><span class="material-symbols-rounded" style="font-size:1rem;">inventory</span></button>` : ''}
                ${e.estado_nombre === 'ENTREGADA' && e.estado_recepcion === 'EN_DISPUTA' ? `
                  <button class="btn btn-sm btn-outline-success me-1" onclick="resolverDisputa(${e.id_entrega}, 'CONFIRMADA')" title="Resolver: el cliente sí recibió"><span class="material-symbols-rounded" style="font-size:1rem;">call</span></button>
                  <button class="btn btn-sm btn-outline-danger" onclick="resolverDisputa(${e.id_entrega}, 'REDESPACHO')" title="Resolver: redespachar"><span class="material-symbols-rounded" style="font-size:1rem;">restart_alt</span></button>
                ` : ''}
                ${e.estado_nombre === 'ENTREGADA' && e.estado_recepcion !== 'EN_DISPUTA' && (!e.conciliacion_estado || e.conciliacion_estado === 'PENDIENTE') && confirmadoCliente ? `<button class="btn btn-sm btn-outline-success" onclick="abrirModalConciliacion(${e.id_entrega})" title="Conciliar entrega"><span class="material-symbols-rounded" style="font-size:1rem;">fact_check</span></button>` : ''}
                ${e.estado_nombre === 'DEVUELTA' ? `<button class="btn btn-sm btn-outline-danger" onclick="reabrirEntregaDevuelta(${e.id_entrega})" title="Reabrir para redespachar"><span class="material-symbols-rounded" style="font-size:1rem;">restart_alt</span></button>` : ''}
          </td>
        </tr>`;
      }).join('');
      document.getElementById('tabla').innerHTML = `
        <table class="table table-hover mb-0">
          <thead><tr><th>Seguimiento</th><th>Factura</th><th>Cliente</th><th>Repartidor</th><th>Vehículo</th><th class="text-center">Estado</th><th>Fecha</th><th class="text-center">Acción</th></tr></thead>
          <tbody>${rows}</tbody>
        </table>`;
    });
}

function abrirModalEstado(id) {
  document.getElementById('meIdEntrega').value = id;
  document.getElementById('meEstado').value = 'EN_CAMINO';
  document.getElementById('meMotivo').value = '';
  document.getElementById('meDetalleParcial').value = '';
  document.getElementById('meDetalleFallida').value = '';
  cargarMotivosFallidaAdmin();
  toggleCamposEstado();
  modalEstado.show();
}
function toggleCamposEstado() {
  const v = document.getElementById('meEstado').value;
  document.getElementById('campoMotivo').style.display = v === 'INTERRUMPIDA' ? 'block' : 'none';
  document.getElementById('campoParcial').style.display = v === 'PARCIAL' ? 'block' : 'none';
  document.getElementById('campoFallida').style.display = v === 'FALLIDA' ? 'block' : 'none';
}
function cargarMotivosFallidaAdmin() {
  fetch(BASE_URL + '/backend/delivery/listar_motivos_fallida.php')
    .then(r => r.json())
    .then(data => {
      const sel = document.getElementById('meMotivoFallida');
      if (!data.success || !data.motivos.length) { sel.innerHTML = '<option value="">No se pudieron cargar</option>'; return; }
      sel.innerHTML = '<option value="">Selecciona un motivo...</option>' +
        data.motivos.map(m => `<option value="${m.id_motivo}">${m.nombre}</option>`).join('');
    });
}
function guardarEstado() {
  const id_entrega = document.getElementById('meIdEntrega').value;
  const estado = document.getElementById('meEstado').value;
  const motivo = document.getElementById('meMotivo').value.trim();
  const detalle_parcial = document.getElementById('meDetalleParcial').value.trim();
  const id_motivo_fallida = document.getElementById('meMotivoFallida').value;
  const detalle_fallida = document.getElementById('meDetalleFallida').value.trim();

  if (estado === 'INTERRUMPIDA' && !motivo) { Swal.fire('Falta información', 'Indica el motivo de la interrupción.', 'warning'); return; }
  if (estado === 'PARCIAL' && !detalle_parcial) { Swal.fire('Falta información', 'Indica qué se entregó y qué no.', 'warning'); return; }
  if (estado === 'FALLIDA' && !id_motivo_fallida) { Swal.fire('Falta información', 'Selecciona el motivo.', 'warning'); return; }

  fetch(BASE_URL + '/backend/delivery/actualizar_estado_entrega.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ id_entrega, estado, motivo, detalle_parcial, id_motivo_fallida: id_motivo_fallida ? parseInt(id_motivo_fallida) : null, detalle_fallida })
  }).then(r=>r.json()).then(data=>{
    if (data.success) { modalEstado.hide(); Swal.fire({title:'Actualizado', icon:'success', timer:1500, showConfirmButton:false}); cargar(); }
    else Swal.fire('Error', data.message, 'error');
  });
}

// ══════════════ P-2: Registrar Despacho ══════════════

let despachoEntregaActual = null;

function abrirModalDespacho(id) {
  document.getElementById('despachoBody').innerHTML = `<div class="text-center py-4"><div class="spinner-border text-success"></div></div>`;
  modalDespacho.show();

  fetch(BASE_URL + `/backend/delivery/get_detalle_entrega.php?id_entrega=${id}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        document.getElementById('despachoBody').innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        return;
      }
      const e = data.entrega;
      despachoEntregaActual = id;
      const esRedespacho = (e.ronda_actual || 1) > 1;
      const productosPendientes = (data.productos || []).filter(p => (p.cantidad_pendiente ?? p.cantidad) > 0);
      const filas = productosPendientes.map((p, i) => {
        const pend = p.cantidad_pendiente ?? p.cantidad;
        return `
        <tr>
          <td class="text-center">${i + 1}</td>
          <td>${p.producto_nombre}</td>
          <td>${p.numero_lote || '—'}</td>
          <td class="text-center">${p.cantidad}</td>
          <td class="text-center">${pend}</td>
          <td class="text-center">
            <input type="number" class="form-control form-control-sm text-center despacho-cantidad" style="width:80px;margin:0 auto;"
                   value="${pend}" min="0" max="${pend}"
                   data-id-lote="${p.id_lote || ''}" data-id-producto="${p.id_producto || ''}">
          </td>
        </tr>`;
      }).join('');

      document.getElementById('despachoBody').innerHTML = `
        <div class="card-d p-3 mb-3" style="background:#f8f9fa;">
          <strong>${e.numero_seguimiento}</strong><br>
          <span class="text-muted small">
            Repartidor: <strong>${e.repartidor_nombre || '—'}</strong> ·
            Vehículo: <strong>${e.vehiculo_tipo || '—'}${e.vehiculo_placa ? ' ('+e.vehiculo_placa+')' : ''}</strong> ·
            Fecha: <strong>${new Date().toLocaleDateString('es-DO')}</strong>
          </span>
        </div>
        ${esRedespacho ? `<div class="alert alert-warning py-2 px-3 mb-3" style="font-size:.82rem;"><span class="material-symbols-rounded align-middle" style="font-size:1rem;">history</span> Esta entrega quedó <strong>Parcial</strong> — este es el despacho de la ronda ${e.ronda_actual}, solo por lo que todavía falta.</div>` : ''}
        <p class="fw-semibold small mb-2"><span class="material-symbols-rounded align-middle" style="font-size:1rem;">inventory_2</span> Productos a Despachar</p>
        <table class="table tabla-despacho mb-3">
          <thead><tr><th class="text-center">#</th><th>Producto</th><th>Lote</th><th class="text-center">Cant. Pedida</th><th class="text-center">Pendiente</th><th class="text-center">Cant. a Despachar</th></tr></thead>
          <tbody>${filas}</tbody>
        </table>
        <label class="form-label small fw-semibold">Observaciones del despacho</label>
        <textarea class="form-control" id="despachoObservaciones" rows="2" placeholder="Ej: Productos verificados y embalados correctamente."></textarea>`;
    })
    .catch(() => { document.getElementById('despachoBody').innerHTML = `<div class="alert alert-danger">Error de conexión.</div>`; });
}

function confirmarDespacho() {
  const lineas = Array.from(document.querySelectorAll('.despacho-cantidad')).map(input => ({
    id_lote: input.dataset.idLote || null,
    id_producto: input.dataset.idProducto || null,
    cantidad_despachada: parseInt(input.value || 0),
  })).filter(l => l.cantidad_despachada > 0);

  if (!lineas.length) { Swal.fire('Falta información', 'Todas las cantidades a despachar están en 0.', 'warning'); return; }

  fetch(BASE_URL + '/backend/delivery/registrar_despacho.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      id_entrega: despachoEntregaActual,
      lineas,
      observaciones: document.getElementById('despachoObservaciones').value.trim(),
    })
  }).then(r => r.json()).then(data => {
    if (data.success) {
      modalDespacho.hide();
      Swal.fire({title:'Despacho confirmado', text:'El repartidor ya puede salir con el pedido.', icon:'success', timer:1800, showConfirmButton:false});
      cargar();
    } else Swal.fire('Error', data.message, 'error');
  }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
}

// ══════════════ P-4: Conciliación y Validación ══════════════

let conciliacionEntregaActual = null;

function abrirModalConciliacion(id) {
  document.getElementById('conciliacionBody').innerHTML = `<div class="text-center py-4"><div class="spinner-border text-success"></div></div>`;
  document.getElementById('conciliacionFooter').innerHTML = '';
  modalConciliacion.show();

  fetch(BASE_URL + `/backend/delivery/get_conciliacion.php?id_entrega=${id}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        document.getElementById('conciliacionBody').innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        return;
      }
      const e = data.entrega;
      conciliacionEntregaActual = id;

      const filas = data.productos.map((p, i) => `
        <tr>
          <td class="text-center">${i + 1}</td>
          <td>${p.producto_nombre}</td>
          <td>${p.numero_lote || '—'}</td>
          <td class="text-center">${p.cantidad_despachada}</td>
          <td class="text-center">
            <input type="number" class="form-control form-control-sm text-center conciliacion-cantidad" style="width:80px;margin:0 auto;"
                   value="${p.cantidad_despachada}" min="0" max="${p.cantidad_despachada}"
                   data-id-lote="${p.id_lote || ''}" data-id-producto="${p.id_producto || ''}"
                   data-despachado="${p.cantidad_despachada}" onchange="recalcularDiferenciaFila(this)">
          </td>
          <td class="text-center" id="dif-${i}">0</td>
          <td class="text-center" id="est-${i}"><span class="badge-correcto">Correcto</span></td>
        </tr>`).join('');

      document.getElementById('conciliacionBody').innerHTML = `
        <div class="alert alert-success py-2 mb-3" id="alertaDiferencias">
          <span class="material-symbols-rounded align-middle">check_circle</span> Sin diferencias detectadas. La entrega coincide con el despacho.
        </div>
        <p class="fw-semibold small mb-2"><span class="material-symbols-rounded align-middle" style="font-size:1rem;">summarize</span> Resumen del Ciclo</p>
        <div class="row g-2 mb-3 small">
          <div class="col-md-3"><strong>Fecha despacho:</strong><br>${e.fecha_despacho ? new Date(e.fecha_despacho).toLocaleString('es-DO') : '—'}</div>
          <div class="col-md-3"><strong>Repartidor:</strong><br>${e.repartidor_nombre || '—'}</div>
          <div class="col-md-3"><strong>Entregado a:</strong><br>${e.nombre_quien_recibe || '—'}</div>
          <div class="col-md-3"><strong>Hora entrega:</strong><br>${e.fecha_entrega_real ? new Date(e.fecha_entrega_real).toLocaleTimeString('es-DO') : '—'}</div>
        </div>
        <table class="table tabla-despacho mb-3">
          <thead><tr><th class="text-center">#</th><th>Producto</th><th>Lote</th><th class="text-center">Despachado</th><th class="text-center">Entregado</th><th class="text-center">Diferencia</th><th class="text-center">Estado</th></tr></thead>
          <tbody>${filas}</tbody>
        </table>
        <label class="form-label small fw-semibold">Observaciones del cajero</label>
        <textarea class="form-control" id="conciliacionObservaciones" rows="2" placeholder="Ingrese observaciones (opcional)"></textarea>`;

      document.getElementById('conciliacionFooter').innerHTML = `
        <button class="btn btn-danger" onclick="confirmarConciliacion('rechazar')"><span class="material-symbols-rounded align-middle" style="font-size:1rem;">close</span> Rechazar</button>
        <button class="btn btn-success" onclick="confirmarConciliacion('validar')"><span class="material-symbols-rounded align-middle" style="font-size:1rem;">check</span> Validar y Cerrar Pedido</button>`;
    })
    .catch(() => { document.getElementById('conciliacionBody').innerHTML = `<div class="alert alert-danger">Error de conexión.</div>`; });
}

function recalcularDiferenciaFila(input) {
  const fila = input.closest('tr');
  const idx = Array.from(fila.parentElement.children).indexOf(fila);
  const despachado = parseInt(input.dataset.despachado);
  const entregado = parseInt(input.value || 0);
  const diferencia = despachado - entregado;

  document.getElementById(`dif-${idx}`).textContent = diferencia;
  document.getElementById(`est-${idx}`).innerHTML = diferencia === 0
    ? '<span class="badge-correcto">Correcto</span>'
    : '<span class="badge-diferencia">Diferencia</span>';

  const hayDiferencias = Array.from(document.querySelectorAll('.conciliacion-cantidad'))
    .some(el => parseInt(el.dataset.despachado) !== parseInt(el.value || 0));
  document.getElementById('alertaDiferencias').className = hayDiferencias ? 'alert alert-warning py-2 mb-3' : 'alert alert-success py-2 mb-3';
  document.getElementById('alertaDiferencias').innerHTML = hayDiferencias
    ? '<span class="material-symbols-rounded align-middle">warning</span> Hay diferencias entre lo despachado y lo entregado — revísalas antes de validar.'
    : '<span class="material-symbols-rounded align-middle">check_circle</span> Sin diferencias detectadas. La entrega coincide con el despacho.';
}

function confirmarConciliacion(accion) {
  const lineas = Array.from(document.querySelectorAll('.conciliacion-cantidad')).map(input => ({
    id_lote: input.dataset.idLote || null,
    id_producto: input.dataset.idProducto || null,
    cantidad_despachada: parseInt(input.dataset.despachado),
    cantidad_entregada: parseInt(input.value || 0),
  }));

  fetch(BASE_URL + '/backend/delivery/registrar_conciliacion.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      id_entrega: conciliacionEntregaActual,
      lineas, accion,
      observaciones: document.getElementById('conciliacionObservaciones').value.trim(),
    })
  }).then(r => r.json()).then(data => {
    if (data.success) {
      modalConciliacion.hide();
      const msg = accion === 'rechazar' ? 'Conciliación rechazada' :
        (data.estado_conciliacion === 'REQUERIDO_AJUSTE' ? 'Validado con diferencias registradas' : 'Pedido cerrado correctamente');
      Swal.fire({title: msg, icon: accion === 'rechazar' ? 'warning' : 'success', timer: 1800, showConfirmButton: false});
      cargar();
    } else Swal.fire('Error', data.message, 'error');
  }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
}

// ══════════════ Resolver disputa (cliente reportó "no recibí nada") ══════════════
// El cajero debe llamar al cliente y al repartidor para aclarar la situación
// ANTES de resolver — la nota es obligatoria y queda en el historial de la entrega.
function resolverDisputa(id, resolucion) {
  const esConfirmada = resolucion === 'CONFIRMADA';
  Swal.fire({
    title: esConfirmada ? 'Disputa aclarada: sí se entregó' : 'Disputa aclarada: hay que redespachar',
    html: 'Llama primero al cliente y al repartidor para aclarar qué pasó. Escribe abajo qué te dijo cada uno.',
    input: 'textarea',
    inputPlaceholder: 'Nota obligatoria: qué dijeron el cliente y el repartidor al llamarlos...',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: esConfirmada ? 'Confirmar, sí se entregó' : 'Confirmar y redespachar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: esConfirmada ? '#28a745' : '#dc3545',
    preConfirm: (value) => {
      if (!value || !value.trim()) {
        Swal.showValidationMessage('La nota es obligatoria — cuenta qué dijeron el cliente y el repartidor.');
        return false;
      }
      return value.trim();
    }
  }).then(result => {
    if (!result.isConfirmed) return;
    fetch(BASE_URL + '/backend/delivery/resolver_disputa.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ id_entrega: id, resolucion, nota: result.value })
    }).then(r => r.json()).then(data => {
      if (data.success) {
        Swal.fire({
          title: esConfirmada ? 'Disputa resuelta' : 'Entrega reabierta para redespacho',
          text: esConfirmada ? 'Ya se puede conciliar normalmente.' : 'Vuelve a Pendiente para asignar repartidor de nuevo.',
          icon: 'success', timer: 2200, showConfirmButton: false
        });
        cargar();
      } else Swal.fire('Error', data.message, 'error');
    }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
  });
}

function reabrirEntregaDevuelta(id) {
  Swal.fire({
    title: 'Reabrir entrega',
    html: 'La entrega volverá a <strong>Pendiente</strong> para asignarle repartidor y despachar un pedido nuevo con productos validados.',
    input: 'textarea',
    inputPlaceholder: 'Nota (opcional): qué se corrigió, ej. producto vencido reemplazado por lote nuevo.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Reabrir',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#dc3545',
  }).then((result) => {
    if (!result.isConfirmed) return;
    fetch(BASE_URL + '/backend/delivery/reabrir_entrega_devuelta.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ id_entrega: id, observaciones: (result.value || '').trim() })
    }).then(r => r.json()).then(data => {
      if (data.success) {
        Swal.fire({title: 'Entrega reabierta', text: 'Ya está en cola para asignar repartidor.', icon: 'success', timer: 2000, showConfirmButton: false});
        cargar();
      } else Swal.fire('Error', data.message, 'error');
    }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
  });
}
</script>
