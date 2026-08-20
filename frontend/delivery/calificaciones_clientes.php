<?php
// frontend/delivery/calificaciones_clientes.php — se carga DENTRO de
// menuprincipal.php. Pantalla NUEVA para ver todas las calificaciones que
// los clientes han hecho de sus entregas, con el desglose completo por
// pregunta (funciona igual para calificaciones viejas y nuevas).
require_once __DIR__ . '/../../backend/conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$base_url = '/sistema-gestor-de-farmacias';
?>
<style>
  .card-d{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);}
  table thead th{background:#28a745;color:#fff;font-size:.78rem;font-weight:600;border:none;padding:.6rem .8rem;}
  table tbody td{font-size:.82rem;vertical-align:middle;padding:.55rem .8rem;}
  .estrellas-ro span{color:#dee2e6;font-size:.95rem;}
  .estrellas-ro span.llena{color:#FFC107;}
  .badge-cat{background:#EAF1F8;color:#1565C0;border-radius:12px;padding:.2em .6em;font-size:.68rem;font-weight:600;}
</style>

<div class="container-fluid p-0">
  <h2 class="mb-0 text-success"><span class="material-symbols-rounded align-middle me-2">reviews</span> Calificaciones de Clientes</h2>
  <p class="text-muted mb-3">Todo lo que los clientes han calificado sobre sus entregas</p>

  <div class="card-d p-3 mb-3">
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label small fw-bold text-muted mb-1">Repartidor</label>
        <select id="fRepartidor" class="form-select form-select-sm" onchange="cargar()">
          <option value="">Todos</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-bold text-muted mb-1">Desde</label>
        <input type="date" id="fFechaInicio" class="form-control form-control-sm" onchange="cargar()">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-bold text-muted mb-1">Hasta</label>
        <input type="date" id="fFechaFin" class="form-control form-control-sm" onchange="cargar()">
      </div>
      <div class="col-md-5">
        <label class="form-label small fw-bold text-muted mb-1">Buscar (cliente o número de seguimiento)</label>
        <input type="text" id="fBuscar" class="form-control form-control-sm" oninput="cargar()">
      </div>
    </div>
  </div>

  <div class="card-d p-2">
    <div class="table-responsive" id="tabla"><div class="text-center py-4"><div class="spinner-border text-success"></div></div></div>
  </div>
</div>

<div class="modal fade" id="modalDetalleCalif" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:#28a745;color:#fff;">
        <h6 class="modal-title mb-0">Detalle de la calificación</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detalleCalifBody"></div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL = '<?php echo $base_url; ?>';
let modalDetalleCalif;
let calificacionesCache = [];
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('modalDetalleCalif');
  if (el && el.parentElement !== document.body) document.body.appendChild(el);
  modalDetalleCalif = new bootstrap.Modal('#modalDetalleCalif');
  cargar();
});

function renderEstrellasRO(valor) {
  let html = '<span class="estrellas-ro">';
  for (let i = 1; i <= 5; i++) html += `<span class="${i <= valor ? 'llena' : ''}">★</span>`;
  return html + '</span>';
}

function cargar() {
  const idRep = document.getElementById('fRepartidor').value;
  const fi = document.getElementById('fFechaInicio').value;
  const ff = document.getElementById('fFechaFin').value;
  const q = document.getElementById('fBuscar').value;

  fetch(BASE_URL + `/backend/delivery/listar_calificaciones.php?id_repartidor=${idRep}&fecha_inicio=${fi}&fecha_fin=${ff}&q=${encodeURIComponent(q)}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) { document.getElementById('tabla').innerHTML = '<div class="alert alert-danger m-3">' + data.message + '</div>'; return; }

      if (document.getElementById('fRepartidor').options.length <= 1) {
        document.getElementById('fRepartidor').innerHTML = '<option value="">Todos</option>' +
          data.repartidores.map(r => `<option value="${r.id_repartidor}">${r.nombre}</option>`).join('');
      }

      calificacionesCache = data.calificaciones;
      if (!calificacionesCache.length) {
        document.getElementById('tabla').innerHTML = '<div class="text-center text-muted py-5"><span class="material-symbols-rounded" style="font-size:3rem;">inbox</span><br>No hay calificaciones con este filtro.</div>';
        return;
      }

      const rows = calificacionesCache.map((c, i) => {
        const estrellas = (c.respuestas || []).filter(r => r.tipo_respuesta === 'ESTRELLAS' && r.valor_estrellas != null);
        const promedio = estrellas.length ? Math.round(estrellas.reduce((s,r) => s + r.valor_estrellas, 0) / estrellas.length) : null;
        return `<tr>
          <td class="fw-semibold text-success">${c.numero_seguimiento}</td>
          <td>${c.cliente_nombre}</td>
          <td>${c.repartidor_nombre || '—'}</td>
          <td class="text-center">${promedio ? renderEstrellasRO(promedio) : '<span class="text-muted small">—</span>'}</td>
          <td>${new Date(c.fecha_calificacion).toLocaleString('es-DO')}</td>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-success" onclick="verDetalleCalif(${i})" title="Ver detalle">
              <span class="material-symbols-rounded" style="font-size:1rem;">visibility</span>
            </button>
          </td>
        </tr>`;
      }).join('');

      document.getElementById('tabla').innerHTML = `
        <table class="table table-hover mb-0">
          <thead><tr><th>Seguimiento</th><th>Cliente</th><th>Repartidor</th><th class="text-center">General</th><th>Fecha</th><th class="text-center">Detalle</th></tr></thead>
          <tbody>${rows}</tbody>
        </table>`;
    })
    .catch(() => { document.getElementById('tabla').innerHTML = '<div class="alert alert-danger m-3">Error de conexión.</div>'; });
}

function verDetalleCalif(i) {
  const c = calificacionesCache[i];
  const filas = (c.respuestas || []).map(r => `
    <div class="d-flex justify-content-between align-items-start py-2 border-bottom">
      <div>
        <span class="badge-cat me-2">${r.categoria}</span>
        <span class="small">${r.texto_pregunta}</span>
      </div>
      <div class="text-end" style="min-width:140px;">
        ${r.tipo_respuesta === 'ESTRELLAS' ? renderEstrellasRO(r.valor_estrellas) : `<span class="small text-muted">"${r.valor_texto}"</span>`}
      </div>
    </div>`).join('') || '<p class="text-muted">Sin respuestas registradas.</p>';

  document.getElementById('detalleCalifBody').innerHTML = `
    <div class="mb-3" style="font-size:.85rem;">
      <strong>Seguimiento:</strong> ${c.numero_seguimiento}<br>
      <strong>Cliente:</strong> ${c.cliente_nombre}<br>
      <strong>Repartidor:</strong> ${c.repartidor_nombre || '—'}<br>
      <strong>Fecha:</strong> ${new Date(c.fecha_calificacion).toLocaleString('es-DO')}
    </div>
    <hr>
    ${filas}`;
  modalDetalleCalif.show();
}
</script>
