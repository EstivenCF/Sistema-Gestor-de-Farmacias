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
</style>
</head>
<body>
<nav class="topbar">
  <div class="brand"><span class="material-symbols-rounded">local_shipping</span> Mis Pedidos</div>
  <div class="d-flex align-items-center gap-3" style="color:#ADE8F4;font-size:.85rem;">
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

<!-- MODAL: confirmar y calificar -->
<div class="modal fade" id="modalCalificar" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">task_alt</span> Confirmar recepción y calificar</h6>
      </div>
      <div class="modal-body">
        <input type="hidden" id="califIdEntrega">
        <p class="text-muted small">Tu repartidor marcó este pedido como entregado. Confírmalo y cuéntanos cómo te fue — nos ayuda muchísimo.</p>

        <div id="califPreguntas"><div class="text-center py-3"><div class="spinner-border text-success spinner-border-sm"></div></div></div>

        <div class="form-check form-switch mt-2">
          <input class="form-check-input" type="checkbox" id="califPersonaCorrecta" checked>
          <label class="form-check-label small" for="califPersonaCorrecta">Se entregó a la persona correcta</label>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success w-100" onclick="enviarCalificacion()">
          <span class="material-symbols-rounded align-middle me-1">send</span> Enviar calificación
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let modalCancelar, modalCalificar;
let preguntasCalifCache = [];   // catálogo activo, traído del servidor
const respuestasCliente = {};   // id_pregunta -> valor (int 1-5 o string)

document.addEventListener('DOMContentLoaded', () => {
  modalCancelar = new bootstrap.Modal('#modalCancelar');
  modalCalificar = new bootstrap.Modal('#modalCalificar');
  cargar();
  cargarMotivos();
});

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

function cargar() {
  fetch('../../backend/portal_cliente/listar_mis_entregas.php').then(r=>r.json()).then(data => {
    const cont = document.getElementById('listaEntregas');
    if (!data.success) { cont.innerHTML = `<div class="alert alert-danger">${data.message}</div>`; return; }
    if (!data.entregas.length) {
      cont.innerHTML = '<div class="text-center text-muted py-5"><span class="material-symbols-rounded" style="font-size:3rem;">inbox</span><br>No tienes pedidos con delivery en este momento.</div>';
      return;
    }
    cont.innerHTML = data.entregas.map(e => {
      const productos = (e.productos||[]).map(p => `${p.nombre} (${p.cantidad})`).join(', ') || 'Sin detalle';
      const horaAcordada = e.fecha_programada ? new Date(e.fecha_programada).toLocaleString('es-DO') : 'Sin definir todavía';
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
          ${e.puede_cancelar ? `
            <button class="btn btn-sm btn-outline-danger mt-2" onclick="abrirCancelar(${e.id_entrega})">
              <span class="material-symbols-rounded align-middle" style="font-size:1rem;">cancel</span> Cancelar pedido
            </button>` : ''}
          ${e.pendiente_confirmar_cliente ? `
            <div class="alert alert-success mt-2 mb-0 d-flex justify-content-between align-items-center">
              <span><span class="material-symbols-rounded align-middle">notifications_active</span> Tu repartidor marcó este pedido como entregado.</span>
              <button class="btn btn-sm btn-success" onclick="abrirCalificar(${e.id_entrega})">Confirmar y calificar</button>
            </div>` : ''}
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

function abrirCalificar(idEntrega) {
  document.getElementById('califIdEntrega').value = idEntrega;
  document.getElementById('califPersonaCorrecta').checked = true;
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
    persona_correcta: document.getElementById('califPersonaCorrecta').checked,
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
</script>
</body>
</html>
