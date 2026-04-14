<?php
/**
 * EstadisticasQueries.php - Consultas para estadísticas del dashboard
 */

class EstadisticasQueries {
    private $conexion;
    private $productoQueries;
    private $ventaQueries;
    
    public function __construct($conexion) {
        $this->conexion = $conexion;
        $this->productoQueries = new ProductoQueries($conexion);
        $this->ventaQueries = new VentaQueries($conexion);
    }
    
    /**
     * Obtener todas las estadísticas del dashboard
     */
    public function obtenerDashboardStats() {
        $ventas_hoy = $this->ventaQueries->obtenerConteoVentasHoy();
        $ventas_mes = $this->ventaQueries->obtenerVentasMes();
        
        return [
            'ventas_hoy' => $ventas_hoy['total'],
            'monto_hoy' => $ventas_hoy['monto'],
            'ventas_mes' => $ventas_mes['total'],
            'monto_mes' => $ventas_mes['monto'],
            'stock_bajo' => $this->productoQueries->obtenerStockBajo(),
            'por_vencer' => $this->productoQueries->obtenerPorVencer(30),
            'clientes_totales' => $this->obtenerTotalClientes(),
            'ventas_credito_pendientes' => $this->obtenerTotalCreditoPendiente()
        ];
    }
    
    /**
     * Obtener total de clientes registrados
     */
    public function obtenerTotalClientes() {
        try {
            $stmt = $this->conexion->query("SELECT COUNT(*) as total FROM clientes");
            $result = $stmt->fetch();
            return $result['total'] ?? 0;
        } catch(PDOException $e) {
            return 0;
        }
    }
    
    /**
     * Obtener total de crédito pendiente
     */
    public function obtenerTotalCreditoPendiente() {
        try {
            $stmt = $this->conexion->query("
                SELECT COALESCE(SUM(total - abonos_acumulados), 0) as total 
                FROM ventas 
                WHERE es_credito = TRUE AND estado_pago != 'PAGADO'
            ");
            $result = $stmt->fetch();
            return $result['total'] ?? 0;
        } catch(PDOException $e) {
            return 0;
        }
    }
    
    /**
     * Obtener ventas por mes (para gráficos)
     */
    public function obtenerVentasPorMes($anio = null) {
        if (!$anio) $anio = date('Y');
        try {
            $stmt = $this->conexion->prepare("
                SELECT 
                    DATE_PART('month', fecha) as mes,
                    COUNT(*) as cantidad,
                    COALESCE(SUM(total), 0) as monto
                FROM ventas
                WHERE DATE_PART('year', fecha) = ?
                GROUP BY DATE_PART('month', fecha)
                ORDER BY mes
            ");
            $stmt->execute([$anio]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Obtener productos más vendidos
     */
    public function obtenerProductosMasVendidos($limite = 10) {
        try {
            $stmt = $this->conexion->prepare("
                SELECT 
                    m.nombre as medicamento,
                    m.id_medicamento,
                    SUM(dv.cantidad) as cantidad_vendida,
                    COUNT(DISTINCT dv.id_venta) as numero_ventas,
                    SUM(dv.subtotal) as total_ventas
                FROM detalle_venta dv
                JOIN lotes l ON dv.id_lote = l.id_lote
                JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
                WHERE dv.id_lote IS NOT NULL
                GROUP BY m.id_medicamento, m.nombre
                ORDER BY cantidad_vendida DESC
                LIMIT ?
            ");
            $stmt->execute([$limite]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Obtener estadísticas de impuestos
     */
    public function obtenerEstadisticasImpuestos($fecha_inicio, $fecha_fin) {
        try {
            $stmt = $this->conexion->prepare("
                SELECT 
                    i.nombre as impuesto_nombre,
                    i.codigo,
                    i.porcentaje,
                    SUM(dv.impuesto_unitario) as total_impuesto
                FROM detalle_venta dv
                JOIN ventas v ON dv.id_venta = v.id_venta
                JOIN impuestos i ON i.id_impuesto = (
                    SELECT id_impuesto FROM medicamentos m 
                    JOIN lotes l ON m.id_medicamento = l.id_medicamento
                    WHERE l.id_lote = dv.id_lote
                    UNION ALL
                    SELECT id_impuesto FROM productos p
                    WHERE p.id_producto = dv.id_producto
                    LIMIT 1
                )
                WHERE v.fecha BETWEEN ? AND ?
                GROUP BY i.id_impuesto, i.nombre, i.codigo, i.porcentaje
                ORDER BY total_impuesto DESC
            ");
            $stmt->execute([$fecha_inicio, $fecha_fin]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Obtener estadísticas de seguros
     */
    public function obtenerEstadisticasSeguros($fecha_inicio, $fecha_fin) {
        try {
            $stmt = $this->conexion->prepare("
                SELECT 
                    a.nombre as aseguradora,
                    COUNT(DISTINCT p.id_pago) as cantidad_pagos,
                    COALESCE(SUM(p.monto_seguro), 0) as total_cubierto,
                    COUNT(DISTINCT v.id_cliente) as clientes_atendidos
                FROM pagos p
                JOIN ventas v ON p.id_venta = v.id_venta
                JOIN aseguradoras a ON p.id_aseguradora = a.id_aseguradora
                WHERE p.monto_seguro > 0 AND v.fecha BETWEEN ? AND ?
                GROUP BY a.id_aseguradora, a.nombre
                ORDER BY total_cubierto DESC
            ");
            $stmt->execute([$fecha_inicio, $fecha_fin]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Obtener estadísticas de descuentos
     */
    public function obtenerEstadisticasDescuentos($fecha_inicio, $fecha_fin) {
        try {
            $stmt = $this->conexion->prepare("
                SELECT 
                    d.nombre as descuento_nombre,
                    COUNT(vd.id_venta_descuento) as veces_usado,
                    COALESCE(SUM(vd.monto_descuento), 0) as total_descontado
                FROM venta_descuento vd
                JOIN descuentos d ON vd.id_descuento = d.id_descuento
                JOIN ventas v ON vd.id_venta = v.id_venta
                WHERE v.fecha BETWEEN ? AND ?
                GROUP BY d.id_descuento, d.nombre
                ORDER BY veces_usado DESC
            ");
            $stmt->execute([$fecha_inicio, $fecha_fin]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
}
?>