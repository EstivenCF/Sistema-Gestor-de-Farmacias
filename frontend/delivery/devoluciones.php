<?php
// frontend/delivery/devoluciones.php — se carga DENTRO de menuprincipal.php
require_once __DIR__ . '/../../backend/conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$base_url = '/sistema-gestor-de-farmacias';
?>
<style>
  .card-d{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);}
  table thead th{background:#28a745;color:#fff;font-size:.78rem;font-weight:600;border:none;padding:.6rem .8rem;}
  table tbody td{font-size:.8rem;vertical-align:middle;padding:.55rem .8rem;}
  .badge-st{font-size:.7rem;padding:.35em .7em;border-radius:20px;font-weight:700;background:#EAF1F8;color:#1e7e34;}
  .motivo-cell{max-width:280px;white-space:normal;}
</style>

<div class="container-fluid p-0">
  <h2 class="mb-3 text-success"><span class="material-symbols-rounded align-middle me-2">assignment_return</span> Devoluciones</h2>
  <div class="card-d p-2 mb-3">
    <input type="text" id="buscador" class="form-control" placeholder="Buscar por documento, cliente o factura..." oninput="cargar()">
  </div>
  <div class="card-d p-2">
    <div class="table-responsive" id="tabla"><div class="text-center py-4"><div class="spinner-border text-success"></div></div></div>
  </div>
</div>

<!-- MODAL: detalle de la devolución -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:#28a745;color:#fff;">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">assignment_return</span> Detalle de la devolución</h6>
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

<script>
const BASE_URL = '<?php echo $base_url; ?>';
let modalDetalle;
document.addEventListener('DOMContentLoaded', () => { modalDetalle = new bootstrap.Modal('#modalDetalle'); });

function cargar() {
  const q = document.getElementById('buscador').value;
  fetch(BASE_URL + `/backend/delivery/listar_devoluciones.php?q=${encodeURIComponent(q)}`)
    .then(r=>r.json()).then(data=>{
      if (!data.success) { document.getElementById('tabla').innerHTML = '<div class="alert alert-danger m-3">'+data.message+'</div>'; return; }
      if (!data.devoluciones.length) {
        document.getElementById('tabla').innerHTML = '<div class="text-center text-muted py-5"><span class="material-symbols-rounded" style="font-size:3rem;">assignment_return</span><br>No hay devoluciones registradas.</div>';
        return;
      }
      const rows = data.devoluciones.map(d => `
        <tr>
          <td class="fw-semibold text-primary">${d.numero_documento}</td>
          <td>${d.venta_documento || '—'}</td>
          <td>${d.cliente_nombre || 'Consumidor Final'}</td>
          <td><span class="badge-st">${d.tipo_nombre || '—'}</span></td>
          <td class="motivo-cell">${d.motivo || '<span class="text-muted">Sin especificar</span>'}</td>
          <td>${d.estado_nombre || '—'}</td>
          <td>RD$ ${parseFloat(d.monto_reembolso||0).toFixed(2)}</td>
          <td>${new Date(d.fecha_solicitud).toLocaleDateString('es-DO')}</td>
          <td>${d.solicitado_por || '—'}</td>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-primary" onclick="verDetalleDevolucion(${d.id_devolucion})">
              <span class="material-symbols-rounded" style="font-size:1rem;">visibility</span>
            </button>
          </td>
        </tr>`).join('');
      document.getElementById('tabla').innerHTML = `
        <table class="table table-hover mb-0">
          <thead><tr><th>Documento</th><th>Factura</th><th>Cliente</th><th>Tipo</th><th>Motivo</th><th>Estado</th><th>Reembolso</th><th>Fecha</th><th>Solicitado por</th><th class="text-center">Detalle</th></tr></thead>
          <tbody>${rows}</tbody>
        </table>`;
    });
}

function verDetalleDevolucion(id) {
  document.getElementById('detalleBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success"></div></div>';
  modalDetalle.show();

  fetch(BASE_URL + `/backend/delivery/get_detalle_devolucion.php?id_devolucion=${id}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        document.getElementById('detalleBody').innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        return;
      }
      const d = data.devolucion;
      const items = data.detalle || [];

      const filas = items.length
        ? items.map(p => `
            <tr>
              <td>${p.producto_nombre || 'Producto'}</td>
              <td><code>${p.numero_lote || '—'}</code></td>
              <td class="text-center">${p.cantidad}</td>
              <td class="text-end">RD$ ${parseFloat(p.precio_unitario).toFixed(2)}</td>
              <td class="text-end">RD$ ${parseFloat(p.subtotal).toFixed(2)}</td>
            </tr>`).join('')
        : '<tr><td colspan="5" class="text-center text-muted py-3">Sin productos registrados en el detalle</td></tr>';

      const totalDevuelto = items.reduce((sum, p) => sum + parseFloat(p.subtotal || 0), 0);

      document.getElementById('detalleBody').innerHTML = `
        <div class="row g-2 mb-3" style="font-size:.85rem;">
          <div class="col-6"><strong>Documento:</strong> ${d.numero_documento}</div>
          <div class="col-6"><strong>Factura original:</strong> ${d.venta_documento || '—'}</div>
          <div class="col-6"><strong>Cliente:</strong> ${d.cliente_nombre || 'Consumidor Final'}</div>
          <div class="col-6"><strong>Tipo:</strong> ${d.tipo_nombre || '—'}</div>
          <div class="col-6"><strong>Estado:</strong> ${d.estado_nombre || '—'}</div>
          <div class="col-6"><strong>Solicitado por:</strong> ${d.solicitado_por || '—'}</div>
          <div class="col-12"><strong>Motivo:</strong> ${d.motivo || 'Sin especificar'}</div>
        </div>
        <hr>
        <strong style="font-size:.85rem;">Productos devueltos:</strong>
        <div class="table-responsive mt-2">
          <table class="table table-sm mb-0">
            <thead><tr><th>Producto</th><th>Lote</th><th class="text-center">Cantidad</th><th class="text-end">Precio unit.</th><th class="text-end">Subtotal</th></tr></thead>
            <tbody>${filas}</tbody>
            <tfoot><tr><th colspan="4" class="text-end">Total devuelto:</th><th class="text-end">RD$ ${totalDevuelto.toFixed(2)}</th></tr></tfoot>
          </table>
        </div>
        <p class="text-muted small mt-2 mb-0">Monto de reembolso registrado: <strong>RD$ ${parseFloat(d.monto_reembolso||0).toFixed(2)}</strong></p>
      `;
    })
    .catch(() => {
      document.getElementById('detalleBody').innerHTML = '<div class="alert alert-danger">Error de conexión.</div>';
    });
}

cargar();
</script>
