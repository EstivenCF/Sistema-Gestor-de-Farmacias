// =========================
// SIDEBAR
// =========================
const toggleBtn = document.getElementById('toggleSidebar');
const sidebar = document.querySelector('.sidebar');
const mainContent = document.querySelector('.main-content');

toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('sidebar-collapsed');

    const isCollapsed = sidebar.classList.contains('collapsed');
    localStorage.setItem('sidebarStatus', isCollapsed ? 'collapsed' : 'expanded');
});

window.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('sidebarStatus') === 'collapsed') {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('sidebar-collapsed');
    }
});

// =========================
// BLOQUEAR SUBMENUS SI COLAPSADO
// =========================
document.querySelectorAll('.has-submenu > a, .dropdown-toggle').forEach(link => {
    link.addEventListener('click', function (e) {
        if (sidebar.classList.contains('collapsed')) {
            e.preventDefault();
            e.stopPropagation();
        }
    });
});

// =========================
// EFECTO RIPPLE
// =========================
document.querySelectorAll('.nav-link').forEach(button => {
    button.addEventListener('click', function (e) {

        if (sidebar.classList.contains('collapsed') && this.parentElement.classList.contains('has-submenu')) {
            return;
        }

        const rect = this.getBoundingClientRect();
        let ripple = document.createElement('span');
        ripple.classList.add('ripple');

        ripple.style.left = `${e.clientX - rect.left}px`;
        ripple.style.top = `${e.clientY - rect.top}px`;

        this.appendChild(ripple);

        setTimeout(() => ripple.remove(), 600);
    });
});

// =========================
// FUNCION GLOBAL
// =========================
function cerrarDropdowns() {
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(btn => {
        const instance = bootstrap.Dropdown.getInstance(btn);
        if (instance) instance.hide();
    });
}

// =========================
// BUSCADOR
// =========================
document.addEventListener('DOMContentLoaded', () => {

    const searchToggle = document.getElementById('searchToggle');
    const searchWrapper = document.getElementById('searchWrapper');
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById("searchResults");

    let selectedIndex = -1;
    let items = [];

    if (!searchToggle || !searchWrapper || !searchInput) return;

    // =========================
    // ABRIR / CERRAR
    // =========================
    searchToggle.addEventListener('click', (e) => {
        e.stopPropagation();

        cerrarDropdowns();

        const isActive = searchWrapper.classList.contains('active');

        if (!isActive) {
            // 🔓 ABRIR
            searchWrapper.classList.add('active');
            setTimeout(() => searchInput.focus(), 100);
        } else {

            // 🔴 CERRAR COMPLETO (INPUT + RESULTADOS)
            searchWrapper.classList.remove('active');

            if (searchResults) {
                searchResults.style.display = "none";
                searchResults.innerHTML = "";
            }

            selectedIndex = -1;
            items = [];
        }
    });

    // =========================
    // ENTER BUSCAR
    // =========================
    searchInput.addEventListener('keydown', (e) => {

        if (e.key === 'Enter') {
            e.preventDefault();

            if (selectedIndex >= 0 && items[selectedIndex]) {
                const texto = items[selectedIndex].innerText.trim();
                irBusqueda(texto);
                return;
            }

            const valor = searchInput.value.trim();

            if (valor !== '') {
                guardarHistorial(valor);
                window.location.href = `menuprincipal.php?mod=medicamentos&busqueda=${encodeURIComponent(valor)}`;
            }
        }

        // =========================
        // FLECHAS
        // =========================
        if (e.key === "ArrowDown") {
            e.preventDefault();
            selectedIndex++;
            if (selectedIndex >= items.length) selectedIndex = 0;
            actualizarSeleccion();
        }

        if (e.key === "ArrowUp") {
            e.preventDefault();
            selectedIndex--;
            if (selectedIndex < 0) selectedIndex = items.length - 1;
            actualizarSeleccion();
        }
    });

    // =========================
    // ESC
    // =========================
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {

            // 🔴 Cerrar buscador
            searchWrapper.classList.remove('active');
            searchInput.blur();

            if (searchResults) {
                searchResults.style.display = "none";
            }

            // 🔥 Cerrar dropdowns (USER INFO, NOTIFICACIONES, ETC)
            cerrarDropdowns();
        }
    });

    // =========================
    // CLICK FUERA
    // =========================
    document.addEventListener('click', (e) => {
        if (!searchWrapper.contains(e.target)) {
            searchWrapper.classList.remove('active');
            if (searchResults) searchResults.style.display = "none";
        }
    });

    // =========================
    // MOSTRAR HISTORIAL
    // =========================
    searchInput.addEventListener("focus", mostrarHistorial);

    function mostrarHistorial() {

        let historial = JSON.parse(localStorage.getItem("historial_busqueda")) || [];

        if (historial.length === 0) {
            searchResults.style.display = "none";
            return;
        }

        searchResults.innerHTML = `<div class="title">Búsquedas recientes</div>`;

        historial.forEach((h, index) => {
            searchResults.innerHTML += `
                <div class="item" data-index="${index}">
                    <div class="text">${h}</div>
                    <span class="delete-history" data-index="${index}">&times;</span>
                </div>
            `;
        });

        activarHistorial();
        searchResults.style.display = "block";
    }

    function activarHistorial() {
        items = Array.from(searchResults.querySelectorAll(".item"));
        selectedIndex = -1;

        items.forEach(item => {
            item.addEventListener("click", () => {
                const texto = item.querySelector(".text").innerText.trim();
                irBusqueda(texto);
            });
        });

        document.querySelectorAll(".delete-history").forEach(btn => {
            btn.addEventListener("click", function (e) {
                e.stopPropagation();

                let index = this.dataset.index;
                let historial = JSON.parse(localStorage.getItem("historial_busqueda")) || [];

                historial.splice(index, 1);
                localStorage.setItem("historial_busqueda", JSON.stringify(historial));

                mostrarHistorial();
            });
        });
    }

    function actualizarSeleccion() {
        items.forEach(i => i.classList.remove("active"));
        if (items[selectedIndex]) {
            items[selectedIndex].classList.add("active");
        }
    }

    function guardarHistorial(texto) {
        let historial = JSON.parse(localStorage.getItem("historial_busqueda")) || [];

        historial = historial.filter(h => h !== texto);
        historial.unshift(texto);

        if (historial.length > 5) historial.pop();

        localStorage.setItem("historial_busqueda", JSON.stringify(historial));
    }

    function irBusqueda(valor) {
        guardarHistorial(valor);
        window.location.href = `menuprincipal.php?mod=medicamentos&busqueda=${valor}`;
    }

    function ejecutarBusqueda() {
        const valor = searchInput.value.trim();
        if (valor !== '') {
            guardarHistorial(valor);
            window.location.href = `menuprincipal.php?mod=medicamentos&busqueda=${encodeURIComponent(valor)}`;
        }
    }
});