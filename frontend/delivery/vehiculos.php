<?php
// frontend/delivery/vehiculos.php — se carga DENTRO de menuprincipal.php
require_once __DIR__ . '/../../backend/conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$base_url = '/sistema-gestor-de-farmacias';
?>
<style>
  .stat{background:#fff;border-radius:12px;padding:1rem;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.06);}
  .stat .v{font-size:1.7rem;font-weight:700;} .stat .l{font-size:.72rem;color:#777;}
  .card-d{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);}
  table thead th{background:#28a745;color:#fff;font-size:.78rem;font-weight:600;border:none;padding:.6rem .8rem;}
  table tbody td{font-size:.8rem;vertical-align:middle;padding:.55rem .8rem;}
  .badge-st{font-size:.7rem;padding:.35em .7em;border-radius:20px;font-weight:700;}
  .st-DISPONIBLE{background:#D4EDDA;color:#2E8B57;}
  .st-EN_USO{background:#FFF3CD;color:#C77B00;}
  .st-DAÑADO{background:#FDEAEA;color:#DC3545;}
  .st-TALLER{background:#E0E7FF;color:#4338CA;}
  .st-INACTIVO{background:#F5F5F5;color:#888;}
</style>

<div class="container-fluid p-0">
  <div class="d-flex justify-content-between align-items-center mb-1">
    <h2 class="mb-0 text-success"><span class="material-symbols-rounded align-middle me-2">directions_car</span> Vehículos</h2>
    <button class="btn btn-success" onclick="abrirModalAgregarVeh()">
      <span class="material-symbols-rounded align-middle me-1">add_circle</span> Agregar Vehículo
    </button>
  </div>
  <p class="text-muted mb-3">Consulta la flota de motos, carros y camiones disponibles para entregas</p>
  <div class="row g-3 mb-3" id="statsRow"></div>
  <div class="card-d p-2">
    <div class="table-responsive" id="tabla"><div class="text-center py-4"><div class="spinner-border text-success"></div></div></div>
  </div>
</div>

<!-- MODAL: agregar vehículo -->
<div class="modal fade" id="modalAgregarVeh" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:#28a745;color:#fff;">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">add_circle</span> Agregar Vehículo</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold small">Tipo de vehículo <span class="text-danger">*</span></label>
          <select id="avTipo" class="form-select">
            <option value="Motocicleta">Motocicleta</option>
            <option value="Carro">Carro</option>
            <option value="Camión">Camión</option>
          </select>
          <small class="text-muted">Debe coincidir con las licencias que se registran en Repartidores.</small>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold small">Placa</label>
          <input type="text" id="avPlaca" class="form-control" placeholder="A123456">
        </div>
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label fw-semibold small">Marca</label>
            <input type="text" id="avMarca" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small">Modelo</label>
            <input type="text" id="avModelo" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small">Color</label>
            <input type="text" id="avColor" class="form-control">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success" onclick="guardarNuevoVehiculo()">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: editar vehículo -->
<div class="modal fade" id="modalVeh" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:#28a745;color:#fff;">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">edit</span> Editar Vehículo</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="mvId">
        <div class="mb-3">
          <label class="form-label fw-semibold small">Tipo de vehículo <span class="text-danger">*</span></label>
          <select id="mvTipo" class="form-select">
            <option value="Motocicleta">Motocicleta</option>
            <option value="Carro">Carro</option>
            <option value="Camión">Camión</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold small">Placa</label>
          <input type="text" id="mvPlaca" class="form-control" placeholder="A123456">
        </div>
        <div class="row g-2 mb-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold small">Marca</label>
            <input type="text" id="mvMarca" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small">Modelo</label>
            <input type="text" id="mvModelo" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small">Color</label>
            <input type="text" id="mvColor" class="form-control">
          </div>
        </div>
        <label class="form-label fw-semibold small">Estado</label>
        <select class="form-select" id="mvEstado">
          <option value="DISPONIBLE">Disponible</option>
          <option value="DAÑADO">Dañado</option>
          <option value="TALLER">En taller</option>
          <option value="INACTIVO">Inactivo</option>
        </select>
        <small class="text-muted d-block mt-2">El estado "En uso" se asigna automáticamente cuando el vehículo se usa en una entrega — no se puede seleccionar aquí.</small>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success" onclick="guardarEstadoVeh()">Guardar cambios</button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL = '<?php echo $base_url; ?>';
let modalVeh, modalAgregarVeh;
let vehiculosDataCache = [];
document.addEventListener('DOMContentLoaded', () => {
  ['modalVeh', 'modalAgregarVeh'].forEach(id => {
    const el = document.getElementById(id);
    if (el && el.parentElement !== document.body) document.body.appendChild(el);
  });
  modalVeh = new bootstrap.Modal('#modalVeh');
  modalAgregarVeh = new bootstrap.Modal('#modalAgregarVeh');
  cargar();
});

function cargar() {
  fetch(BASE_URL + '/backend/delivery/listar_vehiculos_detalle.php').then(r=>r.json()).then(data=>{
    if (!data.success) { document.getElementById('tabla').innerHTML = '<div class="alert alert-danger m-3">'+data.message+'</div>'; return; }
    vehiculosDataCache = data.vehiculos;
    const s = data.stats;
    document.getElementById('statsRow').innerHTML = `
      <div class="col-6 col-md-3"><div class="stat"><div class="v text-success">${s.total}</div><div class="l">Total</div></div></div>
      <div class="col-6 col-md-3"><div class="stat"><div class="v" style="color:#2E8B57">${s.disponible}</div><div class="l">Disponibles</div></div></div>
      <div class="col-6 col-md-3"><div class="stat"><div class="v" style="color:#C77B00">${s.en_uso}</div><div class="l">En uso</div></div></div>
      <div class="col-6 col-md-3"><div class="stat"><div class="v" style="color:#DC3545">${s.danado + s.taller}</div><div class="l">Dañados / Taller</div></div></div>`;

    const rows = data.vehiculos.map(v => `
      <tr>
        <td><strong>${v.tipo}</strong>${v.placa ? ' — '+v.placa : ''}</td>
        <td>${v.marca||''} ${v.modelo||''} ${v.color?'('+v.color+')':''}</td>
        <td>${v.repartidor_nombre || '<span class="text-muted">Sin asignar</span>'}</td>
        <td class="text-center"><span class="badge-st st-${v.estado}">${v.estado.replace('_',' ')}</span></td>
        <td>${v.entrega_actual ? `${v.entrega_actual} <br><small class="text-muted">${v.repartidor_actual}</small>` : '—'}</td>
        <td class="text-center">
          <button class="btn btn-sm btn-outline-primary" onclick='abrirModalVeh(${v.id_vehiculo})' ${v.estado==='EN_USO'?'disabled title="No se puede editar mientras está en uso"':''}>
            <span class="material-symbols-rounded" style="font-size:1rem;">edit</span>
          </button>
        </td>
      </tr>`).join('');
    document.getElementById('tabla').innerHTML = `
      <table class="table table-hover mb-0">
        <thead><tr><th>Vehículo</th><th>Detalle</th><th>Repartidor asignado</th><th class="text-center">Estado</th><th>Entrega actual</th><th class="text-center">Acción</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>`;
  });
}

function abrirModalVeh(id) {
  const v = vehiculosDataCache.find(x => x.id_vehiculo === id);
  if (!v) return;
  document.getElementById('mvId').value = v.id_vehiculo;
  document.getElementById('mvTipo').value = v.tipo;
  document.getElementById('mvPlaca').value = v.placa || '';
  document.getElementById('mvMarca').value = v.marca || '';
  document.getElementById('mvModelo').value = v.modelo || '';
  document.getElementById('mvColor').value = v.color || '';
  document.getElementById('mvEstado').value = v.estado === 'EN_USO' ? 'DISPONIBLE' : v.estado;
  modalVeh.show();
}
function guardarEstadoVeh() {
  const payload = {
    id_vehiculo: document.getElementById('mvId').value,
    tipo: document.getElementById('mvTipo').value,
    placa: document.getElementById('mvPlaca').value.trim(),
    marca: document.getElementById('mvMarca').value.trim(),
    modelo: document.getElementById('mvModelo').value.trim(),
    color: document.getElementById('mvColor').value.trim(),
    estado: document.getElementById('mvEstado').value,
  };
  fetch(BASE_URL + '/backend/delivery/actualizar_vehiculo.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  }).then(r=>r.json()).then(data=>{
    if (data.success) { modalVeh.hide(); Swal.fire({title:'Actualizado', icon:'success', timer:1300, showConfirmButton:false}); cargar(); }
    else Swal.fire('Error', data.message, 'error');
  }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
}

function abrirModalAgregarVeh() {
  document.getElementById('avTipo').value = 'Motocicleta';
  document.getElementById('avPlaca').value = '';
  document.getElementById('avMarca').value = '';
  document.getElementById('avModelo').value = '';
  document.getElementById('avColor').value = '';
  modalAgregarVeh.show();
}
function guardarNuevoVehiculo() {
  const payload = {
    tipo: document.getElementById('avTipo').value,
    placa: document.getElementById('avPlaca').value.trim() || null,
    marca: document.getElementById('avMarca').value.trim() || null,
    modelo: document.getElementById('avModelo').value.trim() || null,
    color: document.getElementById('avColor').value.trim() || null,
    activo: true,
  };
  fetch(BASE_URL + '/backend/delivery/agregar_vehiculo.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  }).then(r=>r.json()).then(data=>{
    if (data.success) { modalAgregarVeh.hide(); Swal.fire({title:'Vehículo agregado', icon:'success', timer:1300, showConfirmButton:false}); cargar(); }
    else Swal.fire('Error', data.message, 'error');
  }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
}
</script>
