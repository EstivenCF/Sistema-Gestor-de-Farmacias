<?php
require_once __DIR__ . '/../../backend/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_sesion'])) {
    header("Location: ../index.php");
    exit();
}

// Obtener entregas activas
$entregas_activas = [];

try {
    $query = "
        SELECT 
            e.id_entrega,
            e.numero_seguimiento,
            e.cliente_nombre,
            e.direccion_entrega,
            e.barrio_entrega,
            e.ciudad_entrega,
            e.latitud_entrega,
            e.longitud_entrega,
            e.estado,
            e.fecha_asignacion,
            r.nombre AS repartidor_nombre,
            r.id_repartidor
        FROM entregas e
        LEFT JOIN repartidores r ON e.id_repartidor = r.id_repartidor
        WHERE e.estado IN ('pendiente', 'asignada', 'en_camino')
          AND e.fecha_asignacion >= CURRENT_DATE - INTERVAL '1 day'
        ORDER BY e.fecha_asignacion DESC
        LIMIT 50
    ";
    $stmt = $conexion->prepare($query);
    $stmt->execute();
    $entregas_activas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $entregas_activas = [];
}

// Datos simulados si no hay reales (con íconos de muestra)
if (empty($entregas_activas)) {
    $entregas_activas = [
        [
            'id_entrega' => 101,
            'numero_seguimiento' => 'DEL-001',
            'cliente_nombre' => 'Juan Pérez',
            'direccion_entrega' => 'Av. 27 de Febrero #123',
            'barrio_entrega' => 'Gazcue',
            'ciudad_entrega' => 'Santo Domingo',
            'latitud_entrega' => 18.4738,
            'longitud_entrega' => -69.9404,
            'estado' => 'en_camino',
            'repartidor_nombre' => 'Carlos Gómez',
            'id_repartidor' => 1
        ],
        [
            'id_entrega' => 102,
            'numero_seguimiento' => 'DEL-002',
            'cliente_nombre' => 'María Rodríguez',
            'direccion_entrega' => 'Calle Del Sol #45',
            'barrio_entrega' => 'Los Jardines',
            'ciudad_entrega' => 'Santiago',
            'latitud_entrega' => 19.4511,
            'longitud_entrega' => -70.6970,
            'estado' => 'asignada',
            'repartidor_nombre' => 'Laura Martínez',
            'id_repartidor' => 2
        ],
        [
            'id_entrega' => 103,
            'numero_seguimiento' => 'DEL-003',
            'cliente_nombre' => 'Luis Fernández',
            'direccion_entrega' => 'Bulevar Turístico del Este',
            'barrio_entrega' => 'El Cortecito',
            'ciudad_entrega' => 'Punta Cana',
            'latitud_entrega' => 18.5825,
            'longitud_entrega' => -68.3954,
            'estado' => 'pendiente',
            'repartidor_nombre' => 'No asignado',
            'id_repartidor' => null
        ],
        [
            'id_entrega' => 104,
            'numero_seguimiento' => 'DEL-004',
            'cliente_nombre' => 'Ana Torres',
            'direccion_entrega' => 'Calle Beller #7',
            'barrio_entrega' => 'Centro',
            'ciudad_entrega' => 'Puerto Plata',
            'latitud_entrega' => 19.7967,
            'longitud_entrega' => -70.6935,
            'estado' => 'en_camino',
            'repartidor_nombre' => 'Pedro Sánchez',
            'id_repartidor' => 3
        ],
        [
            'id_entrega' => 105,
            'numero_seguimiento' => 'DEL-005',
            'cliente_nombre' => 'Sofía Ramírez',
            'direccion_entrega' => 'Av. Francisco del Rosario Sánchez',
            'barrio_entrega' => 'La Romana',
            'ciudad_entrega' => 'La Romana',
            'latitud_entrega' => 18.4247,
            'longitud_entrega' => -68.9724,
            'estado' => 'asignada',
            'repartidor_nombre' => 'Jorge Díaz',
            'id_repartidor' => 4
        ]
    ];
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
    .header-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .map-container {
        background: #f8f9fa;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    #map {
        height: 500px;
        width: 100%;
        z-index: 1;
    }
    .entregas-sidebar {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        padding: 0;
        height: 500px;
        overflow-y: auto;
    }
    .entregas-sidebar .list-group-item {
        border-left: none;
        border-right: none;
        cursor: pointer;
        transition: background 0.2s;
    }
    .entregas-sidebar .list-group-item:hover {
        background-color: rgba(40,167,69,0.05);
    }
    .entregas-sidebar .list-group-item.active {
        background-color: #28a745;
        border-color: #28a745;
        color: white;
    }
    .badge-estado {
        padding: 3px 8px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
    }
    .badge-pendiente { background-color: #ffc107; color: #000; }
    .badge-asignada { background-color: #17a2b8; color: #fff; }
    .badge-en_camino { background-color: #fd7e14; color: #fff; }
    .btn-refresh {
        background-color: #28a745;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 500;
    }
    .btn-refresh:hover {
        background-color: #1e7e34;
    }
    .info-entrega {
        font-size: 0.85rem;
    }
    .leaflet-popup-content {
        min-width: 200px;
    }
</style>

<div class="dashboard-container">
    <div class="header-actions">
        <div>
            <h2 class="mb-0 text-success">
                <span class="material-symbols-rounded align-middle me-2">location_on</span>
                Tracking de Entregas
            </h2>
            <p class="text-muted mb-0">Monitoreo en tiempo real de repartidores y entregas</p>
        </div>
        <div>
            <button type="button" class="btn btn-refresh" onclick="refrescarMapa()">
                <span class="material-symbols-rounded align-middle me-1">refresh</span>
                Actualizar
            </button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="entregas-sidebar">
                <div class="p-3 bg-light border-bottom">
                    <h6 class="mb-0"><strong>Entregas activas</strong> (<?php echo count($entregas_activas); ?>)</h6>
                </div>
                <div class="list-group list-group-flush" id="listaEntregas">
                    <?php foreach ($entregas_activas as $entrega): 
                        $estado = $entrega['estado'];
                        $badge_class = '';
                        switch($estado) {
                            case 'pendiente': $badge_class = 'badge-pendiente'; break;
                            case 'asignada': $badge_class = 'badge-asignada'; break;
                            case 'en_camino': $badge_class = 'badge-en_camino'; break;
                            default: $badge_class = 'badge-pendiente';
                        }
                        $estado_texto = ucfirst(str_replace('_', ' ', $estado));
                        $repartidor = $entrega['repartidor_nombre'] ?? 'No asignado';
                    ?>
                        <div class="list-group-item" data-id="<?php echo $entrega['id_entrega']; ?>" data-lat="<?php echo $entrega['latitud_entrega']; ?>" data-lng="<?php echo $entrega['longitud_entrega']; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong><?php echo htmlspecialchars($entrega['numero_seguimiento']); ?></strong>
                                <span class="badge-estado <?php echo $badge_class; ?>"><?php echo $estado_texto; ?></span>
                            </div>
                            <div class="info-entrega mt-2">
                                <div><small>Cliente: <?php echo htmlspecialchars($entrega['cliente_nombre']); ?></small></div>
                                <div><small>Repartidor: <?php echo htmlspecialchars($repartidor); ?></small></div>
                                <div><small>Destino: <?php echo htmlspecialchars($entrega['ciudad_entrega']); ?></small></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="map-container">
                <div id="map"></div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const BASE_URL = '<?php echo $base_url; ?>';
let map;
let markers = {};

const CENTRO_RD = [18.7357, -70.1627];
const ZOOM = 8;

const entregasData = <?php echo json_encode($entregas_activas); ?>;

// ========== ÍCONOS CORREGIDOS - SIN TEXTO "DELIVERY" ==========
const iconRepartidor = L.divIcon({
    html: '<div style="background-color:#28a745; border-radius:50%; width:32px; height:32px; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 5px rgba(0,0,0,0.3);"><span style="font-size:20px;">🛵</span></div>',
    iconSize: [32, 32],
    popupAnchor: [0, -16]
});

const iconPendiente = L.divIcon({
    html: '<div style="background-color:#ffc107; border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 5px rgba(0,0,0,0.2);"><span style="font-size:16px;">⏳</span></div>',
    iconSize: [28, 28]
});

const iconAsignada = L.divIcon({
    html: '<div style="background-color:#17a2b8; border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 5px rgba(0,0,0,0.2);"><span style="font-size:16px;">👤</span></div>',
    iconSize: [28, 28]
});

const iconEnCamino = L.divIcon({
    html: '<div style="background-color:#fd7e14; border-radius:50%; width:34px; height:34px; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,0.3);"><span style="font-size:22px;">🏍️</span></div>',
    iconSize: [34, 34]
});

function getIconForEstado(estado) {
    switch(estado) {
        case 'pendiente': return iconPendiente;
        case 'asignada': return iconAsignada;
        case 'en_camino': return iconEnCamino;
        default: return iconPendiente;
    }
}

function initMap() {
    map = L.map('map').setView(CENTRO_RD, ZOOM);
    
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> & CartoDB',
        subdomains: 'abcd',
        maxZoom: 19
    }).addTo(map);
    
    entregasData.forEach(entrega => {
        const lat = parseFloat(entrega.latitud_entrega);
        const lng = parseFloat(entrega.longitud_entrega);
        if (isNaN(lat) || isNaN(lng)) return;
        
        const estado = entrega.estado;
        const icon = getIconForEstado(estado);
        
        const popupContent = `
            <div style="min-width:200px;">
                <strong>${entrega.numero_seguimiento}</strong><br>
                Cliente: ${escapeHtml(entrega.cliente_nombre)}<br>
                Dirección: ${escapeHtml(entrega.direccion_entrega)}<br>
                Ciudad: ${escapeHtml(entrega.ciudad_entrega)}<br>
                Estado: ${estado}<br>
                Repartidor: ${escapeHtml(entrega.repartidor_nombre || 'No asignado')}<br>
                <a href="${BASE_URL}/frontend/menuprincipal.php?mod=entregas&id_entrega=${entrega.id_entrega}" target="_blank">Ver detalle</a>
            </div>
        `;
        
        const marker = L.marker([lat, lng], { icon: icon })
            .bindPopup(popupContent)
            .addTo(map);
        
        markers[entrega.id_entrega] = marker;
    });
    
    // Leyenda mejorada
    const legend = L.control({ position: 'bottomright' });
    legend.onAdd = function() {
        const div = L.DomUtil.create('div', 'info legend');
        div.style.backgroundColor = 'white';
        div.style.padding = '8px 12px';
        div.style.borderRadius = '8px';
        div.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
        div.innerHTML = `
            <strong>Leyenda</strong><br>
            ⏳ Pendiente<br>
            👤 Asignada<br>
            🏍️ En camino<br>
            🛵 Repartidor en ruta
        `;
        return div;
    };
    legend.addTo(map);
}

function refrescarMapa() {
    Swal.fire({
        title: 'Actualizando...',
        text: 'Obteniendo ubicaciones en tiempo real',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    setTimeout(() => {
        Swal.close();
        Swal.fire({
            icon: 'success',
            title: 'Actualizado',
            text: 'Las ubicaciones han sido actualizadas',
            timer: 1500,
            showConfirmButton: false
        });
        location.reload();
    }, 1000);
}

document.querySelectorAll('#listaEntregas .list-group-item').forEach(item => {
    item.addEventListener('click', function() {
        const lat = parseFloat(this.dataset.lat);
        const lng = parseFloat(this.dataset.lng);
        if (!isNaN(lat) && !isNaN(lng)) {
            map.setView([lat, lng], 14);
            const id = this.dataset.id;
            if (markers[id]) {
                markers[id].openPopup();
            }
        }
        document.querySelectorAll('#listaEntregas .list-group-item').forEach(el => el.classList.remove('active'));
        this.classList.add('active');
    });
});

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

document.addEventListener('DOMContentLoaded', function() {
    initMap();
});
</script>