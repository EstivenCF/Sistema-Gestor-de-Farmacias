<?php
require_once __DIR__ . '/../../backend/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_sesion'])) {
    header("Location: ../index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'] ?? 0;

// Obtener datos del usuario y sucursales
$usuario = [];
$sucursales_usuario = [];

try {
    $stmtUser = $conexion->prepare("
        SELECT u.id_usuario, u.nombre, u.id_sucursal, r.nombre as rol
        FROM usuarios u
        JOIN roles r ON u.id_rol = r.id_rol
        WHERE u.id_usuario = :id
    ");
    $stmtUser->execute([':id' => $id_usuario]);
    $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario['rol'] === 'Administrador') {
        $stmtSuc = $conexion->query("SELECT id_sucursal, nombre FROM sucursales WHERE estado = TRUE ORDER BY nombre");
        $sucursales_usuario = $stmtSuc->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sucursales_usuario = [['id_sucursal' => $usuario['id_sucursal'], 'nombre' => 'Mi sucursal']];
    }
} catch(PDOException $e) {}

// Obtener cajas abiertas del usuario
$cajas_abiertas = [];
try {
    $stmtCajas = $conexion->prepare("
        SELECT c.*, s.nombre as sucursal_nombre
        FROM caja c
        JOIN sucursales s ON c.id_sucursal = s.id_sucursal
        WHERE c.id_usuario = :id_usuario AND c.estado = 'ABIERTA'
        ORDER BY c.fecha_apertura DESC
    ");
    $stmtCajas->execute([':id_usuario' => $id_usuario]);
    $cajas_abiertas = $stmtCajas->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

$base_url = '/sistema-gestor-de-farmacias';
?>
<div class="dashboard-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0 text-success">
                <span class="material-symbols-rounded align-middle me-2">point_of_sale</span>
                Apertura de Caja
            </h2>
            <p class="text-muted mb-0">Abre una nueva caja o cierra una existente</p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-success text-white">Cajas abiertas actualmente</div>
                <div class="card-body">
                    <?php if (empty($cajas_abiertas)): ?>
                        <div class="alert alert-info">No tienes ninguna caja abierta.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th>N° Caja</th><th>Sucursal</th><th>Apertura</th><th>Monto inicial</th><th>Acción</th></tr></thead>
                                <tbody>
                                <?php foreach ($cajas_abiertas as $c): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($c['numero_caja']); ?></td>
                                        <td><?php echo htmlspecialchars($c['sucursal_nombre']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($c['fecha_apertura'])); ?></td>
                                        <td>RD$ <?php echo number_format($c['monto_inicial'], 2); ?></td>
                                        <td><a href="menuprincipal.php?mod=cierre_caja&id_caja=<?php echo $c['id_caja']; ?>" class="btn btn-danger btn-sm">Cerrar</a></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-success text-white">Abrir nueva caja</div>
                <div class="card-body">
                    <form id="formAperturaCaja">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Sucursal *</label>
                            <select class="form-select" id="sucursal_id" required>
                                <option value="">Seleccionar</option>
                                <?php foreach ($sucursales_usuario as $suc): ?>
                                    <option value="<?php echo $suc['id_sucursal']; ?>"><?php echo htmlspecialchars($suc['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Número / Nombre de la caja *</label>
                            <input type="text" class="form-control" id="numero_caja" required placeholder="Ej: Caja 1, Principal, Caja A">
                            <small class="text-muted">Identificador único dentro de la sucursal.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Monto inicial (RD$)</label>
                            <input type="number" step="0.01" class="form-control" id="monto_inicial" required placeholder="0.00">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Observaciones</label>
                            <textarea class="form-control" id="observaciones" rows="2"></textarea>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-success">Abrir caja</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Definir BASE_URL para las peticiones fetch
const BASE_URL = '<?php echo $base_url; ?>';

document.getElementById('formAperturaCaja').addEventListener('submit', function(e) {
    e.preventDefault();
    const sucursal_id = document.getElementById('sucursal_id').value;
    const numero_caja = document.getElementById('numero_caja').value.trim();
    const monto_inicial = parseFloat(document.getElementById('monto_inicial').value);
    const observaciones = document.getElementById('observaciones').value;
    if (!sucursal_id || !numero_caja) return Swal.fire('Error', 'Sucursal y número de caja son obligatorios', 'error');
    if (isNaN(monto_inicial) || monto_inicial < 0) return Swal.fire('Error', 'Monto inicial inválido', 'error');
    
    Swal.fire({
        title: 'Confirmar apertura',
        text: `¿Abrir caja "${numero_caja}" con RD$ ${monto_inicial.toFixed(2)}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        confirmButtonText: 'Sí, abrir'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(BASE_URL + '/backend/caja/agregar_apertura_caja.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_sucursal: sucursal_id,
                    id_usuario: <?php echo $id_usuario; ?>,
                    numero_caja: numero_caja,
                    monto_inicial: monto_inicial,
                    observaciones: observaciones
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Éxito', 'Caja abierta correctamente', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message || 'No se pudo abrir la caja', 'error');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                Swal.fire('Error', 'Error de conexión con el servidor', 'error');
            });
        }
    });
});
</script>