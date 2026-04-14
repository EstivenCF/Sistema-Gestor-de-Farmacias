<?php
require_once __DIR__ . '/../../backend/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_sesion'])) {
    header("Location: ../index.php");
    exit();
}

// Obtener compras pendientes (estado PENDIENTE o PARCIAL)
$compras_pendientes = [];
try {
    $sql = "SELECT c.id_compra, c.numero_documento, c.fecha, c.total, 
                   p.nombre as proveedor_nombre, s.nombre as sucursal_nombre,
                   ec.nombre as estado_nombre,
                   (SELECT COUNT(*) FROM detalle_compra WHERE id_compra = c.id_compra) as total_productos
            FROM compras c
            JOIN proveedores p ON c.id_proveedor = p.id_proveedor
            JOIN sucursales s ON c.id_sucursal = s.id_sucursal
            JOIN estado_compra ec ON c.id_estado = ec.id_estado
            WHERE ec.nombre IN ('PENDIENTE', 'PARCIAL')
            ORDER BY c.fecha ASC";
    $stmt = $conexion->prepare($sql);
    $stmt->execute();
    $compras_pendientes = $stmt->fetchAll();
} catch(PDOException $e) {
    $compras_pendientes = [];
}

$base_url = '/sistema-gestor-de-farmacias';
?>

<style>
    .dashboard-container {
        padding: 20px;
        animation: fadeSlideIn 0.5s ease-out;
    }
    @keyframes fadeSlideIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .card-total {
        background: linear-gradient(135deg, #17a2b8 0%, #0f6b7a 100%);
        color: white;
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .card-total h3 { font-size: 1.8rem; margin: 0; font-weight: 700; }
    .badge-pendiente { background-color: #ffc107; color: #000; padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; }
    .badge-parcial { background-color: #fd7e14; color: #fff; }
    .table-hover tbody tr:hover { background-color: rgba(23,162,184,0.05); cursor: pointer; }
    .detalle-item { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
    .cantidad-recibida { width: 90px; text-align: center; }
    .btn-recibir { background-color: #28a745; color: white; border: none; border-radius: 8px; padding: 6px 15px; }
    .btn-recibir:hover { background-color: #218838; }
    .btn-cancelar { background-color: #f1f3f5; color: #495057; border: 1.5px solid #dee2e6; border-radius: 10px; padding: 8px 20px; }
    .form-control, .form-select { border: 1.5px solid #dee2e6 !important; border-radius: 10px; }
    .form-control:focus, .form-select:focus { border-color: #17a2b8 !important; box-shadow: 0 0 0 0.25rem rgba(23,162,184,0.1) !important; }
    
    /* 🔥 ELIMINA EL BACKDROP DEL MODAL 🔥 */
    .modal-backdrop {
        display: none !important;
    }
</style>

<div class="dashboard-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 text-info">
                <span class="material-symbols-rounded align-middle me-2">inbox</span>
                Recepción de Mercancía
            </h2>
            <p class="text-muted mb-0">Confirme la llegada de productos y actualice el inventario</p>
        </div>
    </div>

    <!-- Tarjeta de resumen -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card-total text-center">
                <small>COMPRAS PENDIENTES</small>
                <h3 id="totalPendientes"><?php echo count($compras_pendientes); ?></h3>
            </div>
        </div>
    </div>

    <!-- Listado de compras pendientes -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Nº Documento</th>
                            <th>Fecha</th>
                            <th>Proveedor</th>
                            <th>Sucursal</th>
                            <th>Productos</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($compras_pendientes)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">No hay compras pendientes de recepción</td></tr>
                        <?php else: foreach ($compras_pendientes as $compra): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($compra['numero_documento']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($compra['fecha'])); ?></td>
                                <td><?php echo htmlspecialchars($compra['proveedor_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($compra['sucursal_nombre']); ?></td>
                                <td><?php echo $compra['total_productos']; ?></td>
                                <td class="text-success fw-bold">RD$ <?php echo number_format($compra['total'], 2); ?></td>
                                <td><span class="badge <?php echo $compra['estado_nombre'] == 'PENDIENTE' ? 'badge-pendiente' : 'badge-parcial'; ?>"><?php echo $compra['estado_nombre']; ?></span></td>
                                <td class="text-center">
                                    <button class="btn btn-info btn-sm text-white" onclick="abrirRecepcion(<?php echo $compra['id_compra']; ?>)">
                                        <span class="material-symbols-rounded">inventory_2</span> Recibir
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA RECEPCIÓN DE PRODUCTOS (SIN BACKDROP) -->
<div class="modal fade" id="modalRecepcion" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Recepción de Compra</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="modalRecepcionBody">
                <div class="text-center py-5"><div class="spinner-border text-info" role="status"></div><p>Cargando...</p></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnConfirmarRecepcion" onclick="confirmarRecepcion()">
                    <span class="material-symbols-rounded align-middle me-1">check_circle</span> Confirmar Recepción
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const BASE_URL = '<?php echo $base_url; ?>';
let datosCompra = null;

function abrirRecepcion(idCompra) {
    const modalBody = document.getElementById('modalRecepcionBody');
    modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-info" role="status"></div><p>Cargando detalles...</p></div>';
    const modal = new bootstrap.Modal(document.getElementById('modalRecepcion'), { backdrop: false });
    modal.show();
    
    // Eliminar cualquier backdrop residual (por si acaso)
    setTimeout(() => {
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) backdrop.remove();
    }, 100);
    
    fetch(`${BASE_URL}/backend/compras/get_detalle_compra.php?id_compra=${idCompra}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                datosCompra = data;
                renderizarFormularioRecepcion(data);
            } else {
                modalBody.innerHTML = `<div class="text-center text-danger">Error: ${data.message || 'No se pudo cargar la compra'}</div>`;
            }
        })
        .catch(err => {
            console.error(err);
            modalBody.innerHTML = '<div class="text-center text-danger">Error de conexión con el servidor</div>';
        });
}

function renderizarFormularioRecepcion(data) {
    let html = `
        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="bg-light p-2 rounded"><small class="text-muted">Nº Documento</small><br><strong>${escapeHtml(data.numero_documento)}</strong></div></div>
            <div class="col-md-3"><div class="bg-light p-2 rounded"><small class="text-muted">Proveedor</small><br><strong>${escapeHtml(data.proveedor_nombre)}</strong></div></div>
            <div class="col-md-3"><div class="bg-light p-2 rounded"><small class="text-muted">Sucursal</small><br><strong>${escapeHtml(data.sucursal_nombre)}</strong></div></div>
            <div class="col-md-3"><div class="bg-light p-2 rounded"><small class="text-muted">Fecha</small><br><strong>${data.fecha}</strong></div></div>
        </div>
        <h6 class="fw-bold">Productos a recibir</h6>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="bg-light">
                    <tr>
                        <th>Medicamento</th>
                        <th>Cantidad (pedido / recibido)</th>
                        <th>Cantidad a recibir ahora</th>
                        <th>N° Lote</th>
                        <th>Fecha Vencimiento</th>
                        <th>Costo Unitario</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.productos.forEach((prod, idx) => {
        const loteSolicitado = prod.numero_lote_solicitado || '';
        const vencSolicitado = prod.fecha_vencimiento_solicitada || '';
        const recibidoHastaAhora = prod.cantidad_recibida || 0;
        html += `
            <tr>
                <td><strong>${escapeHtml(prod.producto_nombre)}</strong><br><small class="text-muted">ID: ${prod.id_medicamento}</small></td>
                <td class="text-center">${prod.cantidad} / ${recibidoHastaAhora}</td>
                <td><input type="number" class="form-control cantidad-recibida" data-idx="${idx}" value="${prod.cantidad_pendiente}" min="0" max="${prod.cantidad_pendiente}" step="1" onchange="actualizarCantidadRecibida(${idx}, this.value)"></td>
                <td><input type="text" class="form-control" id="lote_${idx}" placeholder="Número de lote" value="${escapeHtml(loteSolicitado)}"></td>
                <td><input type="date" class="form-control" id="venc_${idx}" value="${vencSolicitado}"></td>
                <td><input type="number" step="0.01" class="form-control" id="costo_${idx}" value="${prod.precio_unitario}" readonly style="background:#e9ecef;"></td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div class="alert alert-info mt-3">
            <i class="fas fa-info-circle"></i> Los datos de lote y vencimiento se precargan desde la orden de compra. Puede modificarlos si es necesario.
        </div>
    `;
    document.getElementById('modalRecepcionBody').innerHTML = html;
}

function actualizarCantidadRecibida(idx, value) {
    let cant = parseInt(value);
    if (isNaN(cant)) cant = 0;
    const max = datosCompra.productos[idx].cantidad_pendiente;
    if (cant > max) cant = max;
    if (cant < 0) cant = 0;
    document.querySelector(`.cantidad-recibida[data-idx="${idx}"]`).value = cant;
}

function confirmarRecepcion() {
    if (!datosCompra) return;
    
    const recibidos = [];
    let todoRecibido = true;
    let parcial = false;
    
    for (let i = 0; i < datosCompra.productos.length; i++) {
        const prod = datosCompra.productos[i];
        const cantidadRecibida = parseInt(document.querySelector(`.cantidad-recibida[data-idx="${i}"]`).value) || 0;
        const numeroLote = document.getElementById(`lote_${i}`).value.trim();
        const fechaVencimiento = document.getElementById(`venc_${i}`).value;
        
        if (cantidadRecibida > 0) {
            if (!numeroLote || !fechaVencimiento) {
                Swal.fire('Error', `Para el producto "${prod.producto_nombre}" debe indicar número de lote y fecha de vencimiento`, 'error');
                return;
            }
            if (cantidadRecibida < prod.cantidad_pendiente) parcial = true;
            if (cantidadRecibida !== prod.cantidad_pendiente) todoRecibido = false;
            
            recibidos.push({
                id_detalle: prod.id_detalle,
                id_medicamento: prod.id_medicamento,
                cantidad_recibida: cantidadRecibida,
                numero_lote: numeroLote,
                fecha_vencimiento: fechaVencimiento,
                costo_unitario: prod.precio_unitario
            });
        } else {
            if (prod.cantidad_pendiente > 0) todoRecibido = false;
        }
    }
    
    if (recibidos.length === 0) {
        Swal.fire('Error', 'Debe recibir al menos un producto', 'error');
        return;
    }
    
    const nuevoEstado = todoRecibido ? 'COMPLETADA' : (parcial ? 'PARCIAL' : 'PENDIENTE');
    
    const dataEnvio = {
        id_compra: datosCompra.id_compra,
        productos: recibidos,
        nuevo_estado: nuevoEstado,
        observaciones: `Recepción ${new Date().toLocaleString()}`
    };
    
    Swal.fire({ title: 'Procesando recepción...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch(BASE_URL + '/backend/compras/procesar_recepcion.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dataEnvio)
    })
    .then(r => r.json())
    .then(res => {
        Swal.close();
        if (res.success) {
            Swal.fire('Éxito', 'Recepción procesada correctamente', 'success')
                .then(() => location.reload());
        } else {
            Swal.fire('Error', res.message || 'Error al procesar la recepción', 'error');
        }
    })
    .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        if (m === '"') return '&quot;';
        if (m === "'") return '&#39;';
        return m;
    });
}
</script>