<?php
require_once __DIR__ . '/../../backend/conexion.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['id_sesion'])) { header("Location: ../index.php"); exit(); }

$id_usuario = $_SESSION['id_usuario'];
$id_caja = $_GET['id_caja'] ?? 0;

// Obtener todas las cajas abiertas del usuario
$cajas_abiertas = [];
try {
    $stmt = $conexion->prepare("
        SELECT c.*, s.nombre as sucursal_nombre 
        FROM caja c
        JOIN sucursales s ON c.id_sucursal = s.id_sucursal
        WHERE c.id_usuario = :id_usuario AND c.estado = 'ABIERTA'
        ORDER BY c.fecha_apertura DESC
    ");
    $stmt->execute([':id_usuario' => $id_usuario]);
    $cajas_abiertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) { $error_bd = $e->getMessage(); }

// Si no se pasó id y hay una sola, la seleccionamos
if (!$id_caja && count($cajas_abiertas) == 1) {
    $id_caja = $cajas_abiertas[0]['id_caja'];
}

$caja = null;
if ($id_caja) {
    foreach ($cajas_abiertas as $c) {
        if ($c['id_caja'] == $id_caja) { $caja = $c; break; }
    }
    if (!$caja) {
        try {
            $stmtCaja = $conexion->prepare("SELECT c.*, s.nombre as sucursal_nombre FROM caja c JOIN sucursales s ON c.id_sucursal = s.id_sucursal WHERE c.id_caja = :id AND c.estado = 'ABIERTA'");
            $stmtCaja->execute([':id' => $id_caja]);
            $caja = $stmtCaja->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {}
    }
}

// Calcular totales si hay caja seleccionada
$total_ventas = 0; $total_ingresos = 0; $total_egresos = 0; $total_efectivo = 0;
if ($caja) {
    try {
        $stmtMov = $conexion->prepare("SELECT tipo, SUM(monto) as total FROM movimiento_caja WHERE id_caja = :id_caja GROUP BY tipo");
        $stmtMov->execute([':id_caja' => $caja['id_caja']]);
        foreach ($stmtMov->fetchAll() as $m) {
            if ($m['tipo'] == 'INGRESO') $total_ingresos = $m['total'];
            if ($m['tipo'] == 'EGRESO') $total_egresos = $m['total'];
        }
        $stmtVentas = $conexion->prepare("SELECT COALESCE(SUM(total), 0) as total_ventas FROM ventas WHERE id_usuario = :id_usuario AND fecha >= :fecha_apertura");
        $stmtVentas->execute([':id_usuario' => $id_usuario, ':fecha_apertura' => $caja['fecha_apertura']]);
        $total_ventas = $stmtVentas->fetchColumn();
        $total_efectivo = $caja['monto_inicial'] + $total_ingresos - $total_egresos;
    } catch(PDOException $e) {}
}

$base_url = '/sistema-gestor-de-farmacias';
?>
<div class="dashboard-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="text-danger"><span class="material-symbols-rounded align-middle me-2">lock</span>Cierre de Caja</h2>
        <a href="menuprincipal.php?mod=apertura_caja" class="btn btn-secondary">Volver</a>
    </div>

    <?php if (!$caja && count($cajas_abiertas) > 1): ?>
        <div class="card">
            <div class="card-header bg-danger text-white">Selecciona la caja a cerrar</div>
            <div class="card-body">
                <form method="GET" action="menuprincipal.php">
                    <input type="hidden" name="mod" value="cierre_caja">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Cajas abiertas</label>
                        <select name="id_caja" class="form-select" required>
                            <?php foreach ($cajas_abiertas as $c): ?>
                                <option value="<?php echo $c['id_caja']; ?>">
                                    <?php echo htmlspecialchars($c['numero_caja'] . ' - ' . $c['sucursal_nombre'] . ' (Apertura: ' . date('d/m/Y H:i', strtotime($c['fecha_apertura'])) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Continuar</button>
                </form>
            </div>
        </div>
    <?php elseif ($caja): ?>
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-danger text-white">Datos de la caja</div>
                    <div class="card-body">
                        <p><strong>N° Caja:</strong> <?php echo htmlspecialchars($caja['numero_caja']); ?></p>
                        <p><strong>Sucursal:</strong> <?php echo htmlspecialchars($caja['sucursal_nombre']); ?></p>
                        <p><strong>Usuario:</strong> <?php echo htmlspecialchars($_SESSION['usuario'] ?? ''); ?></p>
                        <p><strong>Apertura:</strong> <?php echo date('d/m/Y H:i:s', strtotime($caja['fecha_apertura'])); ?></p>
                        <p><strong>Monto inicial:</strong> RD$ <?php echo number_format($caja['monto_inicial'], 2); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-danger text-white">Resumen</div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr><td>Ventas totales:</td><td class="text-end">RD$ <?php echo number_format($total_ventas, 2); ?></td></tr>
                            <tr><td>Ingresos adicionales:</td><td class="text-end">RD$ <?php echo number_format($total_ingresos, 2); ?></td></tr>
                            <tr><td>Egresos:</td><td class="text-end">RD$ <?php echo number_format($total_egresos, 2); ?></td></tr>
                            <tr class="table-active"><td><strong>Efectivo esperado:</strong></td><td class="text-end"><strong>RD$ <?php echo number_format($total_efectivo, 2); ?></strong></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-danger text-white">Confirmar cierre</div>
            <div class="card-body">
                <form id="formCierreCaja">
                    <input type="hidden" id="id_caja" value="<?php echo $caja['id_caja']; ?>">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Monto final contado (RD$)</label>
                        <input type="number" step="0.01" class="form-control" id="monto_final" required value="<?php echo $total_efectivo; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Observaciones de cierre</label>
                        <textarea class="form-control" id="observaciones_cierre" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger">Cerrar caja</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">No se encontró ninguna caja abierta para cerrar.</div>
    <?php endif; ?>
</div>

<script>
const BASE_URL = '<?php echo $base_url; ?>';

document.getElementById('formCierreCaja')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const id_caja = document.getElementById('id_caja').value;
    const monto_final = parseFloat(document.getElementById('monto_final').value);
    const observaciones_cierre = document.getElementById('observaciones_cierre').value;
    if (isNaN(monto_final) || monto_final < 0) return Swal.fire('Error', 'Monto final inválido', 'error');
    Swal.fire({
        title: '¿Cerrar caja?',
        text: 'Esta acción es irreversible.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Sí, cerrar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(BASE_URL + '/backend/caja/cerrar_caja.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_caja: id_caja,
                    monto_final: monto_final,
                    observaciones_cierre: observaciones_cierre
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Éxito', 'Caja cerrada', 'success').then(() => location.href = BASE_URL + '/frontend/menuprincipal.php?mod=apertura_caja');
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                Swal.fire('Error', 'Error de conexión', 'error');
            });
        }
    });
});
</script>