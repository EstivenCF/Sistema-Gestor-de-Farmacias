// scriptusuario.js (Adaptado a tu tabla usuarios)

const modal = document.getElementById('modal');
const form = document.getElementById('formulario-gestion');
const modalTitulo = document.getElementById('modal-titulo');
const btnGuardar = document.getElementById('btn-guardar');
const idInput = document.getElementById('id_usuario');

// Estado inicial del formulario en edición
let initialFormData = null;

// ------------------------------
// Obtener estado actual del formulario
// ------------------------------
function getFormState() {
    const data = new FormData(form);
    const pairs = [];

    for (const [key, value] of data.entries()) {
        if (key !== 'accion' && key !== 'id_usuario' && key !== 'clave' && key !== 'imagen_actual' && key !== 'imagen') {
            pairs.push(`${key}:${String(value).trim()}`);
        }
    }

    // Clave
    const claveValue = document.getElementById('clave').value;
    if (claveValue.trim() !== '') {
        pairs.push(`clave:${claveValue.trim()}`);
    }

    // Imagen
    if (document.getElementById('imagen').files.length > 0) {
        pairs.push('imagen:CHANGED');
    } else {
        pairs.push(`imagen_actual:${document.getElementById('imagen_actual').value.trim()}`);
    }

    return pairs.sort().join('|');
}

// ------------------------------
function mostrarMensaje(mensaje) {
    console.error(mensaje);
    alert(mensaje);
}

// ------------------------------
function hasChanges() {
    if (document.getElementById('accion').value !== 'editar' || !initialFormData) {
        return true;
    }
    return getFormState() !== initialFormData;
}

// ------------------------------
function manejarCierre() {
    if (document.getElementById('accion').value === 'editar' && initialFormData) {
        if (!hasChanges()) {
            cerrarModal();
            return;
        }
        if (confirm("Hay cambios sin guardar. ¿Seguro que quieres salir?")) {
            cerrarModal();
        }
    } else {
        cerrarModal();
    }
}

function cerrarModal() {
    modal.style.display = 'none';
    initialFormData = null;
}

// ------------------------------
// ABRIR MODAL – crear
// ------------------------------
function abrirModal(accion) {
    form.reset();
    document.getElementById('accion').value = accion;
    idInput.value = '';
    initialFormData = null;

    const claveInput = document.getElementById('clave');

    if (accion === 'crear') {
        modalTitulo.textContent = "Crear Nuevo Usuario";
        btnGuardar.textContent = "Crear";

        claveInput.required = true;
        claveInput.placeholder = "Clave obligatoria";

        document.getElementById('estado').value = "1";
        document.getElementById('id_rol').selectedIndex = 0;
        document.getElementById('id_sucursal').selectedIndex = 0;

        document.getElementById('imagen_actual').value = '';
        document.getElementById('imagen').value = '';
    }

    modal.style.display = 'flex';
}

// ------------------------------
// MOSTRAR USUARIO PARA EDITAR
// ------------------------------
function mostrarUsuario(usuario) {
    document.getElementById('accion').value = 'editar';
    modalTitulo.textContent = 'Editar Usuario: ' + usuario.nombre;
    btnGuardar.textContent = 'Guardar Cambios';

    idInput.value = usuario.id_usuario;
    document.getElementById('nombre').value = usuario.nombre;
    document.getElementById('user_name').value = usuario.usuario;

    const claveInput = document.getElementById('clave');
    claveInput.value = '';
    claveInput.required = false;
    claveInput.placeholder = "Dejar vacío para no cambiar";

    document.getElementById('id_rol').value = usuario.id_rol;
    document.getElementById('id_sucursal').value = usuario.id_sucursal;

    document.getElementById('estado').value = (usuario.estado == 1 ? "1" : "0");

    document.getElementById('imagen_actual').value = usuario.imagen_url || '';
    document.getElementById('imagen').value = '';

    modal.style.display = 'flex';

    setTimeout(() => {
        initialFormData = getFormState();
    }, 50);
}

// ------------------------------
// SUBMIT FORMULARIO
// ------------------------------
form.addEventListener('submit', function (event) {

    if (document.getElementById('accion').value === 'editar') {

        event.preventDefault();

        if (!hasChanges()) {
            mostrarMensaje("Para actualizar el registro debes hacer un cambio.");
            return;
        }

        form.submit();
    }
});

// ------------------------------
window.onclick = function (event) {
    if (event.target == modal) {
        manejarCierre();
    }
};

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.style.display === 'flex') {
        manejarCierre();
    }
});