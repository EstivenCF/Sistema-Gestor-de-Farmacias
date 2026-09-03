<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header("Location: " . dirname(__DIR__, 2) . "/frontend/index.php");
    exit();
}

date_default_timezone_set('America/Santo_Domingo');

$base_path = dirname(__DIR__, 2);
require_once $base_path . '/backend/queries/index.php';
require_once $base_path . '/backend/inventario/riesgo_vencimiento_lib.php';

$base_url = '/Sistema-Gestor-de-Farmacias';

$id_lote = isset($_GET['id_lote']) ? (int)$_GET['id_lote'] : 0;
$id_sucursal = isset($_GET['id_sucursal']) ? (int)$_GET['id_sucursal'] : 0;
// Opcional: si se llega desde una acción de recuperación ya creada en la Pantalla #04
// (o desde Control de Stock, sin acción asociada — el wizard funciona igual en ambos casos)
$id_accion = isset($_GET['id_accion']) ? (int)$_GET['id_accion'] : null;

$umbrales = obtenerUmbralesVencimiento($conexion);
$lote = null;
$error = null;

if ($id_lote > 0 && $id_sucursal > 0) {
    $lote = evaluarLoteDetalle($conexion, $id_lote, $id_sucursal, $umbrales);
    if (!$lote) {
        $error = 'No se encontró stock de ese lote en esa sucursal.';
    }
} else {
    $error = 'Falta indicar el lote y la sucursal de origen (id_lote / id_sucursal).';
}
?>

<div class="container-fluid">
    <div class="mb-4">
        <nav class="tarea5-breadcrumb" aria-label="breadcrumb">
            <a href="menuprincipal.php?mod=vencimientos" class="tarea5-breadcrumb-home" title="Vencimientos">
                <span class="material-symbols-rounded">home</span>
            </a>
            <?php if (!$error): ?>
                <span class="tarea5-breadcrumb-sep material-symbols-rounded">chevron_right</span>
                <a href="menuprincipal.php?mod=detalle_riesgo_lote&id_lote=<?php echo $id_lote; ?>&id_sucursal=<?php echo $id_sucursal; ?>" class="tarea5-breadcrumb-link">Detalle de lote</a>
            <?php endif; ?>
            <span class="tarea5-breadcrumb-sep material-symbols-rounded">chevron_right</span>
            <span class="tarea5-breadcrumb-actual">Redistribución inteligente</span>
        </nav>
        <h2 class="mb-0">
            <span class="material-symbols-rounded align-middle me-2 text-info">swap_horiz</span>
            Redistribución inteligente de stock
        </h2>
        <?php if (!$error): ?>
            <p class="text-muted mb-0">
                Lote <code><?php echo htmlspecialchars($lote['numero_lote']); ?></code> ·
                <?php echo htmlspecialchars($lote['medicamento_nombre']); ?> ·
                Existencia disponible en origen: <strong><?php echo $lote['cantidad']; ?> u.</strong>
            </p>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><span class="material-symbols-rounded align-middle me-1">error</span> <?php echo htmlspecialchars($error); ?></div>
        <a href="menuprincipal.php?mod=vencimientos" class="btn btn-outline-secondary">Volver a Vencimientos</a>
    <?php else: ?>

        <!-- Indicador de pasos -->
        <div class="d-flex align-items-center gap-2 mb-4 flex-wrap" id="indicadorPasos">
            <button type="button" class="badge rounded-pill bg-info text-white px-3 py-2 border-0" data-paso="2" onclick="volverAPaso(2)">1. Recomendaciones</button>
            <span class="material-symbols-rounded text-muted">chevron_right</span>
            <button type="button" class="badge rounded-pill bg-light text-muted px-3 py-2 border-0" data-paso="3" onclick="volverAPasoTransporte()">2. Transporte</button>
            <span class="material-symbols-rounded text-muted">chevron_right</span>
            <button type="button" class="badge rounded-pill bg-light text-muted px-3 py-2 border-0" data-paso="4" onclick="volverAPasoConfirmacion()">3. Confirmar</button>
        </div>

        <!-- PASO 1: ranking de sucursales, calculado por el motor de recomendación -->
        <div id="pasoRanking">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <div id="rankingCargando" class="text-center py-5">
                        <div class="spinner-border text-info mb-3"></div>
                        <p class="text-muted mb-0">Analizando necesidad, demanda histórica, urgencia, distancia y combustible de cada sucursal...</p>
                    </div>
                    <div id="rankingContenido" style="display:none;">
                        <h6 class="mb-1">Sucursales candidatas, ordenadas por conveniencia</h6>
                        <small class="text-muted d-block mb-3">
                            Cada sucursal se evalúa con SU propio perfil de criterios (peso + si prefiere más o menos IRV, inventario, distancia, etc.). No se usa la misma ponderación para todas.
                        </small>
                        <div id="listaRanking" class="d-flex flex-column gap-3"></div>
                    </div>
                    <div id="rankingError" class="alert alert-warning" style="display:none;"></div>
                </div>
            </div>
        </div>

        <!-- PASO 3: resumen del viaje -->
        <div id="pasoResumen" style="display:none;">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <h6 class="mb-3">Resumen del viaje</h6>
                    <div class="row g-3" id="resumenViaje"></div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="button" class="btn btn-info text-white" id="btnConfirmarViaje" onclick="confirmarTransferencia()">
                            <span class="material-symbols-rounded align-middle me-1" style="font-size:18px;">check_circle</span>
                            Confirmar redistribución
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="volverAPaso(2)">Volver al ranking</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Paso 2: transporte disponible (vehículo + conductor) -->
        <div class="modal fade" id="modalTransporte" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <!-- Header con gradiente -->
                    <div class="modal-header bg-gradient-primary text-white rounded-top-4" style="background: linear-gradient(135deg, #0d6efd, #0a58ca);">
                        <div class="d-flex align-items-center">
                            <span class="material-symbols-rounded me-2" style="font-size:28px;">local_shipping</span>
                            <div>
                                <h5 class="modal-title fw-bold mb-0">Transporte disponible</h5>
                                <small class="opacity-75">Hacia <span id="modalTransporteDestino" class="fw-bold"></span></small>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body p-4">
                        <!-- Loader -->
                        <div id="transporteCargando" class="text-center py-5">
                            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
                            <p class="text-muted mt-3">Buscando vehículos disponibles...</p>
                        </div>

                        <!-- Sin datos -->
                        <div id="transporteSinDatos" class="text-center py-5" style="display:none;">
                            <span class="material-symbols-rounded text-warning" style="font-size:64px;">warning</span>
                            <h5 class="mt-3">No hay vehículos disponibles</h5>
                            <p class="text-muted">No hay vehículos disponibles con repartidor activo en este momento.<br>Puedes continuar sin asignar transporte o intentar más tarde.</p>
                        </div>

                        <!-- Tabla de vehículos -->
                        <div id="transporteTablaWrap" style="display:none;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <span class="badge bg-success rounded-pill px-3 py-2">
                                        <span class="material-symbols-rounded align-middle" style="font-size:16px;">check_circle</span>
                                        Vehículos disponibles
                                    </span>
                                </div>
                                <small class="text-muted">Selecciona un vehículo para continuar</small>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:40px;"></th>
                                            <th>Vehículo</th>
                                            <th>Conductor</th>
                                            <th class="text-center">Capacidad</th>
                                            <th class="text-end">Combustible</th>
                                            <th class="text-end">Costo</th>
                                            <th class="text-end">Tiempo</th>  
                                        </tr>
                                    </thead>
                                    <tbody id="tablaVehiculosBody"></tbody>
                                </table>
                            </div>

                            <!-- Cantidad a transferir -->
                            <div class="mt-4 pt-3 border-top">
                                <div class="row align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold small text-uppercase text-muted">
                                            <span class="material-symbols-rounded align-middle" style="font-size:16px;">inventory_2</span>
                                            Cantidad a transferir
                                        </label>
                                        <div class="input-group">
                                            <input type="number" class="form-control form-control-lg" id="cantidadTransferir" min="1" style="font-weight:bold;">
                                            <span class="input-group-text bg-light fw-bold">u.</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small">
                                            <span class="material-symbols-rounded align-middle" style="font-size:14px;">info</span>
                                            Disponible: <strong id="cantidadDisponibleLabel"><?php echo $lote['cantidad'] ?? 0; ?></strong> u.
                                        </div>
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
                        <button type="button" class="btn btn-outline-info" onclick="continuarSinTransporte()">
                            <span class="material-symbols-rounded align-middle" style="font-size:18px;">skip_next</span>
                            Continuar sin transporte
                        </button>
                        <button type="button" class="btn btn-info text-white px-4" id="btnElegirVehiculo" onclick="continuarConVehiculo()" disabled>
                            <span class="material-symbols-rounded align-middle" style="font-size:18px;">check_circle</span>
                            Usar vehículo seleccionado
                        </button>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const BASE_URL = '<?php echo $base_url; ?>';
    const idLote = <?php echo $id_lote; ?>;
    const idSucursalOrigen = <?php echo $id_sucursal; ?>;
    const idAccion = <?php echo $id_accion ? $id_accion : 'null'; ?>;
    const cantidadDisponible = <?php echo $lote['cantidad'] ?? 0; ?>;

    let rankingActual = [];
    let seleccion = {
        sucursal: null,
        vehiculo: null,
        distancia: null,
        cantidad: cantidadDisponible
    };
    let modalTransporteInstance = null;
    
    // ================================================================
    // SOLO UNA DECLARACIÓN DE vehiculosDisponibles
    // ================================================================
    let vehiculosDisponibles = [];
    let modalAbierto = false;

    function marcarPaso(n) {
        document.querySelectorAll('#indicadorPasos [data-paso]').forEach(el => {
            const paso = parseInt(el.dataset.paso);
            el.classList.toggle('bg-info', paso === n);
            el.classList.toggle('text-white', paso === n);
            el.classList.toggle('bg-light', paso !== n);
            el.classList.toggle('text-muted', paso !== n);
        });
    }

    function cargarRanking() {
        fetch(
            BASE_URL +
            '/backend/inventario/generar_ranking_destinos.php?id_lote=' +
            encodeURIComponent(idLote) +
            '&id_sucursal_origen=' +
            encodeURIComponent(idSucursalOrigen)
        )
        .then(function(response) {

            return response.text().then(function(texto) {

                console.log(
                    'Respuesta generar_ranking_destinos.php:',
                    texto
                );

                let data;

                try {
                    data = JSON.parse(texto);
                } catch (e) {

                    console.error(
                        'La respuesta del servidor NO es JSON válido:',
                        texto
                    );

                    throw new Error(
                        'El servidor no devolvió JSON válido.'
                    );
                }

                if (!response.ok) {

                    throw new Error(
                        data.message ||
                        'Error HTTP ' + response.status
                    );
                }

                return data;
            });
        })
        .then(function(data) {

            document.getElementById('rankingCargando').style.display = 'none';

            if (!data.success) {

                document.getElementById('rankingError').textContent =
                    data.message || 'Error al generar el ranking.';

                document.getElementById('rankingError').style.display =
                    'block';

                console.error(
                    'Error del motor de recomendación:',
                    data.message
                );

                return;
            }

            rankingActual = Array.isArray(data.ranking)
                ? data.ranking
                : [];

            renderRanking(rankingActual);

            document.getElementById('rankingContenido').style.display =
                'block';
        })
        .catch(function(error) {

            console.error(
                'Error al consultar el motor de recomendación:',
                error
            );

            document.getElementById('rankingCargando').style.display =
                'none';

            document.getElementById('rankingError').innerHTML =
                '<strong>No se pudo consultar el motor de recomendación.</strong><br>' +
                '<small>' +
                (error.message || 'Error desconocido') +
                '</small>';

            document.getElementById('rankingError').style.display =
                'block';
        });
    }

    const SEMAFORO_INFO = {
        OPTIMA: { icono: '⭐', texto: 'Opción óptima', clase: 'success' },
        VIABLE: { icono: '✓', texto: 'Viable, no óptima', clase: 'info' },
        NO_OPTIMA: { icono: '⚠️', texto: 'No óptima', clase: 'warning' },
        NO_RECOMENDADA: { icono: '❌', texto: 'No recomendada', clase: 'danger' },
    };

    const ICONO_EXPLICACION = { positivo: '✓', advertencia: '⚠️', negativo: '✗', neutro: 'ℹ️' };
    const ETIQUETAS_CRITERIO = {
        rotacion_destino: 'IRV',
        demanda_historica: 'Demanda',
        cantidad_disponible_destino: 'Inventario',
        tiempo_restante_vencimiento: 'Tiempo',
        distancia: 'Distancia',
        costo_transporte: 'Costo',
        probabilidad_venta_antes_vencer: 'Prob. venta',
    };

    function renderRanking(ranking) {
        var cont = document.getElementById('listaRanking');
        if (ranking.length === 0) {
            cont.innerHTML = '<p class="text-muted">No hay otras sucursales activas para evaluar.</p>';
            return;
        }

        var html = '';
        for (var i = 0; i < ranking.length; i++) {
            var r = ranking[i];
            var info = SEMAFORO_INFO[r.semaforo] || SEMAFORO_INFO.NO_OPTIMA;
            
            var explicacionesHtml = '';
            for (var j = 0; j < r.explicaciones.length; j++) {
                var e = r.explicaciones[j];
                explicacionesHtml += '<div class="small"><span class="me-1">' + (ICONO_EXPLICACION[e.tipo] || '') + '</span>' + e.texto + '</div>';
            }

            var pesosHtml = '';
            if (r.pesos_aplicados) {
                var keys = Object.keys(r.pesos_aplicados);
                keys = keys.filter(function(k) { return Number(r.pesos_aplicados[k]) > 0; });
                keys.sort(function(a, b) { return Number(r.pesos_aplicados[b]) - Number(r.pesos_aplicados[a]); });
                keys = keys.slice(0, 4);
                for (var k = 0; k < keys.length; k++) {
                    var key = keys[k];
                    var pref = (r.preferencias_aplicadas && r.preferencias_aplicadas[key] === 'menor') ? 'menos' : 'más';
                    pesosHtml += '<span class="badge bg-light text-dark border me-1 mb-1">' + (ETIQUETAS_CRITERIO[key] || key) + ' ' + r.pesos_aplicados[key] + '% · ' + pref + '</span>';
                }
            }

            var perfilBadge = r.perfil_personalizado ? '<span class="badge bg-info text-white mb-1">Perfil propio</span>' : '<span class="badge bg-secondary mb-1">Pesos por defecto</span>';
            var borderClass = i === 0 ? 'border-info border-2' : '';

            html += `
            <div class="border rounded-4 p-3 ${borderClass}">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <span class="badge bg-${info.clase} mb-1">${info.icono} ${info.texto}</span>
                        ${perfilBadge}
                        <h6 class="mb-0">${r.sucursal_nombre}</h6>
                    </div>
                    <div class="text-end">
                        <div class="fs-4 fw-bold text-${info.clase}">${r.score}</div>
                        <small class="text-muted">puntos / 100</small>
                    </div>
                </div>
                <div class="mt-2">${pesosHtml}</div>
                <div class="mt-2">${explicacionesHtml}</div>
                <button type="button" class="btn btn-sm btn-outline-info mt-2" onclick="elegirDestino(${r.id_sucursal}, '${r.sucursal_nombre.replace(/'/g, "\\'")}')">
                    Elegir esta sucursal
                </button>
            </div>`;
        }
        cont.innerHTML = html;
    }

    // ================================================================
    // FUNCIONES DEL MODAL DE TRANSPORTE
    // ================================================================

    function elegirDestino(idSucursal, nombre) {
        seleccion.sucursal = { id: idSucursal, nombre: nombre };
        document.getElementById('modalTransporteDestino').textContent = nombre;

        var cantidadInput = document.getElementById('cantidadTransferir');
        var cantidadActual = parseInt(cantidadInput.value) || cantidadDisponible;
        cantidadInput.max = cantidadDisponible;
        cantidadInput.value = cantidadActual;

        if (modalAbierto) {
            actualizarTablaVehiculos(cantidadActual);
            return;
        }

        document.getElementById('transporteCargando').style.display = 'block';
        document.getElementById('transporteSinDatos').style.display = 'none';
        document.getElementById('transporteTablaWrap').style.display = 'none';
        document.getElementById('btnElegirVehiculo').disabled = true;
        document.getElementById('btnElegirVehiculo').innerHTML = '<span class="material-symbols-rounded align-middle" style="font-size:18px;">check_circle</span> Usar vehículo seleccionado';
        document.getElementById('btnElegirVehiculo').className = 'btn btn-info text-white px-4';
        seleccion.vehiculo = null;

        var modalEl = document.getElementById('modalTransporte');
        if (modalEl.parentNode !== document.body) {
            document.body.appendChild(modalEl);
        }

        if (modalTransporteInstance) {
            modalTransporteInstance.dispose();
        }
        modalTransporteInstance = new bootstrap.Modal(modalEl, {
            backdrop: true,
            focus: true
        });
        modalTransporteInstance.show();
        modalAbierto = true;

        cargarVehiculos(cantidadActual);
    }

    function cargarVehiculos(cantidadActual) {
        var url = BASE_URL + '/backend/inventario/obtener_logistica_disponible.php?id_sucursal_origen=' + idSucursalOrigen + '&id_sucursal_destino=' + seleccion.sucursal.id + '&cantidad=' + cantidadActual;
        
        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('transporteCargando').style.display = 'none';
                if (!data.success || !data.hay_logistica_disponible || !data.vehiculos || data.vehiculos.length === 0) {
                    document.getElementById('transporteSinDatos').style.display = 'block';
                    document.getElementById('transporteTablaWrap').style.display = 'none';
                    seleccion.distancia = data.success ? data : null;
                    return;
                }
                seleccion.distancia = data;
                vehiculosDisponibles = data.vehiculos;
                document.getElementById('transporteSinDatos').style.display = 'none';
                document.getElementById('transporteTablaWrap').style.display = 'block';
                renderizarVehiculos(data.vehiculos, cantidadActual);
            })
            .catch(function() {
                document.getElementById('transporteCargando').style.display = 'none';
                document.getElementById('transporteSinDatos').style.display = 'block';
                document.getElementById('transporteTablaWrap').style.display = 'none';
            });
    }

    function actualizarTablaVehiculos(cantidadActual) {
        var tbody = document.getElementById('tablaVehiculosBody');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Actualizando vehículos...</td></tr>';
        
        var url = BASE_URL + '/backend/inventario/obtener_logistica_disponible.php?id_sucursal_origen=' + idSucursalOrigen + '&id_sucursal_destino=' + seleccion.sucursal.id + '&cantidad=' + cantidadActual;
        
        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.hay_logistica_disponible || !data.vehiculos || data.vehiculos.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">No hay vehículos disponibles para esta cantidad</td></tr>';
                    seleccion.distancia = data.success ? data : null;
                    return;
                }
                seleccion.distancia = data;
                vehiculosDisponibles = data.vehiculos;
                renderizarVehiculos(data.vehiculos, cantidadActual);
                
                seleccion.vehiculo = null;
                document.getElementById('btnElegirVehiculo').disabled = true;
                document.getElementById('btnElegirVehiculo').innerHTML = '<span class="material-symbols-rounded align-middle" style="font-size:18px;">check_circle</span> Usar vehículo seleccionado';
                document.getElementById('btnElegirVehiculo').className = 'btn btn-info text-white px-4';
            })
            .catch(function() {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-danger">Error al cargar vehículos</td></tr>';
            });
    }

    function renderizarVehiculos(vehiculos, cantidadActual) {
        var tbody = document.getElementById('tablaVehiculosBody');
        var html = '';

        for (var i = 0; i < vehiculos.length; i++) {
            var v = vehiculos[i];
            var capacidadCarga = v.capacidad_carga || 0;
            var esSuficiente = capacidadCarga === 0 || capacidadCarga >= cantidadActual;
            var barraColor = capacidadCarga > 0 ? (esSuficiente ? 'success' : 'danger') : 'secondary';
            var barraPorcentaje = capacidadCarga > 0 ? Math.min((cantidadActual / capacidadCarga) * 100, 100) : 0;
            
            var iconoVehiculo = 'directions_car';
            if (v.tipo && (v.tipo.includes('Moto') || v.tipo.includes('moto'))) {
                iconoVehiculo = 'two_wheeler';
            } else if (v.tipo && (v.tipo.includes('Camión') || v.tipo.includes('camion'))) {
                iconoVehiculo = 'local_shipping';
            }

            var capacidadHtml = '';
            if (capacidadCarga > 0) {
                capacidadHtml = `
                    <span class="fw-bold ${esSuficiente ? 'text-success' : 'text-danger'}">${capacidadCarga} u.</span>
                    <div class="progress w-100" style="height:4px; max-width:80px;">
                        <div class="progress-bar bg-${barraColor}" style="width:${barraPorcentaje}%;"></div>
                    </div>
                    <small class="text-${esSuficiente ? 'success' : 'danger'}">
                        ${esSuficiente ? '✅ Suficiente' : '⚠️ Insuficiente para ' + cantidadActual + ' u.'}
                    </small>
                `;
            } else {
                capacidadHtml = `
                    <span class="text-muted">—</span>
                    <small class="text-muted">Sin capacidad registrada</small>
                `;
            }

            var combustibleHtml = v.combustible_estimado_gal !== null ? 
                '<span class="fw-bold">' + Number(v.combustible_estimado_gal).toFixed(2) + ' gal</span>' : 
                '<span class="text-muted">—</span>';

            var costoHtml = v.costo_combustible_estimado !== null ? 
                '<span class="fw-bold text-primary">RD$' + Number(v.costo_combustible_estimado).toFixed(2) + '</span>' : 
                '<span class="text-muted">—</span>';

            var tiempoHtml = v.tiempo_estimado_minutos !== null ? 
                '<span class="fw-bold">' + v.tiempo_estimado_minutos + ' min</span>' : 
                '<span class="text-muted">—</span>';

            var tipoCombustibleHtml = v.etiqueta_combustible ? 
                '<span class="badge bg-secondary">' + v.etiqueta_combustible + '</span>' : 
                '';

            var disabledAttr = !esSuficiente ? 'disabled' : '';
            var cursorStyle = esSuficiente ? 'pointer' : 'not-allowed';
            var opacityStyle = esSuficiente ? '1' : '0.6';

            html += `
            <tr class="fila-vehiculo" data-index="${i}" style="cursor:${cursorStyle}; opacity:${opacityStyle};">
                <td>
                    <input type="radio" 
                           name="vehiculoSel" 
                           value="${v.id_vehiculo}" 
                           data-index="${i}"
                           ${disabledAttr}>
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <span class="material-symbols-rounded me-2 text-primary" style="font-size:28px;">${iconoVehiculo}</span>
                        <div>
                            <div class="fw-bold">${v.tipo || 'Vehículo'}</div>
                            <small class="text-muted">${v.placa ? 'Placa: ' + v.placa : 'Sin placa'}</small>
                            <div class="mt-1">${tipoCombustibleHtml}</div>
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
                        ${capacidadHtml}
                    </div>
                </td>
                <td class="text-end">${combustibleHtml}</td>
                <td class="text-end">${costoHtml}</td>
                <td class="text-end">${tiempoHtml}</td>
            </tr>`;
        }

        tbody.innerHTML = html;

        // Event listeners para las filas
        var filas = tbody.querySelectorAll('.fila-vehiculo');
        for (var f = 0; f < filas.length; f++) {
            (function(fila) {
                fila.addEventListener('click', function(e) {
                    if (e.target.type === 'radio') return;
                    
                    var radio = this.querySelector('input[type="radio"]');
                    if (!radio || radio.disabled) {
                        var index = parseInt(this.dataset.index);
                        var vehiculo = vehiculosDisponibles[index];
                        if (vehiculo) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Capacidad insuficiente',
                                text: 'Este vehículo tiene capacidad para ' + (vehiculo.capacidad_carga || 0) + ' u. y necesitas transferir ' + cantidadActual + ' u.',
                                confirmButtonColor: '#0d6efd'
                            });
                        }
                        return;
                    }
                    
                    radio.checked = true;
                    var index = parseInt(this.dataset.index);
                    var vehiculo = vehiculosDisponibles[index];
                    if (vehiculo) {
                        seleccionarVehiculo(vehiculo);
                    }
                });
            })(filas[f]);
        }

        // Event listeners para los radios
        var radios = tbody.querySelectorAll('input[type="radio"]');
        for (var r = 0; r < radios.length; r++) {
            (function(radio) {
                radio.addEventListener('change', function() {
                    if (this.checked) {
                        var index = parseInt(this.dataset.index);
                        var vehiculo = vehiculosDisponibles[index];
                        if (vehiculo) {
                            seleccionarVehiculo(vehiculo);
                        }
                    }
                });
            })(radios[r]);
        }
    }

    function seleccionarVehiculo(v) {
        var cantidadInput = document.getElementById('cantidadTransferir');
        var cantidad = parseInt(cantidadInput.value) || cantidadDisponible;
        var capacidadCarga = v.capacidad_carga || 0;
        
        if (capacidadCarga > 0 && capacidadCarga < cantidad) {
            Swal.fire({
                icon: 'warning',
                title: 'Capacidad insuficiente',
                text: 'Este vehículo tiene capacidad para ' + capacidadCarga + ' u. y necesitas transferir ' + cantidad + ' u.',
                confirmButtonColor: '#0d6efd'
            });
            var checkedRadio = document.querySelector('input[name="vehiculoSel"]:checked');
            if (checkedRadio) {
                checkedRadio.checked = false;
            }
            seleccion.vehiculo = null;
            document.getElementById('btnElegirVehiculo').disabled = true;
            document.getElementById('btnElegirVehiculo').innerHTML = '<span class="material-symbols-rounded align-middle" style="font-size:18px;">check_circle</span> Usar vehículo seleccionado';
            document.getElementById('btnElegirVehiculo').className = 'btn btn-info text-white px-4';
            return;
        }
        
        seleccion.vehiculo = v;
        var btn = document.getElementById('btnElegirVehiculo');
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-rounded align-middle" style="font-size:18px;">check_circle</span> Usar ' + (v.tipo || 'vehículo') + ' (' + (v.placa || 'Sin placa') + ') - ' + (v.tiempo_estimado_minutos || '?') + ' min';
        btn.classList.remove('btn-info');
        btn.classList.add('btn-success');
    }

    function continuarSinTransporte() {
        seleccion.vehiculo = null;
        modalTransporteInstance.hide();
        irAPasoResumen();
    }

    function continuarConVehiculo() {
        modalTransporteInstance.hide();
        irAPasoResumen();
    }

    function volverAPasoTransporte() {
        if (!seleccion.sucursal) {
            volverAPaso(2);
            return;
        }
        var modalEl = document.getElementById('modalTransporte');
        if (modalEl.parentNode !== document.body) {
            document.body.appendChild(modalEl);
        }
        if (!modalTransporteInstance) {
            modalTransporteInstance = new bootstrap.Modal(modalEl, {
                backdrop: true,
                focus: true
            });
        }
        marcarPaso(3);
        modalTransporteInstance.show();
    }

    function volverAPasoConfirmacion() {
        if (seleccion.sucursal) {
            irAPasoResumen();
        }
    }

    function irAPasoResumen() {
        seleccion.cantidad = parseInt(document.getElementById('cantidadTransferir').value) || cantidadDisponible;
        marcarPaso(4);
        document.getElementById('pasoRanking').style.display = 'none';
        document.getElementById('pasoResumen').style.display = 'block';

        var r = null;
        for (var i = 0; i < rankingActual.length; i++) {
            if (rankingActual[i].id_sucursal === seleccion.sucursal.id) {
                r = rankingActual[i];
                break;
            }
        }

        var vehiculoInfo = 'Sin asignar todavía';
        var combustibleInfo = '—';
        var tiempoInfo = '—';
        
        if (seleccion.vehiculo) {
            vehiculoInfo = seleccion.vehiculo.tipo + ' (' + seleccion.vehiculo.repartidor_nombre + ')';
            if (seleccion.vehiculo.costo_combustible_estimado != null) {
                combustibleInfo = seleccion.vehiculo.combustible_estimado_gal + ' gal · RD$' + Number(seleccion.vehiculo.costo_combustible_estimado).toFixed(2) + ' (' + (seleccion.vehiculo.etiqueta_combustible || '') + ')';
            }
            if (seleccion.vehiculo.tiempo_estimado_minutos != null) {
                tiempoInfo = seleccion.vehiculo.tiempo_estimado_minutos + ' min';
            }
        }

        var filas = [
            ['Origen', '<?php echo htmlspecialchars($lote["sucursal_nombre"] ?? ""); ?>'],
            ['Destino', seleccion.sucursal.nombre],
            ['Cantidad', seleccion.cantidad + ' u.'],
            ['Distancia', seleccion.distancia && seleccion.distancia.distancia_km != null ? seleccion.distancia.distancia_km + ' km' : 'No disponible (faltan coordenadas)'],
            ['Tiempo estimado', tiempoInfo],
            ['Vehículo', vehiculoInfo],
            ['Combustible / costo estimado', combustibleInfo],
            ['Puntuación de la recomendación', r ? r.score + ' / 100 (' + ((SEMAFORO_INFO[r.semaforo] && SEMAFORO_INFO[r.semaforo].texto) || r.semaforo) + ')' : '—'],
        ];

        var resumenHtml = '';
        for (var f = 0; f < filas.length; f++) {
            resumenHtml += `
            <div class="col-md-6">
                <small class="text-muted d-block">${filas[f][0]}</small>
                <div class="fw-bold">${filas[f][1]}</div>
            </div>`;
        }
        document.getElementById('resumenViaje').innerHTML = resumenHtml;
    }

    function volverAPaso(n) {
        marcarPaso(n);
        document.getElementById('pasoResumen').style.display = 'none';
        document.getElementById('pasoRanking').style.display = 'block';
    }

    function confirmarTransferencia() {
        var btn = document.getElementById('btnConfirmarViaje');
        btn.disabled = true;

        var r = null;
        for (var i = 0; i < rankingActual.length; i++) {
            if (rankingActual[i].id_sucursal === seleccion.sucursal.id) {
                r = rankingActual[i];
                break;
            }
        }

        var payload = {
            id_lote: idLote,
            id_sucursal_origen: idSucursalOrigen,
            id_sucursal_destino: seleccion.sucursal.id,
            cantidad: seleccion.cantidad,
            motivo: 'Redistribución inteligente - proceso estratégico de vencimientos',
            id_accion: idAccion,
        };

        if (seleccion.vehiculo) {
            payload.id_vehiculo = seleccion.vehiculo.id_vehiculo;
            payload.id_repartidor = seleccion.vehiculo.id_repartidor;
            payload.datos_viaje = {
                distancia_km: (seleccion.distancia && seleccion.distancia.distancia_km) || null,
                tiempo_estimado_minutos: (seleccion.vehiculo && seleccion.vehiculo.tiempo_estimado_minutos) || (seleccion.distancia && seleccion.distancia.tiempo_estimado_minutos) || null,
                combustible_estimado_gal: seleccion.vehiculo.combustible_estimado_gal,
                costo_estimado: seleccion.vehiculo.costo_combustible_estimado,
                score: r ? r.score : null,
                semaforo: r ? r.semaforo : null,
                explicaciones: r ? r.explicaciones : null,
                tipo_combustible: seleccion.vehiculo.etiqueta_combustible || null,
            };
        }

        fetch(BASE_URL + '/backend/inventario/transferir_stock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            if (data.success) {
                Swal.fire('¡Redistribución realizada!', data.message, 'success')
                    .then(function() {
                        window.location.href = 'menuprincipal.php?mod=detalle_riesgo_lote&id_lote=' + idLote + '&id_sucursal=' + seleccion.sucursal.id;
                    });
                return;
            }
            Swal.fire('Error', data.message, 'error');
        })
        .catch(function() {
            btn.disabled = false;
            Swal.fire('Error', 'Error de conexión con el servidor', 'error');
        });
    }

    // Evento para cuando cambia la cantidad
    document.addEventListener('DOMContentLoaded', function() {
        var inputCantidad = document.getElementById('cantidadTransferir');
        if (inputCantidad) {
            inputCantidad.addEventListener('input', function() {
                var nuevaCantidad = parseInt(this.value) || cantidadDisponible;
                if (nuevaCantidad < 1) {
                    this.value = 1;
                    nuevaCantidad = 1;
                    return;
                }
                if (nuevaCantidad > cantidadDisponible) {
                    this.value = cantidadDisponible;
                    nuevaCantidad = cantidadDisponible;
                    return;
                }
                if (seleccion.sucursal && modalAbierto) {
                    actualizarTablaVehiculos(nuevaCantidad);
                }
                seleccion.vehiculo = null;
                document.getElementById('btnElegirVehiculo').disabled = true;
                document.getElementById('btnElegirVehiculo').innerHTML = '<span class="material-symbols-rounded align-middle" style="font-size:18px;">check_circle</span> Usar vehículo seleccionado';
                document.getElementById('btnElegirVehiculo').className = 'btn btn-info text-white px-4';
            });
        }
    });

    <?php if (!$error): ?>
        marcarPaso(2);
        cargarRanking();
    <?php endif; ?>
</script>