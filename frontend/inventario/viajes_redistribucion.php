<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario'])) {
    header("Location: " . dirname(__DIR__, 2) . "/frontend/index.php");
    exit();
}
$base_url = '/Sistema-Gestor-de-Farmacias';
?>

<style>
/* Corrección de capas para el modal de reasignación */
#modalReasignar {
    z-index: 1055 !important;
}

.modal-backdrop {
    z-index: 1050 !important;
}
</style>

<div class="container-fluid">
    <div class="mb-4">
        <nav class="tarea5-breadcrumb" aria-label="breadcrumb">
            <a href="menuprincipal.php?mod=vencimientos" class="tarea5-breadcrumb-home" title="Vencimientos">
                <span class="material-symbols-rounded">home</span>
            </a>
            <span class="tarea5-breadcrumb-sep material-symbols-rounded">chevron_right</span>
            <span class="tarea5-breadcrumb-actual">Viajes de redistribución</span>
        </nav>
        <h2 class="mb-0">
            <span class="material-symbols-rounded align-middle me-2 text-info">local_shipping</span>
            Viajes de redistribución
        </h2>
        <p class="text-muted mb-0">Traslados de inventario entre sucursales generados por el proceso de vencimientos. Aquí se cierran (marcar entregado) y se reasignan si el vehículo o el repartidor dejan de estar disponibles a mitad de camino.</p>
    </div>

    <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="switchHistorial" onchange="cargarViajes()">
        <label class="form-check-label small" for="switchHistorial">Incluir viajes ya cerrados (últimos 30 días)</label>
    </div>

    <div id="listaViajes" class="d-flex flex-column gap-3"></div>
    <div id="sinViajes" class="text-center text-muted py-5" style="display:none;">
        <span class="material-symbols-rounded" style="font-size:48px;">local_shipping</span>
        <p class="mt-2 mb-0">No hay viajes de redistribución activos en este momento.</p>
    </div>

    <!-- Modal: reasignar transporte -->
    <div class="modal fade" id="modalReasignar" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <!-- Header con gradiente -->
                <div class="modal-header bg-gradient-primary text-white rounded-top-4" style="background: linear-gradient(135deg, #0d6efd, #0a58ca);">
                    <div class="d-flex align-items-center">
                        <span class="material-symbols-rounded me-2" style="font-size:28px;">sync_alt</span>
                        <div>
                            <h5 class="modal-title fw-bold mb-0">Reasignar transporte</h5>
                            <small class="opacity-75">Selecciona un nuevo vehículo y repartidor para este viaje</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Motivo de reasignación -->
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase text-muted">
                            <span class="material-symbols-rounded align-middle" style="font-size:16px;">edit_note</span>
                            Motivo de la reasignación
                        </label>
                        <textarea class="form-control" id="motivoReasignacion" rows="2" 
                            placeholder="Ej. El repartidor se reportó enfermo a mitad de camino, se reasigna el vehículo a otro repartidor disponible." 
                            required style="border-radius: 12px; resize: none;"></textarea>
                    </div>

                    <!-- Loader -->
                    <div id="reasignarCargando" class="text-center py-5">
                        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
                        <p class="text-muted mt-3">Buscando vehículos disponibles para reasignar...</p>
                    </div>

                    <!-- Sin datos -->
                    <div id="reasignarSinDatos" class="text-center py-5" style="display:none;">
                        <span class="material-symbols-rounded text-warning" style="font-size:64px;">warning</span>
                        <h5 class="mt-3">No hay vehículos disponibles</h5>
                        <p class="text-muted">No hay otros vehículos disponibles con repartidor activo en este momento.<br>Puedes intentar más tarde o continuar con el viaje actual.</p>
                    </div>

                    <!-- Tabla de vehículos -->
                    <div id="reasignarTablaWrap" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <span class="badge bg-success rounded-pill px-3 py-2">
                                    <span class="material-symbols-rounded align-middle" style="font-size:16px;">check_circle</span>
                                    Vehículos disponibles para reasignar
                                </span>
                            </div>
                            <small class="text-muted">Selecciona un vehículo para reasignar</small>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:40px;"></th>
                                        <th>Vehículo</th>
                                        <th>Conductor disponible</th>
                                        <th class="text-center">Capacidad</th>
                                        <th class="text-end">Combustible est.</th>
                                        <th class="text-end">Costo</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaReasignarBody"></tbody>
                            </table>
                        </div>

                        <!-- Información adicional -->
                        <div class="mt-3 pt-3 border-top">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center text-muted small">
                                        <span class="material-symbols-rounded me-1" style="font-size:16px;">info</span>
                                        <span>El vehículo actual quedará disponible al completar la reasignación</span>
                                    </div>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <span class="badge bg-light text-dark border">
                                        <span class="material-symbols-rounded align-middle" style="font-size:14px;">schedule</span>
                                        Tiempo estimado de llegada: <span id="tiempoEstimadoReasignacion">—</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                        <span class="material-symbols-rounded align-middle" style="font-size:18px;">close</span>
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-info text-white px-4" id="btnConfirmarReasignacion" onclick="confirmarReasignacion()" disabled>
                        <span class="material-symbols-rounded align-middle" style="font-size:18px;">sync_alt</span>
                        Reasignar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';
let viajeEnModal = null;
let vehiculoElegidoReasignacion = null;

const SEMAFORO_INFO = {
    OPTIMA: { icono: '⭐', clase: 'success' },
    VIABLE: { icono: '✓', clase: 'info' },
    NO_OPTIMA: { icono: '⚠️', clase: 'warning' },
    NO_RECOMENDADA: { icono: '❌', clase: 'danger' },
};
const ESTADO_INFO = {
    PLANIFICADO: { texto: 'Planificado', clase: 'secondary' },
    EN_TRANSITO: { texto: 'En tránsito', clase: 'info' },
    ENTREGADO: { texto: 'Entregado', clase: 'success' },
    CANCELADO: { texto: 'Cancelado', clase: 'dark' },
};

function cargarViajes() {
    const historial = document.getElementById('switchHistorial').checked ? 1 : 0;
    fetch(`${BASE_URL}/backend/inventario/listar_viajes_redistribucion.php?historial=${historial}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            renderViajes(data.viajes);
        });
}

function renderViajes(viajes) {
    const cont = document.getElementById('listaViajes');
    document.getElementById('sinViajes').style.display = viajes.length === 0 ? 'block' : 'none';

    cont.innerHTML = viajes.map(v => {
        const estadoInfo = ESTADO_INFO[v.estado] || { texto: v.estado, clase: 'secondary' };
        const semaforoInfo = SEMAFORO_INFO[v.semaforo] || {};
        const activo = ['PLANIFICADO', 'EN_TRANSITO'].includes(v.estado);

        return `
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <span class="badge bg-${estadoInfo.clase} mb-1">${estadoInfo.texto}</span>
                        ${v.semaforo ? `<span class="badge bg-${semaforoInfo.clase || 'secondary'} mb-1">${semaforoInfo.icono || ''} score ${v.score_recomendacion}</span>` : ''}
                        <h6 class="mb-0">${v.medicamento_nombre} <small class="text-muted">(lote ${v.numero_lote})</small></h6>
                        <div class="text-muted small">${v.sucursal_origen} → ${v.sucursal_destino} · ${v.cantidad} u.</div>
                    </div>
                    <div class="text-end small text-muted">
                        ${v.distancia_km != null ? v.distancia_km + ' km · ' : ''}${v.tiempo_estimado_minutos != null ? v.tiempo_estimado_minutos + ' min est.' : ''}<br>
                        ${v.costo_estimado != null ? 'RD$' + Number(v.costo_estimado).toFixed(2) : ''}
                    </div>
                </div>
                <div class="mt-2 small">
                    <span class="material-symbols-rounded align-middle" style="font-size:16px;">local_shipping</span>
                    ${v.vehiculo_tipo || 'Sin vehículo'} ${v.vehiculo_placa ? '· ' + v.vehiculo_placa : ''} —
                    <span class="material-symbols-rounded align-middle" style="font-size:16px;">person</span>
                    ${v.repartidor_nombre || 'Sin asignar'}
                </div>
                ${activo ? `
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-sm btn-success" onclick="cerrarViaje(${v.id_viaje}, 'ENTREGADO')">
                        <span class="material-symbols-rounded align-middle" style="font-size:16px;">check_circle</span> Marcar entregado
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="abrirReasignar(${v.id_viaje}, ${v.id_sucursal_origen}, ${v.id_sucursal_destino}, '${v.sucursal_origen.replace(/'/g, "\\'")}', '${v.sucursal_destino.replace(/'/g, "\\'")}', ${v.cantidad})">
                        <span class="material-symbols-rounded align-middle" style="font-size:16px;">sync_alt</span> Reasignar
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="cerrarViaje(${v.id_viaje}, 'CANCELADO')">
                        Cancelar
                    </button>
                </div>` : ''}
            </div>
        </div>`;
    }).join('');
}

function cerrarViaje(idViaje, nuevoEstado) {
    let titulo, texto, icono, confirmText, confirmColor;
    
    if (nuevoEstado === 'ENTREGADO') {
        titulo = '✅ ¿Marcar como entregado?';
        texto = 'El producto se transferirá a la sucursal de destino y el vehículo quedará disponible.';
        icono = 'question';
        confirmText = 'Sí, entregar';
        confirmColor = '#198754';
    } else if (nuevoEstado === 'CANCELADO') {
        titulo = '⚠️ ¿Cancelar este viaje?';
        texto = '⚠️ ATENCIÓN: El inventario VOLVERÁ a la sucursal de origen. El vehículo quedará disponible. Esta acción no se puede deshacer.';
        icono = 'warning';
        confirmText = 'Sí, cancelar y devolver';
        confirmColor = '#dc3545';
    }
    
    Swal.fire({
        title: titulo,
        text: texto,
        icon: icono,
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        confirmButtonText: confirmText,
        cancelButtonText: 'No, volver',
        reverseButtons: true
    }).then(res => {
        if (!res.isConfirmed) return;
        
        // Mostrar loading
        Swal.fire({
            title: 'Procesando...',
            text: 'Actualizando el viaje...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        fetch(`${BASE_URL}/backend/inventario/actualizar_estado_viaje.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                id_viaje: idViaje, 
                nuevo_estado: nuevoEstado 
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Listo!',
                    text: data.message,
                    timer: 3000,
                    timerProgressBar: true
                });
                cargarViajes();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo procesar la solicitud'
            });
            console.error('Error:', error);
        });
    });
}

function abrirReasignar(idViaje, idSucursalOrigen, idSucursalDestino, origen, destino, cantidad) {
    viajeEnModal = { id_viaje: idViaje, origen, destino, cantidad };
    vehiculoElegidoReasignacion = null;
    document.getElementById('motivoReasignacion').value = '';
    document.getElementById('btnConfirmarReasignacion').disabled = true;
    document.getElementById('reasignarCargando').style.display = 'block';
    document.getElementById('reasignarSinDatos').style.display = 'none';
    document.getElementById('reasignarTablaWrap').style.display = 'none';

    new bootstrap.Modal(document.getElementById('modalReasignar')).show();

    // Reutiliza el mismo endpoint del wizard de creación: como el vehículo
    // actual sigue EN_USO mientras se reasigna, automáticamente no aparece
    // en esta lista de disponibles.
    fetch(`${BASE_URL}/backend/inventario/obtener_logistica_disponible.php?id_sucursal_origen=${idSucursalOrigen}&id_sucursal_destino=${idSucursalDestino}&cantidad=${cantidad}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('reasignarCargando').style.display = 'none';
            if (!data.success || !data.hay_logistica_disponible) {
                document.getElementById('reasignarSinDatos').style.display = 'block';
                return;
            }
            document.getElementById('reasignarTablaWrap').style.display = 'block';
            document.getElementById('tablaReasignarBody').innerHTML = data.vehiculos.map(v => `
                <tr>
                    <td><input type="radio" name="vehiculoReasignar" onchange='vehiculoElegidoReasignacion = ${JSON.stringify(v).replace(/'/g, "&apos;")}; document.getElementById("btnConfirmarReasignacion").disabled = false;'></td>
                    <td>${v.tipo} ${v.placa ? '· ' + v.placa : ''}</td>
                    <td>${v.repartidor_nombre}</td>
                    <td class="text-end">${v.combustible_estimado_gal != null ? v.combustible_estimado_gal + ' gal' : '—'}</td>
                </tr>
            `).join('');
        });
}

function abrirReasignar(idViaje, idSucursalOrigen, idSucursalDestino, origen, destino, cantidad) {
    viajeEnModal = {
        id_viaje: idViaje,
        origen: origen,
        destino: destino,
        cantidad: cantidad
    };

    vehiculoElegidoReasignacion = null;
    const modalEl = document.getElementById('modalReasignar');

    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }

    document.getElementById('motivoReasignacion').value = '';
    document.getElementById('btnConfirmarReasignacion').disabled = true;
    document.getElementById('btnConfirmarReasignacion').innerHTML = '<span class="material-symbols-rounded align-middle" style="font-size:18px;">sync_alt</span> Reasignar';
    document.getElementById('btnConfirmarReasignacion').className = 'btn btn-info text-white px-4';

    document.getElementById('reasignarCargando').style.display = 'block';
    document.getElementById('reasignarSinDatos').style.display = 'none';
    document.getElementById('reasignarTablaWrap').style.display = 'none';
    document.getElementById('tablaReasignarBody').innerHTML = '';
    document.getElementById('tiempoEstimadoReasignacion').textContent = '—';

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    fetch(
        `${BASE_URL}/backend/inventario/obtener_logistica_disponible.php?id_sucursal_origen=${idSucursalOrigen}&id_sucursal_destino=${idSucursalDestino}&cantidad=${cantidad}`
    )
    .then(r => r.json())
    .then(data => {
        document.getElementById('reasignarCargando').style.display = 'none';

        console.log('Datos recibidos:', data);

        if (!data.success) {
            document.getElementById('reasignarSinDatos').style.display = 'block';
            document.getElementById('reasignarSinDatos').innerHTML = '<span class="material-symbols-rounded text-warning" style="font-size:64px;">warning</span><h5 class="mt-3">Error</h5><p class="text-muted">' + (data.message || 'Error al obtener vehículos') + '</p>';
            return;
        }

        if (!data.hay_logistica_disponible || !data.vehiculos || data.vehiculos.length === 0) {
            document.getElementById('reasignarSinDatos').style.display = 'block';
            return;
        }

        document.getElementById('reasignarTablaWrap').style.display = 'block';

        if (data.tiempo_estimado_minutos) {
            document.getElementById('tiempoEstimadoReasignacion').textContent = data.tiempo_estimado_minutos + ' min';
        }

        // ================================================================
        // CORRECCIÓN: Renderizar vehículos con onclick CORRECTO
        // ================================================================
        document.getElementById('tablaReasignarBody').innerHTML =
            data.vehiculos.map((v, index) => {
                const capacidadCarga = v.capacidad_carga || 0;
                const esSuficiente = capacidadCarga === 0 || capacidadCarga >= cantidad;
                const barraColor = capacidadCarga > 0 ? (esSuficiente ? 'success' : 'danger') : 'secondary';
                const barraPorcentaje = capacidadCarga > 0 ? Math.min((cantidad / capacidadCarga) * 100, 100) : 0;

                const vehiculoJSON = JSON.stringify(v).replace(/\\/g, '\\\\').replace(/'/g, "\\'");

                return `
                <tr style="cursor:pointer;" data-vehiculo='${vehiculoJSON}' onclick="seleccionarVehiculoDesdeFila(this)">
                    <td>
                        <input
                            type="radio"
                            name="vehiculoReasignar"
                            value="${v.id_vehiculo}"
                            onchange="seleccionarVehiculoReasignacion(${vehiculoJSON})"
                        >
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="material-symbols-rounded me-2 text-primary" style="font-size:28px;">
                                ${v.tipo?.includes('Moto') || v.tipo?.includes('moto') ? 'two_wheeler' : 
                                  v.tipo?.includes('Carro') || v.tipo?.includes('carro') ? 'directions_car' : 
                                  v.tipo?.includes('Camión') || v.tipo?.includes('camion') ? 'local_shipping' : 
                                  'directions_car'}
                            </span>
                            <div>
                                <div class="fw-bold">${v.tipo || 'Vehículo'}</div>
                                <small class="text-muted">${v.placa ? 'Placa: ' + v.placa : 'Sin placa'}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="material-symbols-rounded me-1 text-secondary" style="font-size:20px;">person</span>
                            ${v.repartidor_nombre || 'Sin asignar'}
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="d-flex flex-column align-items-center">
                            ${capacidadCarga > 0 ? `
                                <span class="fw-bold ${esSuficiente ? 'text-success' : 'text-danger'}">
                                    ${capacidadCarga} u.
                                </span>
                                <div class="progress w-100" style="height:4px; max-width:80px;">
                                    <div class="progress-bar bg-${barraColor}" style="width:${barraPorcentaje}%;"></div>
                                </div>
                                <small class="text-muted">${esSuficiente ? '✅ Suficiente' : '⚠️ Insuficiente para esta cantidad'}</small>
                            ` : `
                                <span class="text-muted">—</span>
                                <small class="text-muted">Sin capacidad registrada</small>
                            `}
                        </div>
                    </td>
                    <td class="text-end">
                        ${v.combustible_estimado_gal !== null ? 
                            `<span class="fw-bold">${Number(v.combustible_estimado_gal).toFixed(2)} gal</span>` : 
                            '<span class="text-muted">—</span>'
                        }
                    </td>
                    <td class="text-end">
                        ${v.costo_combustible_estimado !== null ? 
                            `<span class="fw-bold text-primary">RD$${Number(v.costo_combustible_estimado).toFixed(2)}</span>` : 
                            '<span class="text-muted">—</span>'
                        }
                    </td>
                </tr>`;
            }).join('');
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('reasignarCargando').style.display = 'none';
        document.getElementById('reasignarSinDatos').style.display = 'block';
        document.getElementById('reasignarSinDatos').innerHTML = '<span class="material-symbols-rounded text-danger" style="font-size:64px;">error</span><h5 class="mt-3">Error de conexión</h5><p class="text-muted">No se pudo conectar con el servidor</p>';
    });
}

function seleccionarVehiculoReasignacion(vehiculo) {
    vehiculoElegidoReasignacion = vehiculo;
    const btn = document.getElementById('btnConfirmarReasignacion');
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-rounded align-middle" style="font-size:18px;">check_circle</span> Reasignar con ' + (vehiculo.tipo || 'vehículo');
    btn.classList.remove('btn-info');
    btn.classList.add('btn-success');
}

function seleccionarVehiculoDesdeFila(fila) {
    // Obtener el vehículo del data attribute
    const vehiculoData = fila.getAttribute('data-vehiculo');
    if (vehiculoData) {
        try {
            const vehiculo = JSON.parse(vehiculoData);
            // Buscar el radio button dentro de la fila y marcarlo
            const radio = fila.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                // Disparar el evento onchange manualmente
                radio.dispatchEvent(new Event('change'));
            }
            // Llamar a la función de selección
            seleccionarVehiculoReasignacion(vehiculo);
        } catch (e) {
            console.error('Error al parsear vehículo:', e);
        }
    }
}

function confirmarReasignacion() {
    if (!vehiculoElegidoReasignacion) {
        Swal.fire('Error', 'Debes seleccionar un vehículo primero', 'warning');
        return;
    }

    const motivo = document.getElementById('motivoReasignacion').value.trim();
    if (!motivo) {
        Swal.fire('Error', 'Debes escribir el motivo de la reasignación', 'warning');
        document.getElementById('motivoReasignacion').focus();
        return;
    }

    const btn = document.getElementById('btnConfirmarReasignacion');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';

    const payload = {
        id_viaje: viajeEnModal.id_viaje,
        id_vehiculo_nuevo: vehiculoElegidoReasignacion.id_vehiculo,
        id_repartidor_nuevo: vehiculoElegidoReasignacion.id_repartidor,
        motivo: motivo
    };

    fetch(`${BASE_URL}/backend/inventario/reasignar_viaje.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-rounded align-middle" style="font-size:18px;">sync_alt</span> Reasignar';

        if (data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalReasignar'));
            modal.hide();
            Swal.fire('¡Reasignación exitosa!', data.message, 'success');
            cargarViajes(); // Recargar la lista
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-rounded align-middle" style="font-size:18px;">sync_alt</span> Reasignar';
        Swal.fire('Error', 'Error de conexión con el servidor', 'error');
    });
}

cargarViajes();
</script>
