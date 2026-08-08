<?php
// frontend/administracion/usuarios_clientes.php — se carga DENTRO de
// menuprincipal.php, igual que el resto de las pantallas reales.
require_once __DIR__ . '/../../backend/conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$base_url = '/sistema-gestor-de-farmacias';
?>
<style>
  .card-d{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);}
  .stat-card{background:#fff;border-radius:12px;padding:1rem 1.25rem;display:flex;align-items:center;gap:1rem;box-shadow:0 1px 4px rgba(0,0,0,.06);}
  .stat-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;background:#EAF1F8;color:#1565C0;}
  .stat-val{font-size:1.6rem;font-weight:700;line-height:1;}
  .stat-lbl{font-size:.72rem;color:#777;}
  table thead th{background:#28a745;color:#fff;font-size:.78rem;font-weight:600;border:none;padding:.6rem .8rem;}
  table tbody td{font-size:.82rem;vertical-align:middle;padding:.6rem .8rem;}
  .badge-portal{background:#D4EDDA;color:#2E8B57;border-radius:20px;padding:.3em .7em;font-size:.72rem;font-weight:700;}
</style>

<div class="container-fluid p-0">
  <h2 class="mb-0 text-success"><span class="material-symbols-rounded align-middle me-2">badge</span> Accesos de Clientes al Portal</h2>
  <p class="text-muted mb-3">Clientes con cuenta para ver y calificar sus pedidos — cambia su contraseña o revócales el acceso</p>

  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-icon"><span class="material-symbols-rounded">group</span></div>
        <div><div class="stat-val" id="statTotal">—</div><div class="stat-lbl">Con acceso al portal</div></div>
      </div>
    </div>
  </div>

  <div class="card-d p-2 mb-3">
    <input type="text" id="buscador" class="form-control" placeholder="Buscar por nombre o usuario..." oninput="filtrarTabla()">
  </div>

  <div class="card-d p-2">
    <div class="table-responsive" id="tablaContainer">
      <div class="text-center py-4"><div class="spinner-border text-success"></div></div>
    </div>
  </div>
</div>

<!-- MODAL: cambiar contraseña -->
<div class="modal fade" id="modalPassword" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:#28a745;color:#fff;">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">manage_accounts</span> Editar Acceso</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="pwIdCliente">
        <p class="mb-3 text-muted small" id="pwClienteInfo"></p>
        <label class="form-label small fw-semibold">Usuario</label>
        <input type="text" class="form-control mb-3" id="pwUsuario" placeholder="Usuario de acceso">
        <label class="form-label small fw-semibold">Nueva contraseña</label>
        <input type="password" class="form-control" id="pwNueva" placeholder="Dejar en blanco para no cambiarla">
        <small class="text-muted">Deja este campo vacío si solo quieres cambiar el usuario, sin tocar la contraseña.</small>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success" onclick="guardarPassword()"><span class="material-symbols-rounded align-middle" style="font-size:1rem;">check</span> Guardar</button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL = '<?php echo $base_url; ?>';
let modalPassword;
let clientesCache = [];

document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('modalPassword');
  if (el && el.parentElement !== document.body) document.body.appendChild(el);
  modalPassword = new bootstrap.Modal('#modalPassword');
  cargar();
});

function cargar() {
  fetch(BASE_URL + '/backend/administracion/listar_clientes_portal.php').then(r => r.json()).then(data => {
    if (!data.success) {
      document.getElementById('tablaContainer').innerHTML = `<div class="alert alert-danger m-3">${data.message}</div>`;
      return;
    }
    clientesCache = data.clientes;
    document.getElementById('statTotal').textContent = data.total;
    renderTabla(clientesCache);
  }).catch(() => {
    document.getElementById('tablaContainer').innerHTML = `<div class="alert alert-danger m-3">Error de conexión.</div>`;
  });
}

function renderTabla(lista) {
  const tc = document.getElementById('tablaContainer');
  if (!lista.length) {
    tc.innerHTML = `<div class="text-center text-muted py-5"><span class="material-symbols-rounded" style="font-size:3rem;">inbox</span><br>Ningún cliente tiene acceso al portal todavía.</div>`;
    return;
  }
  const rows = lista.map(c => `
    <tr>
      <td class="fw-semibold">${c.nombre}</td>
      <td><span class="badge-portal">${c.usuario_portal}</span></td>
      <td>${c.telefono || '<span class="text-muted">—</span>'}</td>
      <td class="text-center">${c.total_pedidos}</td>
      <td>${new Date(c.fecha_registro).toLocaleDateString('es-DO')}</td>
      <td class="text-center">
        <button class="btn btn-sm btn-outline-primary me-1" onclick="abrirModalPassword(${c.id_cliente}, '${c.nombre.replace(/'/g,"\\'")}', '${c.usuario_portal}')" title="Editar acceso (usuario / contraseña)">
          <span class="material-symbols-rounded" style="font-size:1rem;">manage_accounts</span>
        </button>
        <button class="btn btn-sm btn-outline-danger" onclick="revocarAcceso(${c.id_cliente}, '${c.nombre.replace(/'/g,"\\'")}')" title="Revocar acceso">
          <span class="material-symbols-rounded" style="font-size:1rem;">person_remove</span>
        </button>
      </td>
    </tr>`).join('');
  tc.innerHTML = `
    <table class="table table-hover mb-0">
      <thead><tr><th>Cliente</th><th>Usuario</th><th>Teléfono</th><th class="text-center">Pedidos</th><th>Cliente desde</th><th class="text-center">Acciones</th></tr></thead>
      <tbody>${rows}</tbody>
    </table>`;
}

function filtrarTabla() {
  const q = document.getElementById('buscador').value.toLowerCase();
  renderTabla(clientesCache.filter(c =>
    c.nombre.toLowerCase().includes(q) || c.usuario_portal.toLowerCase().includes(q)));
}

function abrirModalPassword(id, nombre, usuario) {
  document.getElementById('pwIdCliente').value = id;
  document.getElementById('pwClienteInfo').textContent = nombre;
  document.getElementById('pwUsuario').value = usuario;
  document.getElementById('pwNueva').value = '';
  modalPassword.show();
}

function guardarPassword() {
  const id_cliente = document.getElementById('pwIdCliente').value;
  const usuario_nuevo = document.getElementById('pwUsuario').value.trim();
  const password_nueva = document.getElementById('pwNueva').value;
  if (!usuario_nuevo) { Swal.fire('Falta información', 'El usuario no puede quedar vacío.', 'warning'); return; }
  if (password_nueva && password_nueva.length < 4) { Swal.fire('Falta información', 'La contraseña debe tener al menos 4 caracteres.', 'warning'); return; }

  fetch(BASE_URL + '/backend/administracion/cambiar_password_portal.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ id_cliente: parseInt(id_cliente), usuario_nuevo, password_nueva })
  }).then(r => r.json()).then(data => {
    if (data.success) {
      modalPassword.hide();
      Swal.fire({title: 'Acceso actualizado', icon: 'success', timer: 1500, showConfirmButton: false});
      cargar();
    } else Swal.fire('Error', data.message, 'error');
  }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
}

function revocarAcceso(id, nombre) {
  Swal.fire({
    title: '¿Revocar acceso?',
    text: `${nombre} ya no podrá entrar a su portal de seguimiento.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, revocar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#DC3545',
  }).then(result => {
    if (!result.isConfirmed) return;
    fetch(BASE_URL + '/backend/administracion/revocar_acceso_portal.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ id_cliente: id })
    }).then(r => r.json()).then(data => {
      if (data.success) {
        Swal.fire({title: 'Acceso revocado', icon: 'success', timer: 1500, showConfirmButton: false});
        cargar();
      } else Swal.fire('Error', data.message, 'error');
    }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
  });
}
</script>
