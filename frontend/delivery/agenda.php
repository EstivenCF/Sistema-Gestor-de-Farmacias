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
  .st-ENTREGADA{background:#D4EDDA;color:#1E7E34;}
  .st-INTERRUMPIDA{background:#FDEAEA;color:#DC3545;}
  .st-PARCIAL{background:#FFE8CC;color:#B35C00;}
  .st-REPROGRAMADA{background:#E9D8FD;color:#6B21A8;}
  .st-CANCELADA{background:#F1F1F1;color:#555;}
  .st-FALLIDA{background:#FDEAEA;color:#DC3545;}
  .st-DEVUELTA{background:#F8D7DA;color:#842029;}
  .empty-state{text-align:center;padding:3rem 1rem;color:#aaa;}

  /* ── Modal fusionado de entrega ── */
  .met-info-row{display:flex;gap:.5rem;margin-bottom:.55rem;font-size:.85rem;border-bottom:1px solid #f0f0f0;padding-bottom:.5rem;}
  .met-info-label{font-weight:600;color:#555;min-width:95px;flex-shrink:0;}
  .met-info-val{color:#1a1a1a;}
  .met-producto-item{background:#f8f9fa;border-radius:8px;padding:.5rem .8rem;margin-bottom:.35rem;font-size:.82rem;display:flex;justify-content:space-between;align-items:center;}
  .met-map-wrap{border-radius:10px;overflow:hidden;border:1px solid #e2e2e2;}
  .met-map-placeholder{background:linear-gradient(135deg,#eaf7ee,#dff3e4);border:2px dashed #9ed6ab;border-radius:10px;padding:2rem 1rem;text-align:center;color:#4a8f5a;font-size:.85rem;min-height:140px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;}
  .met-radio-card{border:2px solid #dee2e6;border-radius:10px;padding:.7rem 1rem;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:.7rem;margin-bottom:.5rem;}
  .met-radio-card:hover{border-color:#28a745;background:#f4fbf6;}
  .met-radio-card.sel-exitosa{border-color:#28a745;background:#f0fff4;}
  .met-radio-card.sel-fallida{border-color:#DC3545;background:#fff5f5;}
  .met-radio-card input[type=radio]{width:18px;height:18px;flex-shrink:0;accent-color:#28a745;}
  .met-warn{background:#FFF3CD;border:1.5px solid #C77B00;border-radius:8px;padding:.6rem .9rem;font-size:.78rem;color:#7a5300;margin-bottom:.75rem;}
  .met-cant-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;padding:.5rem;background:#f8f9fa;border-radius:8px;}
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

<!-- MODAL ÚNICO: datos del pedido + acción según el estado -->
<div class="modal fade" id="modalEntrega" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header" style="background:#28a745;color:#fff;">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">local_shipping</span> <span id="metSeguimiento">Detalle de la entrega</span></h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="entregaBody">
        <div class="text-center py-4"><div class="spinner-border text-success"></div></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: reportar interrupción de ruta (accidente, robo, etc.) -->
<div class="modal fade" id="modalProblema" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">report</span> Reportar interrupción de la ruta</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="probIdEntrega">
        <p class="text-muted small">Úsalo solo si no puedes continuar la ruta de ninguna forma (accidente, robo, avería, etc.). Si sí llegaste con el cliente, usa "Registrar Resultado de Entrega" en vez de esto.</p>
        <label class="form-label small fw-semibold">¿Qué pasó?</label>
        <textarea class="form-control" id="probDetalle" rows="3" placeholder="Cuéntanos qué pasó..."></textarea>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-danger" onclick="enviarProblema()">Enviar reporte</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: registrar devolución de producto(s) en esta entrega -->
<div class="modal fade" id="modalDevolucion" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">assignment_return</span> Registrar devolución</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">Indica qué producto(s) devolvió el cliente en el momento de la entrega (ej: llegó vencido) y la cantidad. Esto queda registrado como una devolución en Inventario.</p>
        <label class="fw-semibold mb-2 small">Cantidad devuelta por producto:</label>
        <div id="tablaDevolucion" class="mb-3"></div>
        <div class="mb-3">
          <label class="form-label fw-semibold small">Motivo: <span class="text-danger">*</span></label>
          <select id="motivoDevolucionSelect" class="form-select form-select-sm"><option value="">Cargando motivos...</option></select>
        </div>
        <div class="mb-2">
          <label class="form-label fw-semibold small">Explicación detallada (opcional):</label>
          <textarea id="detalleDevolucion" class="form-control form-control-sm" rows="2" placeholder="Ej: el cliente mostró la fecha de vencimiento en la caja"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-danger btn-sm" onclick="registrarDevolucion()"><span class="material-symbols-rounded align-middle" style="font-size:1rem;">check</span> Registrar devolución</button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL = '<?php echo $base_url; ?>';
let modalEntrega, modalProblema, modalDevolucion;
let entregasCache = [];
let entregaActual = null;   // datos de la entrega abierta en modalEntrega
let productosActual = [];   // productos de esa entrega (id_detalle, cantidad, producto_nombre)

const BADGE = {
  'PENDIENTE':'<span class="badge-st st-PENDIENTE">Pendiente</span>',
  'ASIGNADA':'<span class="badge-st st-ASIGNADA">Asignada</span>',
  'EN_CAMINO':'<span class="badge-st st-EN_CAMINO">En camino</span>',
  'ENTREGADA':'<span class="badge-st st-ENTREGADA">Entregada</span>',
  'INTERRUMPIDA':'<span class="badge-st st-INTERRUMPIDA">Interrumpida</span>',
  'PARCIAL':'<span class="badge-st st-PARCIAL">Parcial</span>',
  'REPROGRAMADA':'<span class="badge-st st-REPROGRAMADA">Reprogramada</span>',
  'CANCELADA':'<span class="badge-st st-CANCELADA">Cancelada</span>',
  'FALLIDA':'<span class="badge-st st-FALLIDA">Fallida</span>',
  'DEVUELTA':'<span class="badge-st st-DEVUELTA">Devolución</span>',
};

document.addEventListener('DOMContentLoaded', () => {
  ['modalEntrega', 'modalProblema', 'modalDevolucion'].forEach(id => {
    const el = document.getElementById(id);
    if (el && el.parentElement !== document.body) document.body.appendChild(el);
  });
  modalEntrega = new bootstrap.Modal('#modalEntrega');
  modalProblema = new bootstrap.Modal('#modalProblema');
  modalDevolucion = new bootstrap.Modal('#modalDevolucion');
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

// ══════════════ Modal único: Detalle + Acción ══════════════

function abrirDetalle(id) {
  document.getElementById('entregaBody').innerHTML = `<div class="text-center py-4"><div class="spinner-border text-success"></div></div>`;
  document.getElementById('metSeguimiento').textContent = 'Detalle de la entrega';
  modalEntrega.show();

  fetch(BASE_URL + `/backend/delivery/get_detalle_entrega.php?id_entrega=${id}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        document.getElementById('entregaBody').innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        return;
      }
      entregaActual = data.entrega;
      productosActual = (data.productos || []).map(p => ({
        id_detalle: p.id_detalle,
        producto_nombre: p.producto_nombre || 'Producto',
        cantidad: parseInt(p.cantidad),
        cantidad_pendiente: parseInt(p.cantidad_pendiente ?? p.cantidad),
      }));
      document.getElementById('metSeguimiento').textContent = entregaActual.numero_seguimiento || 'Detalle de la entrega';
      renderModalEntrega();
    })
    .catch(() => { document.getElementById('entregaBody').innerHTML = `<div class="alert alert-danger">Error de conexión.</div>`; });
}

function renderModalEntrega() {
  const e = entregaActual;

  const mapaHtml = (e.latitud_entrega && e.longitud_entrega) ? `
    <div class="met-map-wrap">
      <iframe width="100%" height="160" frameborder="0" scrolling="no"
        src="https://www.openstreetmap.org/export/embed.html?bbox=${e.longitud_entrega-0.006}%2C${e.latitud_entrega-0.006}%2C${e.longitud_entrega+0.006}%2C${e.latitud_entrega+0.006}&marker=${e.latitud_entrega}%2C${e.longitud_entrega}">
      </iframe>
    </div>` : `
    <div class="met-map-placeholder">
      <span class="material-symbols-rounded" style="font-size:1.8rem;">location_on</span>
      <div>${e.direccion_entrega}${e.barrio_entrega ? ', '+e.barrio_entrega : ''}</div>
    </div>`;

  const mapsUrl = (e.latitud_entrega && e.longitud_entrega)
    ? `https://www.google.com/maps/dir/?api=1&destination=${e.latitud_entrega},${e.longitud_entrega}`
    : `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent((e.direccion_entrega||'') + ' ' + (e.barrio_entrega||'') + ' ' + (e.ciudad_entrega||''))}`;

  const productosHtml = productosActual.map(p => `
    <div class="met-producto-item">
      <span><span class="material-symbols-rounded align-middle me-1" style="font-size:1rem;color:#28a745;">pill</span>${p.producto_nombre}</span>
      <span class="badge bg-success rounded-pill">${p.cantidad} uds.</span>
    </div>`).join('') || '<span class="text-muted">Sin detalle de productos</span>';

  const panelIzquierdo = `
    <div class="card-d p-3 mb-3">
      <h6 class="fw-bold text-success mb-3"><span class="material-symbols-rounded align-middle me-1" style="font-size:1.1rem;">description</span>Datos del Pedido</h6>
      <div class="met-info-row"><span class="met-info-label">Cliente:</span><span class="met-info-val">${e.cliente_nombre}</span></div>
      ${e.cliente_telefono ? `<div class="met-info-row"><span class="met-info-label">Teléfono:</span><span class="met-info-val">${e.cliente_telefono}</span></div>` : ''}
      <div class="met-info-row"><span class="met-info-label">Dirección:</span><span class="met-info-val">${e.direccion_entrega}${e.barrio_entrega ? ', '+e.barrio_entrega : ''}${e.referencia_entrega ? ' — '+e.referencia_entrega : ''}</span></div>
      ${e.observaciones ? `<div class="met-info-row"><span class="met-info-label">Nota:</span><span class="met-info-val">${e.observaciones}</span></div>` : ''}
      <div class="met-info-row" style="border-bottom:none;"><span class="met-info-label">Productos:</span><div class="met-info-val w-100 mt-1">${productosHtml}</div></div>
      <div class="d-flex justify-content-end mt-2">
        <button class="btn btn-outline-danger btn-sm" onclick="abrirModalDevolucion()">
          <span class="material-symbols-rounded align-middle" style="font-size:1rem;">assignment_return</span> Registrar devolución
        </button>
      </div>
    </div>
    <div class="card-d p-3">
      <h6 class="fw-bold text-success mb-2"><span class="material-symbols-rounded align-middle me-1" style="font-size:1.1rem;">map</span>Vista del mapa / dirección</h6>
      ${mapaHtml}
      <a href="${mapsUrl}" target="_blank" class="btn btn-outline-success btn-sm w-100 mt-2">
        <span class="material-symbols-rounded align-middle" style="font-size:1rem;">open_in_new</span> Abrir en Google Maps
      </a>
    </div>`;

  document.getElementById('entregaBody').innerHTML = `
    <div class="row g-3">
      <div class="col-12 col-lg-5">${panelIzquierdo}</div>
      <div class="col-12 col-lg-7" id="metPanelDerecho"></div>
    </div>`;

  renderPanelDerecho();
}

function renderPanelDerecho() {
  const e = entregaActual;
  const cont = document.getElementById('metPanelDerecho');
  const estado = e.estado_nombre;

  if (['PENDIENTE', 'ASIGNADA', 'REPROGRAMADA'].includes(estado)) {
    cont.innerHTML = `
      <div class="card-d p-3">
        <h6 class="fw-bold text-success mb-2"><span class="material-symbols-rounded align-middle me-1" style="font-size:1.1rem;">two_wheeler</span>Salida a ruta</h6>
        <div class="mb-3">Estado actual: ${BADGE[estado] || estado}</div>
        <p class="text-muted small">Cuando ya tengas el pedido contigo y estés saliendo hacia la dirección del cliente, marca la entrega como en camino.</p>
        <button class="btn btn-success w-100" onclick="marcarEnCamino(${e.id_entrega})">
          <span class="material-symbols-rounded align-middle" style="font-size:1rem;">two_wheeler</span> Marcar en camino
        </button>
      </div>`;
  } else if (estado === 'EN_CAMINO') {
    renderPanelResultado(cont);
  } else {
    // Estados finales: solo mostrar el resumen de cómo quedó.
    const filas = [];
    if (e.fecha_entrega_real) filas.push(['Fecha de cierre', new Date(e.fecha_entrega_real).toLocaleString('es-DO')]);
    if (e.nombre_quien_recibe) filas.push(['Recibió', e.nombre_quien_recibe + (e.identificacion_quien_recibe ? ' ('+e.identificacion_quien_recibe+')' : '')]);
    if (e.motivo_fallida_nombre) filas.push(['Motivo', e.motivo_fallida_nombre]);
    if (e.motivo_cancelacion_nombre) filas.push(['Motivo de cancelación', e.motivo_cancelacion_nombre]);
    if (e.detalle_parcial) filas.push(['Detalle', e.detalle_parcial]);
    if (e.calificacion) filas.push(['Calificación del cliente', '★'.repeat(e.calificacion)]);
    cont.innerHTML = `
      <div class="card-d p-3">
        <h6 class="fw-bold text-success mb-3"><span class="material-symbols-rounded align-middle me-1" style="font-size:1.1rem;">task_alt</span>Resultado</h6>
        <div class="mb-3">Estado final: ${BADGE[estado] || estado}</div>
        ${filas.map(([l,v]) => `<div class="met-info-row"><span class="met-info-label">${l}:</span><span class="met-info-val">${v}</span></div>`).join('') || '<span class="text-muted">Sin más detalles.</span>'}
      </div>`;
  }
}

function marcarEnCamino(id_entrega) {
  fetch(BASE_URL + '/backend/delivery/actualizar_estado_entrega.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ id_entrega, estado: 'EN_CAMINO' })
  }).then(r=>r.json()).then(data => {
    if (data.success) {
      modalEntrega.hide();
      Swal.fire({title:'Ya vas en camino', icon:'success', timer:1300, showConfirmButton:false});
      cargarAgenda();
    } else Swal.fire('Error', data.message, 'error');
  }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
}

// ══════════════ Panel: Registrar Resultado de Entrega (EXITOSA/FALLIDA) ══════════════

let motivosFallidaCache = [];

function renderPanelResultado(cont) {
  cont.innerHTML = `
    <div class="card-d p-3">
      <h6 class="fw-bold text-success mb-2"><span class="material-symbols-rounded align-middle me-1" style="font-size:1.1rem;">task_alt</span>Registrar Resultado de Entrega</h6>
      <p class="text-muted small mb-3">Indica el resultado de la entrega. Esta acción no se puede deshacer.</p>

      <label class="fw-semibold mb-2 small">Resultado:</label>
      <div class="met-radio-card" id="cardExitosa" onclick="seleccionarResultado('EXITOSA')">
        <input type="radio" name="resultado" value="EXITOSA" id="rExitosa">
        <label for="rExitosa" style="cursor:pointer;margin:0;">
          <span class="text-success fw-semibold"><span class="material-symbols-rounded align-middle" style="font-size:1rem;">check_circle</span> Entrega Exitosa</span>
          <div class="text-muted" style="font-size:.75rem;">El cliente recibió el pedido (completo o parcial)</div>
        </label>
      </div>
      <div class="met-radio-card" id="cardFallida" onclick="seleccionarResultado('FALLIDA')">
        <input type="radio" name="resultado" value="FALLIDA" id="rFallida">
        <label for="rFallida" style="cursor:pointer;margin:0;">
          <span class="text-danger fw-semibold"><span class="material-symbols-rounded align-middle" style="font-size:1rem;">cancel</span> Entrega Fallida</span>
          <div class="text-muted" style="font-size:.75rem;">No se pudo entregar nada</div>
        </label>
      </div>

      <div id="bloqueExitosa" style="display:none;">
        <div class="mt-3 mb-2">
          <label class="fw-semibold small">Confirma la cantidad entregada de cada producto:</label>
          <div id="tablaCantidades" class="mt-2"></div>
          <small class="text-muted" id="hintParcial" style="display:none;"></small>
        </div>
        <div class="mb-2 mt-3">
          <label class="form-label fw-semibold small">Nombre de quien recibe: <span class="text-danger">*</span></label>
          <input type="text" id="receptor" class="form-control form-control-sm" placeholder="Nombre completo">
        </div>
        <div class="mb-2">
          <label class="form-label fw-semibold small">Cédula de quien recibe: <span class="text-danger">*</span></label>
          <input type="text" id="cedula" class="form-control form-control-sm" placeholder="000-0000000-0">
        </div>
        <div class="mb-2">
          <label class="form-label fw-semibold small">Observaciones (opcional):</label>
          <textarea id="observaciones" class="form-control form-control-sm" rows="2"></textarea>
        </div>
      </div>

      <div id="bloqueFallida" style="display:none;">
        <div class="mb-2 mt-3">
          <label class="form-label fw-semibold small">Motivo: <span class="text-danger">*</span></label>
          <select id="motivoFallidaSelect" class="form-select form-select-sm"><option value="">Cargando motivos...</option></select>
        </div>
        <div class="mb-2">
          <label class="form-label fw-semibold small">Detalle adicional (opcional):</label>
          <textarea id="detalleFallida" class="form-control form-control-sm" rows="2"></textarea>
        </div>
      </div>

      <button class="btn btn-success w-100 mt-2" onclick="confirmarResultadoEntrega()">
        <span class="material-symbols-rounded align-middle" style="font-size:1rem;">check</span> Registrar Confirmación
      </button>
      <button class="btn btn-link btn-sm w-100 mt-1 text-danger" onclick="abrirProblema(${entregaActual.id_entrega})">
        ¿Tuviste un accidente o no puedes continuar la ruta?
      </button>
    </div>`;
  cargarMotivosFallida();
}

function seleccionarResultado(val) {
  document.getElementById('rExitosa').checked = (val === 'EXITOSA');
  document.getElementById('rFallida').checked = (val === 'FALLIDA');
  document.getElementById('cardExitosa').className = 'met-radio-card' + (val === 'EXITOSA' ? ' sel-exitosa' : '');
  document.getElementById('cardFallida').className = 'met-radio-card' + (val === 'FALLIDA' ? ' sel-fallida' : '');
  document.getElementById('bloqueExitosa').style.display = (val === 'EXITOSA') ? 'block' : 'none';
  document.getElementById('bloqueFallida').style.display = (val === 'FALLIDA') ? 'block' : 'none';
  if (val === 'EXITOSA') renderTablaCantidades();
}

function renderTablaCantidades() {
  const rows = productosActual.map((p, i) => `
    <div class="met-cant-row">
      <div style="font-size:.8rem;">
        <div class="fw-semibold">${p.producto_nombre}</div>
        <div class="text-muted">Pedido: ${p.cantidad} uds.${p.cantidad_pendiente < p.cantidad ? ' (pendiente: ' + p.cantidad_pendiente + ')' : ''}</div>
      </div>
      <input type="number" class="form-control form-control-sm" style="width:80px;" id="cant_${i}"
             min="0" max="${p.cantidad_pendiente}" value="${p.cantidad_pendiente}" oninput="verificarParcial()">
    </div>`).join('');
  document.getElementById('tablaCantidades').innerHTML = rows;
  verificarParcial();
}

function verificarParcial() {
  let hayParcial = false, totalEntregado = 0;
  productosActual.forEach((p, i) => {
    const inp = document.getElementById('cant_' + i);
    if (!inp) return;
    let val = parseInt(inp.value);
    if (isNaN(val) || val < 0) val = 0;
    if (val > p.cantidad_pendiente) val = p.cantidad_pendiente;
    inp.value = val;
    totalEntregado += val;
    if (val < p.cantidad_pendiente) hayParcial = true;
  });
  const hint = document.getElementById('hintParcial');
  if (totalEntregado === 0) {
    hint.style.display = 'block';
    hint.innerHTML = '<span class="text-danger fw-semibold">Todas las cantidades están en 0.</span> Si no se entregó nada, usa "Entrega Fallida" en vez de esto.';
  } else if (hayParcial) {
    hint.style.display = 'block';
    hint.innerHTML = 'Si alguna cantidad es menor a la pedida, la entrega quedará marcada como <strong>Parcial</strong> automáticamente.';
  } else {
    hint.style.display = 'none';
  }
}

function cargarMotivosFallida() {
  fetch(BASE_URL + '/backend/delivery/listar_motivos_fallida.php').then(r=>r.json()).then(data => {
    const sel = document.getElementById('motivoFallidaSelect');
    if (!sel) return;
    if (!data.success || !data.motivos.length) { sel.innerHTML = '<option value="">No se pudieron cargar</option>'; return; }
    motivosFallidaCache = data.motivos;
    sel.innerHTML = '<option value="">Selecciona un motivo...</option>' + data.motivos.map(m => `<option value="${m.id_motivo}">${m.nombre}</option>`).join('');
  });
}

function confirmarResultadoEntrega() {
  const resultado = document.querySelector('input[name="resultado"]:checked')?.value;
  if (!resultado) { Swal.fire('Falta información', 'Selecciona el resultado de la entrega.', 'warning'); return; }

  const payload = { id_entrega: entregaActual.id_entrega, resultado };
  let tituloConfirm = '';

  if (resultado === 'FALLIDA') {
    const id_motivo_fallida = document.getElementById('motivoFallidaSelect').value;
    if (!id_motivo_fallida) { Swal.fire('Falta información', 'Selecciona el motivo por el cual no se pudo completar la entrega.', 'warning'); return; }
    payload.id_motivo_fallida = parseInt(id_motivo_fallida);
    payload.detalle_fallida = document.getElementById('detalleFallida').value.trim();
    tituloConfirm = '¿Reportar entrega fallida?';
  } else {
    const receptor = document.getElementById('receptor').value.trim();
    const cedula_receptor = document.getElementById('cedula').value.trim();
    if (!receptor || !cedula_receptor) { Swal.fire('Falta información', 'Debes indicar el nombre y la cédula de quien recibió el pedido.', 'warning'); return; }

    const productos = productosActual.map((p, i) => ({
      id_detalle: p.id_detalle,
      cantidad_entregada: parseInt(document.getElementById('cant_' + i)?.value || 0),
    }));
    const totalEntregado = productos.reduce((s, p) => s + p.cantidad_entregada, 0);
    if (totalEntregado === 0) { Swal.fire('Cantidades en cero', 'No se puede confirmar como "Entrega Exitosa" si no se entregó ningún producto. Usa "Entrega Fallida".', 'warning'); return; }

    payload.nombre_receptor = receptor;
    payload.cedula_receptor = cedula_receptor;
    payload.observaciones = document.getElementById('observaciones').value.trim();
    payload.productos = productos;
    tituloConfirm = productos.some((p, i) => p.cantidad_entregada < productosActual[i].cantidad_pendiente) ? '¿Confirmar entrega parcial?' : '¿Confirmar entrega completa?';
  }

  Swal.fire({
    title: tituloConfirm, icon: resultado === 'EXITOSA' ? 'question' : 'warning',
    showCancelButton: true, confirmButtonText: 'Sí, registrar', cancelButtonText: 'Cancelar',
    confirmButtonColor: resultado === 'EXITOSA' ? '#28a745' : '#DC3545',
  }).then(r => {
    if (!r.isConfirmed) return;
    fetch(BASE_URL + '/backend/delivery/confirmar_entrega_delivery.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload),
    }).then(r => r.json()).then(data => {
      if (data.success) {
        modalEntrega.hide();
        const disputaMsg = data.estado_recepcion === 'EN_DISPUTA' ? ' Se marcó en disputa por diferencia en el receptor.' : '';
        Swal.fire({title: `Entrega marcada como ${data.estado_final}`, text: disputaMsg || undefined, icon: data.estado_recepcion === 'EN_DISPUTA' ? 'warning' : 'success', timer: 2200, showConfirmButton: false});
        cargarAgenda();
      } else Swal.fire('Error', data.message, 'error');
    }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
  });
}

// ══════════════ Modal: registrar devolución en la entrega ══════════════

function abrirModalDevolucion() {
  if (!productosActual.length) {
    Swal.fire('Sin productos', 'Esta entrega no tiene productos para devolver.', 'info');
    return;
  }
  const rows = productosActual.map((p, i) => `
    <div class="met-cant-row">
      <div style="font-size:.8rem;">
        <div class="fw-semibold">${p.producto_nombre}</div>
        <div class="text-muted">Pedido: ${p.cantidad} uds.</div>
      </div>
      <input type="number" class="form-control form-control-sm" style="width:80px;" id="devcant_${i}"
             min="0" max="${p.cantidad}" value="0">
    </div>`).join('');
  document.getElementById('tablaDevolucion').innerHTML = rows;
  document.getElementById('detalleDevolucion').value = '';
  document.getElementById('motivoDevolucionSelect').innerHTML = '<option value="">Cargando motivos...</option>';
  cargarMotivosDevolucion();
  modalDevolucion.show();
}

function cargarMotivosDevolucion() {
  fetch(BASE_URL + '/backend/delivery/listar_motivos_devolucion_delivery.php').then(r=>r.json()).then(data => {
    const sel = document.getElementById('motivoDevolucionSelect');
    if (!sel) return;
    if (!data.success || !data.motivos.length) { sel.innerHTML = '<option value="">No se pudieron cargar</option>'; return; }
    sel.innerHTML = '<option value="">Selecciona un motivo...</option>' + data.motivos.map(m => `<option value="${m.id_motivo}">${m.nombre}</option>`).join('');
  });
}

function registrarDevolucion() {
  const id_motivo = document.getElementById('motivoDevolucionSelect').value;
  if (!id_motivo) { Swal.fire('Falta información', 'Selecciona el motivo de la devolución.', 'warning'); return; }

  const items = productosActual.map((p, i) => ({
    id_detalle: p.id_detalle,
    cantidad: parseInt(document.getElementById('devcant_' + i)?.value || 0)
  })).filter(i => i.cantidad > 0);

  if (!items.length) { Swal.fire('Falta información', 'Indica la cantidad de al menos un producto devuelto.', 'warning'); return; }

  const detalle = document.getElementById('detalleDevolucion').value.trim();

  Swal.fire({ title: '¿Registrar esta devolución?', text: 'Esta acción quedará registrada en Inventario > Devoluciones.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, registrar', cancelButtonText: 'Cancelar', confirmButtonColor: '#DC3545' })
  .then(r => {
    if (!r.isConfirmed) return;
    fetch(BASE_URL + '/backend/delivery/registrar_devolucion_entrega.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id_entrega: entregaActual.id_entrega, id_motivo: parseInt(id_motivo), detalle, items })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        modalDevolucion.hide();
        if (data.entrega_cerrada) {
          // No quedó nada pendiente por confirmar — el cliente devolvió
          // todo el pedido, así que la entrega se cerró sola.
          modalEntrega.hide();
          Swal.fire({
            title: '¡Devolución registrada!',
            text: `Documento ${data.numero_documento}. Como el cliente devolvió todo el pedido, la entrega se cerró automáticamente.`,
            icon: 'success',
          });
          cargarAgenda();
        } else {
          // Todavía queda algo pendiente por confirmar en esta entrega —
          // se refresca el detalle para que las cantidades disponibles ya
          // no incluyan lo que se acaba de devolver.
          Swal.fire({title: '¡Devolución registrada!', text: `Documento ${data.numero_documento} — quedó en Inventario > Devoluciones para su aprobación.`, icon: 'success', timer: 2500, showConfirmButton: false});
          abrirDetalle(entregaActual.id_entrega);
        }
      } else {
        Swal.fire('Error', data.message, 'error');
      }
    })
    .catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
  });
}

// ══════════════ Modal: reportar interrupción de ruta ══════════════

function abrirProblema(id_entrega) {
  document.getElementById('probIdEntrega').value = id_entrega;
  document.getElementById('probDetalle').value = '';
  modalEntrega.hide();
  modalProblema.show();
}

function enviarProblema() {
  const id_entrega = document.getElementById('probIdEntrega').value;
  const motivo = document.getElementById('probDetalle').value.trim();
  if (!motivo) { Swal.fire('Falta información', 'Cuéntanos qué pasó.', 'warning'); return; }

  fetch(BASE_URL + '/backend/delivery/actualizar_estado_entrega.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ id_entrega: parseInt(id_entrega), estado: 'INTERRUMPIDA', motivo })
  }).then(r=>r.json()).then(data => {
    if (data.success) {
      modalProblema.hide();
      Swal.fire({title:'Reporte enviado', icon:'success', timer:1500, showConfirmButton:false});
      cargarAgenda();
    } else Swal.fire('Error', data.message, 'error');
  }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
}
</script>
