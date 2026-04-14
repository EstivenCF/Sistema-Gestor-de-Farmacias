<?php
/**
 * index.php - Archivo central para cargar todas las consultas
 * Ubicación: backend/queries/index.php
 */

// Incluir la conexión (ruta correcta)
require_once __DIR__ . '/../conexion.php';

// Incluir todas las clases de consultas
require_once __DIR__ . '/ClienteQueries.php';
require_once __DIR__ . '/ProductoQueries.php';
require_once __DIR__ . '/VentaQueries.php';
require_once __DIR__ . '/DescuentoQueries.php';
require_once __DIR__ . '/EstadisticasQueries.php';
require_once __DIR__ . '/ImpuestoQueries.php';
require_once __DIR__ . '/SeguroQueries.php';

// Crear instancias globales
$clienteQueries = new ClienteQueries($conexion);
$productoQueries = new ProductoQueries($conexion);
$ventaQueries = new VentaQueries($conexion);
$descuentoQueries = new DescuentoQueries($conexion);
$estadisticasQueries = new EstadisticasQueries($conexion);
$impuestoQueries = new ImpuestoQueries($conexion);
$seguroQueries = new SeguroQueries($conexion);

// Funciones helper para datos simples (opcional)
function obtenerSucursales() {
    global $conexion;
    try {
        $stmt = $conexion->query("SELECT id_sucursal, nombre FROM sucursales");
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [['id_sucursal' => 1, 'nombre' => 'Sucursal Principal']];
    }
}

function obtenerCondicionesPago() {
    global $conexion;
    try {
        $stmt = $conexion->query("SELECT id_condicion, nombre, dias_plazo FROM condicion_pago");
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [['id_condicion' => 1, 'nombre' => 'Contado', 'dias_plazo' => 0]];
    }
}

function obtenerMetodosPago() {
    global $conexion;
    try {
        $stmt = $conexion->query("SELECT id_metodo, nombre FROM metodos_pago");
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [
            ['id_metodo' => 1, 'nombre' => 'Efectivo'],
            ['id_metodo' => 2, 'nombre' => 'Tarjeta'],
            ['id_metodo' => 3, 'nombre' => 'Transferencia']
        ];
    }
}

function obtenerImpuestosActivos() {
    global $impuestoQueries;
    return $impuestoQueries->obtenerActivos();
}

function obtenerDescuentosActivos() {
    global $descuentoQueries;
    return $descuentoQueries->obtenerTodosActivos();
}

function obtenerAseguradoras() {
    global $seguroQueries;
    return $seguroQueries->obtenerAseguradoras();
}
?>