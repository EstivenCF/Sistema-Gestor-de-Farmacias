<?php
if (!isset($conexion)) {
    include_once __DIR__ . '/../../backend/conexion.php';
}

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-01-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$id_categoria = $_GET['id_categoria'] ?? '';

// CATEGORÍAS
$categorias = $conexion->query("SELECT * FROM categorias")->fetchAll(PDO::FETCH_ASSOC);

// QUERY DE RENTABILIDAD
$query = "
SELECT 
    m.nombre AS medicamento,
    SUM(dv.cantidad) AS cantidad_vendida,
    SUM(dv.cantidad * dv.precio_unitario) AS ingreso_total,
    SUM(dv.cantidad * COALESCE(dc.precio_unitario, 0)) AS costo_total,
    COALESCE(SUM(dd.cantidad * dd.precio_unitario), 0) AS devoluciones_total,
    (SUM(dv.cantidad * dv.precio_unitario) - SUM(dv.cantidad * COALESCE(dc.precio_unitario, 0))) AS margen_bruto,
    (SUM(dv.cantidad * dv.precio_unitario) - SUM(dv.cantidad * COALESCE(dc.precio_unitario, 0)) - COALESCE(SUM(dd.cantidad * dd.precio_unitario), 0)) AS rentabilidad_neta,
    CASE 
        WHEN SUM(dv.cantidad * dv.precio_unitario) = 0 THEN 0
        ELSE ((SUM(dv.cantidad * dv.precio_unitario) - SUM(dv.cantidad * COALESCE(dc.precio_unitario, 0))) / SUM(dv.cantidad * dv.precio_unitario)) * 100
    END AS margen_porcentual
FROM detalle_venta dv
INNER JOIN ventas v ON dv.id_venta = v.id_venta
INNER JOIN lotes l ON dv.id_lote = l.id_lote
INNER JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
LEFT JOIN (
    SELECT id_lote, AVG(precio_unitario) AS precio_unitario
    FROM detalle_compra
    GROUP BY id_lote
) dc ON dc.id_lote = l.id_lote
LEFT JOIN detalle_devolucion dd ON dd.id_lote = l.id_lote 
WHERE v.fecha BETWEEN :inicio AND :fin
GROUP BY m.nombre
ORDER BY rentabilidad_neta DESC";

$stmt = $conexion->prepare($query);
$params = [':inicio' => $fecha_inicio, ':fin' => $fecha_fin];
$stmt->execute($params);
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// KPIs
$total_ingresos = 0;
$total_costos = 0;
$total_rentabilidad = 0;
foreach ($resultados as $row) {
    $total_ingresos += $row['ingreso_total'];
    $total_costos += $row['costo_total'];
    $total_rentabilidad += $row['rentabilidad_neta'];
}

// TOP 5 PARA GRÁFICO
$top = array_slice($resultados, 0, 5);
$labels = [];
$data = [];
foreach ($top as $t) {
    $labels[] = $t['medicamento'];
    $data[] = round($t['rentabilidad_neta'], 2);
}
?>

<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />

<style>
    :root {
        --primary-font: 'Poppins', sans-serif;
    }

    /* Quitamos margin-left y width fijo para que no choque con el sidebar */
    .main-content-rentabilidad {
        background: #ececec;
        min-height: 100vh;
        padding: 20px;
        transition: all 0.3s ease;
    }

    /* Tarjetas KPI */
    .kpi-card {
        border: none;
        border-radius: 16px;
        background: #ffffff;
        border-left: 6px solid;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        height: 100%;
    }

    .kpi-card:hover {
        transform: translateY(-5px);
    }

    .icon-circle {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .kpi-ingresos {
        border-left-color: #10b981;
    }

    .kpi-costos {
        border-left-color: #ef4444;
    }

    .kpi-rentabilidad {
        border-left-color: #3b82f6;
    }

    /* Filtros y Contenedores */
    .glass-card {
        background: #ffffff;
        border-radius: 16px;
        border: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .form-control,
    .form-select {
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        padding: 0.6rem 1rem;
    }

    .btn-primary-custom {
        background: #3b82f6;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        color: white;
        padding: 10px 20px;
        transition: background 0.2s;
    }

    .btn-primary-custom:hover {
        color: white;
        background: #2563eb;
    }

    /* Tabla con Bordes Redondeados */
    .table-container {
        background: #ffffff;
        border-radius: 16px;
        /* Ajusta el radio a tu gusto */
        overflow: hidden;
        /* Esto es vital para que las esquinas de la tabla no sobresalgan */
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        border: none;
    }

    .table-custom {
        margin-bottom: 0;
        width: 100%;
        border-collapse: collapse;
    }

    .table-custom thead {
        background: #f8fafc;
        color: #64748b;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
    }

    .table-custom th,
    .table-custom td {
        padding: 16px;
        border: none;
        /* Quitamos bordes internos para un look más limpio */
    }

    .table-custom tbody tr {
        border-bottom: 1px solid #f1f5f9;
    }

    .table-custom tbody tr:last-child {
        border-bottom: none;
        /* Evita doble borde al final */
    }

    /* Estilo para las insignias dentro de la tabla */
    .badge-profit {
        padding: 5px 12px;
        border-radius: 12px;
        font-weight: 600;
    }
</style>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <div class="bg-success bg-opacity-10 p-3 rounded-4 me-3">
            <span class="material-symbols-rounded text-success fs-1">monitoring</span>
        </div>
        <div>
            <h2 class="fw-bold text-dark mb-0">Análisis Financiero</h2>
            <p class="text-muted">Control de rentabilidad por producto</p>
        </div>
    </div>

    <div class="card glass-card mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="mod" value="rentabilidad_medicamentos">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary">Desde</label>
                    <input type="date" name="fecha_inicio" class="form-control text-muted" value="<?= $fecha_inicio ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary">Hasta</label>
                    <input type="date" name="fecha_fin" class="form-control text-muted" value="<?= $fecha_fin ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary">Categoría</label>
                    <select name="id_categoria" class="form-select text-muted">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= $c['id_categoria'] ?>" <?= $id_categoria == $c['id_categoria'] ? 'selected' : '' ?>>
                                <?= $c['nombre'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary-custom w-100 d-flex align-items-center justify-content-center">
                        <span class="material-symbols-rounded me-2">filter_alt</span> Filtrar Datos
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card kpi-card kpi-ingresos p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold d-block mb-1 text-uppercase">Ingresos Brutos</span>
                        <h3 class="fw-bold mb-0 text-dark">$<?= number_format($total_ingresos, 2) ?></h3>
                    </div>
                    <div class="icon-circle bg-success bg-opacity-10 text-success">
                        <span class="material-symbols-rounded">account_balance_wallet</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card kpi-card kpi-costos p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold d-block mb-1 text-uppercase">Costos Totales</span>
                        <h3 class="fw-bold mb-0 text-danger">$<?= number_format($total_costos, 2) ?></h3>
                    </div>
                    <div class="icon-circle bg-danger bg-opacity-10 text-danger">
                        <span class="material-symbols-rounded">inventory_2</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card kpi-card kpi-rentabilidad p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold d-block mb-1 text-uppercase">Rentabilidad Neta</span>
                        <h3 class="fw-bold mb-0 <?= $total_rentabilidad >= 0 ? 'text-primary' : 'text-danger' ?>">
                            $<?= number_format($total_rentabilidad, 2) ?>
                        </h3>
                    </div>
                    <div class="icon-circle bg-primary bg-opacity-10 text-primary">
                        <span class="material-symbols-rounded">query_stats</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card glass-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold text-dark mb-0">Top 5 Medicamentos Más Rentables</h5>
                    <button onclick="exportTableToExcel('tabla')" class="btn btn-light btn-sm rounded-pill px-3">
                        <span class="material-symbols-rounded align-middle fs-6 me-1">file_download</span> Exportar
                    </button>
                </div>
                <div id="grafico" style="min-height: 350px;"></div>
            </div>
        </div>
    </div>

    <div class="card glass-card">
        <div class="card-body p-0">
            <div class="table-container">
                <div class="table-responsive">
                    <table id="tabla" class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th>Medicamento</th>
                                <th>Ventas</th>
                                <th>Ingresos</th>
                                <th>Costos</th>
                                <th>Rentabilidad</th>
                                <th>Margen %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados as $row): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= $row['medicamento'] ?></td>
                                    <td><span class="badge bg-light text-dark px-3 py-2 rounded-pill"><?= $row['cantidad_vendida'] ?> uds</span></td>
                                    <td class="text-secondary">$<?= number_format($row['ingreso_total'], 2) ?></td>
                                    <td class="text-secondary">$<?= number_format($row['costo_total'], 2) ?></td>
                                    <td>
                                        <span class="badge-profit <?= $row['rentabilidad_neta'] >= 0 ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger' ?>">
                                            $<?= number_format($row['rentabilidad_neta'], 2) ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold"><?= number_format($row['margen_porcentual'], 2) ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    var options = {
        chart: {
            type: 'bar',
            height: 350,
            fontFamily: 'Inter, sans-serif',
            toolbar: {
                show: false
            }
        },
        plotOptions: {
            bar: {
                borderRadius: 10,
                columnWidth: '40%',
                distributed: true,
                dataLabels: {
                    position: 'top'
                }
            }
        },
        colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
        series: [{
            name: 'Rentabilidad',
            data: <?= json_encode($data) ?>
        }],
        xaxis: {
            categories: <?= json_encode($labels) ?>,
            axisBorder: {
                show: false
            },
            axisTicks: {
                show: false
            }
        },
        grid: {
            borderColor: '#f1f5f9',
            strokeDashArray: 4
        },
        dataLabels: {
            enabled: true,
            formatter: (val) => "$" + val,
            offsetY: -25,
            style: {
                fontSize: '12px',
                fontWeight: 'bold',
                colors: ["#64748b"]
            }
        },
        legend: {
            show: false
        }
    };
    new ApexCharts(document.querySelector("#grafico"), options).render();

    function exportTableToExcel(tableID) {
        let table = document.getElementById(tableID);
        if (!table) {
            alert("Error: No se encontró la tabla para exportar.");
            return;
        }

        let html = table.outerHTML;
        // Esto ayuda a que Excel reconozca los caracteres especiales (tildes, Ñ, etc.)
        let blob = new Blob(['\ufeff', html], {
            type: 'application/vnd.ms-excel'
        });

        let url = URL.createObjectURL(blob);
        let link = document.createElement("a");
        link.href = url;
        link.download = 'Rentabilidad_PharmaSystem.xls'; // Es mejor .xls para este método

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>