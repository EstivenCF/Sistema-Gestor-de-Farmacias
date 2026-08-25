<?php
// frontend/portal_cliente/mis_entregas.php — NUEVO
// Portal del cliente: ve sus entregas, puede cancelar, y cuando el
// repartidor confirma la entrega, aquí la confirma y la califica.
require_once __DIR__ . '/../../backend/conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['id_cliente_portal'])) { header("Location: login.php"); exit(); }
$nombre_cliente = $_SESSION['nombre_cliente_portal'] ?? 'Cliente';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mis Pedidos — PharmaSystem</title>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
  .material-symbols-rounded{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;vertical-align:middle;}
  :root{--primary:#1F5C99;--bg:#F0F2F5;}
  *{font-family:"Poppins",sans-serif;box-sizing:border-box;}
  body{background:var(--bg);margin:0;}
  .topbar{background:var(--primary);padding:0 1.25rem;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:1000;}
  .topbar .brand{color:#fff;font-weight:700;display:flex;align-items:center;gap:.5rem;}
  .card-d{background:#fff;border-radius:14px;box-shadow:0 1px 6px rgba(0,0,0,.07);}
  .badge-st{font-size:.72rem;padding:.35em .7em;border-radius:20px;font-weight:700;}
  .st-PENDIENTE{background:#EAF1F8;color:#1F5C99;} .st-ASIGNADA{background:#E0E7FF;color:#4338CA;}
  .st-EN_CAMINO{background:#FFF3CD;color:#C77B00;} .st-ENTREGADA{background:#D4EDDA;color:#2E8B57;}
  .pedido-card{border-left:4px solid #dee2e6;}
  .pedido-card.destacar{border-left-color:#28a745;background:#f6fffa;}
  .estrellas{cursor:pointer;font-size:1.8rem;letter-spacing:.15rem;}
  .estrellas span{color:#dee2e6;transition:color .1s;}
  .estrellas span.activa{color:#FFC107;}
  .bloque-calif{background:#f8f9fa;border-radius:10px;padding:1rem;margin-bottom:1rem;}
  .campana-btn{position:relative;background:none;border:none;color:#fff;cursor:pointer;padding:.25rem;}
  .campana-badge{position:absolute;top:-2px;right:-2px;background:#DC3545;color:#fff;border-radius:50%;font-size:.62rem;min-width:16px;height:16px;display:flex;align-items:center;justify-content:center;padding:0 3px;font-weight:700;}
  .panel-notif{position:absolute;right:0;top:44px;width:340px;max-width:92vw;background:#fff;border-radius:12px;box-shadow:0 8px 28px rgba(0,0,0,.18);z-index:2000;max-height:70vh;overflow-y:auto;display:none;}
  .panel-notif.abierto{display:block;}
  .notif-item{padding:.75rem 1rem;border-bottom:1px solid #f0f0f0;cursor:pointer;font-size:.82rem;}
  .notif-item:last-child{border-bottom:none;}
  .notif-item.no-leida{background:#EEF5FF;}
  .notif-item .titulo{font-weight:700;color:#222;margin-bottom:.15rem;}
  .notif-item .fecha{color:#999;font-size:.7rem;margin-top:.25rem;}
  .notif-header{padding:.65rem 1rem;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;}
</style>
</head>
<body>
<nav class="topbar">
  <div class="brand"><span class="material-symbols-rounded">local_shipping</span> Mis Pedidos</div>
  <div class="d-flex align-items-center gap-3" style="color:#ADE8F4;font-size:.85rem;position:relative;">
    <button class="campana-btn" onclick="toggleNotificaciones()" title="Notificaciones">
      <span class="material-symbols-rounded">notifications</span>
      <span class="campana-badge" id="campanaBadge" style="display:none;">0</span>
    </button>
    <div class="panel-notif" id="panelNotif">
      <div class="notif-header">
        <strong style="font-size:.85rem;color:#222;">Notificaciones</strong>
        <a href="#" style="font-size:.75rem;" onclick="marcarTodasLeidas();return false;">Marcar todas como leídas</a>
      </div>
      <div id="listaNotificaciones"><div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div></div>
    </div>
    <span><?=htmlspecialchars($nombre_cliente)?></span>
    <a href="#" onclick="cerrarSesion()" style="color:#fff;text-decoration:none;"><span class="material-symbols-rounded" style="font-size:1rem;">logout</span> Salir</a>
  </div>
</nav>

<div class="container-fluid p-3" style="max-width:900px;margin:0 auto;">
  <div id="listaEntregas"><div class="text-center py-5"><div class="spinner-border text-primary"></div></div></div>
</div>

<!-- MODAL: cancelar entrega -->
<div class="modal fade" id="modalCancelar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h6 class="modal-title mb-0">Cancelar pedido</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="cancIdEntrega">
        <label class="form-label small fw-semibold">¿Por qué quieres cancelar? *</label>
        <select class="form-select mb-3" id="cancMotivo"><option value="">Selecciona...</option></select>
        <label class="form-label small fw-semibold">Comentario (opcional)</label>
        <textarea class="form-control" id="cancComentario" rows="3" placeholder="Cuéntanos más, si quieres"></textarea>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
        <button class="btn btn-danger" onclick="confirmarCancelacion()">Sí, cancelar pedido</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: calificar (paso 2, aparte de confirmar recepción) -->
<div class="modal fade" id="modalCalificar" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">star_rate</span> Califica tu experiencia</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="califIdEntrega">
        <p class="text-muted small">Cuéntanos cómo te fue con este pedido — nos ayuda muchísimo.</p>

        <div id="califPreguntas"><div class="text-center py-3"><div class="spinner-border text-success spinner-border-sm"></div></div></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success w-100" onclick="enviarCalificacion()">
          <span class="material-symbols-rounded align-middle me-1">send</span> Enviar calificación
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: confirmar cantidades recibidas (paso 1, "Sí") -->
<div class="modal fade" id="modalConfirmarCantidades" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">inventory_2</span> ¿Cuánto recibiste de cada medicamento?</h6>
      </div>
      <div class="modal-body">
        <input type="hidden" id="confCantIdEntrega">
        <p class="text-muted small mb-3">Revisa la cantidad de cada producto — ya viene puesta la cantidad completa, cámbiala solo si te faltó algo.</p>
        <div id="confCantProductos"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
        <button class="btn btn-success" id="btnConfirmarCantidades" onclick="enviarConfirmarCantidades()">
          <span class="material-symbols-rounded align-middle" style="font-size:1rem;">check</span> Confirmar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: no recibí el pedido -->
<div class="modal fade" id="modalNoRecibido" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">report</span> Algo está mal con esta entrega</h6>
      </div>
      <div class="modal-body">
        <input type="hidden" id="noRecIdEntrega">
        <p class="text-muted small">El repartidor marcó este pedido como entregado, pero cuéntanos qué pasó de verdad — no llegó nadie, llegó incompleto, o no fuiste tú (ni alguien autorizado) quien lo recibió.</p>
        <label class="form-label small fw-semibold">¿Qué pasó? *</label>
        <textarea class="form-control" id="noRecComentario" rows="3" placeholder="Ej: nadie tocó mi puerta, no recibí nada. / Lo recibió alguien que yo no autoricé."></textarea>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
        <button class="btn btn-danger" onclick="enviarNoRecibido()">Enviar reporte</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: confirmar devolución -->
<div class="modal fade" id="modalDevolucion" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">assignment_return</span> Confirmar devolución</h6>
      </div>
      <div class="modal-body">
        <input type="hidden" id="devIdDevolucion">
        <p class="small text-muted mb-1">Tu repartidor registró esta devolución:</p>
        <div class="bg-light rounded p-2 mb-3" style="font-size:.85rem;">
          <div id="devProductos" class="mb-1"></div>
          <div><strong>Motivo:</strong> <span id="devMotivo"></span></div>
        </div>
        <p class="fw-semibold small mb-2">¿Es correcto? ¿De verdad devolviste esos productos?</p>
        <label class="form-label small fw-semibold" id="devNotaLabel">Comentario (opcional)</label>
        <textarea class="form-control" id="devNota" rows="2" placeholder="Ej: sí, el frasco llegó roto."></textarea>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-danger" onclick="responderDevolucion('NO')">No es correcto</button>
        <button class="btn btn-success" onclick="responderDevolucion('SI')">Sí, es correcto</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let modalCancelar, modalCalificar, modalDevolucion, modalNoRecibido, modalConfirmarCantidades;
let preguntasCalifCache = [];   // catálogo activo, traído del servidor
const respuestasCliente = {};   // id_pregunta -> valor (int 1-5 o string)
const productosDespachadosCache = {}; // id_entrega -> productos despachados en la ronda actual

document.addEventListener('DOMContentLoaded', () => {
  modalCancelar = new bootstrap.Modal('#modalCancelar');
  modalCalificar = new bootstrap.Modal('#modalCalificar');
  modalDevolucion = new bootstrap.Modal('#modalDevolucion');
  modalNoRecibido = new bootstrap.Modal('#modalNoRecibido');
  modalConfirmarCantidades = new bootstrap.Modal('#modalConfirmarCantidades');
  cargar();
  cargarMotivos();
  cargarNotificaciones();
  // Sin push real todavía (ver backend/notificaciones), así que se
  // refresca solo cada 30s mientras el cliente tiene el portal abierto —
  // suficiente para que se sienta "proactivo" sin tener que recargar.
  setInterval(cargarNotificaciones, 30000);
  document.addEventListener('click', (ev) => {
    const panel = document.getElementById('panelNotif');
    if (panel.classList.contains('abierto') && !ev.target.closest('.panel-notif') && !ev.target.closest('.campana-btn')) {
      panel.classList.remove('abierto');
    }
  });
});

// ══════════════ Notificaciones (PATCH 31/31) ══════════════

let notifCache = [];

function toggleNotificaciones() {
  const panel = document.getElementById('panelNotif');
  panel.classList.toggle('abierto');
  if (panel.classList.contains('abierto')) cargarNotificaciones();
}

function cargarNotificaciones() {
  fetch('../../backend/portal_cliente/listar_notificaciones.php').then(r => r.json()).then(data => {
    if (!data.success) return;
    notifCache = data.notificaciones;
    const badge = document.getElementById('campanaBadge');
    if (data.no_leidas > 0) {
      badge.style.display = 'flex';
      badge.textContent = data.no_leidas > 9 ? '9+' : data.no_leidas;
    } else {
      badge.style.display = 'none';
    }
    renderNotificaciones();
  }).catch(() => {});
}

const ICONO_NOTIF = {
  ASIGNADA: 'two_wheeler', EN_COLA: 'hourglass_top', EN_CAMINO: 'local_shipping',
  ENTREGADA: 'check_circle', PARCIAL: 'inventory_2', ATRASADA: 'schedule',
  REPROGRAMADA: 'event_repeat', FALLIDA: 'error', CANCELADA: 'cancel', INTERRUMPIDA: 'pause_circle',
};

function tiempoRelativo(fechaStr) {
  const diffMs = new Date() - new Date(fechaStr.replace(' ', 'T'));
  const mins = Math.floor(diffMs / 60000);
  if (mins < 1) return 'ahora mismo';
  if (mins < 60) return `hace ${mins} min`;
  const horas = Math.floor(mins / 60);
  if (horas < 24) return `hace ${horas} h`;
  return `hace ${Math.floor(horas / 24)} d`;
}

function renderNotificaciones() {
  const cont = document.getElementById('listaNotificaciones');
  if (!notifCache.length) {
    cont.innerHTML = '<p class="text-muted text-center small py-4 mb-0">No tienes notificaciones todavía.</p>';
    return;
  }
  cont.innerHTML = notifCache.map(n => `
    <div class="notif-item ${!n.leida ? 'no-leida' : ''}" onclick="marcarLeida(${n.id_notificacion})">
      <div class="titulo"><span class="material-symbols-rounded align-middle" style="font-size:1rem;">${ICONO_NOTIF[n.tipo] || 'notifications'}</span> ${escapeHtmlNotif(n.titulo)}</div>
      <div>${escapeHtmlNotif(n.mensaje)}</div>
      <div class="fecha">${tiempoRelativo(n.fecha_creacion)}</div>
    </div>
  `).join('');
}

function escapeHtmlNotif(str) {
  const d = document.createElement('div');
  d.textContent = str || '';
  return d.innerHTML;
}

function marcarLeida(id) {
  fetch('../../backend/portal_cliente/marcar_notificacion_leida.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ id_notificacion: id })
  }).then(() => cargarNotificaciones());
}

function marcarTodasLeidas() {
  fetch('../../backend/portal_cliente/marcar_notificacion_leida.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ todas: true })
  }).then(() => cargarNotificaciones());
}

function cargarPreguntasCalificacion() {
  return fetch('../../backend/portal_cliente/listar_preguntas_calificacion.php')
    .then(r => r.json())
    .then(data => {
      preguntasCalifCache = (data.success && data.preguntas) ? data.preguntas : [];
    });
}

function renderPreguntasCalificacion() {
  const grupos = {};
  preguntasCalifCache.forEach(p => { (grupos[p.categoria] = grupos[p.categoria] || []).push(p); });

  const iconoCategoria = { 'Repartidor': 'two_wheeler', 'Producto': 'medication', 'Medicamento': 'medication', 'Envío': 'local_shipping' };

  const html = Object.entries(grupos).map(([categoria, preguntas]) => `
    <div class="bloque-calif">
      <strong class="d-block mb-2"><span class="material-symbols-rounded align-middle">${iconoCategoria[categoria] || 'fact_check'}</span> ${categoria}</strong>
      ${preguntas.map(p => `
        <label class="small text-muted d-block mb-1">${p.texto}${p.obligatoria ? '' : ' (opcional)'}</label>
        ${p.tipo_respuesta === 'ESTRELLAS'
          ? `<div class="estrellas mb-2" data-id-pregunta="${p.id_pregunta}">${[1,2,3,4,5].map(i => `<span data-val="${i}">★</span>`).join('')}</div>`
          : `<textarea class="form-control mb-2" rows="2" data-id-pregunta="${p.id_pregunta}" onchange="respuestasCliente[${p.id_pregunta}] = this.value.trim()"></textarea>`}
      `).join('')}
    </div>`).join('');

  document.getElementById('califPreguntas').innerHTML = html || '<p class="text-muted small">No hay preguntas configuradas todavía.</p>';

  document.querySelectorAll('#califPreguntas .estrellas').forEach(cont => {
    const idPregunta = cont.dataset.idPregunta;
    cont.querySelectorAll('span').forEach(sp => {
      sp.addEventListener('click', () => {
        const val = parseInt(sp.dataset.val);
        respuestasCliente[idPregunta] = val;
        cont.querySelectorAll('span').forEach(s2 => s2.classList.toggle('activa', parseInt(s2.dataset.val) <= val));
      });
    });
  });
}

function cerrarSesion() {
  fetch('../../backend/portal_cliente/logout.php').finally(() => window.location.href = 'login.php');
}

const ESTADO_LABEL = { PENDIENTE:'Pendiente', ASIGNADA:'Asignada', EN_CAMINO:'En camino', ENTREGADA:'Entregada' };
const devolucionesCache = {}; // id_devolucion -> datos, para no meter JSON crudo en el HTML

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>"']/g, m => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[m]));
}

function cargar() {
  fetch('../../backend/portal_cliente/listar_mis_entregas.php').then(r=>r.json()).then(data => {
    const cont = document.getElementById('listaEntregas');
    if (!data.success) { cont.innerHTML = `<div class="alert alert-danger">${data.message}</div>`; return; }
    if (!data.entregas.length) {
      cont.innerHTML = '<div class="text-center text-muted py-5"><span class="material-symbols-rounded" style="font-size:3rem;">inbox</span><br>No tienes pedidos con delivery en este momento.</div>';
      return;
    }
    data.entregas.forEach(e => (e.devoluciones_pendientes || []).forEach(d => devolucionesCache[d.id_devolucion] = d));
    data.entregas.forEach(e => productosDespachadosCache[e.id_entrega] = e.productos_despachados || []);
    cont.innerHTML = data.entregas.map(e => {
      const productos = (e.productos||[]).map(p => `${p.nombre} (${p.cantidad})`).join(', ') || 'Sin detalle';
      const horaAcordada = e.fecha_programada ? new Date(e.fecha_programada).toLocaleString('es-DO') : 'Sin definir todavía';
      const atrasada = e.entrega_atrasada === true || e.entrega_atrasada === 't' || e.entrega_atrasada === 1;
      let etaHtml = '';
      if (e.hora_estimada_llegada) {
        const horaEta = new Date(e.hora_estimada_llegada.replace(' ', 'T')).toLocaleTimeString('es-DO', { hour: '2-digit', minute: '2-digit' });
        etaHtml = `
          <div class="alert ${atrasada ? 'alert-danger' : 'alert-light border'} mt-2 mb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>
              <span class="material-symbols-rounded align-middle">schedule</span>
              <strong>${atrasada ? 'Tu pedido está atrasado' : 'Llegaría aprox. a las'}:</strong> ${horaEta}
            </span>
            ${atrasada && e.repartidor_telefono ? `<a class="btn btn-sm btn-danger" href="tel:${e.repartidor_telefono}"><span class="material-symbols-rounded align-middle" style="font-size:1rem;">call</span> Llamar al repartidor</a>` : ''}
          </div>`;
      }
      return `
        <div class="card-d p-3 mb-3 pedido-card ${e.pendiente_confirmar_cliente ? 'destacar' : ''}">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
              <strong class="text-primary">${e.numero_seguimiento}</strong>
              <span class="badge-st st-${e.estado_nombre} ms-2">${ESTADO_LABEL[e.estado_nombre] || e.estado_nombre}</span>
            </div>
            <small class="text-muted">Factura ${e.numero_documento}</small>
          </div>
          <div class="row g-2 mt-1" style="font-size:.85rem;">
            <div class="col-md-6"><strong>Productos:</strong> ${productos}</div>
            <div class="col-md-6"><strong>Dirección:</strong> ${e.direccion_entrega}${e.barrio_entrega ? ', '+e.barrio_entrega : ''}</div>
            <div class="col-md-6"><strong>Hora acordada:</strong> ${horaAcordada}</div>
            <div class="col-md-6"><strong>Repartidor:</strong> ${e.repartidor_nombre || 'Todavía sin asignar'}</div>
            <div class="col-md-6"><strong>Despachado por:</strong> ${e.despachado_por || '—'}</div>
            <div class="col-md-6"><strong>Costo de envío:</strong> RD$ ${parseFloat(e.costo_entrega).toFixed(2)}</div>
          </div>
          ${etaHtml}
          ${e.puede_cancelar ? `
            <button class="btn btn-sm btn-outline-danger mt-2" onclick="abrirCancelar(${e.id_entrega})">
              <span class="material-symbols-rounded align-middle" style="font-size:1rem;">cancel</span> Cancelar pedido
            </button>` : ''}
          ${e.pendiente_confirmar_cliente ? `
            <div class="alert alert-success mt-2 mb-0">
              <div class="mb-2"><span class="material-symbols-rounded align-middle">notifications_active</span> Tu repartidor marcó este pedido como entregado. ¿Te lo entregaron a ti (o a alguien autorizado por ti)?</div>
              <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-sm btn-success" onclick="abrirConfirmarCantidades(${e.id_entrega})">Sí, todo bien</button>
                <button class="btn btn-sm btn-outline-danger" onclick="abrirNoRecibido(${e.id_entrega})">No, algo está mal</button>
              </div>
            </div>` : ''}
          ${e.pendiente_calificar ? `
            <div class="alert alert-light border mt-2 mb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
              <span><span class="material-symbols-rounded align-middle">star_rate</span> ¿Nos regalas un minuto para calificar cómo te fue?</span>
              <button class="btn btn-sm btn-outline-success" onclick="abrirCalificar(${e.id_entrega})">Calificar ahora</button>
            </div>` : ''}
          ${e.estado_recepcion === 'EN_DISPUTA' ? `
            <div class="alert alert-danger mt-2 mb-0">
              <span class="material-symbols-rounded align-middle">report</span> <strong>Reportaste que no recibiste este pedido.</strong> La farmacia va a llamarte para aclarar la situación.
              ${e.comentario_cliente ? `<div class="small mt-1">Tu reporte: "${escapeHtml(e.comentario_cliente)}"</div>` : ''}
            </div>` : ''}
          ${(e.devoluciones_pendientes || []).map(d => `
            <div class="alert alert-warning mt-2 mb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
              <span><span class="material-symbols-rounded align-middle">assignment_return</span> Tu repartidor registró una devolución en este pedido — necesitamos que la confirmes.</span>
              <button class="btn btn-sm btn-warning" onclick="abrirDevolucion(${d.id_devolucion})">Responder</button>
            </div>`).join('')}
        </div>`;
    }).join('');
  });
}

function cargarMotivos() {
  fetch('../../backend/portal_cliente/listar_motivos_cancelacion.php').then(r=>r.json()).then(data => {
    if (!data.success) return;
    document.getElementById('cancMotivo').innerHTML = '<option value="">Selecciona...</option>' +
      data.motivos.map(m => `<option value="${m.id_motivo}">${m.nombre}</option>`).join('');
  });
}

function abrirCancelar(idEntrega) {
  document.getElementById('cancIdEntrega').value = idEntrega;
  document.getElementById('cancMotivo').value = '';
  document.getElementById('cancComentario').value = '';
  modalCancelar.show();
}

function confirmarCancelacion() {
  const id_entrega = document.getElementById('cancIdEntrega').value;
  const id_motivo = document.getElementById('cancMotivo').value;
  const comentario = document.getElementById('cancComentario').value.trim();
  if (!id_motivo) { Swal.fire('Falta información', 'Selecciona el motivo de la cancelación.', 'warning'); return; }

  fetch('../../backend/portal_cliente/cancelar_entrega.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ id_entrega: parseInt(id_entrega), id_motivo: parseInt(id_motivo), comentario })
  }).then(r=>r.json()).then(data => {
    if (data.success) {
      modalCancelar.hide();
      Swal.fire({title:'Pedido cancelado', icon:'success', timer:1500, showConfirmButton:false});
      cargar();
    } else Swal.fire('Error', data.message, 'error');
  }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
}

function abrirConfirmarCantidades(idEntrega) {
  const productos = productosDespachadosCache[idEntrega] || [];
  document.getElementById('confCantIdEntrega').value = idEntrega;
  document.getElementById('confCantProductos').innerHTML = productos.length
    ? productos.map((p, i) => `
        <div class="row g-2 align-items-center mb-2">
          <div class="col-8">${escapeHtml(p.nombre)}</div>
          <div class="col-4">
            <input type="number" class="form-control form-control-sm cant-recibida"
                   min="0" max="${p.cantidad}" value="${p.cantidad}"
                   data-id-lote="${p.id_lote ?? ''}" data-id-producto="${p.id_producto ?? ''}">
          </div>
        </div>`).join('')
    : '<p class="text-muted small">No se encontró el detalle de productos de este pedido.</p>';
  // Por si el modal se reabre después de un intento anterior, se asegura
  // de que el botón esté habilitado otra vez.
  const btnConfirmar = document.getElementById('btnConfirmarCantidades');
  if (btnConfirmar) { btnConfirmar.disabled = false; }
  modalConfirmarCantidades.show();
}

function enviarConfirmarCantidades() {
  const btnConfirmar = document.getElementById('btnConfirmarCantidades');
  // Se desactiva el botón apenas se hace clic — evita que un doble clic
  // (o alguien impaciente con internet lento) mande la confirmación dos
  // veces y se tope con el bloqueo de "ya confirmaste esta entrega antes".
  if (btnConfirmar) { if (btnConfirmar.disabled) return; btnConfirmar.disabled = true; }

  const id_entrega = parseInt(document.getElementById('confCantIdEntrega').value);
  const productos = Array.from(document.querySelectorAll('#confCantProductos .cant-recibida')).map(input => ({
    id_lote: input.dataset.idLote ? parseInt(input.dataset.idLote) : null,
    id_producto: input.dataset.idProducto ? parseInt(input.dataset.idProducto) : null,
    cantidad_recibida: parseInt(input.value || 0),
  }));

  fetch('../../backend/portal_cliente/confirmar_recepcion.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ id_entrega, productos })
  }).then(r=>r.json()).then(data => {
    if (data.success) {
      modalConfirmarCantidades.hide();
      if (data.disputa) {
        Swal.fire({
          title: 'Reportamos la diferencia',
          text: 'Lo que indicaste no coincide con lo que se despachó — la farmacia va a llamarte para aclarar la situación.',
          icon: 'warning', confirmButtonText: 'Entendido',
        });
      } else {
        Swal.fire({title:'¡Gracias por confirmar!', icon:'success', timer:1500, showConfirmButton:false});
      }
      cargar();
    } else if (data.message === 'Ya confirmaste esta entrega antes') {
      // No es un error real (el bloqueo de seguridad hizo su trabajo) —
      // seguro fue un doble clic. Se cierra el modal en silencio y se
      // refresca la lista, que ya va a mostrar el estado correcto.
      modalConfirmarCantidades.hide();
      cargar();
    } else {
      if (btnConfirmar) { btnConfirmar.disabled = false; }
      Swal.fire('Error', data.message, 'error');
    }
  }).catch(() => {
    if (btnConfirmar) { btnConfirmar.disabled = false; }
    Swal.fire('Error', 'Error de conexión.', 'error');
  });
}

function abrirCalificar(idEntrega) {
  document.getElementById('califIdEntrega').value = idEntrega;
  Object.keys(respuestasCliente).forEach(k => delete respuestasCliente[k]);
  document.getElementById('califPreguntas').innerHTML = '<div class="text-center py-3"><div class="spinner-border text-success spinner-border-sm"></div></div>';
  modalCalificar.show();
  cargarPreguntasCalificacion().then(renderPreguntasCalificacion);
}

function enviarCalificacion() {
  const respuestas = [];
  for (const p of preguntasCalifCache) {
    const valor = respuestasCliente[p.id_pregunta];
    if (p.tipo_respuesta === 'ESTRELLAS') {
      if (!valor) {
        if (p.obligatoria) { Swal.fire('Falta calificar', `Por favor califica: "${p.texto}"`, 'warning'); return; }
        continue;
      }
      respuestas.push({ id_pregunta: p.id_pregunta, valor_estrellas: valor });
    } else {
      if (!valor) {
        if (p.obligatoria) { Swal.fire('Falta responder', `Por favor responde: "${p.texto}"`, 'warning'); return; }
        continue;
      }
      respuestas.push({ id_pregunta: p.id_pregunta, valor_texto: valor });
    }
  }

  const payload = {
    id_entrega: parseInt(document.getElementById('califIdEntrega').value),
    respuestas,
  };
  fetch('../../backend/portal_cliente/confirmar_y_calificar.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  }).then(r=>r.json()).then(data => {
    if (data.success) {
      modalCalificar.hide();
      Swal.fire({title:'¡Gracias por tu calificación!', icon:'success', timer:1800, showConfirmButton:false});
      cargar();
    } else Swal.fire('Error', data.message, 'error');
  }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
}

function abrirNoRecibido(idEntrega) {
  document.getElementById('noRecIdEntrega').value = idEntrega;
  document.getElementById('noRecComentario').value = '';
  modalNoRecibido.show();
}

function enviarNoRecibido() {
  const id_entrega = parseInt(document.getElementById('noRecIdEntrega').value);
  const comentario = document.getElementById('noRecComentario').value.trim();
  if (!comentario) { Swal.fire('Falta información', 'Cuéntanos qué pasó.', 'warning'); return; }

  fetch('../../backend/portal_cliente/reportar_no_recibido.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ id_entrega, comentario })
  }).then(r=>r.json()).then(data => {
    if (data.success) {
      modalNoRecibido.hide();
      Swal.fire({title:'Reporte enviado', text:'La farmacia va a revisar esto.', icon:'success', timer:2000, showConfirmButton:false});
      cargar();
    } else Swal.fire('Error', data.message, 'error');
  }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
}

function abrirDevolucion(idDevolucion) {
  const d = devolucionesCache[idDevolucion];
  if (!d) return;
  document.getElementById('devIdDevolucion').value = d.id_devolucion;
  document.getElementById('devProductos').innerHTML = '<strong>Productos:</strong> ' +
    ((d.productos || []).map(p => `${p.nombre} (${p.cantidad})`).join(', ') || 'Sin detalle');
  document.getElementById('devMotivo').textContent = d.motivo || 'Sin especificar';
  document.getElementById('devNota').value = '';
  document.getElementById('devNotaLabel').textContent = 'Comentario (opcional)';
  modalDevolucion.show();
}

function responderDevolucion(respuesta) {
  const id_devolucion = parseInt(document.getElementById('devIdDevolucion').value);
  const nota = document.getElementById('devNota').value.trim();

  if (respuesta === 'NO' && !nota) {
    document.getElementById('devNotaLabel').textContent = 'Cuéntanos por qué no es correcto *';
    document.getElementById('devNota').focus();
    Swal.fire('Falta información', 'Cuéntanos brevemente por qué no es correcto.', 'warning');
    return;
  }

  fetch('../../backend/portal_cliente/confirmar_devolucion.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ id_devolucion, respuesta, nota })
  }).then(r=>r.json()).then(data => {
    if (data.success) {
      modalDevolucion.hide();
      Swal.fire({
        title: respuesta === 'SI' ? 'Gracias por confirmar' : 'Gracias por avisarnos',
        text: respuesta === 'SI' ? 'Tu respuesta ya está registrada.' : 'La farmacia va a revisar esto.',
        icon: 'success', timer: 1800, showConfirmButton: false
      });
      cargar();
    } else Swal.fire('Error', data.message, 'error');
  }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
}
</script>
</body>
</html>
