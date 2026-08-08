<?php
// frontend/portal_cliente/login.php — NUEVO
// Login separado para clientes, no usa el login del staff.
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['id_cliente_portal'])) { header("Location: mis_entregas.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mi Farmacia | Seguimiento de pedidos</title>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
  .material-symbols-rounded{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;vertical-align:middle;}
  :root{--primary:#1F5C99;}
  *{font-family:"Poppins",sans-serif;box-sizing:border-box;}
  body{background:linear-gradient(135deg,#1F5C99,#0dcaf0);min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0;}
  .card-login{background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.2);padding:2.5rem;width:100%;max-width:400px;}
  .btn-primary{background:var(--primary);border-color:var(--primary);}
</style>
</head>
<body>
<div class="card-login">
  <div class="text-center mb-4">
    <span class="material-symbols-rounded" style="font-size:3rem;color:var(--primary);">local_shipping</span>
    <h4 class="fw-bold mt-2 mb-0">Mis Pedidos</h4>
    <p class="text-muted small">Sigue y califica tus entregas a domicilio</p>
  </div>
  <div class="mb-3">
    <label class="form-label small fw-semibold">Usuario</label>
    <input type="text" class="form-control" id="usuario" placeholder="Tu usuario">
  </div>
  <div class="mb-3">
    <label class="form-label small fw-semibold">Contraseña</label>
    <input type="password" class="form-control" id="password" placeholder="Tu contraseña">
  </div>
  <button class="btn btn-primary w-100" onclick="iniciarSesion()">Entrar</button>
  <p class="text-muted small text-center mt-3 mb-0">¿No tienes acceso todavía? Pídeselo a la farmacia donde compraste.</p>
</div>
<script>
function iniciarSesion() {
  const usuario = document.getElementById('usuario').value.trim();
  const password = document.getElementById('password').value;
  if (!usuario || !password) { Swal.fire('Falta información', 'Ingresa tu usuario y contraseña.', 'warning'); return; }

  fetch('../../backend/portal_cliente/login.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ usuario, password })
  }).then(r=>r.json()).then(data => {
    if (data.success) { window.location.href = 'mis_entregas.php'; }
    else Swal.fire('Error', data.message, 'error');
  }).catch(() => Swal.fire('Error', 'Error de conexión.', 'error'));
}
document.getElementById('password').addEventListener('keyup', e => { if (e.key === 'Enter') iniciarSesion(); });
</script>
</body>
</html>
