<?php
// frontend/delivery/detalle_entrega.php — Confirmación de entrega (Repartidor)
require_once __DIR__ . '/../../backend/conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['id_sesion'])) { header("Location: ../index.php"); exit(); }
if (!in_array($_SESSION['rol'], ['Repartidor', 'Administrador'])) {
    header("Location: ../index.php?error=Sin+permisos"); exit();
}

$id_entrega = intval($_GET['id'] ?? 0);
if (!$id_entrega) { header("Location: agenda.php"); exit(); }
$usuario_nombre = $_SESSION['nombre'] ?? $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery | Confirmar Entrega — PharmaSystem</title>
    <link rel="icon" type="image/x-icon" href="../../assets/img/Icon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary:#1F5C99; --success:#2E8B57; --danger:#DC3545; --bg:#F0F2F5; }
        * { font-family:"Poppins",sans-serif; box-sizing:border-box; }
        body { background:var(--bg); margin:0; }
        .delivery-nav { background:var(--primary); padding:0 1.25rem; height:60px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:1000; box-shadow:0 2px 8px rgba(0,0,0,.2); }
        .nav-brand { color:#fff; font-weight:700; font-size:1rem; text-decoration:none; display:flex; align-items:center; gap:.5rem; }
        .nav-user { color:#ADE8F4; font-size:.8rem; display:flex; align-items:center; gap:.75rem; }
        .btn-logout { background:rgba(255,255,255,.15); border:none; color:#fff; border-radius:8px; padding:.3rem .7rem; font-size:.78rem; cursor:pointer; }
        .breadcrumb-bar { background:#fff; padding:.5rem 1.25rem; border-bottom:1px solid #dee2e6; font-size:.8rem; color:#555; }
        .breadcrumb-bar a { color:var(--primary); text-decoration:none; }
        .card-delivery { background:#fff; border-radius:12px; padding:1.25rem; box-shadow:0 1px 4px rgba(0,0,0,.06); height:100%; }
        .card-delivery h6 { color:var(--primary); font-weight:700; margin-bottom:1rem; display:flex; align-items:center; gap:.5rem; }
        .info-row { display:flex; align-items:flex-start; gap:.5rem; margin-bottom:.6rem; font-size:.85rem; border-bottom:1px solid #f0f0f0; padding-bottom:.5rem; }
        .info-label { font-weight:600; color:#555; min-width:90px; flex-shrink:0; }
        .info-val { color:#1A1A1A; }
        .producto-item { background:#f8f9fa; border-radius:8px; padding:.6rem .9rem; margin-bottom:.4rem; font-size:.82rem; display:flex; justify-content:space-between; align-items:center; }
        .map-placeholder { background:linear-gradient(135deg,#e8f0fb,#ddeeff); border:2px dashed #aac4ee; border-radius:10px; padding:2.5rem 1rem; text-align:center; color:#7aabdd; font-size:.85rem; min-height:180px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.5rem; }
        .radio-card { border:2px solid #dee2e6; border-radius:10px; padding:.8rem 1rem; cursor:pointer; transition:all .2s; display:flex; align-items:center; gap:.75rem; margin-bottom:.5rem; }
        .radio-card:hover { border-color:var(--primary); background:#f0f8ff; }
        .radio-card.selected-exitosa { border-color:var(--success); background:#f0fff4; }
        .radio-card.selected-fallida { border-color:var(--danger); background:#fff5f5; }
        .radio-card input[type=radio] { accent-color:var(--primary); width:18px; height:18px; flex-shrink:0; }
        .btn-confirmar { background:var(--primary); color:#fff; border:none; border-radius:10px; padding:.7rem 1.5rem; font-weight:600; transition:background .2s; width:100%; font-size:.9rem; }
        .btn-confirmar:hover { background:var(--primary-dark,#174a7a); }
        .btn-volver { background:#fff; color:#555; border:1.5px solid #dee2e6; border-radius:10px; padding:.7rem 1.5rem; font-weight:500; transition:all .2s; width:100%; font-size:.9rem; }
        .btn-volver:hover { border-color:#999; }
        .warn-receptor { background:#FFF3CD; border:1.5px solid #C77B00; border-radius:8px; padding:.6rem .9rem; font-size:.78rem; color:#7a5300; margin-bottom:.75rem; display:none; }
    </style>
</head>
<body>

<nav class="delivery-nav">
    <a class="nav-brand" href="agenda.php"><i class="fas fa-truck-fast"></i> SGF | Módulo de Delivery</a>
    <div class="nav-user">
        <span><i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($usuario_nombre) ?></span>
        <a href="../../backend/cerrar_sesion.php" class="btn-logout"><i class="fas fa-sign-out-alt me-1"></i>Salir</a>
    </div>
</nav>

<div class="breadcrumb-bar">
    <i class="fas fa-truck-fast me-1" style="color:var(--primary)"></i>
    <a href="agenda.php">Delivery</a> &rsaquo; <a href="agenda.php">Mi Agenda</a> &rsaquo; <strong>Confirmar Entrega</strong>
</div>

<div class="container-fluid p-3" id="mainContent">
    <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const ID_ENTREGA = <?= $id_entrega ?>;
let RECEPTOR_AUTORIZADO = null;
let productosData = [];
let motivosFallida = [];

function cargarMotivosFallida() {
    fetch('../../backend/delivery/listar_motivos_fallida.php')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('motivoFallidaSelect');
            if (!data.success || !data.motivos.length) {
                sel.innerHTML = '<option value="">No se pudieron cargar los motivos</option>';
                return;
            }
            motivosFallida = data.motivos;
            sel.innerHTML = '<option value="">Selecciona un motivo...</option>' +
                data.motivos.map(m => `<option value="${m.id_motivo}">${m.nombre}</option>`).join('');
        });
}

function renderPage(data) {
    const e = data.entrega;
    const prods = data.productos || [];
    RECEPTOR_AUTORIZADO = e.cedula_receptor_autorizado || null;

    const coord = e.latitud_entrega && e.longitud_entrega ? `${e.latitud_entrega},${e.longitud_entrega}` : null;
    const mapsUrl = coord
        ? `https://www.google.com/maps/dir/?api=1&destination=${coord}`
        : `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(e.direccion_entrega + ' ' + (e.barrio_entrega||'') + ' ' + (e.ciudad_entrega||''))}`;

    const mapHtml = `
        <div class="map-placeholder">
            <i class="fas fa-map-marker-alt fa-2x mb-2"></i>
            <div>${e.direccion_entrega}${e.barrio_entrega ? ', ' + e.barrio_entrega : ''}</div>
            <a href="${mapsUrl}" target="_blank" class="btn btn-outline-primary btn-sm mt-2"><i class="fas fa-external-link-alt me-1"></i>Abrir en Google Maps</a>
        </div>`;

    const prodsHtml = prods.map(p => `
        <div class="producto-item">
            <span><i class="fas fa-pills me-2" style="color:var(--primary)"></i>${p.producto_nombre}</span>
            <span class="badge bg-primary rounded-pill">${p.cantidad} uds.</span>
        </div>`).join('');

    const receptorInfo = e.nombre_receptor_autorizado
        ? `<div class="info-row"><span class="info-label"><i class="fas fa-user-shield me-1"></i>Autorizado:</span><span class="info-val">${e.nombre_receptor_autorizado} ${e.cedula_receptor_autorizado ? '(' + e.cedula_receptor_autorizado + ')' : ''}</span></div>`
        : '';

    // Guardamos los productos para la tabla de cantidades entregadas
    productosData = prods.map(p => ({
        id_detalle: p.id_detalle,
        producto_nombre: p.producto_nombre || 'Producto',
        cantidad: parseInt(p.cantidad)
    }));

    document.getElementById('mainContent').innerHTML = `
    <div class="row g-3">
      <div class="col-12 col-md-6">
        <div class="card-delivery mb-3">
          <h6><i class="fas fa-file-alt"></i>Datos del Pedido</h6>
          <div class="info-row"><span class="info-label"><i class="fas fa-user me-1"></i>Cliente:</span><span class="info-val">${e.cliente_nombre}</span></div>
          <div class="info-row"><span class="info-label"><i class="fas fa-map-pin me-1"></i>Dirección:</span><span class="info-val">${e.direccion_entrega}${e.barrio_entrega ? ', ' + e.barrio_entrega : ''}</span></div>
          ${receptorInfo}
          <div class="info-row"><span class="info-label"><i class="fas fa-pills me-1"></i>Productos:</span><div class="info-val w-100 mt-1">${prodsHtml || '<em class="text-muted">Sin detalle de productos</em>'}</div></div>
          <div class="info-row"><span class="info-label"><i class="fas fa-hashtag me-1"></i>Seguimiento:</span><span class="info-val fw-semibold text-primary">${e.numero_seguimiento || 'N/A'}</span></div>
        </div>
        <div class="card-delivery"><h6><i class="fas fa-map-marked-alt"></i>Vista del mapa / dirección</h6>${mapHtml}</div>
      </div>
      <div class="col-12 col-md-6">
        <div class="card-delivery">
          <h6><i class="fas fa-check-double"></i>Registrar Resultado de Entrega</h6>
          <p class="text-muted mb-3" style="font-size:.82rem;">Indica el resultado de la entrega. Esta acción no se puede deshacer.</p>

          <label class="fw-semibold mb-2" style="font-size:.85rem;">Resultado:</label>
          <div class="radio-card" id="cardExitosa" onclick="seleccionarResultado('EXITOSA')">
            <input type="radio" name="resultado" value="EXITOSA" id="rExitosa">
            <label for="rExitosa" style="cursor:pointer;">
              <span style="color:var(--success);font-weight:600;"><i class="fas fa-check-circle me-1"></i>Entrega Exitosa</span>
              <div style="font-size:.75rem;color:#666;">El cliente recibió el pedido (completo o parcial)</div>
            </label>
          </div>
          <div class="radio-card" id="cardFallida" onclick="seleccionarResultado('FALLIDA')">
            <input type="radio" name="resultado" value="FALLIDA" id="rFallida">
            <label for="rFallida" style="cursor:pointer;">
              <span style="color:var(--danger);font-weight:600;"><i class="fas fa-times-circle me-1"></i>Entrega Fallida</span>
              <div style="font-size:.75rem;color:#666;">No se pudo entregar nada</div>
            </label>
          </div>

          <div class="warn-receptor" id="warnReceptor">
            <i class="fas fa-exclamation-triangle me-1"></i>
            La cédula ingresada no coincide con el receptor autorizado. La entrega quedará marcada "En disputa" para revisión del cajero.
          </div>

          <!-- Bloque EXITOSA: cantidades por producto + receptor -->
          <div id="bloqueExitosa" style="display:none;">
            <div class="mt-3 mb-2">
              <label class="fw-semibold" style="font-size:.85rem;">Confirma la cantidad entregada de cada producto:</label>
              <div id="tablaCantidades" class="mt-2"></div>
              <small class="text-muted" id="hintParcial" style="display:none;">Si alguna cantidad es menor a la pedida, la entrega quedará marcada como <strong>Parcial</strong> automáticamente.</small>
            </div>
            <div class="mb-3 mt-3">
              <label class="form-label fw-semibold" style="font-size:.85rem;">Nombre de quien recibe: <span class="text-danger">*</span></label>
              <input type="text" id="receptor" class="form-control" placeholder="Ingrese el nombre completo" style="font-size:.85rem;">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold" style="font-size:.85rem;">Cédula de quien recibe: <span class="text-danger">*</span></label>
              <input type="text" id="cedula" class="form-control" placeholder="000-0000000-0" style="font-size:.85rem;" oninput="verificarReceptor()">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold" style="font-size:.85rem;">Observaciones (opcional):</label>
              <textarea id="observaciones" class="form-control" rows="2" placeholder="Notas adicionales" style="font-size:.85rem;"></textarea>
            </div>
          </div>
          <!-- Bloque FALLIDA: motivo obligatorio (catálogo) + detalle opcional -->
          <div id="bloqueFallida" style="display:none;">
            <div class="mb-3 mt-3">
              <label class="form-label fw-semibold" style="font-size:.85rem;">Motivo: <span class="text-danger">*</span></label>
              <select id="motivoFallidaSelect" class="form-select" style="font-size:.85rem;">
                <option value="">Cargando motivos...</option>
              </select>
            </div>
            <div class="mb-4">
              <label class="form-label fw-semibold" style="font-size:.85rem;">Detalle adicional (opcional):</label>
              <textarea id="detalleFallida" class="form-control" rows="2" placeholder="Cualquier información adicional que quieras dejar registrada" style="font-size:.85rem;"></textarea>
            </div>
          </div>

          <div class="d-flex gap-2 mt-2">
            <button class="btn-volver" onclick="location.href='agenda.php'"><i class="fas fa-arrow-left me-1"></i>Volver a Agenda</button>
            <button class="btn-confirmar" onclick="confirmarEntrega()"><i class="fas fa-check me-1"></i>Registrar Confirmación</button>
          </div>
        </div>
      </div>
    </div>`;
}

function renderTablaCantidades() {
    const rows = productosData.map((p, i) => `
        <div class="d-flex align-items-center justify-content-between mb-2 p-2" style="background:#f8f9fa;border-radius:8px;">
            <div style="font-size:.8rem;">
                <div class="fw-semibold">${p.producto_nombre}</div>
                <div class="text-muted">Pedido: ${p.cantidad} uds.</div>
            </div>
            <input type="number" class="form-control form-control-sm" style="width:80px;" id="cant_${i}"
                   min="0" max="${p.cantidad}" value="${p.cantidad}" data-id-detalle="${p.id_detalle}"
                   oninput="verificarParcial()">
        </div>`).join('');
    document.getElementById('tablaCantidades').innerHTML = rows;
    verificarParcial();
}

function verificarParcial() {
    let hayParcial = false;
    let totalEntregado = 0;
    productosData.forEach((p, i) => {
        const inp = document.getElementById('cant_' + i);
        if (!inp) return;
        let val = parseInt(inp.value);
        if (isNaN(val) || val < 0) val = 0;
        if (val > p.cantidad) val = p.cantidad;
        inp.value = val;
        totalEntregado += val;
        if (val < p.cantidad) hayParcial = true;
    });
    const hint = document.getElementById('hintParcial');
    if (totalEntregado === 0) {
        hint.style.display = 'block';
        hint.innerHTML = '<i class="fas fa-exclamation-triangle text-danger me-1"></i><strong class="text-danger">Todas las cantidades están en 0.</strong> Si no se entregó nada, usa "Entrega Fallida" en vez de esto.';
    } else if (hayParcial) {
        hint.style.display = 'block';
        hint.innerHTML = 'Si alguna cantidad es menor a la pedida, la entrega quedará marcada como <strong>Parcial</strong> automáticamente.';
    } else {
        hint.style.display = 'none';
    }
}

function seleccionarResultado(val) {
    document.getElementById('rExitosa').checked = (val === 'EXITOSA');
    document.getElementById('rFallida').checked = (val === 'FALLIDA');
    document.getElementById('cardExitosa').className = 'radio-card' + (val === 'EXITOSA' ? ' selected-exitosa' : '');
    document.getElementById('cardFallida').className = 'radio-card' + (val === 'FALLIDA' ? ' selected-fallida' : '');
    document.getElementById('bloqueExitosa').style.display = (val === 'EXITOSA') ? 'block' : 'none';
    document.getElementById('bloqueFallida').style.display = (val === 'FALLIDA') ? 'block' : 'none';
    if (val === 'EXITOSA') renderTablaCantidades();
    verificarReceptor();
}

function verificarReceptor() {
    const resultado = document.querySelector('input[name="resultado"]:checked')?.value;
    const cedula = document.getElementById('cedula')?.value.trim();
    const warn = document.getElementById('warnReceptor');
    if (resultado === 'EXITOSA' && RECEPTOR_AUTORIZADO && cedula && cedula !== RECEPTOR_AUTORIZADO) {
        warn.style.display = 'block';
    } else {
        warn.style.display = 'none';
    }
}

function confirmarEntrega() {
    const resultado = document.querySelector('input[name="resultado"]:checked')?.value;
    if (!resultado) { Swal.fire('Falta información', 'Selecciona el resultado de la entrega.', 'warning'); return; }

    let payload = { id_entrega: ID_ENTREGA, resultado };
    let tituloConfirm = '';

    if (resultado === 'FALLIDA') {
        const id_motivo_fallida = document.getElementById('motivoFallidaSelect')?.value;
        if (!id_motivo_fallida) {
            Swal.fire('Falta información', 'Selecciona el motivo por el cual no se pudo completar la entrega.', 'warning');
            return;
        }
        payload.id_motivo_fallida = parseInt(id_motivo_fallida);
        payload.detalle_fallida = document.getElementById('detalleFallida')?.value.trim() || '';
        tituloConfirm = '¿Reportar entrega fallida?';
    } else {
        const receptor        = document.getElementById('receptor')?.value.trim();
        const cedula_receptor = document.getElementById('cedula')?.value.trim();
        const observaciones   = document.getElementById('observaciones')?.value.trim();

        if (!receptor || !cedula_receptor) {
            Swal.fire('Falta información', 'Debes indicar el nombre y la cédula de quien recibió el pedido.', 'warning');
            return;
        }

        const productos = productosData.map((p, i) => ({
            id_detalle: p.id_detalle,
            cantidad_entregada: parseInt(document.getElementById('cant_' + i)?.value || 0)
        }));

        const totalEntregado = productos.reduce((sum, p) => sum + p.cantidad_entregada, 0);
        if (totalEntregado === 0) {
            Swal.fire('Cantidades en cero', 'No se puede confirmar como "Entrega Exitosa" si no se entregó ningún producto. Selecciona "Entrega Fallida" e indica el motivo.', 'warning');
            return;
        }

        payload.nombre_receptor = receptor;
        payload.cedula_receptor = cedula_receptor;
        payload.observaciones   = observaciones;
        payload.productos       = productos;

        const esParcial = productos.some((p, i) => p.cantidad_entregada < productosData[i].cantidad);
        tituloConfirm = esParcial ? '¿Confirmar entrega parcial?' : '¿Confirmar entrega completa?';
    }

    Swal.fire({ title: tituloConfirm, icon: resultado === 'EXITOSA' ? 'question' : 'warning', showCancelButton: true, confirmButtonText: 'Sí, registrar', cancelButtonText: 'Cancelar', confirmButtonColor: resultado === 'EXITOSA' ? '#2E8B57' : '#DC3545' })
    .then(r => {
        if (!r.isConfirmed) return;
        Swal.fire({ title: 'Registrando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        fetch('../../backend/delivery/confirmar_entrega_delivery.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const disputaMsg = data.estado_recepcion === 'EN_DISPUTA'
                    ? ' Se marcó en disputa por diferencia en el receptor.' : '';
                Swal.fire({ title: '¡Registrado!', text: `Entrega marcada como ${data.estado_final}.${disputaMsg}`, icon: data.estado_recepcion === 'EN_DISPUTA' ? 'warning' : 'success', timer: 2500, showConfirmButton: false })
                .then(() => location.href = 'agenda.php');
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
    });
}

fetch(`../../backend/delivery/get_detalle_entrega.php?id_entrega=${ID_ENTREGA}`)
    .then(r => r.json())
    .then(data => {
        if (data.success) { renderPage(data); cargarMotivosFallida(); }
        else { Swal.fire('Error', data.message, 'error').then(() => location.href = 'agenda.php'); }
    });
</script>
</body>
</html>
