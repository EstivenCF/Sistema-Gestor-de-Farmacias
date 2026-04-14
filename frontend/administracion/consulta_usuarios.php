<?php
include(__DIR__ . "/../../backend/conexion.php");

$campo_default = 'nombre';

$campo = $_GET['campo'] ?? $campo_default;
$busqueda = trim($_GET['busqueda'] ?? '');

$campos_validos = [
    'id_usuario',
    'nombre',
    'usuario',
    'id_rol',
    'estado'
];

if (!in_array($campo, $campos_validos)) {
    $campo = $campo_default;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Usuarios</title>

    <style>
        /* Importación de Fuente */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Poppins", sans-serif;
        }

        /* Cambia el body para que no limite el contenido */
        body {
            color: #333;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 20px;
        }

        /* Alineación del encabezado */
        .header-consulta-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            width: 100%;
        }

        .titulo-con-icono {
            display: flex;
            align-items: center;
            color: #198754;
            /* Verde característico */
            font-size: 26px;
            font-weight: 600;
        }

        /* Botón de retorno estilo Outline */
        .btn-outline-retorno {
            background-color: transparent;
            color: #198754;
            border: 1px solid #198754;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-outline-retorno:hover {
            background-color: #198754;
            color: white;
        }

        /* El main-wrapper ahora debe ser fluido al 100% */
        .main-wrapper {
            width: 100%;
            /* Ocupa todo el ancho */
            max-width: 100%;
            /* Eliminamos el límite de 1500px */
            padding: 0;
            /* Quitamos padding extra para maximizar espacio */
        }

        /* Ajustamos el contenedor de la consulta */
        .consulta-container {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            width: 100%;
            /* Asegura que use todo el ancho del wrapper */
        }

        .consulta-container h2 {
            text-align: center;
            margin-top: 0;
            margin-bottom: 25px;
            /* Espacio después del título */
            color: #333;
            font-size: 24px;
            padding-bottom: 10px;
        }

        /* Opcional: Hacer que los filtros no se estiren demasiado en pantallas gigantes */
        .filtros {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 25px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        .filtros label {
            font-weight: bold;
            color: #555;
        }

        .filtros input[type="text"],
        .filtros select {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 12px;
            font-size: 14px;
            flex-grow: 1;
            /* Permite que los campos crezcan */
            min-width: 150px;
        }

        /* Estilo de la tabla */
        .tabla-resultado {
            overflow-x: auto;
        }

        /* Ajuste de la tabla para que se vea mejor en pantallas anchas */
        .tabla-resultado table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
            /* Mantiene el scroll horizontal en móviles */
        }

        .tabla-resultado th,
        .tabla-resultado td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .tabla-resultado thead {
            background-color: #e8e8e8;
        }

        .tabla-resultado tbody tr:hover {
            background-color: #f2f2f2;
            cursor: pointer;
        }

        #btnMostrar {
            padding: 10px 15px;
            width: 200px;
            height: 60px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: bold;
            color: white;
            background-color: #148c48;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        #btnMostrar:hover {
            background-color: #148344;
            transform: translateY(-3px);
        }

        /* --- PAGINACIÓN MÁS GRANDE --- */
        .pagination-mini {
            display: flex;
            align-items: center;
            gap: 25px;
            /* Más espacio */
            background: #ffffff;
            padding: 12px 25px;
            border-radius: 50px;
            border: 1px solid #eee;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .page-arrow {
            background: #f8f9fa;
            border: 1px solid #ddd;
            font-size: 24px;
            /* Flechas más grandes */
            color: #198754;
            cursor: pointer;
            width: 45px;
            /* Botón circular/cuadrado grande */
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.3s;
        }

        .page-arrow:hover:not(:disabled) {
            background-color: #198754;
            color: white;
            border-color: #198754;
            transform: scale(1.1);
        }

        .page-label {
            font-size: 16px;
            /* Texto "1 de X" más legible */
            font-weight: 600;
            color: #444;
        }

        .page-arrow:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* Texto central */
        .page-label {
            font-size: 14px;
            font-weight: 600;
            color: #666;
        }

        .footer-consulta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
        }

        /* Paginación alineada a la derecha */
        .paginacion {
            display: flex;
            justify-content: flex-end;
            flex-grow: 1;
        }

        .estado-punto {
            height: 8px;
            width: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        .estado-punto.verde {
            background-color: #28a745;
            box-shadow: 0 0 5px #28a745;
        }

        .estado-punto.rojo {
            background-color: #dc3545;
            box-shadow: 0 0 5px #dc3545;
        }

        .badge-rol {
            background: #eef2f7;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            color: #555;
            border: 1px solid #dce3eb;
        }

        /* Animación fade */
        .fade-in {
            opacity: 0;
            transform: translateY(5px);
            animation: fadeIn 0.35s ease forwards;
        }

        /* Animación suave de entrada para las filas */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fila-animada {
            animation: fadeInUp 0.4s ease forwards;
        }

        /* Efecto de "cristal" o carga suave sobre la tabla */
        .tabla-actualizando {
            opacity: 0.5;
            filter: blur(1px);
            transition: all 0.3s ease;
        }

        #tbodyUsuarios {
            transition: opacity 0.3s ease;
        }

        /* Transición para los botones de paginación */
        .page-arrow:active {
            transform: scale(0.9);
        }
    </style>
</head>

<body>

    <div class="main-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 header-consulta-flex">
            <div>
                <h2 class="mb-0 text-success titulo-con-icono">
                    <span class="material-symbols-rounded align-middle me-2">person_search</span>
                    Consulta de Usuarios
                </h2>
                <p class="text-muted mb-0">Explora la lista completa de usuarios, filtra por roles o verifica estados actuales.</p>
            </div>
            <div>
                <button type="button" class="btn-outline-retorno" onclick="window.location.href='menuprincipal.php?mod=usuarios'">
                    <span class="material-symbols-rounded align-middle me-1">arrow_back</span>
                    Gestión de Usuarios
                </button>
            </div>
        </div>
        <div class="consulta-container">

            <h2>Consulta de Usuarios</h2>

            <div class="filtros">
                <select id="campo">
                    <option value="id_usuario">ID</option>
                    <option value="nombre">Nombre</option>
                    <option value="usuario">Usuario</option>
                    <option value="id_rol">Rol</option>
                    <option value="estado">Estado</option>
                </select>

                <input type="text" id="buscar" placeholder="Buscar..." autocomplete="off">
            </div>

            <div class="tabla-resultado">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Sucursal</th>
                            <th>Activo</th>
                        </tr>
                    </thead>

                    <tbody id="tbodyUsuarios"></tbody>
                </table>
            </div>

            <div class="footer-consulta">

                <button id="btnMostrar" onclick="window.location.href='menuprincipal.php?mod=consulta_usuarios'">
                    Mostrar Todos
                </button>

                <div class="paginacion" id="paginacion"></div>

            </div>

        </div>
    </div>

    <script>
        const ajaxURL = "/sistema-gestor-de-farmacias/frontend/administracion/consulta_usuarios_ajax.php";
        let currentPage = 1;
        let timeout;

        document.getElementById("buscar").addEventListener("input", () => {
            clearTimeout(timeout);
            timeout = setTimeout(() => loadUsuariosAJAX(1), 300);
        });

        document.getElementById("campo").addEventListener("change", () => loadUsuariosAJAX(1));

        function loadUsuariosAJAX(page = 1) {
            currentPage = page;
            const campo = document.getElementById("campo").value;
            const busqueda = document.getElementById("buscar").value;
            const tbody = document.getElementById("tbodyUsuarios");

            // 1. Efecto visual de "Cargando" suavizado
            tbody.classList.add('tabla-actualizando');

            fetch(`${ajaxURL}?campo=${campo}&busqueda=${encodeURIComponent(busqueda)}&page=${page}`)
                .then(res => res.json())
                .then(json => {
                    // Pequeño retraso para que la transición se aprecie y no sea un parpadeo molesto
                    setTimeout(() => {
                        renderTabla(json.data);
                        renderPagination(json.page, json.pages);
                        tbody.classList.remove('tabla-actualizando');
                    }, 150);
                })
                .catch(err => {
                    console.error("Error:", err);
                    tbody.classList.remove('tabla-actualizando');
                });
        }

        function renderTabla(usuarios) {
            const tbody = document.getElementById("tbodyUsuarios");
            tbody.innerHTML = "";

            if (usuarios.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 40px;">No se encontraron resultados</td></tr>`;
                return;
            }

            usuarios.forEach((u, index) => {
                const tr = document.createElement("tr");
                tr.className = "fila-animada";
                // Escalonamos la animación de cada fila para un efecto más profesional
                tr.style.animationDelay = `${index * 0.03}s`;

                tr.innerHTML = `
            <td>${u.id_usuario}</td>
            <td style="font-weight: 500;">${u.nombre}</td>
            <td>${u.usuario}</td>
            <td><span class="badge-rol">${u.id_rol || 'N/A'}</span></td>
            <td>${u.id_sucursal || 'Principal'}</td>
            <td>
                <span class="estado-punto ${u.estado_texto.toLowerCase() === 'activo' ? 'verde' : 'rojo'}"></span>
                ${u.estado_texto}
            </td>
        `;
                tbody.appendChild(tr);
            });
        }

        function renderPagination(current, total) {
            const pag = document.getElementById("paginacion");
            pag.innerHTML = "";
            if (total <= 1) return;

            pag.innerHTML = `
        <div class="pagination-mini">
            <button class="page-arrow" onclick="loadUsuariosAJAX(${current - 1})" ${current <= 1 ? 'disabled' : ''}>
                <span class="material-symbols-rounded">chevron_left</span>
            </button>
            <span class="page-label">${current} De ${total}</span>
            <button class="page-arrow" onclick="loadUsuariosAJAX(${current + 1})" ${current >= total ? 'disabled' : ''}>
                <span class="material-symbols-rounded">chevron_right</span>
            </button>
        </div>
    `;
        }

        loadUsuariosAJAX(1);
    </script>

</body>

</html>