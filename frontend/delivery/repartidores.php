<?php
// frontend/delivery/repartidores.php — se carga DENTRO de menuprincipal.php
// (vía include, como el resto de las pantallas). El acceso ya lo valida
// menuprincipal.php antes de llegar aquí, así que no hace falta
// redirigir de nuevo — un header() aquí rompería la página porque
// menuprincipal.php ya empezó a imprimir HTML.
require_once __DIR__ . '/../../backend/conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$base_url = '/sistema-gestor-de-farmacias';
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<style>
  .card-d{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);}
  table thead th{background:#28a745;color:#fff;font-size:.78rem;font-weight:600;border:none;padding:.6rem .8rem;}
  table tbody td{font-size:.8rem;vertical-align:middle;padding:.55rem .8rem;}
  .badge-st{font-size:.7rem;padding:.35em .7em;border-radius:20px;font-weight:700;}
  .st-DISPONIBLE{background:#D4EDDA;color:#2E8B57;}
  .st-EN_CAMINO{background:#FFF3CD;color:#C77B00;}
  .st-VACACIONES{background:#D6E8F8;color:#1565C0;}
  .st-LICENCIA_MEDICA{background:#F3E5F5;color:#7B1FA2;}
  .st-HOSPITALIZADO{background:#FDEAEA;color:#DC3545;}
  .st-LUTO{background:#E2E3E5;color:#41464B;}
  .st-OTRO{background:#F5F5F5;color:#888;}
  .st-INACTIVO{background:#212529;color:#fff;}
  .stat-card{background:#fff;border-radius:12px;padding:1rem 1.1rem;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.06);}
  .stat-card .v{font-size:1.6rem;font-weight:700;}
  .stat-card .l{font-size:.72rem;color:#777;}
  .estrella-llena{color:#FFC107;}
  .estrella-vacia{color:#DEE2E6;}
  .chip-filtro{border:1.5px solid #dee2e6;border-radius:20px;padding:.35rem .9rem;font-size:.78rem;font-weight:600;cursor:pointer;background:#fff;color:#555;white-space:nowrap;}
  .chip-filtro.active{background:#28a745;border-color:#28a745;color:#fff;}
  .hab-badge{background:#EAF1F8;color:#1e7e34;border-radius:20px;padding:.25em .6em;font-size:.68rem;font-weight:600;margin-right:.3rem;}
  .avatar{width:32px;height:32px;border-radius:50%;background:#28a745;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;margin-right:.5rem;}
</style>

<div class="container-fluid p-0">
  <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
    <h2 class="mb-0 text-success"><span class="material-symbols-rounded align-middle me-2">person</span> Gestión de Repartidores</h2>
    <button class="btn btn-success" onclick="abrirModalAgregar()">
      <span class="material-symbols-rounded align-middle me-1">person_add</span> Agregar Repartidor
    </button>
  </div>
  <p class="text-muted mb-3">Administra el personal de delivery: licencias, disponibilidad y calificación</p>

  <!-- DASHBOARD -->
  <div class="row g-3 mb-3" id="statsRow"></div>

  <!-- FILTROS -->
  <div class="card-d p-3 mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small fw-semibold mb-1">Desde</label>
        <input type="date" id="fFechaInicio" class="form-control form-control-sm">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold mb-1">Hasta</label>
        <input type="date" id="fFechaFin" class="form-control form-control-sm">
      </div>
      <div class="col-md-2">
        <button class="btn btn-sm btn-outline-success w-100" onclick="cargar()">
          <span class="material-symbols-rounded align-middle" style="font-size:1rem;">search</span> Filtrar
        </button>
      </div>
      <div class="col-md-2">
        <div class="form-check form-switch mt-2">
          <input class="form-check-input" type="checkbox" id="fMostrarInactivos" onchange="cargar()">
          <label class="form-check-label small" for="fMostrarInactivos">Ver inactivos</label>
        </div>
      </div>
      <div class="col-md-2">
        <button class="btn btn-sm btn-outline-secondary w-100" onclick="exportarPDF()">
          <span class="material-symbols-rounded align-middle" style="font-size:1rem;">picture_as_pdf</span> Exportar PDF
        </button>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3" id="chipsFiltro"></div>
  </div>

  <div class="card-d p-2">
    <div class="table-responsive" id="tabla"><div class="text-center py-4"><div class="spinner-border text-primary"></div></div></div>
  </div>
</div>

<!-- MODAL: agregar repartidor -->
<div class="modal fade" id="modalAgregar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:#28a745;color:#fff;">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">person_add</span> Agregar Repartidor</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold small">Nombre completo <span class="text-danger">*</span></label>
            <input type="text" id="agNombre" class="form-control" oninput="sugerirUsuario()">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold small">Tipo de identificación</label>
            <select id="agTipoId" class="form-select">
              <option value="Cedula">Cédula</option>
              <option value="Pasaporte">Pasaporte</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold small">Número de identificación</label>
            <input type="text" id="agNumeroId" class="form-control" placeholder="000-0000000-0">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold small">Teléfono de emergencia</label>
            <input type="text" id="agTelefono" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold small">Fecha de ingreso</label>
            <input type="date" id="agFechaIngreso" class="form-control">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold small">Tipos de vehículo que sabe manejar, con su licencia</label>
            <p class="text-muted small mb-2">Cada tipo de vehículo es una licencia distinta — agrega una fila por cada una.</p>
            <div id="agHabLista" class="mb-2"></div>
            <div class="row g-2 align-items-end">
              <div class="col-md-4">
                <label class="form-label small mb-1">Tipo de vehículo</label>
                <select class="form-select form-select-sm" id="agHabTipo">
                  <option value="">Selecciona...</option>
                  <option value="Motocicleta">Motocicleta</option>
                  <option value="Carro">Carro</option>
                  <option value="Camión">Camión</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Número de licencia</label>
                <input type="text" class="form-control form-control-sm" id="agHabLicencia" placeholder="0000000000">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Vencimiento</label>
                <input type="date" class="form-control form-control-sm" id="agHabVencimiento">
              </div>
              <div class="col-md-2">
                <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="agregarHabilidadNueva()">Agregar</button>
              </div>
            </div>
          </div>
        </div>

        <hr class="my-3">

        <h6 class="fw-semibold" style="font-size:.9rem;color:#1e7e34;">
          <span class="material-symbols-rounded align-middle" style="font-size:1.1rem;">lock_person</span>
          Acceso al sistema
        </h6>
        <p class="text-muted small mb-2">Todo repartidor usa la agenda para ver sus entregas, así que su cuenta de acceso se crea aquí mismo, ya enlazada.</p>
        <div id="agBloqueAcceso">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold small">Usuario (login) <span class="text-danger">*</span></label>
              <input type="text" id="agUsuarioLogin" class="form-control" placeholder="jperez">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold small">Contraseña <span class="text-danger">*</span></label>
              <input type="password" id="agPasswordLogin" class="form-control" placeholder="Mínimo 4 caracteres">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold small">Sucursal <span class="text-danger">*</span></label>
              <select id="agSucursal" class="form-select">
                <option value="">Cargando...</option>
              </select>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" onclick="guardarNuevoRepartidor()">
          <span class="material-symbols-rounded align-middle me-1">save</span> Guardar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: editar repartidor -->
<div class="modal fade" id="modalEditar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:#28a745;color:#fff;">
        <h6 class="modal-title mb-0"><span class="material-symbols-rounded align-middle me-1">edit</span> Editar Repartidor</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="edId">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold small">Nombre completo <span class="text-danger">*</span></label>
            <input type="text" id="edNombre" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold small">Tipo de identificación</label>
            <select id="edTipoId" class="form-select">
              <option value="Cedula">Cédula</option>
              <option value="Pasaporte">Pasaporte</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold small">Número de identificación</label>
            <input type="text" id="edNumeroId" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold small">Teléfono de emergencia</label>
            <input type="text" id="edTelefono" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small">Fecha de ingreso</label>
            <input type="date" id="edFechaIngreso" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small">Estado laboral</label>
            <select id="edEstadoLaboral" class="form-select">
              <option value="ACTIVO">Activo</option>
              <option value="VACACIONES">Vacaciones</option>
              <option value="LICENCIA_MEDICA">Licencia médica</option>
              <option value="HOSPITALIZADO">Hospitalizado</option>
              <option value="LUTO">Luto</option>
              <option value="OTRO">Otro</option>
            </select>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="edActivo">
              <label class="form-check-label small" for="edActivo">Sigue en la empresa</label>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold small">Tipos de vehículo que sabe manejar, con su licencia</label>
            <p class="text-muted small mb-2">Cada tipo de vehículo es una licencia distinta.</p>
            <div id="edHabLista" class="mb-2"></div>
            <div class="row g-2 align-items-end">
              <div class="col-md-4">
                <label class="form-label small mb-1">Tipo de vehículo</label>
                <select class="form-select form-select-sm" id="edHabTipo">
                  <option value="">Selecciona...</option>
                  <option value="Motocicleta">Motocicleta</option>
                  <option value="Carro">Carro</option>
                  <option value="Camión">Camión</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Número de licencia</label>
                <input type="text" class="form-control form-control-sm" id="edHabLicencia" placeholder="0000000000">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Vencimiento</label>
                <input type="date" class="form-control form-control-sm" id="edHabVencimiento">
              </div>
              <div class="col-md-2">
                <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="agregarHabilidadEditar()">Agregar</button>
              </div>
            </div>
          </div>
        </div>

        <hr class="my-3">
        <div id="edSinAcceso" style="display:none;">
          <h6 class="fw-semibold" style="font-size:.9rem;color:var(--danger);">
            <span class="material-symbols-rounded align-middle" style="font-size:1.1rem;">lock_person</span>
            Este repartidor todavía no tiene acceso al sistema
          </h6>
          <p class="text-muted small mb-2">Todo repartidor necesita poder ver su agenda — completa estos datos para crearle el acceso y poder guardar.</p>
          <div id="edBloqueAcceso">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Usuario (login) <span class="text-danger">*</span></label>
                <input type="text" id="edUsuarioLogin" class="form-control">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Contraseña <span class="text-danger">*</span></label>
                <input type="password" id="edPasswordLogin" class="form-control">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Sucursal <span class="text-danger">*</span></label>
                <select id="edSucursal" class="form-select"><option value="">Cargando...</option></select>
              </div>
            </div>
          </div>
        </div>
        <p id="edYaTieneAcceso" class="text-success small mb-0" style="display:none;">
          <span class="material-symbols-rounded align-middle" style="font-size:1rem;">check_circle</span>
          Ya tiene acceso al sistema (usuario: <strong id="edUsuarioActual"></strong>)
        </p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" onclick="guardarEdicionRepartidor()">
          <span class="material-symbols-rounded align-middle me-1">save</span> Guardar cambios
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL = '<?php echo $base_url; ?>';
let modalEditar, modalAgregar, habilidadesTemp = [], habilidadesEditar = [];
let habilidadesNuevoRepartidor = [];
let repartidoresDataCache = [];
document.addEventListener('DOMContentLoaded', () => {
  // El contenedor de la página (.main-content) tiene una animación de
  // entrada (transform + opacity), y cualquier elemento con eso crea
  // su propio "contexto de apilamiento" en CSS — atrapa a los modales
  // que estén anidados adentro, sin importar qué tan alto sea su
  // z-index, porque nunca llegan a competir contra el fondo oscuro
  // del modal (que Bootstrap coloca directo en <body>). Se sacan los
  // modales de ahí y se mueven a <body> antes de inicializarlos.
  ['modalEditar', 'modalAgregar'].forEach(id => {
    const el = document.getElementById(id);
    if (el && el.parentElement !== document.body) document.body.appendChild(el);
  });

  modalEditar = new bootstrap.Modal('#modalEditar');
  modalAgregar = new bootstrap.Modal('#modalAgregar');
  const hoy = new Date().toISOString().slice(0,10);
  document.getElementById('fFechaInicio').value = hoy;
  document.getElementById('fFechaFin').value = hoy;
  cargar();
});

let filtroActual = 'ENTREGADA';
const FILTROS = [
  ['ENTREGADA','Completadas'], ['FALLIDA','Fallidas'], ['PARCIAL','Parciales'],
  ['INTERRUMPIDA','Interrumpidas'], ['CANCELADA','Canceladas'], ['REPROGRAMADA','Reprogramadas'],
  ['DEVOLUCIONES','Devoluciones'],
];
const ESTADO_LABEL = {
  DISPONIBLE:'DISPONIBLE', EN_CAMINO:'EN CAMINO', VACACIONES:'VACACIONES',
  LICENCIA_MEDICA:'LICENCIA MÉDICA', HOSPITALIZADO:'HOSPITALIZADO', LUTO:'LUTO', OTRO:'OTRO',
  INACTIVO:'INACTIVO',
};
const TIPOS_VEHICULO = ['Motocicleta','Carro','Camión'];
let ultimoResultado = null;

function renderChipsFiltro() {
  document.getElementById('chipsFiltro').innerHTML = FILTROS.map(([val,lbl]) =>
    `<span class="chip-filtro ${filtroActual===val?'active':''}" onclick="filtrarPor('${val}')">${lbl}</span>`
  ).join('');
}
function filtrarPor(val) { filtroActual = val; cargar(); }

function vehiculosSegunLicencia(habilidades) {
  const tipos = (habilidades||[]).map(h => h.tipo_vehiculo);
  if (!tipos.length) return 'Sin definir';
  const tieneTodos = TIPOS_VEHICULO.every(t => tipos.includes(t));
  return tieneTodos ? 'Todos' : tipos.join(', ');
}

function renderEstrellas(valor, totalCalificaciones) {
  if (!valor || !totalCalificaciones) {
    return `<span class="text-muted small">Sin calificaciones todavía</span>`;
  }
  const llenas = Math.round(valor);
  let html = '';
  for (let i = 1; i <= 5; i++) {
    html += `<span class="${i<=llenas?'estrella-llena':'estrella-vacia'}" style="font-size:1rem;">★</span>`;
  }
  return `<span title="${parseFloat(valor).toFixed(1)} / 5 — ${totalCalificaciones} calificación${totalCalificaciones===1?'':'es'}">${html} <small class="text-muted">(${totalCalificaciones})</small></span>`;
}

function renderStats(stats) {
  document.getElementById('statsRow').innerHTML = `
    <div class="col-6 col-md-2"><div class="stat-card"><div class="v text-primary">${stats.total}</div><div class="l">Total en lista</div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="v" style="color:#2E8B57">${stats.disponibles}</div><div class="l">Disponibles</div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="v" style="color:#C77B00">${stats.en_camino}</div><div class="l">En camino</div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="v" style="color:#7B1FA2">${stats.no_disponibles}</div><div class="l">No disponibles</div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="v" style="color:#DC3545">${stats.sin_licencia}</div><div class="l">Sin licencia</div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="v text-dark">${stats.total_filtro}</div><div class="l">Total del filtro</div></div></div>
  `;
}

function cargar() {
  renderChipsFiltro();
  const fi = document.getElementById('fFechaInicio').value;
  const ff = document.getElementById('fFechaFin').value;
  const mostrarInactivos = document.getElementById('fMostrarInactivos').checked ? 1 : 0;

  fetch(BASE_URL + `/backend/delivery/listar_repartidores_detalle.php?filtro=${filtroActual}&fecha_inicio=${fi}&fecha_fin=${ff}&mostrar_inactivos=${mostrarInactivos}`)
    .then(r=>r.json()).then(data=>{
    if (!data.success) { document.getElementById('tabla').innerHTML = '<div class="alert alert-danger m-3">'+data.message+'</div>'; return; }
    repartidoresDataCache = data.repartidores;
    ultimoResultado = data;
    renderStats(data.stats);
    const labelFiltro = FILTROS.find(([v])=>v===filtroActual)?.[1] || 'Total';
    const rangoTexto = fi === ff ? fi : `${fi} a ${ff}`;
    const rows = data.repartidores.map(r => {
      const vehiculos = vehiculosSegunLicencia(r.habilidades);
      return `<tr class="${!r.activo ? 'table-secondary' : ''}">
        <td><span class="avatar">${r.nombre.charAt(0)}</span><strong>${r.nombre}</strong></td>
        <td>${vehiculos}</td>
        <td>${renderEstrellas(r.calificacion_promedio, r.total_calificaciones)}</td>
        <td class="text-center"><span class="badge-st st-${r.estado_actual}">${ESTADO_LABEL[r.estado_actual] || r.estado_actual}</span></td>
        <td class="text-center">${r.entregas_filtro}</td>
        <td class="text-center">
          <button class="btn btn-sm btn-outline-primary" onclick="abrirModalEditar(${r.id_repartidor})">
            <span class="material-symbols-rounded" style="font-size:1rem;">edit</span> Editar
          </button>
        </td>
      </tr>`;
    }).join('');
    document.getElementById('tabla').innerHTML = `
      <div class="px-2 pt-2 text-muted" style="font-size:.78rem;">Mostrando "${labelFiltro}" del ${rangoTexto}</div>
      <table class="table table-hover mb-0">
        <thead><tr><th>Repartidor</th><th>Vehículo(s)</th><th>Calificación</th><th class="text-center">Estado</th><th class="text-center">${labelFiltro}</th><th class="text-center">Acción</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>`;
  });
}

function exportarPDF() {
  if (!ultimoResultado || !ultimoResultado.repartidores.length) {
    Swal.fire('Sin datos', 'No hay repartidores para exportar con este filtro.', 'warning');
    return;
  }
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();
  const labelFiltro = FILTROS.find(([v])=>v===filtroActual)?.[1] || 'Total';
  const fi = ultimoResultado.fecha_inicio, ff = ultimoResultado.fecha_fin;

  doc.setFontSize(14);
  doc.text('Reporte de Repartidores — Delivery', 14, 15);
  doc.setFontSize(9);
  doc.setTextColor(100);
  doc.text(`Filtro: ${labelFiltro}   |   Rango: ${fi} a ${ff}   |   Generado: ${new Date().toLocaleString('es-DO')}`, 14, 21);

  doc.autoTable({
    startY: 26,
    head: [['Repartidor', 'Vehículo(s)', 'Calificación', 'Estado', labelFiltro]],
    body: ultimoResultado.repartidores.map(r => [
      r.nombre,
      vehiculosSegunLicencia(r.habilidades),
      calificacionSimulada(r.id_repartidor).toFixed(1) + ' / 5',
      ESTADO_LABEL[r.estado_actual] || r.estado_actual,
      r.entregas_filtro,
    ]),
    headStyles: { fillColor: [31, 92, 153] },
    styles: { fontSize: 8 },
  });

  const finalY = doc.lastAutoTable.finalY + 8;
  doc.setFontSize(9);
  doc.setTextColor(0);
  doc.text(`Total repartidores listados: ${ultimoResultado.stats.total}`, 14, finalY);
  doc.text(`Total "${labelFiltro}" en el rango: ${ultimoResultado.stats.total_filtro}`, 14, finalY + 5);

  doc.save(`repartidores_${filtroActual}_${fi}_a_${ff}.pdf`);
}

// ══════════════ EDITAR REPARTIDOR ══════════════

function abrirModalEditar(id) {
  const r = repartidoresDataCache.find(x => x.id_repartidor === id);
  if (!r) return;

  document.getElementById('edId').value = r.id_repartidor;
  document.getElementById('edNombre').value = r.nombre || '';
  document.getElementById('edTipoId').value = r.tipo_identificacion || 'Cedula';
  document.getElementById('edNumeroId').value = r.numero_identificacion || '';
  document.getElementById('edTelefono').value = r.telefono_emergencia || '';
  document.getElementById('edFechaIngreso').value = r.fecha_ingreso ? r.fecha_ingreso.slice(0,10) : '';
  document.getElementById('edActivo').checked = !!r.activo;
  document.getElementById('edEstadoLaboral').value = r.estado_laboral || 'ACTIVO';

  habilidadesEditar = (r.habilidades || []).map(h => ({
    tipo_vehiculo: h.tipo_vehiculo,
    numero_licencia: h.numero_licencia,
    fecha_vencimiento_licencia: h.fecha_vencimiento_licencia ? h.fecha_vencimiento_licencia.slice(0,10) : null,
    nivel: h.nivel || 'COMPETENTE',
  }));
  renderHabilidadesEditar();

  if (r.tiene_acceso) {
    document.getElementById('edSinAcceso').style.display = 'none';
    document.getElementById('edYaTieneAcceso').style.display = 'block';
    document.getElementById('edUsuarioActual').textContent = r.usuario_login || '';
  } else {
    document.getElementById('edSinAcceso').style.display = 'block';
    document.getElementById('edYaTieneAcceso').style.display = 'none';
    document.getElementById('edUsuarioLogin').value = '';
    document.getElementById('edPasswordLogin').value = '';
    cargarSucursalesParaEditar();
  }

  modalEditar.show();
}

function cargarSucursalesParaEditar() {
  fetch(BASE_URL + '/backend/delivery/listar_sucursales.php').then(r=>r.json()).then(data=>{
    const sel = document.getElementById('edSucursal');
    if (!data.success || !data.sucursales.length) { sel.innerHTML = '<option value="">No se pudieron cargar</option>'; return; }
    sel.innerHTML = '<option value="">Selecciona...</option>' +
      data.sucursales.map(s => `<option value="${s.id_sucursal}">${s.nombre}</option>`).join('');
  });
}

function renderHabilidadesEditar() {
  const cont = document.getElementById('edHabLista');
  if (!habilidadesEditar.length) {
    cont.innerHTML = '<span class="text-muted small">Ninguna aún</span>';
    return;
  }
  cont.innerHTML = `<table class="table table-sm mb-0"><thead><tr>
      <th style="font-size:.75rem;">Tipo</th><th style="font-size:.75rem;">Licencia</th>
      <th style="font-size:.75rem;">Vence</th><th></th></tr></thead><tbody>` +
    habilidadesEditar.map((h,i) => `
      <tr style="font-size:.8rem;">
        <td>${h.tipo_vehiculo}</td>
        <td>${h.numero_licencia || '—'}</td>
        <td>${h.fecha_vencimiento_licencia || '—'}</td>
        <td class="text-end"><span style="cursor:pointer;color:#DC3545;" onclick="quitarHabilidadEditar(${i})">×</span></td>
      </tr>`).join('') + '</tbody></table>';
}
function agregarHabilidadEditar() {
  const tipo = document.getElementById('edHabTipo').value.trim();
  const licencia = document.getElementById('edHabLicencia').value.trim();
  const vencimiento = document.getElementById('edHabVencimiento').value;
  if (!tipo) { Swal.fire('Falta información', 'Indica el tipo de vehículo.', 'warning'); return; }
  if (habilidadesEditar.some(h => h.tipo_vehiculo.toLowerCase() === tipo.toLowerCase())) {
    Swal.fire('Ya existe', 'Ese tipo de vehículo ya está en la lista.', 'warning'); return;
  }
  habilidadesEditar.push({ tipo_vehiculo: tipo, numero_licencia: licencia || null, fecha_vencimiento_licencia: vencimiento || null, nivel: 'COMPETENTE' });
  renderHabilidadesEditar();
  document.getElementById('edHabTipo').value = '';
  document.getElementById('edHabLicencia').value = '';
  document.getElementById('edHabVencimiento').value = '';
}
function quitarHabilidadEditar(i) { habilidadesEditar.splice(i,1); renderHabilidadesEditar(); }

function guardarEdicionRepartidor() {
  const nombre = document.getElementById('edNombre').value.trim();
  if (!nombre) { Swal.fire('Falta información', 'El nombre es obligatorio.', 'warning'); return; }

  const necesitaAcceso = document.getElementById('edSinAcceso').style.display !== 'none';
  const usuarioLogin = document.getElementById('edUsuarioLogin')?.value.trim() || '';
  const passwordLogin = document.getElementById('edPasswordLogin')?.value || '';
  const idSucursal = document.getElementById('edSucursal')?.value || '';

  if (necesitaAcceso && (!usuarioLogin || !passwordLogin || !idSucursal)) {
    Swal.fire('Falta información', 'Este repartidor todavía no tiene acceso al sistema: completa usuario, contraseña y sucursal para poder guardar.', 'warning');
    return;
  }

  const payload = {
    id_repartidor: parseInt(document.getElementById('edId').value),
    nombre,
    tipo_identificacion: document.getElementById('edTipoId').value,
    numero_identificacion: document.getElementById('edNumeroId').value.trim() || null,
    telefono_emergencia: document.getElementById('edTelefono').value.trim() || null,
    fecha_ingreso: document.getElementById('edFechaIngreso').value || null,
    activo: document.getElementById('edActivo').checked,
    estado_laboral: document.getElementById('edEstadoLaboral').value,
    habilidades: habilidadesEditar,
    usuario_login: necesitaAcceso ? usuarioLogin : null,
    password_login: necesitaAcceso ? passwordLogin : null,
    id_sucursal_usuario: necesitaAcceso ? parseInt(idSucursal) : null,
  };

  fetch(BASE_URL + '/backend/delivery/editar_repartidor.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  }).then(r=>r.json()).then(data=>{
    if (data.success) {
      modalEditar.hide();
      Swal.fire({title:'Guardado', icon:'success', timer:1300, showConfirmButton:false});
      cargar();
    } else {
      Swal.fire('Error', data.message, 'error');
    }
  }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
}

// ══════════════ AGREGAR REPARTIDOR ══════════════

function abrirModalAgregar() {
  document.getElementById('agNombre').value = '';
  document.getElementById('agTipoId').value = 'Cedula';
  document.getElementById('agNumeroId').value = '';
  document.getElementById('agTelefono').value = '';
  document.getElementById('agFechaIngreso').value = new Date().toISOString().slice(0,10);
  document.getElementById('agUsuarioLogin').value = '';
  document.getElementById('agUsuarioLogin').dataset.editadoManual = '0';
  document.getElementById('agPasswordLogin').value = '';
  habilidadesNuevoRepartidor = [];
  renderHabilidadesNuevas();
  cargarSucursalesParaAgregar();
  modalAgregar.show();
}

function sugerirUsuario() {
  const campoUsuario = document.getElementById('agUsuarioLogin');
  if (campoUsuario.dataset.editadoManual === '1') return; // no pisar si ya lo editaron a mano
  const nombre = document.getElementById('agNombre').value.trim().toLowerCase();
  if (!nombre) { campoUsuario.value = ''; return; }
  const partes = nombre.normalize('NFD').replace(/[\u0300-\u036f]/g,'').split(/\s+/).filter(Boolean);
  if (partes.length === 0) return;
  const sugerido = partes.length === 1 ? partes[0] : (partes[0][0] + partes[partes.length-1]);
  campoUsuario.value = sugerido;
}
document.addEventListener('DOMContentLoaded', () => {
  const campoUsuario = document.getElementById('agUsuarioLogin');
  if (campoUsuario) campoUsuario.addEventListener('input', () => { campoUsuario.dataset.editadoManual = '1'; });
});

// ── Habilidades con licencia por tipo (para Agregar) ──
function renderHabilidadesNuevas() {
  const cont = document.getElementById('agHabLista');
  if (!habilidadesNuevoRepartidor.length) {
    cont.innerHTML = '<span class="text-muted small">Ninguna aún</span>';
    return;
  }
  cont.innerHTML = `<table class="table table-sm mb-0"><thead><tr>
      <th style="font-size:.75rem;">Tipo</th><th style="font-size:.75rem;">Licencia</th>
      <th style="font-size:.75rem;">Vence</th><th></th></tr></thead><tbody>` +
    habilidadesNuevoRepartidor.map((h,i) => `
      <tr style="font-size:.8rem;">
        <td>${h.tipo_vehiculo}</td>
        <td>${h.numero_licencia || '—'}</td>
        <td>${h.fecha_vencimiento_licencia || '—'}</td>
        <td class="text-end"><span style="cursor:pointer;color:#DC3545;" onclick="quitarHabilidadNueva(${i})">×</span></td>
      </tr>`).join('') + '</tbody></table>';
}
function agregarHabilidadNueva() {
  const tipo = document.getElementById('agHabTipo').value.trim();
  const licencia = document.getElementById('agHabLicencia').value.trim();
  const vencimiento = document.getElementById('agHabVencimiento').value;
  if (!tipo) { Swal.fire('Falta información', 'Indica el tipo de vehículo.', 'warning'); return; }
  if (habilidadesNuevoRepartidor.some(h => h.tipo_vehiculo.toLowerCase() === tipo.toLowerCase())) {
    Swal.fire('Ya existe', 'Ese tipo de vehículo ya está en la lista.', 'warning'); return;
  }
  habilidadesNuevoRepartidor.push({ tipo_vehiculo: tipo, numero_licencia: licencia || null, fecha_vencimiento_licencia: vencimiento || null, nivel: 'COMPETENTE' });
  renderHabilidadesNuevas();
  document.getElementById('agHabTipo').value = '';
  document.getElementById('agHabLicencia').value = '';
  document.getElementById('agHabVencimiento').value = '';
}
function quitarHabilidadNueva(i) { habilidadesNuevoRepartidor.splice(i,1); renderHabilidadesNuevas(); }

function cargarSucursalesParaAgregar() {
  fetch(BASE_URL + '/backend/delivery/listar_sucursales.php').then(r=>r.json()).then(data=>{
    const sel = document.getElementById('agSucursal');
    if (!data.success || !data.sucursales.length) { sel.innerHTML = '<option value="">No se pudieron cargar</option>'; return; }
    sel.innerHTML = '<option value="">Selecciona...</option>' +
      data.sucursales.map(s => `<option value="${s.id_sucursal}">${s.nombre}</option>`).join('');
  });
}

function guardarNuevoRepartidor() {
  const nombre = document.getElementById('agNombre').value.trim();
  if (!nombre) { Swal.fire('Falta información', 'El nombre es obligatorio.', 'warning'); return; }

  const usuarioLogin = document.getElementById('agUsuarioLogin').value.trim();
  const passwordLogin = document.getElementById('agPasswordLogin').value;
  const idSucursal = document.getElementById('agSucursal').value;

  if (!usuarioLogin || !passwordLogin || !idSucursal) {
    Swal.fire('Falta información', 'Todo repartidor necesita acceso al sistema: completa usuario, contraseña y sucursal.', 'warning');
    return;
  }

  const payload = {
    nombre,
    tipo_identificacion: document.getElementById('agTipoId').value,
    numero_identificacion: document.getElementById('agNumeroId').value.trim() || null,
    telefono_emergencia: document.getElementById('agTelefono').value.trim() || null,
    fecha_ingreso: document.getElementById('agFechaIngreso').value || null,
    habilidades: habilidadesNuevoRepartidor,
    usuario_login: usuarioLogin,
    password_login: passwordLogin,
    id_sucursal_usuario: parseInt(idSucursal),
  };

  fetch(BASE_URL + '/backend/delivery/agregar_repartidor.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  }).then(r=>r.json()).then(data=>{
    if (data.success) {
      modalAgregar.hide();
      const msg = data.acceso_creado
        ? `Repartidor creado y su acceso al sistema quedó enlazado automáticamente (usuario: ${usuarioLogin}).`
        : 'Repartidor creado correctamente.';
      Swal.fire({title:'¡Listo!', text: msg, icon:'success'});
      cargar();
    } else {
      Swal.fire('Error', data.message, 'error');
    }
  }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
}
</script>
