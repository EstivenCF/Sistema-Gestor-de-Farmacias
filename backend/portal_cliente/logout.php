<?php
// backend/portal_cliente/logout.php
if (session_status() === PHP_SESSION_NONE) session_start();
unset($_SESSION['id_cliente_portal'], $_SESSION['nombre_cliente_portal']);
header('Content-Type: application/json');
echo json_encode(['success' => true]);
