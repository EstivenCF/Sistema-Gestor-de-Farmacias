<?php
// Verificar que el usuario sea administrador
if ($_SESSION['rol'] !== 'Administrador') {
    echo "<div class='alert alert-danger'>Acceso denegado. Solo administradores.</div>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Desbloqueo de Usuarios - PharmaSystem</title>
    <style>
        /* Estilos específicos para el módulo de desbloqueo */
        
        /* Eliminar backdrop duplicado o conflictivo */
        .modal-backdrop {
            display: none !important;
        }
        
        /* Usar SweetAlert2 para confirmaciones en lugar de modal de Bootstrap */
        .modal {
            --bs-modal-margin: 1.75rem;
        }
        
        /* Tabla responsiva */
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table th {
            font-weight: 600;
            border-bottom-width: 1px;
        }
        
        /* Badges mejorados */
        .badge {
            font-weight: 500;
            padding: 6px 12px;
        }
        
        /* Cards */
        .card {
            border-radius: 16px;
            overflow: hidden;
            border: none;
        }
        
        .card-header {
            border-bottom: none;
            font-weight: 600;
        }
        
        /* Botones */
        .btn {
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        .btn:active {
            transform: translateY(1px);
        }
        
        /* Input group */
        .input-group .form-control {
            border-radius: 12px 0 0 12px;
            border: 1px solid #dee2e6;
        }
        
        .input-group .btn {
            border-radius: 0 12px 12px 0;
        }
        
        /* Alertas */
        .alert {
            border-radius: 12px;
            border: none;
        }

        /* Animación de carga */
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-unlock-alt me-2"></i> Desbloqueo de Usuarios
                    </h4>
                    <small class="text-white-50">Administre los usuarios bloqueados del sistema</small>
                </div>
                <div class="card-body">
                    
                    <!-- Formulario para desbloquear usuario específico -->
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="card border-warning h-100">
                                <div class="card-header bg-warning text-dark">
                                    <i class="fas fa-user-lock me-2"></i> Desbloquear usuario específico
                                </div>
                                <div class="card-body">
                                    <div class="input-group">
                                        <input type="text" id="usuarioDesbloquear" class="form-control" placeholder="Nombre de usuario a desbloquear">
                                        <button class="btn btn-success" onclick="desbloquearUsuario()">
                                            <i class="fas fa-unlock me-1"></i> Desbloquear
                                        </button>
                                    </div>
                                    <small class="text-muted mt-2 d-block">
                                        <i class="fas fa-info-circle"></i> Ingrese el nombre exacto del usuario que está bloqueado
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-info h-100">
                                <div class="card-header bg-info text-white">
                                    <i class="fas fa-list me-2"></i> Acciones rápidas
                                </div>
                                <div class="card-body">
                                    <button class="btn btn-outline-primary w-100 mb-2" onclick="listarUsuariosBloqueados()">
                                        <i class="fas fa-search me-2"></i> Ver usuarios bloqueados
                                    </button>
                                    <button class="btn btn-outline-danger w-100" onclick="desbloquearTodos()">
                                        <i class="fas fa-trash-alt me-2"></i> Desbloquear todos los usuarios
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Resultado del desbloqueo -->
                    <div id="resultadoDesbloqueo" class="mb-4" style="display: none;"></div>
                    
                    <!-- Lista de usuarios bloqueados -->
                    <div id="listaBloqueados" class="mt-4"></div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Variables globales
let usuarioAConfirmar = '';

// Desbloquear usuario desde el input
function desbloquearUsuario() {
    const usuario = document.getElementById('usuarioDesbloquear').value.trim();
    if (!usuario) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Ingrese un nombre de usuario',
            confirmButtonColor: '#28a745'
        });
        return;
    }
    
    usuarioAConfirmar = usuario;
    
    Swal.fire({
        title: 'Confirmar desbloqueo',
        html: `<i class="fas fa-user me-2 text-warning"></i> 
               ¿Está seguro de que desea desbloquear al usuario <strong class="text-success">${escapeHtml(usuario)}</strong>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-unlock me-1"></i> Sí, desbloquear',
        cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            ejecutarDesbloqueo(usuarioAConfirmar);
        }
    });
}

// Desbloquear desde la tabla
function desbloquearEspecifico(usuario) {
    usuarioAConfirmar = usuario;
    
    Swal.fire({
        title: 'Confirmar desbloqueo',
        html: `<i class="fas fa-user me-2 text-warning"></i> 
               ¿Está seguro de que desea desbloquear al usuario <strong class="text-success">${escapeHtml(usuario)}</strong>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-unlock me-1"></i> Sí, desbloquear',
        cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            ejecutarDesbloqueo(usuarioAConfirmar);
        }
    });
}

// Ejecutar el desbloqueo
function ejecutarDesbloqueo(usuario) {
    Swal.fire({
        title: 'Procesando...',
        text: `Desbloqueando usuario ${usuario}`,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('../backend/desbloquear_usuario.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `usuario=${encodeURIComponent(usuario)}`
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Desbloqueado',
                text: data.message,
                confirmButtonColor: '#28a745',
                timer: 3000,
                timerProgressBar: true
            });
            document.getElementById('usuarioDesbloquear').value = '';
            document.getElementById('resultadoDesbloqueo').style.display = 'block';
            document.getElementById('resultadoDesbloqueo').innerHTML = `
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> ${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            setTimeout(() => {
                document.getElementById('resultadoDesbloqueo').style.display = 'none';
            }, 5000);
            
            // Recargar lista si estaba visible
            const listaDiv = document.getElementById('listaBloqueados');
            if (listaDiv.innerHTML !== '') {
                listarUsuariosBloqueados();
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message,
                confirmButtonColor: '#28a745'
            });
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error de conexión con el servidor',
            confirmButtonColor: '#28a745'
        });
    });
}

// Desbloquear todos los usuarios
function desbloquearTodos() {
    Swal.fire({
        title: '¿Desbloquear todos los usuarios?',
        text: 'Esta acción desbloqueará a TODOS los usuarios que estén actualmente bloqueados.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> Sí, desbloquear todos',
        cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Procesando...',
                text: 'Desbloqueando todos los usuarios',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('../backend/desbloquear_todos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Completado',
                        text: data.message,
                        confirmButtonColor: '#28a745',
                        timer: 3000,
                        timerProgressBar: true
                    });
                    document.getElementById('listaBloqueados').innerHTML = '';
                    document.getElementById('resultadoDesbloqueo').style.display = 'block';
                    document.getElementById('resultadoDesbloqueo').innerHTML = `
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> ${data.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `;
                    setTimeout(() => {
                        document.getElementById('resultadoDesbloqueo').style.display = 'none';
                    }, 5000);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message,
                        confirmButtonColor: '#28a745'
                    });
                }
            })
            .catch(error => {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error de conexión con el servidor',
                    confirmButtonColor: '#28a745'
                });
            });
        }
    });
}

// Listar usuarios bloqueados
function listarUsuariosBloqueados() {
    const listaDiv = document.getElementById('listaBloqueados');
    listaDiv.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-success" role="status"></div>
            <br>
            <span class="text-muted mt-2">Cargando usuarios bloqueados...</span>
        </div>
    `;
    
    fetch('../backend/listar_bloqueados.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.usuarios.length > 0) {
                let html = `
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-danger text-white">
                            <i class="fas fa-ban me-2"></i> Usuarios Bloqueados <span class="badge bg-light text-danger ms-2">${data.usuarios.length}</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-user me-1"></i> Usuario</th>
                                            <th><i class="fas fa-chart-simple me-1"></i> Intentos</th>
                                            <th><i class="fas fa-clock me-1"></i> Estado</th>
                                            <th><i class="fas fa-hourglass-half me-1"></i> Tiempo restante</th>
                                            <th><i class="fas fa-cog me-1"></i> Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;
                
                data.usuarios.forEach(u => {
                    let estadoBadge = '';
                    let tiempoTexto = '';
                    
                    if (u.bloqueo_permanente) {
                        estadoBadge = '<span class="badge bg-danger px-3 py-2">🔒 BLOQUEADO PERMANENTE</span>';
                        tiempoTexto = '<span class="text-danger fw-bold">Permanente</span>';
                    } else if (u.tiempo_restante > 0) {
                        let minutos = Math.floor(u.tiempo_restante / 60);
                        let segundos = u.tiempo_restante % 60;
                        if (minutos > 0) {
                            tiempoTexto = `<span class="text-warning fw-bold">${minutos} minuto${minutos !== 1 ? 's' : ''}</span>`;
                        } else {
                            tiempoTexto = `<span class="text-warning fw-bold">${segundos} segundo${segundos !== 1 ? 's' : ''}</span>`;
                        }
                        estadoBadge = '<span class="badge bg-warning px-3 py-2">⚠️ BLOQUEADO TEMPORAL</span>';
                    } else {
                        tiempoTexto = '<span class="text-secondary">Esperando recarga...</span>';
                        estadoBadge = '<span class="badge bg-secondary px-3 py-2">⏳ EXPIRANDO</span>';
                    }
                    
                    html += `
                        <tr>
                            <td><strong>${escapeHtml(u.usuario)}</strong></td>
                            <td><span class="fw-bold">${u.intentos}</span> / 10</td>
                            <td>${estadoBadge}</td>
                            <td>${tiempoTexto}</td>
                            <td>
                                <button class="btn btn-sm btn-success" onclick="desbloquearEspecifico('${escapeHtml(u.usuario)}')">
                                    <i class="fas fa-unlock me-1"></i> Desbloquear
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `;
                listaDiv.innerHTML = html;
            } else {
                listaDiv.innerHTML = `
                    <div class="alert alert-success mt-4">
                        <i class="fas fa-check-circle me-2"></i> 
                        <strong>No hay usuarios bloqueados</strong><br>
                        <small>Todos los usuarios pueden acceder al sistema normalmente.</small>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            listaDiv.innerHTML = `
                <div class="alert alert-danger mt-4">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error al cargar la lista de usuarios bloqueados. Por favor, intente nuevamente.
                </div>
            `;
        });
}

// Función para escapar HTML (seguridad)
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Cargar lista automáticamente al entrar a la página
document.addEventListener('DOMContentLoaded', function() {
    listarUsuariosBloqueados();
});
</script>

</body>
</html>