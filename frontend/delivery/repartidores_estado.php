<?php
// frontend/delivery/repartidores_estado.php — Estado de repartidores en tiempo real (Cajero/Admin)
require_once __DIR__ . '/../../backend/conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['id_sesion'])) { header("Location: ../index.php"); exit(); }
if (!in_array($_SESSION['rol'], ['Cajero','Administrador'])) { header("Location: ../menuprincipal.php"); exit(); }
$usuario_nombre = $_SESSION['nombre'] ?? $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Envíos | Estado de Repartidores — PharmaSystem</title>
    <link rel="icon" type="image/x-icon" href="../../assets/img/Icon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root{--primary:#1F5C99;--bg:#F0F2F5;}
        *{font-family:"Poppins",sans-serif;box-sizing:border-box;}
        body{background:var(--bg);margin:0;}
        .delivery-nav{background:var(--primary);padding:0 1.25rem;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:1000;box-shadow:0 2px 8px rgba(0,0,0,.2);}
        .nav-brand{color:#fff;font-weight:700;font-size:1rem;text-decoration:none;display:flex;align-items:center;gap:.5rem;}
        .nav-user{color:#ADE8F4;font-size:.8rem;}
        .btn-back{background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:8px;padding:.3rem .7rem;font-size:.78rem;cursor:pointer;text-decoration:none;}
        .breadcrumb-bar{background:#fff;padding:.5rem 1.25rem;border-bottom:1px solid #dee2e6;font-size:.8rem;color:#555;}
        .breadcrumb-bar a{color:var(--primary);text-decoration:none;}
        .stat-card{background:#fff;border-radius:12px;padding:1rem 1.25rem;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.06);}
        .stat-val{font-size:1.8rem;font-weight:700;}
        .stat-lbl{font-size:.75rem;color:#777;}
        table thead th{background:var(--primary);color:#fff;font-size:.8rem;font-weight:600;border:none;padding:.65rem 1rem;}
        table tbody td{font-size:.82rem;vertical-align:middle;padding:.6rem 1rem;}
        .avatar{width:32px;height:32px;border-radius:50%;background:var(--primary);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;margin-right:.5rem;}
        .badge-status{font-size:.7rem;padding:.35em .7em;border-radius:20px;font-weight:600;}
        .bg-disponible{background:#D4EDDA;color:#2E8B57;}
        .bg-en_camino{background:#FFF3CD;color:#C77B00;}
        .bg-en_descanso{background:#F5F5F5;color:#888;}
        .bg-fuera_turno{background:#FDEAEA;color:#DC3545;}
        .dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:5px;}
        .card-d{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);}
    </style>
</head>
<body>
<nav class="delivery-nav">
    <a class="nav-brand" href="repartidores_estado.php"><i class="fas fa-motorcycle"></i> SGF | Estado de Repartidores</a>
    <div class="nav-user d-flex align-items-center gap-3">
        <span><i class="fas fa-user-circle me-1"></i><?=htmlspecialchars($usuario_nombre)?> [<?=$_SESSION['rol']?>]</span>
        <a href="../menuprincipal.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i>Volver</a>
    </div>
</nav>
<div class="breadcrumb-bar">
    <i class="fas fa-motorcycle me-1" style="color:var(--primary)"></i>
    <a href="../menuprincipal.php">PharmaSystem</a> › <strong>Envíos &gt; Estado de Repartidores</strong>
</div>

<div class="container-fluid p-3">
    <div class="row g-3 mb-3" id="statsRow">
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-val text-primary" id="sTotal">—</div><div class="stat-lbl">Total repartidores</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-val" style="color:#2E8B57" id="sDisp">—</div><div class="stat-lbl">Disponibles</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-val" style="color:#C77B00" id="sCamino">—</div><div class="stat-lbl">En camino</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-val text-secondary" id="sDescanso">—</div><div class="stat-lbl">En descanso</div></div></div>
    </div>

    <div class="card-d p-3">
        <div class="table-responsive" id="tablaContainer">
            <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
        </div>
    </div>
    <div class="text-end mt-2"><small class="text-muted"><i class="fas fa-sync-alt me-1"></i>Actualización automática cada 30 segundos</small></div>
</div>

<script>
const STATUS_LABEL = {DISPONIBLE:'Disponible', EN_CAMINO:'En camino', EN_DESCANSO:'En descanso', FUERA_TURNO:'Fuera de turno'};

function cargar() {
    fetch('../../backend/delivery/get_repartidores_estado.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) { document.getElementById('tablaContainer').innerHTML = '<div class="alert alert-danger">'+data.message+'</div>'; return; }
            document.getElementById('sTotal').textContent    = data.stats.total;
            document.getElementById('sDisp').textContent     = data.stats.disponibles;
            document.getElementById('sCamino').textContent   = data.stats.en_camino;
            document.getElementById('sDescanso').textContent = data.stats.en_descanso;

            const rows = data.repartidores.map(r => `
                <tr>
                    <td><span class="avatar">${r.nombre.charAt(0)}</span><strong>${r.nombre}</strong></td>
                    <td>${r.vehiculo_tipo || '—'} ${r.vehiculo_placa ? '('+r.vehiculo_placa+')' : ''}</td>
                    <td class="text-center"><span class="badge-status bg-${r.estado_actual.toLowerCase()}"><span class="dot" style="background:currentColor"></span>${STATUS_LABEL[r.estado_actual]}</span></td>
                    <td class="text-center">${r.entregas_completadas || 0} / ${r.total_entregas || 0}</td>
                    <td>${r.entrega_actual || '—'}</td>
                    <td class="text-center">
                        ${r.estado_actual === 'DISPONIBLE'
                            ? '<a href="despacho.php" class="btn btn-sm" style="background:#2E8B57;color:#fff;">Asignar</a>'
                            : '<a href="#" class="btn btn-sm btn-secondary">Ver ruta</a>'}
                    </td>
                </tr>`).join('');

            document.getElementById('tablaContainer').innerHTML = `
                <table class="table table-hover mb-0">
                    <thead><tr><th>Repartidor</th><th>Vehículo</th><th class="text-center">Estado</th><th class="text-center">Entregas hoy</th><th>Entrega actual</th><th class="text-center">Acción</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>`;
        });
}
cargar();
setInterval(cargar, 30000);
</script>
</body>
</html>
