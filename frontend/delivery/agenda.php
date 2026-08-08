<?php
// frontend/delivery/agenda.php — se carga DENTRO de menuprincipal.php,
// igual que el resto de las pantallas reales del sistema. El acceso ya
// lo filtra menuprincipal.php según el rol (solo Repartidor y
// Administrador ven este enlace en el menú).
require_once __DIR__ . '/../../backend/conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$base_url = '/sistema-gestor-de-farmacias';
?>
<style>
  .card-d{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);}
  .stat-card{background:#fff;border-radius:12px;padding:1rem 1.25rem;display:flex;align-items:center;gap:1rem;box-shadow:0 1px 4px rgba(0,0,0,.06);}
  .stat-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;}
  .stat-val{font-size:1.6rem;font-weight:700;line-height:1;}
  .stat-lbl{font-size:.72rem;color:#777;}
  table thead th{background:#28a745;color:#fff;font-size:.78rem;font-weight:600;border:none;padding:.6rem .8rem;}
  table tbody td{font-size:.82rem;vertical-align:middle;padding:.65rem .8rem;}
  .badge-st{font-size:.72rem;padding:.35em .65em;border-radius:20px;font-weight:700;}
  .st-PENDIENTE{background:#EAF1F8;color:#1565C0;}
  .st-ASIGNADA{background:#E0E7FF;color:#4338CA;}
  .st-EN_CAMINO{background:#FFF3CD;color:#C77B00;}
  .st-INTERRUMPIDA{background:#FDEAEA;color:#DC3545;}
  .st-PARCIAL{background:#FFE8CC;color:#B35C00;}
  .st-REPROGRAMADA{background:#E9D8FD;color:#6B21A8;}
  .empty-state{text-align:center;padding:3rem 1rem;color:#aaa;}
</style>

<div class="container-fluid p-0">
  <h2 class="mb-0 text-success"><span class="material-symbols-rounded align-middle me-2">local_shipping</span> Mi Agenda de Hoy</h2>
  <p class="text-muted mb-3">Tus entregas asignadas y en curso ahora mismo</p>

  <div class="row g-3 mb-3" id="statsRow">
    <div class="col-6 col-md-3">
      <div class="stat-card"><div class="stat-icon" style="background:#EAF1F8"><span class="material-symbols-rounded" style="color:#1565C0;">assignment</span></div>
        <div><div class="stat-val" id="statTotal">—</div><div class="stat-lbl">Total asignadas</div></div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card"><div class="stat-icon" style="background:#D4EDDA"><span class="material-symbols-rounded" style="color:#2E8B57;">task_alt</span></div>
        <div><div class="stat-val" style="color:#2E8B57" id="statComp">—</div><div class="stat-lbl">Completadas hoy</div></div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card"><div class="stat-icon" style="background:#FFF3CD"><span class="material-symbols-rounded" style="color:#C77B00;">two_wheeler</span></div>
        <div><div class="stat-val" style="color:#C77B00" id="statCamino">—</div><div class="stat-lbl">En camino</div></div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card"><div class="stat-icon" style="background:#F5F5F5"><span class="material-symbols-rounded" style="color:#888;">schedule</span></div>
        <div><div class="stat-val text-secondary" id="statPend">—</div><div class="stat-lbl">Pendientes</div></div></div>
    </div>
  </div>

  <div class="card-d p-2 mb-3 d-flex flex-row justify-content-between align-items-center px-3">
    <strong id="fechaHoyTexto"><span class="material-symbols-rounded align-middle me-1">calendar_today</span> —</strong>
    <button class="btn btn-sm btn-outline-success" onclick="cargarAgenda()"><span class="material-symbols-rounded align-middle" style="font-size:1rem;">refresh</span> Actualizar</button>
  </div>

  <div class="card-d p-2" id="tablaContainer">
    <div class="empty-state"><div class="spinner-border text-success"></div></div>
  </div>
</div>

<!-- MODAL: detalle + acciones de una entrega -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:#28a745;color:#fff;">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">local_shipping</span> Detalle de la entrega</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detalleBody">
        <div class="text-center py-4"><div class="spinner-border text-success"></div></div>
      </div>
      <div class="modal-footer" id="detalleFooter"></div>
    </div>
  </div>
</div>

<!-- MODAL: confirmar entrega con detalle real (reemplaza el "marcar entregada" simple) -->
<div class="modal fade" id="modalConfirmarEntrega" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:#28a745;color:#fff;">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">task_alt</span> Confirmar Entrega</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="confirmarEntregaBody">
        <div class="text-center py-4"><div class="spinner-border text-success"></div></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success" onclick="confirmarEntregaDetalle()"><span class="material-symbols-rounded align-middle" style="font-size:1rem;">check</span> Confirmar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: reportar problema (interrumpida / parcial / fallida) -->
<div class="modal fade" id="modalProblema" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">report</span> Reportar un problema</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="probIdEntrega">
        <label class="form-label small fw-semibold">¿Qué pasó?</label>
        <select class="form-select mb-3" id="probTipo" onchange="cambiarTipoProblema()">
          <option value="INTERRUMPIDA">Se interrumpió (no pude terminar la ruta)</option>
          <option value="PARCIAL">Entrega parcial (entregué solo parte del pedido)</option>
          <option value="FALLIDA">No se pudo entregar</option>
        </select>
        <div id="probMotivoFallidaWrap" style="display:none;">
          <label class="form-label small fw-semibold">Motivo</label>
          <select class="form-select mb-3" id="probMotivoFallida"><option value="">Cargando...</option></select>
        </div>
        <label class="form-label small fw-semibold">Detalle</label>
        <textarea class="form-control" id="probDetalle" rows="3" placeholder="Cuéntanos qué pasó..."></textarea>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-danger" onclick="enviarProblema()">Enviar reporte</button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL = '<?php echo $base_url; ?>';
let modalDetalle, modalProblema, modalConfirmarEntrega;
let entregasCache = [];

const BADGE = {
  'PENDIENTE':'<span class="badge-st st-PENDIENTE">Pendiente</span>',
  'ASIGNADA':'<span class="badge-st st-ASIGNADA">Asignada</span>',
  'EN_CAMINO':'<span class="badge-st st-EN_CAMINO">En camino</span>',
  'INTERRUMPIDA':'<span class="badge-st st-INTERRUMPIDA">Interrumpida</span>',
  'PARCIAL':'<span class="badge-st st-PARCIAL">Parcial</span>',
  'REPROGRAMADA':'<span class="badge-st st-REPROGRAMADA">Reprogramada</span>',
};

document.addEventListener('DOMContentLoaded', () => {
  ['modalDetalle', 'modalProblema', 'modalConfirmarEntrega'].forEach(id => {
    const el = document.getElementById(id);
    if (el && el.parentElement !== document.body) document.body.appendChild(el);
  });
  modalDetalle = new bootstrap.Modal('#modalDetalle');
  modalProblema = new bootstrap.Modal('#modalProblema');
  modalConfirmarEntrega = new bootstrap.Modal('#modalConfirmarEntrega');
  cargarAgenda();
  setInterval(cargarAgenda, 60000);
});

function cargarAgenda() {
  fetch(BASE_URL + '/backend/delivery/get_agenda_repartidor.php').then(r=>r.json()).then(data => {
    if (!data.success) {
      document.getElementById('tablaContainer').innerHTML = `<div class="alert alert-danger m-3">${data.message}</div>`;
      return;
    }
    entregasCache = data.entregas;
    document.getElementById('fechaHoyTexto').innerHTML = `<span class="material-symbols-rounded align-middle me-1">calendar_today</span> ${data.fecha_hoy}`;
    const s = data.stats;
    document.getElementById('statTotal').textContent = s.total;
    document.getElementById('statComp').textContent = s.completadas;
    document.getElementById('statCamino').textContent = s.en_camino;
    document.getElementById('statPend').textContent = s.pendientes;

    const tc = document.getElementById('tablaContainer');
    if (!data.entregas.length) {
      tc.innerHTML = `<div class="empty-state"><span class="material-symbols-rounded" style="font-size:3rem;">inbox</span><br>No tienes entregas asignadas por ahora.</div>`;
      return;
    }
    const rows = data.entregas.map(e => `
      <tr>
        <td class="text-center fw-bold">${e.orden_visita}</td>
        <td class="fw-semibold text-success">${e.numero_seguimiento}</td>
        <td>${e.cliente_nombre}</td>
        <td>${e.direccion_entrega}${e.barrio_entrega ? ', '+e.barrio_entrega : ''}</td>
        <td class="text-center">${BADGE[e.estado_nombre] || e.estado_nombre}</td>
        <td class="text-center">
          <button class="btn btn-sm btn-success" onclick="abrirDetalle(${e.id_entrega})">
            <span class="material-symbols-rounded align-middle" style="font-size:1rem;">visibility</span> Ver Detalle
          </button>
        </td>
      </tr>`).join('');
    tc.innerHTML = `
      <table class="table table-hover mb-0">
        <thead><tr><th class="text-center">Orden</th><th>Pedido</th><th>Cliente</th><th>Dirección</th><th class="text-center">Estado</th><th class="text-center">Acción</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>`;
  }).catch(() => {
    document.getElementById('tablaContainer').innerHTML = `<div class="alert alert-danger m-3">Error de conexión.</div>`;
  });
}

function abrirDetalle(id) {
  document.getElementById('detalleBody').innerHTML = `<div class="text-center py-4"><div class="spinner-border text-success"></div></div>`;
  document.getElementById('detalleFooter').innerHTML = '';
  modalDetalle.show();

  fetch(BASE_URL + `/backend/delivery/get_detalle_entrega.php?id_entrega=${id}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        document.getElementById('detalleBody').innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        return;
      }
      const e = data.entrega;
      const productos = (data.productos || []).map(p => `${p.producto_nombre} × ${p.cantidad}`).join('<br>') || '—';
      document.getElementById('detalleBody').innerHTML = `
        <div class="row g-3">
          <div class="col-md-6"><strong>Cliente:</strong><br>${e.cliente_nombre}</div>
          <div class="col-md-6"><strong>Estado:</strong><br>${BADGE[e.estado_nombre] || e.estado_nombre}</div>
          <div class="col-md-12"><strong>Dirección:</strong><br>${e.direccion_entrega}${e.barrio_entrega ? ', '+e.barrio_entrega : ''}${e.referencia_entrega ? ' — '+e.referencia_entrega : ''}</div>
          <div class="col-md-12"><strong>Productos:</strong><br>${productos}</div>
          ${e.observaciones ? `<div class="col-md-12"><strong>Observaciones:</strong><br>${e.observaciones}</div>` : ''}
        </div>`;

      let botones = `<button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>`;
      if (e.estado_nombre === 'PENDIENTE' || e.estado_nombre === 'ASIGNADA' || e.estado_nombre === 'REPROGRAMADA') {
        botones += `<button class="btn btn-success" onclick="cambiarEstado(${e.id_entrega}, 'EN_CAMINO')"><span class="material-symbols-rounded align-middle" style="font-size:1rem;">two_wheeler</span> Marcar en camino</button>`;
      } else if (e.estado_nombre === 'EN_CAMINO') {
        botones += `<button class="btn btn-outline-danger" onclick="abrirProblema(${e.id_entrega})">Reportar problema</button>`;
        botones += `<button class="btn btn-success" onclick="abrirModalConfirmarEntrega(${e.id_entrega})"><span class="material-symbols-rounded align-middle" style="font-size:1rem;">task_alt</span> Marcar entregada</button>`;
      }
      document.getElementById('detalleFooter').innerHTML = botones;
    })
    .catch(() => { document.getElementById('detalleBody').innerHTML = `<div class="alert alert-danger">Error de conexión.</div>`; });
}

function cambiarEstado(id_entrega, estado) {
  fetch(BASE_URL + '/backend/delivery/actualizar_estado_entrega.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ id_entrega, estado })
  }).then(r=>r.json()).then(data => {
    if (data.success) {
      modalDetalle.hide();
      Swal.fire({title:'Actualizado', icon:'success', timer:1300, showConfirmButton:false});
      cargarAgenda();
    } else Swal.fire('Error', data.message, 'error');
  }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
}

function abrirProblema(id_entrega) {
  document.getElementById('probIdEntrega').value = id_entrega;
  document.getElementById('probTipo').value = 'INTERRUMPIDA';
  document.getElementById('probDetalle').value = '';
  cambiarTipoProblema();
  cargarMotivosFallida();
  modalDetalle.hide();
  modalProblema.show();
}
function cambiarTipoProblema() {
  document.getElementById('probMotivoFallidaWrap').style.display =
    document.getElementById('probTipo').value === 'FALLIDA' ? 'block' : 'none';
}
function cargarMotivosFallida() {
  fetch(BASE_URL + '/backend/delivery/listar_motivos_fallida.php').then(r=>r.json()).then(data => {
    const sel = document.getElementById('probMotivoFallida');
    if (!data.success) { sel.innerHTML = '<option value="">No se pudo cargar</option>'; return; }
    sel.innerHTML = '<option value="">Selecciona...</option>' + data.motivos.map(m => `<option value="${m.id_motivo}">${m.nombre}</option>`).join('');
  });
}
function enviarProblema() {
  const id_entrega = document.getElementById('probIdEntrega').value;
  const estado = document.getElementById('probTipo').value;
  const detalle = document.getElementById('probDetalle').value.trim();
  const payload = { id_entrega: parseInt(id_entrega), estado };
  if (estado === 'INTERRUMPIDA') payload.motivo = detalle;
  if (estado === 'PARCIAL') payload.detalle_parcial = detalle;
  if (estado === 'FALLIDA') {
    payload.id_motivo_fallida = parseInt(document.getElementById('probMotivoFallida').value || 0);
    payload.detalle_fallida = detalle;
    if (!payload.id_motivo_fallida) { Swal.fire('Falta información', 'Selecciona el motivo.', 'warning'); return; }
  }
  if (!detalle && estado !== 'FALLIDA') { Swal.fire('Falta información', 'Cuéntanos qué pasó.', 'warning'); return; }

  fetch(BASE_URL + '/backend/delivery/actualizar_estado_entrega.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  }).then(r=>r.json()).then(data => {
    if (data.success) {
      modalProblema.hide();
      Swal.fire({title:'Reporte enviado', icon:'success', timer:1500, showConfirmButton:false});
      cargarAgenda();
    } else Swal.fire('Error', data.message, 'error');
  }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
}

// ══════════════ Confirmar entrega con detalle real ══════════════

const MOTIVOS_DEVOLUCION = [
  'Cliente rechazó el producto',
  'Cliente no tenía efectivo suficiente',
  'Producto dañado en el trayecto',
  'Producto equivocado',
  'Cliente cambió de opinión',
  'Otro motivo',
];

let confirmarEntregaIdActual = null;

function abrirModalConfirmarEntrega(id) {
  document.getElementById('confirmarEntregaBody').innerHTML = `<div class="text-center py-4"><div class="spinner-border text-success"></div></div>`;
  modalConfirmarEntrega.show();

  fetch(BASE_URL + `/backend/delivery/get_despacho_detalle.php?id_entrega=${id}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        document.getElementById('confirmarEntregaBody').innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        return;
      }
      confirmarEntregaIdActual = id;

      const filas = data.productos.map((p, i) => `
        <tr>
          <td>${p.producto_nombre}</td>
          <td class="text-center">${p.cantidad_despachada}</td>
          <td class="text-center">
            <input type="number" class="form-control form-control-sm text-center entrega-cantidad" style="width:80px;margin:0 auto;"
                   value="${p.cantidad_despachada}" min="0" max="${p.cantidad_despachada}"
                   data-id-lote="${p.id_lote || ''}" data-id-producto="${p.id_producto || ''}"
                   data-despachado="${p.cantidad_despachada}" data-idx="${i}" onchange="toggleMotivoDevolucion(${i})">
          </td>
          <td id="motivoWrap-${i}" style="display:none;">
            <select class="form-select form-select-sm motivo-devolucion" data-idx="${i}">
              <option value="">Motivo...</option>
              ${MOTIVOS_DEVOLUCION.map(m => `<option value="${m}">${m}</option>`).join('')}
            </select>
          </td>
        </tr>`).join('');

      document.getElementById('confirmarEntregaBody').innerHTML = `
        <p class="text-muted small">Confirma cuánto entregaste de cada producto. Si algo no se entregó completo, indica el motivo — se registrará como una devolución automáticamente.</p>
        <table class="table table-sm mb-3">
          <thead><tr><th>Producto</th><th class="text-center">Despachado</th><th class="text-center">Entregado</th><th>Motivo (si aplica)</th></tr></thead>
          <tbody>${filas}</tbody>
        </table>
        <label class="form-label small fw-semibold">¿Quién recibió el pedido?</label>
        <input type="text" class="form-control mb-2" id="nombreQuienRecibe" placeholder="Nombre de quien recibió">`;
    })
    .catch(() => { document.getElementById('confirmarEntregaBody').innerHTML = `<div class="alert alert-danger">Error de conexión.</div>`; });
}

function toggleMotivoDevolucion(idx) {
  const input = document.querySelector(`.entrega-cantidad[data-idx="${idx}"]`);
  const despachado = parseInt(input.dataset.despachado);
  const entregado = parseInt(input.value || 0);
  document.getElementById(`motivoWrap-${idx}`).style.display = entregado < despachado ? 'table-cell' : 'none';
}

function confirmarEntregaDetalle() {
  const inputs = Array.from(document.querySelectorAll('.entrega-cantidad'));
  const lineas = inputs.map(input => {
    const idx = input.dataset.idx;
    const despachado = parseInt(input.dataset.despachado);
    const entregado = parseInt(input.value || 0);
    const motivoSelect = document.querySelector(`.motivo-devolucion[data-idx="${idx}"]`);
    return {
      id_lote: input.dataset.idLote || null,
      id_producto: input.dataset.idProducto || null,
      cantidad_despachada: despachado,
      cantidad_entregada: entregado,
      motivo_devolucion: entregado < despachado ? (motivoSelect?.value || '') : null,
    };
  });

  const faltaMotivo = lineas.some(l => l.cantidad_entregada < l.cantidad_despachada && !l.motivo_devolucion);
  if (faltaMotivo) {
    Swal.fire('Falta información', 'Selecciona el motivo para cada producto que no se entregó completo.', 'warning');
    return;
  }

  fetch(BASE_URL + '/backend/delivery/confirmar_entrega_detalle.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      id_entrega: confirmarEntregaIdActual,
      lineas,
      nombre_quien_recibe: document.getElementById('nombreQuienRecibe').value.trim(),
    })
  }).then(r => r.json()).then(data => {
    if (data.success) {
      modalConfirmarEntrega.hide();
      const msg = data.id_devolucion
        ? 'Entrega registrada — se creó una devolución para lo que faltó'
        : '¡Entrega completada!';
      Swal.fire({title: msg, icon: 'success', timer: 2000, showConfirmButton: false});
      cargarAgenda();
    } else Swal.fire('Error', data.message, 'error');
  }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
}
</script>
