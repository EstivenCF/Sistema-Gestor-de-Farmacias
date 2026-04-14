<?php
/**
 * VentaQueries.php - Consultas relacionadas con ventas (actualizado con impuestos y seguros)
 */

class VentaQueries {
    private $conexion;
    private $descuentoQueries;
    private $seguroQueries;
    private $impuestoQueries;
    
    public function __construct($conexion) {
        $this->conexion = $conexion;
        $this->descuentoQueries = new DescuentoQueries($conexion);
        $this->seguroQueries = new SeguroQueries($conexion);
        $this->impuestoQueries = new ImpuestoQueries($conexion);
    }
    
    /**
     * Obtener número de ventas del día
     */
    public function obtenerConteoVentasHoy() {
        try {
            $stmt = $this->conexion->query("
                SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as monto 
                FROM ventas WHERE DATE(fecha) = CURRENT_DATE
            ");
            return $stmt->fetch();
        } catch(PDOException $e) {
            return ['total' => 0, 'monto' => 0];
        }
    }
    
    /**
     * Obtener ventas del mes
     */
    public function obtenerVentasMes() {
        try {
            $stmt = $this->conexion->query("
                SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as monto 
                FROM ventas 
                WHERE DATE_PART('month', fecha) = DATE_PART('month', CURRENT_DATE)
                  AND DATE_PART('year', fecha) = DATE_PART('year', CURRENT_DATE)
            ");
            return $stmt->fetch();
        } catch(PDOException $e) {
            return ['total' => 0, 'monto' => 0];
        }
    }
    
    /**
     * Generar nuevo número de documento
     */
    public function generarNumeroDocumento() {
        try {
            $stmt = $this->conexion->query("
                SELECT COUNT(*) as total FROM ventas WHERE DATE(fecha) = CURRENT_DATE
            ");
            $row = $stmt->fetch();
            $numero = ($row['total'] ?? 0) + 1;
            return 'FAC-' . date('Ymd') . '-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
        } catch(PDOException $e) {
            return 'FAC-' . date('Ymd') . '-0001';
        }
    }
    
    /**
     * Calcular impuestos para un detalle de venta
     */
    public function calcularImpuestosDetalle($id_producto, $id_medicamento, $precio, $cantidad, $requiere_receta = false) {
        $subtotal = $precio * $cantidad;
        $impuesto_unitario = 0;
        $impuesto_total = 0;
        
        // Buscar impuesto aplicable
        if ($id_medicamento) {
            $impuesto = $this->impuestoQueries->obtenerImpuestoMedicamento($id_medicamento, $requiere_receta);
        } else {
            $impuesto = $this->impuestoQueries->obtenerImpuestoProducto($id_producto);
        }
        
        if ($impuesto && $impuesto['porcentaje'] > 0) {
            $impuesto_unitario = $precio * ($impuesto['porcentaje'] / 100);
            $impuesto_total = $impuesto_unitario * $cantidad;
        }
        
        return [
            'impuesto_unitario' => $impuesto_unitario,
            'impuesto_total' => $impuesto_total,
            'id_impuesto' => $impuesto ? $impuesto['id_impuesto'] : null
        ];
    }
    
    /**
     * Calcular totales de una venta (subtotal, descuentos, impuestos, total)
     */
    public function calcularTotalesVenta($items, $id_cliente = null, $descuentos_aplicar = []) {
        $subtotal = 0;
        $impuesto_total = 0;
        $detalles = [];
        
        foreach ($items as $item) {
            $subtotal_item = $item['precio'] * $item['cantidad'];
            $subtotal += $subtotal_item;
            
            // Calcular impuestos
            $impuestos = $this->calcularImpuestosDetalle(
                $item['id_producto'] ?? null,
                $item['id_medicamento'] ?? null,
                $item['precio'],
                $item['cantidad'],
                $item['requiere_receta'] ?? false
            );
            
            $impuesto_total += $impuestos['impuesto_total'];
            
            $detalles[] = [
                'item' => $item,
                'subtotal_item' => $subtotal_item,
                'impuesto_unitario' => $impuestos['impuesto_unitario'],
                'impuesto_total_item' => $impuestos['impuesto_total'],
                'id_impuesto' => $impuestos['id_impuesto']
            ];
        }
        
        // Calcular descuentos aplicables
        $descuentos = [];
        $descuento_total = 0;
        
        if ($id_cliente && !empty($descuentos_aplicar)) {
            foreach ($descuentos_aplicar as $id_descuento) {
                $monto = $this->descuentoQueries->calcularMontoDescuento($id_descuento, $subtotal);
                if ($monto > 0) {
                    $descuentos[] = [
                        'id_descuento' => $id_descuento,
                        'monto' => $monto
                    ];
                    $descuento_total += $monto;
                }
            }
        }
        
        // Aplicar límite máximo de descuento (no puede superar el subtotal)
        $descuento_total = min($descuento_total, $subtotal);
        
        $total = $subtotal - $descuento_total + $impuesto_total;
        
        return [
            'subtotal' => $subtotal,
            'descuento_total' => $descuento_total,
            'impuesto_total' => $impuesto_total,
            'total' => $total,
            'detalles' => $detalles,
            'descuentos' => $descuentos
        ];
    }
    
    /**
     * Insertar nueva venta
     */
    public function insertarVenta($data) {
        try {
            $stmt = $this->conexion->prepare("
                INSERT INTO ventas (
                    numero_documento, id_usuario, id_cliente, id_condicion, id_sucursal,
                    subtotal, descuento_total, impuesto_total, total, fecha,
                    es_credito, fecha_vencimiento_pago, estado_pago, usa_seguro,
                    monto_cubre_seguro, monto_paga_paciente
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?, ?, ?)
                RETURNING id_venta
            ");
            $stmt->execute([
                $data['numero_documento'],
                $data['id_usuario'],
                $data['id_cliente'],
                $data['id_condicion'],
                $data['id_sucursal'],
                $data['subtotal'],
                $data['descuento_total'],
                $data['impuesto_total'],
                $data['total'],
                $data['es_credito'] ?? false,
                $data['fecha_vencimiento_pago'] ?? null,
                $data['estado_pago'] ?? 'PENDIENTE',
                $data['usa_seguro'] ?? false,
                $data['monto_cubre_seguro'] ?? 0,
                $data['monto_paga_paciente'] ?? $data['total']
            ]);
            $result = $stmt->fetch();
            return $result['id_venta'];
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Insertar detalle de venta
     */
    public function insertarDetalleVenta($id_venta, $id_lote, $id_producto, $cantidad, $precio_unitario, $descuento_unitario = 0, $impuesto_unitario = 0) {
        try {
            $subtotal = ($precio_unitario - $descuento_unitario) * $cantidad;
            $stmt = $this->conexion->prepare("
                INSERT INTO detalle_venta (
                    id_venta, id_lote, id_producto, cantidad, precio_unitario,
                    descuento_unitario, impuesto_unitario, subtotal
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([
                $id_venta, $id_lote, $id_producto, $cantidad, $precio_unitario,
                $descuento_unitario, $impuesto_unitario, $subtotal
            ]);
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Insertar pago
     */
    public function insertarPago($id_venta, $id_metodo, $monto) {
        try {
            $stmt = $this->conexion->prepare("
                INSERT INTO pagos (id_venta, id_metodo, monto) VALUES (?, ?, ?)
            ");
            return $stmt->execute([$id_venta, $id_metodo, $monto]);
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Insertar pago con seguro
     */
    public function insertarPagoConSeguro($id_venta, $id_metodo, $monto_paciente, $monto_seguro, $id_aseguradora, $id_autorizacion = null) {
        try {
            $stmt = $this->conexion->prepare("
                INSERT INTO pagos (
                    id_venta, id_metodo, monto, id_aseguradora, id_autorizacion,
                    monto_seguro, monto_paciente, estado_seguro
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDIENTE')
            ");
            return $stmt->execute([
                $id_venta, $id_metodo, $monto_paciente + $monto_seguro,
                $id_aseguradora, $id_autorizacion, $monto_seguro, $monto_paciente
            ]);
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Insertar descuento aplicado a venta
     */
    public function insertarDescuentoVenta($id_venta, $id_descuento, $monto, $id_usuario) {
        try {
            $stmt = $this->conexion->prepare("
                INSERT INTO venta_descuento (id_venta, id_descuento, monto_descuento, id_usuario)
                VALUES (?, ?, ?, ?)
            ");
            return $stmt->execute([$id_venta, $id_descuento, $monto, $id_usuario]);
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Insertar acumulación de puntos
     */
    public function insertarAcumulacionPuntos($id_venta, $id_cliente, $puntos) {
        try {
            $stmt = $this->conexion->prepare("
                INSERT INTO acumulacion_puntos (id_venta, id_cliente, puntos_ganados)
                VALUES (?, ?, ?)
            ");
            return $stmt->execute([$id_venta, $id_cliente, $puntos]);
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Obtener información adicional de venta
     */
    public function obtenerInfoAdicional($id_venta, $id_condicion, $id_metodo_pago) {
        try {
            $info = [];
            
            // Condición de pago
            $stmt = $this->conexion->prepare("SELECT nombre FROM condicion_pago WHERE id_condicion = ?");
            $stmt->execute([$id_condicion]);
            $info['condicion_pago'] = $stmt->fetch()['nombre'] ?? '';
            
            // Método de pago
            $stmt = $this->conexion->prepare("SELECT nombre FROM metodos_pago WHERE id_metodo = ?");
            $stmt->execute([$id_metodo_pago]);
            $info['metodo_pago'] = $stmt->fetch()['nombre'] ?? 'Efectivo';
            
            return $info;
        } catch(PDOException $e) {
            return ['condicion_pago' => '', 'metodo_pago' => 'Efectivo'];
        }
    }
    
    /**
     * Obtener ventas a crédito pendientes
     */
    public function obtenerVentasCreditoPendientes() {
        try {
            $stmt = $this->conexion->query("
                SELECT * FROM vista_ventas_credito_pendientes
                ORDER BY fecha_vencimiento_pago ASC
            ");
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Registrar abono a venta a crédito
     */
    public function registrarAbono($id_venta, $monto, $id_metodo_pago, $referencia, $id_usuario) {
        try {
            $stmt = $this->conexion->prepare("
                INSERT INTO abonos_credito (id_venta, monto, id_metodo_pago, referencia, creado_por)
                VALUES (?, ?, ?, ?, ?)
            ");
            return $stmt->execute([$id_venta, $monto, $id_metodo_pago, $referencia, $id_usuario]);
        } catch(PDOException $e) {
            return false;
        }
    }
}
?>